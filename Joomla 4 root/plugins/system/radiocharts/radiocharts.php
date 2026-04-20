<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.radiocharts
 *
 * @copyright   Copyright (C) 2025 Durham Radio Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;

/**
 * System plugin for mod_ciwv_radiocharts / mod_radiochartsdashboard.
 *
 * Responsibilities:
 *  1. On every front-end page load, inject a Joomla CSRF form token into the
 *     page as window.RC_FORM_TOKEN so AJAX endpoints can validate it.
 *  2. Remove PHP display_errors that could corrupt JSON AJAX responses (safety
 *     net for any rogue ini_set in included files).
 */
class PlgSystemRadiocharts extends CMSPlugin
{
    /**
     * Fires after the Joomla framework has initialised but before routing.
     * We use this to suppress any stray error output that could break AJAX JSON.
     */
    public function onAfterInitialise(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $app = Factory::getApplication();

        // Only suppress display_errors in production (debug off).
        // Developers running with debug mode enabled will still see PHP notices.
        if (!$app->get('debug', false)) {
            ini_set('display_errors', '0');
        }
    }

    /**
     * Fires just before the page is rendered.
     * Injects the Joomla CSRF token into the JS global scope so the dashboard's
     * AJAX calls can include it for server-side validation if desired.
     */
    public function onBeforeRender(): void
    {
        $app = Factory::getApplication();

        // Only inject on site (front-end) pages.
        if (!$app->isClient('site')) {
            return;
        }

        $doc = $app->getDocument();

        // Only inject into HTML documents (not JSON/raw format responses).
        if ($doc->getType() !== 'html') {
            return;
        }

        $session = Factory::getSession();
        $token   = $session->getFormToken();

        // Expose token for the dashboard AJAX calls.
        $doc->addScriptDeclaration('window.RC_FORM_TOKEN = ' . json_encode($token) . ';');
    }
}