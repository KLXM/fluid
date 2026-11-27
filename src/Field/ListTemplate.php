<?php

namespace Klxm\Fluid\Field;

use Attribute;
use Redaxo\Core\Form\Field\Text as CoreText;
use Redaxo\Core\View\Renderable;

/**
 * List template of a list block (Data\ListTemplates): key of the chosen template, empty = own settings.
 * In fluid as tiles with a preview per template of the chosen table; the block needs #[AsBlock(listTemplate: true)].
 *
 * Only in fluid (own name). In ordinary core forms it behaves like a text field.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ListTemplate extends CoreText implements FluidField
{
    public function __construct(
        string|Renderable $label,
        string|Renderable|null $note = null,
        ?string $key = null,
    ) {
        parent::__construct($label, $note, false, $key);
    }

    public function fluid(): array
    {
        return ['type' => 'listtemplate'];
    }
}
