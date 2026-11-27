<?php

namespace Klxm\Fluid\Glossary;

use function count;
use function in_array;
use function Redaxo\Core\View\escape;
use function strlen;

use const ENT_HTML5;
use const ENT_NOQUOTES;
use const ENT_QUOTES;
use const PHP_URL_HOST;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Taken from KLXM Studio (Core\Glossary\Annotator, MIT) – unchanged except for namespace and escaping.
 *
 * Marks glossary terms in the finished HTML (server-side, without DOM library, before the page cache).
 *
 * Rules:
 *  - Readable content only: if there is a <main>, only inside it. Never in links, buttons, form fields, labels, <summary>,
 *    code (<code>, <pre>, <kbd>, <samp>), <abbr>/<dfn>, navigation, page header/footer (header/footer outside of
 *    main/article/section), headings h1…h{headings}, hidden areas (hidden, aria-hidden, inert, .sr-only …),
 *    editable areas (contenteditable, editorial notes) and areas with data-glossary="off".
 *  - Only the first occurrence per term – per page (mode „page“) or per <section>/<article> (mode „section“).
 *  - Unicode word boundaries (umlauts, ß), longest variant first; abbreviations (several capitals, e.g. SPF, IPv6,
 *    MTA-STS) only in exactly this spelling (+ plural s), words case-insensitively (+ common
 *    endings from 4 characters in the page language, option lang: English -s/-es or y → ies, otherwise German endings).
 *    "In quotes" forces the exact spelling without endings.
 *  - Already marked spots (wrapper .gl) are skipped – applying twice changes nothing.
 *
 * Output per match (disclosure/"toggletip", no role=tooltip – the popup contains a link):
 *   <span class="gl"><button type="button" class="gl-term" popovertarget="gl-1" aria-expanded="false" aria-controls="gl-1">SPF</button>
 *   <span class="gl-pop" id="gl-1" popover>…term, short explanation, „Mehr im Glossar →“…</span></span>
 *
 * Terms: list<array{id: int, key: string, term: string, short: string, url: ?string, variants: list<string>, draft?: bool}>
 */
final class Annotator
{
    /** Elements in which nothing is ever marked (content stays untouched) */
    private const SKIP_TAGS = ['a', 'button', 'label', 'summary', 'legend', 'select', 'option', 'textarea', 'input', 'kbd', 'samp', 'var',
        'abbr', 'dfn', 'nav', 'dialog', 'time', 'output', 'meter', 'progress', 'map', 'canvas', 'audio', 'video', 'picture', 'title'];
    /** Areas taken over unread (raw text or code) */
    // Raw text areas and comments; (?|…) keeps the groups equal (comment: group 2 empty) – otherwise a comment shifted the
    // parts of preg_split and the text after it counted as raw text (e.g. fragment comments in debug mode: no marking)
    private const RAW = '~(?|(<(script|style|textarea|template|svg|math|noscript|iframe|object|select|pre|code|head)\b[^>]*>.*?</\2\s*>)|(<!--.*?-->)())~is';
    /** Classes of hidden or technical areas */
    private const SKIP_CLASSES = ['gl', 'gl-pop', 'sr-only', 'visually-hidden', 'screen-reader-text', 'sr-text', 'cms-note', 'skip-link', 'notranslate'];
    /** Classes of labels (eyebrow, tag, badge, chip) – suffix of the class name */
    private const LABEL_CLASS = '~(^|[-_])(eyebrow|kicker|overline|badge|chip|chips|tag|tags|pill|label)$~';
    private const SKIP_ROLES = ['button', 'link', 'navigation', 'banner', 'contentinfo', 'tab', 'tablist', 'menu', 'menubar', 'menuitem', 'option',
        'switch', 'checkbox', 'radio', 'search', 'toolbar', 'tooltip', 'treeitem', 'textbox', 'combobox', 'listbox', 'dialog', 'alertdialog', 'img', 'math'];
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'param'];
    /** Optionally closed elements: a new one of the same kind closes the open one */
    private const AUTO_CLOSE = ['p' => ['p'], 'li' => ['li'], 'dt' => ['dt', 'dd'], 'dd' => ['dt', 'dd'], 'tr' => ['tr', 'td', 'th'], 'td' => ['td', 'th'], 'th' => ['td', 'th']];
    /** Common endings of German words (plural, genitive, dative) */
    private const SUFFIX_WORD = '(?:e|en|n|s|es|er|ern)?';
    /** English: plural -s/-es (words ending in consonant + y: -ies, Annotator::alternative) */
    private const SUFFIX_WORD_EN = '(?:s|es)?';

    private array $byId = [];
    private ?string $rx = null;
    private array $seen = [];
    private int $n = 0;
    /** Number of markings in the last annotate() */
    public int $count = 0;
    /** Marked terms (id => true) in the last annotate() */
    public array $marked = [];
    /** Marked text per term (id => match as in the text, e.g. „SPF-Einträge“) – quick glossary „Auf dieser Seite“ */
    public array $hits = [];

    /**
     * $o: mode (page|section), headings (0–6: skip h1…hN, default 3), exclude (list of term IDs),
     *     labels ([more, close, draft]), prefix (ID prefix, default „gl-“), main (null = automatic: only in <main> if present),
     *     lang (page language for the word endings, default „de“).
     */
    public function __construct(
        array $terms,
        private array $o = [],
    ) {
        $this->o += ['mode' => 'page', 'headings' => 3, 'exclude' => [], 'labels' => [], 'prefix' => 'gl-', 'main' => null, 'lang' => 'de'];
        $this->o['labels'] += ['more' => 'Mehr im Glossar', 'close' => 'Schließen', 'draft' => 'Entwurf'];
        $exclude = array_map('intval', (array) $this->o['exclude']);
        foreach ($terms as $t) {
            if (in_array((int) $t['id'], $exclude, true) || '' === trim((string) ($t['short'] ?? ''))) {
                continue;
            }
            $this->byId[(int) $t['id']] = $t;
        }
        $this->rx = self::pattern(array_values($this->byId), (string) $this->o['lang']);
    }

    /** Regular expression for all variants (longest first, term ID as MARK) – null without terms. $lang: word endings */
    public static function pattern(array $terms, string $lang = 'de'): ?string
    {
        $alts = [];
        foreach ($terms as $t) {
            foreach (self::variants($t) as $v) {
                [$src, $len] = self::alternative($v, $lang);
                if ('' === $src) {
                    continue;
                }
                $alts[] = [$len, $src . '(*MARK:' . (int) $t['id'] . ')'];
            }
        }
        if (!$alts) {
            return null;
        }
        usort($alts, static fn ($a, $b) => $b[0] <=> $a[0]);
        $alts = array_unique(array_column($alts, 1));
        // Left boundary: no letter/digit before, nor part of an address (.@/:) or entity (&); same on the right (full stop allowed)
        return '~(?<![\p{L}\p{N}_@./:&=\\\\])(?:' . implode('|', $alts) . ')(?![\p{L}\p{N}_@=]|[.:/][\p{L}\p{N}])~u';
    }

    /** Term + variants (without empty ones and duplicates) */
    public static function variants(array $t): array
    {
        $out = [];
        foreach ([(string) $t['term'], ...array_map('strval', (array) ($t['variants'] ?? []))] as $v) {
            $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            if ('' !== $v && !in_array($v, $out, true)) {
                $out[] = $v;
            }
        }
        // "Word" in quotes replaces the same word without them (also the term itself): exact spelling only
        foreach ($out as $v) {
            if (preg_match('~^["„“](.+)["“”]$~u', $v, $m)) {
                $out = array_values(array_filter($out, static fn ($x) => $x === $v || mb_strtolower($x) !== mb_strtolower(trim($m[1]))));
            }
        }
        return $out;
    }

    /**
     * Abbreviation or brand spelling? A word with at least two capitals (SPF, IPv6, DNSSEC, MTA-STS) or a
     * capital after a lowercase letter (eID) – then the exact spelling counts.
     */
    public static function caseSensitive(string $v): bool
    {
        foreach (preg_split('~[\s\-/]+~u', $v) ?: [] as $w) {
            if (preg_match_all('~\p{Lu}~u', $w) >= 2 || preg_match('~\p{Ll}\p{Lu}~u', $w)) {
                return true;
            }
        }
        return false;
    }

    /** Language with English instead of German word endings? (en, en-gb …) */
    public static function english(string $lang): bool
    {
        return 'en' === $lang || str_starts_with($lang, 'en-');
    }

    /** Partial expression of a variant: [source, length] */
    private static function alternative(string $v, string $lang = 'de'): array
    {
        $exact = false;
        if (preg_match('~^["„“](.+)["“”]$~u', $v, $m)) {
            $v = trim($m[1]);
            $exact = true;
        }
        if (mb_strlen($v) < 2) {
            return ['', 0];
        }
        $cs = $exact || self::caseSensitive($v);
        // In HTML &, <, > are entities; whitespace may also be a non-breaking space
        $src = preg_quote(htmlspecialchars($v, ENT_NOQUOTES | ENT_HTML5, 'UTF-8'), '~');
        $src = str_replace(' ', '(?:\s|&nbsp;|&#160;|\x{00A0})+', $src);
        // Endings after the last word: abbreviation → plural s (CDNs), word from 4 letters → common endings (DNS-Lookups)
        $suffix = '';
        $words = preg_split('~[\s\-/]+~u', $v) ?: [''];
        $last = (string) end($words);
        if (!$exact) {
            if (preg_match('~\p{Lu}$~u', $v) && preg_match_all('~\p{Lu}~u', $last) >= 2) {
                $suffix = '(?:s)?';
            } elseif (mb_strlen($last) >= 4 && preg_match('~\p{Ll}$~u', $v) && !preg_match('~\p{Ll}\p{Lu}~u', $last)) {
                if (!self::english($lang)) {
                    $suffix = self::SUFFIX_WORD;
                }
                // English: entry → entries (consonant + y), otherwise -s/-es
                elseif (preg_match('~[^aeiouy]y$~iu', $last)) {
                    $src = substr($src, 0, -1) . '(?:y|ies)';
                } else {
                    $suffix = self::SUFFIX_WORD_EN;
                }
            }
        }
        return [($cs ? '(?:' . $src . ')' : '(?i:' . $src . ')') . $suffix, mb_strlen($v)];
    }

    /** Marks a whole page or a fragment */
    public function annotate(string $html): string
    {
        $this->count = 0;
        $this->marked = [];
        $this->hits = [];
        $this->seen = [];
        if (null === $this->rx || '' === $html) {
            return $html;
        }
        // Only the <body> (head, JSON-LD, meta data stay untouched)
        $start = 0;
        $end = strlen($html);
        if (($b = stripos($html, '<body')) !== false) {
            $start = (int) strpos($html, '>', $b) + 1;
            $close = strripos($html, '</body>');
            $end = false !== $close && $close > $start ? $close : $end;
        }
        $body = substr($html, $start, $end - $start);
        // Already marked terms (second pass, e.g. extension + core) count as seen – never twice
        if (str_contains($body, 'class="gl" data-gl="') && preg_match_all('~class="gl" data-gl="([^"]+)"~', $body, $done)) {
            $keys = array_flip($done[1]);
            foreach ($this->byId as $id => $t) {
                if (isset($keys[escape((string) $t['key'])])) {
                    $this->seen[$id] = true;
                }
            }
            if (preg_match_all('~id="' . preg_quote((string) $this->o['prefix'], '~') . '(\d+)"~', $body, $ids)) {
                $this->n = max($this->n, ...array_map('intval', $ids[1]));
            }
        }
        $main = $this->o['main'] ?? (bool) preg_match('~<main[\s>]~i', $body);
        $out = $this->walk($body, $main);
        return $out !== $body ? substr($html, 0, $start) . $out . substr($html, $end) : $html;
    }

    private function walk(string $body, bool $mainOnly): string
    {
        $stack = [];      // [name, skip, main, landmark]
        $skip = 0;
        $inMain = 0;
        $inArticle = 0;   // main/article/section/aside: header/footer inside are content
        $maxH = max(0, min(6, (int) $this->o['headings']));
        $out = '';
        $parts = preg_split(self::RAW, $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
        foreach ($parts as $i => $part) {
            if (2 === $i % 3) {
                continue;
            }                       // name of the raw text element (group 2)
            if (1 === $i % 3) {
                $out .= $part;
                continue;
            }   // raw text area unchanged
            $tokens = preg_split('~(<[a-zA-Z/!?](?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part];
            foreach ($tokens as $j => $tok) {
                if ('' === $tok) {
                    continue;
                }
                if (1 === $j % 2) {
                    $out .= $tok;
                    if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)~', $tok, $m)) {
                        continue;
                    }   // comment, doctype
                    $name = strtolower($m[2]);
                    if ('/' === $m[1]) {
                        for ($k = count($stack) - 1; $k >= 0; --$k) {
                            if ($stack[$k][0] !== $name) {
                                continue;
                            }
                            while (count($stack) > $k) {
                                [, $s, $mn, $ar] = array_pop($stack);
                                $skip -= $s ? 1 : 0;
                                $inMain -= $mn ? 1 : 0;
                                $inArticle -= $ar ? 1 : 0;
                            }
                            break;
                        }
                        continue;
                    }
                    if (in_array($name, self::VOID, true) || str_ends_with($tok, '/>')) {
                        continue;
                    }
                    // Optionally closed elements (<p>, <li> …) – the open element of the same kind ends here
                    if (isset(self::AUTO_CLOSE[$name]) && $stack && in_array($stack[count($stack) - 1][0], self::AUTO_CLOSE[$name], true)) {
                        [, $s, $mn, $ar] = array_pop($stack);
                        $skip -= $s ? 1 : 0;
                        $inMain -= $mn ? 1 : 0;
                        $inArticle -= $ar ? 1 : 0;
                    }
                    $isMain = 'main' === $name || preg_match('~\srole\s*=\s*["\']?main\b~i', $tok);
                    $isArticle = in_array($name, ['main', 'article', 'section', 'aside'], true);
                    $s = $this->skips($name, $tok, $maxH, $inArticle > 0);
                    $stack[] = [$name, $s, (bool) $isMain, $isArticle];
                    $skip += $s ? 1 : 0;
                    $inMain += $isMain ? 1 : 0;
                    $inArticle += $isArticle ? 1 : 0;
                    if ('section' === $this->o['mode'] && in_array($name, ['section', 'article'], true)) {
                        $this->seen = [];
                    }
                    continue;
                }
                $out .= (0 === $skip && (!$mainOnly || $inMain > 0)) ? $this->text($tok) : $tok;
            }
        }
        return $out;
    }

    /** Is an element skipped? */
    private function skips(string $name, string $tag, int $maxH, bool $inContent): bool
    {
        if (in_array($name, self::SKIP_TAGS, true)) {
            return true;
        }
        if ($maxH > 0 && preg_match('~^h([1-6])$~', $name, $h) && (int) $h[1] <= $maxH) {
            return true;
        }
        // Page header/footer – inside main/article/section header/footer are part of the content (e.g. intro of a block)
        if (('header' === $name || 'footer' === $name) && !$inContent) {
            return true;
        }
        if (!str_contains($tag, '=') && !preg_match('~\s(hidden|inert|contenteditable)\b~i', $tag)) {
            return false;
        }
        if (preg_match('~\sdata-glossary\s*=\s*["\']?off\b~i', $tag)) {
            return true;
        }
        if (preg_match('~\s(hidden|inert|contenteditable)(?=[\s=/>])~i', $tag)) {
            return true;
        }
        if (preg_match('~\saria-hidden\s*=\s*["\']?true\b~i', $tag)) {
            return true;
        }
        if (preg_match('~\srole\s*=\s*["\']?([a-z]+)~i', $tag, $r)) {
            $role = strtolower($r[1]);
            if (in_array($role, self::SKIP_ROLES, true)) {
                return true;
            }
            if ('heading' === $role && $maxH > 0 && (!preg_match('~aria-level\s*=\s*["\']?(\d)~', $tag, $lv) || (int) $lv[1] <= $maxH)) {
                return true;
            }
        }
        if (preg_match('~\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $c)) {
            $classes = preg_split('~\s+~', trim($c[1] . ($c[2] ?? '') . ($c[3] ?? ''))) ?: [];
            if (array_intersect($classes, self::SKIP_CLASSES)) {
                return true;
            }
            // Labels instead of running text: eyebrows, tags, badges (also with prefix, e.g. cli-eyebrow, card__tag)
            foreach ($classes as $c) {
                if (preg_match(self::LABEL_CLASS, $c)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Text node: replaces the first occurrence of terms not yet marked */
    private function text(string $t): string
    {
        if (!preg_match('~\p{L}{2}~u', $t)) {
            return $t;
        }
        if ('section' !== $this->o['mode'] && count($this->seen) >= count($this->byId)) {
            return $t;
        }
        return (string) preg_replace_callback($this->rx, function (array $m): string {
            $id = (int) ($m['MARK'] ?? 0);
            if (0 === $id || isset($this->seen[$id]) || !isset($this->byId[$id])) {
                return $m[0];
            }
            $this->seen[$id] = true;
            $this->marked[$id] = true;
            $this->hits[$id] ??= html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            ++$this->count;
            return $this->markup($this->byId[$id], $m[0]);
        }, $t);
    }

    /** Markup of a match (the matched text stays as in the original – entities included) */
    public function markup(array $t, string $match): string
    {
        $id = $this->o['prefix'] . (++$this->n);
        $l = $this->o['labels'];
        $draft = !empty($t['draft']) ? ' <span class="gl-pop__draft">' . escape($l['draft']) . '</span>' : '';
        // „Mehr im Glossar“ only with a detailed explanation; otherwise – if present – the source directly (new tab)
        $src = (string) ($t['link'] ?? '');
        $more = !empty($t['url']) && ($t['more'] ?? true)
            ? '<a class="gl-pop__more" href="' . escape((string) $t['url']) . '">' . escape($l['more']) . ' <span aria-hidden="true">→</span></a>'
            : ('' !== $src && self::safeLink($src)
                ? '<a class="gl-pop__more gl-pop__src" href="' . escape($src) . '" target="_blank" rel="noopener">' . escape(self::linkHost($src)) . ' <span aria-hidden="true">↗</span><span class="gl-sr"> ' . escape($l['newtab'] ?? '') . '</span></a>'
                : '');
        return '<span class="gl" data-gl="' . escape((string) $t['key']) . '">'
            . '<button type="button" class="gl-term" popovertarget="' . $id . '" aria-expanded="false" aria-controls="' . $id . '">' . $match . '</button>'
            . '<span class="gl-pop" id="' . $id . '" popover>'
            . '<span class="gl-pop__head"><span class="gl-pop__t">' . escape((string) $t['term']) . '</span>' . $draft
            . '<button type="button" class="gl-pop__x" popovertarget="' . $id . '" popovertargetaction="hide" aria-label="' . escape($l['close']) . '"><span aria-hidden="true">×</span></button></span>'
            . '<span class="gl-pop__d">' . escape((string) $t['short']) . '</span>' . $more
            . '</span></span>';
    }

    /** Source of a term as a link in the popup: http(s) only */
    public static function safeLink(string $u): bool
    {
        return (bool) preg_match('~^https?://[^\s"<>]+$~i', trim($u));
    }

    /** Display name of a source: host without „www.“ */
    public static function linkHost(string $u): string
    {
        return (string) preg_replace('~^www\.~i', '', (string) parse_url(trim($u), PHP_URL_HOST));
    }
}
