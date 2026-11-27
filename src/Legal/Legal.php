<?php

namespace Klxm\Fluid\Legal;

use Klxm\Fluid\Settings;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Language\Language;

use function function_exists;
use function preg_match;
use function trim;

/**
 * Legal texts in one place (from KLXM Studio – Core\Legal): imprint, privacy policy and accessibility statement from
 * Website → Einstellungen → „Recht & Suchmaschinen“, in the language of the requested page.
 * For kits, blocks and addons – instead of custom helpers. Without a setting, privacy falls back to a top-level page
 * whose name starts with „Datenschutz“ or „Privacy“.
 *
 *     Legal::url('privacy')   Legal::links()   Legal::article('imprint')
 *
 * Optional: privacy notices on forms in a dialog (LegalDialog) – `Legal::$config = new LegalConfig(dialog: true)`.
 */
final class Legal
{
    /** Type => key of the setting */
    public const array KEYS = ['imprint' => 'imprint_page', 'privacy' => 'privacy_page', 'accessibility' => 'accessibility_page'];

    /** Type => label (source German, translated with the kit's lt()) */
    public const array LABELS = ['imprint' => 'Impressum', 'privacy' => 'Datenschutz', 'accessibility' => 'Barrierefreiheit'];

    /** Einstellungen des Projekts (Project::configure()); null = Vorgaben */
    public static ?LegalConfig $config = null;

    public static function config(): LegalConfig
    {
        return self::$config ??= new LegalConfig();
    }

    /** Page of a type in the current language (online only) – or null */
    public static function article(string $type): ?Article
    {
        $value = trim((string) Settings::get(self::KEYS[$type] ?? '', ''));
        $article = preg_match('~^(?:redaxo://|page:)?(\d+)$~', $value, $m) ? Article::get((int) $m[1], Language::getCurrentId()) : null;
        if (null === $article && '' === $value && 'privacy' === $type) {
            foreach (Article::getRootArticles(true) as $a) {
                if (preg_match('~^(datenschutz|privacy)~i', $a->name)) {
                    $article = $a;
                    break;
                }
            }
        }

        return null !== $article && $article->isOnline() ? $article : null;
    }

    /** URL of a type – a page of the website or an entered URL (e.g. https://…) – or '' */
    public static function url(string $type): string
    {
        $article = self::article($type);
        if (null !== $article) {
            return $article->getUrl();
        }
        $value = trim((string) Settings::get(self::KEYS[$type] ?? '', ''));

        return preg_match('~^(https?://|/)~', $value) ? $value : '';
    }

    /**
     * Links imprint, privacy, accessibility (only those set).
     *
     * @return list<array{type: string, label: string, href: string}>
     */
    public static function links(): array
    {
        $out = [];
        foreach (self::LABELS as $type => $label) {
            $href = self::url($type);
            if ('' !== $href) {
                $out[] = ['type' => $type, 'label' => function_exists('lt') ? lt($label) : $label, 'href' => $href];
            }
        }

        return $out;
    }
}
