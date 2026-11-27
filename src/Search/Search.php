<?php

namespace Klxm\Fluid\Search;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\LayoutBlock;
use Klxm\Fluid\PageSettings;
use Klxm\Fluid\PageTemplates;
use Klxm\Routes\ExtensionPoint\RoutesRebuilt;
use Klxm\Routes\Routes;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Website search (modeled on KLXM Studio – Core\Search with Loupe): keyword search with typo tolerance and stemming,
 * index per language as SQLite in the addon's data folder (data/addons/fluid/search/{sprache}).
 *
 * Public content only: the published version (live) of pages that are online (including parents, language online), not
 * set to „nicht indexieren“ and not page templates. The block data itself is read (fields text, textarea,
 * rich text; headings weighted) – without rendering the pages. Left out are:
 *   - offline blocks and blocks in the columns of an offline layout (visitors don't see them),
 *   - blocks with 'search' => false in the kit,
 *   - fields with 'search' => false and settings that don't appear on the page as such (NO_FIELDS: recipients and
 *     confirmation texts of forms, placeholders, filter values, hints for empty lists …),
 *   - editorial comments [# … #].
 * Updating: publishing, structure and content changes (also in the backend) mark the index; the next
 * search rebuilds it (fractions of a second for websites of this size), additionally `php bin/console fluid:search-index`.
 * Every hit is checked again before output (visible()) – an outdated index never shows non-public content.
 */
final class Search
{
    /** Block settings that are not visible on the page (or only in special cases) – also hidden for guests (fluid_demo) */
    public const array NO_FIELDS = ['placeholder', 'empty_text', 'filter_value', 'mail_to', 'mail_subject', 'confirm_subject', 'confirm_text',
        'form_name', 'success', 'lang', 'redirect'];

    private const string DIRTY = 'search_dirty';
    private const array HL = ["\u{E000}", "\u{E001}"];

    /** Addresses recalculated (structure changed) → rebuild the search index on the next search */
    public static function boot(): void
    {
        Extension::register(RoutesRebuilt::class, static fn () => self::markDirty());
    }

    private static function loupe(int $languageId): Loupe
    {
        $dir = Path::addonData('fluid', 'search/' . $languageId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        $code = substr(Language::get($languageId)->code ?? 'de', 0, 2);
        $config = Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(['title', 'headings', 'text'])
            ->withLanguages([$code])
            ->withMaxTotalHits(500)
            ->withMinTokenLengthForPrefixSearch(2);

        return new LoupeFactory()->create($dir, $config);
    }

    public static function markDirty(): void
    {
        FluidAddon::instance()->setConfig(self::DIRTY, true);
    }

    /** Rebuild the index of all languages; returns the number of documents */
    public static function rebuild(): int
    {
        $n = 0;
        foreach (Language::getAll() as $lang) {
            $loupe = self::loupe($lang->id);
            $loupe->deleteAllDocuments();
            $docs = self::documents($lang->id);
            foreach (array_chunk($docs, 200) as $chunk) {
                $loupe->addDocuments($chunk);
            }
            $n += count($docs);
        }
        FluidAddon::instance()->setConfig(self::DIRTY, false);

        return $n;
    }

    /** @return list<array{id: int, title: string, headings: string, text: string}> */
    private static function documents(int $languageId): array
    {
        $noindex = [];
        foreach (Sql::factory()->getArray('SELECT article_id FROM ' . PageSettings::TABLE . ' WHERE noindex = 1 AND language_id = ?', [$languageId]) as $r) {
            $noindex[(int) $r['article_id']] = true;
        }
        $docs = [];
        if (!(Language::get($languageId)?->isOnline() ?? false)) {
            return [];
        }
        foreach (Routes::all() as $id => $byLang) {
            $a = Article::get($id, $languageId);
            if (!isset($byLang[$languageId]) || !$a || !self::visible($a, $noindex)) {
                continue;
            }
            $headings = [];
            $text = [];
            // all blocks of the live version (offline ones too), to detect columns of an offline layout
            $slices = Sql::factory()->getArray('SELECT module, data, status, content_section_id FROM rex_article_slice WHERE article_id = ? AND language_id = ? AND revision = ? ORDER BY content_section_id, priority',
                [$id, $languageId, ArticleRevision::LIVE]);
            $layoutOn = false;   // last section is a layout that visitors see
            $section = null;
            foreach ($slices as $s) {
                if ($section !== $s['content_section_id']) {
                    [$section, $layoutOn] = [$s['content_section_id'], false];   // layouts don't reach beyond their content area
                }
                $m = BlockModule::classFor((string) $s['module']);
                $on = 1 === (int) $s['status'];
                $stored = BlockModule::stored($s['data']);
                if (0 === (int) ($stored['tunes']['col'] ?? 0)) {
                    $layoutOn = $on && null !== $m && is_a($m, LayoutBlock::class, true);
                } elseif (!$layoutOn) {
                    continue;   // column block of an offline layout (or without layout): not visible
                }
                if (!$on || null === $m || false === ($m::definition()['search'] ?? true)) {
                    continue;
                }
                self::collect($m::fields(), $stored['values'], $headings, $text);
            }
            $s = PageSettings::get($id, $languageId);
            // FLUID_SEARCH_DOCUMENT: extend the document (e.g. texts of own data) or skip it with null
            $doc = Extension::dispatch(new ExtensionPoint('FLUID_SEARCH_DOCUMENT',
                ['id' => $id, 'title' => $s['title'] ?: $a->name, 'headings' => implode(' · ', array_unique($headings)), 'text' => trim($s['description'] . ' ' . implode(' ', $text))],
                ['article' => $a]));
            if (is_array($doc)) {
                $docs[] = $doc;
            }
        }

        return $docs;
    }

    /**
     * Publicly visible? Online including parents, not „nicht indexieren“, no page template, not the error page.
     *
     * @param array<int, true>|null $noindex pre-read pages with „nicht indexieren“ (otherwise read per page)
     */
    private static function visible(Article $a, ?array $noindex = null): bool
    {
        if (!$a->isOnlineIncludingParents() || PageTemplates::isTemplate($a->id) || !(Language::get($a->languageId)?->isOnline() ?? false)
            || ($a->id === Article::getNotfoundArticleId() && $a->id !== Article::getSiteStartArticleId())) {
            return false;
        }
        if (null !== $noindex) {
            return !isset($noindex[$a->id]);
        }

        return !Sql::factory()->getArray('SELECT 1 FROM ' . PageSettings::TABLE . ' WHERE noindex = 1 AND article_id = ? AND language_id = ? LIMIT 1', [$a->id, $a->languageId]);
    }

    /** Texts of the fields (recursively in repeater groups); fields "title"/"eyebrow"/"q" count as headings */
    public static function collect(array $fields, array $data, array &$headings, array &$text): void
    {
        foreach ($fields as $f) {
            if (false === ($f['search'] ?? true) || in_array($f['name'] ?? '', self::NO_FIELDS, true)) {
                continue;
            }
            $v = $data[$f['name']] ?? null;
            if ('repeater' === ($f['type'] ?? '') && is_array($v)) {
                foreach ($v as $item) {
                    if (is_array($item)) {
                        self::collect((array) ($f['fields'] ?? []), $item, $headings, $text);
                    }
                }
                continue;
            }
            if (!in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext'], true) || !is_string($v)) {
                continue;
            }
            $plain = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], (string) preg_replace('~\[#.*?#\]~s', '', $v))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $plain = (string) preg_replace('~\*([^*]+)\*~u', '$1', $plain);
            if ('' === $plain || preg_match('~^(https?://|redaxo://|#|\d+$)~', $plain)) {
                continue;
            }
            in_array($f['name'], ['title', 'eyebrow', 'q', 'name', 'label'], true) ? $headings[] = $plain : $text[] = $plain;
        }
    }

    /** @return array{total: int, hits: list<array{url: string, title: string, excerpt: string}>} */
    public static function search(string $q, int $languageId, int $limit = 20): array
    {
        $q = trim(mb_substr($q, 0, 100));
        if (mb_strlen($q) < 2) {
            return ['total' => 0, 'hits' => []];
        }
        $loupe = self::loupe($languageId);
        if (FluidAddon::instance()->getConfig(self::DIRTY, true) || 0 === $loupe->countDocuments() || $loupe->needsReindex()) {
            self::rebuild();
            $loupe = self::loupe($languageId);
        }
        $params = SearchParameters::create()->withQuery($q)->withHitsPerPage(max(1, min(50, $limit)))
            ->withAttributesToRetrieve(['id', 'title', 'text'])
            ->withAttributesToHighlight(['title', 'text'], self::HL[0], self::HL[1])
            ->withAttributesToCrop(['text'], 160, '…', 1);
        for ($try = 0;; ++$try) {
            // require all words first; without hits one is enough
            $res = $loupe->search($params->withMatchingStrategy('all'));
            if (0 === $res->getTotalHits() && str_contains($q, ' ')) {
                $res = $loupe->search($params->withMatchingStrategy('any'));
            }
            $hits = [];
            $stale = false;
            foreach ($res->getHits() as $h) {
                $a = Article::get((int) $h['id'], $languageId);
                // index outdated (page now offline, deleted, „nicht indexieren“ …): never output the hit
                if (!$a || !self::visible($a)) {
                    $stale = true;
                    continue;
                }
                $f = (array) ($h['_formatted'] ?? []);
                $hits[] = ['url' => $a->getUrl(), 'title' => self::html((string) ($f['title'] ?? $h['title'] ?? $a->name)), 'excerpt' => self::html((string) ($f['text'] ?? ''))];
            }
            if (!$stale) {
                return ['total' => $res->getTotalHits(), 'hits' => $hits];
            }
            if ($try > 0) {
                return ['total' => count($hits), 'hits' => $hits];
            }
            self::rebuild();
            $loupe = self::loupe($languageId);
        }
    }

    private static function html(string $s): string
    {
        return str_replace(self::HL, ['<mark>', '</mark>'], escape($s));
    }
}
