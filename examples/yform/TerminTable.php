<?php

namespace Project\YForm;

use Klxm\Fluid\Data\FluidLanguage;
use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Form\Layout\Cell;
use Yakamara\YForm\Form\Layout\Layout;
use Yakamara\YForm\Form\Layout\Tab;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

/** Demo „Termine“ for the fluid block „Datenliste“ (page „Daten“) */
#[AsTable('rex_yf_termin', label: 'Termine (Beispiel)', icon: 'fa-calendar')]
final class TerminTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('title')->label('Titel')->required()->maxLength(191)->filterable();
        yield Date::new('date')->label('Datum')->filterable();
        yield Text::new('place')->label('Ort')->maxLength(191)->filterable();
        yield Media::new('image')->label('Bild')->types('jpg', 'jpeg', 'png', 'webp', 'avif');
        yield Textarea::new('text')->label('Beschreibung');
        yield Checkbox::new('online')->label('Online')->default(true)->filterable();
        // Language: the data list shows only entries of the current language (empty = all languages)
        yield FluidLanguage::new('lang');
    }

    /** Backend form: two tabs, rows with widths in twelfths (alternatively in the designer, „YForm → Tabellen“) */
    public function layout(Layout $layout): Layout
    {
        return $layout
            ->tab('Termin', static fn (Tab $t) => $t->row(Cell::field('title', 8), Cell::field('date', 4))->row('place')->row('text'))
            ->tab('Bild und Sichtbarkeit', static fn (Tab $t) => $t->row('image')->row(Cell::field('online', 6), Cell::field('lang', 6)));
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'title', 'date', 'place', 'online', 'lang')->sortBy('date');
    }
}
