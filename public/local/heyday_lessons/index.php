<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Redirect to the master Heyday course player, preserving page key and cmid.
 *
 * local_heyday_lessons no longer operates as a standalone learner shell.
 * All learner routing is owned by local_heyday_courseplayer. The lesson
 * structure and service logic in locallib.php continues to be used by the
 * courseplayer internally.
 *
 * Page-key mapping:
 *   (none) or home            → ?page=home
 *   content or (cmid > 0)     → ?page=lesson&cmid=CMID
 *   scores                    → ?page=scores
 *   discussions               → ?page=discussions
 *   gettingstarted            → ?page=gettingstarted
 *   pretest                   → ?page=pretest
 *   resources                 → ?page=resources
 *   finalexam                 → ?page=finalexam
 *
 * @package   local_heyday_lessons
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$cmid     = optional_param('cmid', 0, PARAM_INT);
$pagekey  = optional_param('page', '', PARAM_ALPHANUMEXT);

$course = get_course($courseid);
require_login($course);
require_capability('moodle/course:view', context_course::instance($course->id));

$params = ['id' => $course->id];

if ($pagekey === 'content' || ($pagekey === '' && $cmid > 0)) {
    $params['page'] = 'lesson';
    $params['cmid'] = $cmid;
} else if ($pagekey !== '' && $pagekey !== 'home') {
    $params['page'] = $pagekey;
    if ($cmid > 0) {
        $params['cmid'] = $cmid;
    }
} else {
    $params['page'] = 'home';
}

redirect(new moodle_url('/local/heyday_courseplayer/index.php', $params));
