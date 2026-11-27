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
 * News aus einer Tabelle unter der Seite „Aktuelles“ (ID 12): /aktuelles/sommerfest-im-garten.
 *
 * Die Seite bekommt die ID als ?news_id=… und gibt den Eintrag aus. Adresse, Sitemap, Canonical, hreflang, Open Graph
 * und die 301-Weiterleitung nach einer Titeländerung kommen vom Routing. Titel, Beschreibung, Bild und Adresse lassen
 * sich in fluid in den Seiteneinstellungen der Detailseite bearbeiten.
 */
#[AsRouteSource('news', label: 'News')]
#[FromTable('rex_news', page: 12, where: 'status = 1', param: 'news_id')]
#[Slug('title')]
#[Title('seo_title')]
#[Description('teaser')]
#[Image('image')]
#[Modified('updatedate')]
final class NewsRoutes extends TableSource {}
