<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Bild im Block „Bildergalerie“ */
final class GalleryImage
{
    #[MediaPicker(
        'Bild',
        required: true,
        types: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        preview: true,
        width: 'half',
    )]
    public ?Media $image = null;

    #[Text(
        'Bildunterschrift (optional)',
        maxLength: 160,
        width: 'half',
    )]
    public string $caption = '';
}
