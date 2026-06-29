<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player for a single forum activity.
 *
 * local_heyday_discussions/view.php no longer operates as a standalone learner
 * shell. Forum activities are rendered by local_heyday_courseplayer via the
 * generic lesson renderer (?page=lesson&cmid=CMID).
 *
 * The embed= param is ignored; the courseplayer owns the full shell.
 *
 * @package   local_heyday_discussions
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);
require_capability('moodle/course:view', context_course::instance($course->id));

redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
    'id'   => $course->id,
    'page' => 'lesson',
    'cmid' => $cm->id,
]));
