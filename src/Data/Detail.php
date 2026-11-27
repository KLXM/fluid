<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\BlockModule;
use Klxm\Routes\RouteMatch;
use Klxm\Routes\Router;
use Klxm\Routes\Routes;
use Klxm\Routes\Seo\PageMeta;
use Klxm\Routes\Sitemap;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;

use function array_key_exists;

/**
 * Detail view of the data list with a pretty address: /{seite}/{titel}-{id} instead of ?eintrag={id}.
 *
 * The router asks match() for addresses that are not a page: if the address ends in „-{Zahl}“ and the page before it
 * has a data list with detail view, it shows that page with the entry. Old links ?eintrag={id} redirect with 301.
 */
final class Detail
{
    public const string PARAM = 'eintrag';

    /**
     * Register with the routing: detail view /seite/titel-ID (wrong title part → 301), old link ?eintrag=ID → 301,
     * detail pages in the sitemap.xml.
     */
    public static function boot(): void
    {
        Router::resolver(static function (string $full, int $lang): ?RouteMatch {
            if (null === ($dm = self::match($full, $lang))) {
                return null;
            }
            [$pid, $eid] = $dm;
            $cfg = (array) self::config($pid, $lang);
            $title = self::title($cfg, $eid);
            if (null === $title) {
                return null;
            }
            // the same thing in other languages: page with a data list where the entry meets the condition
            $alternates = [];
            foreach (Language::getAll() as $l) {
                $c = $l->id === $lang ? $cfg : self::config($pid, $l->id);
                $t = $l->id === $lang ? $title : ($c ? self::title($c, $eid) : null);
                if (null !== $t && '' !== ($page = Router::url($pid, $l->id))) {
                    $alternates[$l->id] = self::url($page, $t, $eid);
                }
            }

            // Titles can change (also back): do not cache the redirect to the correct address
            return new RouteMatch($pid, $lang, [self::PARAM => $eid], self::url(Router::url($pid, $lang), $title, $eid),
                cacheRedirect: false, meta: self::meta($cfg, $eid, $title), alternates: $alternates);
        });
        Router::onPage(static function (int $id, int $lang, array $query): void {
            if (isset($query[self::PARAM]) && ($cfg = self::config($id, $lang)) && null !== ($t = self::title($cfg, (int) $query[self::PARAM]))) {
                $eid = (int) $query[self::PARAM];
                unset($query[self::PARAM]);
                Router::redirectTo(self::url(Router::url($id, $lang, $query), $t, $eid), 301, false);
            }
        });
        Sitemap::children(static function (int $id, int $lang, string $loc): iterable {
            if ($cfg = self::config($id, $lang)) {
                foreach (self::entries($cfg) as [$eid, $etitle]) {
                    yield ['loc' => self::url($loc, $etitle, $eid)];
                }
            }
        });
    }

    /** Settings of the first data list with detail view on a page (live version) */
    public static function config(int $articleId, int $languageId): ?array
    {
        static $cache = [];
        $key = $articleId . '/' . $languageId;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = null;
            $rows = Sql::factory()->getArray("SELECT module, data FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? AND status = 1 AND module LIKE '%.datalist' ORDER BY priority",
                [$articleId, $languageId, ArticleRevision::LIVE]);
            foreach ($rows as $r) {
                $d = BlockModule::stored($r['data'])['values'];
                // List template: image and text field of the template (title, condition and addresses stay with the block)
                if (null !== ($cls = BlockModule::classFor((string) $r['module']))) {
                    $d = ListTemplates::resolve($cls, $d);
                }
                if (!empty($d['detail']) && '' !== (string) ($d['table'] ?? '')) {
                    $cache[$key] = $d + ['_language' => $languageId];   // Language of the page (field „Sprache“ of the table)
                    break;
                }
            }
        }

        return $cache[$key];
    }

    /** Last part of the address: „titel-des-eintrags-12“ */
    public static function slug(string $title, int $id): string
    {
        $s = substr(Routes::slugify($title), 0, 80);

        return ('' !== trim($s, '-') ? rtrim($s, '-') . '-' : '') . $id;
    }

    /** Address of an entry below its page's address – in the routing's address format (/daten/titel-5.html) */
    public static function url(string $pageUrl, string $title, int $id): string
    {
        [$path, $query] = array_pad(explode('?', $pageUrl, 2), 2, '');
        $format = Router::config()->format;

        return $format->strip($path) . '/' . $format->append(self::slug($title, $id)) . ('' !== $query ? '?' . $query : '');
    }

    /** Meta data of an entry for the page head: title, description (text field), image */
    public static function meta(array $config, int $id, string $title): PageMeta
    {
        $t = YFormBridge::table((string) $config['table']);
        $row = $t ? YFormBridge::row($t, $id, YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), trim((string) ($config['filter_value'] ?? '')), $config['_language'] ?? null) : null;
        if (!$t || !$row) {
            return new PageMeta(title: $title);
        }
        $textF = YFormBridge::field($t, (string) ($config['text_field'] ?? ''), [Textarea::class]);
        $imageF = YFormBridge::field($t, (string) ($config['image_field'] ?? ''), [Media::class]);
        $text = $textF ? trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(YFormBridge::text($row, $textF))))) : '';

        return new PageMeta(
            title: '' !== $title ? $title : null,
            description: '' !== $text ? mb_strimwidth($text, 0, 160, '…') : null,
            image: $imageF && '' !== ($f = YFormBridge::file($row, $imageF)) ? $f : null,
            type: 'article',
        );
    }

    /** Address without page: [page ID, entry ID] if it matches a detail view */
    public static function match(string $full, int $languageId): ?array
    {
        if (!preg_match('~^(?:(.*)/)?(?:[a-z0-9-]*-)?(\d+)$~', $full, $m)) {
            return null;
        }
        $page = '' === $m[1] ? Article::getSiteStartArticleId() : Routes::find($m[1], $languageId);

        return null !== $page && null !== self::config($page, $languageId) ? [$page, (int) $m[2]] : null;
    }

    /** All entries of a data list with detail view: [[id, title], …] (sitemap) */
    public static function entries(array $config, int $limit = 1000): array
    {
        $t = YFormBridge::table((string) $config['table']);
        if (!$t) {
            return [];
        }
        $f = YFormBridge::field($t, (string) ($config['title_field'] ?? ''), [Text::class]);
        $rows = YFormBridge::rows($t, ['filter' => YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), 'value' => trim((string) ($config['filter_value'] ?? '')), 'limit' => $limit, 'language' => $config['_language'] ?? null]);

        return array_map(static fn ($r) => [(int) $r->id, $f ? YFormBridge::text($r, $f) : ''], $rows);
    }

    /** Title of an entry for the address (title field of the data list) */
    public static function title(array $config, int $id): ?string
    {
        $t = YFormBridge::table((string) $config['table']);
        if (!$t) {
            return null;
        }
        $f = YFormBridge::field($t, (string) ($config['title_field'] ?? ''), [Text::class]);
        $row = YFormBridge::row($t, $id, YFormBridge::field($t, (string) ($config['filter_field'] ?? '')), trim((string) ($config['filter_value'] ?? '')), $config['_language'] ?? null);

        return $row ? ($f ? YFormBridge::text($row, $f) : '') : null;
    }
}
