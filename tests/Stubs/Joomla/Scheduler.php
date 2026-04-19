<?php

/**
 * Stubs for Joomla Scheduler component classes used during unit tests.
 */

namespace Joomla\Component\Scheduler\Administrator\Task;

if (!class_exists(Status::class)) {
    class Status
    {
        public const OK           = 0;
        public const KNOCKOUT     = 1;
        public const INVALID_EXIT = 2;
    }
}

namespace Joomla\Component\Scheduler\Administrator\Event;

use Joomla\Event\Event;

if (!class_exists(ExecuteTaskEvent::class)) {
    class ExecuteTaskEvent extends Event
    {
    }
}

namespace Joomla\Component\Scheduler\Administrator\Traits;

if (!trait_exists(TaskPluginTrait::class)) {
    trait TaskPluginTrait
    {
        protected function logTask(string $message, string $level = 'info'): void
        {
        }

        protected function advertiseRoutines(\Joomla\Event\Event $event): void
        {
        }

        protected function standardRoutineHandler(\Joomla\Event\Event $event): void
        {
        }

        protected function enhanceTaskItemForm(\Joomla\Event\Event $event): void
        {
        }
    }
}
