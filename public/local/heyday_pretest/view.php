<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player Pretest page.
 *
 * local_heyday_pretest/view.php no longer operates as a standalone learner shell.
 * The pretest experience is owned by local_heyday_courseplayer (?page=pretest).
 * Moodle Quiz remains the real quiz engine; this is a URL redirect only.
 *
 * @package   local_heyday_pretest
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm     = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);
require_capability('moodle/course:view', context_course::instance($course->id));

redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
    'id'   => $course->id,
    'page' => 'pretest',
    'cmid' => $cm->id,
]));
