<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Kit;
use Klxm\Fluid\LayoutBlock;
use Override;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.layout', new Translatable('fluid_block_layout'))]
final class Layout extends LayoutBlock
{
    #[Override]
    public static function kit(): Kit
    {
        return Kit::active();
    }
}
