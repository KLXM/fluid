<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\Glossary\GlossaryTable;
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

// Only with YForm (fluid also runs without)
if (!class_exists(Table::class)) {
    return;
}

/**
 * Table kind „Wissen“ (knowledge base, manual, statutes …): chapters with chapter tree, numbers, area, tags, related
 * chapters, glossary reference, „Zuletzt geprüft“ and content from blocks (field „Fluid-Blöcke“). Extend in the project:
 *
 *     #[AsTable('rex_yf_handbuch', label: 'Handbuch', icon: 'fa-book-open')]
 *     final class HandbuchTable extends KnowledgeTable
 *     {
 *         public function areas(): array { return ['start' => 'Erste Schritte', 'praxis' => 'Im Alltag']; }
 *     }
 *
 * Output via the block „Datenliste“ (Data\Knowledge): overview as table of contents or cards, detail view with
 * chapter navigation. Custom fields: override fields() and `yield from parent::fields();`.
 */
abstract class KnowledgeTable extends Table
{
    /** @return array<string, string> areas (key => label; order = order in the table of contents) */
    public function areas(): array
    {
        return [];
    }

    /** @return list<string> blocks in the content (empty = all) */
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
        if (class_exists(GlossaryTable::class)) {
            yield ManyToMany::new('terms')->label('Glossar-Begriffe')->target(GlossaryTable::class);
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
