<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\LogosItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.logos', new Translatable('fluid_block_logos'))]
#[AsFluidBlock(
    icon: 'handshake',
    group: new Translatable('fluid_block_group_media'),
    help: new Translatable('fluid_block_logos_help'),
    variants: ['grid' => new Translatable('fluid_block_logos_variant_grid'), 'marquee' => new Translatable('fluid_block_logos_variant_marquee')],
    nestable: ['grid'],
)]
final class Logos extends FluidBlock
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
        new Translatable('fluid_block_logos_items'),
        item: LogosItem::class,
        itemLabel: new Translatable('fluid_block_logos_items_item_label'),
        titleField: 'name',
        max: 16,
    )]
    public array $items = [];
}
