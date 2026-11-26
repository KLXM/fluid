<?php

namespace Klxm\Fluid\Media;

use Redaxo\Core\Translation\I18n;

/**
 * How an image is shown in its frame.
 *
 * @internal
 */
enum ImageFit: string
{
    /** Fills the frame, the edges are cut off */
    case Cover = 'cover';
    /** Shows the whole image, the free area gets a background */
    case Contain = 'contain';
    /** At most in its original size */
    case Original = 'original';

    public function label(): string
    {
        return I18n::msg('fluid_image_fit_' . $this->value);
    }
}
