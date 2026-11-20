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

/**
 * Element plugin "Table of contents" - string file.
 *
 * @package   cdelement_tableofcontents
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

$string['actiontstatus'] = 'Call to action';
$string['actiontstatus_help'] = 'This setting enables a Call to Action button that dynamically guides users based on their progress:<br>
<b>Start</b> Now directs to the first chapter if no chapters are completed.<br>
<b>Resume</b> appears if some chapters are complete, linking to the next incomplete chapter.<br>
<b>Review</b> shows once all chapters are complete, linking back to the first chapter for a recap."';
$string['elementdescription'] = 'Add a table of contents';
$string['hiddenonmobile'] = 'Hidden on mobile';
$string['introtext'] = 'Intro text';
$string['introtext_help'] = 'This text is an introduction to the Table of Contents, giving users an overview of what’s covered in this activity.<br><b>Example:</b>Welcome to this content designer module! Below is a breakdown of the chapters we’ll cover, organized for easy navigation. Use this Table of Contents to quickly access each chapter and keep track of your progress as you go';
$string['pluginname'] = 'Table of contents';
$string['privacy:metadata'] = 'The tableofcontents only displays the tableofcontents content and does not store any user data.';
$string['resume'] = 'Resume';
$string['review'] = 'Review';
$string['scrollup'] = 'Enabled when scrolling up';
$string['startnow'] = 'Start now';
$string['sticky:chaptervisible'] = 'Chapter titles in sticky state';
$string['sticky:chaptervisible_help'] = 'These settings control the visibility of chapter titles when the Table of Contents (TOC) is in a sticky state.<br><b>Visible (default)</b>Chapter titles are always displayed in a sticky state.<br><b>Hidden</b>Chapter titles are never displayed in the sticky state.<br><b>Hidden on Mobile</b>Chapter titles are hidden in the sticky state on mobile devices but will be shown on larger screens.';
$string['sticky:modtitlevisible'] = 'Activity title in sticky state';
$string['sticky:modtitlevisible_help'] = 'These settings control the visibility of the activity title when the Table of Contents (TOC) is in a sticky state.<br><b>Visible (default)</b>The activity title is always displayed in a sticky state.<br><b>Hidden</b>The activity title is never displayed in the sticky state.<br><b>Hidden on Mobile</b>The activity title is hidden in the sticky state on mobile devices but will be displayed on larger screens.';
$string['stickytype'] = 'Sticky table of contents';
$string['stickytype_help'] = 'This setting makes the Table of Contents (TOC) stick to the top of the page as users scroll, improving navigation. You can choose:<br><b>Disabled</b> to keep the TOC in its original place,<br><b>Enabled</b> to make it sticky below the navbar as you scroll down,<br><b>Enabled When Scrolling Up</b> to make it sticky only when you scroll up after it has moved out of view.';
