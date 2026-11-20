<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Eintrag von „Audiodateien“ im Block „Video & Audio“ (Darstellung „Audio“) */
final class AudioTrack
{
    #[MediaPicker('Audiodatei', required: true, types: ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'flac'])]
    public ?Media $file = null;

    #[Text('Titel', maxLength: 90, width: 'half', placeholder: 'sonst der Titel im Medienpool')]
    public string $title = '';

    #[Text('Sprecher oder Interpret (optional)', maxLength: 90, width: 'half')]
    public string $artist = '';

    #[MediaPicker('Kapitel (optional, .vtt oder .srt)', types: ['vtt', 'srt'], note: 'Kapitelmarken in der Zeitleiste und als Liste zum Anklicken.')]
    public ?Media $chapters = null;
}
