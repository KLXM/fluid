<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Kit;
use Klxm\Fluid\LayoutBlock;
use Override;
use Redaxo\Core\Content\AsModule;

#[AsModule('fluid.layout', 'Layout (Spalten)')]
final class Layout extends LayoutBlock
{
    #[Override]
    public static function kit(): Kit
    {
        return Kit::active();
    }
}
