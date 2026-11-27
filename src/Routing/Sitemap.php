<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\ExtensionPoint\SitemapEntries;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Language\Language;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function date;
use function is_array;
use function Redaxo\Core\View\escape;

use const DATE_ATOM;

/**
 * sitemap.xml of the main address or of a landing domain.
 *
 * Pages: online (including parents), with saved address, canonical on this domain, not the error page – with lastmod
 * (with time), hreflang (incl. x-default = start language) and up to IMAGES images. From MAX addresses: sitemap index
 * (/sitemap.xml) with parts (/sitemap-1.xml …). In the browser /sitemap.xsl shows the addresses as a table.
 *
 * Extensions without the routing knowing them:
 *   Sitemap::exclude(fn (int $id, int $lang): bool)            omit a page (e.g. „nicht indexieren“)
 *   Sitemap::images(fn (int $id, int $lang): iterable)         images of a page (addresses, e.g. preview image, media of the blocks)
 *   Sitemap::children(fn (int $id, int $lang, string $loc): iterable)  further addresses below a page
 *                                                              (e.g. detail pages of a data list): ['loc' => …, 'lastmod' => int|null]
 *   Entries of the sources (RouteSource) are added automatically (sitemap: true, images, hreflang)
 *   Extension point SitemapEntries (list of all entries, landing)  add, change, remove
 */
final class Sitemap
{
    /** Maximum number of addresses per file (protocol: 50,000) */
    public const int MAX = 45000;

    /** Maximum number of images per address */
    public const int IMAGES = 5;

    /** @var list<callable(int, int): iterable<string>> */
    private static array $images = [];

    /** @var list<callable(int, int): bool> */
    private static array $excludes = [];

    /** @var list<callable(int, int, string): iterable<array{loc: string, lastmod?: int|null}>> */
    private static array $children = [];

    public static function exclude(callable $filter): void
    {
        self::$excludes[] = $filter;
    }

    public static function images(callable $provider): void
    {
        self::$images[] = $provider;
    }

    public static function children(callable $provider): void
    {
        self::$children[] = $provider;
    }

    /**
     * All entries of the domain.
     *
     * @return list<array{loc: string, lastmod: int|null, alternates: array<string, string>, images?: list<string>}>
     */
    public static function entries(?Domain $landing): array
    {
        $origin = $landing ? Router::origin($landing) : null;
        $startLang = Language::getStartId();
        $out = [];
        foreach (Routes::all() as $id => $byLang) {
            if ($id === Article::getNotfoundArticleId() && $id !== Article::getSiteStartArticleId()) {
                continue;
            }
            $pages = [];
            foreach ($byLang as $lang => $_) {
                $a = Article::get($id, $lang);
                if (!$a || !$a->isOnlineIncludingParents() || self::excluded($id, $lang)) {
                    continue;
                }
                $loc = Router::canonical($id, $lang);
                // Only addresses of this domain (canonical points to a landing domain → listed in its sitemap)
                if (null !== $origin ? !str_starts_with($loc, $origin . '/') && $loc !== $origin : self::onLanding($id, $a->path)) {
                    continue;
                }
                $pages[$lang] = [$loc, $a->updateDate > 0 ? $a->updateDate : null];
            }
            $alternates = self::alternatesOf($pages, $startLang);
            foreach ($pages as $lang => [$loc, $time]) {
                $out[] = ['loc' => $loc, 'lastmod' => $time, 'alternates' => $alternates, 'images' => self::imagesOf($id, $lang)];
                foreach (self::$children as $provider) {
                    foreach ($provider($id, $lang, $loc) as $child) {
                        $out[] = ['loc' => (string) $child['loc'], 'lastmod' => $child['lastmod'] ?? null, 'alternates' => []];
                    }
                }
            }
        }

        // Entries of the sources (each with hreflang of its language versions)
        $groups = [];
        foreach (Routes::entries() as $e) {
            if (!$e->sitemap) {
                continue;
            }
            $a = Article::get($e->articleId, $e->languageId);
            if (!$a || !$a->isOnlineIncludingParents() || '' === ($loc = Router::canonicalFor($e->source, $e->id, $e->languageId))) {
                continue;
            }
            if (null !== $origin ? !str_starts_with($loc, $origin . '/') : self::onLanding($e->articleId, $a->path)) {
                continue;
            }
            $images = array_slice(array_map(Seo\Head::imageUrl(...), $e->images), 0, self::IMAGES);
            $groups[$e->source . '|' . $e->id][$e->languageId] = [$loc, $e->modified, array_values(array_filter($images))];
        }
        foreach ($groups as $pages) {
            $alternates = self::alternatesOf($pages, $startLang);
            foreach ($pages as [$loc, $time, $images]) {
                $out[] = ['loc' => $loc, 'lastmod' => $time, 'alternates' => $alternates, 'images' => $images];
            }
        }

        $out = Extension::dispatch(new SitemapEntries($out, $landing));

        return is_array($out) ? array_values($out) : [];
    }

    /** Content of /sitemap.xml ($part = 0) or /sitemap-{part}.xml; null = this part doesn't exist. */
    public static function xml(?Domain $landing, int $part = 0): ?string
    {
        $entries = self::entries($landing);
        $parts = (int) ceil(count($entries) / self::MAX);
        if ($parts > 1 && 0 === $part) {
            return self::index($landing, $parts);
        }
        if ($part > 0) {
            if ($part > $parts || $parts < 2) {
                return null;
            }
            $entries = array_slice($entries, ($part - 1) * self::MAX, self::MAX);
        }
        $xml = self::head($landing)
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($entries as $e) {
            $xml .= '  <url><loc>' . escape($e['loc']) . '</loc>' . (null !== $e['lastmod'] ? '<lastmod>' . date(DATE_ATOM, $e['lastmod']) . '</lastmod>' : '');
            foreach ($e['alternates'] as $code => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="' . escape($code) . '" href="' . escape($href) . '"/>';
            }
            foreach ($e['images'] ?? [] as $src) {
                $xml .= '<image:image><image:loc>' . escape($src) . '</image:loc></image:image>';
            }
            $xml .= "</url>\n";
        }

        return $xml . '</urlset>' . "\n";
    }

    private static function index(?Domain $landing, int $parts): string
    {
        $base = self::base($landing);
        $xml = self::head($landing) . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        for ($i = 1; $i <= $parts; ++$i) {
            $xml .= '  <sitemap><loc>' . escape($base . '/sitemap-' . $i . '.xml') . "</loc></sitemap>\n";
        }

        return $xml . '</sitemapindex>' . "\n";
    }

    /** /sitemap.xsl: table view of the sitemap in the browser (search engines read the XML). */
    public static function xsl(): string
    {
        return <<<'XSL'
            <?xml version="1.0" encoding="UTF-8"?>
            <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
            <xsl:output method="html" encoding="UTF-8" indent="yes"/>
            <xsl:template match="/">
            <html lang="de"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><meta name="robots" content="noindex"/><title>Sitemap</title>
            <style>body{font:15px/1.5 system-ui,sans-serif;margin:2rem auto;max-width:1100px;padding:0 1rem;color:#1f2937}h1{font-size:1.4rem}p{color:#6b7280}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:.45rem .6rem;border-bottom:1px solid #e5e7eb;vertical-align:top}th{font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}a{color:#2563eb;word-break:break-all}td.n{white-space:nowrap;color:#6b7280}</style></head><body>
            <h1>Sitemap</h1>
            <xsl:choose>
            <xsl:when test="s:sitemapindex">
            <p><xsl:value-of select="count(s:sitemapindex/s:sitemap)"/> Teile</p>
            <table><tr><th>Teil</th></tr><xsl:for-each select="s:sitemapindex/s:sitemap"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td></tr></xsl:for-each></table>
            </xsl:when>
            <xsl:otherwise>
            <p><xsl:value-of select="count(s:urlset/s:url)"/> Adressen – diese Ansicht ist für Menschen, Suchmaschinen lesen das XML.</p>
            <table><tr><th>Adresse</th><th>Bilder</th><th>Geändert</th></tr>
            <xsl:for-each select="s:urlset/s:url"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td class="n"><xsl:value-of select="count(image:image)"/></td><td class="n"><xsl:if test="s:lastmod"><xsl:value-of select="concat(substring(s:lastmod, 9, 2), '.', substring(s:lastmod, 6, 2), '.', substring(s:lastmod, 1, 4), ' ', substring(s:lastmod, 12, 5))"/></xsl:if></td></tr></xsl:for-each>
            </table>
            </xsl:otherwise>
            </xsl:choose>
            </body></html>
            </xsl:template>
            </xsl:stylesheet>
            XSL;
    }

    /** XML header with reference to the table view */
    private static function head(?Domain $landing): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . escape(self::base($landing) . '/sitemap.xsl') . '"?>' . "\n";
    }

    private static function base(?Domain $landing): string
    {
        return rtrim($landing ? Router::origin($landing) : Router::absolute(Router::base() . '/'), '/');
    }

    /** @return list<string> absolute image addresses, without duplicates, at most IMAGES */
    private static function imagesOf(int $id, int $lang): array
    {
        $out = [];
        foreach (self::$images as $provider) {
            foreach ($provider($id, $lang) as $src) {
                $src = Router::absolute((string) $src);
                $out[$src] = $src;
                if (count($out) >= self::IMAGES) {
                    return array_values($out);
                }
            }
        }

        return array_values($out);
    }

    /**
     * hreflang (incl. x-default = start language) if there is more than one language version.
     *
     * @param array<int, array{0: string}> $pages language => [address, …]
     * @return array<string, string>
     */
    private static function alternatesOf(array $pages, int $startLang): array
    {
        $alternates = [];
        if (count($pages) > 1) {
            foreach ($pages as $lang => [$loc]) {
                $alternates[Language::get($lang)->code ?? (string) $lang] = $loc;
            }
            if (isset($pages[$startLang])) {
                $alternates['x-default'] = $pages[$startLang][0];
            }
        }

        return $alternates;
    }

    private static function excluded(int $id, int $lang): bool
    {
        foreach (self::$excludes as $filter) {
            if ($filter($id, $lang)) {
                return true;
            }
        }

        return false;
    }

    private static function onLanding(int $id, array $ancestors): bool
    {
        $l = Domains::landingFor($id, $ancestors);

        return null !== $l && $l->canonicalOwn;
    }
}
