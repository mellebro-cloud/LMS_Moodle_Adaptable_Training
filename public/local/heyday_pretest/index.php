<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player Pretest page.
 *
 * local_heyday_pretest no longer operates as a standalone learner shell.
 * The pretest experience is owned by local_heyday_courseplayer (?page=pretest).
 * Moodle Quiz remains the real quiz engine; this is a URL redirect only.
 *
 * @package   local_heyday_pretest
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$cmid     = optional_param('cmid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_capability('moodle/course:view', context_course::instance($course->id));

$params = ['id' => $course->id, 'page' => 'pretest'];
if ($cmid > 0) {
    $params['cmid'] = $cmid;
}

redirect(new moodle_url('/local/heyday_courseplayer/index.php', $params));
