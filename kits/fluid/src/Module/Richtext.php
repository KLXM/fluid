<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;   // Klasse dieses Blocks heißt Richtext
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\RichText as RichTextField;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.richtext', 'Fließtext / Artikel')]
#[AsBlock(
    icon: 'paragraph',
    group: 'Inhalt',
    help: 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: angenehme Lesebreite mit automatischem Inhaltsverzeichnis.',
    variants: [
        'standard' => 'Standard',
        'article' => 'Artikel mit Inhaltsverzeichnis',
        'columns' => 'Mehrspaltig (Zeitungssatz)',
    ],
    nestable: ['standard', 'columns'],
    drags: [
        [
            'field' => 'measure',
            'target' => '.prose:not(.prose--columns)',
            'class' => 'prose--m-{v}',
            'label' => 'Textbreite',
        ],
    ],
)]
final class Richtext extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[RichTextField('Text')]
    public string $text = '';

    #[Choice(
        'Textbreite',
        required: true,
        choices: ['narrow' => 'Schmal', 'normal' => 'Lesebreite', 'wide' => 'Breit', 'full' => 'Volle Breite'],
        width: 'half',
        variants: ['standard', 'article'],
        note: 'Auch auf der Seite einstellbar: am rechten Rand des Textes ziehen.',
    )]
    public string $measure = 'normal';

    #[Text(
        'Angaben über dem Text (optional, bei „Artikel“)',
        maxLength: 120,
        placeholder: 'z. B. Redaktion · 12. März 2026',
    )]
    public string $meta = '';

    #[Checkbox('Initiale (großer erster Buchstabe)', width: 'half')]
    public bool $dropcap = false;

    #[Checkbox('Inhaltsverzeichnis anzeigen (bei „Artikel“)', width: 'half')]
    public bool $toc = true;

    #[Choice(
        'Einblenden, wenn der Text in den Blick kommt',
        required: true,
        choices: ['off' => 'Aus', 'block' => 'Ganzer Block', 'paragraphs' => 'Absätze nacheinander'],
        width: 'half',
        note: 'Art der Bewegung: Design → Bewegung & Farbschema („Art des Einblendens“). Nur mit eingeschalteten Animationen; Besucher mit „Bewegung reduzieren“ sehen den Text sofort.',
    )]
    public string $reveal = 'off';

    #[Choice(
        'Beim Scrollen mitlaufen (Inhaltsverzeichnis)',
        required: true,
        choices: [
            'off' => 'Aus',
            'mark' => 'Aktuellen Abschnitt markieren',
            'progress' => 'Markieren mit Lesefortschritt',
        ],
        width: 'half',
        variants: ['article'],
        note: 'Die Markierung gleitet zum Abschnitt, der gerade gelesen wird. Ohne Bewegung (Design oder „Bewegung reduzieren“) springt sie ohne Animation.',
    )]
    public string $scrollspy = 'off';
}
