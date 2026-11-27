<?php

namespace Klxm\Fluid\Tiptap;

use Klxm\Fluid\TextFormats;
use Klxm\Tiptap\AsStyles;
use Klxm\Tiptap\StyleDefinition;
use Override;

/**
 * Text formats of the website's kit (kit.php `text`, {@see TextFormats}) for fluid's text editor – registered like the
 * formats of any other addon. Other profiles get them with an own style class, e.g. `profiles: ['tiptap.news']`.
 */
#[AsStyles('fluid.kit', 'fluid-Kit', profiles: [FluidProfile::NAME], priority: -10)]
final class KitStyles extends StyleDefinition
{
    #[Override]
    public function styles(): array
    {
        return TextFormats::active()->profileConfig();
    }
}
