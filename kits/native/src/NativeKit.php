<?php

namespace Klxm\Fluid\Kit\Native;

use Klxm\Fluid\AsKit;
use Klxm\Fluid\Kit;
use Override;

/**
 * Kit „NativeCSS“: Seitenrahmen und Blöcke auf Basis von NativeCSS (https://github.com/skerbis/nativecss, MIT) –
 * Cascade Layers, Container Queries, light-dark(), native Elemente (dialog, details). Erbt vom Kit fluid: Blöcke ohne
 * eigene View kommen von dort und sehen über die Token-Brücke (assets/css/native.css) wie NativeCSS aus.
 */
#[AsKit('native', 'NativeCSS')]
final class NativeKit extends Kit
{
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
