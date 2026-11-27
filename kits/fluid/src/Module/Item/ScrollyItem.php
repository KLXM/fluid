<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Abschnitte“ in the block Scrolly */
final class ScrollyItem
{
    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 40,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_item_title'),
        required: true,
        maxLength: 90,
        width: 'half',
    )]
    public string $title = '';

    // Formerly a text field: old plain text appears unchanged as before (fluid_is_plain_text), the editor turns it into paragraphs
    #[RichText(
        new Translatable('fluid_field_text'),
        note: new Translatable('fluid_block_scrolly_item_text_help'),
    )]
    public string $text = '';

    #[MediaPicker(
        new Translatable('fluid_field_image_optional'),
        preview: true,
        note: new Translatable('fluid_block_scrolly_item_image_help'),
    )]
    public ?Media $image = null;
}
