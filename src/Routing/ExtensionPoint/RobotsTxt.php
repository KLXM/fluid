<?php

namespace Klxm\Fluid\Routing\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;

/**
 * robots.txt: $rules are the rules (subject on the first call with $part = 'rules' – replace, e.g. custom text),
 * then the finished text including the sitemap line ($part = 'text' – extend).
 *
 * @extends ExtensionPoint<string>
 */
final class RobotsTxt extends ExtensionPoint
{
    /** @param 'rules'|'text' $part */
    public function __construct(
        string $text,
        public readonly string $part = 'text',
    ) {
        parent::__construct(self::class, $text);
    }
}
