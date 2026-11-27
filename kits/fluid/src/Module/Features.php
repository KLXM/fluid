<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\FeaturesItem;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.features', new Translatable('fluid_block_features'))]
#[AsBlock(
    icon: 'squares-four',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_features_help'),
    variants: [
        'cards' => new Translatable('fluid_block_features_variant_cards'),
        'icons' => new Translatable('fluid_block_features_variant_icons'),
        'list' => new Translatable('fluid_block_features_variant_list'),
        'numbered' => new Translatable('fluid_block_features_variant_numbered'),
    ],
    nestable: true,
)]
final class Features extends FluidBlock
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

    #[Collection(
        new Translatable('fluid_block_features_items'),
        item: FeaturesItem::class,
        itemLabel: new Translatable('fluid_block_features_item'),
        titleField: 'title',
        max: 12,
    )]
    public array $items = [];
}
