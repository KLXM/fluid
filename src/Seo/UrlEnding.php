<?php

namespace Klxm\Fluid\Seo;

use InvalidArgumentException;
use Klxm\Routes\UrlFormat;
use Redaxo\Core\Translation\I18n;

/**
 * Ending of the addresses, as chosen in the site panel ("Website → Adressen").
 *
 * @internal
 */
enum UrlEnding: string
{
    /** /leistungen/beratung */
    case None = 'none';
    /** /leistungen/beratung/ */
    case Slash = 'slash';
    /** /leistungen/beratung.html */
    case Html = 'html';
    /** /leistungen/ (menu items) · /leistungen/beratung.html (pages) */
    case Mixed = 'mixed';
    /** own ending such as .htm or .php */
    case Custom = 'custom';

    /** @throws InvalidArgumentException for an invalid custom ending */
    public function format(string $suffix = ''): UrlFormat
    {
        return match ($this) {
            self::None => UrlFormat::none(),
            self::Slash => UrlFormat::slash(),
            self::Html => UrlFormat::html(),
            self::Mixed => UrlFormat::mixed(),
            self::Custom => UrlFormat::suffix($suffix),
        };
    }

    public function label(): string
    {
        return I18n::msg('fluid_url_ending_' . $this->value);
    }
}
