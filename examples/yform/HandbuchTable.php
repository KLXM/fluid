<?php

namespace Project\YForm;

use Klxm\Fluid\Data\KnowledgeTable;
use Yakamara\YForm\Attribute\AsTable;

/**
 * Knowledge base „Handbuch“ (example): table kind „Wissen“ from fluid – chapter tree, numbers, areas, tags,
 * related chapters, glossary reference, „Zuletzt geprüft“ and content from blocks. Output via the block „Datenliste“
 * (display „Inhaltsverzeichnis“ or „Karten“, detail view with chapter navigation).
 */
#[AsTable('rex_yf_handbuch', label: 'Handbuch (Beispiel)', icon: 'fa-book-open')]
final class HandbuchTable extends KnowledgeTable
{
    public function areas(): array
    {
        return ['start' => 'Erste Schritte', 'alltag' => 'Im Alltag', 'technik' => 'Technik'];
    }
}
