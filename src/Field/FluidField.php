<?php

namespace Klxm\Fluid\Field;

/**
 * Field with fluid's additions (width, displays, placeholder … or its own field type). The core's fields work the same
 * in blocks – only without these additions.
 *
 * @internal
 */
interface FluidField
{
    /**
     * Additions for fluid's form (format of Form\FieldForm), e.g. ['width' => 'half', 'variants' => [...]];
     * 'type' only for field types that exist only in fluid.
     *
     * @return array<string, mixed>
     */
    public function fluid(): array;
}
