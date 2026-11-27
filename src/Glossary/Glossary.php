<?php

namespace Klxm\Fluid\Glossary;

use Klxm\Fluid\ApiError;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Routing\RouteMatch;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Routes;
use Klxm\Fluid\Routing\Seo\PageMeta;
use Klxm\Fluid\Routing\Sitemap;
use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;
use Throwable;

use function array_key_exists;
use function array_slice;
use function function_exists;
use function in_array;
use function Redaxo\Core\View\escape;
use function strlen;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The website's glossary (after KLXM Studio, Core\Glossary, MIT): ONE glossary per website – terms with spellings,
 * short text and long text.
 *
 *  - Data: data source GlossaryStore, by default the YForm table rex_fluid_glossary (GlossaryTable). Other source:
 *    `Glossary::$store = new MeinStore();` in Project::configure().
 *  - Marking (Website → Glossar → „Begriffe automatisch markieren“, off by default): annotator on the finished HTML of each
 *    page (OUTPUT_FILTER), at the first occurrence per page (or per section), never in links, headings (h1–h3), buttons,
 *    navigation, header and footer, code; tooltip via popover. Never in edit mode and never on the term's own detail page.
 *    Exclude: data-glossary="off" on an element.
 *  - Overview and detail page: block „Glossar“ (fluid.glossary, A–Z with search). Its page is the overview, each term has
 *    the URL {page}/{slug}, e.g. /glossar/barrierefreiheit (resolver, sitemap, canonical, DefinedTerm/DefinedTermSet).
 *  - Quick glossary in the editor (menu ⋯ → Glossar, Alt+G): search terms, create a new term from the selected text,
 *    „Gibt es schon?“ with „Als Schreibweise hinzufügen“. Permission: editing the data source (YForm table permission).
 */
final class Glossary
{
    public const int SHORT_MAX = 240;
    public const string BLOCK = 'fluid.glossary';
    public const string PARAM = 'begriff';
    public const array MODES = ['page' => 'Erstes Vorkommen je Seite', 'section' => 'Erstes Vorkommen je Abschnitt'];

    public static ?GlossaryStore $store = null;

    /** Begriff dieser Anfrage (Detailseite) */
    private static ?array $current = null;

    public static function store(): GlossaryStore
    {
        return self::$store ??= new YFormGlossaryStore();
    }

    public static function available(): bool
    {
        return self::store()->available();
    }

    /** Mark automatically? (website setting, off by default) */
    public static function enabled(): bool
    {
        return (bool) Settings::get('glossary', false) && self::available();
    }

    public static function language(?int $id = null): string
    {
        return (null !== $id ? Language::get($id) : Language::getCurrent())->code ?? 'de';
    }

    /** @return list<array<string, mixed>> Terms of the language with the detail page URL */
    public static function terms(?int $languageId = null, bool $all = false): array
    {
        $lang = $languageId ?? Language::getCurrentId();
        $page = self::page($lang);
        $base = null !== $page ? Router::url($page, $lang) : '';

        return array_map(static fn (array $t) => $t + ['url' => '' !== $base ? self::url($base, (string) $t['key']) : null, 'more' => $t['long'], 'draft' => !$t['online']],
            self::store()->terms(self::language($lang), $all));
    }

    /** URL of a term below the overview's URL (routing's URL format) */
    public static function url(string $pageUrl, string $slug): string
    {
        $format = Router::config()->format;

        return $format->strip($pageUrl) . '/' . $format->append($slug);
    }

    /** Page with the block „Glossar“ (overview, live version) – null if none */
    public static function page(int $languageId): ?int
    {
        static $cache = [];
        if (!array_key_exists($languageId, $cache)) {
            try {
                $row = Sql::factory()->getArray('SELECT s.article_id FROM rex_article_slice s JOIN rex_article_translation t ON t.article_id = s.article_id AND t.language_id = s.language_id
                    WHERE s.module = ? AND s.language_id = ? AND s.revision = ? AND s.status = 1 AND t.status = 1 ORDER BY s.article_id LIMIT 1', [self::BLOCK, $languageId, ArticleRevision::LIVE]);
                $cache[$languageId] = isset($row[0]) ? (int) $row[0]['article_id'] : null;
            } catch (Throwable) {
                $cache[$languageId] = null;
            }
        }

        return $cache[$languageId];
    }

    /** Term of this request's detail page (or null) */
    public static function current(): ?array
    {
        return self::$current;
    }

    public static function short(string $s): string
    {
        $s = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($s) <= self::SHORT_MAX) {
            return $s;
        }
        $cut = mb_substr($s, 0, self::SHORT_MAX - 1);
        $sp = mb_strrpos($cut, ' ');

        return rtrim(false !== $sp && $sp > self::SHORT_MAX * .6 ? mb_substr($cut, 0, $sp) : $cut, ' ,;:–-') . '…';
    }

    /** @return list<string> Spellings: one per line, or separated by comma or semicolon */
    public static function splitVariants(string $s): array
    {
        $out = [];
        foreach (preg_split('~[\r\n;,]+~u', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $v) {
            $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            if ('' !== $v && mb_strlen($v) <= 80 && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return array_slice($out, 0, 20);
    }

    // ------------------------------------------------------------------ Anmelden

    public static function boot(): void
    {
        // Website settings: tab „Glossar“ (only with data source)
        Extension::register('FLUID_SETTINGS_SCHEMA', static function (ExtensionPoint $ep): array {
            $schema = (array) $ep->subject;
            if (!self::available()) {
                return $schema;
            }
            $schema['glossary'] = ['label' => 'Glossar', 'fields' => [
                ['name' => 'glossary', 'label' => 'Begriffe automatisch markieren', 'type' => 'bool', 'default' => false,
                    'help' => 'Fachbegriffe aus dem Glossar werden im Text unterstrichen; ein Klick zeigt die Erklärung. Nicht in Links, Überschriften und Knöpfen. Ein Bereich lässt sich mit data-glossary="off" ausnehmen.'],
                ['name' => 'glossary_mode', 'label' => 'Wie oft markieren', 'type' => 'select', 'default' => 'page', 'required' => true, 'options' => self::MODES],
            ]];

            return $schema;
        });

        // Interactive image: glossary term of a marker (field „term“: slug or term)
        Extension::register('FLUID_HOTSPOT_TERM', static function (ExtensionPoint $ep): mixed {
            if (null !== $ep->subject || !self::available()) {
                return $ep->subject;
            }
            $key = mb_strtolower(trim((string) $ep->getParam('key')));
            foreach (self::terms() as $t) {
                if ($t['key'] === $key || mb_strtolower((string) $t['term']) === $key) {
                    return ['term' => $t['term'], 'short' => $t['short'], 'url' => (string) $t['url']];
                }
            }

            return null;
        });

        // Detail page of a term: {overview}/{slug}
        Router::resolver(static function (string $full, int $lang): ?RouteMatch {
            if ('' === $full || !self::available() || null === ($page = self::page($lang))) {
                return null;
            }
            $base = (string) Routes::path($page, $lang);
            if ('' === $base || !str_starts_with(strtolower($full), strtolower($base) . '/')) {
                return null;
            }
            $slug = substr($full, strlen($base) + 1);
            foreach (self::terms($lang) as $t) {
                if ($t['key'] === $slug) {
                    self::$current = $t;

                    return new RouteMatch($page, $lang, [self::PARAM => $t['id']], (string) $t['url'], cacheRedirect: false,
                        meta: new PageMeta(title: (string) $t['term'], description: (string) $t['short'], type: 'article'));
                }
            }

            return null;
        });
        Sitemap::children(static function (int $id, int $lang, string $loc): iterable {
            if (self::available() && $id === self::page($lang)) {
                foreach (self::terms($lang) as $t) {
                    yield ['loc' => self::url($loc, (string) $t['key'])];
                }
            }
        });
        // Structured data: DefinedTermSet (overview) or DefinedTerm (detail page)
        Extension::register('FLUID_STRUCTURED_DATA', static function (ExtensionPoint $ep): array {
            $data = (array) $ep->subject;
            $article = $ep->getParam('article');
            if (!$article instanceof Article || null === ($page = self::page($article->languageId)) || $page !== $article->id) {
                return $data;
            }
            $setUrl = Router::canonical($page, $article->languageId);
            $set = ['@type' => 'DefinedTermSet', '@id' => $setUrl . '#set', 'name' => $article->name, 'url' => $setUrl];
            $abs = static fn (?string $u): string => Router::absolute((string) $u);
            if (null !== ($t = self::$current)) {
                $data['@graph'][] = ['@type' => 'DefinedTerm', '@id' => $abs($t['url']) . '#term', 'name' => $t['term'], 'description' => $t['short'],
                    'url' => $abs($t['url']), 'inDefinedTermSet' => $set] + ($t['variants'] ? ['alternateName' => $t['variants']] : []);
            } else {
                $set['hasDefinedTerm'] = array_map(static fn (array $x) => ['@type' => 'DefinedTerm', '@id' => $abs($x['url']) . '#term', 'name' => $x['term'], 'url' => $abs($x['url'])],
                    array_slice(self::terms($article->languageId), 0, 500));
                $data['@graph'][] = $set;
            }

            return $data;
        });
    }

    // ------------------------------------------------------------------ Markieren

    /**
     * Whole page: marks terms and – only if something was marked – includes stylesheet and script.
     * Unchanged when the glossary is off, in edit mode and for responses that are not a page.
     */
    public static function annotate(string $html): string
    {
        if (!self::enabled() || Editing::active() || null !== Editing::user() && Editing::requested() || !str_contains($html, '</body>') || !str_starts_with(Response::getStatus(), '200')) {
            return $html;
        }
        $terms = self::terms();
        if (!$terms) {
            return $html;
        }
        $a = new Annotator($terms, ['mode' => 'section' === Settings::get('glossary_mode', 'page') ? 'section' : 'page', 'headings' => 3,
            'exclude' => null !== self::$current ? [(int) self::$current['id']] : [], 'labels' => self::labels(), 'lang' => self::language()]);
        $out = $a->annotate($html);
        if (0 === $a->count) {
            return $html;
        }

        return self::assets($out);
    }

    /** @return array<string, string> Fixed texts of the tooltips (language of the page) */
    public static function labels(): array
    {
        $lt = static fn (string $s): string => function_exists('lt') ? (string) lt($s) : $s;

        return ['more' => $lt('Mehr im Glossar'), 'close' => $lt('Erklärung schließen'), 'draft' => $lt('Entwurf'), 'newtab' => $lt('(öffnet in neuem Tab)')];
    }

    /** Includes the glossary's stylesheet and script (once) */
    public static function assets(string $html): string
    {
        if (str_contains($html, '/glossary.js')) {
            return $html;   // Page with the block „Glossar“: the kit has already included both
        }
        // Files of the kit (css/glossary.css, js/glossary.js) – a custom kit can replace them
        $kit = Kit::active();
        $css = '<link rel="stylesheet" href="' . escape($kit->assetUrl('css/glossary.css')) . '">';
        $js = '<script src="' . escape($kit->assetUrl('js/glossary.js')) . '" defer></script>';
        if (false !== ($p = stripos($html, '</head>'))) {
            $html = substr_replace($html, $css . "\n", $p, 0);
        }

        return false !== ($p = strripos($html, '</body>')) ? substr_replace($html, $js . "\n", $p, 0) : $html . $js;
    }

    // ------------------------------------------------------------------ Quick-Glossar (Editor)

    /** For the toolbar: null without glossary or permission */
    public static function toolbar(?User $user): ?array
    {
        if (!self::available() || !self::store()->canEdit($user)) {
            return null;
        }

        return ['max' => self::SHORT_MAX, 'manage' => self::store()->editUrl(), 'auto' => self::enabled()];
    }

    /**
     * API des Editors (Aktion glossary): op = list | create | variant.
     *
     * @return array<string, mixed>
     */
    public static function api(User $user, Article $article): array
    {
        $store = self::store();
        if (!$store->canEdit($user)) {
            throw new ApiError('Dafür fehlt Ihnen das Recht, das Glossar zu bearbeiten.', 403);
        }
        $op = Request::post('op', 'string', 'list');
        $lang = $article->languageId;
        $msg = '';
        if ('create' === $op) {
            $id = $store->create(Request::post('term', 'string', ''), Request::post('short', 'string', ''), self::splitVariants(Request::post('variants', 'string', '')),
                Request::post('online', 'bool', true), Request::post('all_languages', 'bool', false) ? '' : self::language($lang));
            $msg = 'Begriff angelegt.' . (self::enabled() ? ' Er wird beim Ansehen der Seite markiert.' : '');
        } elseif ('variant' === $op) {
            $store->addVariant(Request::post('id', 'int', 0), Request::post('variant', 'string', ''));
            $msg = 'Schreibweise hinzugefügt.';
        }
        $list = array_map(static fn (array $t) => ['id' => $t['id'], 'term' => $t['term'], 'short' => $t['short'], 'variants' => $t['variants'], 'online' => $t['online'],
            'url' => $t['url'], 'edit' => $store->editUrl((int) $t['id'])], self::terms($lang, true));

        return ['terms' => $list, 'message' => $msg, 'id' => $id ?? null];
    }
}
