<?php

namespace Project\YForm;

use Klxm\Fluid\Data\KnowledgeTable;
use Yakamara\YForm\Attribute\AsTable;

/**
 * Wissensbereich „Handbuch“ (Beispiel): Tabellenart „Wissen“ aus fluid – Kapitelbaum, Nummern, Bereiche, Schlagworte,
 * verwandte Kapitel, Glossar-Bezug, „Zuletzt geprüft“ und Inhalt aus Blöcken. Ausgabe über den Block „Datenliste“
 * (Darstellung „Inhaltsverzeichnis“ oder „Karten“, Detailansicht mit Kapitelnavigation).
 */
#[AsTable('rex_yf_handbuch', label: 'Handbuch (Beispiel)', icon: 'fa-book-open')]
final class HandbuchTable extends KnowledgeTable
{
    public function areas(): array
    {
        return ['start' => 'Erste Schritte', 'alltag' => 'Im Alltag', 'technik' => 'Technik'];
    }
}
