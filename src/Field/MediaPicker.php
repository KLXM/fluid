<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\MediaPicker as CoreMediaPicker;
use Redaxo\Core\View\Renderable;

/**
 * Core field (`MediaPicker`) with fluid's additions – all additional parameters are optional; without them
 * it behaves exactly like the core field (see docs/entwickler.md, „Felder“).
 *
 * In fluid: with `preview: true` an image field (preview, image editing), otherwise the selection of a file.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MediaPicker extends CoreMediaPicker implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these displays of the block
     */
    public function __construct(
        string|Renderable $label,
        bool $multiple = false,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        ?int $categoryId = null,
        /** @var list<string> */
        array $types = [],
        bool $preview = false,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, $multiple, $note, $required, $key, $categoryId, $types, $preview);
    }

    public function fluid(): array
    {
        return array_filter(['width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}
