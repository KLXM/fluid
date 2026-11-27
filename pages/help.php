<?php

use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Markdown\Markdown;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;

// README (introduction) and developer reference; omit images (relative paths for the repository) in the backend
foreach ([I18n::rawMsg('fluid_backend_page_help') => 'README.md', I18n::rawMsg('fluid_backend_help_developer') => 'docs/entwickler.md'] as $title => $file) {
    $md = (string) @file_get_contents(dirname(__DIR__) . '/' . $file);
    $md = (string) preg_replace('~^\s*!\[[^\]]*\]\([^)]*\)\s*$~m', '', $md);
    $md = (string) preg_replace('~\|\s*!\[[^\]]*\]\([^)]*\)\s*\|\s*!\[[^\]]*\]\([^)]*\)\s*\|~', '', $md);
    $fragment = new Fragment();
    $fragment->setVar('title', escape($title), false);
    $fragment->setVar('body', '<div class="rex-docs">' . Markdown::factory()->parse($md) . '</div>', false);
    echo $fragment->parse('core/page/section.php');
}
