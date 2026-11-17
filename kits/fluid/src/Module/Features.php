<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\{Choice, Collection, Icon, Link, MediaPicker, Text, Textarea};
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;
use Klxm\Fluid\Kit\Fluid\Module\Item\FeaturesItem;

#[AsModule('fluid.features', 'Merkmale / Leistungen')]
#[AsBlock(
    icon: 'squares-four',
    group: 'Inhalt',
    help: 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild. Anzahl nebeneinander ergibt sich aus dem Platz.',
    variants: [
        'cards' => 'Karten',
        'icons' => 'Große Symbole, ohne Rahmen',
        'list' => 'Liste mit Symbol links',
        'numbered' => 'Nummeriert',
    ],
    nestable: true,
)]
final class Features extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Choice(
        'Breite je Eintrag (mindestens)',
        required: true,
        choices: self::SIZES,
        width: 'half',
        note: 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.',
    )]
    public string $size = 'm';

    #[Collection('Einträge', item: FeaturesItem::class, itemLabel: 'Eintrag', titleField: 'title', max: 12)]
    public array $items = [];
}
