<?php

namespace Klxm\Fluid\Kit\Fluid;

use Klxm\Fluid\BlockModule;
use Klxm\Fluid\Kit;
use Override;
use Redaxo\Core\Translation\Translatable;

/**
 * Base of fluid's block modules – fields as attributes on the class, output from the selected kit (views/{type}.php).
 * The modules don't belong to any particular kit: when switching kits the content stays, only the styling changes.
 */
abstract class FluidBlock extends BlockModule
{
    /**
     * Image formats (#[Choice(…, choices: self::ratios(...))]).
     *
     * @return array<string, Translatable>
     */
    public static function ratios(): array
    {
        return ['16:9' => new Translatable('fluid_ratio_16_9'), '3:2' => new Translatable('fluid_ratio_3_2'), '4:3' => new Translatable('fluid_ratio_4_3'),
            '1:1' => new Translatable('fluid_ratio_1_1'), '4:5' => new Translatable('fluid_ratio_4_5'), '3:4' => new Translatable('fluid_ratio_3_4')];
    }

    /**
     * Minimum width per entry (breakpoint-free: the grid fills the row itself) – #[Choice(…, choices: self::sizes(...))].
     *
     * @return array<string, Translatable>
     */
    public static function sizes(): array
    {
        return ['s' => new Translatable('fluid_size_s'), 'm' => new Translatable('fluid_size_m'), 'l' => new Translatable('fluid_size_l')];
    }

    #[Override]
    public static function kit(): Kit
    {
        return Kit::active();
    }
}
