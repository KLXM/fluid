<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\StepsItem;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.steps', 'Ablauf / Zeitleiste')]
#[AsBlock(
    icon: 'path',
    group: 'Inhalt',
    help: 'Nummerierte Schritte, eine Zeitleiste mit Datum/Phase oder ein Prozess mit Verbindungslinien.',
    variants: ['numbers' => 'Nummerierte Schritte', 'timeline' => 'Zeitleiste', 'process' => 'Prozess mit Pfeilen'],
    nestable: ['numbers', 'timeline'],
)]
final class Steps extends FluidBlock
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

    #[Textarea(
        'Einleitung (optional)',
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[Choice(
        'Kreise',
        required: true,
        choices: [
            'auto' => 'Wie Design',
            'filled' => 'Gefüllt',
            'soft' => 'Zart gefüllt mit Rand',
            'ring' => 'Nur Rand',
            'dot' => 'Kleiner Punkt (ohne Nummer)',
        ],
        width: 'half',
        variants: ['timeline', 'process'],
    )]
    public string $marker = 'auto';

    #[Choice(
        'Farben',
        required: true,
        choices: [
            'auto' => 'Wie Design',
            'alternate' => 'Farbig abwechselnd',
            'accent' => 'Eine Farbe (Akzent)',
            'neutral' => 'Neutral (Schriftfarbe)',
        ],
        width: 'half',
        variants: ['timeline', 'process', 'numbers'],
    )]
    public string $colors = 'auto';

    #[Choice(
        'Animation',
        required: true,
        choices: [
            'auto' => 'Wie Website (einblenden)',
            'sequence' => 'Nacheinander einblenden',
            'draw' => 'Linie wächst beim Scrollen (Zeitleiste)',
            'none' => 'Keine Animation',
        ],
        width: 'half',
        note: 'Animationen entfallen, wenn Besucher in ihrem System „Bewegung reduzieren“ eingestellt haben. „Nacheinander einblenden“ braucht die Bewegung der Website (Design).',
    )]
    public string $motion = 'auto';

    #[Collection(
        'Schritte',
        item: StepsItem::class,
        itemLabel: 'Schritt',
        titleField: 'title',
        max: 12,
    )]
    public array $items = [];
}
