<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;

use function in_array;
use function Redaxo\Core\View\escape;
use function sscanf;
use function strlen;
use function strpos;
use function substr_replace;

/**
 * Head of a page for any template: title, SEO tags, styles and scripts of the active kit, design (colors, fonts).
 * The fluid template uses it, and so can your own:
 *
 *     <html lang="de" <?= KitHead::htmlAttributes() ?>>
 *     <head>
 *         <meta charset="utf-8">
 *         <meta name="viewport" content="width=device-width, initial-scale=1">
 *         <?= KitHead::render() ?>
 *     </head>
 *
 * render() only leaves a marker: the tags are written at the end of the request, when it is known which elements the page
 * uses (only their styles load) and which SEO values they set – so the head may come before the content.
 * Without KitHead, editing on the website and ordinary modules still work; the elements and design of fluid need it.
 *
 * @api
 */
final class KitHead
{
    private const string MARKER = '<!--fluid:kit-head-->';

    private static bool $requested = false;

    /** Marker for the head – replaced by tags() before the page is sent */
    public static function render(): string
    {
        self::$requested = true;

        return self::MARKER;
    }

    /** Attributes for <html>: design classes (palette, header, buttons …) and the base for the live design preview */
    public static function htmlAttributes(): string
    {
        $design = Kit::active()->design();
        $classes = $design->classes();
        // dark base color and serif headings
        $rgb = sscanf((string) $design->get('background'), '#%02x%02x%02x') ?: [255, 255, 255];
        $extra = (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255 < 0.18 ? ' is-darkbase' : '';
        $extra .= in_array((string) $design->get('font_head'), ['fraunces', 'newsreader', 'instrument-serif', 'roboto-slab'], true) ? ' head-serif' : '';

        $kitClass = Kit::active()->htmlClass();

        return 'class="no-js ' . escape(('' !== $kitClass ? $kitClass . ' ' : '') . $classes . $extra) . '" data-fluid-classes="' . escape($classes) . '"';
    }

    /** Whether the visitor preferences of NativeCSS are on (Website → "Darstellungs-Knopf für Besucher anzeigen") */
    public static function visitorPreferences(): bool
    {
        return (bool) Settings::get('visitor_prefs', false) && null !== Kit::get('native');
    }

    /**
     * Replaces the marker (OUTPUT_FILTER).
     *
     * @internal
     */
    public static function replace(string $html): string
    {
        if (!self::$requested || false === ($pos = strpos($html, self::MARKER))) {
            return $html;
        }

        return substr_replace($html, self::tags(), $pos, strlen(self::MARKER));
    }

    /**
     * Tags of the active kit for the current page ({@see Kit::head()}).
     *
     * @internal
     */
    public static function tags(): string
    {
        $article = Article::getCurrent();

        return null !== $article ? Kit::active()->head($article) : '';
    }
}
