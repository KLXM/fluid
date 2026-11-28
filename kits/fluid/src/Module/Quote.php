<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\QuoteItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.quote', new Translatable('fluid_block_quote'))]
#[AsFluidBlock(
    icon: 'quotes',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_quote_help'),
    variants: [
        'single' => new Translatable('fluid_block_quote_variant_single'),
        'grid' => new Translatable('fluid_block_quote_variant_grid'),
        'reel' => new Translatable('fluid_block_quote_variant_reel'),
        'marquee' => new Translatable('fluid_block_quote_variant_marquee'),
    ],
    nestable: ['single', 'grid'],
)]
final class Quote extends FluidBlock
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

    #[Collection(
        new Translatable('fluid_block_quote_items'),
        item: QuoteItem::class,
        itemLabel: new Translatable('fluid_block_quote_items_item_label'),
        titleField: 'name',
        max: 12,
    )]
    public array $items = [];
}
