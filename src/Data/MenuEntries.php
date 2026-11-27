<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\Routing\Router;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Throwable;
use Yakamara\YForm\Field\Text;

use function array_slice;
use function in_array;

/**
 * Entries in the menu (after KLXM Studio, Core\Data\MenuEntries, MIT): entries of a YForm table with detail page appear as
 * virtual sub-items of their overview page – in header and mobile menu (FluidTemplate::menu(), both kits), in the breadcrumbs
 * (schema.org) and – like all detail pages – in the sitemap.
 *
 * Set on the block „Datenliste“ with detail view (the page with the block is the overview):
 *  - „Einträge im Menü zeigen“: '' none (default, the menu stays unchanged) | 'top' top level | 'two' two levels.
 *    Two levels exist for tables with a chapter tree (knowledge base, Data\Knowledge): chapters and subchapters; otherwise one
 *    level in the block's sort order.
 *  - „Höchstens im Menü“: items per level (1–50, default 12).
 * On the detail page of an entry the entry is „current“, the overview (and parent chapters) „active“.
 * Extension point FLUID_MENU_ENTRIES: change items (params article, language, config).
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
     * Menu items below a page (empty without setting) in the format of FluidTemplate::menu().
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
        $page = Router::url($articleId, $languageId);
        $here = Article::getCurrentId() === $articleId ? Request::get(Detail::PARAM, 'int', 0) : 0;
        try {
            if (Knowledge::is($t)) {
                $tree = Knowledge::tree($t, $cfg);
                $active = $here > 0 ? [...array_column(Knowledge::ancestors($tree, $here), 'id'), $here] : [];
                $node = static function (array $n, int $depth) use (&$node, $tree, $m, $active, $here, $page): array {
                    $kids = [];
                    if ('two' === $m['mode'] && 0 === $depth) {
                        foreach (array_slice($n['children'], 0, $m['limit']) as $c) {
                            $kids[] = $node($tree['nodes'][$c], 1);
                        }
                    }

                    return ['label' => ('' !== $n['number'] ? $n['number'] . ' ' : '') . $n['title'], 'href' => Detail::url($page, $n['title'], $n['id']),
                        'current' => $n['id'] === $here, 'active' => in_array($n['id'], $active, true), 'children' => $kids, 'entry' => $n['id']];
                };
                $out = [];
                foreach (array_slice($tree['roots'], 0, $m['limit']) as $id) {
                    $out[] = $node($tree['nodes'][$id], 0);
                }
            } else {
                $titleF = YFormBridge::field($t, (string) ($cfg['title_field'] ?? ''), [Text::class]);
                $rows = YFormBridge::rows($t, ['filter' => YFormBridge::field($t, (string) ($cfg['filter_field'] ?? '')), 'value' => trim((string) ($cfg['filter_value'] ?? '')),
                    'sort' => YFormBridge::field($t, (string) ($cfg['sort_field'] ?? '')), 'dir' => (string) ($cfg['sort_dir'] ?? 'asc'), 'limit' => $m['limit'], 'language' => $languageId]);
                $out = [];
                foreach ($rows as $r) {
                    $title = $titleF ? YFormBridge::text($r, $titleF) : '#' . $r->id;
                    $out[] = ['label' => '' !== $title ? $title : '#' . $r->id, 'href' => Detail::url($page, $title, (int) $r->id), 'current' => (int) $r->id === $here,
                        'active' => (int) $r->id === $here, 'children' => [], 'entry' => (int) $r->id];
                }
            }
        } catch (Throwable) {
            return [];
        }

        return (array) Extension::dispatch(new ExtensionPoint('FLUID_MENU_ENTRIES', $out, ['article' => $articleId, 'language' => $languageId, 'config' => $cfg]));
    }

    /**
     * Adds the entries to the menu (FluidTemplate::menu): to every page with an enabled data list its entries as children.
     * On the detail page: the overview is no longer „current“ (the entry is), but „active“.
     *
     * @param list<array<string, mixed>> $menu items with `id` (article)
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
                // Entry below a subpage active: the menu item above is active too
                if (array_filter($menu[$i]['children'], static fn (array $c) => !empty($c['active']))) {
                    $menu[$i]['active'] = true;
                }
            }
        }

        return $menu;
    }

    /** Breadcrumbs of an entry with chapter tree: parent chapters [[title, address], …] (without the entry itself) */
    public static function crumbs(int $articleId, int $languageId, int $entry): array
    {
        $cfg = Detail::config($articleId, $languageId);
        $t = null !== $cfg ? YFormBridge::table((string) $cfg['table']) : null;
        if (null === $t || !Knowledge::is($t)) {
            return [];
        }
        $page = Router::url($articleId, $languageId);
        $tree = Knowledge::tree($t, $cfg);

        return array_map(static fn (array $n) => [$n['title'], Detail::url($page, $n['title'], $n['id'])], Knowledge::ancestors($tree, $entry));
    }
}
