<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Bild im Block „Bildergalerie“ */
final class GalleryImage
{
    #[MediaPicker(
        new Translatable('fluid_field_image'),
        required: true,
        types: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        preview: true,
        width: 'half',
    )]
    public ?Media $image = null;

    #[Text(
        new Translatable('fluid_field_caption_optional'),
        maxLength: 160,
        width: 'half',
    )]
    public string $caption = '';
}
