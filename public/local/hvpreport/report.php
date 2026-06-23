<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * View all student results for an H5P activity
 *
 * @package    local_hvpreport
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/hvp/locallib.php');

global $DB, $OUTPUT, $PAGE;

// Get parameters from URL.
$id = required_param('id', PARAM_INT); // Course Module ID.
$groupid = optional_param('group', 0, PARAM_INT); // Group ID (0 = all groups).

// Get course module and verify access.
$cm = get_coursemodule_from_id('hvp', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$hvp = $DB->get_record('hvp', array('id' => $cm->instance), '*', MUST_EXIST);

// Require login and check permissions.
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/hvp:viewallresults', $context);

// Set up the page.
$PAGE->set_url('/local/hvpreport/report.php', array('id' => $id, 'group' => $groupid));
$PAGE->set_title(format_string($hvp->name) . ' - ' . get_string('studentresults', 'local_hvpreport'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

// Output header.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($hvp->name) . ' - ' . get_string('studentresults', 'local_hvpreport'));

// Get all groups for this course.
$groups = groups_get_all_groups($course->id);

// Get all students enrolled in this course, filtered by group if selected.
if ($groupid > 0) {
    // Get students from specific group.
    $students = get_enrolled_users($context, 'mod/hvp:saveresults', $groupid, 
        'u.id, u.firstname, u.lastname, u.email', 'u.lastname ASC, u.firstname ASC');
} else {
    // Get all students.
    $students = get_enrolled_users($context, 'mod/hvp:saveresults', 0, 
        'u.id, u.firstname, u.lastname, u.email', 'u.lastname ASC, u.firstname ASC');
}

// Check if any students are enrolled.
if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsfound', 'moodle'), 'notifymessage');
    echo $OUTPUT->footer();
    exit;
}

// Get only the parent records (complete attempts) for this H5P activity.
$sql = "SELECT x.id, x.user_id, x.raw_score, x.max_score
        FROM {hvp_xapi_results} x
        WHERE x.content_id = :contentid
        AND x.parent_id IS NULL
        ORDER BY x.user_id, x.id DESC";

$results = $DB->get_records_sql($sql, array('contentid' => $hvp->id));

// Get the grade item for this H5P activity to calculate scores.
$gradeitem = $DB->get_record('grade_items', array(
    'itemtype' => 'mod',
    'itemmodule' => 'hvp',
    'iteminstance' => $hvp->id
));

// Group results by user.
$userresults = array();
foreach ($results as $result) {
    if (!isset($userresults[$result->user_id])) {
        $userresults[$result->user_id] = array();
    }
    $userresults[$result->user_id][] = $result;
}

// Fetch ALL grade records at once for all students to avoid queries in loop.
$gradrecords = array();
if ($gradeitem && !empty($students)) {
    $userids = array_keys($students);
    list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $sql = "SELECT * FROM {grade_grades} 
            WHERE itemid = :itemid AND userid $insql";
    $params = array_merge(array('itemid' => $gradeitem->id), $inparams);
    $grades = $DB->get_records_sql($sql, $params);
    
    // Index by user ID for quick lookup.
    foreach ($grades as $grade) {
        $gradrecords[$grade->userid] = $grade;
    }
}

// Prepare student data for the template.
$studentdata = array();
foreach ($students as $student) {
    $userid = $student->id;
    $studentrow = new stdClass();
    $studentrow->fullname = fullname($student);
    $studentrow->email = $student->email;
    
    // Check if student has any attempts.
    if (isset($userresults[$userid]) && !empty($userresults[$userid])) {
        $attempts = $userresults[$userid];
        $studentrow->attemptcount = count($attempts);
        $studentrow->hasattempts = true;
        
        // Get the gradebook score from pre-fetched records.
        $graderecord = isset($gradrecords[$userid]) ? $gradrecords[$userid] : null;
        
        if ($graderecord && $graderecord->finalgrade !== null) {
            // Calculate percentage and score from gradebook.
            $gradebookscore = $graderecord->finalgrade;
            $grademax = $gradeitem->grademax ?? 100;
            $percentage = ($gradebookscore / $grademax) * 100;
            $studentrow->percentagetext = round($percentage, 1) . '%';
            $studentrow->scoretext = round($gradebookscore, 2) . '/' . round($grademax, 2);
            
            // Format the timestamp.
            $studentrow->timestamp = userdate($graderecord->timemodified, get_string('strftimedatetime', 'langconfig'));
        } else {
            // No grade recorded yet.
            $studentrow->percentagetext = '-';
            $studentrow->scoretext = '-';
            $studentrow->timestamp = get_string('nodate', 'local_hvpreport');
        }
        
        // Link to detailed review page.
        $reviewurl = new moodle_url('/mod/hvp/review.php', array('id' => $hvp->id, 'user' => $userid));
        $studentrow->reviewurl = $reviewurl->out(false);
    } else {
        // No attempts yet.
        $studentrow->attemptcount = 0;
        $studentrow->hasattempts = false;
        $studentrow->percentagetext = '-';
        $studentrow->scoretext = '-';
        $studentrow->timestamp = get_string('noattempts', 'quiz');
    }
    
    $studentdata[] = $studentrow;
}

// Create the renderable and render it.
$backurl = new moodle_url('/mod/hvp/view.php', array('id' => $id));
$reportview = new \local_hvpreport\output\report_view(
    format_string($hvp->name),
    $groups,
    $groupid,
    $id,
    $studentdata,
    $backurl->out(false)
);

$renderer = $PAGE->get_renderer('local_hvpreport');
echo $renderer->render_report_view($reportview);

echo $OUTPUT->footer();
