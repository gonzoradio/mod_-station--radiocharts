<?php

/**
 * Stubs for Joomla Event interfaces used during unit tests.
 */

namespace Joomla\Event;

if (!interface_exists(SubscriberInterface::class)) {
    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }
}

if (!interface_exists(DispatcherInterface::class)) {
    interface DispatcherInterface
    {
    }
}

if (!class_exists(Event::class)) {
    class Event
    {
        public function getArgument(string $name, mixed $default = null): mixed
        {
            return $default;
        }
    }
}
