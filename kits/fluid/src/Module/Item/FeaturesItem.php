<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Einträge“ im Block Features */
final class FeaturesItem
{
    #[Icon('Symbol', width: 'half')]
    public string $icon = '';

    #[MediaPicker('oder Bild (3:2)', width: 'half', preview: true)]
    public ?Media $image = null;

    #[Text('Titel', required: true, maxLength: 70)]
    public string $title = '';

    #[Textarea('Text', maxLength: 320, rows: 3)]
    public string $text = '';

    #[Text('Link – Beschriftung (optional)', maxLength: 32, width: 'half')]
    public string $linkLabel = '';

    #[Link('Link', width: 'half')]
    public string $link = '';
}
