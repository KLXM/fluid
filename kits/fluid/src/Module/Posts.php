<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Blog;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Link;
use Klxm\Fluid\Field\Number;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.posts', 'Beiträge')]
#[AsBlock(
    icon: 'newspaper',
    group: 'Website',
    help: 'Beiträge eines Blogs (Menüseite mit „Blog / News“ in den Seiteneinstellungen): Übersicht mit Blättern und Schlagworten oder die neuesten Beiträge, z. B. auf der Startseite.',
    variants: ['cards' => 'Karten', 'list' => 'Liste', 'featured' => 'Aufmacher + Karten'],
    nestable: ['list'],
    reload: true,
)]
final class Posts extends FluidBlock
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

    #[Blog(
        'Blog',
        width: 'half',
    )]
    public string $blog = '';

    #[Number(
        'Beiträge je Seite',
        width: 'half',
    )]
    public int $limit = 9;

    #[Checkbox(
        'Blättern (Seite 2, 3 …)',
        width: 'half',
    )]
    public bool $pager = true;

    #[Checkbox(
        'Schlagworte zum Filtern zeigen',
        width: 'half',
    )]
    public bool $tags = false;

    #[Choice(
        'Breite je Eintrag (mindestens)',
        required: true,
        choices: self::SIZES,
        width: 'half',
        note: 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.',
    )]
    public string $size = 'm';

    #[Link(
        'Link „Alle Beiträge“ (optional)',
        width: 'half',
        note: 'Für „Neueste Beiträge“ auf anderen Seiten, z. B. zur Blog-Übersicht.',
    )]
    public string $more = '';

    #[Text(
        'Hinweis ohne Beiträge (optional)',
        maxLength: 200,
    )]
    public string $emptyText = '';
}
