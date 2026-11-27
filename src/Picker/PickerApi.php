<?php

namespace Klxm\Fluid\Picker;

use Override;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Throwable;

/**
 * Endpoint of the FrontendPicker: `?rex-api-call=fluid_picker&_csrf_token=…` (POST: action=media|media_upload|articles).
 * Callable on the website (published), but only with a backend session; media pool or structure permissions apply.
 *
 * @internal
 */
#[AsApiFunction('fluid_picker')]
final class PickerApi extends ApiFunction
{
    protected bool $published = true;

    #[Override]
    public function execute(): Result
    {
        try {
            $user = FrontendPicker::user() ?? throw new PickerError('Bitte im Backend anmelden.', 403);
            $readOnly = FrontendPicker::readOnly();
            $types = array_values(array_filter(array_map(static fn (string $t) => strtolower(trim($t)), explode(',', Request::post('types', 'string', '')))));
            $category = Request::post('category', 'int', 0);

            $data = match (Request::post('action', 'string', '')) {
                // guests see the upload button never (readOnly)
                'media' => ($readOnly ? ['upload' => false] : []) + MediaLibrary::list($user, $category, trim(Request::post('q', 'string', '')), $types, Request::post('page', 'int', 0)),
                'media_upload' => $readOnly ? throw new PickerError('Hochladen geht nur mit Anmeldung.', 403) : ['item' => MediaLibrary::upload($user, $category, trim(Request::post('title', 'string', '')), $types, Core::getRequest()->files->get('file'))],
                'articles' => ['articles' => ArticleTree::all($user, Language::exists($l = Request::post('language', 'int', 0)) ? $l : Language::getCurrentId())],
                default => throw new PickerError('Unbekannte Aktion.'),
            };
            self::send(['ok' => true] + $data);
        } catch (PickerError $e) {
            self::send(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
        } catch (UserMessageException $e) {
            // Media pool messages (file type not allowed …)
            self::send(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            self::send(['ok' => false, 'error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /** @param array<string, mixed> $data */
    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        Response::sendJson($data);
        exit;
    }
}
