<?php

namespace Klxm\Fluid;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Response;

use function implode;
use function md5;
use function preg_split;
use function Redaxo\Core\View\escape;
use function str_contains;
use function str_replace;
use function stripos;
use function substr;
use function substr_replace;
use function trim;

/**
 * No style attributes (Content Security Policy, enforced in the backend): values that can't be expressed with fixed
 * classes (focus point of an image, grid width …) get a class – the rules are collected in
 * a <style nonce> in the page head (OUTPUT_FILTER, website and backend).
 *
 *     '<img class="' . InlineStyle::class('object-position:30% 40%') . '" …>'
 *
 * The rules carry !important so they take precedence over the stylesheets like the former style attributes.
 */
final class InlineStyle
{
    /** @var array<string, string> class → declarations */
    private static array $rules = [];

    private static bool $booted = false;

    /** Class for these declarations ('' for empty ones) */
    public static function class(string $declarations): string
    {
        $declarations = trim($declarations, " ;\t\n");
        if ('' === $declarations) {
            return '';
        }
        $class = 'fs-' . substr(md5($declarations), 0, 10);
        self::$rules[$class] = $declarations;
        self::boot();

        return $class;
    }

    /** Attribute class="…" (empty for empty declarations), with further classes */
    public static function attr(string $declarations, string $classes = ''): string
    {
        $all = trim($classes . ' ' . self::class($declarations));

        return '' !== $all ? ' class="' . escape($all) . '"' : '';
    }

    /** <style nonce> with all rules – empty if there were none */
    public static function tag(): string
    {
        $css = self::css();

        return '' !== $css ? '<style nonce="' . escape(Response::getNonce()) . '">' . $css . '</style>' : '';
    }

    /**
     * All rules as CSS text (cleared afterwards) – for responses without page head, e.g. the editor API that returns
     * re-rendered blocks.
     */
    public static function css(): string
    {
        $css = '';
        foreach (self::$rules as $class => $declarations) {
            $parts = [];
            foreach (preg_split('~;(?![^(]*\))~', $declarations) ?: [] as $d) {
                $d = trim($d);
                if ('' !== $d) {
                    $parts[] = str_contains($d, '!important') ? $d : $d . ' !important';
                }
            }
            $css .= '.' . $class . '{' . str_replace('</', '<\/', implode(';', $parts)) . '}';
        }
        self::$rules = [];

        return $css;
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        Extension::register('OUTPUT_FILTER', static function (ExtensionPoint $ep): string {
            $html = (string) $ep->subject;
            $tag = self::tag();
            if ('' === $tag) {
                return $html;
            }
            $pos = stripos($html, '</head>');
            $pos = false !== $pos ? $pos : stripos($html, '</body>');

            return false !== $pos ? substr_replace($html, $tag, $pos, 0) : $html . $tag;
        }, ExtensionLevel::Late);
    }
}
