<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_contentdesigner\local;

/**
 * Lock factory for tests: every lock is held by someone else, so no lock can be obtained.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class busy_lock_factory implements \core\lock\lock_factory {
    /**
     * Creates the factory.
     *
     * @param string $type The type of the locks.
     */
    public function __construct($type) {
    }

    /**
     * Whether the factory supports timeouts.
     *
     * @return bool
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Whether locks are released automatically.
     *
     * @return bool
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Whether the factory can be used.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * No lock can be obtained.
     *
     * @param string $resource The resource.
     * @param int $timeout The timeout.
     * @param int $maxlifetime The maximum lifetime.
     * @return bool
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        return false;
    }

    /**
     * Releases a lock.
     *
     * @param \core\lock\lock $lock The lock.
     * @return bool
     */
    public function release_lock(\core\lock\lock $lock) {
        return true;
    }
}
