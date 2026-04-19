<?php

/**
 * PHPUnit bootstrap for mod_ciwv_radiocharts unit tests.
 *
 * Defines the Joomla security constant, loads the Composer autoloader, and
 * includes the stub files that stand in for the Joomla framework interfaces
 * and classes used by the module and plugins.
 */

// Joomla security guard – required by every class file in this package.
define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

// Load framework stubs so plugin/module classes can be instantiated without
// a live Joomla installation.
require __DIR__ . '/Stubs/Joomla/Database.php';
require __DIR__ . '/Stubs/Joomla/Event.php';
require __DIR__ . '/Stubs/Joomla/CMS.php';
require __DIR__ . '/Stubs/Joomla/Scheduler.php';
