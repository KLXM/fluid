<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\ScrollyItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.scrolly', new Translatable('fluid_block_scrolly'))]
#[AsBlock(
    icon: 'article',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_scrolly_help'),
    variants: ['left' => new Translatable('fluid_block_scrolly_variant_left'), 'right' => new Translatable('fluid_block_scrolly_variant_right')],
)]
final class Scrolly extends FluidBlock
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
        new Translatable('fluid_field_ratio'),
        required: true,
        choices: self::scrollyRatios(...),
        width: 'half',
        note: new Translatable('fluid_block_scrolly_ratio_help'),
    )]
    public string $ratio = 'auto';

    #[Collection(
        new Translatable('fluid_block_scrolly_items'),
        item: ScrollyItem::class,
        itemLabel: new Translatable('fluid_block_scrolly_items_item_label'),
        titleField: 'title',
        max: 8,
    )]
    public array $items = [];

    /**
     * Image formats of the block: automatic (per image, no cropping) or one of the formats.
     *
     * @return array<string, Translatable>
     */
    public static function scrollyRatios(): array
    {
        return ['auto' => new Translatable('fluid_block_scrolly_ratio_auto')] + self::ratios();
    }
}
