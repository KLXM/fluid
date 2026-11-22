<?php

namespace Klxm\Fluid\Glossary;

use Klxm\Fluid\ApiError;
use Klxm\Fluid\BlockModule;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Routing\RouteMatch;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Seo\PageMeta;
use Klxm\Fluid\Routing\Sitemap;
use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;

use function Redaxo\Core\View\escape;

/**
 * Glossar der Website (nach KLXM Studio, Core\Glossary, MIT): EIN Glossar je Website – Begriffe mit Schreibweisen,
 * Kurztext und ausführlichem Text.
 *
 *  - Daten: Datenquelle GlossaryStore, Standard YForm-Tabelle rex_fluid_glossary (GlossaryTable). Andere Quelle:
 *    `Glossary::$store = new MeinStore();` in Project::configure().
 *  - Markieren (Website → Glossar → „Begriffe automatisch markieren“, Standard aus): Annotator auf dem fertigen HTML jeder
 *    Seite (OUTPUT_FILTER), beim ersten Vorkommen je Seite (oder je Abschnitt), nie in Links, Überschriften (h1–h3), Knöpfen,
 *    Navigation, Kopf und Fuß, Code; Hinweisfenster per Popover. Nie im Bearbeiten-Modus und nie auf der eigenen Detailseite.
 *    Ausnehmen: data-glossary="off" an einem Element.
 *  - Übersicht und Detailseite: Block „Glossar“ (fluid.glossary, A–Z mit Suche). Seine Seite ist die Übersicht, jeder Begriff hat
 *    die Adresse {seite}/{slug}, z. B. /glossar/barrierefreiheit (Resolver, Sitemap, Canonical, DefinedTerm/DefinedTermSet).
 *  - Quick-Glossar im Editor (Menü ⋯ → Glossar, Alt+G): Begriffe suchen, markierten Text als neuen Begriff anlegen,
 *    „Gibt es schon?“ mit „Als Schreibweise hinzufügen“. Recht: Bearbeiten der Datenquelle (YForm-Tabellenrecht).
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

    /** Automatisch markieren? (Website-Einstellung, Standard aus) */
    public static function enabled(): bool
    {
        return (bool) Settings::get('glossary', false) && self::available();
    }

    public static function language(?int $id = null): string
    {
        return (null !== $id ? Language::get($id) : Language::getCurrent())->code ?? 'de';
    }

    /** @return list<array<string, mixed>> Begriffe der Sprache mit Adresse der Detailseite */
    public static function terms(?int $languageId = null, bool $all = false): array
    {
        $lang = $languageId ?? Language::getCurrentId();
        $page = self::page($lang);
        $base = null !== $page ? Router::url($page, $lang) : '';

        return array_map(static fn (array $t) => $t + ['url' => '' !== $base ? self::url($base, (string) $t['key']) : null, 'more' => $t['long'], 'draft' => !$t['online']],
            self::store()->terms(self::language($lang), $all));
    }

    /** Adresse eines Begriffs unter der Adresse der Übersicht (Adressformat des Routings) */
    public static function url(string $pageUrl, string $slug): string
    {
        $format = Router::config()->format;

        return $format->strip($pageUrl) . '/' . $format->append($slug);
    }

    /** Seite mit dem Block „Glossar“ (Übersicht, Live-Fassung) – null ohne */
    public static function page(int $languageId): ?int
    {
        static $cache = [];
        if (!\array_key_exists($languageId, $cache)) {
            try {
                $row = Sql::factory()->getArray('SELECT s.article_id FROM rex_article_slice s JOIN rex_article_translation t ON t.article_id = s.article_id AND t.language_id = s.language_id
                    WHERE s.module = ? AND s.language_id = ? AND s.revision = ? AND s.status = 1 AND t.status = 1 ORDER BY s.article_id LIMIT 1', [self::BLOCK, $languageId, ArticleRevision::LIVE]);
                $cache[$languageId] = isset($row[0]) ? (int) $row[0]['article_id'] : null;
            } catch (\Throwable) {
                $cache[$languageId] = null;
            }
        }

        return $cache[$languageId];
    }

    /** Begriff der Detailseite dieser Anfrage (oder null) */
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

    /** @return list<string> Schreibweisen: eine je Zeile, oder durch Komma bzw. Semikolon getrennt */
    public static function splitVariants(string $s): array
    {
        $out = [];
        foreach (preg_split('~[\r\n;,]+~u', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $v) {
            $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            if ('' !== $v && mb_strlen($v) <= 80 && !\in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return \array_slice($out, 0, 20);
    }

    // ------------------------------------------------------------------ Anmelden

    public static function boot(): void
    {
        // Website-Einstellungen: Reiter „Glossar“ (nur mit Datenquelle)
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

        // Interaktives Bild: Glossar-Begriff einer Markierung (Feld „term“: Slug oder Begriff)
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

        // Detailseite eines Begriffs: {Übersicht}/{slug}
        Router::resolver(static function (string $full, int $lang): ?RouteMatch {
            if ('' === $full || !self::available() || null === ($page = self::page($lang))) {
                return null;
            }
            $base = (string) \Klxm\Fluid\Routing\Routes::path($page, $lang);
            if ('' === $base || !str_starts_with(strtolower($full), strtolower($base) . '/')) {
                return null;
            }
            $slug = substr($full, \strlen($base) + 1);
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
        // Strukturierte Daten: DefinedTermSet (Übersicht) bzw. DefinedTerm (Detailseite)
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
                    \array_slice(self::terms($article->languageId), 0, 500));
                $data['@graph'][] = $set;
            }

            return $data;
        });
    }

    // ------------------------------------------------------------------ Markieren

    /**
     * Ganze Seite: Begriffe markieren und – nur wenn etwas markiert wurde – Stylesheet und Skript einbinden.
     * Unverändert bei ausgeschaltetem Glossar, im Bearbeiten-Modus und bei Antworten, die keine Seite sind.
     */
    public static function annotate(string $html): string
    {
        if (!self::enabled() || Editing::active() || null !== Editing::user() && Editing::requested() || !str_contains($html, '</body>') || !str_starts_with(\Redaxo\Core\Http\Response::getStatus(), '200')) {
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

    /** @return array<string, string> Feste Texte der Hinweisfenster (Sprache der Seite) */
    public static function labels(): array
    {
        $lt = static fn (string $s): string => \function_exists('lt') ? (string) lt($s) : $s;

        return ['more' => $lt('Mehr im Glossar'), 'close' => $lt('Erklärung schließen'), 'draft' => $lt('Entwurf'), 'newtab' => $lt('(öffnet in neuem Tab)')];
    }

    /** Stylesheet und Skript des Glossars einbinden (einmal) */
    public static function assets(string $html): string
    {
        if (str_contains($html, '/glossary.js')) {
            return $html;   // Seite mit dem Block „Glossar“: das Kit hat beides schon eingebunden
        }
        // Dateien des Kits (css/glossary.css, js/glossary.js) – ein eigenes Kit kann sie ersetzen
        $kit = \Klxm\Fluid\Kit::active();
        $css = '<link rel="stylesheet" href="' . escape($kit->assetUrl('css/glossary.css')) . '">';
        $js = '<script src="' . escape($kit->assetUrl('js/glossary.js')) . '" defer></script>';
        if (false !== ($p = stripos($html, '</head>'))) {
            $html = substr_replace($html, $css . "\n", $p, 0);
        }

        return false !== ($p = strripos($html, '</body>')) ? substr_replace($html, $js . "\n", $p, 0) : $html . $js;
    }

    // ------------------------------------------------------------------ Quick-Glossar (Editor)

    /** Für die Leiste: null ohne Glossar bzw. Recht */
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
