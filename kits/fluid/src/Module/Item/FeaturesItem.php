<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Einträge“ in the block Features */
final class FeaturesItem
{
    #[Icon(
        new Translatable('fluid_field_icon'),
        width: 'half',
    )]
    public string $icon = '';

    #[MediaPicker(
        new Translatable('fluid_block_features_item_image'),
        width: 'half',
        preview: true,
    )]
    public ?Media $image = null;

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 70,
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_text'),
        maxLength: 320,
        rows: 3,
    )]
    public string $text = '';

    #[Text(
        new Translatable('fluid_field_link_label'),
        maxLength: 32,
        width: 'half',
    )]
    public string $linkLabel = '';

    #[Link(
        new Translatable('fluid_field_link'),
        width: 'half',
    )]
    public string $link = '';
}
