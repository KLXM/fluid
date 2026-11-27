<?php

namespace Klxm\Fluid;

use RuntimeException;

/**
 * Error of an editor endpoint: message for the editors, code = HTTP status.
 *
 * @internal
 */
final class ApiError extends RuntimeException {}
