<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Dateien (bei „Einzelne Dateien“)“ in the block Downloads */
final class DownloadsFile
{
    #[Text(
        new Translatable('fluid_field_label'),
        width: 'half',
        note: new Translatable('fluid_block_downloads_item_label_help'),
    )]
    public string $label = '';

    #[MediaPicker(
        new Translatable('fluid_block_downloads_item'),
        width: 'half',
    )]
    public ?Media $file = null;

    #[Text(new Translatable('fluid_block_downloads_item_note'))]
    public string $note = '';
}
