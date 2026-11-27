<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Zitate“ im Block Quote */
final class QuoteItem
{
    #[Textarea(
        new Translatable('fluid_block_quote_item_text'),
        required: true,
        maxLength: 480,
        rows: 3,
    )]
    public string $text = '';

    #[Text(
        new Translatable('fluid_field_name'),
        width: 'half',
    )]
    public string $name = '';

    #[Text(
        new Translatable('fluid_block_quote_item_role'),
        width: 'half',
    )]
    public string $role = '';

    #[MediaPicker(
        new Translatable('fluid_block_quote_item_image'),
        preview: true,
    )]
    public ?Media $image = null;
}
