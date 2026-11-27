<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\MediaPool\Media;

/**
 * Page settings on the website: title and online status (core) plus description, social image and „nicht indexieren“
 * (table rex_fluid_page – deliberately no meta schema, in REDAXO 6 that belongs to the project).
 */
final class PageSettings
{
    public const string TABLE = 'rex_fluid_page';

    /** @var array<string, true>|null Pages set to „nicht indexieren“ (article|language) */
    private static ?array $noindex = null;

    /** Is the page set to „nicht indexieren“? (sitemap, one query per request) */
    public static function noindex(int $articleId, int $languageId): bool
    {
        if (null === self::$noindex) {
            self::$noindex = [];
            foreach (Sql::factory()->getArray('SELECT article_id, language_id FROM ' . self::TABLE . ' WHERE noindex = 1') as $r) {
                self::$noindex[$r['article_id'] . '|' . $r['language_id']] = true;
            }
        }

        return isset(self::$noindex[$articleId . '|' . $languageId]);
    }

    /** @return array{description: string, image: string, noindex: bool, title: string} */
    public static function get(int $articleId, int $languageId): array
    {
        $row = Sql::factory()->getArray('SELECT description, image, noindex, title FROM ' . self::TABLE . ' WHERE article_id = ? AND language_id = ?', [$articleId, $languageId])[0] ?? [];

        return ['description' => (string) ($row['description'] ?? ''), 'image' => (string) ($row['image'] ?? ''), 'noindex' => !empty($row['noindex']), 'title' => (string) ($row['title'] ?? '')];
    }

    /**
     * Overrides the page head data from an output – e.g. a block showing an entry:
     * PageSettings::override(['title' => …, 'description' => …, 'image' => 'datei.jpg', 'canonical' => 'https://…']).
     * Must happen while rendering the blocks (the template renders the content before the head). Shorthand for Seo\Head::override().
     */
    public static function override(array $values): void
    {
        Routing\Seo\Head::override($values);
    }

    /**
     * @internal The page's SEO fields (page settings) and the website's defaults into the routing's meta data:
     * custom SEO title = title in the browser tab and og:title, description (else the website's default), image, „nicht indexieren“
     */
    #[AsExtension]
    public static function articleMeta(Routing\ExtensionPoint\ArticleMeta $ep): Routing\Seo\PageMeta
    {
        $m = $ep->subject;
        $a = $ep->article;
        $s = self::get($a->id, $a->languageId);
        $m->siteName = Settings::name();
        if ('' !== $s['title']) {
            $m->title = $m->documentTitle = $s['title'];
        }
        $m->description = '' !== $s['description'] ? $s['description'] : (trim((string) Settings::get('default_meta_description', '')) ?: null);
        $m->image = '' !== $s['image'] ? $s['image'] : $m->image;
        if ($s['noindex']) {
            $m->noindex = $m->nofollow = true;
        }

        return $m;
    }

    /** Title in the browser tab and in search results: custom SEO title or „Seite – Website“ (start page: website) */
    public static function title(Article $article, string $siteName = ''): string
    {
        return Routing\Seo\Head::title($article);
    }

    /** Saves title, status and settings */
    public static function save(Article $article, string $name, bool $online, string $description, string $image, bool $noindex, string $title = '', ?string $slug = null, ?Routing\AddressOptions $address = null): void
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
        Sql::factory()->setQuery(
            'INSERT INTO ' . self::TABLE . ' (article_id, language_id, description, image, noindex, title) VALUES (?, ?, ?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE description = VALUES(description), image = VALUES(image), noindex = VALUES(noindex), title = VALUES(title)',
            [$article->id, $article->languageId, mb_substr(trim($description), 0, 300), '' !== $image && Media::get($image) ? $image : '', $noindex ? 1 : 0, mb_substr(trim($title), 0, 191)],
        );
        // URL: custom slug (empty = from the title); the start page always has „/“
        if (null !== $slug && $article->id !== Article::getSiteStartArticleId()) {
            $before = Routing\Routes::options($article->id, $article->languageId);
            $ownPath = $address->ownPath ?? $before->ownPath;
            $want = '' !== trim($slug) ? trim($slug) : $name;
            $normalized = $ownPath ? Routing\Routes::slugifyPath($want) : Routing\Routes::slugify($want);
            if ($normalized !== Routing\Routes::slug($article->id, $article->languageId) || $ownPath !== $before->ownPath) {
                Routing\Routes::setAddress($article->id, $article->languageId, $want, $ownPath);
            }
            if (null !== $address && $article->isStartArticle() && $article->categoryId && $address->hidden !== $before->hidden) {
                Routing\Routes::setHidden($article->id, $address->hidden);
            }
        }
        ArticleCache::delete($article->id, $article->languageId);
    }

    /** Page head for the template (description, robots, canonical, hreflang, Open Graph, JSON-LD) – shorthand for Seo\Head::render() */
    public static function head(Article $article, string $siteName = ''): string
    {
        return Routing\Seo\Head::render($article);
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
