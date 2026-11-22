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

namespace mod_contentdesigner\hook;

/**
 * Lets plugins add support for a Moodle feature to the activity, for example grades.
 *
 * Dispatched by contentdesigner_supports() for the features that Content Designer does not support by itself
 * (FEATURE_GRADE_HAS_GRADE, FEATURE_GRADE_OUTCOMES, FEATURE_COMPLETION_HAS_RULES). The answer is cached for
 * the request, so it must not depend on an activity or a user.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add support for a Moodle feature (e.g. grades) to Content Designer.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class supports_feature {
    /** @var bool Whether a plugin supports the feature. */
    private bool $supported = false;

    /**
     * Creates the hook.
     *
     * @param string $feature The FEATURE_xx constant.
     */
    public function __construct(
        /** @var string The FEATURE_xx constant */
        public readonly string $feature,
    ) {
    }

    /**
     * Declares that the activity supports the feature.
     */
    public function set_supported(): void {
        $this->supported = true;
    }

    /**
     * Whether a plugin declared support for the feature.
     *
     * @return bool
     */
    public function is_supported(): bool {
        return $this->supported;
    }
}
