<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Klxm\Fluid\Hotspots as Format;
use Override;
use Redaxo\Core\Form\BoundField;
use Redaxo\Core\Form\Field\Field;
use Redaxo\Core\Form\ValueType;
use Redaxo\Core\Form\Widget\Textarea as TextareaWidget;
use Redaxo\Core\View\Renderable;

use function is_string;

/**
 * Markierungen auf einem Bild (Block „Interaktives Bild“): Eigenschaft vom Typ array im Format Klxm\Fluid\Hotspots
 * (Version 1, wie KLXM Studio). In fluid mit dem Marker-Editor (Bild aus dem Bildfeld `image` desselben Formulars, darunter
 * eine Zeile je Markierung); in gewöhnlichen Formularen des Cores als JSON-Text. Gespeichert wird immer das bereinigte Format.
 *
 *     #[MediaPicker('Bild', preview: true)]
 *     public ?Media $image = null;
 *
 *     #[Hotspots('Markierungen', image: 'image')]
 *     public array $hotspots = [];
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Hotspots extends Field implements FluidField
{
    /**
     * @param string $image fluid: Schlüssel des Bildfelds im selben Formular
     * @param list<string>|null $variants fluid: nur in diesen Darstellungen des Blocks
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly string $image = 'image',
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $note, $required, $key);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'hotspots', 'image_field' => $this->image, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }

    #[Override]
    public function supports(ValueType $type): bool
    {
        return $type->isAnyOf('array');
    }

    #[Override]
    public function widget(BoundField $field): TextareaWidget
    {
        return new TextareaWidget($field->inputName, is_string($field->raw) ? $field->raw : Format::json($field->raw), $field->id, 8, $this->required, $field->disabled);
    }

    #[Override]
    public function toRaw(mixed $value): string
    {
        return Format::json($value);
    }

    /** Formularzeilen des Marker-Editors (FieldForm) oder JSON → Format */
    #[Override]
    public function fromRaw(string|array|null $raw, ValueType $type): array
    {
        return Format::fromForm($raw ?? '');
    }

    #[Override]
    public function isEmpty(mixed $value): bool
    {
        return [] === Format::normalize($value)['items'];
    }

    #[Override]
    public function encode(mixed $value): array
    {
        return Format::normalize($value);
    }

    #[Override]
    public function decode(mixed $stored, ValueType $type): array
    {
        return Format::normalize($stored);
    }
}
