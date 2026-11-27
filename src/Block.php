<?php

namespace Klxm\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleSlice;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Wird an jede Block-Ausgabe übergeben (`$b`). `$b->data` enthält die Felder mit Standardwerten.
 *
 * Kit-Ausgaben markieren Textfelder mit `$b->edit('feld')` – im Bearbeiten-Modus werden sie dann direkt auf der Website
 * bearbeitbar (`data-edit`), sonst liefert die Methode einen leeren String.
 */
final class Block
{
    /** @var array<string, mixed> */
    public readonly array $tunes;

    public string $id {
        get => $this->slice ? (string) $this->slice->id : $this->ownId;
    }

    /** Seite, auf der der Block steht (für Adressen wie Blättern oder Detailansicht) */
    public int $articleId {
        get => $this->slice->articleId ?? ($this->ownArticleId ?: (int) Article::getCurrentId());
    }

    /** Block, dessen View gerade ausgegeben wird (BlockModule::renderInner) */
    public static ?self $rendering = null;

    private static bool $firstImageUsed = false;

    /** Blocktyp ohne Kit-Präfix (`cta`, `media_text` …) */
    public string $type {
        get => $this->module::type();
    }

    /** @var array<int, string> */
    private static array $readonly = [];

    /** @param ArticleSlice|null $slice null = Block aus Daten (z. B. YForm-Feld „Fluid-Blöcke“), dann $id und $articleId angeben */
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
            unset($tunes['background']);   // leer = Standard des Blocks
        }
        $this->tunes = $tunes + ['background' => $module::definition()['background'] ?? 'white', 'anchor' => '', 'spaceTop' => 'normal', 'spaceBottom' => 'normal'];
    }

    /** Erster sichtbarer Block der Seite – oder eine Spalte des ersten Layouts? */
    public function isFirst(): bool
    {
        if (null === $this->slice) {
            return false;
        }
        $slice = Layout::parentOf($this->slice) ?? $this->slice;

        return null === $slice->getPreviousSlice(!Editing::active());
    }

    /**
     * Soll dieses Bild ohne Angabe im Kit sofort und mit Vorrang laden? Ja für das erste Bild im ersten Block der Seite
     * (meist der Einstieg, wichtig für LCP) – nur einmal je Seite.
     */
    public static function firstImage(): bool
    {
        if (self::$firstImageUsed || null === self::$rendering || !self::$rendering->isFirst()) {
            return false;
        }

        return self::$firstImageUsed = true;
    }

    /** Block aus einem Slice (Seite) oder aus Daten (YForm-Feld)? */
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
     * Macht ein Feld im Bearbeiten-Modus direkt auf der Website bearbeitbar.
     * Pfad mit Punkten für Listen: `items.0.title`. Modus: `plain` (einzeilig), `lines` (mehrzeilig), `rich` (Formatierung).
     */
    public function edit(string $path, string $mode = 'plain'): string
    {
        if (!Editing::active() || '' !== $this->readonly()) {
            return '';   // gesperrt bzw. ohne Modulrecht: nicht direkt auf der Seite bearbeitbar
        }

        return ' data-edit="' . escape($path) . '"' . ('plain' !== $mode ? ' data-edit-mode="' . escape($mode) . '"' : '');
    }

    /**
     * Darf die angemeldete Person diesen Block ändern? '' = ja, 'perm' = kein Modulrecht, 'lock' = gesperrt (Lock).
     * Blöcke aus Daten (Datensätze) prüft Data\RecordBlocks selbst.
     */
    public function readonly(): string
    {
        if (null === $this->slice || !Editing::active()) {
            return '';
        }

        return self::$readonly[$this->slice->id] ??= Lock::reason(Editing::user(), $this->slice);
    }

    /** Zentral gepflegte Inhalte (z. B. aus den Einstellungen) im Bearbeiten-Modus kennzeichnen */
    public function central(?string $label = null): string
    {
        return Editing::active() ? ' data-central="' . escape($label ?? 'Einstellungen') . '"' : '';
    }

    /**
     * „Ziel bearbeiten“ an Karten und Kacheln, die auf eine Seite oder einen Datensatz zeigen – als erstes Kind der Karte:
     * `<?= $b->targetEdit($it['link'], $it['title']) ?>`. Für Besucher und ohne Recht leer (siehe TargetEdit).
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

    /** Zusätzliche Attribute des Abschnitts für den Einblend-Effekt (z. B. data-ncss-reveal für das Skript des Kits) */
    public function revealAttrs(): string
    {
        $attr = (string) ($this->revealOption()['attr'] ?? '');

        return '' !== $attr ? ' ' . $attr : '';
    }

    /**
     * Einblend-Effekt (Abschnitt → „Einblend-Effekt“, Optionen aus kit.php → 'reveal') – nur für Besucher: nicht beim
     * Bearbeiten, nicht beim ersten Block der Seite (wichtig für den ersten Eindruck/LCP) und nicht, wenn im Design
     * „Dezente Animationen“ aus ist. „Bewegung reduzieren“ beachtet das CSS des Kits.
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
