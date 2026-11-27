<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\ScrollyItem;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.scrolly', 'Scrollytelling')]
#[AsBlock(
    icon: 'article',
    group: 'Inhalt',
    help: 'Ein Bild bleibt stehen, während die Texte daneben vorbeiziehen; das Bild wechselt passend zum Abschnitt. Abschnitte ohne eigenes Bild behalten das vorige, Texte dürfen lang sein (Zwischenüberschriften, Links). Bei wenig Platz stehen Bild und Text jeweils untereinander.',
    variants: ['left' => 'Bild links', 'right' => 'Bild rechts'],
)]
final class Scrolly extends FluidBlock
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
        'Bildformat',
        required: true,
        choices: ['auto' => 'Automatisch (je Bild, ohne Zuschnitt)'] + self::RATIOS,
        width: 'half',
        note: '„Automatisch“ zeigt jedes Bild ganz in seinem eigenen Format – Hochformate bleiben ganz.',
    )]
    public string $ratio = 'auto';

    #[Collection(
        'Abschnitte',
        item: ScrollyItem::class,
        itemLabel: 'Abschnitt',
        titleField: 'title',
        max: 8,
    )]
    public array $items = [];
}
