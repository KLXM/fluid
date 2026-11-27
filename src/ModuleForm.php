<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Form\FieldForm;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Renderable;
use ReflectionMethod;
use Throwable;

use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function preg_replace;
use function trim;

/**
 * Gewöhnliche REDAXO-Module (keine fluid-Blöcke) in der Seitenleiste der Website – im Formular von fluid.
 *
 * Dieselbe Abbildung der Felder wie bei den Blöcken (Field\BlockSchema): alle Felder des Cores samt Collection und die
 * Felder von fluid. Werte: `Field::toRaw()` des Cores; Speichern über das Formular des Cores (`Form::submit()` wandelt um
 * und prüft wie im Backend), `Module::validate()` und `ContentHandler::editSlice()`. Module mit eigenem `input()` und
 * Mehrfachauswahl von Medien bzw. Seiten öffnen weiter das Formular des Cores im Rahmen (null von {@see self::fields()}).
 * Module, deren Felder das Formular vollständig zeigt, lassen sich auf der Website auch hinzufügen (Blockauswahl, Gruppe
 * „REDAXO-Module“): erst das Formular, dann der Slice ({@see self::create()}).
 */
final class ModuleForm
{
    /** Feldtypen, die das Formular von fluid für gewöhnliche Module zeigt */
    private const array TYPES = ['text', 'email', 'url', 'textarea', 'richtext', 'number', 'bool', 'select', 'date', 'time', 'datetime',
        'media', 'file', 'article', 'link', 'icon', 'geo', 'repeater', 'blog', 'mediacategory', 'yform_table', 'yform_form', 'yform_field'];

    /**
     * Felder der Modulklasse im Schema von fluid – null, wenn das Modul nicht vollständig abgebildet werden kann.
     *
     * @param class-string<Module> $class
     * @return list<array<string, mixed>>|null
     */
    public static function fields(string $class): ?array
    {
        // eigenes input(): Aufbau des Formulars steht im Code des Moduls – nur der Core kann es zeigen
        if (Module::class !== new ReflectionMethod($class, 'input')->getDeclaringClass()->getName()) {
            return null;
        }
        $fields = Field\BlockSchema::fields($class);

        return self::supported($fields) ? $fields : null;
    }

    /** @param list<array<string, mixed>> $fields */
    private static function supported(array $fields): bool
    {
        foreach ($fields as $f) {
            $type = (string) ($f['type'] ?? '');
            if (!in_array($type, self::TYPES, true) || (!empty($f['multiple']) && 'select' !== $type)
                || ('repeater' === $type && !self::supported((array) ($f['fields'] ?? [])))) {
                return false;
            }
        }

        return true;
    }

    /** API „module_form“: Formular in der Seitenleiste oder null (dann Formular des Cores im Rahmen) */
    public static function form(ArticleSlice $slice): ?string
    {
        $class = Module::getClass($slice->moduleKey);
        $fields = null !== $class ? self::fields($class) : null;
        if (null === $fields) {
            return null;
        }
        $module = Module::of($slice);
        $raw = [];
        foreach (FormSchema::of($class)->fields as $prop => $definition) {
            $raw[$prop] = $definition->field->toRaw($definition->getValue($module));
        }

        return FieldForm::standalone($fields, self::formValues($fields, $raw), 'm');
    }

    /**
     * API „module_save“: Eingaben prüfen, umwandeln und speichern.
     *
     * @param array<string, mixed> $input
     */
    public static function save(ArticleSlice $slice, array $input): void
    {
        [$class, $fields] = self::schema($slice->moduleKey);
        $module = Module::of($slice);
        self::submit($module, $fields, $input);
        $module->beforeSave();
        // afterSave() auf einer frischen Instanz des gespeicherten Slices (wie im Backend)
        Module::of(ContentHandler::editSlice($slice->id, FormSchema::of($class)->encode($module)))->afterSave();
    }

    /**
     * Can this module be added on the website? An ordinary module (no fluid element) whose fields the form of fluid shows
     * completely, allowed in the template and category of the page.
     */
    public static function addable(string $key, Article $article): bool
    {
        $class = Module::getClass($key);

        return null !== $class && null === BlockModule::classFor($key) && null !== self::fields($class)
            && Template::checkModuleAllowed((string) $article->templateKey, 1, $key)
            && $class::isAllowedInCategory($article->getCategory());
    }

    /** API "module_new": empty form of a module that is not on the page yet (defaults of its fields) */
    public static function newForm(string $key, Article $article): string
    {
        [$class, $fields] = self::schema($key);
        $module = Module::of(ArticleSlice::forNewSlice($article->id, $article->languageId, 1, $key, 1, FluidAddon::revision()));
        $raw = [];
        foreach (FormSchema::of($class)->fields as $prop => $definition) {
            $raw[$prop] = $definition->field->toRaw($definition->getValue($module));
        }

        return FieldForm::standalone($fields, self::formValues($fields, $raw), 'm');
    }

    /**
     * API "module_add": checks the input like the backend and only then adds the slice – no empty slices with missing
     * required fields.
     *
     * @param array<string, mixed> $input
     */
    public static function create(string $key, Article $article, array $input, int $priority): ArticleSlice
    {
        [$class, $fields] = self::schema($key);
        $module = Module::of(ArticleSlice::forNewSlice($article->id, $article->languageId, 1, $key, $priority, FluidAddon::revision()));
        self::submit($module, $fields, $input);
        $module->beforeSave();
        $slice = ContentHandler::addSlice($article->id, $article->languageId, 1, $key, FormSchema::of($class)->encode($module), $priority, FluidAddon::revision());
        Module::of($slice)->afterSave();

        return $slice;
    }

    /**
     * Class and fields of a module the form of fluid can show.
     *
     * @return array{0: class-string<Module>, 1: list<array<string, mixed>>}
     */
    private static function schema(string $key): array
    {
        $class = Module::getClass($key);
        $fields = null !== $class ? self::fields($class) : null;
        if (null === $class || null === $fields) {
            throw new ApiError('Dieses Modul lässt sich nur im Formular des Backends bearbeiten.');
        }

        return [$class, $fields];
    }

    /**
     * Submits the input into the form of the core (converts and checks like the backend) and runs validate().
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $input
     */
    private static function submit(Module $module, array $fields, array $input): void
    {
        $form = new Form($module, name: 'fluid_module', csrf: false);
        if (!$form->submit(self::rawValues($fields, $input))) {
            $labels = [];
            foreach ($fields as $f) {
                $labels[(string) $f['prop']] = (string) $f['label'];
            }
            $messages = [];
            foreach ($form->getFieldErrors() as $prop => $error) {
                $messages[] = '„' . ($labels[$prop] ?? $prop) . '“: ' . self::text($error);
            }
            throw new ApiError(implode(' ', $messages) ?: 'Die Eingaben sind nicht gültig.');
        }
        $error = $module->validate();
        if (null !== $error) {
            throw new ApiError(self::text($error));
        }
    }

    /**
     * Rohwerte des Cores (nach Eigenschaft) → Werte des Formulars von fluid (nach Schlüssel).
     *
     * @param list<array<string, mixed>> $fields
     * @param array<array-key, mixed> $raw
     * @return array<string, mixed>
     */
    private static function formValues(array $fields, array $raw): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $raw[(string) $f['prop']] ?? null;
            $out[(string) $f['name']] = match ((string) $f['type']) {
                'repeater' => array_map(static fn ($it) => self::formValues((array) $f['fields'], is_array($it) ? $it : []), is_array($v) ? array_values($v) : []),
                'bool' => is_string($v) && '' !== $v && '0' !== $v,
                'article' => is_string($v) && '' !== $v ? 'redaxo://' . $v : '',
                default => $v,
            };
        }

        return $out;
    }

    /**
     * Eingaben des Formulars von fluid (nach Schlüssel) → Rohwerte für das Formular des Cores (nach Eigenschaft).
     *
     * @param list<array<string, mixed>> $fields
     * @param array<array-key, mixed> $input
     * @return array<string, mixed>
     */
    private static function rawValues(array $fields, array $input): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $input[(string) $f['name']] ?? null;
            $out[(string) $f['prop']] = match ((string) $f['type']) {
                'repeater' => array_map(static fn ($it) => self::rawValues((array) $f['fields'], is_array($it) ? $it : []), is_array($v) ? array_values($v) : []),
                'bool' => null !== $v && '' !== $v && '0' !== $v ? '1' : '',
                'article' => (string) preg_replace('~^redaxo://~', '', trim((string) $v)),
                'select' => is_array($v) ? array_map('strval', $v) : trim((string) $v),
                default => is_array($v) ? $v : trim((string) $v),
            };
        }

        return $out;
    }

    private static function text(mixed $v): string
    {
        try {
            return match (true) {
                $v instanceof Translatable => $v->translate(),
                is_string($v) => $v,
                $v instanceof Renderable => trim(strip_tags((string) $v->render())),
                default => (string) $v,
            };
        } catch (Throwable) {
            return '';
        }
    }
}
