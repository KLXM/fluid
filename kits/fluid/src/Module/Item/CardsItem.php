<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Karten“ im Block Cards */
final class CardsItem
{
    #[MediaPicker('Bild', preview: true)]
    public ?Media $image = null;

    #[Text('Dachzeile (optional)', maxLength: 40, width: 'half')]
    public string $eyebrow = '';

    #[Text('Titel', required: true, maxLength: 90, width: 'half')]
    public string $title = '';

    #[Textarea('Text', maxLength: 240, rows: 2)]
    public string $text = '';

    #[Text('Link – Beschriftung (optional)', maxLength: 32, width: 'half')]
    public string $linkLabel = '';

    #[Link('Link', width: 'half')]
    public string $link = '';
}
