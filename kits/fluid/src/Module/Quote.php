<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\QuoteItem;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.quote', 'Zitat / Stimmen')]
#[AsBlock(
    icon: 'quotes',
    group: 'Inhalt',
    help: '„Groß“: ein Zitat als Blickfang (Pull-Quote). „Raster“, „Band“ und „Laufband“ für mehrere Stimmen (Laufband hält bei Maus oder Fokus an). Nur echte, freigegebene Stimmen verwenden.',
    variants: [
        'single' => 'Groß (ein Zitat)',
        'grid' => 'Raster (Mauerwerk)',
        'reel' => 'Wischbares Band',
        'marquee' => 'Laufband (läuft langsam durch)',
    ],
    nestable: ['single', 'grid'],
)]
final class Quote extends FluidBlock
{
    #[Text(
        'Dachzeile (optional)',
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        'Überschrift',
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Collection(
        'Zitate',
        item: QuoteItem::class,
        itemLabel: 'Zitat',
        titleField: 'name',
        max: 12,
    )]
    public array $items = [];
}
