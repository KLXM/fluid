<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\BentoItem;
use Redaxo\Core\Content\AsModule;

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
