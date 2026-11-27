<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\View;

echo View::title('fluid', I18n::msg('fluid_backend_page_title'));
Controller::includeCurrentPageSubPath();
