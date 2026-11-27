<?php

namespace Klxm\Fluid\Picker;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Security\User;

/**
 * Page picker on the website (FrontendPicker): categories and articles of the structure, like the core's linkmap.
 * Without the permission `linkmap[all_categories]` the user sees only their mount points (as the top level).
 */
final class ArticleTree
{
    /**
     * Pages in structure order. `parent`: category the page is in (0 = top level);
     * `cat`: start article of a category – it carries the subcategories and articles.
     *
     * @return list<array{id: int, name: string, path: string, online: bool, parent: int, cat: bool}>
     */
    public static function all(User $user, int $languageId): array
    {
        $perm = $user->getComplexPerm('structure');
        $mountpoints = !$user->hasPerm('linkmap[all_categories]') && $perm instanceof StructurePermission ? $perm->getMountpointCategories() : [];

        $out = [];
        $add = static function (Article $a, string $path, int $parent, bool $cat) use (&$out): void {
            $out[] = ['id' => $a->id, 'name' => $a->name, 'path' => $path, 'online' => $a->isOnline(), 'parent' => $parent, 'cat' => $cat];
        };
        $walk = static function (array $cats, string $path, int $parent) use (&$walk, $add): void {
            foreach ($cats as $c) {
                /** @var Category $c */
                $p = '' === $path ? $c->name : $path . ' › ' . $c->name;
                foreach ($c->getArticles() as $a) {
                    $a->isStartArticle() ? $add($a, $p, $parent, true) : $add($a, $p . ' › ' . $a->name, $c->id, false);
                }
                $walk($c->getChildren(), $p, $c->id);
            }
        };

        if ($mountpoints) {
            $walk(array_values(array_filter(array_map(static fn (Category $c) => Category::get($c->id, $languageId), $mountpoints))), '', 0);

            return $out;
        }
        foreach (Article::getRootArticles(false, $languageId) as $a) {
            $add($a, 'Hauptebene', 0, false);
        }
        $walk(Category::getRootCategories(false, $languageId), '', 0);

        return $out;
    }
}
