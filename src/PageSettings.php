<?php

namespace Klxm\Fluid;

use Klxm\Routes\AddressOptions;
use Klxm\Routes\ExtensionPoint\ArticleMeta;
use Klxm\Routes\Pages;
use Klxm\Routes\PageSettings as RoutesPageSettings;
use Klxm\Routes\Router;
use Klxm\Routes\Routes;
use Klxm\Routes\Seo\Head;
use Klxm\Routes\Seo\PageMeta;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\MediaPool\Media;

/**
 * Page settings on the website: title and online status (core) plus the SEO settings of klxm/routes ({@see Pages}:
 * title, description, image, noindex/nofollow, canonical, page as redirect, sitemap, page type).
 */
final class PageSettings
{
    /** Old table (until 1.0.0-beta.16) – taken over by the migration into rex_routes_page */
    public const string TABLE = 'rex_fluid_page';

    /** Is the page set to „nicht indexieren“? */
    public static function noindex(int $articleId, int $languageId): bool
    {
        return Pages::get($articleId, $languageId)->noindex;
    }

    /** @return array{description: string, image: string, noindex: bool, title: string} */
    public static function get(int $articleId, int $languageId): array
    {
        $s = Pages::get($articleId, $languageId);

        return ['description' => $s->description, 'image' => $s->image, 'noindex' => $s->noindex, 'title' => $s->title];
    }

    /**
     * Overrides the page head data from an output – e.g. a block showing an entry:
     * PageSettings::override(['title' => …, 'description' => …, 'image' => 'datei.jpg', 'canonical' => 'https://…']).
     * Must happen while rendering the blocks (the template renders the content before the head). Shorthand for Seo\Head::override().
     */
    public static function override(array $values): void
    {
        Head::override($values);
    }

    /**
     * @internal Defaults of the website settings for the page head: name of the website, description if the page has
     * none (the page's own settings are applied by klxm/routes)
     */
    #[AsExtension(level: ExtensionLevel::Late)]
    public static function articleMeta(ArticleMeta $ep): PageMeta
    {
        $m = $ep->subject;
        if (Router::site()->isMain()) {
            $m->siteName = Settings::name();
        }
        if (null === $m->description || '' === $m->description) {
            $m->description = trim((string) Settings::get('default_meta_description', '')) ?: null;
        }

        return $m;
    }

    /** Title in the browser tab and in search results: custom SEO title or „Seite – Website“ (start page: website) */
    public static function title(Article $article, string $siteName = ''): string
    {
        return Head::title($article);
    }

    /**
     * Saves title, status and settings.
     *
     * @param array<string, mixed> $seo further SEO settings (nofollow, canonical, redirect, redirectCode, sitemap, type)
     */
    public static function save(Article $article, string $name, bool $online, string $description, string $image, bool $noindex, string $title = '', ?string $slug = null, ?AddressOptions $address = null, array $seo = []): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ApiError('Bitte einen Titel angeben.');
        }
        if ($name !== $article->name) {
            ArticleHandler::edit($article->id, $article->languageId, ['name' => $name, 'template' => $article->templateKey]);
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::edit($article->categoryId, $article->languageId, ['name' => $name]);
            }
        }
        if ($online !== $article->isOnline()) {
            if ($article->isStartArticle() && $article->categoryId) {
                CategoryHandler::changeStatus($article->categoryId, $article->languageId, $online ? 1 : 0);
            } else {
                ArticleHandler::changeStatus($article->id, $article->languageId, $online ? 1 : 0);
            }
        }
        $before = Pages::get($article->id, $article->languageId)->toArray();
        Pages::save($article->id, $article->languageId, RoutesPageSettings::fromArray([...$before, ...$seo,
            'description' => mb_substr(trim($description), 0, 300), 'image' => '' !== $image && Media::get($image) ? $image : '', 'noindex' => $noindex, 'title' => trim($title)]));
        // URL: custom slug (empty = from the title); the start page always has „/“
        if (null !== $slug && $article->id !== Article::getSiteStartArticleId()) {
            $before = Routes::options($article->id, $article->languageId);
            $ownPath = $address->ownPath ?? $before->ownPath;
            $want = '' !== trim($slug) ? trim($slug) : $name;
            $normalized = $ownPath ? Routes::slugifyPath($want) : Routes::slugify($want);
            if ($normalized !== Routes::slug($article->id, $article->languageId) || $ownPath !== $before->ownPath) {
                Routes::setAddress($article->id, $article->languageId, $want, $ownPath);
            }
            if (null !== $address && $article->isStartArticle() && $article->categoryId && $address->hidden !== $before->hidden) {
                Routes::setHidden($article->id, $address->hidden);
            }
        }
        ArticleCache::delete($article->id, $article->languageId);
    }

    /** Page head for the template (description, robots, canonical, hreflang, Open Graph, JSON-LD) – shorthand for Seo\Head::render() */
    public static function head(Article $article, string $siteName = ''): string
    {
        return Head::render($article);
    }

    /**
     * Images of a page for the sitemap: preview image, then images of the published, visible blocks (the core's
     * references, rex_article_slice_reference).
     *
     * @return list<string>
     */
    public static function sitemapImages(int $articleId, int $languageId): array
    {
        $files = [];
        $own = (string) (self::get($articleId, $languageId)['image'] ?? '');
        if ('' !== $own) {
            $files[] = $own;
        }
        $rows = Sql::factory()->getArray(
            'SELECT r.target FROM rex_article_slice_reference r JOIN rex_article_slice s ON s.id = r.slice_id
             WHERE r.type = ? AND s.article_id = ? AND s.language_id = ? AND s.revision = ? AND s.status = 1 ORDER BY s.content_section_id, s.priority',
            ['media', $articleId, $languageId, ArticleRevision::LIVE],
        );
        foreach ($rows as $r) {
            $files[] = (string) $r['target'];
        }
        $out = [];
        foreach (array_unique($files) as $file) {
            if (1 === preg_match('~\.(jpe?g|png|webp|avif|gif|svg)$~i', $file) && ($m = Media::get($file))) {
                $out[] = $m->getUrl();
            }
        }

        return $out;
    }
}
