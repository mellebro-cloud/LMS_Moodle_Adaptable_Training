<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Activity bridge for the Heyday master course player.
 *
 * Use this URL when a Moodle course module should open inside the
 * ed2go-style player instead of the native /mod/... page:
 * /local/heyday_courseplayer/view.php?id=CMID
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = optional_param('id', 0, PARAM_INT);
$qmid = optional_param('qmid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$requestedcmid = $cmid > 0 ? $cmid : $qmid;

if ($requestedcmid > 0) {
    $cm = get_coursemodule_from_id(null, $requestedcmid, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    require_login($course, false, $cm);

    $page = 'lesson';
    $modname = (string)$cm->modname;
    $cmname = core_text::strtolower(trim((string)$cm->name));

    if (strpos($cmname, 'pretest') !== false) {
        $page = 'pretest';
    } else if (strpos($cmname, 'final') !== false && strpos($cmname, 'exam') !== false) {
        $page = 'finalexam';
    } else if (in_array($modname, ['folder', 'resource', 'url'], true)) {
        $page = 'resources';
    }

    redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
        'id' => $course->id,
        'page' => $page,
        'cmid' => $cm->id,
    ]));
}

if ($courseid > 0) {
    redirect(new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $courseid]));
}

print_error('missingparam', 'error', '', 'id');
