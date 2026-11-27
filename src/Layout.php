<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\Module;

use function count;
use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

/**
 * Block „Layout“: blocks side by side in columns (concept from KLXM Studio, Core\Layout).
 *
 * In REDAXO the column blocks remain real slices: they follow the layout slice directly and carry `col` = column 1–4
 * in their section options (value2). The group ends at the first slice without `col`. This way they keep permissions,
 * working version, history, online/offline and their backend form; copying and publishing keep the order.
 *
 * Output: the layout slice renders the grid with its column blocks; these render nothing on the top level themselves.
 * A column block with `col` but no layout before it (e.g. after deleting the layout) appears as a normal section.
 */
final class Layout
{
    public const string TYPE = 'layout';

    /** Grid: key → shares per column */
    public const array PRESETS = [
        '1-1' => [1, 1], '2-1' => [2, 1], '1-2' => [1, 2], '1-1-1' => [1, 1, 1], '1-1-1-1' => [1, 1, 1, 1], '1-3' => [1, 3], '3-1' => [3, 1],
    ];
    public const array PRESET_LABELS = [
        '1-1' => '½ + ½', '2-1' => '⅔ + ⅓', '1-2' => '⅓ + ⅔', '1-1-1' => '⅓ × 3', '1-1-1-1' => '¼ × 4', '1-3' => '¼ + ¾', '3-1' => '¾ + ¼',
    ];

    /** Dragged column widths in twelfths (handle between the columns, like Core\Layout in Studio) */
    public const int UNITS = 12;
    public const int MIN_UNITS = 2;

    /**
     * Validates dragged widths: only valid for the grid they were dragged in (other grid = the grid's widths).
     *
     * @return array{preset: string, w: list<int>}|null
     */
    public static function cleanWidths(mixed $w, string $preset): ?array
    {
        if (!is_array($w) || ($w['preset'] ?? '') !== $preset || !is_array($w['w'] ?? null)) {
            return null;
        }
        $v = array_map('intval', array_values($w['w']));
        if (count($v) !== count(self::PRESETS[$preset]) || self::UNITS !== array_sum($v)) {
            return null;
        }
        foreach ($v as $x) {
            if ($x < self::MIN_UNITS || $x > self::UNITS - self::MIN_UNITS) {
                return null;
            }
        }

        return ['preset' => $preset, 'w' => $v];
    }

    /** @return list<int> Column weights: dragged widths or those of the grid */
    public static function weights(array $data): array
    {
        $p = self::preset($data);

        return self::cleanWidths($data['widths'] ?? null, $p)['w'] ?? self::PRESETS[$p];
    }

    /** @return list<int> Widths in twelfths (for the handle in the editor) */
    public static function units(array $data): array
    {
        $w = self::weights($data);
        $sum = array_sum($w);

        return self::UNITS === $sum ? $w : array_map(static fn (int $x) => (int) round($x * self::UNITS / $sum), $w);
    }

    public static function preset(array $data): string
    {
        $p = (string) ($data['preset'] ?? '');

        return isset(self::PRESETS[$p]) ? $p : '1-1';
    }

    public static function count(array $data): int
    {
        return count(self::PRESETS[self::preset($data)]);
    }

    /** Column of a slice (1–4), or 0 if it is not marked for a layout */
    public static function col(ArticleSlice $slice): int
    {
        return max(0, (int) (BlockModule::tunesOf($slice)['col'] ?? 0));
    }

    public static function isLayout(ArticleSlice $slice): bool
    {
        return is_a((string) Module::getClass($slice->moduleKey), LayoutBlock::class, true);
    }

    /**
     * Column blocks of a layout (in order, offline ones too).
     *
     * @return list<ArticleSlice>
     */
    public static function children(ArticleSlice $layout): array
    {
        $out = [];
        for ($s = $layout->getNextSlice(); $s && self::col($s) > 0 && $s->contentSectionId === $layout->contentSectionId; $s = $s->getNextSlice()) {
            $out[] = $s;
        }

        return $out;
    }

    /** Layout in whose column the slice sits – or null */
    public static function parentOf(ArticleSlice $slice): ?ArticleSlice
    {
        if (0 === self::col($slice)) {
            return null;
        }
        for ($p = $slice->getPreviousSlice(); $p; $p = $p->getPreviousSlice()) {
            if ($p->contentSectionId !== $slice->contentSectionId) {
                return null;
            }
            if (self::col($p) > 0) {
                continue;
            }

            return self::isLayout($p) ? $p : null;
        }

        return null;
    }

    /**
     * Grid with the column blocks (content of the layout section).
     * More columns in the blocks than in the grid: these blocks go into the last column (nothing gets lost).
     */
    public static function render(Block $layout): string
    {
        $d = $layout->data;
        $preset = self::preset($d);
        $n = self::count($d);
        $editing = Editing::active();
        $valign = in_array($d['valign'] ?? '', ['top', 'center', 'bottom', 'stretch'], true) ? $d['valign'] : 'top';
        $gap = in_array($d['gap'] ?? '', ['small', 'normal', 'large'], true) ? $d['gap'] : 'normal';
        $cls = ['wrap', 'lay-grid', 'lay-grid--' . $preset, 'lay-v-' . $valign, 'lay-gap-' . $gap];
        if ('tablet' === ($d['stack'] ?? '')) {
            $cls[] = 'lay-stack-tablet';
        }
        if (!empty($d['reverse'])) {
            $cls[] = 'lay-rev';
        }

        $cols = array_fill(1, $n, '');
        $parentBg = (string) $layout->tunes['background'];
        foreach (self::children($layout->slice) as $child) {
            if (1 !== $child->status && !$editing) {
                continue;
            }
            if (null === BlockModule::classFor($child->moduleKey)) {
                continue;
            }
            $cols[min($n, self::col($child))] .= BlockModule::of($child)->renderChild($parentBg);
        }

        $weights = self::weights($d);
        $html = '<div class="' . escape(implode(' ', $cls)) . '"' . ($editing ? ' data-lay-w="' . implode(',', self::units($d)) . '" data-lay-preset="' . implode(',', self::units(['preset' => $preset])) . '"' : '') . '>' . "\n";
        foreach ($cols as $i => $items) {
            $html .= '<div class="lay-col lay-col--w' . (int) $weights[$i - 1] . ('' === $items ? ' lay-col--empty' : '') . '"'
                . ($editing ? ' data-lay-col="' . $i . '"' : '') . '>' . $items . "</div>\n";
        }

        return $html . '</div>';
    }
}
