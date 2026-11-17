<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\{Choice, Collection, Icon, Link, MediaPicker, Text, Textarea};
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Klxm\Fluid\Kit\Fluid\Module\Item\BentoItem;

#[AsModule('fluid.bento', 'Bento-Raster')]
#[AsBlock(
    icon: 'squares-four',
    group: 'Inhalt',
    help: 'Kacheln unterschiedlicher Größe in einem dichten Raster – für Leistungen, Zahlen und Bilder. Kacheln rücken bei wenig Platz automatisch untereinander.',
    nestable: false,
)]
final class Bento extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Kacheln', item: BentoItem::class, itemLabel: 'Kachel', titleField: 'title', max: 10)]
    public array $items = [];
}
