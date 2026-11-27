<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;

/** Entry of „Abschnitte“ in the block Scrolly */
final class ScrollyItem
{
    #[Text('Dachzeile (optional)', maxLength: 40, width: 'half')]
    public string $eyebrow = '';

    #[Text('Titel', required: true, maxLength: 90, width: 'half')]
    public string $title = '';

    // Formerly a text field: old plain text appears unchanged as before (fluid_is_plain_text), the editor turns it into paragraphs
    #[RichText('Text', note: 'Zwischenüberschriften, Fettdruck, Links und Listen möglich. Gut lesbar bis etwa 2.500 Zeichen je Abschnitt – längere Texte lieber teilen.')]
    public string $text = '';

    #[MediaPicker('Bild (optional)', preview: true, note: 'Ohne Bild bleibt das Bild des vorigen Abschnitts stehen.')]
    public ?Media $image = null;
}
