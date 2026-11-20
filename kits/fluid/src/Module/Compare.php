<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\{Choice, MediaPicker, Text, Textarea};
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

#[AsModule('fluid.compare', 'Vorher/Nachher')]
#[AsBlock(
    icon: 'square-half',
    group: 'Medien',
    help: 'Zwei Bilder übereinander, ein Regler zeigt mehr vom einen oder anderen – für Umbau, Renovierung, Restaurierung, Bildbearbeitung. Beide Bilder im selben Ausschnitt aufnehmen. Ohne JavaScript stehen sie nebeneinander.',
    nestable: true,
)]
final class Compare extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[MediaPicker('Bild vorher', required: true, width: 'half', preview: true, note: 'Beide Bilder brauchen einen Alternativtext im Medienpool, der den Zustand beschreibt, z. B. „Küche vor dem Umbau: alte Fliesen, dunkle Schränke“. Fehlt er, zeigt der Block beim Bearbeiten einen Hinweis.')]
    public ?Media $before = null;

    #[MediaPicker('Bild nachher', required: true, width: 'half', preview: true)]
    public ?Media $after = null;

    #[Text('Beschriftung vorher', maxLength: 24, width: 'half', placeholder: 'Vorher')]
    public string $labelBefore = '';

    #[Text('Beschriftung nachher', maxLength: 24, width: 'half', placeholder: 'Nachher')]
    public string $labelAfter = '';

    #[Choice('Bildformat', required: true, choices: self::RATIOS, width: 'half')]
    public string $ratio = '16:9';

    #[Text('Bildunterschrift (optional)', maxLength: 200)]
    public string $caption = '';
}
