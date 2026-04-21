<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.radiocharts
 *
 * @copyright   Copyright (C) 2025
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemRadiocharts extends CMSPlugin
{
    public function onAfterInitialise()
    {
        // This will log to the Joomla debug log on every page load if debug is enabled.
        // Replace this with your custom logic.
        // Uncomment for testing:
        // \Joomla\CMS\Factory::getApplication()->enqueueMessage('plg_system_radiocharts loaded');
    }
}