<?php

namespace Klxm\Fluid;

use Klxm\Fluid\Routing\Attribute\AsEndpoint;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;

use function count;
use function dirname;
use function function_exists;
use function Redaxo\Core\View\escape;

use const ARRAY_FILTER_USE_BOTH;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;
use const PHP_SAPI;

/**
 * Live updates (server-sent events) and „Neu laden auf Aktion“ – model from KLXM Studio (Core\Live, MIT), same
 * attributes, channels and scripts (assets/live.js, assets/live.css).
 *
 * Channels are names whose version increases on changes – the stream only transmits versions, never content:
 *   page:{id}               page (automatically on publishing)
 *   presence:{site}         presence of the editors (Presence: coming and going)
 *   ext:{addon}:…           custom channels: Live::touch('ext:shop:lager')
 * Subscriptions are signed (HMAC) – visitors cannot query arbitrary channels.
 *
 * Reload on action (filtering, paging, search without page change), in a block's view with 'reload' => true in the kit:
 *   <div class="wrap …"<?= Live::region($b) ?>> … <a href="?seite=2#…" data-live-nav> … <form method="get" data-live-nav> <input data-live-input>
 *   … </div><?= Live::assets() ?>
 * live.js fetches the block via the page URL with ?fluid_block={slice} and the parameters of the link or form
 * (JSON {html}) and replaces [data-live-region] – history, back, focus and announcement ([data-live-count]) included.
 * Without JavaScript they stay normal links and forms. The block checks the parameters itself; fluid_block is reserved.
 *
 * Operation: an open stream occupies a PHP worker – hence short (20 s) and at most MAX_STREAMS at once; beyond that
 * and on the development server (php -S) /fluid-live answers immediately with the versions and a longer retry (= polling).
 */
final class Live
{
    public const string PATH = 'fluid-live';
    public const string BLOCK = 'fluid_block';
    private const int MAX_STREAMS = 16;
    private const int SECONDS = 20;

    private static bool $assets = false;

    /** @internal Endpoint of the live updates (SSE) – before the domain redirect */
    #[AsEndpoint('/' . self::PATH, early: true)]
    public static function serve(): void
    {
        self::stream();
    }

    // ------------------------------------------------------------------ Versionen

    private static function file(string $channel): string
    {
        return Path::addonCache('fluid', 'live/' . substr(sha1($channel), 0, 24) . '.v');
    }

    /** Marks channels as changed (version = timestamp in ms, always increasing) */
    public static function touch(string ...$channels): void
    {
        foreach (array_unique($channels) as $c) {
            if ('' === $c) {
                continue;
            }
            $f = self::file($c);
            if (!is_dir(dirname($f))) {
                @mkdir(dirname($f), 0o775, true);
            }
            $v = max((int) round(microtime(true) * 1000), self::version($c) + 1);
            @file_put_contents($f, (string) $v, LOCK_EX);
        }
    }

    public static function version(string $channel): int
    {
        $f = self::file($channel);
        clearstatcache(true, $f);

        return is_file($f) ? (int) @file_get_contents($f) : 0;
    }

    /** @param list<string> $channels */
    public static function versionOf(array $channels): int
    {
        $v = 0;
        foreach ($channels as $c) {
            $v = max($v, self::version((string) $c));
        }

        return $v;
    }

    // ------------------------------------------------------------------ Abos (signiert)

    private static function key(): string
    {
        $addon = FluidAddon::instance();
        $k = (string) $addon->getConfig('live_key', '');
        if ('' === $k) {
            $k = bin2hex(random_bytes(32));
            $addon->setConfig('live_key', $k);
        }

        return $k;
    }

    private static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', 'live|' . $payload, self::key()), 0, 20);
    }

    /** @param list<string> $channels signiertes Abo (URL-sicher) */
    public static function token(array $channels): string
    {
        $p = rtrim(strtr(base64_encode(implode("\n", array_values(array_unique(array_map('strval', $channels))))), '+/', '-_'), '=');

        return $p . '.' . self::sign($p);
    }

    /** @return list<string>|null Channels of a subscription or null (invalid signature) */
    public static function channels(string $token): ?array
    {
        if (!preg_match('~^([A-Za-z0-9_-]{1,800})\.([a-f0-9]{20})$~', $token, $m) || !hash_equals(self::sign($m[1]), $m[2])) {
            return null;
        }
        $raw = base64_decode(strtr($m[1], '-_', '+/'), true);

        return false === $raw ? null : array_values(array_filter(explode("\n", $raw), static fn ($c) => '' !== $c));
    }

    /** Adresse des Streams */
    public static function endpoint(): string
    {
        return Routing\Router::base() . '/' . self::PATH;
    }

    /** @param list<string> $channels Attributes for the element (subscription, version, optional reload URL) – empty while editing */
    public static function attrs(array $channels, string $src = ''): string
    {
        if (Editing::active() || !$channels) {
            return '';
        }

        return ' data-live="' . escape(self::token($channels)) . '" data-live-v="' . self::versionOf($channels) . '"'
            . ('' !== $src ? ' data-live-src="' . escape($src) . '"' : '');
    }

    /** Script and styles (once per page) – output in the view after the element */
    public static function assets(): string
    {
        if (self::$assets || Editing::active()) {
            return '';
        }
        self::$assets = true;
        $a = FluidAddon::instance();
        $v = static fn (string $f) => is_file($p = $a->getPath('assets/' . $f)) ? '?v=' . substr(md5((string) filemtime($p)), 0, 8) : '';
        $lt = static fn (string $t) => function_exists('lt') ? lt($t) : $t;   // the kit's translation (functions.php)

        return '<link rel="stylesheet" href="' . escape($a->getAssetsUrl('live.css') . $v('live.css')) . '">'
            . '<script src="' . escape($a->getAssetsUrl('live.js') . $v('live.js')) . '" defer data-live-endpoint="' . escape(self::endpoint()) . '"'
            . ' data-l-new="' . escape($lt('{n} neue Einträge')) . '" data-l-new1="' . escape($lt('Ein neuer Eintrag')) . '" data-l-updated="' . escape($lt('Inhalt aktualisiert')) . '"></script>';
    }

    /**
     * Reload on action: attributes for the outer element of a block with 'reload' => true. Links/forms inside with
     * data-live-nav, inputs with data-live-input (pause data-live-delay, ms). Empty while editing and for blocks from data.
     */
    public static function region(Block $b): string
    {
        if (Editing::active() || '' === ($src = self::src($b))) {
            return '';
        }

        return ' data-live-region="' . escape($b->domId()) . '" data-live-src="' . escape($src) . '"';
    }

    /** URL at which the block of the published page is re-rendered */
    public static function src(Block $b): string
    {
        if (!$b->slice) {
            return '';
        }

        return Url::article($b->slice->articleId, $b->slice->languageId, [self::BLOCK => $b->slice->id]);
    }

    // ------------------------------------------------------------------ Endpunkte

    /** Stream possible? The development server (php -S) usually has only one worker – then polling instead of an open connection */
    private static function streaming(): bool
    {
        return 'cli-server' !== PHP_SAPI || (int) getenv('PHP_CLI_SERVER_WORKERS') > 1;
    }

    /** GET /fluid-live?t=…&t=… (EventSource) – Ereignis „v“: {abo: version} */
    public static function stream(): never
    {
        $tokens = [];
        foreach (explode('&', (string) ($_SERVER['QUERY_STRING'] ?? '')) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if ('t' === $k && count($tokens) < 20 && null !== ($ch = self::channels(rawurldecode($v)))) {
                $tokens[rawurldecode($v)] = $ch;
            }
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!$tokens) {
            http_response_code(400);
            header('Content-Type: application/json');
            exit('{"error":"Kein gültiges Abo."}');
        }
        $versions = static fn () => array_map(static fn ($ch) => self::versionOf($ch), $tokens);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Accel-Buffering: no');
        header('X-Robots-Tag: noindex');
        $send = static function (string $event, array $data): bool {
            echo 'event: ' . $event . "\ndata: " . json_encode((object) $data, JSON_UNESCAPED_SLASHES) . "\n\n";
            @flush();

            return !connection_aborted();
        };
        $slot = self::streaming() ? self::slot() : null;
        if (null === $slot) {
            // Polling: versions right away, the browser reconnects after retry
            echo "retry: 15000\n";
            $send('v', $versions());
            $send('bye', ['poll' => true]);
            exit;
        }
        ignore_user_abort(false);
        @set_time_limit(self::SECONDS + 10);
        echo "retry: 2000\n";
        $last = $versions();
        $send('v', $last);
        $end = microtime(true) + self::SECONDS;
        $ping = microtime(true) + 12;
        while (microtime(true) < $end) {
            usleep(1_000_000);
            if (connection_aborted()) {
                break;
            }
            $now = $versions();
            $diff = array_filter($now, static fn ($v, $k) => ($last[$k] ?? 0) !== $v, ARRAY_FILTER_USE_BOTH);
            if ($diff) {
                if (!$send('v', $diff)) {
                    break;
                }
                $last = $now;
                $ping = microtime(true) + 12;
            } elseif (microtime(true) > $ping) {
                echo ": ping\n\n";
                @flush();
                $ping = microtime(true) + 12;
            }
        }
        $send('bye', ['poll' => false]);
        flock($slot, LOCK_UN);
        fclose($slot);
        exit;
    }

    /** @return resource|null free slot for a stream (file lock) */
    private static function slot()
    {
        $dir = Path::addonCache('fluid', 'live/slots');
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        foreach (range(1, self::MAX_STREAMS) as $n) {
            $h = @fopen("$dir/$n.lock", 'c');
            if ($h && flock($h, LOCK_EX | LOCK_NB)) {
                return $h;
            }
            if ($h) {
                fclose($h);
            }
        }

        return null;
    }

    /**
     * {page URL}?fluid_block={slice}[&more] (FE_OUTPUT): re-renders the block of the published page – only blocks with
     * 'reload' or 'live' in the kit. The block reads further parameters from the request as in a normal call.
     */
    public static function block(): never
    {
        $article = Article::getCurrent();
        $id = Request::get(self::BLOCK, 'int', 0);
        $slice = $article && $article->isOnline() ? ArticleSlice::getArticleSliceById($id, $article->languageId, ArticleRevision::LIVE) : null;
        $module = $slice && $slice->articleId === $article->id && 1 === $slice->status ? BlockModule::classFor($slice->moduleKey) : null;
        $def = null !== $module ? $module::definition() : [];
        $parent = null !== $module ? Layout::parentOf($slice) : null;
        // only what visitors see on the page: block online, and the layout above it (column block) too
        if (null === $module || (empty($def['reload']) && empty($def['live'])) || ($parent && 1 !== $parent->status)) {
            self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $html = Editing::without(static function () use ($slice, $parent): string {
            if ($parent && null !== BlockModule::classFor($parent->moduleKey)) {
                return BlockModule::of($slice)->renderChild((string) BlockModule::of($parent)->block()->tunes['background']);
            }

            return BlockModule::of($slice)->renderBlock();
        });
        header('Cache-Control: public, max-age=2');
        header('X-Robots-Tag: noindex');
        self::json(['html' => $html]);
    }

    private static function json(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ------------------------------------------------------------------ Selbsttest (Status-Seite)

    /** @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = static function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) {
                ++$ok;

                return;
            }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $c = 'ext:selftest:' . bin2hex(random_bytes(4));
        $eq('unbekannter Kanal hat Version 0', self::version($c), 0);
        self::touch($c);
        $v1 = self::version($c);
        $eq('touch setzt eine Version', $v1 > 0, true);
        self::touch($c);
        $eq('Version steigt bei jedem touch', self::version($c) > $v1, true);
        $tok = self::token([$c, 'page:1']);
        $eq('Abo liefert die Kanäle zurück', self::channels($tok), [$c, 'page:1']);
        [$p, $sig] = explode('.', $tok);
        $eq('gefälschte Signatur wird abgewiesen', self::channels($p . '.' . str_repeat('0', 20)), null);
        $other = rtrim(strtr(base64_encode('page:geheim'), '+/', '-_'), '=');
        $eq('fremde Kanäle mit alter Signatur abgewiesen', self::channels($other . '.' . $sig), null);
        $eq('Unsinn wird abgewiesen', self::channels('kein-abo'), null);
        @unlink(self::file($c));
        $eq('live.js kennt data-live-region/-nav/-input', (static fn (string $js) => str_contains($js, 'data-live-region') && str_contains($js, 'data-live-nav') && str_contains($js, 'data-live-input'))((string) @file_get_contents(FluidAddon::instance()->getPath('assets/live.js'))), true);

        return ['ok' => $ok, 'fails' => $fails];
    }
}
