<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player Discussions page.
 *
 * local_heyday_discussions no longer operates as a standalone learner shell.
 * All discussion display is owned by local_heyday_courseplayer (?page=discussions).
 *
 * @package   local_heyday_discussions
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_capability('moodle/course:view', context_course::instance($course->id));

redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
    'id'   => $course->id,
    'page' => 'discussions',
]));
