<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Logos“ im Block Logos */
final class LogosItem
{
    #[Text(
        new Translatable('fluid_field_name'),
        required: true,
        width: 'half',
    )]
    public string $name = '';

    #[Link(
        new Translatable('fluid_block_logos_item_link'),
        width: 'half',
    )]
    public string $link = '';

    #[MediaPicker(
        new Translatable('fluid_block_logos_item_image'),
        preview: true,
    )]
    public ?Media $image = null;
}
