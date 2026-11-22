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
 * Tests of the validation of style values.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\local\css::class)]
final class css_test extends \basic_testcase {
    /**
     * Margins and paddings: lengths only.
     */
    public function test_spacing(): void {
        foreach (['', '0', '10px', '1.5rem 2%', '0 auto', '-5px 10px 0 1em'] as $valid) {
            $this->assertTrue(css::is_spacing($valid), $valid);
        }
        foreach (['10px;', '0" onmouseover="alert(1)', 'expression(alert(1))', '1px 2px 3px 4px 5px', 'red'] as $invalid) {
            $this->assertFalse(css::is_spacing($invalid), $invalid);
            $this->assertSame('', css::clean_spacing($invalid));
        }
    }

    /**
     * Backgrounds: colours and gradients only.
     */
    public function test_background(): void {
        foreach (
            ['', '#fff', '#e66465', 'red', 'rgba(0, 0, 0, 0.5)', 'linear-gradient(#e66465, #9198e5)',
                'radial-gradient(circle, rgb(0, 0, 0) 10%, transparent 90%)', 'linear-gradient(45deg, red 0%, blue 100%)'] as $valid
        ) {
            $this->assertTrue(css::is_background($valid), $valid);
        }
        foreach (
            ['url(http://evil)', 'red;position:fixed', '#fff" onclick="x', 'linear-gradient(url(x), red)',
                'expression(alert(1))', 'var(--x)', '</style>'] as $invalid
        ) {
            $this->assertFalse(css::is_background($invalid), $invalid);
            $this->assertSame('', css::clean_background($invalid));
        }
    }

    /**
     * Values longer than their columns in contentdesigner_options are not valid.
     */
    public function test_column_sizes(): void {
        $spacing = '100.25rem 100.25rem 100.25rem'; // 29 characters.
        $this->assertTrue(css::is_spacing($spacing));
        // Four valid lengths, but 33 characters.
        $this->assertFalse(css::is_spacing($spacing . ' 1px'));

        $gradient = 'linear-gradient(90deg, ' . implode(', ', array_fill(0, 5, 'rgba(10, 20, 30, 0.5)')) . ')';
        $this->assertLessThanOrEqual(css::MAX_BACKGROUND, strlen('linear-gradient(90deg, rgba(10, 20, 30, 0.5), red)'));
        $this->assertTrue(css::is_background('linear-gradient(90deg, rgba(10, 20, 30, 0.5), red)'));
        $this->assertGreaterThan(css::MAX_BACKGROUND, strlen($gradient));
        $this->assertFalse(css::is_background($gradient));
    }
}
