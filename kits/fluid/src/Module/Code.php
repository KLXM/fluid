<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\CodeItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.code', new Translatable('fluid_block_code'))]
#[AsFluidBlock(
    icon: 'code',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_code_help'),
    nestable: true,
)]
final class Code extends FluidBlock
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
        new Translatable('fluid_block_code_items'),
        item: CodeItem::class,
        itemLabel: new Translatable('fluid_block_code_item'),
        titleField: 'file',
        min: 1,
        max: 6,
    )]
    public array $items = [];

    #[Text(
        new Translatable('fluid_block_code_note'),
        maxLength: 240,
    )]
    public string $note = '';
}
