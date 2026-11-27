<?php

namespace Klxm\Fluid\Routing\Seo;

/**
 * Settings for the page head, set by the project in Project::configure():
 *
 *     Head::$config = new HeadConfig(siteName: 'Muster GmbH', tags: [HeadTag::meta('theme-color', '#0a5')]);
 */
final readonly class HeadConfig
{
    /**
     * @param string|null $siteName name of the website (og:site_name, title); null = name of the instance or what an AddOn sets
     * @param list<HeadTag> $tags own tags on every page (the same key replaces the value from routing)
     * @param string|null $twitterCard card for X/Twitter: with image „summary_large_image“, otherwise „summary“; null = no tag
     *     (X reads the other twitter:* values from og:*)
     * @param string $titleSeparator between page title and website name
     */
    public function __construct(
        public ?string $siteName = null,
        public array $tags = [],
        public ?string $twitterCard = 'summary_large_image',
        public string $titleSeparator = ' – ',
    ) {}
}
