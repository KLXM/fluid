<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Routing\Routes;
use Klxm\NavigationArray\NavigationMeta;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\User;

use function in_array;

/**
 * Sidebar „Seiten“ on the website (model from KLXM Studio – page tree): all pages as a tree, sort and move by
 * dragging, online/offline, „im Menü“ (REDAXO: category ↔ article), delete, new page at a position.
 *
 * Menu items (categories) come before pages without menu entry (articles) on each level – both groups have their own
 * order in REDAXO. Permissions: structure permission of the respective category (admins may do everything).
 *
 * @internal
 */
final class Structure
{
    /**
     * Menu item „nicht im Menü“: meta field „Nicht in der Navigation zeigen“ of navigation_array (column
     * meta_nav_hidden of the category, for all languages) – position, subpages and status remain, the navigation omits
     * the category. In the backend under category → metadata.
     */
    public static function isHidden(int $categoryId): bool
    {
        $cat = Category::get($categoryId);

        return null !== $cat && NavigationMeta::of($cat)->navHidden;
    }

    private static function setHidden(int $categoryId, int $languageId, bool $hidden): void
    {
        CategoryHandler::edit($categoryId, $languageId, [], ['nav_hidden' => $hidden]);
    }

    /** @return list<array<string, mixed>> */
    public static function tree(int $languageId): array
    {
        $home = Article::getSiteStartArticleId();
        $types = ArticleHandler::statusTypes();   // 0 offline, 1 online, weitere von AddOns (z. B. gesperrt)
        $node = static function (Article $a, bool $menu, array $children = []) use ($home, $types): array {
            $status = (int) $a->getValue('status');

            return ['id' => $a->id, 'name' => $a->name, 'url' => $a->getUrl(), 'online' => $a->isOnline(), 'menu' => $menu,
                'status' => $status, 'statusLabel' => (string) ($types[$status][0] ?? ''),
                'hidden' => $menu && self::isHidden($a->id),
                'home' => $a->id === $home, 'blog' => $menu && Blog\Blog::isBlog($a->id), 'parent' => $a->categoryId && !$menu ? $a->categoryId : ($menu ? (Category::get($a->id)->parentId ?? 0) : 0),
                'children' => $children];
        };
        $walk = static function (?Category $cat) use (&$walk, $node, $languageId): array {
            $out = [];
            $cats = $cat ? $cat->getChildren(false) : Category::getRootCategories(false, $languageId);
            foreach ($cats as $c) {
                if (PageTemplates::categoryId() === $c->id) {
                    continue;   // Seitenvorlagen: eigene Liste im Dialog „Neue Seite“
                }
                $start = Article::get($c->id, $languageId);
                if ($start) {
                    $out[] = $node($start, true, $walk($c));
                }
            }
            $arts = $cat ? $cat->getArticles(false) : Article::getRootArticles(false, $languageId);
            foreach ($arts as $a) {
                if (!$a->isStartArticle()) {
                    $out[] = $node($a, false);
                }
            }

            return $out;
        };

        return $walk(null);
    }

    public static function handle(string $action, User $user, int $languageId): array
    {
        $id = Request::post('id', 'int', 0);
        $msg = '';
        if ('tree' !== $action) {
            $a = Article::get($id, $languageId) ?? throw new ApiError('Seite nicht gefunden.');
            self::requirePerm($user, $a->isStartArticle() && $a->categoryId === $a->id ? (Category::get($a->id)?->parentId) : $a->categoryId);
            $isCat = null !== Category::get($a->id) && $a->isStartArticle();
            Perm::require($user, match ($action) {
                'tree_move' => Perm::forPage($a, 'move'),
                'tree_status' => Perm::forPage($a, 'publish'),
                'tree_menu' => 'article2category[]',
                default => Perm::forPage($a, 'delete'),
            });
            switch ($action) {
                case 'tree_move':
                    $msg = self::move($a, $isCat, Request::post('parent', 'int', 0) ?: null, max(0, Request::post('index', 'int', 0)), $user, $languageId);
                    break;
                case 'tree_status':
                    $on = !$a->isOnline();
                    $isCat ? CategoryHandler::changeStatus($a->id, $languageId, $on ? 1 : 0) : ArticleHandler::changeStatus($a->id, $languageId, $on ? 1 : 0);
                    $msg = '„' . $a->name . '“ ist jetzt ' . ($on ? 'online.' : 'offline.');
                    break;
                case 'tree_menu':
                    if ($isCat) {
                        // Menu item: only hide from the menu or show again – position, subpages and status remain
                        $hide = !self::isHidden($a->id);
                        self::setHidden($a->id, $languageId, $hide);
                        $msg = $hide ? '„' . $a->name . '“ erscheint nicht mehr im Menü – die Seite bleibt online und an ihrer Stelle.' : '„' . $a->name . '“ steht wieder im Menü.';
                    } else {
                        // Page without menu entry: menu items are categories in REDAXO
                        ArticleHandler::convertToCategory($a->id);
                        $msg = '„' . $a->name . '“ steht jetzt im Menü.';
                    }
                    break;
                case 'tree_delete':
                    if ($a->id === Article::getSiteStartArticleId() || $a->id === Article::getNotfoundArticleId()) {
                        throw new ApiError('Startseite und Seite „nicht gefunden“ lassen sich nicht löschen.');
                    }
                    $isCat ? CategoryHandler::delete($a->id) : ArticleHandler::delete($a->id);
                    $msg = '„' . $a->name . '“ gelöscht.';
                    break;
            }
            Routes::rebuild();
        }

        return ['tree' => self::tree($languageId), 'message' => $msg];
    }

    /** Dragging: new parent page (null = top level) and position within its own group (menu items or pages) */
    private static function move(Article $a, bool $isCat, ?int $parent, int $index, User $user, int $languageId): string
    {
        if (null !== $parent && !Category::get($parent)) {
            throw new ApiError('Hier lassen sich keine Unterseiten anlegen – die Zielseite steht nicht im Menü.');
        }
        self::requirePerm($user, $parent);
        if ($isCat) {
            $cat = Category::get($a->id) ?? throw new ApiError('Seite nicht gefunden.');
            if (null !== $parent && ($parent === $a->id || in_array($a->id, Category::get($parent)->path ?? [], true))) {
                throw new ApiError('Eine Seite kann nicht unter sich selbst stehen.');
            }
            if ($parent !== $cat->parentId) {
                CategoryHandler::move($a->id, $parent);   // throws on errors
            }
            CategoryHandler::edit($a->id, $languageId, ['priority' => $index + 1]);
        } else {
            if ($parent !== $a->categoryId) {
                ArticleHandler::move($a->id, $a->categoryId, $parent);
            }
            $fresh = Article::get($a->id, $languageId) ?? $a;
            ArticleHandler::edit($a->id, $languageId, ['name' => $fresh->name, 'priority' => $index + 1, 'template' => $fresh->templateKey]);
        }

        return '„' . $a->name . '“ verschoben.';
    }

    private static function requirePerm(User $user, ?int $categoryId): void
    {
        if ($user->admin) {
            return;
        }
        $perm = $user->getComplexPerm('structure');
        if (!$perm instanceof StructurePermission || !$perm->hasCategoryPerm($categoryId)) {
            throw new ApiError('Keine Berechtigung für diesen Bereich der Seitenstruktur.');
        }
    }
}
