<?php

namespace Klxm\Fluid\Media;

use Redaxo\Core\Translation\I18n;

/**
 * Effect of an image at one place (CSS filter). `None` also switches off the site-wide image effect of the design.
 *
 * @internal
 */
enum ImageEffect: string
{
    case None = 'none';
    case Gray = 'gray';
    case Sepia = 'sepia';
    case Warm = 'warm';
    case Cool = 'cool';
    case Muted = 'muted';
    case Vivid = 'vivid';
    case Contrast = 'contrast';

    public function filter(): string
    {
        return match ($this) {
            self::None => '',
            self::Gray => 'grayscale(1)',
            self::Sepia => 'sepia(.8)',
            self::Warm => 'sepia(.25) saturate(1.15) hue-rotate(-8deg)',
            self::Cool => 'saturate(.9) hue-rotate(14deg) brightness(1.03)',
            self::Muted => 'saturate(.55) contrast(.94)',
            self::Vivid => 'saturate(1.45) contrast(1.06)',
            self::Contrast => 'contrast(1.3)',
        };
    }

    public function label(): string
    {
        return I18n::msg('fluid_image_effect_' . $this->value);
    }
}
