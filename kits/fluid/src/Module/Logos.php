<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\{Collection, Link, MediaPicker, Text};
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Klxm\Fluid\Kit\Fluid\Module\Item\LogosItem;

#[AsModule('fluid.logos', 'Logos (Partner, Kunden)')]
#[AsBlock(
    icon: 'handshake',
    group: 'Medien',
    help: 'Als ruhiges Raster oder Laufband (ohne JavaScript; steht bei „Bewegung reduzieren“ still und hält bei Maus/Fokus an). Ohne Bild erscheint der Name als Schriftzug. Nur Logos mit Freigabe verwenden.',
    variants: ['grid' => 'Raster', 'marquee' => 'Laufband'],
    nestable: ['grid'],
)]
final class Logos extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Collection('Logos', item: LogosItem::class, itemLabel: 'Logo', titleField: 'name', max: 16)]
    public array $items = [];
}
