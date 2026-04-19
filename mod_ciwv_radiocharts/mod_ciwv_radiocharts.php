<?php

/**
 * @package     mod_ciwv_radiocharts
 * @subpackage  mod_ciwv_radiocharts
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

// The module entry point simply loads the dispatcher, which handles all logic.
// For Joomla 4/5 modules the actual bootstrapping is done via the service provider.
$app->bootModule('mod_ciwv_radiocharts', 'site')
    ->getDispatcher($module, $app)
    ->dispatch();
