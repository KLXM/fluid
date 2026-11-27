<?php

namespace Klxm\Fluid\Routing;

use Klxm\Fluid\Routing\ExtensionPoint\RobotsTxt;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;

/**
 * robots.txt: rules plus the line „Sitemap:“ (always).
 * Optional (allowPreviews(), off by default): if the rules block everything (`Disallow: /` for all), link previews may
 * still read when sharing (PREVIEW_BOTS) – Facebook, LinkedIn & co. obey robots.txt and would otherwise show only a
 * grey image. Search engines stay out. Only explicitly, because whoever blocks everything often means it (test, preview).
 *   Extension point RobotsTxt, part 'rules'  replace the rules (e.g. own text from the website settings)
 *   Extension point RobotsTxt, part 'text'   final text including the sitemap line – add rules.
 */
final class Robots
{
    public const string DEFAULT = "User-agent: *\nDisallow: /redaxo/\nDisallow: /*?rex_version=";

    /** Link preview when sharing (image, title, text) – they only read what someone shares; no search engines */
    public const array PREVIEW_BOTS = ['facebookexternalhit', 'Facebot', 'LinkedInBot', 'Twitterbot', 'Slackbot-LinkExpanding', 'Slackbot',
        'WhatsApp', 'TelegramBot', 'Discordbot', 'Mastodon', 'Pinterestbot', 'SkypeUriPreview'];

    private static bool $previews = false;

    /** Allow link previews even when the website is blocked (e.g. live website before launch) */
    public static function allowPreviews(bool $allow = true): void
    {
        self::$previews = $allow;
    }

    public static function text(): string
    {
        $rules = trim((string) Extension::dispatch(new RobotsTxt(self::DEFAULT, 'rules')));
        if (self::$previews && self::locked($rules)) {
            $rules .= "\n\n# Linkvorschau beim Teilen (keine Suchmaschinen)\nUser-agent: " . implode("\nUser-agent: ", self::PREVIEW_BOTS)
                . "\nDisallow: " . Router::base() . "/redaxo/\nAllow: /";
        }
        $r = Core::getRequest();

        return (string) Extension::dispatch(new RobotsTxt($rules . "\n\nSitemap: " . $r->getSchemeAndHttpHost() . Router::base() . "/sitemap.xml\n"));
    }

    /** Do the rules block the whole website for all bots – without mentioning a preview bot? */
    private static function locked(string $rules): bool
    {
        if (1 !== preg_match('~^User-agent:\s*\*\s*\R(?:(?!User-agent).*\R)*?Disallow:\s*/\s*$~mi', $rules)) {
            return false;
        }
        foreach (self::PREVIEW_BOTS as $bot) {
            if (false !== stripos($rules, $bot)) {
                return false;   // own rules for preview bots: don't interfere
            }
        }

        return true;
    }
}
