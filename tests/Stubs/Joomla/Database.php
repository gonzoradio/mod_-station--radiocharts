<?php

/**
 * Stubs for Joomla framework interfaces and classes used by the module/plugins.
 *
 * These are only loaded during unit tests (via tests/bootstrap.php) when
 * the full Joomla framework is not present.  Each stub satisfies type-hints
 * without providing real implementations.
 */

namespace Joomla\Database;

if (!interface_exists(DatabaseInterface::class)) {
    interface DatabaseInterface
    {
    }
}

if (!interface_exists(DatabaseAwareInterface::class)) {
    interface DatabaseAwareInterface
    {
        public function setDatabase(DatabaseInterface $db): void;
    }
}

if (!trait_exists(DatabaseAwareTrait::class)) {
    trait DatabaseAwareTrait
    {
        private DatabaseInterface $db;

        public function setDatabase(DatabaseInterface $db): void
        {
            $this->db = $db;
        }

        public function getDatabase(): DatabaseInterface
        {
            return $this->db;
        }
    }
}
