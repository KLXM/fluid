<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\StatsItem;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.stats', 'Kennzahlen')]
#[AsBlock(
    icon: 'chart-bar',
    group: 'Inhalt',
    help: 'Nur belegbare Zahlen verwenden.',
    variants: [
        'row' => 'Große Zahlen in einer Reihe',
        'cards' => 'Karten',
        'split' => 'Überschrift links, Zahlen rechts',
    ],
    nestable: ['row', 'cards'],
)]
final class Stats extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Kennzahlen', item: StatsItem::class, itemLabel: 'Kennzahl', titleField: 'value', max: 6)]
    public array $items = [];
}
