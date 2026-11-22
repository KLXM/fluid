<?php

namespace Klxm\Fluid\Data;

use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Choice;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Integer;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Relation\BelongsTo;
use Yakamara\YForm\Field\Relation\ManyToMany;
use Yakamara\YForm\Field\Relation\OnDelete;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

// Nur mit YForm (fluid läuft auch ohne)
if (!class_exists(Table::class)) {
    return;
}

/**
 * Tabellenart „Wissen“ (Wissensbereich, Handbuch, Satzung …): Kapitel mit Kapitelbaum, Nummern, Bereich, Schlagworten,
 * verwandten Kapiteln, Glossar-Bezug, „Zuletzt geprüft“ und Inhalt aus Blöcken (Feld „Fluid-Blöcke“). Im Projekt ableiten:
 *
 *     #[AsTable('rex_yf_handbuch', label: 'Handbuch', icon: 'fa-book-open')]
 *     final class HandbuchTable extends KnowledgeTable
 *     {
 *         public function areas(): array { return ['start' => 'Erste Schritte', 'praxis' => 'Im Alltag']; }
 *     }
 *
 * Ausgabe über den Block „Datenliste“ (Data\Knowledge): Übersicht als Inhaltsverzeichnis oder Karten, Detailansicht mit
 * Kapitelnavigation. Eigene Felder: fields() überschreiben und `yield from parent::fields();`.
 */
abstract class KnowledgeTable extends Table
{
    /** @return array<string, string> Bereiche (Schlüssel => Beschriftung; Reihenfolge = Reihenfolge im Inhaltsverzeichnis) */
    public function areas(): array
    {
        return [];
    }

    /** @return list<string> Blöcke im Inhalt (leer = alle) */
    public function contentBlocks(): array
    {
        return ['richtext', 'media_text', 'gallery', 'video', 'quote', 'faq', 'steps', 'features', 'downloads', 'hotspots', 'datalist', 'cta'];
    }

    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191)->filterable();
        yield BelongsTo::new('parent')->label('Oberkapitel')->target(static::class)->onDelete(OnDelete::SetNull)->placeholder('– oberste Ebene –')->filterable();
        yield Integer::new('sort')->label('Reihenfolge')->notice('Kapitel einer Ebene stehen nach dieser Zahl, dann nach dem Titel.');
        yield Text::new('number')->label('Kapitelnummer (optional)')->maxLength(20)->notice('Leer = automatisch (1, 1.1, 1.2 …).');
        if ([] !== $this->areas()) {
            yield Choice::new('area')->label('Bereich')->options($this->areas())->placeholder('–')->filterable();
        }
        yield Textarea::new('teaser')->label('Anreißer')->rows(3);
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield FluidBlocks::new('content')->label('Inhalt (Blöcke)')->blocks(...$this->contentBlocks());
        yield Text::new('tags')->label('Schlagworte')->maxLength(255)->notice('Mit Komma getrennt.');
        yield ManyToMany::new('related')->label('Verwandte Kapitel')->target(static::class);
        if (class_exists(\Klxm\Fluid\Glossary\GlossaryTable::class)) {
            yield ManyToMany::new('terms')->label('Glossar-Begriffe')->target(\Klxm\Fluid\Glossary\GlossaryTable::class);
        }
        yield Date::new('reviewed')->label('Zuletzt geprüft');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
        yield FluidLanguage::new('lang');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'parent', 'sort', 'online', 'lang')->sortBy('sort', 'asc');
    }
}
