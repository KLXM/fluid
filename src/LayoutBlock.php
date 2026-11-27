<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Form\Stored;
use Override;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;

use function count;
use function Redaxo\Core\View\escape;

/**
 * Module „Layout (Spalten)“ – one small class per kit, e.g.:
 *
 *     #[AsModule('fluid.layout', 'Layout (Spalten)')]
 *     final class Layout extends LayoutBlock { public static function kit(): Kit { return Kit::active(); } }
 *
 * Fields: here. Output: Layout::render(). CSS: css/layout.css of the kit (mapping 'layout' in kit.php → assets).
 *
 * @internal
 */
abstract class LayoutBlock extends BlockModule
{
    #[Choice(new Translatable('fluid_layout_preset'), choices: Layout::PRESET_LABELS, required: true,
        note: new Translatable('fluid_layout_preset_note'))]
    public string $preset = '1-1';

    #[Choice(
        new Translatable('fluid_layout_valign'),
        choices: ['top' => new Translatable('fluid_layout_valign_top'), 'center' => new Translatable('fluid_layout_valign_center'), 'bottom' => new Translatable('fluid_layout_valign_bottom'), 'stretch' => new Translatable('fluid_layout_valign_stretch')],
        required: true,
        width: 'half',
    )]
    public string $valign = 'top';

    #[Choice(
        new Translatable('fluid_layout_gap'),
        choices: ['small' => new Translatable('fluid_layout_gap_small'), 'normal' => new Translatable('fluid_layout_gap_normal'), 'large' => new Translatable('fluid_layout_gap_large')],
        required: true,
        width: 'half',
    )]
    public string $gap = 'normal';

    #[Choice(
        new Translatable('fluid_layout_stack'),
        choices: ['default' => new Translatable('fluid_layout_stack_default'), 'tablet' => new Translatable('fluid_layout_stack_tablet')],
        required: true,
    )]
    public string $stack = 'default';

    #[Checkbox(new Translatable('fluid_layout_reverse'))]
    public bool $reverse = false;

    /** @var array{preset?: string, w?: list<int>} dragged column widths (handle on the website) */
    #[Stored]
    public array $widths = [];

    #[Override]
    public static function type(): string
    {
        return Layout::TYPE;
    }

    #[Override]
    public static function definition(): array
    {
        return [
            'label' => I18n::rawMsg('fluid_layout_label'), 'group' => 'Layout',
            'help' => I18n::rawMsg('fluid_layout_help'),
            'fields' => Field\BlockSchema::fields(static::class),
        ];
    }

    #[Override]
    public static function nestable(string $variant = ''): bool
    {
        return false;
    }

    #[Override]
    public function output(): string
    {
        $b = $this->context();
        // backend: short info instead of grid – the column blocks follow as their own slices
        if (self::backendPreview() && null !== $b->slice && Backend\SliceGrid::showsGrid($b->slice)) {
            $count = count(Layout::children($b->slice));

            $offline = 1 !== $b->slice->status && $count > 0
                ? '<p class="blocks-be-inherited"><i class="rex-icon rex-icon-offline" aria-hidden="true"></i> ' . I18n::msg('fluid_layout_offline') . '</p>' : '';

            return '<div class="fluid-be-layouthead"><p class="blocks-be-layout"><i class="rex-icon fa-table-columns" aria-hidden="true"></i> ' . I18n::msg('fluid_layout_grid', Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . I18n::msg(1 === $count ? 'fluid_layout_blocks_one' : 'fluid_layout_blocks', $count) . '</p>' . Backend\SliceGrid::switcher($b->slice) . '</div>' . $offline . Backend\SliceGrid::grid($b->slice, $b->data);
        }
        if (self::backendPreview()) {
            // grid sketch: columns in their width (twelfths), containing the blocks as anchors to their slices below
            $n = Layout::count($b->data);
            $cols = array_fill(1, $n, '');
            $children = Layout::children($b->slice);
            foreach ($children as $child) {
                $cols[min(Layout::col($child), $n)] .= '<a class="blocks-be-chip' . (1 !== $child->status ? ' is-offline' : '') . '" href="#slice' . $child->id . '">'
                    . escape(BlockModule::summary($child)) . '</a>';
            }
            $units = Layout::units($b->data);
            $grid = '';
            foreach ($cols as $i => $chips) {
                $grid .= '<div class="blocks-be-gcol blocks-be-w' . max(1, min(12, (int) ($units[$i - 1] ?? 6))) . '"><span class="blocks-be-gcol__label">' . I18n::msg('fluid_block_column', $i) . '</span>'
                    . ('' !== $chips ? $chips : '<span class="blocks-be-empty">' . I18n::msg('fluid_layout_empty') . '</span>') . '</div>';
            }
            $count = count($children);

            return '<div class="fluid-be-layouthead"><p class="blocks-be-layout"><i class="rex-icon fa-table-columns" aria-hidden="true"></i> ' . I18n::msg('fluid_layout_grid', Layout::PRESET_LABELS[Layout::preset($b->data)])
                . ' · ' . I18n::msg(1 === $count ? 'fluid_layout_blocks_one_cols' : 'fluid_layout_blocks_cols', $count) . '</p>' . (null !== $b->slice && Backend\SliceGrid::active() ? Backend\SliceGrid::switcher($b->slice) : '') . '</div><div class="blocks-be-grid">' . $grid . '</div>';
        }

        return Layout::render($b);
    }
}
