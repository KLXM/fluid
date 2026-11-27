<?php

use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Migration\Migration;

/*
 * Addresses, domains, redirects, 404 log and SEO fields move from fluid's own routing (until 1.0.0-beta.16) to
 * klxm/routes. Fixed slugs and paths stay exactly as they were, landing domains become websites. The old tables stay
 * until fluid drops them in a later release.
 */
return new class extends Migration {
    public function up(): void
    {
        $sql = Sql::factory();
        $has = static fn (string $t): bool => Table::get($t)->exists();
        if (!$has('rex_routes_path')) {
            return;   // klxm/routes not installed – nothing to move
        }

        if ($has('rex_fluid_route')) {
            $sql->setQuery('REPLACE INTO rex_routes_path (article_id, language_id, site, slug, path, own_path, hide)
                SELECT article_id, language_id, "main", slug, path, own_path, hide FROM rex_fluid_route');
        }
        if ($has('rex_fluid_page')) {
            $sql->setQuery('INSERT INTO rex_routes_page (article_id, language_id, title, description, image, noindex, updated)
                SELECT article_id, language_id, COALESCE(title, ""), description, COALESCE(image, ""), noindex, NOW() FROM rex_fluid_page
                ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), image = VALUES(image), noindex = VALUES(noindex)');
        }
        if ($has('rex_fluid_redirect')) {
            $sql->setQuery('INSERT INTO rex_routes_redirect (site, source, source_hash, wildcard, target, code, keep_query, note, origin, hits, last_hit, active, expires, created, updated)
                SELECT "", source, source_hash, wildcard, target, code, 1, note, origin, hits, last_hit, active, expires, created, updated FROM rex_fluid_redirect f
                WHERE NOT EXISTS (SELECT 1 FROM rex_routes_redirect r WHERE r.source_hash = f.source_hash AND r.site = "")');
        }
        if ($has('rex_fluid_404')) {
            $sql->setQuery('INSERT INTO rex_routes_404 (site, path, path_hash, hits, first_seen, last_seen)
                SELECT "main", path, SHA1(CONCAT("main|", path_hash)), hits, first_seen, last_seen FROM rex_fluid_404');
        }
        if ($has('rex_fluid_domain')) {
            foreach ($sql->getArray('SELECT * FROM rex_fluid_domain ORDER BY id') as $d) {
                $host = strtolower(trim((string) $d['host']));
                if ('' === $host || [] !== $sql->getArray('SELECT id FROM rex_routes_domain WHERE host = ?', [$host])) {
                    continue;
                }
                $site = 'main';
                if ('landing' === $d['role'] && $d['article_id']) {
                    $site = 'landing-' . (int) $d['id'];
                    $sql->setQuery('INSERT IGNORE INTO rex_routes_site (`key`, name, root_id, single, mirror, languages, settings) VALUES (?, ?, ?, ?, ?, "", "{}")',
                        [$site, $host, (int) $d['article_id'], (int) $d['subtree'] ? 0 : 1, 'mirror' === $d['canonical'] ? 1 : 0]);
                }
                $role = 'alias' === $d['role'] ? 'alias' : 'primary';
                $sql->setQuery('INSERT INTO rex_routes_domain (host, site, role, language_id, https, note) VALUES (?, ?, ?, NULL, 0, ?)', [$host, $site, $role, $d['note'] ?? null]);
            }
        }
        // klxm/routes rebuilds the addresses once on the next request (cache, websites of the pages)
        File::put(Path::addonData('routes', 'rebuild'), '1');
    }
};
