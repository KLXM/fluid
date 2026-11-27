<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Reiter“ im Block Tabs */
final class TabsItem
{
    #[Text(
        'Beschriftung des Reiters',
        required: true,
        maxLength: 40,
    )]
    public string $label = '';

    #[RichText('Inhalt')]
    public string $text = '';

    #[MediaPicker(
        'Bild (optional, 4:3)',
        preview: true,
    )]
    public ?Media $image = null;
}
