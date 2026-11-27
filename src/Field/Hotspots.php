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
 * Markers on an image (block „Interaktives Bild“): property of type array in the format Klxm\Fluid\Hotspots
 * (version 1, like KLXM Studio). In fluid with the marker editor (image from the image field `image` of the same form,
 * below it one row per marker); in ordinary core forms as JSON text. The cleaned format is always stored.
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
     * @param string $image fluid: key of the image field in the same form
     * @param list<string>|null $variants fluid: only in these displays of the block
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

    /** Form rows of the marker editor (FieldForm) or JSON → format */
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
