<?php

namespace Klxm\Fluid\Data;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;

/**
 * Einträge im Menü (nach KLXM Studio, Core\Data\MenuEntries, MIT): Einträge einer YForm-Tabelle mit Detailseite erscheinen als
 * virtuelle Unterpunkte ihrer Übersichtsseite – in Kopf- und Mobilmenü (FluidTemplate::menu(), beide Kits), in den Brotkrumen
 * (schema.org) und – wie alle Detailseiten – in der Sitemap.
 *
 * Eingestellt am Block „Datenliste“ mit Detailansicht (die Seite mit dem Block ist die Übersicht):
 *  - „Einträge im Menü zeigen“: '' keine (Standard, das Menü bleibt unverändert) | 'top' oberste Ebene | 'two' zwei Ebenen.
 *    Zwei Ebenen gibt es bei Tabellen mit Kapitelbaum (Wissensbereich, Data\Knowledge): Kapitel und Unterkapitel; sonst eine Ebene
 *    in der Sortierung des Blocks.
 *  - „Höchstens im Menü“: Punkte je Ebene (1–50, Standard 12).
 * Auf der Detailseite eines Eintrags ist der Eintrag „aktuell“, die Übersicht (und Oberkapitel) „aktiv“.
 * Extension Point FLUID_MENU_ENTRIES: Punkte ändern (Params article, language, config).
 */
final class MenuEntries
{
    public const array MODES = ['' => 'Keine', 'top' => 'Oberste Ebene', 'two' => 'Zwei Ebenen'];
    public const int LIMIT = 12;

    /** @return array{mode: string, limit: int} */
    public static function config(?array $blockData): array
    {
        $mode = (string) ($blockData['menu_entries'] ?? '');

        return ['mode' => isset(self::MODES[$mode]) ? $mode : '', 'limit' => max(1, min(50, (int) ($blockData['menu_limit'] ?? self::LIMIT) ?: self::LIMIT))];
    }

    /**
     * Menüpunkte unter einer Seite (leer ohne Einstellung) im Format von FluidTemplate::menu().
     *
     * @return list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>, entry: int}>
     */
    public static function items(int $articleId, int $languageId): array
    {
        $cfg = Detail::config($articleId, $languageId);
        $m = self::config($cfg);
        if (null === $cfg || '' === $m['mode'] || !YFormBridge::available()) {
            return [];
        }
        $t = YFormBridge::table((string) $cfg['table']);
        if (null === $t) {
            return [];
        }
        $page = \Klxm\Fluid\Routing\Router::url($articleId, $languageId);
        $here = \Redaxo\Core\Content\Article::getCurrentId() === $articleId ? Request::get(Detail::PARAM, 'int', 0) : 0;
        try {
            if (Knowledge::is($t)) {
                $tree = Knowledge::tree($t, $cfg);
                $active = $here > 0 ? [...array_column(Knowledge::ancestors($tree, $here), 'id'), $here] : [];
                $node = static function (array $n, int $depth) use (&$node, $tree, $m, $active, $here, $page): array {
                    $kids = [];
                    if ('two' === $m['mode'] && 0 === $depth) {
                        foreach (\array_slice($n['children'], 0, $m['limit']) as $c) {
                            $kids[] = $node($tree['nodes'][$c], 1);
                        }
                    }

                    return ['label' => ('' !== $n['number'] ? $n['number'] . ' ' : '') . $n['title'], 'href' => Detail::url($page, $n['title'], $n['id']),
                        'current' => $n['id'] === $here, 'active' => \in_array($n['id'], $active, true), 'children' => $kids, 'entry' => $n['id']];
                };
                $out = [];
                foreach (\array_slice($tree['roots'], 0, $m['limit']) as $id) {
                    $out[] = $node($tree['nodes'][$id], 0);
                }
            } else {
                $titleF = YFormBridge::field($t, (string) ($cfg['title_field'] ?? ''), [\Yakamara\YForm\Field\Text::class]);
                $rows = YFormBridge::rows($t, ['filter' => YFormBridge::field($t, (string) ($cfg['filter_field'] ?? '')), 'value' => trim((string) ($cfg['filter_value'] ?? '')),
                    'sort' => YFormBridge::field($t, (string) ($cfg['sort_field'] ?? '')), 'dir' => (string) ($cfg['sort_dir'] ?? 'asc'), 'limit' => $m['limit'], 'language' => $languageId]);
                $out = [];
                foreach ($rows as $r) {
                    $title = $titleF ? YFormBridge::text($r, $titleF) : '#' . $r->id;
                    $out[] = ['label' => '' !== $title ? $title : '#' . $r->id, 'href' => Detail::url($page, $title, (int) $r->id), 'current' => (int) $r->id === $here,
                        'active' => (int) $r->id === $here, 'children' => [], 'entry' => (int) $r->id];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return (array) Extension::dispatch(new ExtensionPoint('FLUID_MENU_ENTRIES', $out, ['article' => $articleId, 'language' => $languageId, 'config' => $cfg]));
    }

    /**
     * Menü (FluidTemplate::menu) um die Einträge ergänzen: an jede Seite mit eingeschalteter Datenliste ihre Einträge als Kinder.
     * Auf der Detailseite: Übersicht nicht mehr „aktuell“ (der Eintrag ist es), aber „aktiv“.
     *
     * @param list<array<string, mixed>> $menu Punkte mit `id` (Artikel)
     * @return list<array<string, mixed>>
     */
    public static function extend(array $menu, int $languageId, int $depth = 0): array
    {
        foreach ($menu as $i => $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id > 0 && [] !== ($entries = self::items($id, $languageId))) {
                $menu[$i]['children'] = [...(array) $m['children'], ...$entries];
                if (array_filter($entries, static fn (array $e) => $e['active'])) {
                    $menu[$i]['current'] = false;
                    $menu[$i]['active'] = true;
                }
            } elseif ($depth < 1 && !empty($m['children'])) {
                $menu[$i]['children'] = self::extend((array) $m['children'], $languageId, $depth + 1);
                // Eintrag unter einer Unterseite aktiv: auch der Menüpunkt darüber ist aktiv
                if (array_filter($menu[$i]['children'], static fn (array $c) => !empty($c['active']))) {
                    $menu[$i]['active'] = true;
                }
            }
        }

        return $menu;
    }

    /** Brotkrumen eines Eintrags mit Kapitelbaum: Oberkapitel [[titel, adresse], …] (ohne den Eintrag selbst) */
    public static function crumbs(int $articleId, int $languageId, int $entry): array
    {
        $cfg = Detail::config($articleId, $languageId);
        $t = null !== $cfg ? YFormBridge::table((string) $cfg['table']) : null;
        if (null === $t || !Knowledge::is($t)) {
            return [];
        }
        $page = \Klxm\Fluid\Routing\Router::url($articleId, $languageId);
        $tree = Knowledge::tree($t, $cfg);

        return array_map(static fn (array $n) => [$n['title'], Detail::url($page, $n['title'], $n['id'])], Knowledge::ancestors($tree, $entry));
    }
}
