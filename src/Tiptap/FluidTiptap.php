<?php

namespace Klxm\Fluid\Tiptap;

use Klxm\Fluid\FluidAddon;
use Klxm\Tiptap\Plugin;
use Klxm\Tiptap\Tiptap;

/**
 * fluid in the TipTap editor: plugin with the extensions fluid needs (anchors of headings, video lightbox on links).
 * The profile comes as class ({@see FluidProfile}).
 *
 * @internal
 */
final class FluidTiptap
{
    public static function boot(): void
    {
        $addon = FluidAddon::instance();
        Tiptap::register(new Plugin(
            id: 'fluid',
            label: 'fluid',
            script: $addon->getAssetsUrl('tiptap-fluid.js'),
        ));
    }
}
