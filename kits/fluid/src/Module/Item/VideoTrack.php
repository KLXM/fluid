<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Untertitel und Kapitel (eigene Videodatei)“ im Block Video */
final class VideoTrack
{
    #[MediaPicker('Datei (.vtt oder .srt)', required: true, types: ['vtt', 'srt'])]
    public ?Media $file = null;

    #[Text('Sprache (Kürzel)', required: true, maxLength: 10, placeholder: 'de', width: 'half')]
    public string $lang = '';

    #[Choice(
        'Art',
        choices: [
            'subtitles' => 'Untertitel',
            'captions' => 'Untertitel für Gehörlose (mit Geräuschen)',
            'chapters' => 'Kapitel',
        ],
        width: 'half',
    )]
    public string $kind = 'subtitles';

    #[Text('Bezeichnung im Player (optional)', maxLength: 40, placeholder: 'automatisch, z. B. „Deutsch“')]
    public string $label = '';
}
