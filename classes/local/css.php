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
 * Validation of the CSS values that teachers enter in the general settings of elements.
 *
 * The values end up in style attributes, so they must not contain anything but the expected value: no quotes,
 * semicolons, braces, url() or expressions.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class css {
    /** @var int Size of the margin and padding columns of contentdesigner_options. */
    public const MAX_SPACING = 30;

    /** @var int Size of the abovecolorbg and belowcolorbg columns of contentdesigner_options. */
    public const MAX_BACKGROUND = 100;

    /** @var string One CSS length, e.g. 10px, 1.5rem, -2%, 0, auto. */
    private const LENGTH = '(?:auto|0|-?\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|vmin|vmax|pt|ch|ex)?)';

    /**
     * Whether a value is a valid margin or padding: one to four lengths.
     *
     * @param string $value The value.
     * @return bool
     */
    public static function is_spacing(string $value): bool {
        $value = trim($value);
        if (strlen($value) > self::MAX_SPACING) {
            return false;
        }
        return $value === '' || (bool) preg_match('/^' . self::LENGTH . '(?:\s+' . self::LENGTH . '){0,3}$/i', $value);
    }

    /**
     * Whether a value is a valid background: a colour or a linear or radial gradient.
     *
     * @param string $value The value.
     * @return bool
     */
    public static function is_background(string $value): bool {
        $value = trim($value);
        if ($value === '') {
            return true;
        }
        if (strlen($value) > self::MAX_BACKGROUND || preg_match('/url\s*\(|expression|var\s*\(|attr\s*\(|image-set/i', $value)) {
            return false;
        }
        $color = '(?:#[0-9a-f]{3,8}|[a-z]+|(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/deg]+\))';
        if (preg_match('/^' . $color . '$/i', $value)) {
            return true;
        }
        // Gradients: only names, numbers, colours, units, commas, spaces and nested colour functions.
        return (bool) preg_match('/^(?:repeating-)?(?:linear|radial|conic)-gradient\((?:[a-z0-9#.,%\s\/-]|' .
            '(?:rgb|rgba|hsl|hsla)\([0-9.,%\s\/deg]+\))+\)$/i', $value);
    }

    /**
     * The value if it is a valid margin or padding, otherwise an empty string.
     *
     * @param string|null $value The value.
     * @return string
     */
    public static function clean_spacing(?string $value): string {
        return ($value !== null && self::is_spacing($value)) ? trim($value) : '';
    }

    /**
     * The value if it is a valid background, otherwise an empty string.
     *
     * @param string|null $value The value.
     * @return string
     */
    public static function clean_background(?string $value): string {
        return ($value !== null && self::is_background($value)) ? trim($value) : '';
    }
}
