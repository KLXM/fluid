<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.media_text', new Translatable('fluid_block_media_text'))]
#[AsFluidBlock(
    icon: 'square-half',
    group: new Translatable('fluid_block_group_content'),
    help: new Translatable('fluid_block_media_text_help'),
    variants: [
        'auto' => new Translatable('fluid_block_media_text_variant_auto'),
        'right' => new Translatable('fluid_block_media_text_variant_right'),
        'left' => new Translatable('fluid_block_media_text_variant_left'),
        'split' => new Translatable('fluid_block_media_text_variant_split'),
        'overlap' => new Translatable('fluid_block_media_text_variant_overlap'),
    ],
    drags: [
        [
            'field' => 'split',
            'target' => '.mt__media',
            'class' => 'mt--s-{v}',
            'apply' => '.mt',
            'label' => new Translatable('fluid_block_media_text_drag_split'),
        ],
    ],
)]
final class MediaText extends FluidBlock
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        required: true,
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[RichText(new Translatable('fluid_field_text'))]
    public string $text = '';

    #[Textarea(
        new Translatable('fluid_block_media_text_list'),
        rows: 3,
    )]
    public string $list = '';

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
        new Translatable('fluid_field_image'),
        width: 'half',
        note: new Translatable('fluid_block_media_text_image_help'),
        preview: true,
    )]
    public ?Media $image = null;

    #[Choice(
        new Translatable('fluid_field_ratio'),
        required: true,
        choices: self::ratios(...),
        width: 'half',
    )]
    public string $ratio = '4:3';

    #[Choice(
        new Translatable('fluid_block_media_text_split'),
        required: true,
        choices: [
            'text' => new Translatable('fluid_block_media_text_split_text'),
            'even' => new Translatable('fluid_block_media_text_split_even'),
            'media' => new Translatable('fluid_block_media_text_split_media'),
            'mediaxl' => new Translatable('fluid_block_media_text_split_mediaxl'),
        ],
        width: 'half',
        variants: ['auto', 'right', 'left'],
        note: new Translatable('fluid_block_media_text_split_help'),
    )]
    public string $split = 'even';

    #[Choice(
        new Translatable('fluid_block_media_text_valign'),
        required: true,
        choices: ['follow' => new Translatable('fluid_block_media_text_valign_follow'), 'top' => new Translatable('fluid_block_media_text_valign_top'), 'center' => new Translatable('fluid_block_media_text_valign_center')],
        width: 'half',
        variants: ['auto', 'right', 'left'],
        note: new Translatable('fluid_block_media_text_valign_help'),
    )]
    public string $valign = 'follow';
}
