<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.cta', new Translatable('fluid_block_cta'))]
#[AsBlock(
    icon: 'megaphone',
    group: new Translatable('fluid_block_group_content'),
    background: 'accent',
    variants: [
        'band' => new Translatable('fluid_block_cta_variant_band'),
        'box' => new Translatable('fluid_block_cta_variant_box'),
        'split' => new Translatable('fluid_block_cta_variant_split'),
        'big' => new Translatable('fluid_block_cta_variant_big'),
    ],
    nestable: ['box', 'big'],
)]
final class Cta extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        required: true,
        maxLength: 110,
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text_optional'),
        maxLength: 300,
        rows: 2,
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

    #[MediaPicker(
        new Translatable('fluid_block_cta_image'),
        preview: true,
    )]
    public ?Media $image = null;
}
