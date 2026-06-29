<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Redirect to the master Heyday course player Getting Started page.
 *
 * local_heyday_gettingstarted no longer operates as a standalone learner shell.
 * Getting Started is rendered by local_heyday_courseplayer (?page=gettingstarted).
 * The gs= param selects the sub-page (overview / syllabus / navigating).
 *
 * @package   local_heyday_gettingstarted
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$pagekey  = optional_param('page', 'overview', PARAM_ALPHANUMEXT);
$cmid     = optional_param('cmid', 0, PARAM_INT);

// Resolve course from cmid when courseid is absent.
if (!$courseid && $cmid) {
    $tempcm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
    if ($tempcm) {
        $courseid = (int)$tempcm->course;
    }
}

if (!$courseid) {
    throw new moodle_exception('missingparam', 'error', '', 'courseid');
}

$course = get_course($courseid);
require_login($course);
require_capability('moodle/course:view', context_course::instance($course->id));

$allowedgspages = ['overview', 'syllabus', 'navigating'];
if (!in_array($pagekey, $allowedgspages, true)) {
    $pagekey = 'overview';
}

redirect(new moodle_url('/local/heyday_courseplayer/index.php', [
    'id'   => $course->id,
    'page' => 'gettingstarted',
    'gs'   => $pagekey,
]));
