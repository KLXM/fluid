<?php

namespace Klxm\Fluid;

use Klxm\Meta\Alt;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\I18n;

use function array_column;
use function array_filter;
use function array_is_list;
use function array_map;
use function array_sum;
use function array_values;
use function count;
use function html_entity_decode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function mb_substr;
use function min;
use function number_format;
use function preg_match;
use function preg_replace;
use function preg_split;
use function Redaxo\Core\View\escape;
use function round;
use function rtrim;
use function strip_tags;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Interactive image: markers (hotspots) on a media pool image – basis for the block „Interaktives Bild“
 * (fluid.hotspots), the field „Markierungen“ (Field\Hotspots, marker editor in the sidebar) and external editors
 * (extension point FLUID_HOTSPOT_EDITORS). Taken from KLXM Studio (Core\Hotspots, MIT) – the data format is the same,
 * so that markers stay interchangeable between Studio and fluid.
 *
 * Data format, version 1 (stored as JSON; coordinates in percent of image width or height, 0–100, two decimals):
 *
 *   {"v": 1, "items": [
 *     {"id": "h1", "shape": "point",  "x": 12.5, "y": 40,                 …content},
 *     {"id": "h2", "shape": "circle", "x": 50, "y": 50, "r": 8,            …},   r = radius in % of the image width
 *     {"id": "h3", "shape": "rect",   "x": 10, "y": 60, "w": 20, "h": 15,  …},   x/y = top left corner
 *     {"id": "h4", "shape": "poly",   "points": [[10, 10], [30, 12], [20, 30]], …}   3–64 points
 *   ]}
 *   Content per marker: "title" (plain text), "text" (rich text without images), "link" (link as in the field „Link“: redaxo://12,
 *   https://…, #anchor), "link_label", "term" (slug or term of the glossary – Glossary\Glossary answers via FLUID_HOTSPOT_TERM, an
 *   AddOn can provide another source), "icon" (icon instead of the number, empty = number).
 *
 * The order of the list is the number (1, 2, 3 …), the tab order and the order of the list below the image.
 * Areas (circle/rect/poly) appear as SVG over the image; their numbered button sits at the center (anchor()).
 * normalize() accepts the format, a plain list, form rows (points as „x,y x,y …“) and JSON – anything unknown is dropped.
 */
final class Hotspots
{
    public const int VERSION = 1;
    public const array SHAPES = ['point', 'circle', 'rect', 'poly'];
    /** Maximum number of markers per image and of points of an area */
    public const int MAX = 60;
    public const int MAX_POINTS = 64;

    private static int $n = 0;

    // ================================================================= data format

    /** @return array{v: int, items: list<array<string, mixed>>} */
    public static function empty(): array
    {
        return ['v' => self::VERSION, 'items' => []];
    }

    /**
     * Any input → format version 1 (sanitized). JSON text, {v, items}, list of markers or form rows.
     *
     * @return array{v: int, items: list<array<string, mixed>>}
     */
    public static function normalize(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = '' === trim($raw) ? [] : json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return self::empty();
        }
        $list = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : (array_is_list($raw) ? $raw : array_values($raw));
        $items = [];
        $ids = [];
        foreach ($list as $it) {
            if (!is_array($it) || count($items) >= self::MAX) {
                continue;
            }
            $h = self::item($it);
            if (null === $h) {
                continue;
            }
            // ID unique per image (external editors match by it); if missing, it is derived from the position
            $base = '' !== $h['id'] ? $h['id'] : 'h' . (count($items) + 1);
            $id = $base;
            for ($k = 2; isset($ids[$id]); ++$k) {
                $id = $base . '-' . $k;
            }
            $h['id'] = $id;
            $ids[$id] = true;
            $items[] = $h;
        }

        return ['v' => self::VERSION, 'items' => $items];
    }

    /**
     * Sanitize one marker – null if it has no valid position.
     *
     * @param array<array-key, mixed> $it
     * @return array<string, mixed>|null
     */
    private static function item(array $it): ?array
    {
        $shape = in_array($it['shape'] ?? 'point', self::SHAPES, true) ? (string) ($it['shape'] ?? 'point') : 'point';
        $num = static fn (mixed $v, float $def = 0.0): float => is_numeric($v) ? round(max(0.0, min(100.0, (float) $v)), 2) : $def;
        $h = ['id' => preg_match('~^[A-Za-z0-9_-]{1,40}$~', (string) ($it['id'] ?? '')) ? (string) $it['id'] : '', 'shape' => $shape];
        if ('poly' === $shape) {
            $pts = self::points($it['points'] ?? []);
            if (count($pts) < 3) {
                // too few points: keep as a point at the given position (nothing is lost when switching)
                if (!is_numeric($it['x'] ?? null) || !is_numeric($it['y'] ?? null)) {
                    return null;
                }
                $h['shape'] = 'point';
            } else {
                $h['points'] = $pts;
            }
        }
        if ('poly' !== $h['shape']) {
            if (!is_numeric($it['x'] ?? null) || !is_numeric($it['y'] ?? null)) {
                return null;
            }
            $h['x'] = $num($it['x']);
            $h['y'] = $num($it['y']);
        }
        if ('circle' === $h['shape']) {
            $h['r'] = max(0.5, $num($it['r'] ?? 5, 5.0));
        }
        if ('rect' === $h['shape']) {
            $h['w'] = max(0.5, min(100 - $h['x'], $num($it['w'] ?? 10, 10.0)));
            $h['h'] = max(0.5, min(100 - $h['y'], $num($it['h'] ?? 10, 10.0)));
        }
        $text = RichText::html((string) ($it['text'] ?? $it['content'] ?? ''));
        if ('' === trim(strip_tags($text))) {
            $text = '';
        }
        $link = trim((string) ($it['link'] ?? ''));
        if ('' !== $link && !preg_match('~^(?:redaxo://\d+(?:#[\w-]+)?|https?://\S+|mailto:\S+|tel:[+\d\s/-]+|#[\w-]+|/[^\s]*)$~i', $link)) {
            $link = '';
        }
        $icon = trim((string) ($it['icon'] ?? ''));

        return $h + [
            'title' => mb_substr(trim(html_entity_decode(strip_tags((string) ($it['title'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 160),
            'text' => $text,
            'link' => $link,
            'link_label' => mb_substr(trim(strip_tags((string) ($it['link_label'] ?? ''))), 0, 60),
            'term' => preg_match('~^[a-z0-9][a-z0-9-]{0,120}$~', (string) ($it['term'] ?? '')) ? (string) $it['term'] : '',
            'icon' => '' !== $icon && preg_match('~^[a-z0-9-]{1,40}$~', $icon) ? $icon : '',
        ];
    }

    /**
     * Points of an area: [[x, y], …], [{x, y}, …] or text „x,y x,y …“ (also separated by semicolon/line break).
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function points(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = array_map(static fn (string $p) => explode(',', $p), preg_split('~[\s;]+~', trim($raw)) ?: []);
        }
        $out = [];
        foreach ((array) $raw as $p) {
            $x = is_array($p) ? ($p['x'] ?? $p[0] ?? null) : null;
            $y = is_array($p) ? ($p['y'] ?? $p[1] ?? null) : null;
            if (!is_numeric($x) || !is_numeric($y)) {
                continue;
            }
            $out[] = [round(max(0.0, min(100.0, (float) $x)), 2), round(max(0.0, min(100.0, (float) $y)), 2)];
            if (count($out) >= self::MAX_POINTS) {
                break;
            }
        }

        return $out;
    }

    /** @param list<array{0: float, 1: float}> $pts points as text for the form („12.5,40 20,30 …“) */
    public static function pointsText(array $pts): string
    {
        return implode(' ', array_map(static fn (array $p) => self::fmt((float) $p[0]) . ',' . self::fmt((float) $p[1]), $pts));
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /**
     * Center of a marker (where the numbered button sits).
     *
     * @param array<string, mixed> $h
     * @return array{0: float, 1: float} in %
     */
    public static function anchor(array $h): array
    {
        return match ($h['shape'] ?? 'point') {
            'rect' => [round($h['x'] + $h['w'] / 2, 2), round($h['y'] + $h['h'] / 2, 2)],
            'poly' => self::centroid($h['points']),
            default => [(float) $h['x'], (float) $h['y']],
        };
    }

    /**
     * Centroid of a polygon (for degenerate areas the mean of the points).
     *
     * @param list<array{0: float, 1: float}> $pts
     * @return array{0: float, 1: float}
     */
    private static function centroid(array $pts): array
    {
        $a = $cx = $cy = 0.0;
        $n = count($pts);
        for ($i = 0; $i < $n; ++$i) {
            [$x0, $y0] = $pts[$i];
            [$x1, $y1] = $pts[($i + 1) % $n];
            $f = $x0 * $y1 - $x1 * $y0;
            $a += $f;
            $cx += ($x0 + $x1) * $f;
            $cy += ($y0 + $y1) * $f;
        }
        if (abs($a) < 1e-6) {
            return [round(array_sum(array_column($pts, 0)) / max(1, $n), 2), round(array_sum(array_column($pts, 1)) / max(1, $n), 2)];
        }

        return [round($cx / (3 * $a), 2), round($cy / (3 * $a), 2)];
    }

    // ================================================================= form (field „Markierungen“)

    /**
     * Fields of a row in the form (repeater below the marker editor, FieldForm).
     *
     * @return list<array<string, mixed>>
     */
    public static function subFields(): array
    {
        $shapes = [];
        foreach (['point', 'circle', 'rect', 'poly'] as $s) {
            $shapes[$s] = I18n::rawMsg('fluid_hotspots_shape_' . $s);
        }

        return [
            ['name' => 'title', 'label' => I18n::rawMsg('fluid_hotspots_title'), 'type' => 'text', 'max' => 160, 'width' => 'half'],
            ['name' => 'shape', 'label' => I18n::rawMsg('fluid_hotspots_shape'), 'type' => 'select', 'required' => true, 'default' => 'point', 'width' => 'half', 'options' => $shapes],
            ['name' => 'x', 'label' => I18n::rawMsg('fluid_hotspots_x'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'y', 'label' => I18n::rawMsg('fluid_hotspots_y'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'r', 'label' => I18n::rawMsg('fluid_hotspots_r'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'w', 'label' => I18n::rawMsg('fluid_hotspots_w'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'h', 'label' => I18n::rawMsg('fluid_hotspots_h'), 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'points', 'label' => I18n::rawMsg('fluid_hotspots_points'), 'type' => 'text', 'placeholder' => '10,10 30,12 20,30',
                'help' => I18n::rawMsg('fluid_hotspots_points_help')],
            ['name' => 'text', 'label' => I18n::rawMsg('fluid_hotspots_text'), 'type' => 'richtext'],
            ['name' => 'link', 'label' => I18n::rawMsg('fluid_hotspots_link'), 'type' => 'link', 'width' => 'half'],
            ['name' => 'link_label', 'label' => I18n::rawMsg('fluid_hotspots_link_label'), 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => I18n::rawMsg('fluid_hotspots_link_label_placeholder')],
            ['name' => 'term', 'label' => I18n::rawMsg('fluid_hotspots_term'), 'type' => 'text', 'max' => 120, 'width' => 'half',
                'help' => I18n::rawMsg('fluid_hotspots_term_help')],
            ['name' => 'icon', 'label' => I18n::rawMsg('fluid_hotspots_icon'), 'type' => 'icon', 'width' => 'half'],
            ['name' => 'id', 'label' => 'ID', 'type' => 'hidden'],
        ];
    }

    /** Form rows (repeater) or format → format */
    public static function fromForm(mixed $raw): array
    {
        if (is_string($raw) || (is_array($raw) && isset($raw['v']))) {
            return self::normalize($raw);
        }
        $rows = [];
        foreach (is_array($raw) ? array_values($raw) : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            // new row without a position (button „+ Markierung“) but with content: place it in the image center instead of dropping it
            $content = '' !== trim((string) ($row['title'] ?? '')) || '' !== trim(strip_tags((string) ($row['text'] ?? ''))) || '' !== trim((string) ($row['term'] ?? ''));
            if ($content && (!is_numeric($row['x'] ?? null) || !is_numeric($row['y'] ?? null)) && 'poly' !== ($row['shape'] ?? '')) {
                $row['x'] = is_numeric($row['x'] ?? null) ? $row['x'] : 50;
                $row['y'] = is_numeric($row['y'] ?? null) ? $row['y'] : 50;
            }
            $rows[] = $row;
        }

        return self::normalize($rows);
    }

    /**
     * Format → rows for the form (points as text, x/y of an area = center).
     *
     * @return list<array<string, mixed>>
     */
    public static function toForm(mixed $data): array
    {
        $rows = [];
        foreach (self::normalize($data)['items'] as $h) {
            if ('poly' === $h['shape']) {
                [$h['x'], $h['y']] = self::anchor($h);
                $h['points'] = self::pointsText($h['points']);
            }
            $rows[] = $h;
        }

        return $rows;
    }

    /**
     * Further editors (button „Im … öffnen“ above the markers), e.g. a graphics editor of an AddOn. Extension point
     * FLUID_HOTSPOT_EDITORS: list of ['url' => page (same origin, „/…“), 'label' => button, 'title' => tooltip].
     * The page opens embedded with ?format=hotspots&media={file name}&embed=1 and talks via postMessage (same origin):
     *   editor → field {source: 'klxm-hotspot-editor', type: 'ready'}  → field answers {source: 'klxm-hotspots', type: 'init',
     *     v: 1, data: {v: 1, items: […]}, media: {file, url, width, height}}
     *   editor → field {source: 'klxm-hotspot-editor', type: 'apply', data: {v: 1, items: […]}, close?: true}
     *   editor → field {source: 'klxm-hotspot-editor', type: 'close'}
     * The same protocol as in KLXM Studio (Extension::hotspotEditor) – one editor can serve both.
     *
     * @return list<array{url: string, label: string, title: string}>
     */
    public static function editors(): array
    {
        $out = [];
        /** @var list<mixed> $list */
        $list = (array) Extension::dispatch(new ExtensionPoint('FLUID_HOTSPOT_EDITORS', []));
        foreach ($list as $d) {
            if (!is_array($d) || !is_string($d['url'] ?? null) || !str_starts_with($d['url'], '/') || str_starts_with($d['url'], '//')) {
                continue;
            }
            $label = trim((string) ($d['label'] ?? ''));
            $out[] = ['url' => $d['url'], 'label' => '' !== $label ? $label : I18n::rawMsg('fluid_hotspots_open_editor'), 'title' => trim((string) ($d['title'] ?? ''))];
        }

        return $out;
    }

    // ================================================================= output on the website

    /**
     * Interactive image: image with numbered buttons; each opens a popup (<button popovertarget> + <div popover>,
     * without inline scripts and styles – position via classes x0–x100/y0–y100, exact via CSSOM in js/hotspots.js). Areas as SVG.
     * Below, the list of all markers (number, title, text): visible on narrow screens, otherwise for screen readers
     * ($o['list'] = 'always' always visible). $o: label (name), id (ID prefix), sizes, list, eager. Empty without an image.
     *
     * @param array<string, mixed> $o
     */
    public static function render(?string $file, mixed $data, array $o = []): string
    {
        $m = null !== $file && '' !== $file ? Media::get($file) : null;
        if (null === $m || !str_starts_with((string) $m->type, 'image/')) {
            return '';
        }
        $items = self::normalize($data)['items'];
        $id = '' !== (string) ($o['id'] ?? '') ? (string) preg_replace('~[^A-Za-z0-9_-]~', '', (string) $o['id']) : 'hs' . ++self::$n;
        $w = max(1, (int) $m->width);
        $hgt = max(1, (int) $m->height);
        if ((int) $m->width <= 0 || (int) $m->height <= 0) {
            [$w, $hgt] = [1000, 1000];
        }
        $terms = [];
        foreach (array_filter(array_column($items, 'term')) as $key) {
            // glossary AddOn: ['term' => term, 'short' => short explanation, 'url' => page] or null
            $t = Extension::dispatch(new ExtensionPoint('FLUID_HOTSPOT_TERM', null, ['key' => $key]));
            if (is_array($t) && '' !== trim((string) ($t['term'] ?? ''))) {
                $terms[$key] = ['term' => (string) $t['term'], 'short' => (string) ($t['short'] ?? ''), 'url' => (string) ($t['url'] ?? '')];
            }
        }
        $label = trim((string) ($o['label'] ?? ''));
        $alt = Alt::attribute($file);
        $img = img($file, (string) ($o['sizes'] ?? '(min-width: 1280px) 1200px, 100vw'), ['class' => 'cms-hs__img'] + (isset($o['eager']) ? ['eager' => (bool) $o['eager']] : []));

        $pins = $areas = $list = '';
        foreach ($items as $i => $h) {
            $n = $i + 1;
            $c = self::content($h, $terms);
            [$ax, $ay] = self::anchor($h);
            $pid = $id . '-' . $n;
            $name = '' !== $c['title'] ? lt('{n}: {title}', ['n' => $n, 'title' => $c['title']]) : lt('Markierung {n}', ['n' => $n]);
            $pinInner = '' !== $h['icon'] ? icon($h['icon']) . '<span class="sr-only">' . escape($name) . '</span>' : '<span aria-hidden="true">' . $n . '</span><span class="sr-only">' . escape($name) . '</span>';
            $pins .= '<button type="button" class="cms-hs__pin x' . (int) round($ax) . ' y' . (int) round($ay) . ('point' !== $h['shape'] ? ' cms-hs__pin--area' : '') . ('' !== $h['icon'] ? ' cms-hs__pin--icon' : '') . '"'
                . ' data-x="' . escape(self::fmt($ax)) . '" data-y="' . escape(self::fmt($ay)) . '" data-n="' . $n . '"'
                . ' popovertarget="' . escape($pid) . '" aria-controls="' . escape($pid) . '" aria-expanded="false">' . $pinInner . '</button>'
                . '<div class="cms-hs__pop" id="' . escape($pid) . '" popover role="dialog" aria-labelledby="' . escape($pid) . '-t">'
                . '<div class="cms-hs__pophead"><span class="cms-hs__num" aria-hidden="true">' . $n . '</span>'
                . '<p class="cms-hs__ptitle" id="' . escape($pid) . '-t">' . escape('' !== $c['title'] ? $c['title'] : lt('Markierung {n}', ['n' => $n])) . '</p>'
                . '<button type="button" class="cms-hs__x" popovertarget="' . escape($pid) . '" popovertargetaction="hide" aria-label="' . escape(lt('Schließen')) . '"><span aria-hidden="true">×</span></button></div>'
                . $c['body'] . '</div>';
            if ('point' !== $h['shape']) {
                $areas .= self::svgShape($h, $n, $w, $hgt);
            }
            $list .= '<li class="cms-hs__li" data-n="' . $n . '"><span class="cms-hs__num" aria-hidden="true">' . $n . '</span><div class="cms-hs__lb">'
                . '<p class="cms-hs__lt">' . escape('' !== $c['title'] ? $c['title'] : lt('Markierung {n}', ['n' => $n])) . '</p>' . $c['body'] . '</div></li>';
        }
        $svg = '' !== $areas ? '<svg class="cms-hs__areas" viewBox="0 0 ' . $w . ' ' . $hgt . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $areas . '</svg>' : '';
        $listMode = 'always' === ($o['list'] ?? 'auto') ? 'always' : 'auto';
        $aria = '' !== $label ? $label : ('' !== $alt ? $alt : lt('Interaktives Bild'));

        return '<figure class="cms-hs cms-hs--list-' . $listMode . '" id="' . escape($id) . '" aria-label="' . escape(lt('Interaktives Bild: {name}', ['name' => str_replace('*', '', $aria)])) . '" data-cms-hs>'
            . '<div class="cms-hs__stage">' . $img . $svg . $pins . '</div>'
            . ($items ? '<figcaption class="cms-hs__cap"><span class="cms-hs__hint">' . escape(lt('{n} Markierungen – zum Öffnen anklicken oder mit der Tab-Taste wählen.', ['n' => count($items)])) . '</span></figcaption>'
                . '<ol class="cms-hs__list" aria-label="' . escape(lt('Markierungen im Bild')) . '">' . $list . '</ol>' : '')
            . '</figure>';
    }

    /**
     * Title and content of a popup (text, glossary, link).
     *
     * @param array<string, mixed> $h
     * @param array<string, array{term: string, short: string, url: string}> $terms
     * @return array{title: string, body: string}
     */
    private static function content(array $h, array $terms): array
    {
        $t = '' !== $h['term'] ? ($terms[$h['term']] ?? null) : null;
        $title = '' !== $h['title'] ? $h['title'] : (string) ($t['term'] ?? '');
        $body = '';
        if ('' !== $h['text']) {
            $body .= '<div class="cms-hs__text">' . RichText::html($h['text']) . '</div>';
        }
        if (null !== $t) {
            // short explanation: as content without own text, otherwise below it, preceded by the term
            if ('' !== $t['short']) {
                $dfn = '' !== $h['text'] || $title !== $t['term'] ? '<dfn class="cms-hs__dfn">' . escape($t['term']) . '</dfn>: ' : '';
                $body .= '<p class="cms-hs__gl">' . $dfn . escape($t['short']) . '</p>';
            }
            if ('' !== $t['url']) {
                $body .= '<p class="cms-hs__more"><a href="' . escape($t['url']) . '">' . escape(lt('Mehr im Glossar')) . ' <span aria-hidden="true">→</span></a></p>';
            }
        }
        if ('' !== $h['link'] && '#' !== ($href = link_href($h['link']))) {
            $ext = (bool) preg_match('~^https?://~i', $href);
            $body .= '<p class="cms-hs__more"><a href="' . escape($href) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . escape('' !== $h['link_label'] ? $h['link_label'] : lt('Mehr erfahren'))
                . ($ext ? '<span class="sr-only"> ' . escape(lt('(öffnet in neuem Tab)')) . '</span>' : '') . ' <span aria-hidden="true">→</span></a></p>';
        }

        return ['title' => $title, 'body' => $body];
    }

    /** @param array<string, mixed> $h area as SVG shape (coordinates in the pixel grid of the image, viewBox = image size) */
    private static function svgShape(array $h, int $n, int $w, int $hgt): string
    {
        $px = static fn (float $v): string => self::fmt($v * $w / 100);
        $py = static fn (float $v): string => self::fmt($v * $hgt / 100);
        $cls = ' class="cms-hs__area" data-n="' . $n . '"';

        return match ($h['shape']) {
            'circle' => '<circle' . $cls . ' cx="' . $px($h['x']) . '" cy="' . $py($h['y']) . '" r="' . $px($h['r']) . '"/>',
            'rect' => '<rect' . $cls . ' x="' . $px($h['x']) . '" y="' . $py($h['y']) . '" width="' . $px($h['w']) . '" height="' . $py($h['h']) . '" rx="' . self::fmt(min($w, $hgt) / 200) . '"/>',
            'poly' => '<polygon' . $cls . ' points="' . implode(' ', array_map(static fn (array $p) => $px($p[0]) . ',' . $py($p[1]), $h['points'])) . '"/>',
            default => '',
        };
    }

    /** For self tests: reset the counter */
    public static function reset(): void
    {
        self::$n = 0;
    }

    /** Data as JSON (format v1) – e.g. for exchange with KLXM Studio */
    public static function json(mixed $data): string
    {
        return (string) json_encode(self::normalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
