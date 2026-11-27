<?php

namespace Klxm\Fluid\Kit\Fluid;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\Kit;
use Override;

/**
 * Base of fluid's block modules – fields as attributes on the class, output from the selected kit (views/{type}.php).
 * The modules don't belong to any particular kit: when switching kits the content stays, only the styling changes.
 */
abstract class FluidBlock extends BlockModule
{
    /** Image formats (#[Choice(…, choices: self::RATIOS)]) */
    public const array RATIOS = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];

    /** Minimum width per entry (breakpoint-free: the grid fills the row itself) */
    public const array SIZES = ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'];

    #[Override]
    public static function kit(): Kit
    {
        return Kit::active();
    }
}
