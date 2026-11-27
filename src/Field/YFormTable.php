<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Klxm\Fluid\Data\YFormBridge;
use Redaxo\Core\Form\Field\Choice as CoreChoice;
use Redaxo\Core\View\Renderable;

/**
 * Table from YForm (class with #[AsTable] in the project).
 *
 * Only in fluid (own name). In ordinary core forms it behaves like the field it extends.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class YFormTable extends CoreChoice implements FluidField
{
    /**
     * @param 'half'|null $width fluid: half width in the form
     * @param list<string>|null $variants fluid: only in these variants of the block
     */
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        bool $required = false,
        ?string $key = null,
        public readonly ?string $width = null,
        /** @var list<string>|null */
        public readonly ?array $variants = null,
    ) {
        parent::__construct($label, static fn (): array => YFormBridge::tables(), placeholder: '–', note: $note, required: $required, key: $key);
    }

    public function fluid(): array
    {
        return array_filter(['type' => 'yform_table', 'width' => $this->width, 'variants' => $this->variants], static fn ($v) => null !== $v);
    }
}
