<?php

namespace Klxm\Fluid\Routing\Seo;

/**
 * Meta data of a page or entry – the page head is built from it ({@see Head::render()}).
 *
 * Order: page (name, address, languages) → ArticleMeta (e.g. the page's SEO fields) → entry of a source or
 * resolver → Head::override() (blocks) → PageMetaResolved (final changes). Empty values never override.
 */
final class PageMeta
{
    /** @var array<string, HeadTag> */
    public array $tags = [];

    /**
     * @param string|null $title title of the page (og:title); the title in the browser tab is title + website name
     * @param string|null $documentTitle own, complete title in the browser tab (replaces title + website name)
     * @param string|null $image image: media pool file or absolute URL
     * @param string $type og:type, e.g. website, article, product
     * @param string|null $url canonical absolute URL
     * @param array<string, string> $alternates hreflang => absolute URL (incl. x-default)
     * @param list<array<string, mixed>> $jsonLd nodes for the @graph (schema.org)
     */
    public function __construct(
        public ?string $title = null,
        public ?string $documentTitle = null,
        public ?string $siteName = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
        public ?string $imageType = null,
        public string $type = 'website',
        public ?string $url = null,
        public ?string $locale = null,
        public array $alternates = [],
        public ?bool $noindex = null,
        public ?bool $nofollow = null,
        public ?int $published = null,
        public ?int $modified = null,
        public array $jsonLd = [],
    ) {}

    /** Take over the set values of $other (not empty strings and null); tags and JSON-LD are added */
    public function merge(self $other): static
    {
        foreach (['title', 'documentTitle', 'siteName', 'description', 'image', 'imageAlt', 'imageWidth', 'imageHeight', 'imageType', 'url', 'locale', 'noindex', 'nofollow', 'published', 'modified'] as $k) {
            if (null !== $other->$k && '' !== $other->$k) {
                $this->$k = $other->$k;
            }
        }
        // new title without own tab title: the old tab title (e.g. SEO title of the list page) no longer fits
        if (null !== $other->title && '' !== $other->title && null === $other->documentTitle) {
            $this->documentTitle = null;
        }
        // new image: the old dimensions no longer apply
        if (null !== $other->image && '' !== $other->image && null === $other->imageWidth) {
            $this->imageWidth = $this->imageHeight = null;
            $this->imageType = $other->imageType;
        }
        if ('website' !== $other->type) {
            $this->type = $other->type;
        }
        if ([] !== $other->alternates) {
            $this->alternates = $other->alternates;
        }
        $this->add(...array_values($other->tags));
        $this->jsonLd = [...$this->jsonLd, ...$other->jsonLd];

        return $this;
    }

    public function add(HeadTag ...$tags): static
    {
        foreach ($tags as $t) {
            unset($this->tags[$t->key]);   // neu ans Ende
            $this->tags[$t->key] = $t;
        }

        return $this;
    }
}
