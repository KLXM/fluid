<?php

namespace Klxm\Fluid;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\MediaPool\Media;

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
use function round;
use function rtrim;
use function strip_tags;
use function trim;

use function Redaxo\Core\View\escape;

/**
 * Interaktives Bild: Markierungen (Hotspots) auf einem Bild des Medienpools – Grundlage für den Block „Interaktives Bild“
 * (fluid.hotspots), das Feld „Markierungen“ (Field\Hotspots, Marker-Editor in der Seitenleiste) und externe Editoren
 * (Extension Point FLUID_HOTSPOT_EDITORS). Übernommen aus KLXM Studio (Core\Hotspots, MIT) – das Datenformat ist dasselbe,
 * damit Markierungen zwischen Studio und fluid austauschbar bleiben.
 *
 * Datenformat, Version 1 (gespeichert als JSON; Koordinaten in Prozent der Bildbreite bzw. -höhe, 0–100, zwei Nachkommastellen):
 *
 *   {"v": 1, "items": [
 *     {"id": "h1", "shape": "point",  "x": 12.5, "y": 40,                 …Inhalt},
 *     {"id": "h2", "shape": "circle", "x": 50, "y": 50, "r": 8,            …},   r = Radius in % der Bildbreite
 *     {"id": "h3", "shape": "rect",   "x": 10, "y": 60, "w": 20, "h": 15,  …},   x/y = linke obere Ecke
 *     {"id": "h4", "shape": "poly",   "points": [[10, 10], [30, 12], [20, 30]], …}   3–64 Punkte
 *   ]}
 *   Inhalt je Markierung: "title" (Klartext), "text" (Rich-Text ohne Bilder), "link" (Link wie im Feld „Link“: redaxo://12,
 *   https://…, #anker), "link_label", "term" (Kurzname eines Glossar-Begriffs – fluid hat kein eigenes Glossar, ein AddOn liefert
 *   den Begriff über FLUID_HOTSPOT_TERM), "icon" (Symbol statt der Nummer, leer = Nummer).
 *
 * Die Reihenfolge der Liste ist die Nummer (1, 2, 3 …), die Tab-Reihenfolge und die Reihenfolge der Liste unter dem Bild.
 * Flächen (circle/rect/poly) erscheinen als SVG über dem Bild; ihr Knopf mit der Nummer sitzt im Mittelpunkt (anchor()).
 * normalize() nimmt das Format, eine bloße Liste, Formularzeilen (points als „x,y x,y …“) und JSON an – Unbekanntes fällt weg.
 */
final class Hotspots
{
    public const int VERSION = 1;
    public const array SHAPES = ['point', 'circle', 'rect', 'poly'];
    /** Höchstzahl der Markierungen je Bild und der Punkte einer Fläche */
    public const int MAX = 60;
    public const int MAX_POINTS = 64;

    private static int $n = 0;

    // ================================================================= Datenformat

    /** @return array{v: int, items: list<array<string, mixed>>} */
    public static function empty(): array
    {
        return ['v' => self::VERSION, 'items' => []];
    }

    /**
     * Beliebige Eingabe → Format Version 1 (bereinigt). JSON-Text, {v, items}, Liste von Markierungen oder Formularzeilen.
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
            // Kennung je Bild eindeutig (externe Editoren ordnen darüber zu); fehlt sie, entsteht sie aus der Position
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
     * Eine Markierung bereinigen – null, wenn sie keine gültige Lage hat
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
                // Zu wenige Punkte: als Punkt an der angegebenen Lage behalten (nichts geht beim Umschalten verloren)
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
     * Punkte einer Fläche: [[x, y], …], [{x, y}, …] oder Text „x,y x,y …“ (auch mit Semikolon/Zeilenumbruch getrennt)
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

    /** @param list<array{0: float, 1: float}> $pts Punkte als Text für das Formular („12.5,40 20,30 …“) */
    public static function pointsText(array $pts): string
    {
        return implode(' ', array_map(static fn (array $p) => self::fmt((float) $p[0]) . ',' . self::fmt((float) $p[1]), $pts));
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /**
     * Mittelpunkt einer Markierung (dort sitzt der Knopf mit der Nummer)
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
     * Schwerpunkt eines Vielecks (bei entarteten Flächen der Mittelwert der Punkte)
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

    // ================================================================= Formular (Feld „Markierungen“)

    /**
     * Felder einer Zeile im Formular (Wiederholgruppe unter dem Marker-Editor, FieldForm)
     *
     * @return list<array<string, mixed>>
     */
    public static function subFields(): array
    {
        $shapes = ['point' => 'Punkt (Nummer)', 'circle' => 'Kreis', 'rect' => 'Rechteck', 'poly' => 'Fläche (Vieleck)'];

        return [
            ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'max' => 160, 'width' => 'half'],
            ['name' => 'shape', 'label' => 'Form', 'type' => 'select', 'required' => true, 'default' => 'point', 'width' => 'half', 'options' => $shapes],
            ['name' => 'x', 'label' => 'Waagerecht (%)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'y', 'label' => 'Senkrecht (%)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'r', 'label' => 'Radius (% der Breite, Kreis)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'w', 'label' => 'Breite (%, Rechteck)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'h', 'label' => 'Höhe (%, Rechteck)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max_value' => 100, 'width' => 'half'],
            ['name' => 'points', 'label' => 'Eckpunkte (Fläche: x,y in %, durch Leerzeichen getrennt)', 'type' => 'text', 'placeholder' => '10,10 30,12 20,30',
                'help' => 'Im Bild die Eckpunkte ziehen; Umschalt + Klick ins Bild fügt der gewählten Fläche einen Punkt an.'],
            ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link', 'width' => 'half'],
            ['name' => 'link_label', 'label' => 'Beschriftung des Links', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'Mehr erfahren'],
            ['name' => 'term', 'label' => 'Glossar-Begriff (Kurzname, optional)', 'type' => 'text', 'max' => 120, 'width' => 'half',
                'help' => 'Nur mit einem Glossar-AddOn: zeigt die Kurz-Erklärung des Begriffs und verlinkt ins Glossar.'],
            ['name' => 'icon', 'label' => 'Symbol statt Nummer (optional)', 'type' => 'icon', 'width' => 'half'],
            ['name' => 'id', 'label' => 'ID', 'type' => 'hidden'],
        ];
    }

    /** Formularzeilen (Wiederholgruppe) oder Format → Format */
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
            // Neue Zeile ohne Lage (Knopf „+ Markierung“), aber mit Inhalt: in die Bildmitte statt verwerfen
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
     * Format → Zeilen für das Formular (points als Text, x/y einer Fläche = Mittelpunkt)
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
     * Weitere Editoren (Knopf „Im … öffnen“ über den Markierungen), z. B. ein Grafik-Editor eines AddOns. Extension Point
     * FLUID_HOTSPOT_EDITORS: Liste von ['url' => Seite (gleiche Herkunft, „/…“), 'label' => Knopf, 'title' => Tooltip].
     * Die Seite öffnet eingebettet mit ?format=hotspots&media={Dateiname}&embed=1 und spricht per postMessage (gleiche Herkunft):
     *   Editor → Feld {source: 'klxm-hotspot-editor', type: 'ready'}  → Feld antwortet {source: 'klxm-hotspots', type: 'init',
     *     v: 1, data: {v: 1, items: […]}, media: {file, url, width, height}}
     *   Editor → Feld {source: 'klxm-hotspot-editor', type: 'apply', data: {v: 1, items: […]}, close?: true}
     *   Editor → Feld {source: 'klxm-hotspot-editor', type: 'close'}
     * Dasselbe Protokoll wie in KLXM Studio (Extension::hotspotEditor) – ein Editor kann beide bedienen.
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
            $out[] = ['url' => $d['url'], 'label' => '' !== $label ? $label : 'Im Editor öffnen', 'title' => trim((string) ($d['title'] ?? ''))];
        }

        return $out;
    }

    // ================================================================= Ausgabe auf der Website

    /**
     * Interaktives Bild: Bild mit nummerierten Knöpfen; jeder öffnet ein Hinweisfenster (<button popovertarget> + <div popover>,
     * ohne Inline-Skripte und -Stile – Lage über Klassen x0–x100/y0–y100, genau per CSSOM in js/hotspots.js). Flächen als SVG.
     * Darunter die Liste aller Markierungen (Nummer, Titel, Text): auf schmalen Flächen sichtbar, sonst für Screenreader
     * ($o['list'] = 'always' immer sichtbar). $o: label (Name), id (Präfix der IDs), sizes, list, eager. Leer ohne Bild.
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
            // Glossar-AddOn: ['term' => Begriff, 'short' => Kurz-Erklärung, 'url' => Seite] oder null
            $t = Extension::dispatch(new ExtensionPoint('FLUID_HOTSPOT_TERM', null, ['key' => $key]));
            if (is_array($t) && '' !== trim((string) ($t['term'] ?? ''))) {
                $terms[$key] = ['term' => (string) $t['term'], 'short' => (string) ($t['short'] ?? ''), 'url' => (string) ($t['url'] ?? '')];
            }
        }
        $label = trim((string) ($o['label'] ?? ''));
        $alt = \Klxm\Meta\Alt::attribute($file);
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
     * Titel und Inhalt eines Hinweisfensters (Text, Glossar, Link)
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
            // Kurz-Erklärung: ohne eigenen Text als Inhalt, sonst darunter mit dem Begriff davor
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

    /** @param array<string, mixed> $h Fläche als SVG-Form (Koordinaten im Pixelraster des Bildes, viewBox = Bildgröße) */
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

    /** Für Selbsttests: Zähler zurücksetzen */
    public static function reset(): void
    {
        self::$n = 0;
    }

    /** Daten als JSON (Format v1) – z. B. für den Austausch mit KLXM Studio */
    public static function json(mixed $data): string
    {
        return (string) json_encode(self::normalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
