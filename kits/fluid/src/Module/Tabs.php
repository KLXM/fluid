<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\TabsItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.tabs', new Translatable('fluid_block_tabs'))]
#[AsFluidBlock(
    icon: 'list-bullets',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_tabs_help'),
    variants: ['top' => new Translatable('fluid_block_tabs_variant_top'), 'side' => new Translatable('fluid_block_tabs_variant_side')],
)]
final class Tabs extends FluidBlock
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
        new Translatable('fluid_block_tabs_items'),
        item: TabsItem::class,
        itemLabel: new Translatable('fluid_block_tabs_items_item_label'),
        titleField: 'label',
        max: 8,
    )]
    public array $items = [];
}
