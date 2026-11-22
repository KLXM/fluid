<?php

namespace Klxm\Fluid\Glossary;

use Klxm\Fluid\ApiError;
use Klxm\Fluid\Data\RecordBlocks;
use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Routing\Routes;
use Redaxo\Core\Security\User;

/**
 * Glossar aus der YForm-Tabelle rex_fluid_glossary (GlossaryTable) – Standard-Datenquelle von Glossary. Ohne YForm ist
 * das Glossar einfach nicht verfügbar.
 */
final class YFormGlossaryStore implements GlossaryStore
{
    public const string TABLE = 'rex_fluid_glossary';

    /** @var array<string, list<array<string, mixed>>> */
    private array $cache = [];

    private function table(): ?\Yakamara\YForm\Table\Table
    {
        return YFormBridge::table(self::TABLE);
    }

    public function available(): bool
    {
        return null !== $this->table();
    }

    public function terms(string $language, bool $all = false): array
    {
        $key = $language . ($all ? '+' : '');
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $t = $this->table();
        if (null === $t) {
            return [];
        }
        try {
            $q = $t::query()->whereAny(static fn (\Yakamara\YForm\Data\Query $g) => $g->whereIn('lang', ['', $language])->whereNull('lang'))->orderBy('term', 'ASC')->limit(3000);
            if (!$all) {
                $q->where('online', true);
            }
            $rows = iterator_to_array($q->find(), false);
        } catch (\Throwable) {
            return [];   // Tabelle noch nicht angelegt (vor migrate)
        }
        $out = [];
        $slugs = [];
        foreach ($rows as $r) {
            $term = trim((string) $r->get('term'));
            if ('' === $term) {
                continue;
            }
            // Slug aus dem Begriff; doppelte bekommen die ID dazu
            $slug = Routes::slugify($term) ?: 'begriff';
            if (isset($slugs[$slug])) {
                $slug .= '-' . $r->id;
            }
            $slugs[$slug] = true;
            $out[] = [
                'id' => (int) $r->id, 'key' => $slug, 'term' => $term,
                'short' => Glossary::short((string) $r->get('short')),
                'variants' => Glossary::splitVariants((string) $r->get('variants')),
                'long' => '' !== trim(\Klxm\Fluid\BlockData::text($r->get('text'))),
                'online' => (bool) $r->get('online'),
                'language' => (string) ($r->get('lang') ?? ''),
            ];
        }

        return $this->cache[$key] = $out;
    }

    public function longHtml(int $id, int $articleId): string
    {
        $t = $this->table();
        $r = $t ? $t::query()->where('id', $id)->first() : null;
        if (null === $t || null === $r) {
            return '';
        }
        $f = $t->getField('text');

        return $f instanceof \Klxm\Fluid\Data\FluidBlocks ? RecordBlocks::render($t, $r, $f, $articleId) : '';
    }

    public function create(string $term, string $short, array $variants, bool $online, string $language): int
    {
        $t = $this->table() ?? throw new ApiError('Das Glossar ist nicht eingerichtet (YForm fehlt bzw. console migrate).');
        $term = trim(strip_tags($term));
        $short = trim(strip_tags($short));
        if ('' === $term || '' === $short) {
            throw new ApiError('Bitte Begriff und Kurztext angeben.');
        }
        if (mb_strlen($short) > Glossary::SHORT_MAX) {
            throw new ApiError('Der Kurztext ist zu lang (höchstens ' . Glossary::SHORT_MAX . ' Zeichen).');
        }
        $r = $t::create(['term' => mb_substr($term, 0, 120), 'short' => $short, 'variants' => implode("\n", $variants), 'online' => $online, 'lang' => $language]);
        $errors = $r->validate();
        if (\count($errors) > 0) {
            throw new ApiError('Nicht gespeichert: ' . implode(' ', $errors->all()));
        }
        $r->save();
        $this->cache = [];

        return (int) $r->id;
    }

    public function addVariant(int $id, string $variant): void
    {
        $t = $this->table() ?? throw new ApiError('Das Glossar ist nicht eingerichtet.');
        $r = $t::query()->where('id', $id)->first() ?? throw new ApiError('Begriff nicht gefunden.');
        $list = Glossary::splitVariants((string) $r->get('variants'));
        $variant = trim(strip_tags($variant));
        if ('' === $variant || \in_array($variant, $list, true) || $variant === (string) $r->get('term')) {
            return;
        }
        $list[] = $variant;
        $r->set('variants', implode("\n", $list));
        $r->save();
        $this->cache = [];
    }

    public function canEdit(?User $user): bool
    {
        $t = $this->table();

        return null !== $t && null !== $user && !Editing::isGuest() && RecordBlocks::canEdit($user, $t);
    }

    public function editUrl(?int $id = null): ?string
    {
        $t = $this->table();

        return null !== $t ? YFormBridge::editUrl($t, $id) : null;
    }
}
