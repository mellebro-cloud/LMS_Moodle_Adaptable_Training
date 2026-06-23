<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English strings for local_pretestautostart.
 *
 * @package    local_pretestautostart
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Pretest auto-start';
$string['cmids'] = 'Quiz course module IDs';
$string['cmids_desc'] = 'Enter the course module id numbers for quizzes that should open directly into the attempt page. Use the id from the quiz URL, for example mod/quiz/view.php?id=123. Separate multiple ids with commas, spaces, or new lines.';
$string['autopreview'] = 'Auto-start teacher preview too';
$string['autopreview_desc'] = 'If enabled, users with the quiz preview capability, such as teachers, will also be redirected automatically from the quiz front page into preview mode. Leave disabled on production sites unless you really want teachers to skip the Preview quiz button.';
