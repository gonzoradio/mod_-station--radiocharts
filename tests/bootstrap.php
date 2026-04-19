<?php

/**
 * PHPUnit bootstrap for mod_ciwv_radiocharts unit tests.
 *
 * Defines the Joomla security constant and loads the Composer autoloader.
 * The stubs below satisfy class/interface type-hints that would normally be
 * provided by the Joomla framework at runtime.
 */

// Joomla security guard – required by every class file in this package.
define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// Minimal Joomla framework stubs so the plugin/module classes can be loaded
// without a full Joomla installation.
// ---------------------------------------------------------------------------

if (!interface_exists(\Joomla\Database\DatabaseInterface::class)) {
    eval('namespace Joomla\Database; interface DatabaseInterface {}');
}

if (!interface_exists(\Joomla\Database\DatabaseAwareInterface::class)) {
    eval('namespace Joomla\Database; interface DatabaseAwareInterface { public function setDatabase(\Joomla\Database\DatabaseInterface $db): void; }');
}

if (!trait_exists(\Joomla\Database\DatabaseAwareTrait::class)) {
    eval('namespace Joomla\Database;
    trait DatabaseAwareTrait {
        private \Joomla\Database\DatabaseInterface $db;
        public function setDatabase(\Joomla\Database\DatabaseInterface $db): void { $this->db = $db; }
        public function getDatabase(): \Joomla\Database\DatabaseInterface { return $this->db; }
    }');
}

if (!interface_exists(\Joomla\Event\SubscriberInterface::class)) {
    eval('namespace Joomla\Event; interface SubscriberInterface { public static function getSubscribedEvents(): array; }');
}

if (!interface_exists(\Joomla\Event\DispatcherInterface::class)) {
    eval('namespace Joomla\Event; interface DispatcherInterface {}');
}

if (!class_exists(\Joomla\CMS\Plugin\CMSPlugin::class)) {
    eval('namespace Joomla\CMS\Plugin;
    class CMSPlugin {
        protected $params;
        public function __construct(\Joomla\Event\DispatcherInterface $d, array $c) {}
        public function getApplication(): ?\Joomla\CMS\Application\CMSApplicationInterface { return null; }
    }');
}

if (!trait_exists(\Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait::class)) {
    eval('namespace Joomla\Component\Scheduler\Administrator\Traits;
    trait TaskPluginTrait {
        protected function logTask(string $msg, string $level = "info"): void {}
        protected function advertiseRoutines(\Joomla\Event\Event $event): void {}
        protected function standardRoutineHandler(\Joomla\Event\Event $event): void {}
        protected function enhanceTaskItemForm(\Joomla\Event\Event $event): void {}
    }');
}

if (!interface_exists(\Joomla\Event\Event::class) && !class_exists(\Joomla\Event\Event::class)) {
    eval('namespace Joomla\Event; class Event { public function getArgument(string $name, $default = null) { return $default; } }');
}

if (!class_exists(\Joomla\Component\Scheduler\Administrator\Task\Status::class)) {
    eval('namespace Joomla\Component\Scheduler\Administrator\Task;
    class Status {
        const OK = 0;
        const KNOCKOUT = 1;
        const INVALID_EXIT = 2;
    }');
}

if (!class_exists(\Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent::class)) {
    eval('namespace Joomla\Component\Scheduler\Administrator\Event;
    class ExecuteTaskEvent extends \Joomla\Event\Event {}');
}
