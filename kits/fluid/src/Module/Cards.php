<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\CardsItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.cards', new Translatable('fluid_block_cards'))]
#[AsFluidBlock(
    icon: 'copy',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_cards_help'),
    variants: [
        'image' => new Translatable('fluid_block_cards_variant_image'),
        'overlay' => new Translatable('fluid_block_cards_variant_overlay'),
        'horizontal' => new Translatable('fluid_block_cards_variant_horizontal'),
        'reel' => new Translatable('fluid_block_cards_variant_reel'),
    ],
    nestable: ['image', 'horizontal', 'overlay'],
)]
final class Cards extends FluidBlock
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
        new Translatable('fluid_field_size'),
        required: true,
        choices: self::sizes(...),
        width: 'half',
        note: new Translatable('fluid_field_size_help'),
    )]
    public string $size = 'm';

    #[Choice(
        new Translatable('fluid_field_ratio'),
        required: true,
        choices: self::ratios(...),
        width: 'half',
    )]
    public string $ratio = '3:2';

    #[Collection(
        new Translatable('fluid_block_cards'),
        item: CardsItem::class,
        itemLabel: new Translatable('fluid_block_cards_item'),
        titleField: 'title',
        max: 12,
    )]
    public array $items = [];

    #[Checkbox(
        new Translatable('fluid_block_cards_filter'),
        width: 'half',
        variants: ['image', 'overlay', 'horizontal'],
        note: new Translatable('fluid_block_cards_filter_help'),
    )]
    public bool $filter = false;

    #[Text(
        new Translatable('fluid_block_cards_more_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $moreLabel = '';

    #[Link(
        new Translatable('fluid_block_cards_more_link'),
        width: 'half',
    )]
    public string $moreLink = '';
}
