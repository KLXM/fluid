<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\StepsItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.steps', new Translatable('fluid_block_steps'))]
#[AsFluidBlock(
    icon: 'path',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_steps_help'),
    variants: ['numbers' => new Translatable('fluid_block_steps_variant_numbers'), 'timeline' => new Translatable('fluid_block_steps_variant_timeline'), 'process' => new Translatable('fluid_block_steps_variant_process')],
    nestable: ['numbers', 'timeline'],
)]
final class Steps extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_intro'),
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[Choice(
        new Translatable('fluid_block_steps_marker'),
        required: true,
        choices: [
            'auto' => new Translatable('fluid_block_steps_marker_auto'),
            'filled' => new Translatable('fluid_block_steps_marker_filled'),
            'soft' => new Translatable('fluid_block_steps_marker_soft'),
            'ring' => new Translatable('fluid_block_steps_marker_ring'),
            'dot' => new Translatable('fluid_block_steps_marker_dot'),
        ],
        width: 'half',
        variants: ['timeline', 'process'],
    )]
    public string $marker = 'auto';

    #[Choice(
        new Translatable('fluid_block_steps_colors'),
        required: true,
        choices: [
            'auto' => new Translatable('fluid_block_steps_colors_auto'),
            'alternate' => new Translatable('fluid_block_steps_colors_alternate'),
            'accent' => new Translatable('fluid_block_steps_colors_accent'),
            'neutral' => new Translatable('fluid_block_steps_colors_neutral'),
        ],
        width: 'half',
        variants: ['timeline', 'process', 'numbers'],
    )]
    public string $colors = 'auto';

    #[Choice(
        new Translatable('fluid_block_steps_motion'),
        required: true,
        choices: [
            'auto' => new Translatable('fluid_block_steps_motion_auto'),
            'sequence' => new Translatable('fluid_block_steps_motion_sequence'),
            'draw' => new Translatable('fluid_block_steps_motion_draw'),
            'none' => new Translatable('fluid_block_steps_motion_none'),
        ],
        width: 'half',
        note: new Translatable('fluid_block_steps_motion_help'),
    )]
    public string $motion = 'auto';

    #[Collection(
        new Translatable('fluid_block_steps_items'),
        item: StepsItem::class,
        itemLabel: new Translatable('fluid_block_steps_items_item_label'),
        titleField: 'title',
        max: 12,
    )]
    public array $items = [];
}
