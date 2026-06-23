<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Teacher editing bridge for the Heyday course player.
 *
 * This page intentionally sends teachers to Moodle's native editable course
 * structure page so sections, subsections, activities, and resources are edited
 * using Moodle + Adaptable instead of duplicating Moodle editing UI.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:update', $context);

// Moodle 5.x requires a valid sesskey when toggling course editing mode.
// Redirect through this bridge so teacher shortcuts do not open the
// 'required parameter sesskey was missing' error page.
redirect(new moodle_url('/course/view.php', [
    'id' => $course->id,
    'edit' => '1',
    'sesskey' => sesskey(),
]));
