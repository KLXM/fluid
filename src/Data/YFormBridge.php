<?php

namespace Klxm\Fluid\Data;

use DateTimeInterface;
use Klxm\Fluid\Editing;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Data\Query;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field\Checkbox;
use Yakamara\YForm\Field\CreatedAt;
use Yakamara\YForm\Field\CreatedBy;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\DateTime;
use Yakamara\YForm\Field\Decimal;
use Yakamara\YForm\Field\Email;
use Yakamara\YForm\Field\Field;
use Yakamara\YForm\Field\Hidden;
use Yakamara\YForm\Field\Integer;
use Yakamara\YForm\Field\Markup;
use Yakamara\YForm\Field\Media;
use Yakamara\YForm\Field\Password;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;
use Yakamara\YForm\Field\UpdatedAt;
use Yakamara\YForm\Field\UpdatedBy;
use Yakamara\YForm\Field\Url as UrlField;
use Yakamara\YForm\Field\Uuid;
use Yakamara\YForm\Form\Action\SaveRecord;
use Yakamara\YForm\Form\Definition\FormDefinition;
use Yakamara\YForm\Form\Definition\Forms;
use Yakamara\YForm\Form\Theme\Theme;
use Yakamara\YForm\Permission\Access;
use Yakamara\YForm\Search\TextFilter;
use Yakamara\YForm\Table\Table;
use Yakamara\YForm\Table\Tables;

use function in_array;
use function is_scalar;
use function Redaxo\Core\View\escape;
use function strlen;

/**
 * Connection to YForm for REDAXO 6 (yakamara/yform, rewrite: tables and forms are classes in the project).
 * Exclusively with YForm's own means:
 *  - Forms: #[AsForm] classes; run with FormDefinition::run() – validation, CSRF, honeypot and actions
 *    (SaveRecord, SendMail, ShowMessage …) come from the form class. fluid only provides a theme in the kit's style.
 *  - Data list: #[AsTable] classes; queries via Table::query(), values via Field::textValue() of the field classes.
 *  - Permissions: Permission\Access (view/edit tables), editing in YForm's data page.
 */
final class YFormBridge
{
    /** Choices for the largest upload in the form assistant (BuiltForm) */
    public const array UPLOAD_SIZES = ['1M' => '1 MB', '2M' => '2 MB', '5M' => '5 MB', '10M' => '10 MB', '20M' => '20 MB'];

    public static function available(): bool
    {
        return class_exists(Tables::class) && Addon::exists('yform');   // REDAXO 6: installiert = aktiv
    }

    // ------------------------------------------------------------------ Formulare

    /** @return array<string, string> Forms for the selection in the block (name => label) */
    public static function forms(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (Forms::all() as $name => $form) {
            $out[$name] = $form->label() . ($form->definition->module ? '' : ' (' . $name . ')');
        }

        return $out;
    }

    public static function formDefinition(string $name): ?FormDefinition
    {
        return self::available() && '' !== $name ? Forms::find($name) : null;
    }

    /** Run a form (YForm handles submission, validation and actions) – in the kit's theme unless the project sets one */
    public static function runForm(FormDefinition $form): string
    {
        $before = Forms::$defaultTheme;
        Forms::$defaultTheme ??= self::theme();
        try {
            return '<div class="fyf-wrap">' . $form->run() . '</div>';
        } finally {
            Forms::$defaultTheme = $before;
        }
    }

    /** Feldtypen des eigenen Formulars (Block „Formular“) */
    public static function builderTypes(): array
    {
        return class_exists(FormDefinition::class) ? BuiltForm::TYPES : ['text' => 'Textzeile'];
    }

    /** Theme with the classes of the kit's public forms (css/dataform.css, .dff) */
    public static function theme(): Theme
    {
        return new Theme(
            form: 'dff fyf', group: 'dff-f', groupInvalid: 'is-invalid', label: '', control: '', staticControl: '', controlInvalid: '',
            select: '', checkWrapper: 'dff-check', checkInput: '', checkLabel: '', radioWrapper: 'dff-check', help: 'dff-help',
            error: 'dff-err', row: 'dff-grid', column: 'dff-c dff-c--%d', buttons: 'dff-actions', submit: 'btn btn--primary', secondary: 'btn btn--ghost',
            alertError: 'dff-summary', alertSuccess: 'dff-done',
            // Reiter = Schritte (js/formsteps.js): ohne JavaScript stehen alle Schritte untereinander
            tabs: 'dff-steps', tabToggle: 'data-dff-step', tabItem: 'dff-steps__item', tabLink: 'dff-steps__link', tabActive: 'is-active', tabActiveOnItem: true,
            tabPane: 'dff-pane', tabPaneActive: 'is-active', tabContent: 'dff-panes',
        );
    }

    /** Tables a form saves to (action SaveRecord) @return list<string> */
    public static function formTables(FormDefinition $form): array
    {
        $out = [];
        foreach ($form->actions() as $action) {
            if ($action instanceof SaveRecord && ($t = Tables::find($action->table))) {
                $out[] = $t->definition->name;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Tabellen

    /** @return array<string, string> Tables for the selection in the block (table name => label) */
    public static function tables(): array
    {
        if (!self::available()) {
            return [];
        }
        $out = [];
        foreach (Tables::all() as $name => $t) {
            $out[$name] = $t->label() . ' (' . $name . ')';
        }

        return $out;
    }

    public static function table(string $name): ?Table
    {
        return self::available() && '' !== $name ? Tables::find($name) : null;
    }

    /** @return array<string, array<string, string>> Felder je Tabelle (Tabellenname => [Feldname => Beschriftung]) */
    public static function fieldOptions(): array
    {
        $out = [];
        foreach (array_keys(self::tables()) as $name) {
            foreach (self::detailFields(Tables::get($name), true) as $f) {
                $out[$name][$f->name] = $f->translatedLabel() . ' (' . $f->name . ')';
            }
        }

        return $out;
    }

    /** Field from "table.field" (selection in the block) – or the first field of one of the classes @param list<class-string<Field>> $types */
    public static function field(Table $table, string $choice, array $types = []): ?Field
    {
        $prefix = $table->definition->name . '.';
        if (str_starts_with($choice, $prefix) && $table->hasField(substr($choice, strlen($prefix)))) {
            return $table->getField(substr($choice, strlen($prefix)));
        }
        foreach ($types as $type) {
            foreach ($table->getFields() as $f) {
                if ($f::class === $type) {
                    return $f;
                }
            }
        }

        return null;
    }

    /** @return list<Field> Fields with content for visitors (without technical fields) */
    public static function detailFields(Table $table, bool $withMeta = false): array
    {
        $skip = [Markup::class, Hidden::class, Password::class, Uuid::class, CreatedBy::class, UpdatedBy::class, UpdatedAt::class];
        if (!$withMeta) {
            $skip[] = CreatedAt::class;
        }

        return array_values(array_filter($table->getFields(), static fn (Field $f) => $f->storesValue() && !in_array($f::class, $skip, true)));
    }

    /**
     * @param array{filter?: ?Field, value?: string, sort?: ?Field, dir?: string, limit?: int, language?: ?int} $o
     *
     * @return list<Record>
     */
    public static function rows(Table $table, array $o): array
    {
        $q = self::search(self::inLanguage($table, $table::query(), $o['language'] ?? null), $table, (string) ($o['search'] ?? ''));
        if (($f = $o['filter'] ?? null) && '' !== ($o['value'] ?? '')) {
            $q->where($f->name, $f instanceof Checkbox ? (bool) $o['value'] : $o['value']);
        }
        if ($f = $o['sort'] ?? null) {
            $q->orderBy($f->name, 'desc' === ($o['dir'] ?? '') ? 'DESC' : 'ASC');
        }
        if (($o['limit'] ?? 0) > 0) {
            $q->limit((int) $o['limit']);
        }

        return array_values(iterator_to_array($q->find()));
    }

    /**
     * Visitor search over the text fields of a table, with the syntax of YForm's detailed search (TextFilter): `tee`
     * contains, `tee*` starts with, `"tee"` exactly, `!tee` excludes. A hit in any text field counts; an exclusion
     * must hold for all of them.
     */
    public static function search(Query $q, Table $table, string $term): Query
    {
        $term = trim($term);
        $fields = array_values(array_filter($table->getFields(), self::isText(...)));
        if ('' === $term || !$fields) {
            return $q;
        }
        $apply = static function (Query $group) use ($fields, $term): void {
            foreach ($fields as $field) {
                $filter = new TextFilter($field);
                $value = $filter->parse($term);
                if (null !== $value) {
                    $filter->apply($group, $value);
                }
            }
        };

        return str_starts_with($term, '!') ? $q->whereAll($apply) : $q->whereAny($apply);
    }

    /** Wie rows(), aber seitenweise (YForm Query::paginate): ['rows' => …, 'total' => …, 'page' => …, 'pages' => …] */
    public static function page(Table $table, array $o, int $page, int $perPage): array
    {
        $q = self::search(self::inLanguage($table, $table::query(), $o['language'] ?? null), $table, (string) ($o['search'] ?? ''));
        if (($f = $o['filter'] ?? null) && '' !== ($o['value'] ?? '')) {
            $q->where($f->name, $f instanceof Checkbox ? (bool) $o['value'] : $o['value']);
        }
        if ($f = $o['sort'] ?? null) {
            $q->orderBy($f->name, 'desc' === ($o['dir'] ?? '') ? 'DESC' : 'ASC');
        }
        $p = $q->paginate(max(1, $page), max(1, $perPage));

        return ['rows' => array_values(iterator_to_array($p->items)), 'total' => $p->total, 'page' => $p->page, 'pages' => $p->pages];
    }

    /** Entry for the detail view – only if it meets the list's condition */
    public static function row(Table $table, int $id, ?Field $filter, string $value, ?int $languageId = null): ?Record
    {
        $q = self::inLanguage($table, $table::query()->where('id', $id), $languageId);
        if ($filter && '' !== $value) {
            $q->where($filter->name, $filter instanceof Checkbox ? (bool) $value : $value);
        }

        return $q->first();
    }

    /**
     * With field „Sprache“ (FluidLanguage): only entries of the language (default: current) and those without language.
     * When resolving addresses the current language is not set yet – pass the page's language there.
     */
    public static function inLanguage(Table $table, Query $q, ?int $languageId = null): Query
    {
        foreach ($table->getFields() as $f) {
            if ($f instanceof FluidLanguage) {
                $lang = null !== $languageId ? Language::get($languageId) : null;

                $code = ($lang ?? Language::getCurrent())->code;

                // "all languages" is stored as '' or NULL (YForm form) in the column
                return $q->whereAny(static fn (Query $g) => $g->whereIn($f->name, ['', $code])->whereNull($f->name));
            }
        }

        return $q;
    }

    /** Value as text – output of the field class (choice label, date format, yes/no …) */
    public static function text(Record $record, Field $f): string
    {
        return trim($f->textValue($record->get($f->name)));
    }

    /** Value as HTML (line breaks, e-mail and links clickable) */
    public static function html(Record $record, Field $f): string
    {
        $v = self::text($record, $f);
        if ('' === $v) {
            return '';
        }

        return match (true) {
            $f instanceof Email => '<a href="mailto:' . escape($v) . '">' . escape($v) . '</a>',
            $f instanceof UrlField, (bool) preg_match('~^https?://\S+$~', $v) => '<a href="' . escape($v) . '" rel="noopener">' . escape((string) preg_replace('~^https?://(www\.)?~', '', $v)) . '</a>',
            default => nl2br(escape($v), false),
        };
    }

    /** First file of a media field (or '') */
    public static function file(Record $record, ?Field $f): string
    {
        if (!$f instanceof Media) {
            return '';
        }

        return trim(explode(',', $f->textValue($record->get($f->name)))[0]);
    }

    public static function isText(?Field $f): bool
    {
        return $f instanceof Text || $f instanceof Textarea;
    }

    /**
     * Column type for „Spalten sortierbar“ (js/table-sort.js from NativeCSS): `number` (integer, decimal – right-aligned),
     * `date` (date, date + time), otherwise '' (text or auto-detected).
     */
    public static function sortType(?Field $f): string
    {
        return match (true) {
            $f instanceof Integer, $f instanceof Decimal => 'number',
            $f instanceof Date => 'date',
            default => '',
        };
    }

    /** Stored value for data-sort-value (date as ISO 8601, number unformatted), '' = none */
    public static function sortValue(Record $record, Field $f): string
    {
        $v = $record->get($f->name);
        if ($v instanceof DateTimeInterface) {
            return $f instanceof DateTime ? $v->format('Y-m-d\\TH:i:sP') : $v->format('Y-m-d');
        }
        $v = is_scalar($v) ? trim((string) $v) : '';

        return str_starts_with($v, '0000-00-00') ? '' : $v;
    }

    /** Edit in YForm's data page (only with edit permission); without ID: new entry */
    public static function editUrl(Table $table, ?int $id = null): ?string
    {
        $user = Editing::user();
        if (!$user || Editing::isGuest() || !Access::canEdit($table, $user)) {
            return null;
        }

        return Url::backendPage('yform/data', ['table' => $table->definition->name] + ($id ? ['func' => 'edit', 'id' => $id] : ['func' => 'add']));
    }
}
