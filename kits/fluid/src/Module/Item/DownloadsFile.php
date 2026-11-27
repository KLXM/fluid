<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Entry of „Dateien (bei „Einzelne Dateien“)“ in the block Downloads */
final class DownloadsFile
{
    #[Text(
        'Bezeichnung',
        width: 'half',
        note: 'Leer = Titel der Datei',
    )]
    public string $label = '';

    #[MediaPicker(
        'Datei',
        width: 'half',
    )]
    public ?Media $file = null;

    #[Text('Hinweis (optional)')]
    public string $note = '';
}
