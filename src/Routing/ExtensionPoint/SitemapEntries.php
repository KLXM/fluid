<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Klxm\Fluid\Routing\Domain;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * Alle Einträge der sitemap.xml einer Domain – hinzufügen, ändern, entfernen.
 *
 * @extends ExtensionPoint<list<array{loc: string, lastmod: int|null, alternates: array<string, string>, images?: list<string>}>>
 */
final class SitemapEntries extends ExtensionPoint
{
    /** @param list<array{loc: string, lastmod: int|null, alternates: array<string, string>, images?: list<string>}> $entries */
    public function __construct(
        array $entries,
        public readonly ?Domain $landing = null,
    ) {
        parent::__construct(self::class, $entries);
    }
}
