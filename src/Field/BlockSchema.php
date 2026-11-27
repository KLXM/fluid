<?php

namespace Klxm\Fluid\Field;

use Klxm\Fluid\Form\Stored;
use Redaxo\Core\Form\Field as Core;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function array_filter;
use function preg_replace;
use function strtolower;

/**
 * Definition of a block from its class's attributes: #[AsBlock] on the class, core field attributes on the
 * properties (as with every core module; fluid's fields of the same name are subclasses with optional
 * additions, see FluidField). Order: inherited properties first, then those of the class.
 *
 * The result is fluid's field schema (format of Form\FieldForm) – the sidebar, the backend form and
 * on-page editing are built from it. Saving and loading work as in the core (flat per field key).
 *
 * @internal
 */
final class BlockSchema
{
    /**
     * @param class-string $class
     * @return array<string, mixed>
     */
    public static function of(string $class, string $name): array
    {
        $ref = new ReflectionClass($class);
        $block = ($ref->getAttributes(AsBlock::class)[0] ?? null)?->newInstance() ?? new AsBlock();
        $def = ['label' => $block->label ?? $name, 'icon' => $block->icon, 'group' => $block->group];
        foreach (['help' => $block->help, 'requires' => $block->requires, 'background' => $block->background, 'jsonld' => $block->jsonld] as $k => $v) {
            if (null !== $v) {
                $def[$k] = $v;
            }
        }
        foreach (['variants' => $block->variants, 'variant_help' => $block->variantHelp, 'drags' => $block->drags] as $k => $v) {
            if ([] !== $v) {
                $def[$k] = $v;
            }
        }
        if (false !== $block->nestable) {
            $def['nestable'] = $block->nestable;
        }
        if ($block->reload) {
            $def['reload'] = true;
        }
        if (false !== $block->listTemplate) {
            $def['list_template'] = $block->listTemplate;
        }
        $def['fields'] = self::fields($class);

        return $def;
    }

    /**
     * Fields of a class (block or collection entry) as field schema.
     *
     * @param class-string $class
     * @return list<array<string, mixed>>
     */
    public static function fields(string $class): array
    {
        $chain = [];
        for ($c = new ReflectionClass($class); false !== $c; $c = $c->getParentClass()) {
            array_unshift($chain, $c);
        }
        $out = [];
        foreach ($chain as $c) {
            foreach ($c->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic() || $prop->getDeclaringClass()->getName() !== $c->getName()) {
                    continue;
                }
                $attr = $prop->getAttributes(Core\Field::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
                if (null === $attr || is_a($attr->getName(), Stored::class, true)) {
                    continue;   // not a field property, or internal values (display, section)
                }
                $out[] = self::field($attr->newInstance(), $prop);
            }
        }

        return $out;
    }

    /** Key as in the core: `key`, otherwise the property name in snake_case (`$maxItems` → `max_items`) */
    public static function key(Core\Field $field, string $property): string
    {
        return $field->key ?? strtolower((string) preg_replace('/(?<=[a-z0-9])[A-Z]|(?<=[A-Z])[A-Z](?=[a-z])/', '_$0', $property));
    }

    /** @return array<string, mixed> */
    private static function field(Core\Field $f, ReflectionProperty $prop): array
    {
        // prop: name of the property – the core form expects the input under it (backend, sidebar for modules)
        $s = ['name' => self::key($f, $prop->getName()), 'prop' => $prop->getName(), 'label' => (string) $f->label, 'type' => 'text'];
        if ($f->required) {
            $s['required'] = true;
        }
        if (null !== $f->note) {
            $s['help'] = (string) $f->note;
        }
        $own = $f instanceof FluidField ? $f->fluid() : [];
        $s = isset($own['type']) ? $s : match (true) {
            $f instanceof Core\Textarea => ['type' => 'textarea', 'rows' => $f->rows, 'max' => $f->maxLength] + $s,
            $f instanceof Core\Email => ['type' => 'email', 'max' => $f->maxLength] + $s,
            $f instanceof Core\Url => ['type' => 'url', 'max' => $f->maxLength] + $s,
            $f instanceof Core\Text => ['max' => $f->maxLength] + $s,
            $f instanceof Core\Number => ['type' => 'number', 'min' => $f->min, 'max_value' => $f->max, 'step' => $f->step] + $s,
            $f instanceof Core\Checkbox => ['type' => 'bool'] + $s,
            $f instanceof Core\Choice => ['type' => 'select', 'options' => $f->choices(), 'multiple' => $f->multiple ?: null, 'placeholder' => null !== $f->placeholder ? (string) $f->placeholder : null] + $s,
            $f instanceof Core\DateTimePicker => ['type' => 'datetime'] + $s,
            $f instanceof Core\DatePicker => ['type' => 'date'] + $s,
            $f instanceof Core\TimePicker => ['type' => 'time'] + $s,
            $f instanceof Core\MediaPicker => ['type' => $f->preview ? 'media' : 'file', 'types' => [] !== $f->types ? $f->types : null, 'multiple' => $f->multiple ?: null] + $s,
            $f instanceof Core\ArticlePicker => ['type' => 'article', 'multiple' => $f->multiple ?: null] + $s,
            $f instanceof Core\Collection => ['type' => 'repeater', 'fields' => self::fields($f->item), 'min_items' => $f->min, 'max_items' => $f->max] + $s,
            default => $s,
        };
        // fluid's additions (width, displays, placeholder, own field type …) override
        $s = $own + $s;
        // default value: the property's default (as in the core)
        $default = $prop->hasDefaultValue() ? $prop->getDefaultValue() : null;
        if (null !== $default && '' !== $default && [] !== $default && !(false === $default && 'bool' === $s['type'])) {
            $s['default'] = $default;
        }

        return array_filter($s, static fn ($v) => null !== $v);
    }
}
