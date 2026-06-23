<?php
// Setup page for local_heyday_gettingstarted.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$setup = optional_param('setup', 0, PARAM_BOOL);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/course:update', $context);

$PAGE->set_url(new moodle_url('/local/heyday_gettingstarted/index.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('setupheading', 'local_heyday_gettingstarted'));
$PAGE->set_heading(format_string($course->fullname));

/**
 * Normalize text for matching names.
 *
 * @param string|null $value
 * @return string
 */
function local_heyday_gettingstarted_norm(?string $value): string {
    return core_text::strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

/**
 * Find a course section by name.
 *
 * @param int $courseid
 * @param string $name
 * @return stdClass|null
 */
function local_heyday_gettingstarted_find_section(int $courseid, string $name): ?stdClass {
    global $DB;

    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

    foreach ($sections as $section) {
        if (local_heyday_gettingstarted_norm($section->name) === local_heyday_gettingstarted_norm($name)) {
            return $section;
        }
    }

    return null;
}

/**
 * Get or create Getting Started section.
 *
 * @param stdClass $course
 * @return stdClass
 */
function local_heyday_gettingstarted_get_section(stdClass $course): stdClass {
    global $DB;

    $section = local_heyday_gettingstarted_find_section((int)$course->id, 'Getting Started');

    if ($section) {
        $section->name = 'Getting Started';
        $section->visible = 1;
        $section->summary = '';
        $section->summaryformat = FORMAT_HTML;
        $DB->update_record('course_sections', $section);
        return $section;
    }

    $maxsection = (int)$DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$course->id]);
    $newnum = $maxsection + 1;

    course_create_sections_if_missing($course->id, range(0, $newnum));

    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $newnum], '*', MUST_EXIST);
    $section->name = 'Getting Started';
    $section->visible = 1;
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $DB->update_record('course_sections', $section);

    return $section;
}

/**
 * Get visible name for a course module.
 *
 * @param stdClass $cm
 * @return string
 */
function local_heyday_gettingstarted_cm_name(stdClass $cm): string {
    global $DB;

    if ($cm->modname === 'url') {
        return (string)$DB->get_field('url', 'name', ['id' => $cm->instance]);
    }

    if ($cm->modname === 'page') {
        return (string)$DB->get_field('page', 'name', ['id' => $cm->instance]);
    }

    return '';
}

/**
 * Delete duplicate/old Getting Started activities in the section.
 * This removes the old Moodle Page resources that opened /mod/page/view.php and any extra duplicate URL rows.
 *
 * @param int $courseid
 * @param int $sectionid
 * @param array $keepcmids
 * @return int number deleted
 */
function local_heyday_gettingstarted_delete_old_duplicates(int $courseid, int $sectionid, array $keepcmids = []): int {
    global $DB;

    $names = [
        'course overview',
        'syllabus',
        'navigating this course',
    ];

    $sql = "SELECT cm.*, m.name AS modname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid
               AND cm.section = :sectionid
               AND m.name IN ('page', 'url')
          ORDER BY cm.id ASC";

    $cms = $DB->get_records_sql($sql, [
        'courseid' => $courseid,
        'sectionid' => $sectionid,
    ]);

    $deleted = 0;

    foreach ($cms as $cm) {
        if (in_array((int)$cm->id, $keepcmids, true)) {
            continue;
        }

        $name = local_heyday_gettingstarted_norm(local_heyday_gettingstarted_cm_name($cm));

        if (in_array($name, $names, true)) {
            course_delete_module((int)$cm->id);
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Find URL cm by idnumber.
 *
 * @param int $courseid
 * @param string $idnumber
 * @return stdClass|null
 */
function local_heyday_gettingstarted_find_url_cm(int $courseid, string $idnumber): ?stdClass {
    global $DB;

    $sql = "SELECT cm.*, u.id AS urlid, u.externalurl, u.name AS urlname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {url} u ON u.id = cm.instance
             WHERE cm.course = :courseid
               AND cm.idnumber = :idnumber
               AND m.name = 'url'
          ORDER BY cm.id ASC";

    $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'idnumber' => $idnumber], 0, 1);

    if (!$records) {
        return null;
    }

    return reset($records);
}

/**
 * Create or update one URL module.
 *
 * @param stdClass $course
 * @param stdClass $section
 * @param string $idnumber
 * @param string $name
 * @param string $pagekey
 * @return int cmid
 */
function local_heyday_gettingstarted_create_or_update_url(stdClass $course, stdClass $section, string $idnumber, string $name, string $pagekey): int {
    global $DB;

    $moduleid = $DB->get_field('modules', 'id', ['name' => 'url'], MUST_EXIST);
    $existing = local_heyday_gettingstarted_find_url_cm((int)$course->id, $idnumber);

    if ($existing) {
        $cmid = (int)$existing->id;
        $urlid = (int)$existing->urlid;

        $externalurl = (new moodle_url('/local/heyday_gettingstarted/view.php', [
            'courseid' => $course->id,
            'page' => $pagekey,
            'cmid' => $cmid,
        ]))->out(false);

        $url = $DB->get_record('url', ['id' => $urlid], '*', MUST_EXIST);
        $url->name = $name;
        $url->externalurl = $externalurl;
        $url->intro = '';
        $url->introformat = FORMAT_HTML;
        $url->display = 0;
        $url->timemodified = time();
        $DB->update_record('url', $url);

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $cm->section = $section->id;
        $cm->visible = 1;
        $cm->visibleold = 1;
        $cm->idnumber = $idnumber;
        $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
        $cm->completionview = 1;
        $cm->completionexpected = 0;
        $cm->showdescription = 0;
        $DB->update_record('course_modules', $cm);

        return $cmid;
    }

    $url = new stdClass();
    $url->course = $course->id;
    $url->name = $name;
    $url->intro = '';
    $url->introformat = FORMAT_HTML;
    $url->externalurl = (new moodle_url('/local/heyday_gettingstarted/view.php', [
        'courseid' => $course->id,
        'page' => $pagekey,
    ]))->out(false);
    $url->display = 0;
    $url->displayoptions = serialize([]);
    $url->parameters = serialize([]);
    $url->timemodified = time();

    $urlid = $DB->insert_record('url', $url);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $moduleid;
    $cm->instance = $urlid;
    $cm->section = $section->id;
    $cm->idnumber = $idnumber;
    $cm->added = time();
    $cm->score = 0;
    $cm->indent = 0;
    $cm->visible = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
    $cm->completiongradeitemnumber = null;
    $cm->completionview = 1;
    $cm->completionexpected = 0;
    $cm->availability = null;
    $cm->showdescription = 0;
    $cm->deletioninprogress = 0;

    $cmid = $DB->insert_record('course_modules', $cm);

    course_add_cm_to_section($course->id, $cmid, (int)$section->section);

    $url->id = $urlid;
    $url->externalurl = (new moodle_url('/local/heyday_gettingstarted/view.php', [
        'courseid' => $course->id,
        'page' => $pagekey,
        'cmid' => $cmid,
    ]))->out(false);
    $DB->update_record('url', $url);

    return $cmid;
}

/**
 * Force Getting Started section sequence to exactly the three custom URL activities first.
 *
 * @param stdClass $section
 * @param array $orderedcmids
 */
function local_heyday_gettingstarted_set_section_sequence(stdClass $section, array $orderedcmids): void {
    global $DB;

    $existing = array_filter(array_map('intval', explode(',', (string)$section->sequence)));
    $ordered = array_map('intval', $orderedcmids);
    $rest = [];

    foreach ($existing as $cmid) {
        if (!in_array($cmid, $ordered, true)) {
            $rest[] = $cmid;
        }
    }

    $section->sequence = implode(',', array_merge($ordered, $rest));
    $DB->update_record('course_sections', $section);
}

if ($setup && confirm_sesskey()) {
    $section = local_heyday_gettingstarted_get_section($course);

    // Remove previously generated Moodle Page duplicates before creating/updating URL placeholders.
    local_heyday_gettingstarted_delete_old_duplicates((int)$course->id, (int)$section->id);

    $overviewcmid = local_heyday_gettingstarted_create_or_update_url($course, $section, 'GS_COURSE_OVERVIEW', 'Course Overview', 'overview');
    $syllabuscmid = local_heyday_gettingstarted_create_or_update_url($course, $section, 'GS_SYLLABUS', 'Syllabus', 'syllabus');
    $navigatingcmid = local_heyday_gettingstarted_create_or_update_url($course, $section, 'GS_NAVIGATING_THIS_COURSE', 'Navigating this Course', 'navigating');

    $keep = [$overviewcmid, $syllabuscmid, $navigatingcmid];

    // Remove any remaining duplicates with the same names, including old hidden Page activities visible to admins.
    local_heyday_gettingstarted_delete_old_duplicates((int)$course->id, (int)$section->id, $keep);

    $section = $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST);
    local_heyday_gettingstarted_set_section_sequence($section, $keep);

    rebuild_course_cache($course->id, true);

    redirect(
        new moodle_url('/local/heyday_gettingstarted/index.php', ['courseid' => $course->id]),
        'Getting Started cleaned and rebuilt. Old Moodle Page duplicates were removed. Course Overview, Syllabus, and Navigating this Course now point to the shared Heyday master-shell pages.',
        2,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('setupheading', 'local_heyday_gettingstarted'));

echo html_writer::tag('p', 'This setup rebuilds the Getting Started section with three URL links. Each link opens inside the shared local_heyday_courseplayer master shell, so teachers keep Moodle editing while learners see the ed2go-style player.');

echo html_writer::alist([
    'Getting Started = course section',
    'Course Overview = URL activity opening the shared master shell at /local/heyday_gettingstarted/view.php?courseid=' . $course->id . '&page=overview',
    'Syllabus = URL activity opening the shared master shell at /local/heyday_gettingstarted/view.php?courseid=' . $course->id . '&page=syllabus',
    'Navigating this Course = URL activity opening the shared master shell at /local/heyday_gettingstarted/view.php?courseid=' . $course->id . '&page=navigating',
    'Old Moodle Page duplicates with the same names are deleted from the Getting Started section.',
]);

$setupurl = new moodle_url('/local/heyday_gettingstarted/index.php', [
    'courseid' => $course->id,
    'setup' => 1,
    'sesskey' => sesskey(),
]);

echo html_writer::link($setupurl, 'Clean and rebuild Getting Started links', ['class' => 'btn btn-primary']);

echo $OUTPUT->footer();
