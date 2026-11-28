<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\PriceListItem;
use Klxm\Fluid\Kit\Fluid\Module\Item\PricingItem;
use Klxm\Fluid\Kit\Fluid\Module\Item\PricingRow;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.pricing', new Translatable('fluid_block_pricing'))]
#[AsFluidBlock(
    icon: 'currency-eur',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_pricing_help'),
    variants: ['cards' => new Translatable('fluid_block_pricing_variant_cards'), 'table' => new Translatable('fluid_block_pricing_variant_table'), 'list' => new Translatable('fluid_block_pricing_variant_list')],
    variantHelp: ['list' => new Translatable('fluid_block_pricing_variant_list_help')],
)]
final class Pricing extends FluidBlock
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
        new Translatable('fluid_block_pricing_items'),
        item: PricingItem::class,
        itemLabel: new Translatable('fluid_block_pricing_items_item_label'),
        titleField: 'name',
        max: 4,
        variants: ['cards', 'table'],
    )]
    public array $items = [];

    /** @var list<PriceListItem> */
    #[Collection(
        new Translatable('fluid_block_pricing_entries'),
        item: PriceListItem::class,
        itemLabel: new Translatable('fluid_block_pricing_entries_item_label'),
        titleField: 'name',
        max: 80,
        variants: ['list'],
    )]
    public array $entries = [];

    #[Collection(
        new Translatable('fluid_block_pricing_rows'),
        item: PricingRow::class,
        itemLabel: new Translatable('fluid_block_pricing_rows_item_label'),
        titleField: 'label',
        max: 20,
        variants: ['table'],
    )]
    public array $rows = [];

    #[Text(
        new Translatable('fluid_block_pricing_note'),
        maxLength: 200,
        placeholder: new Translatable('fluid_block_pricing_note_placeholder'),
    )]
    public string $note = '';
}
