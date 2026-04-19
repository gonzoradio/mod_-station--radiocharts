<?php

/**
 * Stubs for Joomla CMS classes used during unit tests.
 */

namespace Joomla\CMS\Plugin;

use Joomla\Event\DispatcherInterface;

if (!class_exists(CMSPlugin::class)) {
    class CMSPlugin
    {
        protected mixed $params = null;

        public function __construct(DispatcherInterface $dispatcher, array $config)
        {
        }

        public function getApplication(): mixed
        {
            return null;
        }
    }
}
