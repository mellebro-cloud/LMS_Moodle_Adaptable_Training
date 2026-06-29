<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player.
 *
 * local_heyday_quiz no longer operates as a standalone learner shell.
 * Quiz rendering is owned by local_heyday_courseplayer (?page=lesson&cmid=CMID).
 * Moodle Quiz remains the real quiz engine; this is a URL redirect only.
 *
 * reviewattempt: redirect directly to Moodle's native review page, which the
 * courseplayer lib.php hooks already style. This avoids routing a learner-specific
 * attempt id through the player URL and keeps the review link stable.
 *
 * @package   local_heyday_quiz
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid        = required_param('id', PARAM_INT);
$cmid            = optional_param('cmid', 0, PARAM_INT);
$reviewattemptid = optional_param('reviewattempt', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_capability('moodle/course:view', context_course::instance($course->id));

if ($reviewattemptid > 0 && $cmid > 0) {
    redirect(new moodle_url('/mod/quiz/review.php', [
        'attempt' => $reviewattemptid,
        'cmid'    => $cmid,
    ]));
}

$params = ['id' => $course->id];
if ($cmid > 0) {
    $params['page'] = 'lesson';
    $params['cmid'] = $cmid;
} else {
    $params['page'] = 'home';
}

redirect(new moodle_url('/local/heyday_courseplayer/index.php', $params));
