<?php

namespace Project\Routing;

use Klxm\Fluid\Routing\Attribute\AsRouteSource;
use Klxm\Fluid\Routing\Attribute\Description;
use Klxm\Fluid\Routing\Attribute\FromTable;
use Klxm\Fluid\Routing\Attribute\Image;
use Klxm\Fluid\Routing\Attribute\Modified;
use Klxm\Fluid\Routing\Attribute\Slug;
use Klxm\Fluid\Routing\Attribute\Title;
use Klxm\Fluid\Routing\TableSource;

/**
 * News from a table below the page „Aktuelles“ (ID 12): /aktuelles/sommerfest-im-garten.
 *
 * The page receives the ID as ?news_id=… and renders the entry. URL, sitemap, canonical, hreflang, Open Graph and
 * the 301 redirect after a title change come from routing. Title, description, image and URL can be edited in
 * fluid in the detail page's page settings.
 */
#[AsRouteSource('news', label: 'News')]
#[FromTable('rex_news', page: 12, where: 'status = 1', param: 'news_id')]
#[Slug('title')]
#[Title('seo_title')]
#[Description('teaser')]
#[Image('image')]
#[Modified('updatedate')]
final class NewsRoutes extends TableSource {}
