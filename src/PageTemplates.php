<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Filesystem\Url;

/**
 * Page templates (model from KLXM Studio – Core\PageTemplates): templates are normal pages in the offline category
 * „Seitenvorlagen“ – invisible to visitors (offline: no menu, no sitemap, 404), edited in the normal block editor.
 * When creating a page, editors choose „Leere Seite“, „Kopie dieser Seite“ or a template and get a copy of the
 * blocks (from the template's working version – i.e. as it was last edited).
 */
final class PageTemplates
{
    private const string CONFIG = 'templates_category';

    /** Category of the templates (created when the first template is saved) */
    public static function categoryId(bool $create = false): ?int
    {
        $id = (int) FluidAddon::instance()->getConfig(self::CONFIG, 0);
        if ($id && Category::get($id)) {
            return $id;
        }
        if (!$create) {
            return null;
        }
        $id = CategoryHandler::add(['parent_id' => null, 'priority' => 9999, 'name' => 'Seitenvorlagen', 'status' => 0])->id;
        FluidAddon::instance()->setConfig(self::CONFIG, $id);

        return $id;
    }

    /** @return list<array{id: int, name: string, url: string}> url = editing on the website */
    public static function all(int $languageId): array
    {
        $cat = self::categoryId() ? Category::get((int) self::categoryId(), $languageId) : null;
        if (!$cat) {
            return [];
        }

        return array_values(array_map(static fn (Article $a) => ['id' => $a->id, 'name' => $a->name, 'url' => Url::article($a->id, $languageId, [Editing::PARAM => 1])],
            array_filter($cat->getArticles(false), static fn (Article $a) => !$a->isStartArticle())));
    }

    /** Rename a template (only pages in „Seitenvorlagen“, not the category itself) */
    public static function rename(int $id, string $name, int $languageId): void
    {
        $a = self::template($id, $languageId);
        $name = trim($name);
        if ('' === $name) {
            throw new ApiError('Bitte einen Namen eingeben.');
        }
        ArticleHandler::edit($a->id, $languageId, ['name' => mb_substr($name, 0, 191), 'template' => (string) $a->templateKey]);
    }

    /** Delete a template – existing pages created from it stay untouched (they have a copy) */
    public static function delete(int $id, int $languageId): string
    {
        $a = self::template($id, $languageId);
        ArticleHandler::delete($a->id);

        return $a->name;
    }

    private static function template(int $id, int $languageId): Article
    {
        $a = Article::get($id, $languageId);
        if (null === $a || $a->isStartArticle() || $a->categoryId !== self::categoryId()) {
            throw new ApiError('Vorlage nicht gefunden.');
        }

        return $a;
    }

    /** Save the current page as a template; returns the template's ID */
    public static function saveFrom(Article $article, string $name): int
    {
        $cat = (int) self::categoryId(true);
        $id = ArticleHandler::add(['category_id' => $cat, 'priority' => 999, 'name' => trim($name) ?: $article->name, 'template' => (string) $article->templateKey])->id;   // newly created pages are offline
        self::copy($article->id, $id, $article->languageId);

        return $id;
    }

    /** Copy the blocks of a page (working version) to another – into working and live version */
    public static function copy(int $fromId, int $toId, int $languageId): void
    {
        $work = FluidAddon::revision();
        ContentHandler::copyContent($fromId, $toId, $languageId, $languageId, $work);
        if (ArticleRevision::LIVE !== $work) {
            ArticleRevision::copyContent($toId, $languageId, $work, ArticleRevision::LIVE);
        }
        ArticleCache::delete($toId, $languageId);
    }

    public static function isTemplate(int $articleId): bool
    {
        $cat = self::categoryId();

        return null !== $cat && ($articleId === $cat || Article::get($articleId)?->categoryId === $cat);
    }
}
