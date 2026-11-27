<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\ListTemplate;
use Klxm\Fluid\Field\Number;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Field\YFormField;
use Klxm\Fluid\Field\YFormTable;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.datalist', 'Datenliste')]
#[AsBlock(
    icon: 'rows',
    group: 'Daten',
    help: 'Einträge einer YForm-Tabelle als Karten, Liste oder Tabelle – mit Detailansicht auf derselben Seite. Die Tabelle ist eine Klasse im Projekt (#[AsTable]); im Bearbeiten-Modus öffnet „Bearbeiten“ am Eintrag die Maske von YForm im Popup.',
    requires: 'yform',
    variants: ['cards' => 'Karten', 'list' => 'Liste', 'table' => 'Tabelle', 'toc' => 'Inhaltsverzeichnis'],
    reload: true,
    listTemplate: true,
)]
final class Datalist extends FluidBlock
{
    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[YFormTable('YForm-Tabelle', required: true)]
    public string $table = '';

    #[ListTemplate('Vorlage', note: 'Eine Vorlage legt Darstellung, Felder, Sortierung, Filter und Breite fest – Änderungen an der Vorlage gelten überall, wo sie gewählt ist.')]
    public string $template = '';

    #[YFormField('Titel', width: 'half', note: 'Leer = erstes Textfeld')]
    public string $titleField = '';

    #[YFormField('Bild', width: 'half', note: 'Leer = erstes Medienfeld')]
    public string $imageField = '';

    #[YFormField('Text', width: 'half', note: 'Leer = erstes mehrzeiliges Feld')]
    public string $textField = '';

    #[YFormField('Zusatzangabe (z. B. Datum, Ort)', width: 'half')]
    public string $metaField = '';

    #[YFormField('Nur Einträge, bei denen …', width: 'half', note: 'z. B. „Online“')]
    public string $filterField = '';

    #[Text('… gleich ist', maxLength: 100, placeholder: '1', width: 'half')]
    public string $filterValue = '';

    #[YFormField('Sortieren nach', width: 'half', note: 'Leer = Einstellung der Tabelle')]
    public string $sortField = '';

    #[Choice(
        'Reihenfolge',
        required: true,
        choices: ['asc' => 'Aufsteigend (A–Z, älteste zuerst)', 'desc' => 'Absteigend (Z–A, neueste zuerst)'],
        width: 'half',
    )]
    public string $sortDir = 'asc';

    #[Checkbox('Spalten zum Sortieren anklickbar (bei „Tabelle“)', width: 'half', variants: ['table'], note: 'Sortiert die Einträge dieser Seite.')]
    public bool $tableSort = false;

    #[YFormField('Filter für Besucher nach … (bei „Karten“ und „Liste“)', width: 'half', variants: ['cards', 'list'], note: 'z. B. Kategorie – Besucher filtern die Einträge dieser Seite mit Knöpfen über der Liste.')]
    public string $visitorFilter = '';

    #[Number('Höchstens bzw. je Seite (0 = alle)', width: 'half')]
    public int $limit = 0;

    #[Checkbox('Blättern (Seite 2, 3 …)', width: 'half', note: 'Mit „Höchstens“ als Anzahl je Seite.')]
    public bool $pager = false;

    #[Choice(
        'Breite je Eintrag (mindestens)',
        required: true,
        choices: self::SIZES,
        width: 'half',
        note: 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.',
    )]
    public string $size = 'm';

    #[Checkbox(
        'Einträge verlinken (Detailansicht mit allen Feldern)',
        note: 'Eigene Adresse je Eintrag, z. B. /presse/titel-12 – mit Titel und Beschreibung für Suchmaschinen und in der Sitemap.',
    )]
    public bool $detail = true;

    #[Choice(
        'Einträge im Menü zeigen',
        choices: ['' => 'Keine', 'top' => 'Oberste Ebene', 'two' => 'Zwei Ebenen (Kapitel und Unterkapitel)'],
        width: 'half',
        note: 'Mit Detailansicht: Die Einträge erscheinen im Menü unter dieser Seite. Zwei Ebenen gibt es bei Tabellen der Art „Wissen“.',
    )]
    public string $menuEntries = '';

    #[Number('Höchstens im Menü (je Ebene)', min: 1, max: 50, width: 'half')]
    public int $menuLimit = 12;

    #[Choice(
        'Stil der Detailansicht (Wissen)',
        required: true,
        choices: ['magazin' => 'Magazin (mit Einstieg)', 'sachlich' => 'Sachlich (Handbuch, Satzung)'],
        note: 'Nur bei Tabellen der Art „Wissen“: Kapitelnavigation mit Suche, Abschnitten und Vor/Zurück.',
    )]
    public string $knowledgeStyle = 'magazin';

    #[Checkbox(
        '„In den Kalender“ in der Detailansicht anbieten (.ics)',
        note: 'Für Termine: Tabelle mit Datumsfeld (Datum oder Datum mit Uhrzeit, optional Ende und Ort). Besucher laden den Termin als Kalenderdatei.',
    )]
    public bool $ics = false;

    #[Text(
        'Text, wenn keine Einträge da sind',
        maxLength: 160,
        placeholder: 'Zurzeit gibt es hier keine Einträge.',
    )]
    public string $emptyText = '';
}
