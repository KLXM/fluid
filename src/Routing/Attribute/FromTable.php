<?php

namespace Klxm\Fluid\Routing\Attribute;

use Attribute;
use Klxm\Fluid\Routing\TableSource;

/**
 * Records of a table as addresses below a page ({@see TableSource}).
 *
 * @see Slug, Title, Description, Image, Modified for the columns
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FromTable
{
    /**
     * @param non-empty-string $table table, e.g. 'rex_news' (YForm tables too)
     * @param int $page page (article ID) below which the entries live and which displays them
     * @param string $where condition for visible entries (SQL, without visitor values), e.g. 'status = 1'
     * @param string|null $language column with the language ID; null = every entry in all languages
     * @param string $param parameter under which the page gets the ID of the entry (request and $_GET)
     * @param string $id column with the ID
     */
    public function __construct(
        public string $table,
        public int $page,
        public string $where = '',
        public ?string $language = null,
        public string $param = 'id',
        public string $id = 'id',
    ) {}
}
