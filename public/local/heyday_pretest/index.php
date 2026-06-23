<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Course-level entry point. Finds the configured Pretest quiz and opens the shell.
 *
 * @package    local_heyday_pretest
 * @copyright  2026 Heyday LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($course->id);
require_capability('local/heyday_pretest:view', $coursecontext);

$PAGE->set_url(new moodle_url('/local/heyday_pretest/index.php', ['id' => $courseid]));
$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_title(get_string('pluginname', 'local_heyday_pretest'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('course');

global $DB, $USER, $OUTPUT;
$modinfo = get_fast_modinfo($course, $USER->id);

foreach ($modinfo->cms as $cm) {
    if ($cm->modname !== 'quiz') {
        continue;
    }
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
    if ($quiz && local_heyday_pretest_is_pretest_quiz($quiz)) {
        redirect(new moodle_url('/local/heyday_pretest/view.php', ['cmid' => $cm->id]));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('pretestnotfound', 'local_heyday_pretest'), 'warning');
echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
echo $OUTPUT->footer();
