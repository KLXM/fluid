<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\BentoItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.bento', new Translatable('fluid_block_bento'))]
#[AsFluidBlock(
    icon: 'squares-four',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_bento_help'),
    nestable: false,
)]
final class Bento extends FluidBlock
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
        new Translatable('fluid_block_bento_items'),
        item: BentoItem::class,
        itemLabel: new Translatable('fluid_block_bento_item'),
        titleField: 'title',
        max: 10,
    )]
    public array $items = [];
}
