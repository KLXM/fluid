<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Folie im Block „Slider“ */
final class Slide
{
    #[MediaPicker(
        new Translatable('fluid_field_image'),
        required: true,
        types: ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        preview: true,
    )]
    public ?Media $image = null;

    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_block_slider_item_title'),
        maxLength: 90,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text_optional'),
        maxLength: 300,
        rows: 2,
    )]
    public string $text = '';

    #[Text(
        new Translatable('fluid_field_button_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $buttonLabel = '';

    #[Link(
        new Translatable('fluid_field_button_link'),
        width: 'half',
    )]
    public string $buttonLink = '';

    #[Choice(new Translatable('fluid_block_slider_item_position'), required: true, width: 'half', choices: [
        'bottom-left' => new Translatable('fluid_block_slider_item_position_bottom_left'), 'bottom-center' => new Translatable('fluid_block_slider_item_position_bottom_center'), 'center-left' => new Translatable('fluid_block_slider_item_position_center_left'), 'center' => new Translatable('fluid_block_slider_item_position_center'), 'top-left' => new Translatable('fluid_block_slider_item_position_top_left'),
    ])]
    public string $position = 'bottom-left';

    #[Choice(new Translatable('fluid_block_slider_item_overlay'), required: true, width: 'half', choices: [
        'dark' => new Translatable('fluid_block_slider_item_overlay_dark'), 'light' => new Translatable('fluid_block_slider_item_overlay_light'), 'box' => new Translatable('fluid_block_slider_item_overlay_box'), 'none' => new Translatable('fluid_block_slider_item_overlay_none'),
    ])]
    public string $overlay = 'dark';
}
