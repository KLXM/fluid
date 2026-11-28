<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\HeroCard;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.hero', new Translatable('fluid_block_hero'))]
#[AsFluidBlock(
    icon: 'star',
    group: new Translatable('fluid_block_group_header'),
    help: new Translatable('fluid_block_hero_help'),
    variants: [
        'split' => new Translatable('fluid_block_hero_variant_split'),
        'centered' => new Translatable('fluid_block_hero_variant_centered'),
        'fullbleed' => new Translatable('fluid_block_hero_variant_fullbleed'),
        'cards' => new Translatable('fluid_block_hero_variant_cards'),
        'type' => new Translatable('fluid_block_hero_variant_type'),
        'compact' => new Translatable('fluid_block_hero_variant_compact'),
        'scale' => new Translatable('fluid_block_hero_variant_scale'),
    ],
    variantHelp: [
        'split' => new Translatable('fluid_block_hero_variant_split_help'),
        'centered' => new Translatable('fluid_block_hero_variant_centered_help'),
        'fullbleed' => new Translatable('fluid_block_hero_variant_fullbleed_help'),
        'cards' => new Translatable('fluid_block_hero_variant_cards_help'),
        'type' => new Translatable('fluid_block_hero_variant_type_help'),
        'compact' => new Translatable('fluid_block_hero_variant_compact_help'),
        'scale' => new Translatable('fluid_block_hero_variant_scale_help'),
    ],
)]
final class Hero extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_block_hero_title'),
        required: true,
        maxLength: 110,
        note: new Translatable('fluid_block_hero_title_help'),
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text'),
        maxLength: 360,
        rows: 3,
    )]
    public string $text = '';

    #[Text(
        new Translatable('fluid_field_button1_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $buttonLabel = '';

    #[Link(
        new Translatable('fluid_field_button1_link'),
        width: 'half',
        note: new Translatable('fluid_field_link_help'),
    )]
    public string $buttonLink = '';

    #[Text(
        new Translatable('fluid_field_button2_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $button2Label = '';

    #[Link(
        new Translatable('fluid_field_button2_link'),
        width: 'half',
    )]
    public string $button2Link = '';

    #[Textarea(
        new Translatable('fluid_block_hero_points'),
        rows: 3,
        note: new Translatable('fluid_block_hero_points_help'),
    )]
    public string $points = '';

    #[MediaPicker(
        new Translatable('fluid_field_image'),
        width: 'half',
        note: new Translatable('fluid_block_hero_image_help'),
        preview: true,
    )]
    public ?Media $image = null;

    #[Choice(
        new Translatable('fluid_field_ratio'),
        required: true,
        choices: self::ratios(...),
        width: 'half',
        note: new Translatable('fluid_block_hero_ratio_help'),
    )]
    public string $ratio = '4:5';

    #[MediaPicker(
        new Translatable('fluid_block_hero_video'),
        width: 'half',
        variants: ['fullbleed'],
        note: new Translatable('fluid_block_hero_video_help'),
    )]
    public ?Media $video = null;

    #[Choice(
        new Translatable('fluid_block_hero_overlay'),
        required: true,
        choices: ['strong' => new Translatable('fluid_block_hero_overlay_strong'), 'medium' => new Translatable('fluid_size_m'), 'gradient' => new Translatable('fluid_block_hero_overlay_gradient')],
        width: 'half',
        variants: ['fullbleed'],
    )]
    public string $overlay = 'strong';

    #[Choice(
        new Translatable('fluid_block_hero_video_toggle'),
        required: true,
        choices: ['icon' => new Translatable('fluid_field_icon'), 'text' => new Translatable('fluid_block_hero_video_toggle_text')],
        width: 'half',
        variants: ['fullbleed'],
        note: new Translatable('fluid_block_hero_video_toggle_help'),
    )]
    public string $videoToggle = 'icon';

    #[Collection(
        new Translatable('fluid_block_hero_cards'),
        item: HeroCard::class,
        itemLabel: new Translatable('fluid_block_hero_cards_item_label'),
        titleField: 'title',
        max: 3,
        variants: ['cards'],
    )]
    public array $cards = [];

    #[Textarea(
        new Translatable('fluid_block_hero_scale_items'),
        rows: 4,
        variants: ['scale'],
        note: new Translatable('fluid_block_hero_scale_items_help'),
    )]
    public string $scaleItems = '';
}
