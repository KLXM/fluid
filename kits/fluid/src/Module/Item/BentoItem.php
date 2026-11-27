<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Icon;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Kacheln“ im Block Bento */
final class BentoItem
{
    #[Choice(
        'Größe',
        required: true,
        choices: ['s' => 'Normal', 'wide' => 'Breit', 'tall' => 'Hoch', 'big' => 'Groß (breit + hoch)'],
        width: 'half',
    )]
    public string $size = 's';

    #[Choice(
        'Fläche',
        required: true,
        choices: [
            'card' => 'Karte',
            'tint' => 'Akzent hell',
            'highlight' => 'Zweitfarbe',
            'accent' => 'Akzentfarbe',
            'dark' => 'Dunkel',
            'image' => 'Bild mit Text darauf',
        ],
        width: 'half',
    )]
    public string $tone = 'card';

    #[Icon(
        'Symbol (optional)',
        width: 'half',
    )]
    public string $icon = '';

    #[MediaPicker(
        'Bild (optional)',
        width: 'half',
        preview: true,
    )]
    public ?Media $image = null;

    #[Text(
        'Dachzeile oder große Zahl (optional)',
        maxLength: 30,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        'Titel',
        required: true,
        maxLength: 80,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        'Text',
        maxLength: 240,
        rows: 2,
    )]
    public string $text = '';

    #[Link('Link (ganze Kachel, optional)')]
    public string $link = '';
}
