<?php

namespace Klxm\Fluid\Seo;

use InvalidArgumentException;
use Klxm\Fluid\ApiError;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Routing\Router;
use Klxm\Fluid\Routing\Routes;
use Klxm\Fluid\Routing\RoutingConfig;
use Klxm\Fluid\Routing\UrlFormat;
use Redaxo\Core\Cache;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function is_array;
use function is_string;
use function Redaxo\Core\View\escape;

/**
 * Address settings editors choose on the website ("Website → Adressen"): the ending of all addresses.
 * Stored in the addon config and handed to the router as its addon default – Router::$config in the project wins.
 *
 * @internal
 */
final class Addresses
{
    public const string CONFIG = 'addresses';

    public static function boot(): void
    {
        $format = self::stored();
        if (null !== $format) {
            Router::defaults(new RoutingConfig(format: $format));
        }
    }

    public static function ending(): UrlEnding
    {
        $value = self::config()['ending'] ?? null;

        return UrlEnding::tryFrom(is_string($value) ? $value : '') ?? UrlEnding::None;
    }

    public static function suffix(): string
    {
        $value = self::config()['suffix'] ?? null;

        return is_string($value) ? $value : '';
    }

    /** Saves the ending; old spellings of every address redirect with 301 from now on (no rebuild needed) */
    public static function save(string $ending, string $suffix): string
    {
        if (Router::configuredByProject()) {
            throw new ApiError(I18n::msg('fluid_addresses_by_project'));
        }
        $choice = UrlEnding::tryFrom($ending) ?? UrlEnding::None;
        $suffix = UrlEnding::Custom === $choice ? '.' . ltrim(strtolower(trim($suffix)), '.') : '';
        try {
            $format = $choice->format($suffix);
        } catch (InvalidArgumentException) {
            throw new ApiError(I18n::msg('fluid_addresses_invalid_suffix'));
        }
        FluidAddon::instance()->setConfig(self::CONFIG, ['ending' => $choice->value, 'suffix' => $suffix]);
        Router::defaults(new RoutingConfig(format: $format));
        Cache::delete();   // rendered content contains the old addresses

        return I18n::msg('fluid_addresses_saved');
    }

    /** Tab of the site panel */
    public static function render(): string
    {
        $locked = Router::configuredByProject();
        [$category, $page] = self::samples();
        $current = self::ending();
        $options = '';
        foreach (UrlEnding::cases() as $ending) {
            $format = UrlEnding::Custom === $ending
                ? (UrlEnding::Custom === $current ? self::stored() : null)
                : $ending->format();
            $example = null !== $format ? '/' . $format->append($category, true) . ' · /' . $format->append($page) : '';
            $options .= '<label class="blocks-form__check site-choice"><input type="radio" name="ending" value="' . $ending->value . '"'
                . ($ending === $current ? ' checked' : '') . ($locked ? ' disabled' : '') . '> <span>' . escape($ending->label())
                . ('' !== $example ? ' <code>' . escape($example) . '</code>' : '') . '</span></label>';
        }
        $options .= '<input class="form-control site-choice__suffix" name="suffix" value="' . escape(self::suffix()) . '" placeholder=".htm" maxlength="11"'
            . ' pattern="\.?[a-z0-9]{1,10}" spellcheck="false" aria-label="' . escape(I18n::msg('fluid_url_ending_custom')) . '"' . ($locked ? ' disabled' : '') . '>';

        return '<p class="site-intro">' . escape(I18n::msg('fluid_addresses_intro')) . '</p>'
            . ($locked ? '<p class="site-intro"><b>' . escape(I18n::msg('fluid_addresses_by_project')) . '</b></p>' : '')
            . '<form class="site-card" data-site-form="addresses_save" data-tab="addresses"><fieldset class="blocks-form__field"><legend class="control-label">'
            . escape(I18n::msg('fluid_addresses_ending')) . '</legend>' . $options . '</fieldset>'
            . ($locked ? '' : '<div class="site-actions"><button type="submit" class="btn btn--primary">' . escape(I18n::msg('fluid_addresses_save')) . '</button></div>') . '</form>';
    }

    private static function stored(): ?UrlFormat
    {
        if (!isset(self::config()['ending'])) {
            return null;
        }
        try {
            return self::ending()->format(self::suffix());
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        $config = FluidAddon::instance()->getConfig(self::CONFIG, []);

        return is_array($config) ? $config : [];
    }

    /**
     * Paths of a real menu item and a real page below it for the examples.
     *
     * @return array{0: string, 1: string}
     */
    private static function samples(): array
    {
        $lang = Language::getStartId();
        $home = Article::getSiteStartArticleId();
        foreach (Routes::all() as $id => $byLang) {
            $path = $byLang[$lang]['path'] ?? '';
            $parent = substr($path, 0, (int) strrpos($path, '/'));
            if ($id !== $home && str_contains($path, '/') && !Routes::isCategory($id, $lang) && null !== Routes::find($parent, $lang)) {
                return [$parent, $path];
            }
        }

        return ['leistungen', 'leistungen/beratung'];
    }
}
