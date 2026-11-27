<?php

namespace Klxm\Fluid\Kit\Native;

use Klxm\Fluid\AsKit;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Settings;
use Override;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Http\Response;

use function array_map;
use function dirname;
use function Redaxo\Core\View\escape;

/**
 * Kit „NativeCSS“: Seitenrahmen und Blöcke auf Basis von NativeCSS (https://github.com/skerbis/nativecss, MIT) –
 * Cascade Layers, Container Queries, light-dark(), native Elemente (dialog, details). Erbt vom Kit fluid: Blöcke ohne
 * eigene View kommen von dort und sehen über die Token-Brücke (assets/css/native.css) wie NativeCSS aus.
 */
#[AsKit('native', 'NativeCSS')]
final class NativeKit extends Kit
{
    /**
     * Head with cascade layers: tokens < reset < base (NativeCSS) < fluid (base styles of fluid for the elements, imported as
     * layer) < helpers < components < native. Styles without layer – design values, element styles, design options – come on top.
     */
    #[Override]
    public function head(Article $article): string
    {
        $prefs = (bool) Settings::get('visitor_prefs', false);
        $prefsCss = $prefs ? ['css/ncss-preferences.css', ...((bool) Settings::get('visitor_prefs_fonts', true) ? ['css/ncss-preferences-fonts.css'] : [])] : [];
        $css = [$this->assetUrl('css/ncss-base.css'), ...array_map($this->assetUrl(...), $prefsCss), ...$this->designCss(), ...$this->blockCss(Editing::active()), ...$this->kitCss()];
        $fluidCss = Kit::get(Kit::DEFAULT)?->assetUrl('css/site.css') ?? '';

        $h = self::titleTags($article);
        if ($prefs) {
            $h .= self::script($this->assetUrl('ncss/js/preferences-early.js')) . self::script($this->assetUrl('js/prefs-theme.js'));
        }
        $h .= '<style nonce="' . escape(Response::getNonce()) . '">@layer wa-native, wa-base, wa-utilities, wa-color-palette, wa-color-variant, wa-theme, wa-theme-dimension, wa-theme-overrides, tokens, reset, base, fluid, helpers, components, webawesome-bridge, browser-fixes, native;' . "\n"
            . ('' !== $fluidCss ? '@import url("' . escape($fluidCss) . '") layer(fluid);' : '') . '</style>' . "\n"
            . self::stylesheets($css)
            . $this->design()->head()
            . self::script($this->assetUrl('ncss/js/command-fallback.js'), true)
            . self::script($this->assetUrl('ncss/js/dialog-light-dismiss.js'), true);
        if ($prefs) {
            $h .= self::script($this->assetUrl('ncss/js/preferences.js'), true);
        }
        $h .= self::script($this->assetUrl('ncss/js/nav.js'), true);
        if (!Editing::active()) {
            $h .= self::script($this->assetUrl('js/site.js'), true);
            foreach ($this->blockJs() as $js) {
                $h .= self::script($js, true);
            }
        }

        return $h;
    }

    #[Override]
    public function htmlClass(): string
    {
        return 'kit-native';
    }

    #[Override]
    public function path(string $file = ''): string
    {
        return dirname(__DIR__) . ('' !== $file ? '/' . $file : '');
    }

    /**
     * Schwelle der Kopfleiste für NativeCSS (data-ncss-nav-fit, ab 1.3): geschätzte Breite der Zeile in rem, ab der Marke,
     * Hauptmenü und Werkzeuge in EINE Zeile passen. Darunter zeigt die Container Query von NativeCSS die Menü-Schaltfläche –
     * schon ohne JavaScript und vor dem ersten Zeichnen; ncss/js/nav.js misst danach genau nach. Formel wie im Handbuch von
     * NativeCSS (Navigation → „Schwelle schätzen“) mit den Maßen dieser Kopfleiste (native.css): Menüpunkte mit .5rem
     * Innenabstand, Marke 1.125rem fett, Sprachen, Darstellung, Suche, Handlungsaufruf, Lücken der Zeile. Lieber etwas zu groß.
     *
     * @param list<array{label: string, children: array<mixed>}> $menu
     * @return int|null null: kein Menü; 0: breiter als 120rem – immer Menü-Schaltfläche (Klasse ncss-nav--compact)
     */
    public static function navFit(array $menu, string $brand, bool $logo, int $langs, bool $prefs, bool $search, string $cta): ?int
    {
        if (!$menu) {
            return null;
        }
        $em = static fn (string $s, float $size = 1.0): float => mb_strlen($s) * .57 * $size;
        $nav = 0.0;
        foreach ($menu as $m) {
            $nav += $em((string) $m['label']) + 1.0 + ($m['children'] ? 1.3 : 0);
        }
        $brandW = $logo ? 11.5 : 2.1 + .6 + $em($brand, 1.125 * 1.1);
        $tools = ($langs >= 3 ? 3.5 : ($langs > 1 ? $langs * 2.2 + .5 : 0))
            + ($prefs ? 9 : 0)
            + ($search ? 2.5 : 0)
            + ('' !== $cta ? $em($cta) + 2.5 : 0);
        $gaps = 4 * 1.5;                                                  // Abstände der Zeile, Rand der Leiste, Puffer
        $step = (int) (ceil(($brandW + $nav + $tools + $gaps) / 4) * 4);

        return $step > 120 ? 0 : max(24, $step);
    }
}
