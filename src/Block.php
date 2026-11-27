<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Passed to every block output (`$b`). `$b->data` contains the fields with default values.
 *
 * Kit outputs mark text fields with `$b->edit('feld')` – in edit mode they then become editable directly on the
 * website (`data-edit`), otherwise the method returns an empty string.
 */
final class Block
{
    /** @var array<string, mixed> */
    public readonly array $tunes;

    public string $id {
        get => $this->slice ? (string) $this->slice->id : $this->ownId;
    }

    /** Page the block is on (for addresses like pagination or detail view) */
    public int $articleId {
        get => $this->slice->articleId ?? ($this->ownArticleId ?: (int) Article::getCurrentId());
    }

    /** Block whose view is currently being rendered (BlockModule::renderInner) */
    public static ?self $rendering = null;

    private static bool $firstImageUsed = false;

    /** Block type without kit prefix (`cta`, `media_text` …) */
    public string $type {
        get => $this->module::type();
    }

    /** @var array<int, string> */
    private static array $readonly = [];

    /** @param ArticleSlice|null $slice null = block from data (e.g. YForm field „Fluid-Blöcke“), then pass $id and $articleId */
    public function __construct(
        public readonly ?ArticleSlice $slice,
        /** @var class-string<BlockModule> */
        public readonly string $module,
        /** @var array<string, mixed> */
        public readonly array $data,
        /** @var array<string, mixed> */
        array $tunes = [],
        public readonly ?self $prev = null,
        private readonly string $ownId = '',
        private readonly int $ownArticleId = 0,
    ) {
        if ('' === (string) ($tunes['background'] ?? '')) {
            unset($tunes['background']);   // empty = the block's default
        }
        $this->tunes = $tunes + ['background' => $module::definition()['background'] ?? 'white', 'anchor' => '', 'spaceTop' => 'normal', 'spaceBottom' => 'normal'];
    }

    /** First visible block of the page – or a column of the first layout? */
    public function isFirst(): bool
    {
        if (null === $this->slice) {
            return false;
        }
        $slice = Layout::parentOf($this->slice) ?? $this->slice;

        return null === $slice->getPreviousSlice(!Editing::active());
    }

    /**
     * Should this image load immediately and with priority when the kit does not say? Yes for the first image in the
     * first block of the page (usually the intro, important for LCP) – only once per page.
     */
    public static function firstImage(): bool
    {
        if (self::$firstImageUsed || null === self::$rendering || !self::$rendering->isFirst()) {
            return false;
        }

        return self::$firstImageUsed = true;
    }

    /** Block from a slice (page) or from data (YForm field)? */
    public function fromSlice(): bool
    {
        return null !== $this->slice;
    }

    public function variant(): string
    {
        return (string) ($this->data['variant'] ?? '');
    }

    public function tune(string $key): mixed
    {
        return $this->tunes[$key] ?? null;
    }

    public function domId(): string
    {
        return '' !== (string) $this->tunes['anchor'] ? (string) $this->tunes['anchor'] : 'b-' . $this->id;
    }

    public function titleId(): string
    {
        return $this->domId() . '-title';
    }

    /**
     * Makes a field editable directly on the website in edit mode.
     * Dotted path for lists: `items.0.title`. Mode: `plain` (single line), `lines` (multi-line), `rich` (formatting).
     */
    public function edit(string $path, string $mode = 'plain'): string
    {
        if (!Editing::active() || '' !== $this->readonly()) {
            return '';   // locked or without module permission: not editable directly on the page
        }

        return ' data-edit="' . escape($path) . '"' . ('plain' !== $mode ? ' data-edit-mode="' . escape($mode) . '"' : '');
    }

    /**
     * May the signed-in user change this block? '' = yes, 'perm' = no module permission, 'lock' = locked (Lock).
     * Blocks from data (records) are checked by Data\RecordBlocks itself.
     */
    public function readonly(): string
    {
        if (null === $this->slice || !Editing::active()) {
            return '';
        }

        return self::$readonly[$this->slice->id] ??= Lock::reason(Editing::user(), $this->slice);
    }

    /** Mark centrally maintained content (e.g. from the settings) in edit mode */
    public function central(?string $label = null): string
    {
        return Editing::active() ? ' data-central="' . escape($label ?? 'Einstellungen') . '"' : '';
    }

    /**
     * „Ziel bearbeiten“ on cards and tiles that point to a page or a record – as the card's first child:
     * `<?= $b->targetEdit($it['link'], $it['title']) ?>`. Empty for visitors and without permission (see TargetEdit).
     */
    public function targetEdit(?string $ref, ?string $label = null): string
    {
        return TargetEdit::html($ref, $label);
    }

    public function dark(): bool
    {
        return in_array($this->tunes['background'], $this->module::kit()->darkBackgrounds(), true);
    }

    public function sectionClass(): string
    {
        $c = ['sec', 'sec--' . str_replace('_', '-', $this->type), 'bg-' . $this->tunes['background']];
        if ('normal' !== $this->tunes['spaceTop']) {
            $c[] = 'pt-' . $this->tunes['spaceTop'];
        }
        if ('normal' !== $this->tunes['spaceBottom']) {
            $c[] = 'pb-' . $this->tunes['spaceBottom'];
        }
        if ('' !== $this->variant()) {
            $c[] = 'v-' . $this->variant();
        }
        if (null !== ($r = $this->revealOption())) {
            $c[] = $r['class'];
        }

        return implode(' ', $c);
    }

    /** Additional attributes of the section for the fade-in effect (e.g. data-ncss-reveal for the kit's script) */
    public function revealAttrs(): string
    {
        $attr = (string) ($this->revealOption()['attr'] ?? '');

        return '' !== $attr ? ' ' . $attr : '';
    }

    /**
     * Fade-in effect (section → „Einblend-Effekt“, options from kit.php → 'reveal') – only for visitors: not while
     * editing, not on the page's first block (important for the first impression/LCP) and not when „Dezente
     * Animationen“ is off in the design. The kit's CSS respects „Bewegung reduzieren“.
     *
     * @return array{label: string, class: string, attr?: string}|null
     */
    private function revealOption(): ?array
    {
        $key = (string) ($this->tunes['reveal'] ?? '');
        if ('' === $key || Editing::active()) {
            return null;
        }
        $kit = $this->module::kit();
        $opt = $kit->revealOptions()[$key] ?? null;
        if (null === $opt || $this->isFirst() || false === (bool) ($kit->design()->get('motion') ?? true)) {
            return null;
        }
        $kit->markUsed('@reveal');

        return $opt;
    }

    public function sectionBg(): string
    {
        return '';
    }
}
