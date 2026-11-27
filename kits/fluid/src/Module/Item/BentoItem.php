<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Kacheln“ im Block Bento */
final class BentoItem
{
    #[Choice(
        new Translatable('fluid_block_bento_item_size'),
        required: true,
        choices: ['s' => new Translatable('fluid_block_bento_item_size_s'), 'wide' => new Translatable('fluid_block_bento_item_size_wide'), 'tall' => new Translatable('fluid_block_bento_item_size_tall'), 'big' => new Translatable('fluid_block_bento_item_size_big')],
        width: 'half',
    )]
    public string $size = 's';

    #[Choice(
        new Translatable('fluid_block_bento_item_tone'),
        required: true,
        choices: [
            'card' => new Translatable('fluid_block_bento_item_tone_card'),
            'tint' => new Translatable('fluid_block_bento_item_tone_tint'),
            'highlight' => new Translatable('fluid_block_bento_item_tone_highlight'),
            'accent' => new Translatable('fluid_block_bento_item_tone_accent'),
            'dark' => new Translatable('fluid_block_bento_item_tone_dark'),
            'image' => new Translatable('fluid_block_bento_item_tone_image'),
        ],
        width: 'half',
    )]
    public string $tone = 'card';

    #[Icon(
        new Translatable('fluid_field_icon_optional'),
        width: 'half',
    )]
    public string $icon = '';

    #[MediaPicker(
        new Translatable('fluid_field_image_optional'),
        width: 'half',
        preview: true,
    )]
    public ?Media $image = null;

    #[Text(
        new Translatable('fluid_block_bento_item_eyebrow'),
        maxLength: 30,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 80,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text'),
        maxLength: 240,
        rows: 2,
    )]
    public string $text = '';

    #[Link(new Translatable('fluid_block_bento_item_link'))]
    public string $link = '';
}
