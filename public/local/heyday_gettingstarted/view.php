<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Getting Started learner pages rendered through the shared Heyday master shell.
 *
 * This plugin keeps the editable Getting Started URL placeholders, but learner
 * viewing is now delegated to local_heyday_courseplayer's reusable shell so the
 * header, content card, completion footer, Next Up card, sidebar, and mobile
 * layout stay consistent with all other local_heyday_* plugins.
 *
 * @package   local_heyday_gettingstarted
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$pagekey = optional_param('page', 'overview', PARAM_ALPHANUMEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$completionaction = optional_param('completionaction', '', PARAM_ALPHA);

$defs = local_heyday_gettingstarted_view_definitions();
if (!isset($defs[$pagekey])) {
    $pagekey = 'overview';
}

if (!$courseid && $cmid) {
    $tempcm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
    if ($tempcm) {
        $courseid = (int)$tempcm->course;
    }
}

if (!$courseid) {
    throw new moodle_exception('missingparam', 'error', '', 'courseid');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
[$cm, $pageactivity, $pagekey] = local_heyday_gettingstarted_view_resolve_source($course->id, $pagekey, $cmid, $defs);
$current = $defs[$pagekey];

if ($cm) {
    $modulecontext = context_module::instance($cm->id);
    require_login($course, false, $cm);
} else {
    $modulecontext = $coursecontext;
    require_login($course);
}

$currenturl = new moodle_url('/local/heyday_gettingstarted/view.php', [
    'courseid' => $course->id,
    'page' => $pagekey,
]);
if ($cm) {
    $currenturl->param('cmid', $cm->id);
}

$completion = new completion_info($course);
if ($cm && $completionaction !== '') {
    require_sesskey();
    if (!isguestuser() && $completion->is_enabled($cm)) {
        if ($completionaction === 'complete') {
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
        } else if ($completionaction === 'undo') {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $USER->id);
        }
    }
    redirect($currenturl);
}

if ($pageactivity && $cm && $cm->modname === 'page') {
    $content = file_rewrite_pluginfile_urls(
        $pageactivity->content,
        'pluginfile.php',
        $modulecontext->id,
        'mod_page',
        'content',
        $pageactivity->revision
    );
    $content = format_text($content, $pageactivity->contentformat, [
        'context' => $modulecontext,
        'overflowdiv' => true,
    ]);
    $pagetitle = format_string($pageactivity->name, true, ['context' => $modulecontext]);
} else if ($pageactivity) {
    $pagecm = local_heyday_gettingstarted_view_get_page_cm_by_idnumber($course->id, $current['idnumber']);
    $pagecontext = $pagecm ? context_module::instance($pagecm->id) : $modulecontext;
    $content = file_rewrite_pluginfile_urls(
        $pageactivity->content,
        'pluginfile.php',
        $pagecontext->id,
        'mod_page',
        'content',
        $pageactivity->revision
    );
    $content = format_text($content, $pageactivity->contentformat, [
        'context' => $pagecontext,
        'overflowdiv' => true,
    ]);
    $pagetitle = format_string($pageactivity->name, true, ['context' => $pagecontext]);
} else {
    $content = $current['content'];
    $pagetitle = $current['title'];
}

$PAGE->set_url($currenturl);
$PAGE->set_context($modulecontext);
$PAGE->set_course($course);
if ($cm) {
    $PAGE->set_cm($cm, $course);
}

\local_heyday_courseplayer\output\master_shell::prepare_page($PAGE, $course, $pagetitle, 'gettingstarted');

$nexturl = local_heyday_gettingstarted_view_next_url($course->id, $current, $defs);
$sidebarhtml = \local_heyday_courseplayer\output\master_shell::simple_sidebar(
    $course,
    'gettingstarted',
    $pagekey,
    $cm ? (int)$cm->id : 0
);

$contenthtml = html_writer::start_div('heyday-gs-master heyday-gs-reference-layout');
$contenthtml .= html_writer::div($content, 'heyday-gs-content heyday-gs-reference-content heyday-childpage-content');
$contenthtml .= html_writer::empty_tag('span', ['data-heyday-content-sentinel' => '1', 'aria-hidden' => 'true']);
$contenthtml .= html_writer::end_div();

$completionfooter = null;
if ($cm && !isguestuser() && $completion->is_enabled($cm)) {
    $completiondata = $completion->get_data($cm, false, $USER->id);
    $isdone = ((int)$completiondata->completionstate === COMPLETION_COMPLETE ||
        (int)$completiondata->completionstate === COMPLETION_COMPLETE_PASS);

    $completeurl = new moodle_url($currenturl, [
        'completionaction' => 'complete',
        'sesskey' => sesskey(),
    ]);
    $undourl = new moodle_url($currenturl, [
        'completionaction' => 'undo',
        'sesskey' => sesskey(),
    ]);

    $completionfooter = [
        'done' => $isdone,
        'label' => $isdone ? 'Activity complete' : 'Activity not complete',
        'completeurl' => $isdone ? '' : $completeurl,
        'undourl' => $isdone ? $undourl : '',
        'undoavailableurl' => $undourl,
        'undolabel' => 'Undo',
    ];
}

$nextfooter = [
    'url' => $nexturl,
    'title' => $current['nexttitle'],
    'type' => $current['nexttype'],
];

$shellopenhtml = \local_heyday_courseplayer\output\master_shell::open($course, $sidebarhtml, [
    'pageclass' => 'is-page-gettingstarted is-page-childplugin',
    'pagetitle' => $pagetitle,
    'sectionline' => $current['sectiontitle'],
    'backurl' => new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'home']),
    'showtopbar' => true,
    'topbaruser' => fullname($USER),
    'showeditingtools' => false,
    'printactivitylabel' => 'Print/Save activity',
    'printsectionlabel' => 'Print/Save Getting Started',
]);

$brandname = trim((string)get_config('local_heyday_courseplayer', 'brandname')) ?: 'Heyday Training LMS';
$helpurl = (string)get_config('local_heyday_courseplayer', 'supporturl');
if ($helpurl === '') {
    $helpurl = '#';
}

echo $OUTPUT->header();
echo \local_heyday_courseplayer\output\master_shell::css_variables();
echo $shellopenhtml;
echo $contenthtml;
echo \local_heyday_courseplayer\output\master_shell::close([
    'completion' => $completionfooter,
    'next' => $nextfooter,
    'supporturl' => $helpurl,
    'cookieurl' => '#',
    'copyright' => '© 2026 ' . $brandname,
]);
echo $OUTPUT->footer();

/**
 * Page definitions.
 *
 * @return array<string,array<string,string>>
 */
function local_heyday_gettingstarted_view_definitions(): array {
    return [
        'overview' => [
            'idnumber' => 'GS_COURSE_OVERVIEW',
            'title' => 'Course Overview',
            'sectiontitle' => 'Getting Started',
            'nextkey' => 'syllabus',
            'nexttitle' => 'Syllabus',
            'nextsubtitle' => 'Getting Started',
            'nexttype' => 'activity',
            'content' => '<p>This course overview introduces the structure, expectations, and main learning path for this course.</p>
                          <p>Use this page to understand how the course is organized before you begin the lessons, discussions, quizzes, and assessments.</p>',
        ],
        'syllabus' => [
            'idnumber' => 'GS_SYLLABUS',
            'title' => 'Syllabus',
            'sectiontitle' => 'Getting Started',
            'nextkey' => 'navigating',
            'nexttitle' => 'Navigating this Course',
            'nextsubtitle' => 'Getting Started',
            'nexttype' => 'activity',
            'content' => '<p>The syllabus summarizes the course flow, lesson sequence, assessment expectations, and completion requirements.</p>
                          <ul>
                              <li>Review each lesson in order.</li>
                              <li>Complete required quizzes and activities.</li>
                              <li>Use the left course menu to move between lessons and support sections.</li>
                          </ul>',
        ],
        'navigating' => [
            'idnumber' => 'GS_NAVIGATING_THIS_COURSE',
            'title' => 'Navigating this Course',
            'sectiontitle' => 'Getting Started',
            'nextkey' => 'pretest',
            'nexttitle' => 'Pretest',
            'nextsubtitle' => 'Pretest',
            'nexttype' => 'activity',
            'content' => '<p>Use the course menu on the left side of the page to move through the course.</p>
                          <p>The main sections include Home, Scores, Discussions, Getting Started, Pretest, and the lesson sections.</p>
                          <p>Select Continue or use the left menu when you are ready to move to the next activity.</p>',
        ],
    ];
}

/**
 * Resolve current content source.
 *
 * @param int $courseid Course id.
 * @param string $pagekey Page key.
 * @param int $cmid Course module id.
 * @param array<string,array<string,string>> $defs Page definitions.
 * @return array{0:?stdClass,1:?stdClass,2:string}
 */
function local_heyday_gettingstarted_view_resolve_source(int $courseid, string $pagekey, int $cmid, array $defs): array {
    global $DB;

    $cm = null;
    $pageactivity = null;

    if ($cmid > 0) {
        $cm = get_coursemodule_from_id('', $cmid, $courseid, false, IGNORE_MISSING);
        if ($cm) {
            foreach ($defs as $key => $def) {
                if (!empty($cm->idnumber) && $cm->idnumber === $def['idnumber']) {
                    $pagekey = $key;
                    break;
                }
            }
            if ($cm->modname === 'page') {
                $pageactivity = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
            }
        }
    }

    $idnumber = $defs[$pagekey]['idnumber'];

    if (!$cm) {
        $cm = local_heyday_gettingstarted_view_get_any_cm_by_idnumber($courseid, $idnumber);
    }

    if (!$pageactivity) {
        $pagecm = local_heyday_gettingstarted_view_get_page_cm_by_idnumber($courseid, $idnumber);
        if ($pagecm) {
            $pageactivity = $DB->get_record('page', ['id' => $pagecm->instance], '*', IGNORE_MISSING);
        }
    }

    return [$cm, $pageactivity, $pagekey];
}

/** Find a Moodle Page course module by ID number. */
function local_heyday_gettingstarted_view_get_page_cm_by_idnumber(int $courseid, string $idnumber): ?stdClass {
    global $DB;

    $record = $DB->get_record_sql(
        "SELECT cm.*, m.name AS modname
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid
            AND cm.idnumber = :idnumber
            AND m.name = :modname",
        [
            'courseid' => $courseid,
            'idnumber' => $idnumber,
            'modname' => 'page',
        ],
        IGNORE_MISSING
    );

    return $record ?: null;
}

/** Find any course module by ID number. */
function local_heyday_gettingstarted_view_get_any_cm_by_idnumber(int $courseid, string $idnumber): ?stdClass {
    global $DB;

    $record = $DB->get_record_sql(
        "SELECT cm.*, m.name AS modname
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.course = :courseid
            AND cm.idnumber = :idnumber",
        [
            'courseid' => $courseid,
            'idnumber' => $idnumber,
        ],
        IGNORE_MISSING
    );

    return $record ?: null;
}

/** Build next URL. */
function local_heyday_gettingstarted_view_next_url(int $courseid, array $current, array $defs): moodle_url {
    if ($current['nextkey'] === 'pretest') {
        $pretestcm = local_heyday_gettingstarted_view_get_any_cm_by_idnumber($courseid, 'HEYDAY_PRETEST');
        if ($pretestcm) {
            return new moodle_url('/local/heyday_courseplayer/index.php', [
                'id' => $courseid,
                'page' => 'lesson',
                'cmid' => $pretestcm->id,
            ]);
        }
        return new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $courseid, 'page' => 'pretest']);
    }

    $nextkey = $current['nextkey'];
    $params = [
        'courseid' => $courseid,
        'page' => $nextkey,
    ];

    if (isset($defs[$nextkey])) {
        $nextcm = local_heyday_gettingstarted_view_get_any_cm_by_idnumber($courseid, $defs[$nextkey]['idnumber']);
        if ($nextcm) {
            $params['cmid'] = $nextcm->id;
        }
    }

    return new moodle_url('/local/heyday_gettingstarted/view.php', $params);
}
