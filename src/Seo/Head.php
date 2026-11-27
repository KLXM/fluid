<?php

namespace Klxm\Fluid\Seo;

use Klxm\Fluid\Editing;
use Klxm\Fluid\Settings;
use Klxm\ImageEditor\ImageEditor;
use Klxm\Meta\Alt;
use Klxm\Routes\ExtensionPoint\PageMetaResolved;
use Klxm\Routes\ExtensionPoint\StructuredGraph;
use Klxm\Routes\Router;
use Klxm\Routes\Seo\PageMeta;
use Redaxo\Core\Content\Article;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\Media;

use const FILTER_VALIDATE_EMAIL;

/**
 * fluid's data for the routing's page head (\Klxm\Routes\Seo\Head): image for social networks with image_editor
 * (1200 × 630 as JPEG around the focal point) and schema.org (StructuredData). Components add their own nodes via
 * FLUID_STRUCTURED_DATA, custom tags via the routing's extension point PageMetaResolved.
 */
final class Head
{
    /** @internal */
    #[AsExtension]
    public static function resolved(PageMetaResolved $ep): PageMeta
    {
        $m = $ep->subject;
        // Networks: 1200 × 630 as JPEG around the focal point (LinkedIn can't handle WebP)
        if (null !== $m->image && '' !== $m->image && !preg_match('~^https?://~i', $m->image) && class_exists(ImageEditor::class)
            && ($media = Media::get($m->image)) && null !== ($share = ImageEditor::shareUrl($media->fileName))) {
            $m->imageAlt ??= Alt::of($media) ?: null;
            $m->image = Router::absolute($share);
            $m->imageType = 'image/jpeg';
            $m->imageWidth = 1200;
            $m->imageHeight = 630;
        }
        return $m;
    }

    /**
     * @internal schema.org of fluid (organization from the website settings, blocks, blog, maps, glossary …) instead of
     * the basic graph of klxm/routes
     *
     * @return list<array<string, mixed>>
     */
    #[AsExtension]
    public static function graph(StructuredGraph $ep): array
    {
        if (Editing::active()) {
            return [];
        }

        return array_values((array) (StructuredData::graph($ep->article, $ep->meta)['@graph'] ?? $ep->subject));
    }

    /** schema.org node of the organization: name, logo, address, phone, email, geo, opening hours, social media */
    public static function organizationNode(): array
    {
        $s = static fn (string $k) => trim((string) Settings::get($k, ''));
        $type = $s('schema_type') ?: 'Organization';
        $origin = Router::canonical(Article::getSiteStartArticleId(), Language::getCurrentId());
        $data = ['@type' => $type, '@id' => $origin . '#org', 'name' => Settings::name(), 'url' => $origin];
        if ('' !== $s('logo') && ($m = Media::get($s('logo')))) {
            $data['logo'] = Router::absolute($m->getUrl());
        }
        if ('' !== $s('street') || '' !== $s('city')) {
            $data['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s('street'), 'postalCode' => $s('zip'), 'addressLocality' => $s('city'), 'addressCountry' => $s('country')]);
        }
        if ('' !== $s('phone')) {
            $data['telephone'] = $s('phone');
        }
        if (filter_var($s('email'), FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $s('email');
        }
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $spec = [];
        foreach ((array) Settings::get('hours', []) as $r) {
            if (isset($days[(int) ($r['tag'] ?? -1)]) && !empty($r['von']) && !empty($r['bis'])) {
                $slots = !empty($r['pause_von']) && !empty($r['pause_bis']) ? [[$r['von'], $r['pause_von']], [$r['pause_bis'], $r['bis']]] : [[$r['von'], $r['bis']]];
                foreach ($slots as [$o, $c]) {
                    $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days[(int) $r['tag']], 'opens' => $o, 'closes' => $c];
                }
            }
        }
        if ($spec && 'Organization' !== $type && 'Person' !== $type) {
            $data['openingHoursSpecification'] = $spec;
        }
        $same = array_values(array_filter(array_map(static fn ($x) => (string) ($x['url'] ?? ''), (array) Settings::get('social', [])), static fn ($u) => (bool) preg_match('~^https?://~', $u)));
        if ($same) {
            $data['sameAs'] = $same;
        }

        return $data;
    }
}
