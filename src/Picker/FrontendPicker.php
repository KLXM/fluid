<?php

namespace Klxm\Fluid\Picker;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\BackendLogin;

use function Redaxo\Core\View\escape;

use const JSON_HEX_AMP;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;

/**
 * Media and page picker for the website – the counterpart of `rex.pick()` in the backend, usable by any AddOn.
 *
 * Include (only for logged-in backend users): `echo FrontendPicker::head();` in the `<head>` or before `</body>`.
 * Call in the browser, with the same answer as `rex.pick()`:
 *
 *     const [media] = await FrontendPicker.pick('media', { types: ['jpg', 'png'] });
 *     const [page] = await FrontendPicker.pick('article', { category: 5 });   // page.value = article ID
 *
 * Kept self-contained (Picker\*, assets/picker), so that it can become its own AddOn later.
 */
final class FrontendPicker
{
    /** Script and configuration; empty without a backend session */
    public static function head(?int $languageId = null): string
    {
        if (!BackendLogin::createUser()) {
            return '';
        }
        $assets = Addon::require('fluid')->getAssetsUrl('picker/');
        $config = [
            'api' => Url::frontendController(PickerApi::getUrlParams()),
            'css' => $assets . 'picker.css?v=' . self::version('picker.css'),
            'language' => $languageId ?? Language::getCurrentId(),
        ];

        return '<script type="application/json" id="frontend-picker-config">'
            . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
            . '<script src="' . escape($assets . 'picker.js?v=' . self::version('picker.js')) . '" defer></script>';
    }

    private static function version(string $file): string
    {
        $path = Addon::require('fluid')->getPath('assets/picker/' . $file);

        return is_file($path) ? substr(md5((string) filemtime($path)), 0, 8) : '0';
    }
}
