<?php

namespace Klxm\Fluid\Form;

use Attribute;
use Override;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Form\BoundField;
use Redaxo\Core\Form\Field\Field;
use Redaxo\Core\Form\InvalidValueException;
use Redaxo\Core\Form\ValueType;
use Redaxo\Core\Form\Widget\Widget;
use Redaxo\Core\Translation\Translatable;

use function array_filter;
use function array_keys;
use function array_values;
use function is_array;
use function is_int;
use function is_scalar;

/**
 * Internal values of a block that are not form fields: display (`variant`), section options (`tunes`) and
 * dragged column widths of the layout (`widths`). Stored like every core field under its key in the
 * JSON column `data` of the slice; fluid renders the form (Form\FieldForm).
 *
 * @internal
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Stored extends Field
{
    public function __construct(?string $key = null)
    {
        parent::__construct('', key: $key);
    }

    #[Override]
    public function supports(ValueType $type): bool
    {
        return $type->isAnyOf('array', 'string');
    }

    #[Override]
    public function widget(BoundField $field): Widget
    {
        throw new LogicException('Stored values are rendered by Form\\FieldForm.');
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function toRaw(mixed $value): string|array
    {
        return is_array($value) ? $value : (string) $value;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function fromRaw(string|array|null $raw, ValueType $type): string|array
    {
        if (is_array($raw)) {
            return self::clean($raw);
        }

        return $type->isAnyOf('array') ? [] : (string) $raw;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function encode(mixed $value): string|array
    {
        return is_array($value) ? $value : (string) $value;
    }

    /** @return string|array<array-key, mixed> */
    #[Override]
    public function decode(mixed $stored, ValueType $type): string|array
    {
        if ($type->isAnyOf('array')) {
            if (!is_array($stored)) {
                throw new InvalidValueException(new Translatable('form_invalid_value'));
            }

            return $stored;
        }

        return is_scalar($stored) ? (string) $stored : '';
    }

    /**
     * Cleans up form values: renumbers numbered lists (repeaters).
     *
     * @param array<array-key, mixed> $a
     * @return array<array-key, mixed>
     */
    public static function clean(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $v = self::clean($v);
                $a[$k] = [] !== $v && array_filter(array_keys($v), is_int(...)) === array_keys($v) ? array_values($v) : $v;
            }
        }

        return $a;
    }
}
