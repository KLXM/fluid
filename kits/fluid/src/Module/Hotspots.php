<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Hotspots as HotspotsField;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;

/**
 * Interactive image: image from the media pool with markers (point, circle, rectangle, area) – per marker a
 * tooltip with title, text, link and optionally a glossary term. Data format Klxm\Fluid\Hotspots (version 1, like
 * KLXM Studio). Output: views/hotspots.php, styles css/hotspots.css, script js/hotspots.js.
 */
#[AsModule('fluid.hotspots', 'Interaktives Bild')]
#[AsBlock(
    icon: 'map-pin',
    group: 'Medien',
    help: 'Ein Bild aus dem Medienpool mit Markierungen: Punkte oder Flächen, die per Klick oder Tastatur ein Hinweisfenster öffnen. Auf dem Telefon stehen alle Markierungen zusätzlich als Liste unter dem Bild.',
    nestable: true,
)]
final class Hotspots extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half', note: 'Benennt das Bild auch für Screenreader („Interaktives Bild: …“).')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[MediaPicker('Bild', preview: true, note: 'Alt-Text im Medienpool pflegen. Auch Grafiken (SVG) sind möglich.')]
    public ?Media $image = null;

    #[HotspotsField('Markierungen', image: 'image')]
    public array $hotspots = [];

    #[Choice(
        'Liste der Markierungen unter dem Bild',
        required: true,
        choices: ['auto' => 'Auf schmalen Bildschirmen (Telefon)', 'always' => 'Immer'],
        width: 'half',
        note: 'Die Liste (Nummer, Titel, Text) ist immer für Screenreader da; auf großen Bildschirmen zeigt sie sich bei Bedarf.',
    )]
    public string $list = 'auto';

    #[Choice('Breite', required: true, choices: ['normal' => 'Inhaltsbreite', 'text' => 'Textbreite (wie Fließtext)'], width: 'half')]
    public string $width = 'normal';
}
