<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player for a lesson discussion forum.
 *
 * Discovers the "Lesson N Discussion Area" forum by lesson number and redirects
 * to local_heyday_courseplayer (?page=lesson&cmid=CMID). Falls back to the
 * Discussions list page (?page=discussions) when no matching forum is found.
 *
 * @package   local_heyday_discussions
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id     = required_param('id', PARAM_INT);
$lesson = required_param('lesson', PARAM_INT);

if ($lesson < 1 || $lesson > 99) {
    throw new moodle_exception('invalidlessonno', 'local_heyday_discussions');
}

$course       = get_course($id);
$coursecontext = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:view', $coursecontext);

$modinfo = get_fast_modinfo($course, $USER->id);
$cm      = null;

foreach ($modinfo->get_cms() as $candidate) {
    if ($candidate->modname !== 'forum') {
        continue;
    }
    if (!preg_match('/\blesson\s*0*' . $lesson . '\b/i', $candidate->name)) {
        continue;
    }
    if (!preg_match('/discussion\s*area/i', $candidate->name)) {
        continue;
    }
    $cm = $candidate;
    break;
}

if ($cm) {
    redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
        'id'   => $course->id,
        'page' => 'lesson',
        'cmid' => $cm->id,
    ]));
} else {
    redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
        'id'   => $course->id,
        'page' => 'discussions',
    ]));
}
