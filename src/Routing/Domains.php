<?php

namespace Klxm\Fluid\Routing;

use Redaxo\Core\Database\Sql;
use Throwable;

use function in_array;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Domains of a website (table rex_fluid_domain, model from KLXM Studio – Core\Domains and Core\Landings):
 *
 *  - primary: main address. Canonical, sitemap and absolute links use it. Without an entry the requested host applies.
 *  - alias:   another domain of the same website – redirects with 301 to the main address (never for local hosts).
 *  - landing: own domain for one page (optionally with subpages) – „/“ shows the page. Links to pages of the
 *             landing page stay on the landing domain, all others point absolutely to the main address.
 *             Canonical „own“ = landing domain is authoritative, „mirror“ = main address.
 */
final class Domains
{
    public const string TABLE = 'rex_fluid_domain';

    /** @var list<Domain>|null */
    private static ?array $all = null;

    /** @return list<Domain> */
    public static function all(): array
    {
        if (null !== self::$all) {
            return self::$all;
        }
        self::$all = [];
        try {
            foreach (Sql::factory()->getArray('SELECT * FROM ' . self::TABLE . ' ORDER BY id') as $r) {
                $role = DomainRole::tryFrom((string) $r['role']);
                if (!$role || '' === trim((string) $r['host'])) {
                    continue;
                }
                self::$all[] = new Domain((int) $r['id'], self::host((string) $r['host']), $role,
                    $r['article_id'] ? (int) $r['article_id'] : null, (bool) $r['subtree'], 'mirror' !== $r['canonical']);
            }
        } catch (Throwable) {
            // table not created yet
        }

        return self::$all;
    }

    public static function primary(): ?Domain
    {
        foreach (self::all() as $d) {
            if (DomainRole::Primary === $d->role) {
                return $d;
            }
        }

        return null;
    }

    public static function forHost(string $host): ?Domain
    {
        $host = self::host($host);
        foreach (self::all() as $d) {
            if ($d->host === $host) {
                return $d;
            }
        }

        return null;
    }

    /** Landing domain a page belongs to (entry page or – with subpages – below it) */
    public static function landingFor(int $articleId, array $ancestors): ?Domain
    {
        foreach (self::all() as $d) {
            if (DomainRole::Landing === $d->role && null !== $d->articleId
                && ($d->articleId === $articleId || ($d->subtree && in_array($d->articleId, $ancestors, true)))) {
                return $d;
            }
        }

        return null;
    }

    /** Host without scheme, path and port, lowercase */
    public static function host(string $h): string
    {
        $h = strtolower(trim($h));
        $h = (string) preg_replace('~^https?://~', '', $h);

        return trim((string) preg_replace('~[/:].*$~', '', $h), '.');
    }

    /** Local development address – never the source of a redirect to the main address */
    public static function isLocal(string $host): bool
    {
        $h = self::host($host);

        return 'localhost' === $h || str_ends_with($h, '.localhost') || str_ends_with($h, '.test') || str_starts_with($h, '127.') || '::1' === $h;
    }

    public static function reset(): void
    {
        self::$all = null;
    }
}
