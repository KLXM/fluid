<?php

namespace Klxm\Fluid\Mcp;

use DomainException;
use Klxm\Fluid\ApiError;
use Klxm\Fluid\Editing;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Routing\Attribute\AsEndpoint;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Log\Logger;
use stdClass;
use Throwable;

use function array_key_exists;
use function function_exists;
use function in_array;
use function is_array;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
use const PHP_URL_HOST;

/**
 * MCP server (Model Context Protocol) via "Streamable HTTP" – stateless, JSON responses (like KLXM Studio).
 *
 * Endpoint: POST {website}/fluid-mcp (without pretty URLs: /index.php?fluid_mcp=1)
 * Authentication: `Authorization: Bearer fluid_…` (Backend → AddOns → fluid → MCP). Without a key only if the admin has
 * enabled public access – then exclusively published, released content and read-only.
 */
final class Server
{
    public const string PATH = 'fluid-mcp';
    public const string PARAM = 'fluid_mcp';

    private const array VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    /** Requests per 5 minutes: per key, or per IP when public */
    private const int LIMIT_TOKEN = 300;
    private const int LIMIT_PUBLIC = 60;
    private const int WINDOW = 300;

    private Access $access;

    private Tools $tools;

    /** @internal Endpunkt /fluid-mcp bzw. ?fluid_mcp=1 */
    #[AsEndpoint('/' . self::PATH, early: true, param: self::PARAM)]
    public static function serve(): void
    {
        self::handle();
    }

    public static function url(): string
    {
        return Url::absoluteBase(self::PATH);
    }

    public static function handle(): never
    {
        new self()->run();
        exit;
    }

    private function run(): void
    {
        $cfg = Access::config();
        if (!$cfg['enabled']) {
            self::send(['error' => 'not found'], 404);
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ('OPTIONS' === $method || 'GET' === $method || 'DELETE' === $method) {
            // no SSE stream, no sessions: stateless server
            header('Allow: POST');
            self::send(['error' => 'Nur POST (MCP Streamable HTTP, JSON-Antworten).'], 405);
        }
        // Protection against DNS rebinding: reject foreign browser origins
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ('' !== $origin && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
            self::rpcError(403, -32000, 'Origin nicht erlaubt.');
        }

        $bearer = self::bearer();
        if (null !== $bearer) {
            $token = Tokens::check($bearer);
            if (null === $token) {
                header('WWW-Authenticate: Bearer realm="fluid-mcp", error="invalid_token"');
                self::rpcError(401, -32001, 'Schlüssel ungültig, abgelaufen oder Benutzer gesperrt.');
            }
            $this->access = Access::forToken($token);
            self::limit('t' . $token['id'], self::LIMIT_TOKEN);
            // log, versions and permissions run on behalf of the user
            Core::setUser($token['user']);
            Editing::actAs($token['user']);
        } elseif ($cfg['public']) {
            $this->access = Access::anonymous();
            self::limit('ip' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), self::LIMIT_PUBLIC);
        } else {
            header('WWW-Authenticate: Bearer realm="fluid-mcp"');
            self::rpcError(401, -32001, 'Schlüssel nötig: Authorization: Bearer fluid_…');
        }
        $this->tools = new Tools($this->access);

        $raw = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($raw)) {
            self::send(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        }
        $batch = array_is_list($raw);
        $out = [];
        foreach ($batch ? $raw : [$raw] as $msg) {
            $res = $this->dispatch(is_array($msg) ? $msg : [], $this->access->tokenId ?? 0);
            if (null !== $res) {
                $out[] = $res;
            }
        }
        if ([] === $out) {
            http_response_code(202);
            exit;
        }
        self::send($batch ? $out : $out[0]);
    }

    /** @return array<string, mixed>|null */
    private function dispatch(array $m, int $tokenId): ?array
    {
        $id = $m['id'] ?? null;
        $method = (string) ($m['method'] ?? '');
        $params = is_array($m['params'] ?? null) ? $m['params'] : [];
        $notification = !array_key_exists('id', $m);
        if ('2.0' !== ($m['jsonrpc'] ?? '') || '' === $method) {
            return $notification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32600, 'message' => 'Invalid Request']];
        }
        if (str_starts_with($method, 'notifications/')) {
            return null;
        }
        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params, $tokenId),
                'ping', 'logging/setLevel' => new stdClass(),
                'tools/list' => ['tools' => array_values(array_map(static fn (array $t) => array_diff_key($t, ['run' => 1]), $this->tools->all()))],
                'tools/call' => $this->call($params),
                'resources/list' => ['resources' => []],
                'resources/templates/list' => ['resourceTemplates' => []],
                'prompts/list' => ['prompts' => []],
                default => throw new DomainException('Methode „' . $method . '“ nicht gefunden', -32601),
            };

            return $notification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (DomainException $e) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $e->getCode() ?: -32602, 'message' => $e->getMessage()]];
        } catch (Throwable $e) {
            Logger::logException($e);

            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32603, 'message' => 'Interner Fehler']];
        }
    }

    /** @return array<string, mixed> */
    private function initialize(array $p, int $tokenId): array
    {
        $client = trim(mb_substr(strip_tags((string) ($p['clientInfo']['name'] ?? '') . ' ' . (string) ($p['clientInfo']['version'] ?? '')), 0, 120));
        if ($tokenId > 0 && '' !== $client) {
            Tokens::rememberClient($tokenId, $client);
        }
        $req = (string) ($p['protocolVersion'] ?? '');
        $extra = trim((string) Access::config()['instructions']);
        $public = $this->access->isPublic();

        return [
            'protocolVersion' => in_array($req, self::VERSIONS, true) ? $req : self::VERSIONS[1],
            'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false, 'subscribe' => false], 'prompts' => ['listChanged' => false], 'logging' => new stdClass()],
            'serverInfo' => ['name' => 'fluid', 'title' => 'fluid für REDAXO', 'version' => FluidAddon::instance()->getVersion(), 'websiteUrl' => Url::absoluteBase()],
            'instructions' => 'Website mit REDAXO und fluid (' . Url::absoluteBase() . ").\n"
                . "- Starte mit site_overview.\n"
                . ($public
                    ? "- Öffentlicher Zugang: nur veröffentlichte Inhalte, nur lesen.\n"
                    : "- Seiten bestehen aus Blöcken (list_block_types). Änderungen landen im ENTWURF; live geht erst publish_page (falls erlaubt) oder ein Mensch auf der Website. Vorher nachfragen.\n"
                      . "- Rich-Text als HTML ohne Bilder; Bilder nur über Medienfelder (Dateiname aus dem Medienpool).\n"
                      . "- Erfinde keine Fakten.\n")
                . ('' !== $extra ? '- ' . $extra . "\n" : '')
                . 'Zugang: ' . $this->access->describe() . '.',
        ];
    }

    /** @return array<string, mixed> */
    private function call(array $p): array
    {
        $name = (string) ($p['name'] ?? '');
        $tools = $this->tools->all();
        if (!isset($tools[$name])) {
            $why = match (true) {
                $this->access->isPublic() => ' (öffentlich nicht freigegeben)',
                !$this->access->write => ' (dieser Zugang darf nur lesen)',
                default => '',
            };
            throw new DomainException('Unbekanntes Werkzeug „' . $name . '“' . $why, -32602);
        }
        try {
            $result = ($tools[$name]['run'])(is_array($p['arguments'] ?? null) ? $p['arguments'] : []);
        } catch (ApiError|UserMessageException $e) {
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }

        return ['content' => [['type' => 'text', 'text' => (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            'structuredContent' => is_array($result) && !array_is_list($result) ? $result : ['items' => $result]];
    }

    private static function bearer(): ?string
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ('' === $h && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (0 === strcasecmp((string) $k, 'Authorization')) {
                    $h = (string) $v;
                }
            }
        }
        if (1 === preg_match('~^Bearer\s+(\S+)$~i', trim($h), $m)) {
            return $m[1];
        }
        $key = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));

        return '' !== $key ? $key : null;
    }

    /** Simple rate limit per key or IP (file in the addon cache) */
    private static function limit(string $key, int $max): void
    {
        $dir = FluidAddon::instance()->getCachePath('mcp');
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        $file = $dir . '/' . $key . '.json';
        $now = time();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || ($data['start'] ?? 0) < $now - self::WINDOW) {
            $data = ['start' => $now, 'n' => 0];
        }
        if (++$data['n'] > $max) {
            header('Retry-After: ' . max(1, $data['start'] + self::WINDOW - $now));
            self::rpcError(429, -32002, 'Zu viele Anfragen – bitte kurz warten.');
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private static function rpcError(int $status, int $code, string $message): never
    {
        self::send(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $message]], $status);
    }

    private static function send(mixed $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
