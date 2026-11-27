<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\FaqItem;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.faq', 'Fragen & Antworten')]
#[AsBlock(
    icon: 'question',
    group: 'Inhalt',
    help: 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
    variants: ['split' => 'Überschrift links, Fragen rechts', 'stacked' => 'Untereinander, zentriert'],
    nestable: ['stacked'],
    jsonld: ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
)]
final class Faq extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', required: true, maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Collection('Fragen', item: FaqItem::class, itemLabel: 'Frage', titleField: 'q')]
    public array $items = [];
}
