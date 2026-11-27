<?php

namespace Klxm\Fluid\Data;

use Yakamara\YForm\Attribute\AsTable;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Table\ListConfig;
use Yakamara\YForm\Table\Table;

// Only with YForm (fluid also runs without it)
if (!class_exists(Table::class)) {
    return;
}

/**
 * Submissions of the forms editors assemble themselves in the block „Formular“ (BuiltForm) – a YForm table
 * like any other: in the backend under YForm, on the website in the inbox „Anfragen“. `console migrate` creates it.
 *
 * @internal
 */
#[AsTable('rex_fluid_form_entry', label: 'Formular-Eingänge', icon: 'fa-inbox')]
final class FormEntryTable extends Table
{
    public function fields(): iterable
    {
        yield Text::new('form')->label('Formular')->maxLength(191);
        yield Text::new('page')->label('Seite')->maxLength(255);
        yield Textarea::new('summary')->label('Angaben');
        yield Textarea::new('data')->label('Daten (JSON)');
        yield CreatedAt::new('created')->label('Eingegangen');
    }

    public function list(ListConfig $list): ListConfig
    {
        return $list->columns('id', 'form', 'page', 'created')->sortBy('created', 'desc');
    }
}
