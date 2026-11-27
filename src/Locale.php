<?php

namespace Klxm\Fluid;

use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Translation\I18n;

use function in_array;
use function is_file;
use function preg_match_all;
use function str_starts_with;

/**
 * Language of fluid's interface on the website: the language a logged-in editor chose in the backend (profile), guests
 * (demo) get the language of the page. Visitor texts are not affected – they follow the page ({@see lt()}).
 *
 * Texts of the editor script come from the lang files too (keys `fluid_js_*`), handed over with the configuration.
 *
 * @internal
 */
final class Locale
{
    public static function boot(): void
    {
        if (!Core::isFrontend()) {
            return;
        }
        Extension::register('PACKAGES_INCLUDED', static function (): void {
            $user = BackendLogin::createUser();
            $locale = (string) ($user?->language ?? '');
            if ('' === $locale) {
                $code = Language::getCurrent()->code ?? '';
                foreach (I18n::getLocales() as $l) {
                    if ('' !== $code && str_starts_with($l, $code . '_')) {
                        $locale = $l;
                        break;
                    }
                }
            }
            if ('' !== $locale && in_array($locale, I18n::getLocales(), true)) {
                I18n::setLocale($locale, false);
            }
        }, ExtensionLevel::Late);
    }

    /**
     * Texts of the editor script in the current language (keys `fluid_js_*` without the prefix).
     *
     * @return array<string, string>
     */
    public static function scriptTexts(): array
    {
        $file = FluidAddon::instance()->getPath('lang/de_de.lang');
        if (!is_file($file) || !preg_match_all('~^fluid_js_([a-z0-9_]+)\s*=~m', (string) file_get_contents($file), $m)) {
            return [];
        }
        $out = [];
        foreach ($m[1] as $key) {
            $out[$key] = I18n::rawMsg('fluid_js_' . $key);
        }

        return $out;
    }
}
