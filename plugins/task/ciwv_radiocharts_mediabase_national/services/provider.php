<?php

/**
 * @package     plg_task_ciwv_radiocharts_mediabase_national
 * @subpackage  services
 *
 * @copyright   (C) 2026 Gonzo Radio. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Gonzoradio\Plugin\Task\CiwvRadiochartsMediabaseNational\Extension\CiwvRadiochartsMediabaseNational;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new CiwvRadiochartsMediabaseNational(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'ciwv_radiocharts_mediabase_national'),
                    $container->get(DatabaseInterface::class)
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
