<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\PriceListItem;
use Klxm\Fluid\Kit\Fluid\Module\Item\PricingItem;
use Klxm\Fluid\Kit\Fluid\Module\Item\PricingRow;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.pricing', 'Pakete / Vergleich')]
#[AsBlock(
    icon: 'currency-eur',
    group: 'Inhalt',
    help: 'Pakete als Karten oder als Vergleichstabelle, oder eine Preisliste wie auf einer Speisekarte. Ein Paket lässt sich hervorheben. Nur verbindliche, aktuelle Preise angeben.',
    variants: ['cards' => 'Karten', 'table' => 'Vergleichstabelle', 'list' => 'Preisliste (Speisekarte)'],
    variantHelp: ['list' => 'Name, Pünktchen, Preis – für Speisekarten, Preislisten, Gebühren. Beliebig viele Einträge, jeweils mit kurzer Beschreibung.'],
)]
final class Pricing extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Pakete', item: PricingItem::class, itemLabel: 'Paket', titleField: 'name', max: 4, variants: ['cards', 'table'])]
    public array $items = [];

    /** @var list<PriceListItem> */
    #[Collection('Preisliste', item: PriceListItem::class, itemLabel: 'Eintrag', titleField: 'name', max: 80, variants: ['list'])]
    public array $entries = [];

    #[Collection(
        'Vergleichszeilen (bei „Vergleichstabelle“)',
        item: PricingRow::class,
        itemLabel: 'Zeile',
        titleField: 'label',
        max: 20,
        variants: ['table'],
    )]
    public array $rows = [];

    #[Text(
        'Hinweis unter den Paketen (optional)',
        maxLength: 200,
        placeholder: 'z. B. Alle Preise inkl. MwSt.',
    )]
    public string $note = '';
}
