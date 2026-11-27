<?php

namespace Klxm\Fluid\Data;

use DateTimeInterface;
use Klxm\Fluid\Block;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Glossary\Glossary;
use Klxm\Fluid\Kit;
use Klxm\Routes\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Table\Table;

use function array_slice;
use function count;
use function in_array;
use function Redaxo\Core\View\escape;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Knowledge base (after KLXM Studio, Core\Data\Knowledge and KnowledgeLd, MIT – lean first version): chapters of a
 * YForm table of the kind „Wissen“ (Data\KnowledgeTable) in the block „Datenliste“.
 *
 *  - Outline: parent chapter (field parent) → subchapter, siblings by sort, then title. Numbers automatic („2.1.3“),
 *    overridable per chapter (field number). tree() returns nodes, roots and the reading order.
 *  - Overview: data list in the display „Inhaltsverzeichnis“ (areas, chapters, subchapters) or „Karten“.
 *  - Detail view: detail() – chapter navigation (sidebar with search, chapter tree, „In diesem Kapitel“ from the h2 of the
 *    content), content from blocks (editable on the website), tags, glossary terms, related chapters, „Zuletzt
 *    geprüft“, previous/next. Two styles: „Magazin“ (intro with full-width image) and „Sachlich“ (large number, compact).
 *  - Structured data: Article (chapter, isPartOf collection, position, keywords, articleSection, about = DefinedTerm of the glossary),
 *    WebPage with lastReviewed, on the overview CollectionPage with hasPart in reading order.
 *  - Data source: chapters() reads via YForm – the only place another backend would have to replace.
 */
final class Knowledge
{
    public const array STYLES = ['magazin' => 'Magazin (mit Einstieg)', 'sachlich' => 'Sachlich (Handbuch, Satzung)'];

    /** @var array<string, array<string, mixed>> */
    private static array $trees = [];

    public static function is(?Table $t): bool
    {
        return null !== $t && class_exists(KnowledgeTable::class) && $t instanceof KnowledgeTable;
    }

    // ------------------------------------------------------------------ Data

    /**
     * Chapters of the table (online, language of the page; with the data list's condition) as plain arrays.
     *
     * @param array<string, mixed>|null $cfg data of the data list (condition, title field)
     * @return list<array{id: int, parent: int, title: string, number: string, sort: int, area: string}>
     */
    public static function chapters(Table $t, ?array $cfg = null, ?int $languageId = null): array
    {
        $filter = null !== $cfg ? YFormBridge::field($t, (string) ($cfg['filter_field'] ?? '')) : null;
        $q = YFormBridge::inLanguage($t, $t::query(), $languageId ?? ($cfg['_language'] ?? null));
        if ($t->hasField('online')) {
            $q->where('online', true);
        }
        if ($filter && '' !== ($v = trim((string) ($cfg['filter_value'] ?? '')))) {
            $q->where($filter->name, $filter instanceof Checkbox ? (bool) $v : $v);
        }
        $titleF = YFormBridge::field($t, (string) ($cfg['title_field'] ?? ''), [Text::class]);
        $out = [];
        foreach ($q->limit(2000)->find() as $r) {
            $out[] = ['id' => (int) $r->id, 'parent' => (int) ($r->get('parent') ?? 0), 'title' => $titleF ? YFormBridge::text($r, $titleF) : (string) $r->get('title'),
                'number' => trim((string) $r->get('number')), 'sort' => (int) $r->get('sort'), 'area' => $t->hasField('area') ? (string) $r->get('area') : ''];
        }

        return $out;
    }

    /**
     * Chapter tree: nodes (id => node with number, depth, children), roots (top level), order (reading order).
     *
     * @param array<string, mixed>|null $cfg
     * @return array{nodes: array<int, array<string, mixed>>, roots: list<int>, order: list<int>}
     */
    public static function tree(Table $t, ?array $cfg = null): array
    {
        $key = $t->definition->name . '|' . md5((string) json_encode([$cfg['filter_field'] ?? '', $cfg['filter_value'] ?? '', $cfg['title_field'] ?? '', $cfg['_language'] ?? Language::getCurrentId()]));

        return self::$trees[$key] ??= self::build(self::chapters($t, $cfg));
    }

    /**
     * Build a tree from chapters (also for the self-test): unknown parent chapters and loops make a chapter a root.
     *
     * @param list<array{id: int, parent: int, title: string, number: string, sort: int, area: string}> $rows
     * @return array{nodes: array<int, array<string, mixed>>, roots: list<int>, order: list<int>}
     */
    public static function build(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $r) {
            $nodes[$r['id']] = $r + ['children' => [], 'depth' => 0, 'auto' => ''];
        }
        $byParent = [];
        foreach ($nodes as $id => $n) {
            $p = $n['parent'];
            // Loop (A → B → A) or missing parent chapter: top level
            for ($seen = [$id => true], $x = $p; $x > 0 && isset($nodes[$x]) && !isset($seen[$x]); $x = $nodes[$x]['parent']) {
                $seen[$x] = true;
            }
            if ($p > 0 && (!isset($nodes[$p]) || ($x > 0 && isset($seen[$x])))) {
                $p = 0;
            }
            $nodes[$id]['parent'] = $p;
            $byParent[$p][] = $id;
        }
        $cmp = static fn (int $a, int $b): int => [$nodes[$a]['sort'], mb_strtolower($nodes[$a]['title'])] <=> [$nodes[$b]['sort'], mb_strtolower($nodes[$b]['title'])];
        $order = [];
        $walk = static function (int $parent, string $prefix, int $depth) use (&$walk, &$nodes, &$order, $byParent, $cmp): void {
            $kids = $byParent[$parent] ?? [];
            usort($kids, $cmp);
            if ($parent > 0) {
                $nodes[$parent]['children'] = $kids;
            }
            foreach ($kids as $i => $id) {
                $auto = ('' !== $prefix ? $prefix . '.' : '') . ($i + 1);
                $nodes[$id]['depth'] = $depth;
                $nodes[$id]['auto'] = $auto;
                $nodes[$id]['number'] = '' !== $nodes[$id]['number'] ? $nodes[$id]['number'] : $auto;
                $order[] = $id;
                $walk($id, $nodes[$id]['number'], $depth + 1);
            }
        };
        $walk(0, '', 0);
        $roots = $byParent[0] ?? [];
        usort($roots, $cmp);

        return ['nodes' => $nodes, 'roots' => $roots, 'order' => $order];
    }

    /** @param array{nodes: array<int, array<string, mixed>>} $tree @return list<array<string, mixed>> parent chapters from outside in */
    public static function ancestors(array $tree, int $id): array
    {
        $out = [];
        for ($p = (int) ($tree['nodes'][$id]['parent'] ?? 0), $i = 0; $p > 0 && isset($tree['nodes'][$p]) && $i < 50; $p = (int) $tree['nodes'][$p]['parent'], ++$i) {
            array_unshift($out, $tree['nodes'][$p]);
        }

        return $out;
    }

    /** @param array{nodes: array<int, array<string, mixed>>, order: list<int>} $tree @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>} previous, next chapter */
    public static function neighbours(array $tree, int $id): array
    {
        $i = array_search($id, $tree['order'], true);
        if (false === $i) {
            return [null, null];
        }

        return [$tree['nodes'][$tree['order'][$i - 1] ?? 0] ?? null, $tree['nodes'][$tree['order'][$i + 1] ?? 0] ?? null];
    }

    /** Sections (h2) of a content: set anchors where there are none @return array{0: string, 1: list<array{id: string, title: string}>} */
    public static function sections(string $html, string $prefix = 'kn-s'): array
    {
        $list = [];
        $n = 0;
        $html = (string) preg_replace_callback('~<h2(\s[^>]*)?>(.*?)</h2>~is', static function (array $m) use (&$list, &$n, $prefix): string {
            $attrs = (string) $m[1];
            $title = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ('' === $title) {
                return $m[0];
            }
            if (preg_match('~\sid="([^"]+)"~', $attrs, $id)) {
                $anchor = $id[1];
            } else {
                $anchor = $prefix . '-' . ++$n;
                $attrs .= ' id="' . $anchor . '"';
            }
            $list[] = ['id' => $anchor, 'title' => $title];

            return '<h2' . $attrs . '>' . $m[2] . '</h2>';
        }, $html);

        return [$html, $list];
    }

    // ------------------------------------------------------------------ Output

    /**
     * Table of contents (data list, display „Inhaltsverzeichnis“): areas → chapters → subchapters with numbers.
     *
     * @param array<string, mixed> $d data of the data list
     */
    public static function toc(Block $b, array $d, Table $t, string $pageUrl): string
    {
        $tree = self::tree($t, $d + ['_language' => Language::getCurrentId()]);
        if ([] === $tree['roots']) {
            return '';
        }
        $areas = $t instanceof KnowledgeTable ? $t->areas() : [];
        $item = static function (int $id) use (&$item, $tree, $pageUrl): string {
            $n = $tree['nodes'][$id];
            $kids = '';
            foreach ($n['children'] as $c) {
                $kids .= $item($c);
            }

            return '<li class="kn-toc__item kn-toc__item--' . min(3, (int) $n['depth']) . '"><a href="' . escape(Detail::url($pageUrl, $n['title'], $n['id'])) . '">'
                . '<span class="kn-toc__n">' . escape($n['number']) . '</span><span class="kn-toc__t">' . escape($n['title']) . '</span></a>'
                . ('' !== $kids ? '<ol class="kn-toc__list" role="list">' . $kids . '</ol>' : '') . '</li>';
        };
        // Areas: group roots by area (order of the areas), those without area last
        $groups = [];
        foreach ($tree['roots'] as $id) {
            $groups[(string) $tree['nodes'][$id]['area']][] = $id;
        }
        uksort($groups, static fn ($a, $c) => (false === array_search($a, array_keys($areas), true) ? 999 : array_search($a, array_keys($areas), true)) <=> (false === array_search($c, array_keys($areas), true) ? 999 : array_search($c, array_keys($areas), true)));
        $hTag = '' !== trim((string) ($d['title'] ?? '')) ? 'h3' : 'h2';
        $h = '<div class="kn-toc">';
        foreach ($groups as $area => $ids) {
            $h .= '<section class="kn-toc__group">' . ('' !== $area && isset($areas[$area]) ? '<' . $hTag . ' class="kn-toc__area">' . escape($areas[$area]) . '</' . $hTag . '>' : '')
                . '<ol class="kn-toc__list" role="list">' . implode('', array_map($item, $ids)) . '</ol></section>';
        }

        return $h . '</div>';
    }

    /**
     * Detail view of a chapter with chapter navigation.
     *
     * @param array<string, mixed> $d data of the data list
     */
    public static function detail(Block $b, array $d, Table $t, Record $row, string $pageUrl): string
    {
        Kit::active()->markUsed('@knowledge');
        $tree = self::tree($t, $d + ['_language' => Language::getCurrentId()]);
        $id = (int) $row->id;
        $node = $tree['nodes'][$id] ?? ['number' => '', 'title' => (string) $row->get('title'), 'children' => [], 'depth' => 0];
        $style = isset(self::STYLES[(string) ($d['knowledge_style'] ?? '')]) ? (string) $d['knowledge_style'] : 'magazin';
        $url = static fn (array $n): string => Detail::url($pageUrl, (string) $n['title'], (int) $n['id']);
        $areas = $t instanceof KnowledgeTable ? $t->areas() : [];
        $area = $t->hasField('area') ? (string) $row->get('area') : '';
        $areaLabel = '' !== $area ? ($areas[$area] ?? $area) : '';
        $teaser = trim((string) $row->get('teaser'));
        $inline = static fn (string $f): string => $t->hasField($f) ? RecordInline::attrs($t, $row, $t->getField($f)) : '';

        // Content (blocks, editable in edit mode) and its sections
        $content = '';
        if ($t->hasField('content') && ($f = $t->getField('content')) instanceof FluidBlocks) {
            $content = RecordBlocks::render($t, $row, $f, $b->articleId);
        }
        [$content, $sections] = self::sections($content);

        // Sidebar: search, chapter tree (current branch expanded), sections
        $active = [...array_column(self::ancestors($tree, $id), 'id'), $id];
        $navItem = static function (int $nid) use (&$navItem, $tree, $active, $id, $url): string {
            $n = $tree['nodes'][$nid];
            $kids = '';
            if (in_array($nid, $active, true) || 0 === (int) $n['depth']) {
                foreach ($n['children'] as $c) {
                    $kids .= $navItem($c);
                }
            }

            return '<li class="kn-nav__item' . ($nid === $id ? ' is-current' : '') . '" data-kn-item="' . escape(mb_strtolower($n['number'] . ' ' . $n['title'])) . '"><a href="' . escape($url($n)) . '"'
                . ($nid === $id ? ' aria-current="page"' : '') . '><span class="kn-nav__n">' . escape((string) $n['number']) . '</span> ' . escape((string) $n['title']) . '</a>'
                . ('' !== $kids ? '<ol class="kn-nav__list" role="list">' . $kids . '</ol>' : '') . '</li>';
        };
        $sid = $b->domId() . '-kn';
        $side = '<nav class="kn-side" aria-label="' . escape(lt('Kapitel')) . '">'
            . '<p class="kn-side__back"><a href="' . escape($pageUrl) . '">' . escape(lt('Inhaltsverzeichnis')) . '</a></p>'
            // Phone: collapsed (otherwise the chapters come before the content), wide windows: open (js/knowledge.js, summary hidden)
            . '<details class="kn-drawer" data-kn-drawer><summary class="kn-drawer__sum">' . escape(lt('Kapitel') . ' ' . $node['number'] . ' · ' . lt('Navigation')) . '</summary><div class="kn-drawer__body">'
            . '<div class="kn-search" data-kn-search hidden><label class="sr-only" for="' . escape($sid) . '-q">' . escape(lt('Kapitel suchen')) . '</label>'
            . '<input class="kn-search__input" id="' . escape($sid) . '-q" type="search" placeholder="' . escape(lt('Kapitel suchen')) . '" autocomplete="off"><p class="kn-search__count" role="status" data-kn-count data-none="' . escape(lt('Keine Kapitel gefunden.')) . '" data-one="' . escape(lt('1 Kapitel')) . '" data-many="' . escape(lt('{n} Kapitel')) . '"></p></div>'
            . '<ol class="kn-nav__list kn-nav__list--root" role="list">' . implode('', array_map($navItem, $tree['roots'])) . '</ol>'
            . ($sections ? '<div class="kn-sections"><p class="kn-side__h" id="' . escape($sid) . '-s">' . escape(lt('In diesem Kapitel')) . '</p><ul class="kn-sections__list" role="list" aria-labelledby="' . escape($sid) . '-s">'
                . implode('', array_map(static fn (array $s) => '<li><a href="#' . escape($s['id']) . '">' . escape($s['title']) . '</a></li>', $sections)) . '</ul></div>' : '')
            . '</div></details></nav>';

        // Header
        $img = $t->hasField('image') ? YFormBridge::file($row, $t->getField('image')) : '';
        $hx = null === $b->prev ? 'h1' : 'h2';   // first block of the page: main heading
        $hTitle = '<' . $hx . ' class="kn-title"' . $inline('title') . '>' . escape((string) $row->get('title')) . '</' . $hx . '>';
        if ('magazin' === $style) {
            $head = '<header class="kn-hero">'
                . '<p class="kn-eyebrow">' . escape(trim(('' !== (string) $node['number'] ? lt('Kapitel') . ' ' . $node['number'] : '') . ('' !== $areaLabel ? ' · ' . $areaLabel : ''), ' ·')) . '</p>'
                . $hTitle . ('' !== $teaser || Editing::active() ? '<p class="kn-lead"' . $inline('teaser') . '>' . nl2br(escape($teaser), false) . '</p>' : '')
                . ('' !== $img ? '<div class="kn-hero__img ratio-21-9">' . img($img, '(min-width: 1280px) 1200px, 100vw', ['ratio' => '21:9']) . '</div>' : '')
                . '</header>';
        } else {
            $head = '<header class="kn-head">' . ('' !== (string) $node['number'] ? '<p class="kn-num" aria-hidden="true">' . escape((string) $node['number']) . '</p>' : '')
                . '<div class="kn-head__t">' . ('' !== $areaLabel ? '<p class="kn-eyebrow">' . escape($areaLabel) . '</p>' : '') . $hTitle . '</div>'
                . ('' !== $teaser || Editing::active() ? '<p class="kn-lead"' . $inline('teaser') . '>' . nl2br(escape($teaser), false) . '</p>' : '') . '</header>';
        }
        if ('' !== (string) $node['number']) {
            $head = str_replace($hTitle, '<' . $hx . ' class="kn-title"' . $inline('title') . '><span class="sr-only">' . escape(lt('Kapitel') . ' ' . $node['number']) . ': </span>' . escape((string) $row->get('title')) . '</' . $hx . '>', $head);
        }

        // Footer: tags, terms, related chapters, last reviewed, previous/next
        $foot = '';
        $tags = $t->hasField('tags') ? array_values(array_filter(array_map('trim', explode(',', (string) $row->get('tags'))))) : [];
        if ($tags) {
            $foot .= '<div class="kn-meta"><p class="kn-meta__h">' . escape(lt('Schlagworte')) . '</p><ul class="dl-chips" role="list">'
                . implode('', array_map(static fn ($x) => '<li><span class="dl-chip">' . escape($x) . '</span></li>', $tags)) . '</ul></div>';
        }
        $terms = self::terms($t, $row);
        if ($terms) {
            $foot .= '<div class="kn-meta"><p class="kn-meta__h">' . escape(lt('Begriffe')) . '</p><ul class="dl-chips" role="list">'
                . implode('', array_map(static fn ($x) => '<li>' . (null !== $x['url'] ? '<a class="dl-chip" href="' . escape((string) $x['url']) . '">' . escape($x['term']) . '</a>' : '<span class="dl-chip">' . escape($x['term']) . '</span>') . '</li>', $terms)) . '</ul></div>';
        }
        $rel = self::related($t, $row, $tree);
        if ($rel) {
            $foot .= '<div class="kn-meta"><p class="kn-meta__h">' . escape(lt('Verwandte Kapitel')) . '</p><ul class="kn-rel" role="list">'
                . implode('', array_map(static fn ($n) => '<li><a href="' . escape($url($n)) . '"><span class="kn-nav__n">' . escape((string) $n['number']) . '</span> ' . escape((string) $n['title']) . '</a></li>', $rel)) . '</ul></div>';
        }
        $reviewed = $t->hasField('reviewed') ? $row->get('reviewed') : null;
        if ($reviewed instanceof DateTimeInterface) {
            $foot .= '<p class="kn-reviewed">' . escape(lt('Zuletzt geprüft')) . ': <time datetime="' . $reviewed->format('Y-m-d') . '">' . escape($reviewed->format('d.m.Y')) . '</time></p>';
        }
        [$prev, $next] = self::neighbours($tree, $id);
        $pager = ($prev || $next) ? '<nav class="kn-pager" aria-label="' . escape(lt('Kapitel blättern')) . '">'
            . ($prev ? '<a class="kn-pager__prev" href="' . escape($url($prev)) . '" rel="prev"><span class="kn-pager__l">' . escape(lt('Zurück')) . '</span><span class="kn-pager__t">' . escape($prev['number'] . ' ' . $prev['title']) . '</span></a>' : '<span></span>')
            . ($next ? '<a class="kn-pager__next" href="' . escape($url($next)) . '" rel="next"><span class="kn-pager__l">' . escape(lt('Weiter')) . '</span><span class="kn-pager__t">' . escape($next['number'] . ' ' . $next['title']) . '</span></a>' : '')
            . '</nav>' : '';
        $edit = YFormBridge::editUrl($t, $id);
        $editBtn = null !== $edit && Editing::active() ? '<p><a class="fdl-edit" href="' . escape($edit) . '" target="_blank" rel="noopener">' . I18n::msg('fluid_knowledge_edit') . '</a></p>' : '';

        return '<div class="wrap kn kn--' . $style . '">'
            . ('magazin' === $style ? $head : '')
            . '<div class="kn-grid">' . $side
            . '<article class="kn-main">' . ('sachlich' === $style ? $head : '') . $editBtn
            . '<div class="kn-content">' . $content . '</div>'
            . ('' !== $foot ? '<footer class="kn-foot">' . $foot . '</footer>' : '') . $pager
            . '</article></div></div>';
    }

    /** @return list<array{id: int, term: string, url: ?string}> glossary terms of a chapter */
    public static function terms(Table $t, Record $row): array
    {
        if (!$t->hasField('terms')) {
            return [];
        }
        $ids = array_map('intval', (array) $row->get('terms'));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach (Glossary::terms(null, false) as $term) {
            if (in_array((int) $term['id'], $ids, true)) {
                $out[] = ['id' => (int) $term['id'], 'term' => (string) $term['term'], 'url' => $term['url']];
            }
        }

        return $out;
    }

    /** @param array{nodes: array<int, array<string, mixed>>} $tree @return list<array<string, mixed>> related chapters (online, in the tree) */
    public static function related(Table $t, Record $row, array $tree, int $max = 6): array
    {
        if (!$t->hasField('related')) {
            return [];
        }
        $out = [];
        foreach (array_map('intval', (array) $row->get('related')) as $rid) {
            if (isset($tree['nodes'][$rid]) && $rid !== (int) $row->id && count($out) < $max) {
                $out[] = $tree['nodes'][$rid];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Structured data

    public static function boot(): void
    {
        Extension::register('FLUID_STRUCTURED_DATA', static function (ExtensionPoint $ep): array {
            $data = (array) $ep->subject;
            $article = $ep->getParam('article');
            if (!$article instanceof Article || null === ($cfg = Detail::config($article->id, $article->languageId))) {
                return $data;
            }
            $t = YFormBridge::table((string) $cfg['table']);
            if (!self::is($t)) {
                return $data;
            }

            return self::jsonLd($data, $article, $t, $cfg, (string) $ep->getParam('url'));
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    public static function jsonLd(array $data, Article $article, Table $t, array $cfg, string $url): array
    {
        $page = Router::canonical($article->id, $article->languageId);
        $tree = self::tree($t, $cfg);
        $collection = ['@type' => 'Collection', '@id' => $page . '#collection', 'name' => $article->name, 'url' => $page];
        $entry = (int) Request::get(Detail::PARAM, 'int', 0);
        $abs = static fn (array $n): string => Router::absolute(Detail::url(Router::url($article->id, $article->languageId), (string) $n['title'], (int) $n['id']));
        foreach ($data['@graph'] as $i => $node) {
            if (($node['@type'] ?? '') !== 'WebPage') {
                continue;
            }
            if ($entry > 0 && isset($tree['nodes'][$entry])) {
                $row = $t::query()->where('id', $entry)->first();
                $n = $tree['nodes'][$entry];
                $article_ = ['@type' => 'Article', '@id' => $url . '#main', 'headline' => $n['title'], 'url' => $url, 'mainEntityOfPage' => ['@id' => $url . '#webpage'],
                    'isPartOf' => array_filter([$collection, ($p = $tree['nodes'][(int) $n['parent']] ?? null) ? ['@type' => 'Article', '@id' => $abs($p) . '#main', 'name' => $p['title'], 'url' => $abs($p)] : null]),
                    'alternateName' => (string) $n['number']];
                if (0 === (int) $n['depth']) {
                    $article_['position'] = array_search($entry, $tree['roots'], true) + 1;
                }
                if ($row) {
                    $tags = $t->hasField('tags') ? array_values(array_filter(array_map('trim', explode(',', (string) $row->get('tags'))))) : [];
                    if ($tags) {
                        $article_['keywords'] = implode(', ', $tags);
                    }
                    if ($t->hasField('area') && '' !== (string) $row->get('area')) {
                        $article_['articleSection'] = ($t instanceof KnowledgeTable ? ($t->areas()[(string) $row->get('area')] ?? '') : '') ?: (string) $row->get('area');
                    }
                    $about = array_map(static fn (array $x) => ['@type' => 'DefinedTerm', '@id' => Router::absolute((string) $x['url']) . '#term', 'name' => $x['term'], 'url' => Router::absolute((string) $x['url'])],
                        array_filter(self::terms($t, $row), static fn (array $x) => null !== $x['url']));
                    if ($about) {
                        $article_['about'] = array_values($about);
                    }
                    $reviewed = $t->hasField('reviewed') ? $row->get('reviewed') : null;
                    if ($reviewed instanceof DateTimeInterface) {
                        $data['@graph'][$i]['lastReviewed'] = $reviewed->format('Y-m-d');
                    }
                    $rel = self::related($t, $row, $tree);
                    if ($rel) {
                        $data['@graph'][$i]['relatedLink'] = array_map($abs, $rel);
                    }
                }
                $data['@graph'][] = $article_;
                // Breadcrumbs: parent chapters between overview and chapter
                $up = self::ancestors($tree, $entry);
                foreach ($data['@graph'] as $j => $crumb) {
                    if ('BreadcrumbList' === ($crumb['@type'] ?? '') && $up) {
                        $items = $crumb['itemListElement'];
                        $last = array_pop($items);
                        foreach ($up as $a) {
                            $items[] = ['@type' => 'ListItem', 'name' => $a['title'], 'item' => $abs($a)];
                        }
                        $items[] = $last;
                        foreach ($items as $k => $it) {
                            $items[$k]['position'] = $k + 1;
                        }
                        $data['@graph'][$j]['itemListElement'] = $items;
                    }
                }
            } elseif (0 === $entry) {
                $data['@graph'][$i]['@type'] = 'CollectionPage';
                $data['@graph'][$i]['mainEntity'] = ['@id' => $page . '#collection'];
                $collection['hasPart'] = array_map(static fn (int $id) => ['@type' => 'Article', '@id' => $abs($tree['nodes'][$id]) . '#main', 'name' => $tree['nodes'][$id]['title'], 'url' => $abs($tree['nodes'][$id])],
                    array_slice($tree['order'], 0, 500));
                $data['@graph'][] = $collection;
            }
            break;
        }

        return $data;
    }
}
