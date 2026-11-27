<?php

namespace Klxm\Fluid\Media;

use function function_exists;
use function is_resource;
use function is_string;
use function sprintf;

use const PATH_SEPARATOR;

/**
 * Call ffmpeg/ffprobe (video thumbnails). Without ffmpeg everything is a no-op – videos then show the
 * chosen poster image or none.
 *
 * Path: environment variable FFMPEG_BIN or FFPROBE_BIN (e.g. in .env), otherwise search in PATH and common folders.
 *
 * @internal
 */
final class Ffmpeg
{
    private const array DIRS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin'];

    /** @var array<string, string> */
    private static array $bins = [];

    public static function bin(string $name = 'ffmpeg'): string
    {
        if (isset(self::$bins[$name])) {
            return self::$bins[$name];
        }
        $env = getenv(strtoupper($name) . '_BIN') ?: ($_SERVER[strtoupper($name) . '_BIN'] ?? '');
        if (is_string($env) && '' !== $env) {
            return self::$bins[$name] = is_executable($env) ? $env : '';
        }
        if (!function_exists('proc_open')) {
            return self::$bins[$name] = '';
        }
        foreach ([...explode(PATH_SEPARATOR, (string) getenv('PATH')), ...self::DIRS] as $dir) {
            $f = rtrim($dir, '/') . '/' . $name;
            if ('' !== $dir && @is_file($f) && @is_executable($f)) {
                return self::$bins[$name] = $f;
            }
        }

        return self::$bins[$name] = '';
    }

    public static function available(): bool
    {
        return '' !== self::bin('ffmpeg') && '' !== self::bin('ffprobe');
    }

    /**
     * Run a command without a shell (arguments as a list), with a time limit.
     *
     * @param list<string> $argv
     * @return array{code: int, out: string}
     */
    public static function run(array $argv, int $timeout = 15): array
    {
        $p = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            return ['code' => -1, 'out' => ''];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $end = microtime(true) + $timeout;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $st = proc_get_status($p);
            if (!$st['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                break;
            }
            if (microtime(true) > $end) {
                proc_terminate($p, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($p);

                return ['code' => -1, 'out' => ''];
            }
            usleep(20_000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);

        return ['code' => (int) $st['exitcode'], 'out' => $out];
    }

    /** Laufzeit in Sekunden (0 = unbekannt) */
    public static function duration(string $file): float
    {
        $r = self::run([self::bin('ffprobe'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $file], 10);

        return 0 === $r['code'] ? max(0.0, (float) trim($r['out'])) : 0.0;
    }

    /**
     * Typical still frame as JPEG: at ~10 % of the duration (at least 1 s, at most 10 s); the filter "thumbnail" picks
     * the most typical of the following frames (skips fades to black). Returns the file path or null.
     */
    public static function frame(string $video, string $target, int $maxWidth = 1600): ?string
    {
        if (!self::available() || !is_file($video)) {
            return null;
        }
        $d = self::duration($video);
        $at = $d > 0 ? min(10.0, max(1.0, $d * 0.1), max(0.0, $d - 0.5)) : 1.0;
        $r = self::run([self::bin('ffmpeg'), '-nostdin', '-v', 'error', '-y', '-ss', sprintf('%.2F', $at), '-i', $video,
            '-vf', 'thumbnail=24,scale=\'min(' . $maxWidth . ',iw)\':-2', '-frames:v', '1', '-q:v', '3', $target], 20);
        if (0 !== $r['code'] || !is_file($target) || 0 === filesize($target)) {
            // sehr kurze Videos: erstes Bild
            $r = self::run([self::bin('ffmpeg'), '-nostdin', '-v', 'error', '-y', '-i', $video, '-frames:v', '1', '-q:v', '3', $target], 20);
        }

        return 0 === $r['code'] && is_file($target) && filesize($target) > 0 ? $target : null;
    }
}
