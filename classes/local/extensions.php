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

use mod_contentdesigner\hook\completion_rules;
use mod_contentdesigner\hook\supports_feature;

/**
 * Dispatches the hooks through which plugins (the addons) extend Content Designer.
 *
 * The answers that do not depend on an activity (supported features, completion rules) are cached for the
 * request.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extensions {
    /** @var bool[] Whether a plugin supports a feature, by feature. */
    private static array $features = [];

    /** @var completion_rule[]|null The completion rules of the plugins. */
    private static ?array $completionrules = null;

    /**
     * Dispatches a hook.
     *
     * @param object $hook The hook.
     * @return object The dispatched hook.
     */
    public static function dispatch(object $hook): object {
        return \core\di::get(\core\hook\manager::class)->dispatch($hook);
    }

    /**
     * Whether a plugin adds support for a Moodle feature to the activity.
     *
     * @param string $feature The FEATURE_xx constant.
     * @return bool
     */
    public static function supports(string $feature): bool {
        if (!array_key_exists($feature, self::$features)) {
            $hook = self::dispatch(new supports_feature($feature));
            self::$features[$feature] = $hook->is_supported();
        }
        return self::$features[$feature];
    }

    /**
     * The custom completion rules that the plugins add.
     *
     * @return completion_rule[] The rules by name.
     */
    public static function completion_rules(): array {
        if (self::$completionrules === null) {
            self::$completionrules = self::dispatch(new completion_rules())->get_rules();
        }
        return self::$completionrules;
    }

    /**
     * Forgets the cached answers, for example after the plugins changed in a test.
     */
    public static function reset_caches(): void {
        self::$features = [];
        self::$completionrules = null;
    }
}
