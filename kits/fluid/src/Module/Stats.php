<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\StatsItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.stats', new Translatable('fluid_block_stats'))]
#[AsFluidBlock(
    icon: 'chart-bar',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_stats_help'),
    variants: [
        'row' => new Translatable('fluid_block_stats_variant_row'),
        'cards' => new Translatable('fluid_block_stats_variant_cards'),
        'split' => new Translatable('fluid_block_stats_variant_split'),
    ],
    nestable: ['row', 'cards'],
)]
final class Stats extends FluidBlock
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

    #[Collection(
        new Translatable('fluid_block_stats_items'),
        item: StatsItem::class,
        itemLabel: new Translatable('fluid_block_stats_items_item_label'),
        titleField: 'value',
        max: 6,
    )]
    public array $items = [];
}
