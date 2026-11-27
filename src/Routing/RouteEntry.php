<?php

namespace Klxm\Fluid\Routing;

/**
 * A stored address of a source: an entry (record, product …) in one language, shown by a page.
 *
 * The path is created on rebuild: path of the page + „/“ + slug (made unique with -2, -3 …). A slug may contain „/“
 * (e.g. „2026/titel“); the parts are cleaned like page names.
 */
final readonly class RouteEntry
{
    /**
     * @param string $source identifier of the source
     * @param string $id ID of the entry in the source
     * @param int $articleId page under which the address lies and which shows the entry
     * @param array<string, scalar> $params parameters the page receives when called (request and $_GET)
     * @param int|null $modified time of the last change (sitemap)
     * @param bool $sitemap include in the sitemap.xml
     * @param list<string> $images images for the sitemap (media pool files or absolute addresses)
     * @param string|null $path path assigned by the router (without language prefix and suffix) – set only when reading
     */
    public function __construct(
        public string $source,
        public string $id,
        public int $languageId,
        public int $articleId,
        public string $slug,
        public array $params = [],
        public ?int $modified = null,
        public bool $sitemap = true,
        public array $images = [],
        public ?string $path = null,
    ) {}

    /** Key „quelle:id“ (e.g. for the editor) */
    public function key(): string
    {
        return $this->source . ':' . $this->id;
    }
}
