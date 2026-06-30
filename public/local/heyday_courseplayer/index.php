<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * One master ed2go-style learner player for Heyday LMS.
 *
 * This page keeps Moodle and Adaptable as the platform shell, then renders a
 * custom learner sequence inside one local plugin:
 * Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, and Final Exam.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Read a plugin setting with a fallback.
 *
 * @param string $name Setting name.
 * @param mixed $default Fallback value.
 * @return mixed
 */
function local_heyday_courseplayer_cfg(string $name, $default) {
    $value = get_config('local_heyday_courseplayer', $name);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

$defaultcourseid = (int)local_heyday_courseplayer_cfg('defaultcourseid', 105);
$courseid = optional_param('id', $defaultcourseid, PARAM_INT);
$rawpagekey = optional_param('page', '', PARAM_ALPHAEXT);
$pagekey = $rawpagekey !== '' ? $rawpagekey : 'auto';
$autoplayerrequest = ($rawpagekey === '');
$gspage = optional_param('gs', 'overview', PARAM_ALPHANUMEXT);
$subpage = optional_param('subpage', '', PARAM_ALPHANUMEXT);
$did    = optional_param('did', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$qmid = optional_param('qmid', 0, PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);
$completionaction = optional_param('completionaction', '', PARAM_ALPHA);

$requestedcmid = $cmid > 0 ? $cmid : $qmid;

$allowedpages = ['auto', 'home', 'scores', 'discussions', 'discussion', 'gettingstarted', 'pretest', 'lessons', 'lesson', 'objectives', 'assignment', 'quiz', 'lessonquiz', 'resources', 'finalexam'];
if (!in_array($pagekey, $allowedpages, true)) {
    $pagekey = 'home';
    $autoplayerrequest = false;
}

$allowedgspages = ['overview', 'syllabus', 'navigating'];
if (!in_array($gspage, $allowedgspages, true)) {
    $gspage = 'overview';
}

if ($courseid <= 0) {
    print_error('invalidcourseid');
}

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('moodle/course:view', $context);

// Teacher/admin shortcuts. These open Moodle's native editable course structure.
// The editing bridge adds the required sesskey before turning editing mode on.
$caneditcourse = has_capability('moodle/course:update', $context);

// Teachers/managers often test the player immediately after adding or editing
// Moodle activities on the native Adaptable course page. Force a structure
// refresh for editors, and also when ?refresh=1 is supplied, so new Page
// activities, renamed sections, and moved modules are reflected in the shell
// without requiring a manual purge of all Moodle caches.
$heydayrefreshstructure = $caneditcourse || optional_param('refresh', 0, PARAM_BOOL);
if ($heydayrefreshstructure) {
    try {
        if (function_exists('rebuild_course_cache')) {
            rebuild_course_cache($course->id, true);
        }
        if (class_exists('course_modinfo') && method_exists('course_modinfo', 'clear_instance_cache')) {
            course_modinfo::clear_instance_cache($course->id);
        }
        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    } catch (Throwable $e) {
        // Keep the player available even if a cache refresh fails.
    }
}

$editcourseurl = new moodle_url('/local/heyday_courseplayer/edit.php', ['id' => $course->id]);
$contentbankurl = new moodle_url('/contentbank/index.php', ['contextid' => $context->id]);
$editsettingsurl = new moodle_url('/course/edit.php', ['id' => $course->id]);

$params = ['id' => $course->id, 'page' => $pagekey];
if ($pagekey === 'gettingstarted') {
    $params['gs'] = $gspage;
}
    if ($requestedcmid > 0) {
        $params['cmid'] = $requestedcmid;
}

$PAGE->set_url(new moodle_url('/local/heyday_courseplayer/index.php', $params));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-heyday-courseplayer');
$PAGE->add_body_class('local-heyday-masterplayer');
$PAGE->set_title(format_string($course->fullname) . ' - ' . get_string('courseplayer', 'local_heyday_courseplayer'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/heyday_courseplayer/styles.css'));

$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$completion = new completion_info($course);

$requestedcm = null;
if ($requestedcmid > 0) {
    try {
        $candidatecm = $modinfo->get_cm($requestedcmid);
        if ((int)$candidatecm->course === (int)$course->id) {
            $requestedcm = $candidatecm;
        }
    } catch (Throwable $e) {
        $requestedcm = null;
    }
}

// Completion action bridge. This keeps the Undo link inside the player shell
// instead of sending learners to a native Moodle module page. The request must
// carry a valid Moodle sesskey.
if (in_array($completionaction, ['undo', 'complete'], true)) {
    require_sesskey();

    if ($requestedcm && $completion->is_enabled($requestedcm)) {
        try {
            $completion->update_state(
                $requestedcm,
                $completionaction === 'undo' ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE,
                $USER->id
            );
        } catch (Throwable $e) {
            // Completion rules can be automatic or locked by the activity. Keep
            // the learner in the player even if Moodle refuses a manual change.
        }
    }

    $redirectparams = ['id' => $course->id, 'page' => $pagekey];
    if ($pagekey === 'gettingstarted') {
        $redirectparams['gs'] = $gspage;
    }
    if ($requestedcmid > 0) {
        $redirectparams['cmid'] = $requestedcmid;
    }
    if ($pageid > 0) {
        $redirectparams['pageid'] = $pageid;
    }
    redirect(new moodle_url('/local/heyday_courseplayer/index.php', $redirectparams));
}

/**
 * Sanitize hex colour setting.
 *
 * @param string $value Setting value.
 * @param string $default Default colour.
 * @return string
 */
function local_heyday_courseplayer_colour(string $value, string $default): string {
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
        return $value;
    }
    return $default;
}

/**
 * Clamp an integer setting.
 *
 * @param mixed $value Setting value.
 * @param int $default Default.
 * @param int $min Minimum.
 * @param int $max Maximum.
 * @return int
 */
function local_heyday_courseplayer_int($value, int $default, int $min, int $max): int {
    $value = (int)$value;
    if ($value < $min || $value > $max) {
        return $default;
    }
    return $value;
}

/**
 * Build a Moodle URL from a plugin setting.
 *
 * @param string $setting Setting value.
 * @param string $fallback Relative fallback.
 * @return moodle_url
 */
function local_heyday_courseplayer_setting_url(string $setting, string $fallback): moodle_url {
    $setting = trim($setting);
    if ($setting === '') {
        $setting = $fallback;
    }

    if (preg_match('/^https?:\/\//i', $setting)) {
        return new moodle_url($setting);
    }

    if ($setting[0] !== '/') {
        $setting = '/' . $setting;
    }

    return new moodle_url($setting);
}

/**
 * Build a courseplayer URL.
 *
 * @param stdClass $course Course record.
 * @param string $page Page key.
 * @param array<string,mixed> $extra Extra params.
 * @return moodle_url
 */
function local_heyday_courseplayer_url(stdClass $course, string $page, array $extra = []): moodle_url {
    return new moodle_url('/local/heyday_courseplayer/index.php', array_merge(['id' => $course->id, 'page' => $page], $extra));
}

/**
 * Check whether a course section is a lesson section.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_courseplayer_is_lesson_section(string $sectionname): bool {
    return (bool)preg_match('/^\s*lesson\s+\d+/i', trim($sectionname));
}


/**
 * Normalize small bits of HTML/formatted text for menu labels.
 *
 * @param string $text Source text.
 * @return string Plain one-line text.
 */
function local_heyday_courseplayer_plain_menu_text(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

/**
 * Return the lesson number from a Lesson N label.
 *
 * @param string $name Label to inspect.
 * @return int|null Lesson number or null.
 */
function local_heyday_courseplayer_lesson_number_from_name(string $name): ?int {
    if (preg_match('/^\s*lesson\s*([0-9]+)/i', trim($name), $matches)) {
        return (int)$matches[1];
    }
    return null;
}

/**
 * Whether a label is only "Lesson N" with no learner-facing title.
 *
 * @param string $name Label to inspect.
 * @return bool True when the label needs a title inferred.
 */
function local_heyday_courseplayer_is_generic_lesson_label(string $name): bool {
    return (bool)preg_match('/^\s*lesson\s*[0-9]+\s*$/i', local_heyday_courseplayer_plain_menu_text($name));
}

/**
 * Whether a possible title is too generic to use as a sidebar lesson title.
 *
 * @param string $tail Candidate title text after Lesson N.
 * @return bool True for generic activity/page names.
 */
function local_heyday_courseplayer_is_generic_lesson_title_tail(string $tail): bool {
    $tail = core_text::strtolower(local_heyday_courseplayer_plain_menu_text($tail));
    $tail = preg_replace('/[^a-z0-9]+/i', ' ', $tail);
    $tail = trim((string)$tail);

    $generic = [
        '',
        'introduction',
        'lesson introduction',
        'overview',
        'lesson overview',
        'learning objectives',
        'objectives',
        'key terms',
        'review',
        'lesson review',
        'assignment',
        'lesson assignment',
        'discussion',
        'lesson discussion',
        'quiz',
        'lesson quiz',
        'resources',
        'resources for further learning',
    ];

    if (in_array($tail, $generic, true)) {
        return true;
    }

    return (bool)preg_match('/^(chapter|section|part|unit)\s*[0-9]+\b/i', $tail);
}

/**
 * Convert an activity/section candidate into an ed2go-style Lesson N: Title label.
 *
 * @param string $candidate Candidate activity, section, or summary title.
 * @param int $lessonno Lesson number.
 * @return string Empty string when the candidate is not useful.
 */
function local_heyday_courseplayer_lesson_group_candidate(string $candidate, int $lessonno): string {
    $candidate = local_heyday_courseplayer_plain_menu_text($candidate);
    if ($candidate === '') {
        return '';
    }

    if (preg_match('/^\s*lesson\s*' . $lessonno . '\s*[:\-]\s*(.+)$/i', $candidate, $matches)) {
        $tail = local_heyday_courseplayer_plain_menu_text($matches[1]);
        if (!local_heyday_courseplayer_is_generic_lesson_title_tail($tail)) {
            return 'Lesson ' . $lessonno . ': ' . $tail;
        }
        return '';
    }

    if (preg_match('/^\s*lesson\s*' . $lessonno . '\s+(.+)$/i', $candidate, $matches)) {
        $tail = local_heyday_courseplayer_plain_menu_text($matches[1]);
        if (!local_heyday_courseplayer_is_generic_lesson_title_tail($tail)) {
            return 'Lesson ' . $lessonno . ': ' . $tail;
        }
        return '';
    }

    if (preg_match('/^\s*lesson\s*[0-9]+\b/i', $candidate)) {
        return '';
    }

    if (!local_heyday_courseplayer_is_generic_lesson_title_tail($candidate)) {
        return 'Lesson ' . $lessonno . ': ' . $candidate;
    }

    return '';
}

/**
 * Screenshot-parity fallback for the supplied Blockchain Fundamentals template.
 * This is used only when Moodle has generic section/activity names such as "Lesson 1".
 *
 * @param stdClass $course Course record.
 * @param int $lessonno Lesson number.
 * @return string Empty string when no fallback applies.
 */
function local_heyday_courseplayer_reference_lesson_title(stdClass $course, int $lessonno): string {
    $coursename = core_text::strtolower(trim((string)($course->fullname ?? '') . ' ' . (string)($course->shortname ?? '')));

    // Only apply the legacy Blockchain fallback when the course name actually
    // identifies a Blockchain course. Course id 105 is a reusable template and
    // must use its real Moodle section/subsection titles for future courses.
    if (strpos($coursename, 'blockchain') === false) {
        return '';
    }

    $titles = [
        1 => 'Introduction to Blockchain',
        2 => 'Why Is Blockchain Needed?',
        3 => 'The Blockchain Marketplace & Workforce',
        4 => 'Ownership Concepts in Blockchain',
        5 => 'The Shared Ledger',
        6 => 'Securing Transactions with Cryptography',
        7 => 'Distributing the Shared Ledger',
        8 => 'Gaining Consensus on Blockchain',
        9 => 'Cryptocurrencies',
        10 => 'Blockchain Business Cases',
        11 => 'Implementing Blockchain',
        12 => 'The Future of Blockchain',
    ];

    return isset($titles[$lessonno]) ? 'Lesson ' . $lessonno . ': ' . $titles[$lessonno] : '';
}

/**
 * Build the lesson group title shown in the left player menu.
 *
 * @param string $sectionname Moodle section name.
 * @param int $sectionnum Section number.
 * @param section_info $section Section info object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return string Sidebar label.
 */
function local_heyday_courseplayer_lesson_group_name(
    string $sectionname,
    int $sectionnum,
    section_info $section,
    course_modinfo $modinfo,
    stdClass $course,
    context_course $context
): string {
    $plainsection = local_heyday_courseplayer_plain_menu_text(format_string($sectionname));
    $lessonno = local_heyday_courseplayer_lesson_number_from_name($plainsection);

    if ($lessonno === null) {
        return $plainsection !== '' ? $plainsection : format_string($sectionname);
    }

    if (!local_heyday_courseplayer_is_generic_lesson_label($plainsection)) {
        $reference = local_heyday_courseplayer_reference_lesson_title($course, $lessonno);
        if ($reference !== '') {
            return $reference;
        }
        return $plainsection !== '' ? $plainsection : format_string($sectionname);
    }

    if (!empty($section->summary)) {
        $candidate = local_heyday_courseplayer_lesson_group_candidate((string)$section->summary, $lessonno);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $best = '';
    foreach (($modinfo->sections[$sectionnum] ?? []) as $cmid) {
        try {
            $cm = $modinfo->get_cm($cmid);
        } catch (Throwable $e) {
            continue;
        }

        if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
            continue;
        }
        if (local_heyday_courseplayer_is_final_exam_cm($cm) || local_heyday_courseplayer_is_pretest_cm($cm)) {
            continue;
        }

        $candidate = local_heyday_courseplayer_lesson_group_candidate(format_string($cm->name, true, ['context' => $cm->context]), $lessonno);
        if ($candidate === '') {
            continue;
        }

        if ($cm->modname === 'lesson') {
            return $candidate;
        }

        if ($best === '') {
            $best = $candidate;
        }
    }

    if ($best !== '') {
        return $best;
    }

    $reference = local_heyday_courseplayer_reference_lesson_title($course, $lessonno);
    if ($reference !== '') {
        return $reference;
    }

    return $plainsection;
}

/**
 * Check whether a section looks like a Resources section.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_courseplayer_is_resources_section(string $sectionname): bool {
    return (bool)preg_match('/\bresources?\b/i', trim($sectionname));
}

/**
 * Read the backing course_sections record for a section.
 *
 * Moodle's section_info object does not expose delegated-section properties
 * consistently across versions/formats. The database record is the reliable
 * source for mod_subsection child sections, so use it as a fallback when the
 * public object does not contain component/itemid.
 *
 * @param section_info $section Section info object.
 * @param int $courseid Course id.
 * @return stdClass|null course_sections row.
 */
function local_heyday_courseplayer_section_record(section_info $section, int $courseid): ?stdClass {
    global $DB;
    static $cache = [];

    $cachekey = '';
    if (!empty($section->id)) {
        $cachekey = 'id:' . (int)$section->id;
    } elseif (isset($section->section)) {
        $cachekey = 'course:' . $courseid . ':section:' . (int)$section->section;
    }
    if ($cachekey !== '' && array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    try {
        if (!empty($section->id)) {
            $record = $DB->get_record('course_sections', ['id' => (int)$section->id], 'id,course,section,component,itemid', IGNORE_MISSING);
            if ($record) {
                $cache[$cachekey] = $record;
                return $record;
            }
        }

        if (isset($section->section)) {
            $record = $DB->get_record('course_sections', [
                'course' => $courseid,
                'section' => (int)$section->section,
            ], 'id,course,section,component,itemid', IGNORE_MISSING);
            if ($record) {
                $cache[$cachekey] = $record;
                return $record;
            }
        }
    } catch (Throwable $e) {
        $cache[$cachekey] = null;
        return null;
    }

    $cache[$cachekey] = null;
    return null;
}

/**
 * Return delegated-section component metadata.
 *
 * @param section_info $section Section info object.
 * @param int $courseid Course id.
 * @return array{0:string,1:int} Component and itemid.
 */
function local_heyday_courseplayer_section_component(section_info $section, int $courseid): array {
    static $cache = [];

    $cachekey = '';
    if (!empty($section->id)) {
        $cachekey = 'id:' . (int)$section->id;
    } elseif (isset($section->section)) {
        $cachekey = 'course:' . $courseid . ':section:' . (int)$section->section;
    }
    if ($cachekey !== '' && array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    $component = isset($section->component) ? trim((string)$section->component) : '';
    $itemid = isset($section->itemid) ? (int)$section->itemid : 0;

    if ($component !== '') {
        $cache[$cachekey] = [$component, $itemid];
        return [$component, $itemid];
    }

    $record = local_heyday_courseplayer_section_record($section, $courseid);
    if ($record && !empty($record->component)) {
        $cache[$cachekey] = [trim((string)$record->component), (int)$record->itemid];
        return $cache[$cachekey];
    }

    $cache[$cachekey] = ['', 0];
    return ['', 0];
}

/**
 * Check whether this section is a delegated child section, such as a Moodle Subsection.
 *
 * Delegated sections are rendered by their parent subsection activity. If we
 * also collect them as top-level lesson groups, the HeyDay sidebar becomes
 * flatter than Moodle's native Adaptable course index.
 *
 * @param section_info $section Section info object.
 * @param int $courseid Course id.
 * @return bool True for delegated child sections.
 */
function local_heyday_courseplayer_is_delegated_section(section_info $section, int $courseid): bool {
    [$component] = local_heyday_courseplayer_section_component($section, $courseid);
    return $component !== '';
}

/**
 * Safely read the delegated section id exposed by mod_subsection customdata.
 *
 * Moodle's subsection module stores the delegated course_sections id in the
 * course-module custom data. This is more reliable than section_info fields on
 * some course formats/themes, so prefer it when present.
 *
 * @param cm_info $cm Course module.
 * @return int Delegated course_sections.id, or 0 when absent.
 */
function local_heyday_courseplayer_cm_custom_section_id(cm_info $cm): int {
    try {
        if (method_exists($cm, 'get_custom_data')) {
            $customdata = $cm->get_custom_data();
            if (is_array($customdata) && !empty($customdata['sectionid'])) {
                return (int)$customdata['sectionid'];
            }
            if (is_object($customdata) && !empty($customdata->sectionid)) {
                return (int)$customdata->sectionid;
            }
        }
    } catch (Throwable $e) {
        // Fall through to magic-property access.
    }

    try {
        $customdata = $cm->customdata ?? null;
        if (is_array($customdata) && !empty($customdata['sectionid'])) {
            return (int)$customdata['sectionid'];
        }
        if (is_object($customdata) && !empty($customdata->sectionid)) {
            return (int)$customdata->sectionid;
        }
    } catch (Throwable $e) {
        return 0;
    }

    return 0;
}

/**
 * Build a map of delegated subsection section numbers that must not become
 * top-level HeyDay lesson groups.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param int $courseid Course id.
 * @return array<int,bool> section number => true.
 */
function local_heyday_courseplayer_delegated_section_numbers(course_modinfo $modinfo, int $courseid): array {
    global $DB;

    $delegated = [];

    foreach ($modinfo->get_section_info_all() as $candidate) {
        if (!isset($candidate->section)) {
            continue;
        }
        if (local_heyday_courseplayer_is_delegated_section($candidate, $courseid)) {
            $delegated[(int)$candidate->section] = true;
        }
    }

    foreach ($modinfo->get_cms() as $cm) {
        $childsection = local_heyday_courseplayer_get_subsection_section($modinfo, $cm);
        if (!$childsection && local_heyday_courseplayer_is_subsection_cm($cm)) {
            $childsection = local_heyday_courseplayer_find_section_matching_cm_name($modinfo, $cm);
        }
        if ($childsection && isset($childsection->section)) {
            $delegated[(int)$childsection->section] = true;
        }
    }

    try {
        $records = $DB->get_records_select(
            'course_sections',
            'course = ? AND component IS NOT NULL AND component <> ?',
            [$courseid, ''],
            '',
            'id,section,component,itemid'
        );
        foreach ($records as $record) {
            $delegated[(int)$record->section] = true;
        }
    } catch (Throwable $e) {
        // Keep the player available if a site has an older course_sections schema.
    }

    return $delegated;
}

/**
 * Check whether a Moodle section is handled by one of the fixed shell items.
 *
 * Sections matching these names are intentionally not repeated under Lessons.
 * Other visible sections are collected dynamically, so newly added Moodle Page
 * activities in Topic/custom sections appear in the HeyDay player without
 * editing plugin code.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_courseplayer_is_shell_section(string $sectionname): bool {
    $name = core_text::strtolower(local_heyday_courseplayer_plain_menu_text($sectionname));
    $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);
    $name = trim((string)$name);

    if ($name === '') {
        return false;
    }

    $patterns = [
        '/^home$/',
        '/^scores?$/',
        '/^grades?$/',
        '/^discussions?$/',
        '/^forums?$/',
        '/^getting started$/',
        '/^pre\s*test$/',
        '/^pretest$/',
        '/^resources?$/',
        '/^final\s*exam$/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name)) {
            return true;
        }
    }

    return false;
}

/**
 * Check whether an activity looks like the Final Exam.
 *
 * @param cm_info $cm Course module.
 * @return bool
 */
function local_heyday_courseplayer_is_final_exam_cm(cm_info $cm): bool {
    return (bool)preg_match('/\bfinal\s*exam\b/i', core_text::strtolower(trim($cm->name)));
}

/**
 * Check whether an activity looks like the Pretest.
 *
 * @param cm_info $cm Course module.
 * @return bool
 */
function local_heyday_courseplayer_is_pretest_cm(cm_info $cm): bool {
    return (bool)preg_match('/\bpre[\s\-_]*test\b/i', core_text::strtolower(trim($cm->name)));
}

/**
 * Check whether an activity is a Lesson Quiz (idnumber HEYDAY_LESSON<N>_QUIZ or name pattern).
 *
 * @param cm_info $cm Course module.
 * @return bool
 */
function local_heyday_courseplayer_is_lesson_quiz_cm(cm_info $cm): bool {
    $idnumber = strtoupper(trim($cm->idnumber ?? ''));
    if (preg_match('/^HEYDAY_LESSON\d+_QUIZ$/i', $idnumber)) {
        return true;
    }
    return (bool)preg_match('/\blesson\s*\d+\s+quiz\b/i', core_text::strtolower(trim($cm->name)));
}

/**
 * Whether the module should appear to learners in this player.
 *
 * @param cm_info $cm Course module.
 * @param context_course $context Course context.
 * @return bool
 */
function local_heyday_courseplayer_should_show_cm(cm_info $cm, context_course $context): bool {
    if (!empty($cm->deletioninprogress)) {
        return false;
    }

    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $context)) {
        return false;
    }

    if ($cm->modname === 'label') {
        return false;
    }

    return true;
}

/**
 * Return the first Moodle availability date timestamp found in availability JSON.
 *
 * @param mixed $node Decoded availability JSON node.
 * @return int|null
 */
function local_heyday_courseplayer_find_availability_date($node): ?int {
    if (empty($node)) {
        return null;
    }

    if (is_object($node)) {
        if (isset($node->type) && $node->type === 'date' && isset($node->t) && is_numeric($node->t)) {
            return (int)$node->t;
        }

        foreach (get_object_vars($node) as $value) {
            $found = local_heyday_courseplayer_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    if (is_array($node)) {
        foreach ($node as $value) {
            $found = local_heyday_courseplayer_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * Build release/lock message for a course module.
 *
 * @param string $displayname Learner-facing item name.
 * @param cm_info|null $cm Course module.
 * @return string
 */
function local_heyday_courseplayer_locked_message_for_name(string $displayname, ?cm_info $cm): string {
    $name = trim($displayname);
    if ($name === '' && $cm) {
        $name = format_string($cm->name, true, ['context' => $cm->context]);
    }

    $date = null;
    if ($cm && !empty($cm->availability)) {
        $availability = json_decode($cm->availability);
        $date = local_heyday_courseplayer_find_availability_date($availability);
    }

    if ($date) {
        $formatteddate = userdate($date, '%b %d, %Y %I:%M %p %Z');
        return get_string('availableon', 'local_heyday_courseplayer', ['name' => $name, 'date' => $formatteddate]);
    }

    if ($cm && !empty($cm->availableinfo)) {
        $availableinfo = trim(strip_tags($cm->availableinfo));
        if ($availableinfo !== '') {
            return $availableinfo;
        }
    }

    return get_string('locked', 'local_heyday_courseplayer');
}

/**
 * Build locked/release message for a course module.
 *
 * @param cm_info $cm Course module.
 * @return string
 */
function local_heyday_courseplayer_locked_message(cm_info $cm): string {
    $name = format_string($cm->name, true, ['context' => $cm->context]);
    return local_heyday_courseplayer_locked_message_for_name($name, $cm);
}

/**
 * Determine completion status.
 *
 * @param completion_info $completion Completion object.
 * @param cm_info $cm Course module.
 * @return array<string,string>
 */
function local_heyday_courseplayer_completion_status(completion_info $completion, cm_info $cm): array {
    global $USER;

    if (!$cm->available || !$cm->uservisible) {
        $editorpreview = false;
        try {
            $coursecontext = context_course::instance((int)$cm->course);
            $editorpreview = (has_capability('moodle/course:update', $coursecontext) || has_capability('moodle/course:viewhiddenactivities', $coursecontext))
                && (!empty($cm->uservisible) || !empty($cm->visible));
        } catch (Throwable $e) {
            $editorpreview = false;
        }

        if (!$editorpreview) {
            return ['class' => 'locked', 'label' => get_string('locked', 'local_heyday_courseplayer'), 'icon' => '🔒'];
        }
    }

    if (!$completion->is_enabled($cm)) {
        return ['class' => 'nottracked', 'label' => get_string('nottracked', 'local_heyday_courseplayer'), 'icon' => ''];
    }

    $data = $completion->get_data($cm, false, $USER->id);
    if (!empty($data->completionstate)) {
        return ['class' => 'completed', 'label' => get_string('completed', 'local_heyday_courseplayer'), 'icon' => '✓'];
    }

    return ['class' => 'inprogress', 'label' => get_string('inprogress', 'local_heyday_courseplayer'), 'icon' => ''];
}


/**
 * Find a section whose visible Moodle title matches a Subsection activity name.
 *
 * Some Moodle 5 / course-format combinations do not expose delegated section
 * component metadata in section_info or course_sections in a way that is stable
 * for local plugins. The native course page and course index still keep the
 * Subsection activity name equal to the delegated child section title, so this
 * is a safe final fallback for preserving the Moodle hierarchy.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param cm_info $cm Possible Subsection course module.
 * @return section_info|null Matching child section.
 */
function local_heyday_courseplayer_find_section_matching_cm_name(course_modinfo $modinfo, cm_info $cm): ?section_info {
    $courseid = isset($cm->course) ? (int)$cm->course : 0;
    if ($courseid <= 0) {
        return null;
    }

    $cmname = local_heyday_courseplayer_plain_menu_text(format_string($cm->name, true, ['context' => $cm->context]));
    if ($cmname === '') {
        return null;
    }

    try {
        $course = get_course($courseid);
    } catch (Throwable $e) {
        $course = null;
    }

    $best = null;
    foreach ($modinfo->get_section_info_all() as $candidate) {
        if (!isset($candidate->section) || (int)$candidate->section === (int)$cm->sectionnum) {
            continue;
        }

        $sectionname = '';
        try {
            if ($course) {
                $sectionname = get_section_name($course, $candidate);
            } else if (isset($candidate->name)) {
                $sectionname = (string)$candidate->name;
            }
        } catch (Throwable $e) {
            $sectionname = isset($candidate->name) ? (string)$candidate->name : '';
        }

        $sectionname = local_heyday_courseplayer_plain_menu_text(format_string($sectionname));
        if ($sectionname === '' || core_text::strtolower($sectionname) !== core_text::strtolower($cmname)) {
            continue;
        }

        // Prefer the matching section that follows the parent section, because
        // that is how Moodle Subsections are shown in the native course index.
        if ((int)$candidate->section > (int)$cm->sectionnum) {
            return $candidate;
        }
        if (!$best) {
            $best = $candidate;
        }
    }

    return $best;
}

/**
 * Whether a course module is a Moodle Subsection container.
 *
 * @param cm_info $cm Course module.
 * @return bool
 */
function local_heyday_courseplayer_is_subsection_cm(cm_info $cm): bool {
    if (stripos((string)$cm->modname, 'subsection') !== false) {
        return true;
    }

    // Defensive fallback for sites where the module name is localized or
    // proxied but the activity acts like a subsection container.
    try {
        if ($cm->url && preg_match('#/mod/subsection/#i', $cm->url->out(false))) {
            return true;
        }
    } catch (Throwable $e) {
        // Not a URL-backed subsection.
    }

    return false;
}

/**
 * Get delegated child section for a Moodle Subsection activity.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param cm_info $cm Course module.
 * @return section_info|null
 */
function local_heyday_courseplayer_get_subsection_section(course_modinfo $modinfo, cm_info $cm): ?section_info {
    global $DB;
    static $cache = [];

    $cachekey = 'cm:' . (int)$cm->id . ':instance:' . (int)$cm->instance;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    // A delegated child section is only ever owned by a Subsection container
    // activity. Without this guard the component/itemid scan below treats
    // 'mod_subsection' as a candidate for every module, so any ordinary
    // activity whose instance id coincides with a Subsection instance id
    // (an instance-id collision) is misresolved to that subsection's delegated
    // child section, pulling unrelated lessons' content into the wrong group.
    if (!local_heyday_courseplayer_is_subsection_cm($cm)) {
        return $cache[$cachekey] = null;
    }

    $courseid = isset($cm->course) ? (int)$cm->course : 0;
    $componentcandidates = [];

    if (!empty($cm->modname)) {
        $componentcandidates[] = 'mod_' . (string)$cm->modname;
    }
    $componentcandidates[] = 'mod_subsection';
    $componentcandidates = array_values(array_unique($componentcandidates));

    // First use mod_subsection's cached customdata when Moodle exposes it.
    $customsectionid = local_heyday_courseplayer_cm_custom_section_id($cm);
    if ($customsectionid > 0) {
        foreach ($modinfo->get_section_info_all() as $candidate) {
            if (!empty($candidate->id) && (int)$candidate->id === $customsectionid) {
                return $cache[$cachekey] = $candidate;
            }
        }

        try {
            $record = $DB->get_record('course_sections', ['id' => $customsectionid], 'id,course,section,component,itemid', IGNORE_MISSING);
            if ($record && (int)$record->course === $courseid) {
                return $cache[$cachekey] = $modinfo->get_section_info((int)$record->section);
            }
        } catch (Throwable $e) {
            // Continue with component/itemid lookups.
        }
    }

    if (method_exists($modinfo, 'get_section_info_by_component')) {
        foreach ($componentcandidates as $component) {
            try {
                $section = $modinfo->get_section_info_by_component($component, $cm->instance);
                if ($section) {
                    return $cache[$cachekey] = $section;
                }
            } catch (Throwable $e) {
                // Fall back to scanning and then to the course_sections table.
            }
        }
    }

    foreach ($modinfo->get_section_info_all() as $candidate) {
        $component = isset($candidate->component) ? trim((string)$candidate->component) : '';
        $itemid = isset($candidate->itemid) ? (int)$candidate->itemid : 0;

        if ($component === '') {
            [$component, $itemid] = local_heyday_courseplayer_section_component($candidate, $courseid);
        }

        if ($itemid === (int)$cm->instance && in_array($component, $componentcandidates, true)) {
            return $cache[$cachekey] = $candidate;
        }
    }

    try {
        if ($courseid > 0) {
            foreach ($componentcandidates as $component) {
                $record = $DB->get_record('course_sections', [
                    'course' => $courseid,
                    'component' => $component,
                    'itemid' => (int)$cm->instance,
                ], 'id,section,component,itemid', IGNORE_MISSING);

                if ($record) {
                    return $cache[$cachekey] = $modinfo->get_section_info((int)$record->section);
                }
            }

            // Last safe fallback: some cache combinations hide the module name
            // but still store a delegated component containing "subsection".
            if (stripos((string)$cm->modname, 'subsection') !== false || $customsectionid > 0) {
                $records = $DB->get_records('course_sections', [
                    'course' => $courseid,
                    'itemid' => (int)$cm->instance,
                ], '', 'id,section,component,itemid');

                foreach ($records as $record) {
                    if (stripos((string)$record->component, 'subsection') !== false) {
                        return $cache[$cachekey] = $modinfo->get_section_info((int)$record->section);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        return $cache[$cachekey] = null;
    }

    // Final fallback: match the Subsection module name to a course section
    // title. This is intentionally limited to Subsection-like CMs so normal
    // Page/Quiz/URL activities with names similar to section headings are not
    // treated as containers.
    if (local_heyday_courseplayer_is_subsection_cm($cm)) {
        $matchedsection = local_heyday_courseplayer_find_section_matching_cm_name($modinfo, $cm);
        if ($matchedsection) {
            return $cache[$cachekey] = $matchedsection;
        }
    }

    return $cache[$cachekey] = null;
}

/**
 * Check whether a Moodle section title should be folded into the current parent Lesson group.
 *
 * @param string $sectionname Section title.
 * @param int $parentlessonno Parent lesson number.
 * @return bool
 */
function local_heyday_courseplayer_is_lesson_child_section_name(string $sectionname, int $parentlessonno): bool {
    $plain = local_heyday_courseplayer_plain_menu_text($sectionname);
    if ($plain === '') {
        return false;
    }

    $quotedlesson = preg_quote((string)$parentlessonno, '/');

    // Any section whose name starts with "Lesson N" (same number as the parent)
    // belongs to that lesson group — e.g. "Lesson 1 Resources for Further Learning",
    // "Lesson 1 Discussion Area", "Lesson 1 Quiz", "Lesson 1 Introduction".
    // The fold-loop lesson-number guard fires first for a mismatched number,
    // so catching all "Lesson N …" titles here is safe.
    if (preg_match('/^lesson\s*' . $quotedlesson . '[\s:\-]/i', $plain)) {
        return true;
    }

    // HeyDay naming convention for subsections: "1.1 Learning Objectives",
    // "1.2 Key Terms", "2.3 Chapter" etc. — fold these into the parent lesson group.
    if (preg_match('/^\d+\.\d+\b/i', $plain)) {
        return true;
    }

    // Chapter/Part/Unit headings immediately following a Lesson N parent in the
    // native Moodle course index are also child subsections of that lesson.
    if (preg_match('/^(chapter|part|unit|section)\s*\d+\b/i', $plain)) {
        return true;
    }

    return false;
}

/**
 * Get Moodle Lesson internal pages in linked lesson order.
 *
 * @param cm_info $cm Lesson module.
 * @return array<int,stdClass>
 */
function local_heyday_courseplayer_get_lesson_pages(cm_info $cm): array {
    global $DB;

    if ($cm->modname !== 'lesson') {
        return [];
    }

    $lesson = $DB->get_record('lesson', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$lesson) {
        return [];
    }

    $records = $DB->get_records('lesson_pages', ['lessonid' => $lesson->id], 'prevpageid ASC, id ASC');
    if (empty($records)) {
        return [];
    }

    $ordered = [];
    $visited = [];
    $current = null;

    foreach ($records as $record) {
        if ((int)$record->prevpageid === 0) {
            $current = $record;
            break;
        }
    }

    while ($current && empty($visited[(int)$current->id])) {
        $ordered[] = $current;
        $visited[(int)$current->id] = true;
        $nextid = (int)$current->nextpageid;
        if ($nextid <= 0 || empty($records[$nextid])) {
            break;
        }
        $current = $records[$nextid];
    }

    foreach ($records as $record) {
        if (empty($visited[(int)$record->id])) {
            $ordered[] = $record;
        }
    }

    return $ordered;
}

/**
 * Collect activities from a section. Subsections become headings and Moodle Lesson pages expand.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param int $sectionnum Section number.
 * @param context_course $context Course context.
 * @param int $depth Sidebar depth.
 * @param array<int,int> $visited Visited section numbers.
 * @return array<int,array<string,mixed>>
 */
function local_heyday_courseplayer_collect_section_items(
    course_modinfo $modinfo,
    int $sectionnum,
    context_course $context,
    int $depth = 0,
    array $visited = []
): array {
    if (in_array($sectionnum, $visited, true)) {
        return [];
    }

    $visited[] = $sectionnum;
    $items = [];
    $cmids = $modinfo->sections[$sectionnum] ?? [];

    foreach ($cmids as $sectioncmid) {
        $cm = $modinfo->get_cm($sectioncmid);
        if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
            continue;
        }
        if (local_heyday_courseplayer_is_final_exam_cm($cm) || local_heyday_courseplayer_is_pretest_cm($cm)) {
            continue;
        }

        $childsection = local_heyday_courseplayer_get_subsection_section($modinfo, $cm);
        if ($childsection && isset($childsection->section)) {
            $childitems = local_heyday_courseplayer_collect_section_items(
                $modinfo,
                (int)$childsection->section,
                $context,
                $depth + 1,
                $visited
            );

            $heading = [
                'type' => 'heading',
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'depth' => $depth,
            ];

            // Moodle Subsection headings must be clickable in the HeyDay
            // sidebar. The native Moodle course index lets learners click the
            // subsection title; in the player we route that click to the first
            // real activity found inside the delegated subsection, keeping the
            // learner inside the HeyDay shell instead of opening the native
            // course page.
            $headingtarget = local_heyday_courseplayer_first_heading_target($childitems);
            if ($headingtarget) {
                $heading['targetitem'] = $headingtarget;
            }

            // Only add the heading when the delegated child section has visible
            // activities. Empty subsections (e.g. "Assignment" placeholders with
            // no activities yet) must not clutter the learner sidebar.
            if (!empty($childitems)) {
                $items[] = $heading;
                $items = array_merge($items, $childitems);
            }
            continue;
        }

        if ($cm->modname === 'lesson') {
            $lessonpages = local_heyday_courseplayer_get_lesson_pages($cm);
            if (!empty($lessonpages)) {
                foreach ($lessonpages as $lessonpage) {
                    $items[] = [
                        'type' => 'lessonpage',
                        'cm' => $cm,
                        'page' => $lessonpage,
                        'pageid' => (int)$lessonpage->id,
                        'name' => format_string($lessonpage->title),
                        'depth' => $depth,
                    ];
                }
                continue;
            }
        }

        // Any quiz (other than the Pretest or Final Exam, which have their own
        // dedicated flows) gets the 'lessonquiz' type so its sidebar link routes
        // to the dedicated HeyDay quiz player (local_heyday_quiz) instead of the
        // generic "Open Activity" fallback card.
        if ($cm->modname === 'quiz'
                && !local_heyday_courseplayer_is_pretest_cm($cm)
                && !local_heyday_courseplayer_is_final_exam_cm($cm)) {
            $items[] = [
                'type'  => 'lessonquiz',
                'cm'    => $cm,
                'depth' => $depth,
            ];
            continue;
        }

        $items[] = [
            'type' => 'cm',
            'cm' => $cm,
            'depth' => $depth,
        ];
    }

    return $items;
}

/**
 * Return an integer sort priority for a lesson sidebar item by display name.
 *
 * Lower numbers appear first. The scale maps to the ed2go learner sequence:
 * introduction → chapters → review → assignment → discussion → quiz → resources.
 *
 * @param string $name Item display name.
 * @return int
 */
function local_heyday_courseplayer_lesson_item_sort_key(string $name): int {
    $plain = core_text::strtolower(local_heyday_courseplayer_plain_menu_text($name));
    if (preg_match('/\bintro(duction)?\b/', $plain)) {
        return 100;
    }
    if (preg_match('/learning.?obj/', $plain)) {
        return 150;
    }
    if (preg_match('/key.?term/', $plain)) {
        return 180;
    }
    if (preg_match('/\b(?:chapter|unit|part)\s*(\d+)/u', $plain, $m)) {
        return 200 + (int)$m[1];
    }
    if (preg_match('/\breview\b/', $plain)) {
        return 500;
    }
    if (preg_match('/\bassign(ment)?\b/', $plain)) {
        return 600;
    }
    if (preg_match('/\bdiscussion\b/', $plain)) {
        return 700;
    }
    if (preg_match('/\bquiz\b/', $plain)) {
        return 800;
    }
    if (preg_match('/\bresource/', $plain)) {
        return 900;
    }
    if (preg_match('/\bfaq/', $plain)) {
        return 1000;
    }
    return 400;
}

/**
 * Re-order a lesson group's flat item list to match the ed2go learner sequence.
 *
 * Items are grouped into "blocks" where each depth-0 item starts a new block and
 * all subsequent depth-1+ items belong to that block. Blocks are stable-sorted by
 * the sort key of their leading item so introduction/chapters/review come first
 * and assignment/discussion/quiz/resources follow at the end.
 *
 * @param array $items Flat item list.
 * @return array Re-ordered flat item list.
 */
function local_heyday_courseplayer_sort_lesson_items(array $items): array {
    $blocks  = [];
    $current = null;

    foreach ($items as $item) {
        $depth = (int)($item['depth'] ?? 0);
        if ($depth === 0) {
            if ($current !== null) {
                $blocks[] = $current;
            }
            $name = '';
            if (($item['type'] ?? '') === 'heading') {
                $name = (string)($item['name'] ?? '');
            } else {
                $cm   = local_heyday_courseplayer_item_cm($item);
                $name = $cm ? format_string($cm->name) : '';
            }
            $current = ['key' => local_heyday_courseplayer_lesson_item_sort_key($name), 'items' => [$item]];
        } else {
            if ($current !== null) {
                $current['items'][] = $item;
            }
        }
    }
    if ($current !== null) {
        $blocks[] = $current;
    }

    usort($blocks, static function (array $a, array $b): int {
        return $a['key'] <=> $b['key'];
    });

    $sorted = [];
    foreach ($blocks as $block) {
        foreach ($block['items'] as $bitem) {
            $sorted[] = $bitem;
        }
    }
    return $sorted;
}

/**
 * Remove duplicate discussion-area items from a lesson group's item list.
 *
 * When a course has both a Moodle Forum and a URL activity with the same
 * normalized name (e.g. "Lesson 2 Discussion Area"), only the higher-priority
 * type is kept. Preference order: forum > quiz > assign > page > url.
 *
 * @param array $items Flat item list.
 * @return array De-duplicated flat item list.
 */
function local_heyday_courseplayer_dedupe_lesson_items(array $items): array {
    $typerank = ['forum' => 0, 'quiz' => 1, 'assign' => 2, 'page' => 3, 'url' => 4];
    $seen   = [];
    $remove = [];

    foreach ($items as $i => $item) {
        if (($item['type'] ?? '') === 'heading') {
            continue;
        }
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }
        $raw  = core_text::strtolower(trim(format_string($cm->name)));
        $norm = (string)preg_replace('/\s+/', ' ', $raw);
        $norm = (string)preg_replace('/\s*area\s*$/', '', $norm);

        if (!isset($seen[$norm])) {
            $seen[$norm] = $i;
            continue;
        }

        $existidx  = $seen[$norm];
        $existcm   = local_heyday_courseplayer_item_cm($items[$existidx]);
        $existrank = $typerank[$existcm ? (string)$existcm->modname : ''] ?? 99;
        $newrank   = $typerank[(string)$cm->modname] ?? 99;

        if ($newrank < $existrank) {
            $remove[$existidx] = true;
            $seen[$norm] = $i;
        } else {
            $remove[$i] = true;
        }
    }

    if (empty($remove)) {
        return $items;
    }

    $result = [];
    foreach ($items as $i => $item) {
        if (!isset($remove[$i])) {
            $result[] = $item;
        }
    }
    return array_values($result);
}

/**
 * Collect lesson groups from course sections named Lesson 1, Lesson 2, etc.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param array<int,section_info> $sections Course sections.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return array<int,array<string,mixed>>
 */
function local_heyday_courseplayer_collect_lesson_groups(course_modinfo $modinfo, array $sections, stdClass $course, context_course $context): array {
    $groups = [];
    $usedsectionnums = [];
    $delegatedsectionnums = local_heyday_courseplayer_delegated_section_numbers($modinfo, (int)$course->id);

    $orderedsectionnums = array_map('intval', array_keys($sections));
    sort($orderedsectionnums, SORT_NUMERIC);

    foreach ($orderedsectionnums as $sectionnum) {
        if ((int)$sectionnum === 0 || !empty($usedsectionnums[$sectionnum]) || empty($sections[$sectionnum])) {
            continue;
        }

        $section = $sections[$sectionnum];

        // Delegated Moodle Subsection child sections are collected through
        // their parent section below. They must not become separate HeyDay
        // top-level lesson groups.
        if (isset($delegatedsectionnums[$sectionnum]) || local_heyday_courseplayer_is_delegated_section($section, (int)$course->id)) {
            continue;
        }
        if (!$section->visible) {
            continue;
        }

        $sectionname = get_section_name($course, $section);
        $islessonsection = local_heyday_courseplayer_is_lesson_section($sectionname);

        // Earlier builds only collected sections named "Lesson N". Native
        // Moodle editing can create sections named "Topic 12" or any custom
        // title, so include every visible non-shell section that contains
        // learner items. This keeps the HeyDay sidebar synchronized with the
        // Moodle + Adaptable course page after activities are added, renamed,
        // edited, or moved.
        if (!$islessonsection && local_heyday_courseplayer_is_shell_section($sectionname)) {
            continue;
        }

        $items = local_heyday_courseplayer_collect_section_items($modinfo, $sectionnum, $context, 0);
        $lessonno = local_heyday_courseplayer_lesson_number_from_name($sectionname);

        // Moodle Subsections can appear in modinfo as normal following course
        // sections on some Moodle 5 / Adaptable combinations. When a parent
        // Lesson section is followed by same-lesson support sections or Chapter
        // sections, fold those following sections into the parent group as
        // clickable child activities instead of rendering them as top-level
        // HeyDay lesson groups.
        if ($lessonno !== null) {
            foreach ($orderedsectionnums as $childnum) {
                if ($childnum <= $sectionnum || !empty($usedsectionnums[$childnum]) || empty($sections[$childnum])) {
                    continue;
                }

                $childsection = $sections[$childnum];
                if (!$childsection->visible) {
                    continue;
                }

                $childname = get_section_name($course, $childsection);
                if (local_heyday_courseplayer_is_shell_section($childname)) {
                    break;
                }

                $childlessonno = local_heyday_courseplayer_lesson_number_from_name($childname);
                if ($childlessonno !== null && $childlessonno !== $lessonno) {
                    break;
                }

                $isdelegatedchild = isset($delegatedsectionnums[$childnum]) || local_heyday_courseplayer_is_delegated_section($childsection, (int)$course->id);
                $isnamedchild = local_heyday_courseplayer_is_lesson_child_section_name($childname, $lessonno);

                if (!$isdelegatedchild && !$isnamedchild) {
                    // Stop at the next normal custom/topic section so unrelated
                    // Moodle sections do not get swallowed by this lesson.
                    break;
                }

                $childplain = core_text::strtolower(local_heyday_courseplayer_plain_menu_text($childname));
                $alreadycollected = false;
                foreach ($items as $existingitem) {
                    if (($existingitem['type'] ?? '') !== 'heading') {
                        continue;
                    }
                    $existingplain = core_text::strtolower(local_heyday_courseplayer_plain_menu_text((string)$existingitem['name']));
                    if ($existingplain !== '' && $existingplain === $childplain) {
                        $alreadycollected = true;
                        break;
                    }
                }
                if ($alreadycollected) {
                    $usedsectionnums[$childnum] = true;
                    continue;
                }

                $childitems = local_heyday_courseplayer_collect_section_items($modinfo, $childnum, $context, 1);
                if (!empty($childitems)) {
                    $heading = [
                        'type' => 'heading',
                        'name' => format_string($childname),
                        'depth' => 0,
                    ];

                    // When a child Moodle section is folded into the parent
                    // Lesson group, keep its heading clickable by routing it to
                    // the first actual activity inside that child section.
                    $headingtarget = local_heyday_courseplayer_first_heading_target($childitems);
                    if ($headingtarget) {
                        $heading['targetitem'] = $headingtarget;
                    }

                    $items[] = $heading;
                    $items = array_merge($items, $childitems);
                }
                $usedsectionnums[$childnum] = true;
            }
        }

        // Normalize sidebar order to the ed2go learner sequence and remove
        // any duplicate discussion-area items (e.g. both a Forum and a URL CM
        // named "Lesson N Discussion Area" in the same section).
        $items = local_heyday_courseplayer_dedupe_lesson_items(
            local_heyday_courseplayer_sort_lesson_items($items)
        );

        if (empty($items)) {
            continue;
        }

        $groups[] = [
            'name' => local_heyday_courseplayer_lesson_group_name(
                $sectionname,
                $sectionnum,
                $section,
                $modinfo,
                $course,
                $context
            ),
            'sectionnum' => $sectionnum,
            'items' => $items,
        ];
    }

    return $groups;
}

/**
 * Get the course module from a navigation item.
 *
 * @param array<string,mixed>|null $item Navigation item.
 * @return cm_info|null
 */
function local_heyday_courseplayer_item_cm(?array $item): ?cm_info {
    if (!$item) {
        return null;
    }
    if (in_array(($item['type'] ?? ''), ['cm', 'lessonpage', 'pretest', 'finalexam', 'resource', 'lessonquiz'], true)) {
        return $item['cm'];
    }
    return null;
}

/**
 * Get learner-facing title from a navigation item.
 *
 * @param array<string,mixed> $item Navigation item.
 * @return string
 */
function local_heyday_courseplayer_item_title(array $item): string {
    if (($item['type'] ?? '') === 'lessonpage') {
        return format_string($item['page']->title);
    }

    $cm = local_heyday_courseplayer_item_cm($item);
    if ($cm) {
        return format_string($cm->name, true, ['context' => $cm->context]);
    }

    return '';
}

/**
 * Whether an item is openable for the current learner.
 *
 * @param array<string,mixed> $item Navigation item.
 * @return bool
 */
function local_heyday_courseplayer_item_available(array $item): bool {
    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm) {
        return false;
    }

    // Learners still respect Moodle availability/restriction rules. Course
    // editors/managers, however, must be able to preview and click newly added
    // activities inside the HeyDay shell even when a parent Moodle Subsection
    // or future release rule makes cm->available false in modinfo.
    try {
        $coursecontext = context_course::instance((int)$cm->course);
        if (has_capability('moodle/course:update', $coursecontext) || has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
            return !empty($cm->uservisible) || !empty($cm->visible);
        }
    } catch (Throwable $e) {
        // Fall back to the learner rule below.
    }

    return $cm->available && $cm->uservisible;
}


/**
 * Return the first real activity target from a group of collected child items.
 *
 * Heading rows represent Moodle Subsection containers. They are not content by
 * themselves, so a clickable heading should open the first descendant Page,
 * Quiz, Lesson page, or other Moodle activity inside that subsection.
 *
 * @param array<int,array<string,mixed>> $items Child navigation items.
 * @return array<string,mixed>|null First descendant activity item.
 */
function local_heyday_courseplayer_first_heading_target(array $items): ?array {
    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'heading') {
            if (!empty($item['targetitem']) && is_array($item['targetitem'])) {
                if (local_heyday_courseplayer_item_clickable($item['targetitem'])) {
                    return $item['targetitem'];
                }
            }
            continue;
        }

        if (local_heyday_courseplayer_item_cm($item) && local_heyday_courseplayer_item_clickable($item)) {
            return $item;
        }
    }

    foreach ($items as $item) {
        if (($item['type'] ?? '') !== 'heading' && local_heyday_courseplayer_item_cm($item)) {
            return $item;
        }
    }

    return null;
}

/**
 * Whether a sidebar row should be rendered as a clickable link.
 *
 * @param array<string,mixed> $item Navigation item.
 * @return bool True when the row can be linked.
 */
function local_heyday_courseplayer_item_clickable(array $item): bool {
    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm) {
        return false;
    }

    // Teachers/managers need reliable preview links while building the course
    // structure. Moodle Subsection children can inherit availability state from
    // their parent container, so always expose a shell link for course editors.
    try {
        $coursecontext = context_course::instance((int)$cm->course);
        if (has_capability('moodle/course:update', $coursecontext)
                || has_capability('moodle/course:viewhiddenactivities', $coursecontext)) {
            return true;
        }
    } catch (Throwable $e) {
        // Fall through to the learner availability rule.
    }

    return local_heyday_courseplayer_item_available($item);
}

/**
 * Build courseplayer URL for a navigation item.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Navigation item.
 * @return moodle_url
 */
function local_heyday_courseplayer_item_url(stdClass $course, array $item): moodle_url {
    global $CFG;
    $cm = local_heyday_courseplayer_item_cm($item);
    $params = [];
    if ($cm) {
        $params['cmid'] = $cm->id;
    }
    if (($item['type'] ?? '') === 'lessonpage') {
        $params['pageid'] = (int)$item['pageid'];
    }

    // Prefer the custom local_heyday_pretest view when present so the
    // dedicated pretest shell (local/heyday_pretest/view.php) is used instead
    // of the raw Moodle quiz page.
    if (($item['type'] ?? '') === 'pretest') {
        return local_heyday_courseplayer_url($course, 'pretest', $params);
    }

    if (($item['type'] ?? '') === 'finalexam') {
        return local_heyday_courseplayer_url($course, 'finalexam', $params);
    }
    if (($item['type'] ?? '') === 'resource') {
        return local_heyday_courseplayer_url($course, 'resources', $params);
    }

    if (($item['type'] ?? '') === 'lessonquiz') {
        return local_heyday_courseplayer_url($course, 'lessonquiz', $params);
    }

    return local_heyday_courseplayer_url($course, 'lesson', $params);
}

/**
 * Resolve the installed Heyday Pretest plugin URL for a quiz cm.
 *
 * @param cm_info|null $cm Course module.
 * @return moodle_url|null
 */
function local_heyday_courseplayer_pretest_plugin_url(?cm_info $cm): ?moodle_url {
    global $CFG;

    if (!$cm || $cm->modname !== 'quiz') {
        return null;
    }

    $viewpath = $CFG->dirroot . '/local/heyday_pretest/view.php';
    if (!is_file($viewpath) || !is_readable($viewpath)) {
        return null;
    }

    return new moodle_url('/local/heyday_pretest/view.php', ['cmid' => $cm->id]);
}

/**
 * Compare two navigation items.
 *
 * @param array<string,mixed> $itema First item.
 * @param array<string,mixed> $itemb Second item.
 * @return bool
 */
function local_heyday_courseplayer_items_same(array $itema, array $itemb): bool {
    if (($itema['type'] ?? '') !== ($itemb['type'] ?? '')) {
        return false;
    }

    $cma = local_heyday_courseplayer_item_cm($itema);
    $cmb = local_heyday_courseplayer_item_cm($itemb);
    if (!$cma || !$cmb || (int)$cma->id !== (int)$cmb->id) {
        return false;
    }

    if (($itema['type'] ?? '') === 'lessonpage') {
        return (int)$itema['pageid'] === (int)$itemb['pageid'];
    }

    return true;
}

/**
 * Get the first available URL for a lesson group.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $group Lesson group.
 * @return moodle_url|null
 */
function local_heyday_courseplayer_group_url(stdClass $course, array $group): ?moodle_url {
    foreach ($group['items'] as $item) {
        if (($item['type'] ?? '') === 'heading') {
            continue;
        }
        if (local_heyday_courseplayer_item_available($item)) {
            return local_heyday_courseplayer_item_url($course, $item);
        }
    }
    return null;
}

/**
 * First CM from a group.
 *
 * @param array<string,mixed> $group Lesson group.
 * @return cm_info|null
 */
function local_heyday_courseplayer_group_first_cm(array $group): ?cm_info {
    foreach ($group['items'] as $item) {
        $cm = local_heyday_courseplayer_item_cm($item);
        if ($cm) {
            return $cm;
        }
    }
    return null;
}

/**
 * Check if active item belongs to a group.
 *
 * @param array<string,mixed> $group Lesson group.
 * @param array<string,mixed>|null $activeitem Active item.
 * @return bool
 */
function local_heyday_courseplayer_group_is_active(array $group, ?array $activeitem): bool {
    if (!$activeitem) {
        return false;
    }
    foreach ($group['items'] as $item) {
        if (($item['type'] ?? '') === 'heading') {
            continue;
        }
        if (local_heyday_courseplayer_items_same($item, $activeitem)) {
            return true;
        }
    }
    return false;
}

/**
 * Calculate group completion state.
 *
 * @param completion_info $completion Completion object.
 * @param array<string,mixed> $group Lesson group.
 * @return array<string,string>
 */
function local_heyday_courseplayer_group_status(completion_info $completion, array $group): array {
    global $USER;
    $tracked = [];
    $available = 0;
    $completed = 0;

    foreach ($group['items'] as $item) {
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }
        if (local_heyday_courseplayer_item_available($item)) {
            $available++;
        }
        if ($completion->is_enabled($cm) && empty($tracked[$cm->id])) {
            $tracked[$cm->id] = true;
            $data = $completion->get_data($cm, false, $USER->id);
            if (!empty($data->completionstate)) {
                $completed++;
            }
        }
    }

    if ($available === 0) {
        return ['class' => 'locked', 'icon' => '🔒', 'label' => get_string('locked', 'local_heyday_courseplayer')];
    }
    if (!empty($tracked) && $completed >= count($tracked)) {
        return ['class' => 'completed', 'icon' => '✓', 'label' => get_string('completed', 'local_heyday_courseplayer')];
    }
    return ['class' => 'inprogress', 'icon' => '', 'label' => get_string('inprogress', 'local_heyday_courseplayer')];
}

/**
 * Render one lesson group's items as a nested ed2go-style accordion.
 *
 * The collected items are a flat list tagged with a 'depth'. Each heading
 * (a chapter or Moodle Subsection) becomes its own nested <details> that is
 * only `open` when it contains the active item, and sibling headings at the
 * same level share an exclusive accordion `name` so opening one collapses the
 * others. The result: only the current lesson path stays expanded, siblings
 * stay visible but collapsed, and there is no JavaScript or sidebar rebuild.
 *
 * @param array<int,array<string,mixed>> $items Flat depth-tagged group items.
 * @param stdClass $course Course record.
 * @param completion_info $completion Completion object.
 * @param array<string,mixed>|null $activeitem Active navigation item.
 * @param bool $caneditcourse Whether the viewer can edit (preview unlocks items).
 * @param string $groupkey Stable key used to scope the accordion `name` per group.
 * @return string Sidebar HTML.
 */
function local_heyday_courseplayer_render_sidebar_items(
    array $items,
    stdClass $course,
    completion_info $completion,
    ?array $activeitem,
    bool $caneditcourse,
    string $groupkey
): string {
    $count = count($items);

    // Precompute which headings contain the active item, so the whole current
    // path (chapter, and any nested subsection) is opened on load.
    $containsactive = [];
    if ($activeitem) {
        foreach ($items as $i => $item) {
            if (($item['type'] ?? '') !== 'heading') {
                continue;
            }
            $d = (int)$item['depth'];
            for ($j = $i + 1; $j < $count; $j++) {
                if ((int)$items[$j]['depth'] <= $d) {
                    break;
                }
                if (($items[$j]['type'] ?? '') === 'heading') {
                    continue;
                }
                if (local_heyday_courseplayer_items_same($items[$j], $activeitem)) {
                    $containsactive[$i] = true;
                    break;
                }
            }
        }
    }

    $html = '';
    $openstack = []; // Depths of currently open subsection <details>.

    foreach ($items as $i => $item) {
        $depth = (int)$item['depth'];

        // A new entry at depth d ends any open subsection at depth >= d.
        while (!empty($openstack) && end($openstack) >= $depth) {
            $html .= '</div></details>';
            array_pop($openstack);
        }

        if (($item['type'] ?? '') === 'heading') {
            $open = !empty($containsactive[$i]);
            $headingtarget = (!empty($item['targetitem']) && is_array($item['targetitem'])) ? $item['targetitem'] : null;
            $headingactive = $headingtarget && $activeitem && local_heyday_courseplayer_items_same($headingtarget, $activeitem);

            $detailsclasses = ['heyday-subsection-group', 'depth-' . $depth];
            if ($open) {
                $detailsclasses[] = 'is-open-path';
            }
            $summaryclasses = ['heyday-subsection-title', 'depth-' . $depth];
            if ($headingactive) {
                $summaryclasses[] = 'is-current';
            }

            // Exclusive accordion per (group, depth): sibling headings collapse
            // each other; a nested child uses a different name than its parent.
            $name = 'heyday-acc-' . $groupkey . '-d' . $depth;

            $headingtarget = (!empty($item['targetitem']) && is_array($item['targetitem'])) ? $item['targetitem'] : null;
            $headingtargeturl = $headingtarget && local_heyday_courseplayer_item_clickable($headingtarget)
                ? local_heyday_courseplayer_item_url($course, $headingtarget)->out(false)
                : '';
            $headinglocked = $headingtargeturl === '';
            if ($headinglocked) {
                $summaryclasses[] = 'is-disabled';
            }

            $html .= '<details class="' . s(implode(' ', $detailsclasses)) . '" name="' . s($name) . '"' . ($open ? ' open' : '') . '>';
            $html .= '<summary class="' . s(implode(' ', $summaryclasses)) . '">';
            $html .= '<span class="heyday-current-arrow" aria-hidden="true"></span>';

            if ($headingtargeturl) {
                $html .= '<a class="heyday-subsection-title-link" href="' . $headingtargeturl . '">';
                $html .= '<span class="heyday-subsection-title-text">' . $item['name'] . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span class="heyday-subsection-title-text">' . $item['name'] . '</span>';
            }

            $html .= '</summary>';
            $html .= '<div class="heyday-subsection-items">';
            $openstack[] = $depth;
            continue;
        }

        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }

        $isactive = $activeitem && local_heyday_courseplayer_items_same($item, $activeitem);
        $islocked = !local_heyday_courseplayer_item_clickable($item);
        if ($caneditcourse) {
            // Editor preview: keep every visible sidebar activity clickable.
            $islocked = false;
        }
        $status = local_heyday_courseplayer_completion_status($completion, $cm);
        $itemclasses = ['heyday-lesson-item', 'depth-' . $depth, 'is-' . $status['class']];
        if (($item['type'] ?? '') === 'lessonpage') {
            $itemclasses[] = 'is-lesson-page';
        }
        if ($isactive) {
            $itemclasses[] = 'is-current';
        }
        if ($islocked) {
            $itemclasses[] = 'is-locked';
        }
        $itemtitle = local_heyday_courseplayer_item_title($item);

        if ($islocked) {
            $html .= '<div class="' . s(implode(' ', $itemclasses)) . '">';
            $html .= '<span class="heyday-current-arrow" aria-hidden="true"></span>';
            $html .= '<span class="heyday-lesson-text"><span>' . $itemtitle . '</span>';
            $html .= '<small class="heyday-release-note">' . s(local_heyday_courseplayer_locked_message($cm)) . '</small></span>';
            $html .= '<span class="heyday-status-icon locked" aria-hidden="true">🔒</span>';
            $html .= '</div>';
        } else {
            $itemurl = local_heyday_courseplayer_item_url($course, $item);
            $statusicon = $status['icon'] !== '' ? s($status['icon'])
                : ($status['class'] === 'inprogress' ? '<span class="heyday-mini-dot"></span>' : '');
            $html .= '<a class="' . s(implode(' ', $itemclasses)) . '" href="' . $itemurl->out(false) . '">';
            $html .= '<span class="heyday-current-arrow" aria-hidden="true"></span>';
            $html .= '<span class="heyday-lesson-text"><span>' . $itemtitle . '</span>';
            $html .= '<small>' . s($status['label']) . '</small></span>';
            $html .= '<span class="heyday-status-icon ' . s($status['class']) . '" aria-hidden="true">' . $statusicon . '</span>';
            $html .= '</a>';
        }
    }

    while (!empty($openstack)) {
        $html .= '</div></details>';
        array_pop($openstack);
    }

    return $html;
}

/**
 * Find first available item.
 *
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @return array<string,mixed>|null
 */
function local_heyday_courseplayer_first_available_item(array $lessongroups): ?array {
    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') === 'heading') {
                continue;
            }
            if (local_heyday_courseplayer_item_available($item)) {
                return $item;
            }
        }
    }
    return null;
}

/**
 * Flatten all lesson and special items in sequence order.
 *
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<int,array<string,mixed>> $specialitems Special items.
 * @return array<int,array<string,mixed>>
 */
function local_heyday_courseplayer_flat_items(array $lessongroups, array $specialitems = []): array {
    $items = [];
    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') !== 'heading') {
                $items[] = $item;
            }
        }
    }
    foreach ($specialitems as $item) {
        $items[] = $item;
    }
    return $items;
}

/**
 * Find requested item from cmid/pageid.
 *
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<int,array<string,mixed>> $specialitems Special items.
 * @param cm_info|null $requestedcm Requested cm.
 * @param int $requestedpageid Requested lesson page id.
 * @return array<string,mixed>|null
 */
function local_heyday_courseplayer_find_requested_item(array $lessongroups, array $specialitems, ?cm_info $requestedcm, int $requestedpageid): ?array {
    if (!$requestedcm) {
        return null;
    }

    $firstmatchingcm = null;
    foreach (local_heyday_courseplayer_flat_items($lessongroups, $specialitems) as $item) {
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm || (int)$cm->id !== (int)$requestedcm->id) {
            continue;
        }
        if (!$firstmatchingcm) {
            $firstmatchingcm = $item;
        }
        if (($item['type'] ?? '') === 'lessonpage' && $requestedpageid > 0 && (int)$item['pageid'] === $requestedpageid) {
            return $item;
        }
        if (($item['type'] ?? '') !== 'lessonpage' && $requestedpageid <= 0) {
            return $item;
        }
    }
    return $firstmatchingcm;
}

/**
 * Find next available navigation item after the active item.
 *
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<string,mixed> $activeitem Active item.
 * @param array<int,array<string,mixed>> $specialitems Special items.
 * @return array<string,mixed>|null
 */
function local_heyday_courseplayer_next_available_item(array $lessongroups, array $activeitem, array $specialitems = []): ?array {
    $foundactive = false;
    foreach (local_heyday_courseplayer_flat_items($lessongroups, $specialitems) as $item) {
        if (!local_heyday_courseplayer_item_available($item)) {
            continue;
        }
        if ($foundactive) {
            return $item;
        }
        if (local_heyday_courseplayer_items_same($item, $activeitem)) {
            $foundactive = true;
        }
    }
    return null;
}

/**
 * Find parent lesson group name for an item.
 *
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<string,mixed> $activeitem Active item.
 * @return string
 */
function local_heyday_courseplayer_active_group_name(array $lessongroups, array $activeitem): string {
    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') === 'heading') {
                continue;
            }
            if (local_heyday_courseplayer_items_same($item, $activeitem)) {
                return $group['name'];
            }
        }
    }
    if (($activeitem['type'] ?? '') === 'pretest') {
        return get_string('pretest', 'local_heyday_courseplayer');
    }
    if (($activeitem['type'] ?? '') === 'finalexam') {
        return get_string('finalexam', 'local_heyday_courseplayer');
    }
    return '';
}

/**
 * Strip duplicate leading heading from editor HTML.
 *
 * @param string $html Raw HTML.
 * @param string $title Page title.
 * @return string
 */
function local_heyday_courseplayer_strip_duplicate_heading(string $html, string $title): string {
    $normalizetitle = trim(core_text::strtolower(strip_tags($title)));
    if ($normalizetitle === '') {
        return $html;
    }

    if (preg_match('/^\s*<h[1-6][^>]*>(.*?)<\/h[1-6]>\s*/is', $html, $matches)) {
        $headingtext = trim(core_text::strtolower(strip_tags($matches[1])));
        if ($headingtext === $normalizetitle) {
            return preg_replace('/^\s*<h[1-6][^>]*>.*?<\/h[1-6]>\s*/is', '', $html, 1);
        }
    }
    return $html;
}

/**
 * Render Moodle Page activity content inline.
 *
 * @param cm_info $cm Course module.
 * @return string|null
 */
function local_heyday_courseplayer_render_page_content(cm_info $cm): ?string {
    global $DB;

    if ($cm->modname !== 'page') {
        return null;
    }

    $page = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$page || trim((string)$page->content) === '') {
        return null;
    }

    $content = local_heyday_courseplayer_strip_duplicate_heading((string)$page->content, (string)$cm->name);
    $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', $cm->context->id, 'mod_page', 'content', $page->revision);

    return format_text($content, $page->contentformat, ['context' => $cm->context, 'overflowdiv' => true]);
}

/**
 * Render a Moodle Lesson internal page inline.
 *
 * @param cm_info $cm Lesson course module.
 * @param stdClass $lessonpage Lesson page record.
 * @return string|null
 */
function local_heyday_courseplayer_render_lesson_page_content(cm_info $cm, stdClass $lessonpage): ?string {
    if ($cm->modname !== 'lesson' || trim((string)$lessonpage->contents) === '') {
        return null;
    }

    $content = local_heyday_courseplayer_strip_duplicate_heading((string)$lessonpage->contents, (string)$lessonpage->title);
    $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', $cm->context->id, 'mod_lesson', 'page_contents', $lessonpage->id);

    return html_writer::div(format_text($content, $lessonpage->contentsformat, [
        'context' => $cm->context,
        'overflowdiv' => true,
    ]), 'heyday-inline-lesson-content');
}

/**
 * Render a Moodle H5P activity inline.
 *
 * @param cm_info $cm Course module.
 * @return string|null
 */
function local_heyday_courseplayer_render_h5p_activity_content(cm_info $cm): ?string {
    if ($cm->modname !== 'h5pactivity' || !class_exists('\mod_h5pactivity\local\manager')) {
        return null;
    }

    try {
        $manager = \mod_h5pactivity\local\manager::create_from_coursemodule($cm);
        $moduleinstance = $manager->get_instance();
        $context = $manager->get_context();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $file = reset($files);
        if (!$file) {
            return null;
        }

        $fileurl = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false);

        $factory = new \core_h5p\factory();
        $core = $factory->get_core();
        $config = \core_h5p\helper::decode_display_options($core, $moduleinstance->displayoptions);

        $extraactions = [];
        if ($manager->can_view_all_attempts() && $manager->is_tracking_enabled()) {
            $extraactions[] = new action_link(
                new \moodle_url('/mod/h5pactivity/report.php', ['id' => $cm->id]),
                get_string('viewattempts', 'mod_h5pactivity', $manager->count_attempts()),
                null,
                null,
                new pix_icon('i/chartbar', '', 'core')
            );
        }

        $course = get_course($cm->course);
        $manager->set_module_viewed($course);

        return \core_h5p\player::display($fileurl->out(false), $config, true, 'mod_h5pactivity', true, $extraactions);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get activity type label.
 *
 * @param cm_info $cm Course module.
 * @return string
 */
function local_heyday_courseplayer_activity_type_label(cm_info $cm): string {
    try {
        return get_string('modulename', 'mod_' . $cm->modname);
    } catch (Throwable $e) {
        return ucfirst($cm->modname);
    }
}

/**
 * Render a generic activity card with the normal Moodle activity URL.
 *
 * @param cm_info $cm Course module.
 * @param string $buttontext Button text.
 * @param string $message Message.
 * @return string
 */
function local_heyday_courseplayer_activity_card(cm_info $cm, string $buttontext, string $message, ?moodle_url $actionurl = null): string {
    $output = html_writer::start_div('heyday-activity-fallback');
    $output .= html_writer::tag('div', s(local_heyday_courseplayer_activity_type_label($cm)), ['class' => 'heyday-activity-type']);
    $output .= html_writer::tag('p', $message);
    $actionurl = $actionurl ?: $cm->url;
    if ($actionurl) {
        $output .= html_writer::link($actionurl, $buttontext, ['class' => 'heyday-primary-button']);
    }
    $output .= html_writer::end_div();
    return $output;
}

/**
 * Render an active lesson/pretest/final item.
 *
 * @param array<string,mixed> $item Navigation item.
 * @return string
 */
function local_heyday_courseplayer_render_item_content(stdClass $course, array $item): string {
    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm) {
        return '';
    }

    if (($item['type'] ?? '') === 'lessonpage') {
        $content = local_heyday_courseplayer_render_lesson_page_content($cm, $item['page']);
        if ($content !== null) {
            return $content;
        }
    }

    if ($cm->modname === 'h5pactivity') {
        $content = local_heyday_courseplayer_render_h5p_activity_content($cm);
        if ($content !== null) {
            return $content;
        }
    }

    if ($cm->modname === 'forum') {
        return local_heyday_courseplayer_render_discussion_detail($course, $cm);
    }

    if ($cm->modname === 'assign') {
        return local_heyday_courseplayer_render_assignment_card($course, $item);
    }

    // Moodle Page activities are first-class HeyDay content. Render them
    // inline for normal lesson items, resources, pretests, finals, and direct
    // cmid requests instead of falling back to Moodle's native module page.
    $pagecontent = local_heyday_courseplayer_render_page_content($cm);
    if ($pagecontent !== null) {
        return $pagecontent;
    }

    $buttontext = get_string('openactivity', 'local_heyday_courseplayer');
    $message = get_string('normalactivityscreen', 'local_heyday_courseplayer');
    $actionurl = local_heyday_courseplayer_item_url($course, $item);
    if (($item['type'] ?? '') === 'pretest') {
        $buttontext = get_string('openpretest', 'local_heyday_courseplayer');
        $message = get_string('interactiveactivityscreen', 'local_heyday_courseplayer');
        // Open the native quiz page directly. heydaynative=1 bypasses the
        // before_http_headers hook so the user reaches the quiz attempt pages.
        if ($cm->url) {
            $nativeurl = new moodle_url($cm->url);
            $nativeurl->param('heydaynative', 1);
            $actionurl = $nativeurl;
        }
    } else if (($item['type'] ?? '') === 'finalexam') {
        $buttontext = get_string('openfinalexam', 'local_heyday_courseplayer');
        $message = get_string('interactiveactivityscreen', 'local_heyday_courseplayer');
    } else if (($item['type'] ?? '') === 'resource') {
        $buttontext = get_string('openresource', 'local_heyday_courseplayer');
    }

    return local_heyday_courseplayer_activity_card($cm, $buttontext, $message, $actionurl);
}

/**
 * Find first visible course module matching a callback.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @param callable $callback Test callback.
 * @return cm_info|null
 */
function local_heyday_courseplayer_find_cm(course_modinfo $modinfo, context_course $context, callable $callback): ?cm_info {
    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
            continue;
        }
        if ($callback($cm)) {
            return $cm;
        }
    }
    return null;
}

/**
 * Find Final Exam module.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @return cm_info|null
 */
function local_heyday_courseplayer_find_final_exam_cm(course_modinfo $modinfo, context_course $context): ?cm_info {
    $fallback = null;
    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_courseplayer_should_show_cm($cm, $context) || !local_heyday_courseplayer_is_final_exam_cm($cm)) {
            continue;
        }
        if ($cm->modname === 'quiz') {
            return $cm;
        }
        if (!$fallback) {
            $fallback = $cm;
        }
    }
    return $fallback;
}

/**
 * Find Pretest module.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @return cm_info|null
 */
function local_heyday_courseplayer_find_pretest_cm(course_modinfo $modinfo, context_course $context): ?cm_info {
    $fallback = null;
    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_courseplayer_should_show_cm($cm, $context) || !local_heyday_courseplayer_is_pretest_cm($cm)) {
            continue;
        }
        if ($cm->modname === 'quiz') {
            return $cm;
        }
        if (!$fallback) {
            $fallback = $cm;
        }
    }
    return $fallback;
}

/**
 * Collect resources.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param array<int,section_info> $sections Course sections.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return array<int,array<string,mixed>>
 */
function local_heyday_courseplayer_collect_resources(course_modinfo $modinfo, array $sections, stdClass $course, context_course $context): array {
    $items = [];
    $resourcecms = [];

    foreach ($sections as $sectionnum => $section) {
        if ((int)$sectionnum === 0) {
            continue;
        }
        $sectionname = get_section_name($course, $section);
        if (!local_heyday_courseplayer_is_resources_section($sectionname)) {
            continue;
        }
        foreach (($modinfo->sections[$sectionnum] ?? []) as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (local_heyday_courseplayer_should_show_cm($cm, $context) && !local_heyday_courseplayer_is_final_exam_cm($cm)) {
                $resourcecms[$cm->id] = $cm;
            }
        }
    }

    if (empty($resourcecms)) {
        foreach ($modinfo->get_cms() as $cm) {
            if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
                continue;
            }
            if (in_array($cm->modname, ['resource', 'folder', 'url', 'book'], true) || preg_match('/\bresource/i', $cm->name)) {
                $resourcecms[$cm->id] = $cm;
            }
        }
    }

    foreach ($resourcecms as $cm) {
        $items[] = ['type' => 'resource', 'cm' => $cm, 'depth' => 0];
    }

    return $items;
}

/**
 * Collect discussion/forum modules.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @return array<int,cm_info>
 */
function local_heyday_courseplayer_collect_discussions(course_modinfo $modinfo, context_course $context): array {
    $items = [];
    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
            continue;
        }
        if ($cm->modname === 'forum' || preg_match('/\bdiscussion\b/i', $cm->name)) {
            $items[] = $cm;
        }
    }
    return $items;
}

/**
 * Course completion summary from unique tracked course modules.
 *
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @return array<string,int>
 */
function local_heyday_courseplayer_completion_summary(completion_info $completion, course_modinfo $modinfo, context_course $context): array {
    global $USER;
    $tracked = 0;
    $completed = 0;

    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_courseplayer_should_show_cm($cm, $context)) {
            continue;
        }
        if (!$completion->is_enabled($cm)) {
            continue;
        }
        $tracked++;
        $data = $completion->get_data($cm, false, $USER->id);
        if (!empty($data->completionstate)) {
            $completed++;
        }
    }

    $percent = $tracked > 0 ? (int)round(($completed / $tracked) * 100) : 0;
    return ['tracked' => $tracked, 'completed' => $completed, 'percent' => $percent];
}

/**
 * Find next incomplete available item.
 *
 * @param completion_info $completion Completion object.
 * @param array<int,array<string,mixed>> $items Items.
 * @return array<string,mixed>|null
 */
function local_heyday_courseplayer_next_incomplete(completion_info $completion, array $items): ?array {
    global $USER;
    foreach ($items as $item) {
        if (!local_heyday_courseplayer_item_available($item)) {
            continue;
        }
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }
        if (!$completion->is_enabled($cm)) {
            return $item;
        }
        $data = $completion->get_data($cm, false, $USER->id);
        if (empty($data->completionstate)) {
            return $item;
        }
    }
    return null;
}

/**
 * Render a lock card.
 *
 * @param string $title Title.
 * @param string $message Message.
 * @return string
 */
function local_heyday_courseplayer_render_locked_card(string $title, string $message): string {
    $output = html_writer::start_div('heyday-locked-card');
    $output .= html_writer::div('🔒', 'heyday-locked-icon', ['aria-hidden' => 'true']);
    $output .= html_writer::tag('h2', s($title));
    $output .= html_writer::tag('p', s($message));
    $output .= html_writer::end_div();
    return $output;
}

/**
 * Get the first Moodle course overview image URL.
 *
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return string
 */
function local_heyday_courseplayer_course_image_url(stdClass $course, context_course $context): string {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder, id', false);

    foreach ($files as $file) {
        $mimetype = (string)$file->get_mimetype();
        if (strpos($mimetype, 'image/') !== 0) {
            continue;
        }

        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'course',
            'overviewfiles',
            0,
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
        return $url->out(false);
    }

    return '';
}

/**
 * Get learner course score percentage from the Moodle course grade item.
 *
 * @param stdClass $course Course record.
 * @return string
 */
function local_heyday_courseplayer_course_score(stdClass $course): string {
    global $DB, $USER;
    static $cache = [];

    $cachekey = 'course:' . (int)$course->id . ':user:' . (int)$USER->id;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    $gradeitem = $DB->get_record('grade_items', [
        'courseid' => $course->id,
        'itemtype' => 'course',
    ], '*', IGNORE_MISSING);

    if (!$gradeitem) {
        return $cache[$cachekey] = '--';
    }

    $grade = $DB->get_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $USER->id,
    ], '*', IGNORE_MISSING);

    if (!$grade || $grade->finalgrade === null || $grade->finalgrade === '') {
        return $cache[$cachekey] = '--';
    }

    $finalgrade = (float)$grade->finalgrade;
    $grademax = (float)$gradeitem->grademax;

    if ($grademax > 0) {
        return $cache[$cachekey] = (string)((int)round(($finalgrade / $grademax) * 100)) . '%';
    }

    return $cache[$cachekey] = format_float($finalgrade, 1);
}

/**
 * Render home dashboard.
 *
 * @param stdClass $course Course record.
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<int,array<string,mixed>> $sequenceitems Sequence items.
 * @return string
 */
function local_heyday_courseplayer_render_home(stdClass $course, completion_info $completion, course_modinfo $modinfo, context_course $context, array $lessongroups, array $sequenceitems): string {
    $summary = local_heyday_courseplayer_completion_summary($completion, $modinfo, $context);
    $score = local_heyday_courseplayer_course_score($course);
    $next = local_heyday_courseplayer_next_incomplete($completion, $sequenceitems);
    $nexturl = local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => 'syllabus']);
    $nexttitle = $next ? local_heyday_courseplayer_item_title($next) : get_string('lessons', 'local_heyday_courseplayer');
    $bannerurl = local_heyday_courseplayer_course_image_url($course, $context);

    $herostyle = '';
    if ($bannerurl !== '') {
        $herostyle = 'background-image: linear-gradient(90deg, rgba(0,0,0,.72) 0%, rgba(0,0,0,.48) 48%, rgba(244,247,250,.92) 78%, #f4f7fa 100%), url(' . $bannerurl . ');';
    }

    $nextpercent = 0;
    $nextcm = $next ? local_heyday_courseplayer_item_cm($next) : null;
    if ($nextcm && $completion->is_enabled($nextcm)) {
        $status = local_heyday_courseplayer_completion_status($completion, $nextcm);
        $nextpercent = ($status['class'] === 'completed') ? 100 : 0;
    }

    $output = html_writer::start_div('heyday-home-dashboard');

    $heroattrs = ['class' => 'heyday-home-hero'];
    if ($herostyle !== '') {
        $heroattrs['style'] = $herostyle;
    }

    $output .= html_writer::start_tag('section', $heroattrs);
    $output .= html_writer::start_div('heyday-home-hero-title');
    $output .= html_writer::tag('h2', format_string($course->fullname));
    if (!empty($course->shortname)) {
        $output .= html_writer::div('Section: ' . s($course->shortname), 'heyday-home-section-code');
    }
    $output .= html_writer::end_div();

    $output .= html_writer::start_div('heyday-home-meters');
    $output .= html_writer::start_div('heyday-home-meter');
    $output .= html_writer::div(html_writer::span(s($summary['percent'] . '%')), 'heyday-home-meter-ring', ['style' => '--meter-value:' . (int)$summary['percent'] . ';']);
    $output .= html_writer::div('complete', 'heyday-home-meter-label');
    $output .= html_writer::end_div();

    $scoresurl = local_heyday_courseplayer_url($course, 'scores');
    $scorehtml  = html_writer::div(html_writer::span(s($score)), 'heyday-home-meter-ring is-score', ['style' => '--meter-value:0;']);
    $scorehtml .= html_writer::div('score', 'heyday-home-meter-label');
    $output .= html_writer::link($scoresurl, $scorehtml, [
        'class'      => 'heyday-home-meter heyday-home-meter-link heyday-home-score-link',
        'title'      => get_string('scores', 'local_heyday_courseplayer'),
        'aria-label' => 'View course scores',
    ]);
    $output .= html_writer::end_div();
    $output .= html_writer::end_tag('section');

    $output .= html_writer::start_tag('section', ['class' => 'heyday-home-content']);
    $output .= html_writer::tag('h2', 'Welcome!', ['class' => 'heyday-home-welcome']);

    $output .= html_writer::start_div('heyday-home-next-card');
    $output .= html_writer::start_div('heyday-home-next-main');
    $output .= html_writer::tag('h3', s($nexttitle));
    $output .= html_writer::start_div('heyday-home-progress-row');
    $output .= html_writer::start_div('heyday-home-progress-track');
    $output .= html_writer::div('', 'heyday-home-progress-fill', ['style' => 'width:' . (int)$nextpercent . '%;']);
    $output .= html_writer::end_div();
    $output .= html_writer::span(s($nextpercent . '% complete'));
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    $output .= html_writer::start_div('heyday-home-next-action');
    $output .= html_writer::div('Next activity', 'heyday-home-next-label');
    $output .= html_writer::div(s($nexttitle), 'heyday-home-next-name');
    $output .= html_writer::link($nexturl, get_string('continue', 'local_heyday_courseplayer'), ['class' => 'heyday-home-continue-button']);
    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    $output .= html_writer::end_tag('section');
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render scores page.
 *
 * @param stdClass $course Course record.
 * @param course_modinfo $modinfo Course modinfo.
 * @return string
 */
function local_heyday_courseplayer_render_scores(stdClass $course, course_modinfo $modinfo): string {
    global $CFG, $DB, $USER;

    $search = trim(optional_param('q', '', PARAM_TEXT));
    $creditonly = optional_param('creditonly', 0, PARAM_BOOL);
    $pagenum = optional_param('p', 1, PARAM_INT);

    $formatdate = static function($timestamp): string {
        if (empty($timestamp)) {
            return '';
        }
        $timezone = core_date::get_user_timezone_object();
        $date = new DateTime('@' . (int)$timestamp);
        $date->setTimezone($timezone);
        return $date->format('n/j/Y');
    };

    $cleannumber = static function($number): string {
        if ($number === null || $number === '') {
            return '--';
        }
        $number = (float)$number;
        if (floor($number) == $number) {
            return (string)(int)$number;
        }
        return rtrim(rtrim(number_format($number, 2), '0'), '.');
    };

    $percentage = static function($grade, $maxgrade): ?int {
        if ($grade === null || $grade === '' || empty($maxgrade)) {
            return null;
        }
        return (int)round(((float)$grade / (float)$maxgrade) * 100);
    };

    $alloweditem = static function(string $itemname, string $itemmodule = ''): bool {
        $name = trim($itemname);
        if (preg_match('/^pretest$/i', $name)) {
            return true;
        }
        if (preg_match('/^lesson\s*[0-9]+\s*[:\-]?\s*quiz$/i', $name)) {
            return true;
        }
        if ($itemmodule === 'assign' && preg_match('/\blesson\s*[0-9]+\b/i', $name)) {
            return true;
        }
        if (preg_match('/^final\s*exam$/i', $name)) {
            return true;
        }
        return false;
    };

    $sortweight = static function(string $itemname, string $itemmodule = ''): int {
        $name = trim($itemname);
        if (preg_match('/^pretest$/i', $name)) {
            return 1;
        }
        if (preg_match('/^lesson\s*([0-9]+)\s*[:\-]?\s*quiz$/i', $name, $matches)) {
            return 100 + (int)$matches[1];
        }
        if ($itemmodule === 'assign' && preg_match('/\blesson\s*([0-9]+)\b/i', $name, $matches)) {
            return 200 + (int)$matches[1];
        }
        if (preg_match('/^final\s*exam$/i', $name)) {
            return 999;
        }
        return 9999;
    };

    $documenticon = '<span class="hd-score-icon hd-score-icon-document" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM6 4V20H18V4H6ZM8 7H16V9H8V7ZM8 11H16V13H8V11ZM8 15H14V17H8V15Z"></path></svg></span>';
    $checkicon = '<span class="hd-score-icon hd-score-icon-check" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M9.1 16.6L4.9 12.4L3.5 13.8L9.1 19.4L20.8 7.7L19.4 6.3L9.1 16.6Z"></path></svg></span>';
    $lockicon = '<span class="hd-score-lock" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M17 9H16V7C16 4.8 14.2 3 12 3C9.8 3 8 4.8 8 7V9H7C5.9 9 5 9.9 5 11V20C5 21.1 5.9 22 7 22H17C18.1 22 19 21.1 19 20V11C19 9.9 18.1 9 17 9ZM10 7C10 5.9 10.9 5 12 5C13.1 5 14 5.9 14 7V9H10V7Z"></path></svg></span>';

    $cmsbyinstance = [];
    foreach ($modinfo->get_cms() as $cm) {
        $cmsbyinstance[$cm->modname . ':' . $cm->instance] = $cm;
    }

    $items = [];
    $gradeitems = $DB->get_records_select('grade_items', 'courseid = ? AND itemtype = ?', [$course->id, 'mod'], 'sortorder ASC');

    $gradegrades = [];
    if (!empty($gradeitems)) {
        [$insql, $params] = $DB->get_in_or_equal(array_map(static fn($gradeitem) => (int)$gradeitem->id, $gradeitems), SQL_PARAMS_NAMED, 'gg', false);
        $records = $DB->get_records_select(
            'grade_grades',
            'itemid ' . $insql . ' AND userid = :userid',
            array_merge($params, ['userid' => (int)$USER->id])
        );
        foreach ($records as $record) {
            $gradegrades[(int)$record->itemid] = $record;
        }
    }

    foreach ($gradeitems as $gradeitem) {
        if (!empty($gradeitem->hidden) || empty($gradeitem->itemname)) {
            continue;
        }

        $name = trim((string)$gradeitem->itemname);
        $itemmodule = (string)($gradeitem->itemmodule ?? '');
        if (!$alloweditem($name, $itemmodule)) {
            continue;
        }

        if (empty($gradeitem->itemmodule) || empty($gradeitem->iteminstance)) {
            continue;
        }

        $key = $gradeitem->itemmodule . ':' . $gradeitem->iteminstance;
        $cm = $cmsbyinstance[$key] ?? null;
        if (!$cm) {
            continue;
        }

        if ($search !== '') {
            $needle = core_text::strtolower($search);
            $haystack = core_text::strtolower($name);
            if (strpos($haystack, $needle) === false) {
                continue;
            }
        }

        $gradegrade = $gradegrades[(int)$gradeitem->id] ?? null;

        $finalgrade = null;
        $datesubmitted = null;
        if ($gradegrade && $gradegrade->finalgrade !== null) {
            $finalgrade = $gradegrade->finalgrade;
            $datesubmitted = !empty($gradegrade->timemodified) ? $gradegrade->timemodified : $gradegrade->timecreated;
        }

        $submitted = ($finalgrade !== null);
        $locked = (!$cm->available || !$cm->uservisible);
        $maxgrade = !empty($gradeitem->grademax) ? $gradeitem->grademax : 100;
        $percent = $percentage($finalgrade, $maxgrade);

        $ispretest   = (bool)preg_match('/^pretest$/i', $name);
        $isfinalexam = (bool)preg_match('/^final\s*exam$/i', $name);
        $islessonquiz = !$ispretest && !$isfinalexam && $cm->modname === 'quiz';
        $isassignment = $cm->modname === 'assign';

        // Pretest is definitionally not for credit; everything else is.
        $credit = !$ispretest;

        if ($creditonly && !$credit) {
            continue;
        }

        if ($ispretest) {
            $activityurl = local_heyday_courseplayer_url($course, 'pretest', ['cmid' => $cm->id]);
        } else if ($isfinalexam) {
            $activityurl = local_heyday_courseplayer_url($course, 'finalexam', ['cmid' => $cm->id]);
        } else if ($islessonquiz) {
            $activityurl = local_heyday_courseplayer_url($course, 'lessonquiz', ['cmid' => $cm->id]);
        } else if ($isassignment) {
            $activityurl = local_heyday_courseplayer_url($course, 'assignment', ['cmid' => $cm->id]);
        } else {
            $activityurl = local_heyday_courseplayer_url($course, 'lesson', ['cmid' => $cm->id]);
        }

        if ($isassignment) {
            $typelabel = 'Assignment';
        } else {
            $typelabel = 'Quiz';
        }

        $items[] = [
            'name'         => $name,
            'typelabel'    => $typelabel,
            'url'          => $activityurl,
            'submitted'    => $submitted,
            'datesubmitted'=> $datesubmitted,
            'finalgrade'   => $finalgrade,
            'maxgrade'     => $maxgrade,
            'percent'      => $percent,
            'locked'       => $locked,
            'notforgrade'  => $ispretest,
            'credit'       => $credit,
            'weight'       => $sortweight($name, $itemmodule),
        ];
    }

    usort($items, static function($a, $b): int {
        if ($a['weight'] === $b['weight']) {
            return strnatcasecmp($a['name'], $b['name']);
        }
        return $a['weight'] <=> $b['weight'];
    });

    $perpage = 10;
    $totalitems = count($items);
    $totalpages = max(1, (int)ceil($totalitems / $perpage));
    if ($pagenum < 1) {
        $pagenum = 1;
    }
    if ($pagenum > $totalpages) {
        $pagenum = $totalpages;
    }
    $offset = ($pagenum - 1) * $perpage;
    $pageditems = array_slice($items, $offset, $perpage);

    $downloadurl = null;
    if (file_exists($CFG->dirroot . '/local/heyday_scores/download.php')) {
        $downloadurl = new moodle_url('/local/heyday_scores/download.php', ['id' => $course->id]);
    }

    $output = html_writer::start_div('heyday-scores-wrapper');

    $output .= html_writer::start_div('heyday-scores-title-row');
    $output .= html_writer::tag('h1', 'Scores', ['class' => 'heyday-scores-title']);
    if ($downloadurl) {
        $output .= html_writer::link($downloadurl, 'Download scores', ['class' => 'heyday-download-btn']);
    } else {
        $output .= html_writer::tag('button', 'Download scores', [
            'type' => 'button',
            'class' => 'heyday-download-btn',
            'disabled' => 'disabled',
        ]);
    }
    $output .= html_writer::end_div();

    $output .= html_writer::start_tag('form', [
        'method' => 'get',
        'action' => local_heyday_courseplayer_url($course, 'scores')->out(false),
        'class' => 'heyday-score-toolbar',
    ]);
    $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $course->id]);
    $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => 'scores']);

    $checkboxattrs = ['type' => 'checkbox', 'name' => 'creditonly', 'value' => '1', 'id' => 'heyday-credit-only'];
    if ($creditonly) {
        $checkboxattrs['checked'] = 'checked';
    }
    $output .= html_writer::start_tag('label', ['class' => 'heyday-credit-filter']);
    $output .= html_writer::empty_tag('input', $checkboxattrs);
    $output .= html_writer::span('Credit Assignments Only');
    $output .= html_writer::end_tag('label');

    $output .= html_writer::tag('button', 'Sort By', [
        'type' => 'button',
        'class' => 'heyday-sort-btn',
        'id' => 'heyday-sort-button',
    ]);

    $output .= html_writer::start_div('heyday-search-wrap');
    $output .= html_writer::tag('span', '&#128269;', ['class' => 'heyday-search-icon']);
    $output .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'q',
        'id' => 'heyday-score-search',
        'value' => $search,
        'placeholder' => 'Assignment Name',
        'class' => 'heyday-score-search',
    ]);
    $output .= html_writer::end_div();

    $output .= html_writer::tag('button', 'Apply', [
        'type' => 'submit',
        'class' => 'heyday-score-apply',
    ]);

    $output .= html_writer::end_tag('form');

    $output .= html_writer::start_div('heyday-score-list', ['id' => 'heyday-score-list']);

    if (empty($pageditems)) {
        $output .= html_writer::div('No score items are available yet.', 'heyday-empty-state');
    }

    foreach ($pageditems as $item) {
        $rowclasses = ['heyday-score-row'];
        if ($item['locked']) {
            $rowclasses[] = 'is-locked';
        }
        if ($item['submitted']) {
            $rowclasses[] = 'is-submitted';
        }

        $output .= html_writer::start_div('', [
            'class' => implode(' ', $rowclasses),
            'data-name' => core_text::strtolower($item['name']),
            'data-credit' => $item['credit'] ? '1' : '0',
        ]);

        $output .= html_writer::start_div('heyday-score-left');
        $output .= $item['submitted'] ? $checkicon : $documenticon;
        $output .= html_writer::start_div('heyday-score-main');

        if ($item['locked']) {
            $output .= html_writer::tag('span', format_string($item['name']), ['class' => 'heyday-score-name locked-name']);
        } else {
            $output .= html_writer::link($item['url'], format_string($item['name']), ['class' => 'heyday-score-name']);
        }

        $output .= html_writer::div($item['typelabel'], 'heyday-score-type');

        if ($item['submitted'] && !empty($item['datesubmitted'])) {
            $output .= html_writer::div('Submitted on: ' . $formatdate($item['datesubmitted']), 'heyday-score-submitted-date');
        }

        $output .= html_writer::end_div();
        $output .= html_writer::end_div();

        $output .= html_writer::start_div('heyday-score-right');
        if ($item['locked']) {
            $output .= $lockicon;
        } else if ($item['submitted']) {
            $percenttext = ($item['percent'] === null) ? '--' : ((string)$item['percent'] . '%');
            $output .= html_writer::div($percenttext, 'heyday-score-percent');
            if ($item['notforgrade']) {
                $output .= html_writer::div('Does not count for grade', 'heyday-score-note');
            }
            $output .= html_writer::div($cleannumber($item['finalgrade']) . ' / ' . $cleannumber($item['maxgrade']), 'heyday-score-points heyday-score-points-complete');
        } else {
            $output .= html_writer::div('Not Started', 'heyday-score-status');
            if ($item['notforgrade']) {
                $output .= html_writer::div('Does not count for grade', 'heyday-score-note');
            }
            $output .= html_writer::div('-- / ' . $cleannumber($item['maxgrade']), 'heyday-score-points');
        }
        $output .= html_writer::end_div();

        $output .= html_writer::end_div();
    }

    $output .= html_writer::end_div();

    if ($totalpages > 1) {
        $output .= html_writer::start_div('heyday-score-pagination');
        if ($pagenum > 1) {
            $output .= html_writer::link(local_heyday_courseplayer_url($course, 'scores', ['p' => $pagenum - 1]), 'Prev', ['class' => 'heyday-page-btn']);
        } else {
            $output .= html_writer::span('Prev', 'heyday-page-btn is-disabled');
        }
        for ($i = 1; $i <= $totalpages; $i++) {
            if ($i === $pagenum) {
                $output .= html_writer::span((string)$i, 'heyday-page-btn is-active');
            } else {
                $output .= html_writer::link(local_heyday_courseplayer_url($course, 'scores', ['p' => $i]), (string)$i, ['class' => 'heyday-page-btn']);
            }
        }
        if ($pagenum < $totalpages) {
            $output .= html_writer::link(local_heyday_courseplayer_url($course, 'scores', ['p' => $pagenum + 1]), 'Next', ['class' => 'heyday-page-btn']);
        } else {
            $output .= html_writer::span('Next', 'heyday-page-btn is-disabled');
        }
        $output .= html_writer::end_div();
    }

    $output .= html_writer::tag('script', "(function(){'use strict';var searchInput=document.getElementById('heyday-score-search');var creditOnly=document.getElementById('heyday-credit-only');var sortButton=document.getElementById('heyday-sort-button');var list=document.getElementById('heyday-score-list');function applyFilters(){var search=searchInput?searchInput.value.trim().toLowerCase():'';var onlyCredit=creditOnly?creditOnly.checked:false;document.querySelectorAll('.heyday-score-row').forEach(function(row){var name=row.getAttribute('data-name')||'';var credit=row.getAttribute('data-credit')==='1';var matchesSearch=!search||name.indexOf(search)!==-1;var matchesCredit=!onlyCredit||credit;row.style.display=(matchesSearch&&matchesCredit)?'':'none';});}if(searchInput){searchInput.addEventListener('input',applyFilters);}if(creditOnly){creditOnly.addEventListener('change',applyFilters);}if(sortButton&&list){sortButton.addEventListener('click',function(){var rows=Array.from(list.querySelectorAll('.heyday-score-row'));rows.sort(function(a,b){var an=a.getAttribute('data-name')||'';var bn=b.getAttribute('data-name')||'';return an.localeCompare(bn);});rows.forEach(function(row){list.appendChild(row);});});}})();");

    $output .= html_writer::end_div();
    return $output;
}

/**
 * Extract a lesson number from a discussion activity name.
 *
 * @param string $name Activity name.
 * @return int|null
 */
function local_heyday_courseplayer_discussion_lesson_number(string $name): ?int {
    if (preg_match('/lesson\s*(\d+)/i', $name, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

/**
 * Count posts, participants, latest post date, and new posts for a Moodle forum.
 *
 * @param int $forumid Forum instance id.
 * @param int $courseid Course id.
 * @param int $userid User id.
 * @return array<int,int> posts, participants, latest timestamp, new posts.
 */
function local_heyday_courseplayer_discussion_counts(int $forumid, int $courseid, int $userid): array {
    global $DB;
    static $cache = [];

    $cachekey = 'forum:' . $forumid . ':course:' . $courseid . ':user:' . $userid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    $posts = (int)$DB->count_records_sql(
        "SELECT COUNT(fp.id)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    $participants = (int)$DB->count_records_sql(
        "SELECT COUNT(DISTINCT fp.userid)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    $latest = (int)$DB->get_field_sql(
        "SELECT MAX(fp.modified)
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.forum = :forumid",
        ['forumid' => $forumid]
    );

    $lastaccess = (int)$DB->get_field('user_lastaccess', 'timeaccess', [
        'userid' => $userid,
        'courseid' => $courseid,
    ], IGNORE_MISSING);

    $newposts = 0;
    if ($lastaccess > 0) {
        $newposts = (int)$DB->count_records_sql(
            "SELECT COUNT(fp.id)
               FROM {forum_posts} fp
               JOIN {forum_discussions} fd ON fd.id = fp.discussion
              WHERE fd.forum = :forumid
                AND fp.modified > :lastaccess
                AND fp.userid <> :userid",
            [
                'forumid' => $forumid,
                'lastaccess' => $lastaccess,
                'userid' => $userid,
            ]
        );
    }

    return $cache[$cachekey] = [$posts, $participants, $latest, $newposts];
}

/**
 * Prefer the custom Heyday Discussions view when that plugin is installed.
 *
 * @param cm_info $cm Discussion course module.
 * @return moodle_url
 */
function local_heyday_courseplayer_discussion_view_url(cm_info $cm): moodle_url {
    try {
        $course = get_course((int)$cm->course);
        return local_heyday_courseplayer_url($course, 'discussion', ['cmid' => $cm->id]);
    } catch (Throwable $e) {
        return new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
    }
}

/**
 * Render discussion areas.
 *
 * @param stdClass $course Course record.
 * @param array<int,cm_info> $discussioncms Discussion modules.
 * @return string
 */
function local_heyday_courseplayer_render_discussions(stdClass $course, array $discussioncms): string {
    global $DB, $USER;

    if (empty($discussioncms)) {
        return html_writer::div(
            html_writer::div(get_string('discussion_setupneeded', 'local_heyday_courseplayer'), 'heyday-discussion-setup'),
            'heyday-discussions-page-master'
        );
    }

    $rows = [];
    foreach ($discussioncms as $cm) {
        $forum = null;
        if ($cm->modname === 'forum') {
            $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', IGNORE_MISSING);
        }

        $posts = 0;
        $participants = 0;
        $latest = 0;
        $newposts = 0;
        $updatedtime = 0;

        if ($forum) {
            [$posts, $participants, $latest, $newposts] = local_heyday_courseplayer_discussion_counts(
                (int)$forum->id,
                (int)$course->id,
                (int)$USER->id
            );
            $updatedtime = $latest ?: (int)$forum->timemodified;
        }

        $locked = !$cm->uservisible || (property_exists($cm, 'available') && !$cm->available);

        $rows[] = [
            'name'         => format_string($cm->name, true, ['context' => $cm->context]),
            'lessonno'     => local_heyday_courseplayer_discussion_lesson_number((string)$cm->name),
            'url'          => local_heyday_courseplayer_discussion_view_url($cm),
            'posts'        => $posts,
            'participants' => $participants,
            'updated'      => $updatedtime ? userdate($updatedtime, '%m/%d/%Y') : '',
            'newposts'     => $newposts,
            'locked'       => $locked,
            'cm'           => $cm,
        ];
    }

    // Sort by lesson number ascending; forums with no lesson number sort last.
    usort($rows, static function($a, $b): int {
        $an = $a['lessonno'] ?? PHP_INT_MAX;
        $bn = $b['lessonno'] ?? PHP_INT_MAX;
        if ($an !== $bn) {
            return $an <=> $bn;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });

    $lockicon = '<svg class="heyday-discussion-lock-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M17 9H16V7C16 4.8 14.2 3 12 3C9.8 3 8 4.8 8 7V9H7C5.9 9 5 9.9 5 11V20C5 21.1 5.9 22 7 22H17C18.1 22 19 21.1 19 20V11C19 9.9 18.1 9 17 9ZM10 7C10 5.9 10.9 5 12 5C13.1 5 14 5.9 14 7V9H10V7Z"></path></svg>';

    $output = html_writer::start_div('heyday-discussions-page-master');
    $output .= html_writer::start_div('heyday-discussion-card-list');

    foreach ($rows as $row) {
        $classes = ['heyday-discussion-card'];
        if (!empty($row['locked'])) {
            $classes[] = 'is-locked';
        }

        $output .= html_writer::start_div(implode(' ', $classes));

        $output .= html_writer::start_div('heyday-discussion-left');
        $output .= '<span class="heyday-discussion-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M21 6.5C21 5.4 20.1 4.5 19 4.5H5C3.9 4.5 3 5.4 3 6.5V15.5C3 16.6 3.9 17.5 5 17.5H8L12 21.5L16 17.5H19C20.1 17.5 21 16.6 21 15.5V6.5Z"></path></svg></span>';

        $output .= html_writer::start_div('heyday-discussion-main');
        if (empty($row['locked'])) {
            $output .= html_writer::link($row['url'], s($row['name']), ['class' => 'heyday-discussion-title']);
        } else {
            $output .= html_writer::tag('span', s($row['name']), ['class' => 'heyday-discussion-title']);
        }

        if (empty($row['locked'])) {
            if (!empty($row['newposts'])) {
                $badge = $row['newposts'] . ' ' . get_string($row['newposts'] === 1 ? 'newpost' : 'newposts', 'local_heyday_courseplayer');
                $output .= html_writer::div(s($badge), 'heyday-discussion-new-badge');
            }
            $postslabel = $row['posts'] . ' ' . get_string($row['posts'] === 1 ? 'post' : 'posts', 'local_heyday_courseplayer');
            $participantslabel = $row['participants'] . ' ' . get_string($row['participants'] === 1 ? 'participant' : 'participants', 'local_heyday_courseplayer');
            $output .= html_writer::div(s($postslabel . '   ' . $participantslabel), 'heyday-discussion-meta');
        } else {
            $output .= html_writer::tag('small', s(local_heyday_courseplayer_locked_message($row['cm'])), ['class' => 'heyday-release-note']);
        }

        $output .= html_writer::end_div();
        $output .= html_writer::end_div();

        $output .= html_writer::start_div('heyday-discussion-right');
        if (!empty($row['locked'])) {
            $output .= html_writer::div($lockicon, 'heyday-discussion-lock-wrap', ['title' => get_string('locked', 'local_heyday_courseplayer')]);
        } else {
            $output .= html_writer::div(get_string('updated', 'local_heyday_courseplayer'), 'heyday-discussion-updated-label');
            if (!empty($row['updated'])) {
                $output .= html_writer::div(s($row['updated']), 'heyday-discussion-updated-date');
            }
        }
        $output .= html_writer::end_div();

        $output .= html_writer::end_div();
    }

    $output .= html_writer::end_div();
    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render an ed2go-style discussion detail page for a single Moodle Forum activity.
 *
 * Used by page=discussion (forum cmid direct link) and by render_item_content
 * when a lesson-group item is a forum activity (page=lesson&cmid=FORUM_CMID).
 *
 * @param stdClass $course Course record.
 * @param cm_info $cm Forum course module.
 * @return string
 */
function local_heyday_courseplayer_render_discussion_detail(stdClass $course, cm_info $cm, int $did = 0): string {
    global $DB, $USER;

    $forumname = format_string($cm->name, true, ['context' => $cm->context]);

    if (!$cm->uservisible || (property_exists($cm, 'available') && !$cm->available)) {
        return local_heyday_courseplayer_render_locked_card(
            $forumname,
            local_heyday_courseplayer_locked_message($cm)
        );
    }

    if ($cm->modname !== 'forum') {
        return local_heyday_courseplayer_activity_card(
            $cm,
            get_string('openactivity', 'local_heyday_courseplayer'),
            get_string('normalactivityscreen', 'local_heyday_courseplayer'),
            $cm->url ?: new moodle_url('/mod/forum/view.php', ['id' => $cm->id])
        );
    }

    $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$forum) {
        return html_writer::div(
            html_writer::tag('p', get_string('noitemsfound', 'local_heyday_courseplayer')),
            'heyday-empty-state'
        );
    }

    // Closed when forum type is 'news' (announcements) or Moodle blockafter is past.
    $isclosed = (isset($forum->type) && $forum->type === 'news')
        || (isset($forum->blockafter) && (int)$forum->blockafter > 0 && time() > (int)$forum->blockafter);

    // Forum intro / prompt text.
    $intro = '';
    if (!empty($forum->intro)) {
        $intro = format_text($forum->intro, $forum->introformat ?? FORMAT_HTML, ['context' => $cm->context, 'overflowdiv' => false]);
        if (trim(strip_tags($intro)) === '') {
            $intro = '';
        }
    }
    if ($intro === '') {
        $intro = '<p>Use this discussion area to respond to the lesson prompt and interact with your classmates.</p>';
    }

    // Last-access time used for "new posts" detection.
    $lastaccess = (int)$DB->get_field('user_lastaccess', 'timeaccess', [
        'userid'   => (int)$USER->id,
        'courseid' => (int)$course->id,
    ], IGNORE_MISSING);

    $discussions = $DB->get_records_sql(
        "SELECT fd.id, fd.name, fd.userid, fd.timemodified, fd.timestart,
                u.firstname, u.lastname,
                (SELECT COUNT(fp2.id) FROM {forum_posts} fp2 WHERE fp2.discussion = fd.id) AS replycount,
                (SELECT COUNT(fp3.id) FROM {forum_posts} fp3
                  WHERE fp3.discussion = fd.id
                    AND fp3.modified > :lastaccess
                    AND fp3.userid <> :myid) AS newcount
           FROM {forum_discussions} fd
           JOIN {user} u ON u.id = fd.userid
          WHERE fd.forum = :forumid
            AND (fd.timestart = 0 OR fd.timestart <= :now)
       ORDER BY fd.timemodified DESC",
        [
            'forumid'    => (int)$forum->id,
            'now'        => time(),
            'lastaccess' => $lastaccess ?: 0,
            'myid'       => (int)$USER->id,
        ],
        0, 100
    );

    // Load all posts (root + replies) per discussion for inline display.
    $postsbydiscussion = [];
    if (!empty($discussions)) {
        $discids = array_keys($discussions);
        list($discinsql, $discparams) = $DB->get_in_or_equal($discids, SQL_PARAMS_NAMED, 'dp');
        try {
            $postrecords = $DB->get_records_sql(
                "SELECT fp.id, fp.discussion, fp.parent, fp.userid,
                        fp.message, fp.messageformat, fp.created,
                        u.firstname, u.lastname
                   FROM {forum_posts} fp
                   JOIN {user} u ON u.id = fp.userid
                  WHERE fp.discussion $discinsql
                    AND fp.deleted = 0
               ORDER BY fp.discussion, fp.created ASC",
                $discparams
            );
            foreach ($postrecords as $post) {
                $postsbydiscussion[(int)$post->discussion][] = $post;
            }
        } catch (Throwable $e) {
            $postsbydiscussion = [];
        }
    }

    $canpost  = !$isclosed && has_capability('mod/forum:startdiscussion', $cm->context);
    $canreply = !$isclosed && has_capability('mod/forum:replypost', $cm->context);

    // Cache instructor detection per user ID.
    $coursecontext   = context_course::instance((int)$course->id);
    $teachercache    = [];
    $isinstructor_fn = static function (int $uid) use ($coursecontext, &$teachercache): bool {
        if (!array_key_exists($uid, $teachercache)) {
            $teachercache[$uid] = has_capability('moodle/course:update', $coursecontext, $uid);
        }
        return $teachercache[$uid];
    };

    $addposturl     = (new moodle_url('/mod/forum/post.php', ['forum' => $cm->instance]))->out(false);
    $nativeforumurl = (new moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false);

    $totalthreads = count($discussions);
    $minethreads  = 0;
    $newthreads   = 0;
    foreach ($discussions as $disc) {
        if ((int)$disc->userid === (int)$USER->id) {
            $minethreads++;
        }
        if ((int)($disc->newcount ?? 0) > 0) {
            $newthreads++;
        }
    }

    // Thread detail view: when a specific discussion ID is requested.
    if ($did > 0 && isset($discussions[$did])) {
        $disc     = $discussions[$did];
        $discposts = $postsbydiscussion[$did] ?? [];
        $rootpost  = null;
        $replies   = [];
        foreach ($discposts as $post) {
            if ((int)$post->parent === 0) {
                $rootpost = $post;
            } else {
                $replies[] = $post;
            }
        }

        $listurl     = local_heyday_courseplayer_url($course, 'discussion', ['cmid' => $cm->id])->out(false);
        $threadtitle = s(trim((string)$disc->name) !== '' ? $disc->name : '(No Subject)');
        $replycount  = max(0, (int)$disc->replycount - 1);
        $addreplyurl = $rootpost
            ? (new moodle_url('/mod/forum/post.php', ['reply' => (int)$rootpost->id]))->out(false)
            : (new moodle_url('/mod/forum/post.php', ['forum' => $cm->instance]))->out(false);

        ob_start();
        ?>
<div class="hd-discussion-page hd-discussion-thread-view" id="hd-discussion-page">

  <div class="hd-disc-thread-back">
    <a href="<?php echo s($listurl); ?>" class="hd-disc-back-link">
      <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" style="fill:currentColor;flex-shrink:0"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      Back to Discussion Area
    </a>
  </div>

  <div class="hd-disc-thread-header">
    <h2 class="hd-disc-thread-page-title"><?php echo $threadtitle; ?></h2>
    <span class="hd-disc-thread-reply-count"><?php echo $replycount; ?> <?php echo $replycount === 1 ? 'reply' : 'replies'; ?></span>
  </div>

  <?php if ($isclosed): ?>
  <div class="hd-discussion-closed-banner" role="alert">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
    <?php echo get_string('discussion_closed', 'local_heyday_courseplayer'); ?>
  </div>
  <?php endif; ?>

  <?php if ($rootpost): ?>
  <div class="hd-reply hd-reply-root">
    <div class="hd-reply-body">
      <div class="hd-reply-author-row">
        <span class="hd-reply-author"><?php echo s(trim($rootpost->firstname . ' ' . $rootpost->lastname)); ?></span>
        <span class="hd-reply-date"><?php echo userdate((int)$rootpost->created, '%b %d, %Y %I:%M %p'); ?></span>
      </div>
      <div class="hd-reply-content"><?php echo format_text($rootpost->message, (int)$rootpost->messageformat, ['context' => $cm->context, 'overflowdiv' => false]); ?></div>
      <?php if ($canreply): ?>
      <div class="hd-reply-actions">
        <a href="<?php echo s($addreplyurl); ?>" target="_top" class="hd-disc-action-reply">Reply</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($replies)): ?>
  <div class="hd-disc-replies-section">
    <h3 class="hd-disc-replies-heading">Replies</h3>
    <?php foreach ($replies as $reply):
        $replyisinstr  = $isinstructor_fn((int)$reply->userid);
        $replytothisurl = (new moodle_url('/mod/forum/post.php', ['reply' => (int)$reply->id]))->out(false);
    ?>
    <div class="hd-reply<?php echo $replyisinstr ? ' hd-reply-instructor' : ''; ?>">
      <?php if ($replyisinstr): ?>
      <div class="hd-reply-instructor-header">
        <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14" style="fill:currentColor;flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Instructor Response
      </div>
      <?php endif; ?>
      <div class="hd-reply-body">
        <div class="hd-reply-author-row">
          <span class="hd-reply-author"><?php echo s(trim($reply->firstname . ' ' . $reply->lastname)); ?></span>
          <span class="hd-reply-date"><?php echo userdate((int)$reply->created, '%b %d, %Y %I:%M %p'); ?></span>
        </div>
        <div class="hd-reply-content"><?php echo format_text($reply->message, (int)$reply->messageformat, ['context' => $cm->context, 'overflowdiv' => false]); ?></div>
        <?php if ($canreply): ?>
        <div class="hd-reply-actions">
          <a href="<?php echo s($replytothisurl); ?>" target="_top" class="hd-reply-action-reply">Reply</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($canreply && !empty($replies) || ($canpost && !$rootpost)): ?>
  <div class="hd-disc-thread-add-reply">
    <a href="<?php echo s($addreplyurl); ?>" target="_top" class="hd-primary-btn">
      <?php echo $canpost && !$rootpost ? 'Start Discussion' : 'Add a Reply'; ?>
    </a>
  </div>
  <?php endif; ?>

  <div class="hd-discussion-native-wrap">
    <a class="hd-discussion-native-link" href="<?php echo s((new moodle_url('/mod/forum/discuss.php', ['d' => $did]))->out(false)); ?>" target="_top">
      Open Full Thread View
    </a>
  </div>

</div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
<div class="hd-discussion-page" id="hd-discussion-page">

  <div class="hd-discussion-prompt">
    <svg class="hd-discussion-prompt-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 6.5C21 5.4 20.1 4.5 19 4.5H5C3.9 4.5 3 5.4 3 6.5V15.5C3 16.6 3.9 17.5 5 17.5H8L12 21.5L16 17.5H19C20.1 17.5 21 16.6 21 15.5V6.5Z"></path></svg>
    <div class="hd-discussion-prompt-body"><?php echo $intro; ?></div>
  </div>
  <hr class="hd-discussion-divider">

<?php if ($isclosed): ?>
  <div class="hd-discussion-closed-banner" role="alert">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
    <?php echo get_string('discussion_closed', 'local_heyday_courseplayer'); ?>
  </div>
<?php endif; ?>

<?php if ($canpost): ?>
  <div class="hd-write-post-card" id="hd-write-post">
    <div class="hd-write-post-heading">
      <svg class="hd-write-post-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="18" height="18" style="fill:currentColor;flex-shrink:0"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
      <span>Write your post</span>
    </div>
    <div class="hd-write-post-body">
      <input type="text" id="hd-wp-title" class="hd-write-post-title"
             placeholder="<?php echo s('Enter a title for your post...'); ?>"
             maxlength="255" autocomplete="off">
      <div class="hd-editor-toolbar" id="hd-editor-toolbar" role="toolbar" aria-label="Text formatting (preview only — Submit opens full editor)">
        <button type="button" class="hd-editor-btn hd-tb-bold" title="Bold" tabindex="-1"><b>B</b></button>
        <button type="button" class="hd-editor-btn hd-tb-italic" title="Italic" tabindex="-1"><i>I</i></button>
        <button type="button" class="hd-editor-btn hd-tb-underline" title="Underline" tabindex="-1"><u>U</u></button>
        <span class="hd-tb-sep"></span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="text-style">
          <button type="button" class="hd-editor-btn hd-editor-select-btn" title="Text style" tabindex="-1" data-hd-tb-toggle="text-style">
            <b>A</b><sub style="font-size:0.65em">a</sub><span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu" id="hd-tb-menu-text-style" hidden role="menu">
            <li role="menuitem">Normal</li>
            <li role="menuitem">Heading 1</li>
            <li role="menuitem">Heading 2</li>
            <li role="menuitem">Heading 3</li>
            <li role="menuitem">Preformatted</li>
          </ul>
        </span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="color">
          <button type="button" class="hd-editor-btn hd-editor-color-btn" title="Text color" tabindex="-1" data-hd-tb-toggle="color">
            <span class="hd-color-swatch" style="background:#333;border-radius:2px;display:inline-block;width:12px;height:12px;vertical-align:middle;"></span><span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu hd-editor-color-menu" id="hd-tb-menu-color" hidden role="menu">
            <li role="menuitem"><span class="hd-color-swatch" style="background:#000"></span> Black</li>
            <li role="menuitem"><span class="hd-color-swatch" style="background:#e53e3e"></span> Red</li>
            <li role="menuitem"><span class="hd-color-swatch" style="background:#3182ce"></span> Blue</li>
            <li role="menuitem"><span class="hd-color-swatch" style="background:#2f855a"></span> Green</li>
            <li role="menuitem"><span class="hd-color-swatch" style="background:#d69e2e"></span> Yellow</li>
          </ul>
        </span>
        <span class="hd-tb-sep"></span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="paragraph">
          <button type="button" class="hd-editor-btn hd-editor-select-btn" title="Paragraph format" tabindex="-1" data-hd-tb-toggle="paragraph">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M13 4v7h-2V4H9v-.01L3.01 4 3 6h4v12h2V6h2v12h2V4h-2zm5 4c-1.1 0-2 .9-2 2v9h2v-4h2v4h2v-9c0-1.1-.9-2-2-2h-2zm0 2h2v3h-2v-3z"/></svg>
            Normal<span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu" id="hd-tb-menu-paragraph" hidden role="menu">
            <li role="menuitem">Normal</li>
            <li role="menuitem">Heading 1</li>
            <li role="menuitem">Heading 2</li>
            <li role="menuitem">Blockquote</li>
            <li role="menuitem">Code</li>
          </ul>
        </span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="align">
          <button type="button" class="hd-editor-btn hd-editor-select-btn" title="Alignment" tabindex="-1" data-hd-tb-toggle="align">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M3 3h18v2H3V3zm0 4h12v2H3V7zm0 4h18v2H3v-2zm0 4h12v2H3v-2zm0 4h18v2H3v-2z"/></svg>
            <span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu" id="hd-tb-menu-align" hidden role="menu">
            <li role="menuitem">Align Left</li>
            <li role="menuitem">Align Center</li>
            <li role="menuitem">Align Right</li>
            <li role="menuitem">Justify</li>
          </ul>
        </span>
        <span class="hd-tb-sep"></span>
        <button type="button" class="hd-editor-btn" title="Insert link" tabindex="-1">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
        </button>
        <button type="button" class="hd-editor-btn" title="Insert table" tabindex="-1">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M20 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 2v3H5V5h15zm-9 5h-6v-2h6v2zm0 4h-6v-2h6v2zm0 4h-6v-2h6v2zm9-8h-7v-2h7v2zm0 4h-7v-2h7v2zm0 4h-7v-2h7v2z"/></svg>
        </button>
        <button type="button" class="hd-editor-btn hd-editor-btn-omega" title="Special character" tabindex="-1"><b style="font-size:0.95em">&#937;</b></button>
        <button type="button" class="hd-editor-btn" title="Horizontal rule" tabindex="-1"><span style="font-size:0.9em;letter-spacing:-1px">&#8213;</span></button>
        <span class="hd-tb-sep"></span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="font">
          <button type="button" class="hd-editor-btn hd-editor-select-btn hd-editor-font-btn" title="Font" tabindex="-1" data-hd-tb-toggle="font">
            Arial<span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu" id="hd-tb-menu-font" hidden role="menu">
            <li role="menuitem" style="font-family:Arial,sans-serif">Arial</li>
            <li role="menuitem" style="font-family:'Times New Roman',serif">Times New Roman</li>
            <li role="menuitem" style="font-family:'Courier New',monospace">Courier New</li>
            <li role="menuitem" style="font-family:Georgia,serif">Georgia</li>
            <li role="menuitem" style="font-family:Verdana,sans-serif">Verdana</li>
          </ul>
        </span>
        <span class="hd-editor-dropdown-wrap" data-hd-tb-dropdown="size">
          <button type="button" class="hd-editor-btn hd-editor-select-btn hd-editor-size-btn" title="Font size" tabindex="-1" data-hd-tb-toggle="size">
            12<span class="hd-tb-arrow" aria-hidden="true">▾</span>
          </button>
          <ul class="hd-editor-menu" id="hd-tb-menu-size" hidden role="menu">
            <li role="menuitem">8</li><li role="menuitem">10</li>
            <li role="menuitem">12</li><li role="menuitem">14</li>
            <li role="menuitem">16</li><li role="menuitem">18</li>
            <li role="menuitem">24</li><li role="menuitem">36</li>
          </ul>
        </span>
        <span class="hd-tb-sep"></span>
        <button type="button" class="hd-editor-btn hd-editor-fullscreen-btn" title="Fullscreen (opens full editor)" tabindex="-1">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
        </button>
        <span class="hd-tb-sep hd-tb-hint-sep"></span>
        <span class="hd-tb-hint">Submit opens the full Moodle editor</span>
      </div>
      <div class="hd-write-post-editor" id="hd-wp-editor"
           contenteditable="true" role="textbox" aria-multiline="true"
           aria-label="<?php echo s('Post message'); ?>"
           data-placeholder="<?php echo s('Write your message here...'); ?>"></div>
      <div class="hd-write-post-upload-row">
        <svg viewBox="0 0 24 24" aria-hidden="true" width="15" height="15" style="fill:currentColor;flex-shrink:0"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>
        <a href="<?php echo s($addposturl); ?>" target="_top" class="hd-write-post-upload-link">Upload File</a>
      </div>
      <div class="hd-write-post-action-row">
        <button type="button" class="hd-btn-outline hd-wp-cancel-btn" id="hd-wp-cancel">Cancel</button>
        <a href="<?php echo s($addposturl); ?>" target="_top" class="hd-wp-submit-btn">Submit</a>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="hd-discussion-toolbar">
    <div class="hd-discussion-search-wrap">
      <input type="text" id="hd-disc-search" class="hd-discussion-search"
             placeholder="<?php echo get_string('discussion_searchposts', 'local_heyday_courseplayer'); ?>"
             aria-label="Search posts">
      <button type="button" class="hd-discussion-search-btn" id="hd-disc-search-btn" aria-label="Search">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="16" height="16" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        Search
      </button>
    </div>
    <button type="button" class="hd-discussion-sort-btn" id="hd-disc-sort-btn" aria-haspopup="listbox" aria-expanded="false">
      <?php echo get_string('discussion_sortby', 'local_heyday_courseplayer'); ?> <span class="hd-sort-arrow">▾</span>
      <ul class="hd-sort-menu" role="listbox" id="hd-sort-menu" hidden>
        <li role="option" data-sort="recent" aria-selected="true"><?php echo get_string('discussion_sort_recent', 'local_heyday_courseplayer'); ?></li>
        <li role="option" data-sort="oldest" aria-selected="false"><?php echo get_string('discussion_sort_oldest', 'local_heyday_courseplayer'); ?></li>
        <li role="option" data-sort="replies" aria-selected="false"><?php echo get_string('discussion_sort_replies', 'local_heyday_courseplayer'); ?></li>
      </ul>
    </button>
  </div>

  <div class="hd-discussion-threads" id="hd-disc-threads" role="list">
<?php if (empty($discussions)): ?>
    <p class="hd-discussion-empty"><?php echo get_string('discussion_nothreads', 'local_heyday_courseplayer'); ?></p>
<?php else:
    $threadidx = 0;
    foreach ($discussions as $disc):
        $threadidx++;
        $discposts = $postsbydiscussion[(int)$disc->id] ?? [];
        $rootpost  = null;
        $replies   = [];
        foreach ($discposts as $post) {
            if ((int)$post->parent === 0) {
                $rootpost = $post;
            } else {
                $replies[] = $post;
            }
        }
        $threadurl    = local_heyday_courseplayer_url($course, 'discussion', ['cmid' => $cm->id, 'did' => $disc->id])->out(false);
        $authorname   = s(trim($disc->firstname . ' ' . $disc->lastname));
        $replycount   = max(0, (int)$disc->replycount - 1);
        $updated      = userdate((int)$disc->timemodified, '%b %d, %Y');
        $isnew        = ((int)($disc->newcount ?? 0) > 0);
        $ismine       = ((int)$disc->userid === (int)$USER->id);
        $threadname   = trim((string)$disc->name) !== '' ? s($disc->name) : '(No Subject)';
        $nativethread = (new moodle_url('/mod/forum/discuss.php', ['d' => $disc->id]))->out(false);
        $replyposturl = $rootpost
            ? (new moodle_url('/mod/forum/post.php', ['reply' => (int)$rootpost->id]))->out(false)
            : $nativethread;

        $excerpt = '';
        if ($rootpost) {
            $rawcontent = format_text($rootpost->message, (int)$rootpost->messageformat, ['context' => $cm->context, 'overflowdiv' => false]);
            $plaintext  = html_entity_decode(strip_tags($rawcontent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plaintext  = trim((string)preg_replace('/\s+/u', ' ', $plaintext));
            if (core_text::strlen($plaintext) > 200) {
                $plaintext = core_text::substr($plaintext, 0, 197) . '...';
            }
            $excerpt = s($plaintext);
        }
?>
    <div class="hd-disc-thread<?php echo $isnew ? ' is-new' : ''; ?>"
         data-disc-id="<?php echo (int)$disc->id; ?>"
         data-mine="<?php echo $ismine ? '1' : '0'; ?>"
         data-new="<?php echo $isnew ? '1' : '0'; ?>"
         data-replies="<?php echo $replycount; ?>"
         data-modified="<?php echo (int)$disc->timemodified; ?>"
         data-idx="<?php echo $threadidx; ?>"
         role="listitem">

      <div class="hd-disc-thread-main">
        <div class="hd-disc-thread-content">
          <div class="hd-disc-thread-top">
            <a class="hd-disc-thread-title" href="<?php echo s($threadurl); ?>" target="_top"><?php echo $threadname; ?></a>
            <div class="hd-disc-thread-meta-right">
              <div class="hd-disc-thread-stats-col">
                <span class="hd-disc-stats-row">
                  <span class="hd-disc-reply-count"><?php echo $replycount; ?> <?php echo $replycount === 1 ? 'Reply' : 'Replies'; ?></span>
                  <?php if ($isnew): ?>
                  <span class="hd-disc-stats-sep" aria-hidden="true">&ndash;</span>
                  <span class="hd-disc-new-count"><?php echo (int)($disc->newcount ?? 0); ?> New</span>
                  <?php endif; ?>
                </span>
                <span class="hd-disc-updated"><?php echo $updated; ?></span>
              </div>
              <button type="button" class="hd-disc-bookmark-btn" aria-label="Bookmark this discussion" title="Bookmark" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="15" height="15" fill="currentColor"><path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg>
              </button>
            </div>
          </div>
          <div class="hd-disc-thread-author"><?php echo $authorname; ?></div>
          <?php if ($excerpt !== ''): ?>
          <div class="hd-disc-thread-excerpt"><?php echo $excerpt; ?></div>
          <?php endif; ?>
          <div class="hd-disc-thread-footer">
            <div class="hd-disc-thread-footer-left">
              <?php if ($canreply && $rootpost): ?>
              <a class="hd-disc-action-reply" href="<?php echo s($replyposturl); ?>" target="_top">Reply</a>
              <span class="hd-disc-action-sep" aria-hidden="true">&middot;</span>
              <?php endif; ?>
              <?php if (!empty($replies)): ?>
              <button type="button" class="hd-disc-expand-btn"
                      data-target="hd-replies-<?php echo (int)$disc->id; ?>"
                      aria-expanded="false">
                <svg class="hd-expand-icon" viewBox="0 0 24 24" aria-hidden="true" width="16" height="16" style="fill:currentColor;display:inline-block;transition:transform .15s"><path d="M7 10l5 5 5-5z"/></svg>
                <?php echo $replycount; ?> <?php echo get_string($replycount === 1 ? 'reply' : 'replies', 'local_heyday_courseplayer'); ?>
              </button>
              <span class="hd-disc-action-sep" aria-hidden="true">&middot;</span>
              <?php endif; ?>
              <a class="hd-disc-action-report" href="<?php echo s($threadurl); ?>" target="_top">Report as Inappropriate</a>
            </div>
            <span class="hd-disc-thread-posted">Posted <?php echo $updated; ?></span>
          </div>
        </div>
      </div>

      <?php if (!empty($replies)): ?>
      <div class="hd-disc-replies" id="hd-replies-<?php echo (int)$disc->id; ?>" hidden>
        <?php foreach ($replies as $reply):
            $replyauthor   = s(trim($reply->firstname . ' ' . $reply->lastname));
            $replydate     = userdate((int)$reply->created, '%b %d, %Y %I:%M %p');
            $replyisinstr  = $isinstructor_fn((int)$reply->userid);
            $replycontent  = format_text($reply->message, (int)$reply->messageformat, ['context' => $cm->context, 'overflowdiv' => false]);
            $replytothisurl = (new moodle_url('/mod/forum/post.php', ['reply' => (int)$reply->id]))->out(false);
        ?>
        <div class="hd-reply<?php echo $replyisinstr ? ' hd-reply-instructor' : ''; ?>">
          <?php if ($replyisinstr): ?>
          <div class="hd-reply-instructor-header">
            <span class="hd-reply-instr-left">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14" style="fill:currentColor;flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              Posted by <?php echo $replyauthor; ?> <span class="hd-reply-instr-tag">(Instructor)</span>
            </span>
            <button type="button" class="hd-reply-bookmark-btn" aria-label="Bookmark this reply" aria-pressed="false">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14" fill="currentColor"><path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg>
            </button>
          </div>
          <?php endif; ?>
          <div class="hd-reply-body">
            <?php if (!$replyisinstr): ?>
            <div class="hd-reply-author"><?php echo $replyauthor; ?></div>
            <?php endif; ?>
            <div class="hd-reply-content"><?php echo $replycontent; ?></div>
          </div>
          <div class="hd-reply-footer">
            <div class="hd-reply-footer-actions">
              <?php if ($canreply): ?>
              <a class="hd-reply-action-reply" href="<?php echo s($replytothisurl); ?>" target="_top">Reply</a>
              <span aria-hidden="true"> &middot; </span>
              <?php endif; ?>
              <a class="hd-reply-action-report" href="<?php echo s($threadurl); ?>" target="_top">Report</a>
            </div>
            <span class="hd-reply-date">Posted <?php echo $replydate; ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
<?php endforeach; ?>
<?php endif; ?>
  </div>

<?php if ($totalthreads > 5): ?>
  <div class="hd-load-more-wrap" id="hd-load-more-wrap">
    <button type="button" class="hd-load-more-btn" id="hd-disc-load-more">Load more threads</button>
  </div>
<?php endif; ?>

  <nav class="hd-discussion-tabs" role="tablist" aria-label="Filter discussions">
    <button class="hd-disc-tab is-active" data-tab="all" role="tab" aria-selected="true">
      <?php echo get_string('discussion_tab_all', 'local_heyday_courseplayer'); ?>
      <span class="hd-disc-tab-count"><?php echo $totalthreads; ?></span>
    </button>
    <button class="hd-disc-tab" data-tab="mine" role="tab" aria-selected="false">
      <?php echo get_string('discussion_tab_mine', 'local_heyday_courseplayer'); ?>
      <span class="hd-disc-tab-count"><?php echo $minethreads; ?></span>
    </button>
    <button class="hd-disc-tab" data-tab="new" role="tab" aria-selected="false">
      <?php echo get_string('discussion_tab_new', 'local_heyday_courseplayer'); ?>
      <span class="hd-disc-tab-count"><?php echo $newthreads; ?></span>
    </button>
  </nav>

  <div class="hd-discussion-native-wrap">
    <a class="hd-discussion-native-link" href="<?php echo s($nativeforumurl); ?>" target="_top"><?php echo get_string('discussion_viewinforum', 'local_heyday_courseplayer'); ?></a>
  </div>

</div><!-- .hd-discussion-page -->

<div class="hd-discussion-counters" id="hd-disc-counters" aria-label="Discussion filter counters">
  <button type="button" class="hd-counter-toggle" id="hd-counter-toggle" aria-label="Collapse counter bar" aria-expanded="true" title="Collapse">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
  </button>
  <div class="hd-counter-items" id="hd-counter-items">
    <button type="button" class="hd-counter-item hd-counter-mine" data-tab="mine">
      <span class="hd-counter-count"><?php echo $minethreads; ?></span>
      <span class="hd-counter-label">Mine</span>
    </button>
    <span class="hd-counter-person" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
    </span>
    <button type="button" class="hd-counter-item hd-counter-new" data-tab="new">
      <span class="hd-counter-count"><?php echo $newthreads; ?></span>
      <span class="hd-counter-label">New</span>
    </button>
    <button type="button" class="hd-counter-item hd-counter-bookmarked" data-tab="all">
      <span class="hd-counter-count">0</span>
      <span class="hd-counter-label">Bookmarked</span>
    </button>
  </div>
</div>

<script>
(function () {
  'use strict';
  var page = document.getElementById('hd-discussion-page');
  if (!page) { return; }

  var allThreads  = Array.from(page.querySelectorAll('.hd-disc-thread'));
  var tabs        = Array.from(page.querySelectorAll('.hd-disc-tab'));
  var searchEl    = document.getElementById('hd-disc-search');
  var sortBtn     = document.getElementById('hd-disc-sort-btn');
  var sortMenu    = document.getElementById('hd-sort-menu');
  var threadList  = document.getElementById('hd-disc-threads');
  var loadMoreBtn = document.getElementById('hd-disc-load-more');
  var loadMoreWrap = document.getElementById('hd-load-more-wrap');
  var PAGE_SIZE   = 5;
  var loadedCount = Math.min(PAGE_SIZE, allThreads.length);
  var activeTab   = 'all';
  var activeSort  = 'recent';
  var currentOrder = allThreads.slice();

  function renderAll() {
    var needle = searchEl ? searchEl.value.trim().toLowerCase() : '';
    currentOrder.forEach(function (t, i) {
      var loaded      = i < loadedCount;
      var title       = (t.querySelector('.hd-disc-thread-title') || {}).textContent || '';
      var excerpt     = (t.querySelector('.hd-disc-thread-excerpt') || {}).textContent || '';
      var matchSearch = !needle || (title + ' ' + excerpt).toLowerCase().indexOf(needle) !== -1;
      var matchTab    = activeTab === 'all'
        || (activeTab === 'mine' && t.getAttribute('data-mine') === '1')
        || (activeTab === 'new'  && t.getAttribute('data-new')  === '1');
      t.style.display = (loaded && matchSearch && matchTab) ? '' : 'none';
    });
    if (loadMoreWrap) {
      var remaining = currentOrder.length - loadedCount;
      if (remaining <= 0) {
        loadMoreWrap.style.display = 'none';
      } else {
        loadMoreWrap.style.display = '';
        if (loadMoreBtn) {
          var toLoad = Math.min(remaining, PAGE_SIZE);
          loadMoreBtn.textContent = 'Load ' + toLoad + ' more thread' + (toLoad !== 1 ? 's' : '');
        }
      }
    }
  }

  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function () {
      loadedCount = Math.min(loadedCount + PAGE_SIZE, currentOrder.length);
      renderAll();
    });
  }

  page.querySelectorAll('.hd-disc-expand-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      var panel    = document.getElementById(targetId);
      if (!panel) { return; }
      var isOpen   = !panel.hidden;
      panel.hidden = isOpen;
      btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      var icon = btn.querySelector('.hd-expand-icon');
      if (icon) { icon.style.transform = isOpen ? '' : 'rotate(180deg)'; }
    });
  });

  var cancelBtn = document.getElementById('hd-wp-cancel');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      var titleEl  = document.getElementById('hd-wp-title');
      var editorEl = document.getElementById('hd-wp-editor');
      if (titleEl)  { titleEl.value = ''; }
      if (editorEl) { editorEl.textContent = ''; }
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activeTab = tab.getAttribute('data-tab') || 'all';
      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
      renderAll();
    });
  });

  if (searchEl) { searchEl.addEventListener('input', renderAll); }

  if (sortBtn && sortMenu) {
    sortBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = !sortMenu.hidden;
      sortMenu.hidden = open;
      sortBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
    sortMenu.querySelectorAll('[role="option"]').forEach(function (opt) {
      opt.addEventListener('click', function () {
        activeSort = opt.getAttribute('data-sort') || 'recent';
        sortMenu.querySelectorAll('[role="option"]').forEach(function (o) {
          o.setAttribute('aria-selected', o === opt ? 'true' : 'false');
        });
        sortMenu.hidden = true;
        sortBtn.setAttribute('aria-expanded', 'false');
        currentOrder = allThreads.slice().sort(function (a, b) {
          if (activeSort === 'recent') {
            return (+b.getAttribute('data-modified')) - (+a.getAttribute('data-modified'));
          }
          if (activeSort === 'oldest') {
            return (+a.getAttribute('data-modified')) - (+b.getAttribute('data-modified'));
          }
          if (activeSort === 'replies') {
            return (+b.getAttribute('data-replies')) - (+a.getAttribute('data-replies'));
          }
          return 0;
        });
        currentOrder.forEach(function (t) { threadList.appendChild(t); });
        renderAll();
      });
    });
    document.addEventListener('click', function (e) {
      if (sortBtn && !sortBtn.contains(e.target)) {
        sortMenu.hidden = true;
        sortBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  renderAll();

  // Editor toolbar: dropdown open/close (visual-only preview toolbar).
  var toolbar = document.getElementById('hd-editor-toolbar');
  if (toolbar) {
    toolbar.querySelectorAll('[data-hd-tb-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var key = btn.getAttribute('data-hd-tb-toggle');
        var menu = document.getElementById('hd-tb-menu-' + key);
        var wrap = btn.closest('[data-hd-tb-dropdown]');
        var isOpen = menu && !menu.hidden;
        // Close all menus first.
        toolbar.querySelectorAll('.hd-editor-menu').forEach(function (m) { m.hidden = true; });
        toolbar.querySelectorAll('[data-hd-tb-dropdown]').forEach(function (w) { w.classList.remove('hd-tb-open'); });
        // Toggle this one.
        if (menu && !isOpen) {
          menu.hidden = false;
          if (wrap) { wrap.classList.add('hd-tb-open'); }
        }
      });
    });
    toolbar.querySelectorAll('.hd-editor-menu [role="menuitem"]').forEach(function (item) {
      item.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close all menus on selection.
        toolbar.querySelectorAll('.hd-editor-menu').forEach(function (m) { m.hidden = true; });
        toolbar.querySelectorAll('[data-hd-tb-dropdown]').forEach(function (w) { w.classList.remove('hd-tb-open'); });
      });
    });
  }
  document.addEventListener('click', function (e) {
    if (toolbar && !toolbar.contains(e.target)) {
      toolbar.querySelectorAll('.hd-editor-menu').forEach(function (m) { m.hidden = true; });
      toolbar.querySelectorAll('[data-hd-tb-dropdown]').forEach(function (w) { w.classList.remove('hd-tb-open'); });
    }
  });

  // Counter bar: toggle collapse.
  var counterToggle = document.getElementById('hd-counter-toggle');
  var counterItems  = document.getElementById('hd-counter-items');
  if (counterToggle && counterItems) {
    counterToggle.addEventListener('click', function () {
      var collapsed = counterItems.style.display === 'none';
      counterItems.style.display = collapsed ? '' : 'none';
      counterToggle.setAttribute('aria-expanded', String(collapsed));
    });
  }

  // Counter bar items: clicking them activates the matching tab filter.
  var counterItemEls = document.querySelectorAll('.hd-counter-item[data-tab]');
  counterItemEls.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.getAttribute('data-tab');
      tabs.forEach(function (t) {
        var isActive = t.getAttribute('data-tab') === tab;
        t.classList.toggle('is-active', isActive);
        t.setAttribute('aria-selected', String(isActive));
        if (isActive) { activeTab = tab; }
      });
      loadedCount = PAGE_SIZE;
      renderAll();
      // Scroll to thread list.
      if (threadList) { threadList.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });
})();
</script>
<?php
    return ob_get_clean();
}


/**
 * Build the Getting Started page definitions used inside the master player.
 *
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @return array<string,array<string,mixed>>
 */
function local_heyday_courseplayer_gettingstarted_definitions(stdClass $course, context_course $context, array $lessongroups): array {
    $overviewcontent = '';
    if (!empty($course->summary)) {
        $overviewcontent = format_text($course->summary, $course->summaryformat, ['context' => $context, 'overflowdiv' => true]);
    }
    if (trim(strip_tags($overviewcontent)) === '') {
        $overviewcontent = html_writer::tag('p', 'Welcome to the Short Term Certification Training course.') .
            html_writer::tag('p', 'Use this overview to understand the course structure before you begin the lessons, discussions, quizzes, and assessments.') .
            html_writer::start_tag('ol') .
            html_writer::tag('li', get_string('gettingstarted', 'local_heyday_courseplayer')) .
            html_writer::tag('li', get_string('pretest', 'local_heyday_courseplayer')) .
            html_writer::tag('li', get_string('lessons', 'local_heyday_courseplayer')) .
            html_writer::tag('li', get_string('resources', 'local_heyday_courseplayer')) .
            html_writer::tag('li', get_string('finalexam', 'local_heyday_courseplayer')) .
            html_writer::end_tag('ol');
    }

    $syllabuscontent = '';
    if (empty($lessongroups)) {
        $syllabuscontent = html_writer::tag('p', get_string('noitemsfound', 'local_heyday_courseplayer'));
    } else {
        $syllabuscontent .= html_writer::tag('p', 'The syllabus summarizes the lesson sequence and the required course flow.');
        $syllabuscontent .= html_writer::start_tag('ol', ['class' => 'heyday-gs-lesson-list']);
        foreach ($lessongroups as $group) {
            $syllabuscontent .= html_writer::tag('li', format_string($group['name']));
        }
        $syllabuscontent .= html_writer::end_tag('ol');
    }

    $navigatingcontent = html_writer::tag('p', 'Use the course menu on the left side of the page to move through the course.') .
        html_writer::tag('p', 'The main sequence is Home, Scores, Discussions, Getting Started, Pretest, Lessons, Resources, and Final Exam.') .
        html_writer::tag('p', 'Blue links are available. Gray locked items remain visible but are not clickable until their release date. Completed items show a green checkmark.');

    return [
        'overview' => [
            'idnumber' => 'GS_COURSE_OVERVIEW',
            'title' => get_string('courseoverview', 'local_heyday_courseplayer'),
            'sectiontitle' => get_string('gettingstarted', 'local_heyday_courseplayer'),
            'nextkey' => 'syllabus',
            'nexttitle' => get_string('syllabus', 'local_heyday_courseplayer'),
            'nextpage' => 'gettingstarted',
            'content' => $overviewcontent,
        ],
        'syllabus' => [
            'idnumber' => 'GS_SYLLABUS',
            'title' => get_string('syllabus', 'local_heyday_courseplayer'),
            'sectiontitle' => get_string('gettingstarted', 'local_heyday_courseplayer'),
            'nextkey' => 'navigating',
            'nexttitle' => get_string('navigatingcourse', 'local_heyday_courseplayer'),
            'nextpage' => 'gettingstarted',
            'content' => $syllabuscontent,
        ],
        'navigating' => [
            'idnumber' => 'GS_NAVIGATING_THIS_COURSE',
            'title' => get_string('navigatingcourse', 'local_heyday_courseplayer'),
            'sectiontitle' => get_string('gettingstarted', 'local_heyday_courseplayer'),
            'nextkey' => 'pretest',
            'nexttitle' => get_string('pretest', 'local_heyday_courseplayer'),
            'nextpage' => 'pretest',
            'content' => $navigatingcontent,
        ],
    ];
}

/**
 * Find the Getting Started cm by idnumber.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param string $idnumber Activity idnumber.
 * @return cm_info|null
 */
function local_heyday_courseplayer_gettingstarted_cm(course_modinfo $modinfo, string $idnumber): ?cm_info {
    foreach ($modinfo->get_cms() as $cm) {
        if (!empty($cm->idnumber) && $cm->idnumber === $idnumber) {
            return $cm;
        }
    }

    return null;
}

/**
 * Mark the opened Getting Started page complete, matching the standalone
 * local_heyday_gettingstarted behavior.
 *
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param string $gspage Current Getting Started page key.
 * @param array<string,array<string,mixed>> $defs Getting Started page definitions.
 * @return void
 */
function local_heyday_courseplayer_mark_gettingstarted_complete(
    completion_info $completion,
    course_modinfo $modinfo,
    string $gspage,
    array $defs
): void {
    global $USER;

    if (isguestuser() || empty($defs[$gspage]['idnumber'])) {
        return;
    }

    $cm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$defs[$gspage]['idnumber']);

    if (!$cm || !$cm->available || !$cm->uservisible) {
        return;
    }

    if (!$completion->is_enabled($cm)) {
        return;
    }

    try {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    } catch (Throwable $e) {
        // Keep the player safe if completion is temporarily unavailable.
    }
}

/**
 * Render one ed2go-style Getting Started page inside the master player.
 *
 * @param stdClass $course Course record.
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param string $gspage Current Getting Started page key.
 * @return string
 */
function local_heyday_courseplayer_render_gettingstarted(
    stdClass $course,
    completion_info $completion,
    course_modinfo $modinfo,
    context_course $context,
    array $lessongroups,
    string $gspage
): string {
    $defs = local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups);

    if (!isset($defs[$gspage])) {
        $gspage = 'overview';
    }

    $current = $defs[$gspage];
    $currentcm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$current['idnumber']);

    $currentstatus = $currentcm ? local_heyday_courseplayer_completion_status($completion, $currentcm) : [
        'class' => 'completed',
        'label' => get_string('completed', 'local_heyday_courseplayer'),
        'icon' => '✓',
    ];

    if ($current['nextpage'] === 'gettingstarted') {
        $nexturl = local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => $current['nextkey']]);
    } else {
        $nexturl = local_heyday_courseplayer_url($course, (string)$current['nextpage']);
    }

    $output = html_writer::start_div('heyday-gs-master heyday-gs-reference-layout');

    // Content is placed directly inside the reusable master shell card.
    // Completion, Undo and Next Up are rendered by master_footer.mustache so
    // Getting Started pages match normal lesson/activity pages and future
    // local_heyday_* plugins inherit the same footer structure.
    $output .= html_writer::div($current['content'], 'heyday-gs-content heyday-gs-reference-content');

    $output .= html_writer::end_div();

    return $output;
}

/**
 * Render resources page.
 *
 * @param stdClass $course Course record.
 * @param completion_info $completion Completion object.
 * @param array<int,array<string,mixed>> $resourceitems Resource items.
 * @return string
 */
function local_heyday_courseplayer_render_resources(stdClass $course, completion_info $completion, array $resourceitems): string {
    if (empty($resourceitems)) {
        return html_writer::div(html_writer::tag('p', get_string('noitemsfound', 'local_heyday_courseplayer')), 'heyday-empty-state');
    }

    // SVG icons.
    $icons = [
        'file'     => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 2h9l5 5v15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h1zm8 0v5h5M8 11h8M8 14h8M8 17h5"/></svg>',
        'url'      => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>',
        'folder'   => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/></svg>',
        'page'     => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4"/></svg>',
        'h5p'      => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3l14 9-14 9V3z"/></svg>',
        'book'     => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 6.25278V19.2528M12 6.25278C10.8321 5.47686 9.24649 5 7.5 5C5.75351 5 4.16789 5.47686 3 6.25278V19.2528C4.16789 18.4769 5.75351 18 7.5 18C9.24649 18 10.8321 18.4769 12 19.2528M12 6.25278C13.1679 5.47686 14.7535 5 16.5 5C18.2465 5 19.8321 5.47686 21 6.25278V19.2528C19.8321 18.4769 18.2465 18 16.5 18C14.7535 18 13.1679 18.4769 12 19.2528"/></svg>',
        'default'  => '<svg class="hd-res-type-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM6 4V20H18V4H6ZM8 7H16V9H8V7ZM8 11H16V13H8V11ZM8 15H14V17H8V15Z"/></svg>',
    ];
    $checkicon = '<span class="hd-res-check" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M9.1 16.6L4.9 12.4L3.5 13.8L9.1 19.4L20.8 7.7L19.4 6.3L9.1 16.6Z"></path></svg></span>';
    $lockicon  = '<span class="hd-res-lock" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M17 9H16V7C16 4.8 14.2 3 12 3C9.8 3 8 4.8 8 7V9H7C5.9 9 5 9.9 5 11V20C5 21.1 5.9 22 7 22H17C18.1 22 19 21.1 19 20V11C19 9.9 18.1 9 17 9ZM10 7C10 5.9 10.9 5 12 5C13.1 5 14 5.9 14 7V9H10V7Z"></path></svg></span>';

    $typelabels = [
        'resource'    => 'File',
        'url'         => 'Link',
        'folder'      => 'Folder',
        'page'        => 'Page',
        'h5pactivity' => 'Interactive',
        'book'        => 'Book',
    ];

    $output = html_writer::start_div('hd-resources-page');
    $output .= html_writer::start_div('hd-resources-list');

    foreach ($resourceitems as $item) {
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }

        $title     = local_heyday_courseplayer_item_title($item);
        $available = local_heyday_courseplayer_item_available($item);
        $status    = local_heyday_courseplayer_completion_status($completion, $cm);
        $iscomplete = ($status['class'] === 'completed');
        $islocked   = !$available;

        $typekey   = $cm->modname;
        $typelabel = $typelabels[$typekey] ?? ucfirst($cm->modname);
        $icon      = $icons[$typekey] ?? ($typekey === 'h5pactivity' ? $icons['h5p'] : $icons['default']);

        $rowclasses = ['hd-resource-row'];
        if ($islocked)   { $rowclasses[] = 'is-locked'; }
        if ($iscomplete) { $rowclasses[] = 'is-completed'; }

        $output .= html_writer::start_div(implode(' ', $rowclasses));

        // Left: icon + title + type label.
        $output .= html_writer::start_div('hd-resource-left');
        $output .= $icon;
        $output .= html_writer::start_div('hd-resource-main');

        if ($islocked) {
            $output .= html_writer::tag('span', s($title), ['class' => 'hd-resource-name']);
        } else {
            $output .= html_writer::link(
                local_heyday_courseplayer_item_url($course, $item),
                s($title),
                ['class' => 'hd-resource-name']
            );
        }

        $output .= html_writer::div(s($typelabel), 'hd-resource-type');

        if ($islocked) {
            $output .= html_writer::div(s(local_heyday_courseplayer_locked_message($cm)), 'hd-resource-release-note');
        }

        $output .= html_writer::end_div(); // .hd-resource-main
        $output .= html_writer::end_div(); // .hd-resource-left

        // Right: status indicator.
        $output .= html_writer::start_div('hd-resource-right');
        if ($islocked) {
            $output .= $lockicon;
        } else if ($iscomplete) {
            $output .= $checkicon;
        } else {
            $output .= html_writer::link(
                local_heyday_courseplayer_item_url($course, $item),
                get_string('openresource', 'local_heyday_courseplayer'),
                ['class' => 'hd-resource-open-btn']
            );
        }
        $output .= html_writer::end_div(); // .hd-resource-right

        $output .= html_writer::end_div(); // .hd-resource-row
    }

    $output .= html_writer::end_div(); // .hd-resources-list
    $output .= html_writer::end_div(); // .hd-resources-page
    return $output;
}

/**
 * Render the ed2go-style pretest card inside the player.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Pretest item.
 * @param completion_info $completion Moodle completion object.
 * @return string
 */
function local_heyday_courseplayer_render_pretest_card(
    stdClass $course,
    array $item,
    completion_info $completion,
    array $lessongroups = []
): string {
    global $DB, $USER;

    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm) {
        return '';
    }

    // --- Quiz attempt state (mirrors heyday_pretest/view.php logic) ---
    $inprogressattempt = null;
    $finishedattempt   = null;

    if ($cm->modname === 'quiz') {
        $inprogressrecords = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
           ORDER BY timemodified DESC, attempt DESC",
            ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'inprogress'],
            0, 1
        );
        $inprogressattempt = reset($inprogressrecords) ?: null;

        $finishedrecords = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
           ORDER BY timemodified DESC, attempt DESC",
            ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'finished'],
            0, 1
        );
        $finishedattempt = reset($finishedrecords) ?: null;
    }

    // --- Button (Start / Resume / Review Results) ---
    if ($inprogressattempt) {
        $buttontext = 'Resume';
        $buttonurl  = new moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $inprogressattempt->id,
            'cmid'    => $cm->id,
        ]);
    } else if ($finishedattempt) {
        $buttontext = 'Review Results';
        $buttonurl  = new moodle_url('/mod/quiz/review.php', [
            'attempt' => $finishedattempt->id,
        ]);
    } else {
        $buttontext = 'Start';
        $buttonurl  = new moodle_url('/mod/quiz/startattempt.php', [
            'cmid'    => $cm->id,
            'sesskey' => sesskey(),
        ]);
    }

    // --- Skip It → first available lesson item ---
    $skipurl  = local_heyday_courseplayer_url($course, 'lessons');
    $nextname = get_string('lessons', 'local_heyday_courseplayer');
    $nextsect = 'Lesson 1';
    foreach ($lessongroups as $group) {
        foreach ($group['items'] ?? [] as $litem) {
            $lcm = local_heyday_courseplayer_item_cm($litem);
            if ($lcm) {
                $skipurl  = local_heyday_courseplayer_item_url($course, $litem);
                $nextname = format_string($lcm->name);
                break 2;
            }
        }
    }

    // --- Course heading ---
    $courseheading = format_string($course->fullname);

    // --- Button / iframe URL ---
    $iframeurl = null;
    if ($inprogressattempt) {
        $iframeurl = (new moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $inprogressattempt->id,
            'cmid'    => $cm->id,
        ]))->out(false);
    } else if ($finishedattempt) {
        $iframeurl = (new moodle_url('/mod/quiz/review.php', [
            'attempt' => $finishedattempt->id,
        ]))->out(false);
    }

    $starturl    = (new moodle_url('/mod/quiz/startattempt.php'))->out(false);
    $iframeurljs = json_encode($iframeurl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $sesskey     = sesskey();

    // --- Build HTML ---
    ob_start();
    ?>
<div class="hd-pretest-page" id="hd-pretest-page">

  <!-- ── Landing card ───────────────────────────────────────────────────── -->
  <!-- Note: back/bookmark/print/fullscreen topbar + course kicker + h1     -->
  <!-- are already rendered by master_shell / master_header.mustache.       -->
  <div id="hdPretestCardArea">
    <section class="hd-pretest-card">

      <div class="hd-instructions-toggle-wrap">
        <button type="button" class="hd-instructions-toggle" id="hdPretestInstructionsToggle" aria-expanded="true">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          <span>Show / Hide Instructions</span>
        </button>
      </div>

      <div class="hd-pretest-body hd-instructions-panel" id="hdPretestInstructionsPanel">
        <p>This pretest is optional, and it's meant to help you gauge how much you already know about the subject matter of this course.</p>
        <p>As you go through the pretest, you'll be able to save your answer choices and change them up until you submit your pretest for a score.
           To exit the pretest, click the <strong>Save and Close</strong> button at the bottom of the page.
           To submit the pretest, click the <strong>Submit</strong> button at the bottom of the page.
           Once you click Submit you will be asked to confirm you are ready to submit the pretest.
           Upon clicking Submit, you will be presented with your score for the pretest.</p>
        <div class="hd-pretest-rules">
          <ul>
            <li>You have one attempt.
              <ul>
                <li>Your grade is determined by your only attempt.</li>
                <li>This is not for credit and does not affect your overall grade.</li>
              </ul>
            </li>
          </ul>
        </div>
      </div>

      <div class="hd-pretest-actions">
        <a class="hd-skip-link" href="<?php echo $skipurl->out(false); ?>">Skip It</a>

        <?php if (!$inprogressattempt && !$finishedattempt): ?>
          <form id="hdPretestStartForm" method="post"
                action="<?php echo $starturl; ?>"
                target="_top">
            <input type="hidden" name="cmid"     value="<?php echo (int)$cm->id; ?>">
            <input type="hidden" name="sesskey"  value="<?php echo s($sesskey); ?>">
            <button type="submit" class="hd-primary-btn">Start</button>
          </form>
        <?php else: ?>
          <a class="hd-primary-btn" href="<?php echo s($iframeurl ?? ''); ?>">
            <?php echo s($buttontext); ?>
          </a>
        <?php endif; ?>
      </div>

    </section>

  </div><!-- #hdPretestCardArea -->
  <!-- Note: Activity Complete + Next Up are rendered by master_footer.mustache
       below this card, matching the same structure used for lesson pages. -->


</div><!-- .hd-pretest-page -->

<script>
(function(){
  // ── Instructions toggle ────────────────────────────────────────────────
  var toggle = document.getElementById('hdPretestInstructionsToggle');
  var panel  = document.getElementById('hdPretestInstructionsPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', function(){
      var hidden = panel.classList.toggle('is-hidden');
      toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
  }
})();
</script>
    <?php
    return ob_get_clean();
}

/**
 * Render the ed2go-style lesson quiz card inside the player.
 *
 * Mirrors render_pretest_card() but for a quiz embedded in a lesson group
 * child section (e.g. "Lesson 1 Quiz"). The iframe's "Save and Close" returns
 * to ?page=lessonquiz&cmid=N so the outer page reloads the landing card.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Lesson quiz item (type='lessonquiz').
 * @param completion_info $completion Moodle completion object.
 * @return string
 */
function local_heyday_courseplayer_render_lesson_quiz_card(
    stdClass $course,
    array $item,
    completion_info $completion
): string {
    global $DB, $USER;

    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm) {
        return '';
    }

    $inprogressattempt = null;
    $finishedattempt   = null;

    if ($cm->modname === 'quiz') {
        $inprogressrecords = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
           ORDER BY timemodified DESC, attempt DESC",
            ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'inprogress'],
            0, 1
        );
        $inprogressattempt = reset($inprogressrecords) ?: null;

        $finishedrecords = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
           ORDER BY timemodified DESC, attempt DESC",
            ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'finished'],
            0, 1
        );
        $finishedattempt = reset($finishedrecords) ?: null;
    }

    if ($inprogressattempt) {
        $buttontext = 'Resume';
        $iframeurl = (new moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $inprogressattempt->id,
            'cmid'    => $cm->id,
        ]))->out(false);
    } else if ($finishedattempt) {
        $buttontext = 'Review Results';
        $iframeurl = (new moodle_url('/mod/quiz/review.php', [
            'attempt' => $finishedattempt->id,
        ]))->out(false);
    } else {
        $buttontext = 'Start Quiz';
        $iframeurl = null;
    }

    $starturl    = (new moodle_url('/mod/quiz/startattempt.php'))->out(false);
    $iframeurljs = json_encode($iframeurl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $sesskey     = sesskey();

    ob_start();
    ?>
<div class="hd-pretest-page" id="hd-lessonquiz-page">

  <div id="hdLessonQuizCardArea">
    <section class="hd-pretest-card">

      <div class="hd-instructions-toggle-wrap">
        <button type="button" class="hd-instructions-toggle" id="hdLessonQuizInstructionsToggle" aria-expanded="true">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          <span>Show / Hide Instructions</span>
        </button>
      </div>

      <div class="hd-pretest-body hd-instructions-panel" id="hdLessonQuizInstructionsPanel">
        <p>This quiz covers the material from this lesson. Answer all questions, then click <strong>Submit Answers</strong>.</p>
        <p>To save your progress and return later, click <strong>Save and Close</strong> at the bottom of the page.</p>
      </div>

      <div class="hd-pretest-actions">
        <?php if (!$inprogressattempt && !$finishedattempt): ?>
          <form id="hdLessonQuizStartForm" method="post"
                action="<?php echo $starturl; ?>"
                target="_top">
            <input type="hidden" name="cmid"    value="<?php echo (int)$cm->id; ?>">
            <input type="hidden" name="sesskey" value="<?php echo s($sesskey); ?>">
            <button type="submit" class="hd-primary-btn">Start Quiz</button>
          </form>
        <?php else: ?>
          <a class="hd-primary-btn" href="<?php echo s($iframeurl ?? ''); ?>">
            <?php echo s($buttontext); ?>
          </a>
        <?php endif; ?>
      </div>

    </section>
  </div>


</div>

<script>
(function(){
  // ── Instructions toggle ────────────────────────────────────────────────
  var toggle = document.getElementById('hdLessonQuizInstructionsToggle');
  var panel  = document.getElementById('hdLessonQuizInstructionsPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', function(){
      var hidden = panel.classList.toggle('is-hidden');
      toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
  }
})();
</script>
    <?php
    return ob_get_clean();
}

/**
 * Render the Next Steps for Completion page.
 *
 * Shows after the Final Exam is submitted. Looks for a real "Next Steps"
 * Moodle activity; if found, offers a Launch Activity button. Otherwise
 * shows a built-in completion card with certificate and evaluation placeholders.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed>|null $finalitem Final Exam item (may be null).
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course module info.
 * @param context_course $context Course context.
 * @return string
 */
function local_heyday_courseplayer_render_nextsteps_card(
    stdClass $course,
    ?array $finalitem,
    completion_info $completion,
    course_modinfo $modinfo,
    context_course $context
): string {
    global $DB, $USER;

    // --- Determine pass/fail state from Final Exam ---
    $passed = false;
    $pct    = null;
    if ($finalitem) {
        $finalcm = local_heyday_courseplayer_item_cm($finalitem);
        if ($finalcm && $finalcm->modname === 'quiz') {
            $quiz = $DB->get_record('quiz', ['id' => $finalcm->instance], '*', IGNORE_MISSING);
            if ($quiz) {
                $finishedrecords  = $DB->get_records_sql(
                    "SELECT * FROM {quiz_attempts}
                      WHERE quiz = :quizid AND userid = :userid AND state = 'finished' AND preview = 0
                   ORDER BY timemodified DESC, attempt DESC",
                    ['quizid' => $finalcm->instance, 'userid' => $USER->id],
                    0, 1
                );
                $finishedattempt = reset($finishedrecords) ?: null;
                if ($finishedattempt) {
                    $gradeitem = $DB->get_record_sql(
                        "SELECT gi.grademax, gi.gradepass, gg.finalgrade
                           FROM {grade_items} gi
                           JOIN {grade_grades} gg ON gg.itemid = gi.id
                          WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
                            AND gi.iteminstance = :quizid AND gg.userid = :userid",
                        ['quizid' => $finalcm->instance, 'userid' => $USER->id],
                        IGNORE_MISSING
                    );
                    if ($gradeitem && isset($gradeitem->finalgrade)) {
                        $max   = max((float)($gradeitem->grademax ?? 100), 1);
                        $pct   = round((float)$gradeitem->finalgrade / $max * 100, 1);
                        $gpass  = (float)($gradeitem->gradepass ?? 0);
                        $passed = ($gpass > 0) ? ((float)$gradeitem->finalgrade >= $gpass) : true;
                    }
                }
            }
        }
    }

    // --- Look for a real "Next Steps" Moodle activity in the course ---
    $nextstepscm = null;
    foreach ($modinfo->cms as $candidate) {
        if (!local_heyday_courseplayer_should_show_cm($candidate, $context)) {
            continue;
        }
        if (preg_match('/next\s+steps?\b/i', $candidate->name)) {
            $nextstepscm = $candidate;
            break;
        }
    }

    // --- Look for a customcert activity ---
    $certcm = null;
    foreach ($modinfo->cms as $candidate) {
        if ($candidate->modname === 'customcert' && $candidate->uservisible) {
            $certcm = $candidate;
            break;
        }
    }

    $homeurl = local_heyday_courseplayer_url($course, 'home')->out(false);

    ob_start();
    ?>
<div class="hd-nextsteps-page">
  <section class="hd-nextsteps-card">

    <?php if ($pct !== null): ?>
      <div class="hd-nextsteps-grade <?php echo $passed ? 'is-pass' : 'is-fail'; ?>">
        <?php if ($passed): ?>
          <span class="hd-nextsteps-grade-icon">&#10003;</span>
          <span>Final Exam score: <strong><?php echo $pct; ?>%</strong> &mdash; Passed</span>
        <?php else: ?>
          <span class="hd-nextsteps-grade-icon">&#10007;</span>
          <span>Final Exam score: <strong><?php echo $pct; ?>%</strong> &mdash; Did not pass</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="hd-nextsteps-body">
      <?php if ($passed || $pct === null): ?>
        <p>Congratulations on completing the course! Please review the next steps below to receive credit for your work.</p>
      <?php else: ?>
        <p>Your final exam score did not meet the passing requirement. Please contact your instructor or return to the Final Exam to review your results.</p>
      <?php endif; ?>
    </div>

    <div class="hd-nextsteps-items">

      <!-- Completion -->
      <div class="hd-nextsteps-item">
        <div class="hd-nextsteps-item-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="hd-nextsteps-item-body">
          <h3>Completion</h3>
          <p>Your completion record will be updated automatically once you meet all course requirements.</p>
        </div>
      </div>

      <!-- Certificate -->
      <div class="hd-nextsteps-item">
        <div class="hd-nextsteps-item-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
        </div>
        <div class="hd-nextsteps-item-body">
          <h3>Certificate</h3>
          <?php if ($certcm): ?>
            <p>Your certificate is ready to download.</p>
            <a class="hd-primary-btn" href="<?php echo (new moodle_url('/mod/customcert/view.php', ['id' => $certcm->id]))->out(false); ?>" target="_top">
              Download Certificate
            </a>
          <?php else: ?>
            <p>Your certificate will be available once your completion is confirmed.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Evaluation -->
      <div class="hd-nextsteps-item">
        <div class="hd-nextsteps-item-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="hd-nextsteps-item-body">
          <h3>Course Evaluation</h3>
          <?php if ($nextstepscm): ?>
            <p>Please take a few minutes to share your feedback on this course.</p>
            <a class="hd-primary-btn" href="<?php echo (new moodle_url('/mod/' . $nextstepscm->modname . '/view.php', ['id' => $nextstepscm->id]))->out(false); ?>" target="_top">
              <?php echo s(format_string($nextstepscm->name)); ?>
            </a>
          <?php else: ?>
            <p>Course evaluation will be available after completion is confirmed.</p>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- .hd-nextsteps-items -->

    <div class="hd-nextsteps-footer">
      <a class="hd-secondary-btn" href="<?php echo $homeurl; ?>">Return to Course Home</a>
    </div>

  </section>
</div>
    <?php
    return ob_get_clean();
}

/**
 * Render the ed2go-style Final Exam landing card.
 *
 * Shows Start / Resume / Review Results and links out to the native Moodle
 * quiz attempt/review pages (styled by local_heyday_quizskin).
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Final Exam item.
 * @param completion_info $completion Completion object.
 * @return string
 */
function local_heyday_courseplayer_render_final_exam_card(
    stdClass $course,
    array $item,
    completion_info $completion
): string {
    global $DB, $USER;

    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm || $cm->modname !== 'quiz') {
        return html_writer::div(
            html_writer::tag('p', get_string('finalnotfound', 'local_heyday_courseplayer')),
            'heyday-empty-state'
        );
    }

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$quiz) {
        return html_writer::div(
            html_writer::tag('p', get_string('finalnotfound', 'local_heyday_courseplayer')),
            'heyday-empty-state'
        );
    }

    // Check whether the quiz has any questions.
    $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
    if ($questioncount === 0) {
        return html_writer::div(
            html_writer::tag('p', 'The Final Exam has not been set up yet. No questions have been added.'),
            'heyday-empty-state'
        );
    }

    // Attempt state.
    $inprogressrecords = $DB->get_records_sql(
        "SELECT * FROM {quiz_attempts}
          WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
       ORDER BY timemodified DESC, attempt DESC",
        ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'inprogress'],
        0, 1
    );
    $inprogressattempt = reset($inprogressrecords) ?: null;

    $finishedrecords = $DB->get_records_sql(
        "SELECT * FROM {quiz_attempts}
          WHERE quiz = :quizid AND userid = :userid AND state = :state AND preview = 0
       ORDER BY timemodified DESC, attempt DESC",
        ['quizid' => $cm->instance, 'userid' => $USER->id, 'state' => 'finished'],
        0, 1
    );
    $finishedattempt = reset($finishedrecords) ?: null;

    if ($inprogressattempt) {
        $buttontext = 'Resume';
        $actionurl  = new moodle_url('/mod/quiz/attempt.php', [
            'attempt' => $inprogressattempt->id,
            'cmid'    => $cm->id,
        ]);
    } else if ($finishedattempt) {
        $buttontext = 'Review Results';
        $actionurl  = new moodle_url('/mod/quiz/review.php', [
            'attempt' => $finishedattempt->id,
        ]);
    } else {
        $buttontext = 'Start Final Exam';
        $actionurl  = null;
    }

    $startformurl = (new moodle_url('/mod/quiz/startattempt.php'))->out(false);
    $actionurlout = $actionurl ? $actionurl->out(false) : '';
    $sesskey      = sesskey();

    // Grade display when finished.
    $gradehtml  = '';
    $gradepassdisplay = '';
    if ($finishedattempt) {
        $gradeitem = $DB->get_record_sql(
            "SELECT gi.grademax, gi.gradepass, gg.finalgrade
               FROM {grade_items} gi
               JOIN {grade_grades} gg ON gg.itemid = gi.id
              WHERE gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.iteminstance = :quizid
                AND gg.userid = :userid",
            ['quizid' => $cm->instance, 'userid' => $USER->id],
            IGNORE_MISSING
        );
        if ($gradeitem && isset($gradeitem->finalgrade)) {
            $grademax  = max((float)($gradeitem->grademax ?? 100), 1);
            $pct       = round((float)$gradeitem->finalgrade / $grademax * 100, 1);
            // gradepass from grade_items is already on the same scale as finalgrade.
            $gradepass = (float)($gradeitem->gradepass ?? 0);
            $passed    = ($gradepass > 0) ? ((float)$gradeitem->finalgrade >= $gradepass) : true;
            $gradehtml = '<p class="hd-finalexam-grade ' . ($passed ? 'is-pass' : 'is-fail') . '">'
                . 'Score: <strong>' . $pct . '%</strong>'
                . ($passed ? ' &mdash; <span class="hd-grade-pass">Passed</span>' : ' &mdash; <span class="hd-grade-fail">Did not pass</span>')
                . '</p>';
            if ($gradepass > 0 && $grademax > 0) {
                $gradepassdisplay = round($gradepass / $grademax * 100, 0) . '%';
            }
        }
    }

    ob_start();
    ?>
<div class="hd-pretest-page" id="hd-finalexam-page">

  <div id="hdFinalExamCardArea">
    <section class="hd-pretest-card">

      <div class="hd-instructions-toggle-wrap">
        <button type="button" class="hd-instructions-toggle" id="hdFinalExamInstructionsToggle" aria-expanded="true">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          <span>Show / Hide Instructions</span>
        </button>
      </div>

      <div class="hd-pretest-body hd-instructions-panel" id="hdFinalExamInstructionsPanel">
        <p>You must complete the final exam and receive a satisfactory score to complete this course.</p>
        <p>As you go through the exam, you will be able to save your answer choices and change them up until you submit your final exam for grading.
           To exit without submitting, click <strong>Save and Close</strong> at the bottom of the page.
           To submit your final exam for grading, click <strong>Submit Answers</strong> at the bottom of the page.</p>
        <div class="hd-pretest-rules">
          <ul>
            <li>You have one attempt.
              <ul>
                <li>Your grade is determined by your only attempt.</li>
                <li>Do not click Submit until you are absolutely certain of your answers.</li>
                <?php if ($gradepassdisplay !== ''): ?>
                <li>Passing score: <?php echo s($gradepassdisplay); ?></li>
                <?php endif; ?>
              </ul>
            </li>
          </ul>
        </div>
        <?php echo $gradehtml; ?>
      </div>

      <div class="hd-pretest-actions">
        <?php if (!$inprogressattempt && !$finishedattempt): ?>
          <form id="hdFinalExamStartForm" method="post"
                action="<?php echo $startformurl; ?>"
                target="_top">
            <input type="hidden" name="cmid"    value="<?php echo (int)$cm->id; ?>">
            <input type="hidden" name="sesskey" value="<?php echo s($sesskey); ?>">
            <button type="submit" class="hd-primary-btn">Start Final Exam</button>
          </form>
        <?php else: ?>
          <a class="hd-primary-btn" href="<?php echo s($actionurlout); ?>" target="_top">
            <?php echo s($buttontext); ?>
          </a>
        <?php endif; ?>
      </div>

    </section>
  </div>

</div>

<script>
(function(){
  var toggle = document.getElementById('hdFinalExamInstructionsToggle');
  var panel  = document.getElementById('hdFinalExamInstructionsPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', function(){
      var hidden = panel.classList.toggle('is-hidden');
      toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
  }
})();
</script>
    <?php
    return ob_get_clean();
}

/**
 * Render an ed2go-style assignment landing card.
 *
 * Shows submission status, due date, and grade, then links out to the native
 * Moodle assignment page (mod/assign/view.php) for the actual submission UI.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Assignment item.
 * @return string
 */
function local_heyday_courseplayer_render_assignment_card(stdClass $course, array $item): string {
    global $DB, $USER;

    $cm = local_heyday_courseplayer_item_cm($item);
    if (!$cm || $cm->modname !== 'assign') {
        return '';
    }

    $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$assign) {
        return '';
    }

    // Submission status.
    $submission = $DB->get_record('assign_submission', [
        'assignment' => $assign->id,
        'userid'     => $USER->id,
        'latest'     => 1,
    ], '*', IGNORE_MISSING);

    $substatusraw  = $submission ? (string)($submission->status ?? '') : '';
    $substatustext = '';
    $substatusclass = 'hd-assign-status-none';
    if ($substatusraw === 'submitted') {
        $substatustext  = 'Submitted';
        $substatusclass = 'hd-assign-status-submitted';
    } else if ($substatusraw === 'draft') {
        $substatustext  = 'Draft (not submitted)';
        $substatusclass = 'hd-assign-status-draft';
    } else {
        $substatustext  = 'Not submitted';
        $substatusclass = 'hd-assign-status-none';
    }

    // Grade status.
    $graderecord = $DB->get_record('assign_grades', [
        'assignment' => $assign->id,
        'userid'     => $USER->id,
        'attemptnumber' => -1,
    ], '*', IGNORE_MISSING);
    if (!$graderecord) {
        // Fallback: latest grade for this user.
        $grades = $DB->get_records('assign_grades',
            ['assignment' => $assign->id, 'userid' => $USER->id],
            'attemptnumber DESC', '*', 0, 1);
        $graderecord = reset($grades) ?: null;
    }

    $gradetext = '';
    if ($graderecord && isset($graderecord->grade) && (float)$graderecord->grade >= 0) {
        $maxgrade   = (float)($assign->grade > 0 ? $assign->grade : 100);
        $earned     = (float)$graderecord->grade;
        $gradetext  = round($earned, 1) . ' / ' . round($maxgrade, 1);
    }

    // Dates.
    $duetext     = '';
    $cutofftext  = '';
    if (!empty($assign->duedate)) {
        $duetext = userdate((int)$assign->duedate, get_string('strftimedatetimeshort', 'langconfig'));
    }
    if (!empty($assign->cutoffdate)) {
        $cutofftext = userdate((int)$assign->cutoffdate, get_string('strftimedatetimeshort', 'langconfig'));
    }

    // Intro.
    $introhtml = '';
    if (!empty($assign->intro)) {
        $introhtml = format_module_intro('assign', $assign, $cm->id, false);
    }

    $openurl = (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false);

    ob_start();
    ?>
<div class="hd-assign-page">

  <div class="hd-assign-card">

    <?php if ($introhtml !== ''): ?>
    <div class="hd-instructions-toggle-wrap">
      <button type="button" class="hd-instructions-toggle" id="hdAssignInstructionsToggle" aria-expanded="true">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <span>Show / Hide Instructions</span>
      </button>
    </div>
    <div class="hd-pretest-body hd-instructions-panel" id="hdAssignInstructionsPanel">
      <?php echo $introhtml; ?>
    </div>
    <?php endif; ?>

    <table class="hd-assign-status-table">
      <tbody>
        <tr>
          <th scope="row">Submission status</th>
          <td><span class="hd-assign-status-badge <?php echo s($substatusclass); ?>"><?php echo s($substatustext); ?></span></td>
        </tr>
        <?php if ($duetext !== ''): ?>
        <tr>
          <th scope="row">Due date</th>
          <td><?php echo s($duetext); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($cutofftext !== ''): ?>
        <tr>
          <th scope="row">Last submission date</th>
          <td><?php echo s($cutofftext); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($gradetext !== ''): ?>
        <tr>
          <th scope="row">Grade</th>
          <td><?php echo s($gradetext); ?></td>
        </tr>
        <?php else: ?>
        <tr>
          <th scope="row">Grade</th>
          <td class="hd-assign-no-grade">Not graded yet</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="hd-pretest-actions">
      <a class="hd-primary-btn" href="<?php echo s($openurl); ?>" target="_top">
        <?php echo ($substatusraw === 'submitted') ? 'View Submission' : 'Open Assignment'; ?>
      </a>
    </div>

  </div>

</div>

<script>
(function(){
  var toggle = document.getElementById('hdAssignInstructionsToggle');
  var panel  = document.getElementById('hdAssignInstructionsPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', function(){
      var hidden = panel.classList.toggle('is-hidden');
      toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
  }
})();
</script>
    <?php
    return ob_get_clean();
}

/**
 * Render named page content.
 *
 * @param string $pagekey Page key.
 * @param stdClass $course Course record.
 * @param completion_info $completion Completion object.
 * @param course_modinfo $modinfo Course modinfo.
 * @param context_course $context Course context.
 * @param array<int,array<string,mixed>> $lessongroups Lesson groups.
 * @param array<int,array<string,mixed>> $sequenceitems Sequence items.
 * @param array<int,cm_info> $discussioncms Discussion CMs.
 * @param array<int,array<string,mixed>> $resourceitems Resource items.
 * @param array<string,mixed>|null $pretestitem Pretest item.
 * @param array<string,mixed>|null $finalitem Final item.
 * @return string
 */
function local_heyday_courseplayer_render_named_page(
    string $pagekey,
    stdClass $course,
    completion_info $completion,
    course_modinfo $modinfo,
    context_course $context,
    array $lessongroups,
    array $sequenceitems,
    array $discussioncms,
    array $resourceitems,
    ?array $pretestitem,
    ?array $finalitem,
    string $gspage,
    string $subpage = ''
): string {
    if ($pagekey === 'home') {
        return local_heyday_courseplayer_render_home($course, $completion, $modinfo, $context, $lessongroups, $sequenceitems);
    }
    if ($pagekey === 'scores') {
        return local_heyday_courseplayer_render_scores($course, $modinfo);
    }
    if ($pagekey === 'discussions') {
        return local_heyday_courseplayer_render_discussions($course, $discussioncms);
    }
    if ($pagekey === 'gettingstarted') {
        return local_heyday_courseplayer_render_gettingstarted($course, $completion, $modinfo, $context, $lessongroups, $gspage);
    }
    if ($pagekey === 'pretest') {
        if (!$pretestitem) {
            return html_writer::div(html_writer::tag('p', get_string('pretestnotfound', 'local_heyday_courseplayer')), 'heyday-empty-state');
        }
        $cm = local_heyday_courseplayer_item_cm($pretestitem);
        if ($cm && !local_heyday_courseplayer_item_available($pretestitem)) {
            return local_heyday_courseplayer_render_locked_card(get_string('pretest', 'local_heyday_courseplayer'), local_heyday_courseplayer_locked_message($cm));
        }
        return local_heyday_courseplayer_render_pretest_card($course, $pretestitem, $completion, $lessongroups);
    }
    if ($pagekey === 'resources') {
        return local_heyday_courseplayer_render_resources($course, $completion, $resourceitems);
    }
    if ($pagekey === 'finalexam') {
        if ($subpage === 'nextsteps') {
            return local_heyday_courseplayer_render_nextsteps_card($course, $finalitem, $completion, $modinfo, $context);
        }
        if (!$finalitem) {
            return html_writer::div(html_writer::tag('p', get_string('finalnotfound', 'local_heyday_courseplayer')), 'heyday-empty-state');
        }
        $cm = local_heyday_courseplayer_item_cm($finalitem);
        if ($cm && !local_heyday_courseplayer_item_available($finalitem)) {
            return local_heyday_courseplayer_render_locked_card(get_string('finalexam', 'local_heyday_courseplayer'), local_heyday_courseplayer_locked_message_for_name(get_string('finalexam', 'local_heyday_courseplayer'), $cm));
        }
        return local_heyday_courseplayer_render_final_exam_card($course, $finalitem, $completion);
    }

    return html_writer::div(html_writer::tag('p', get_string('selectlesson', 'local_heyday_courseplayer')), 'heyday-empty-state');
}

$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);
$pretestcm = local_heyday_courseplayer_find_pretest_cm($modinfo, $context);
$finalexamcm = local_heyday_courseplayer_find_final_exam_cm($modinfo, $context);
$resourceitems = local_heyday_courseplayer_collect_resources($modinfo, $sections, $course, $context);
$discussioncms = local_heyday_courseplayer_collect_discussions($modinfo, $context);

// Getting Started pages are completed by the shared master shell after the
// learner reaches/views the full content body. Do not mark them complete here,
// otherwise the Activity complete footer appears before the content is viewed.

$pretestitem = $pretestcm ? ['type' => 'pretest', 'cm' => $pretestcm, 'depth' => 0] : null;
$finalitem = $finalexamcm ? ['type' => 'finalexam', 'cm' => $finalexamcm, 'depth' => 0] : null;
$specialitems = [];
if ($pretestitem) {
    $specialitems[] = $pretestitem;
}
if ($finalitem) {
    $specialitems[] = $finalitem;
}
$sequenceitems = local_heyday_courseplayer_flat_items($lessongroups, $specialitems);

// Direct visits to /local/heyday_courseplayer/index.php?id=COURSEID should
// behave like an ed2go learner player: open the next real Moodle activity
// from the native course structure. The dashboard remains available with
// ?page=home for teachers/admins who want it.
if ($autoplayerrequest && $cmid <= 0 && $pageid <= 0 && $completionaction === '') {
    $autoitem = local_heyday_courseplayer_next_incomplete($completion, $sequenceitems);
    if (!$autoitem) {
        $autoitem = local_heyday_courseplayer_first_available_item($lessongroups);
    }

    if ($autoitem) {
        redirect(local_heyday_courseplayer_item_url($course, $autoitem));
    }

    $pagekey = 'home';
}

if ($pagekey === 'auto') {
    $pagekey = 'home';
}

$activeitem = null;
if (in_array($pagekey, ['lesson', 'lessons'], true)) {
    $activeitem = local_heyday_courseplayer_find_requested_item($lessongroups, [], $requestedcm, $pageid);

    // If the URL carries a real Moodle cmid but that activity is not inside a
    // section collected as a HeyDay Lesson group, still open it in the player.
    // This is what lets Page activities created/edited on the native Moodle +
    // Adaptable course page render automatically in the shell.
    if (!$activeitem && $requestedcm && local_heyday_courseplayer_should_show_cm($requestedcm, $context)) {
        $activeitem = [
            'type' => 'cm',
            'cm' => $requestedcm,
            'depth' => 0,
        ];
    }

    if (!$activeitem) {
        $activeitem = local_heyday_courseplayer_first_available_item($lessongroups);
    }
} else if ($pagekey === 'pretest') {
    $activeitem = $pretestitem;
} else if ($pagekey === 'finalexam') {
    $activeitem = $finalitem;
} else if ($pagekey === 'lessonquiz') {
    $activeitem = local_heyday_courseplayer_find_requested_item($lessongroups, [], $requestedcm, 0);
    // Fallback: wrap the raw CM if it is a quiz (excluding Pretest / Final Exam).
    if (!$activeitem && $requestedcm && $requestedcm->modname === 'quiz'
            && !local_heyday_courseplayer_is_pretest_cm($requestedcm)
            && !local_heyday_courseplayer_is_final_exam_cm($requestedcm)
            && local_heyday_courseplayer_should_show_cm($requestedcm, $context)) {
        $activeitem = ['type' => 'lessonquiz', 'cm' => $requestedcm, 'depth' => 0];
    }
} else if ($pagekey === 'resources' && $requestedcm) {
    $activeitem = local_heyday_courseplayer_find_requested_item([], $resourceitems, $requestedcm, 0);

    // Direct resource cmids should also stay inside the player, especially
    // Page activities used as learner resources.
    if (!$activeitem && local_heyday_courseplayer_should_show_cm($requestedcm, $context)) {
        $activeitem = [
            'type' => 'resource',
            'cm' => $requestedcm,
            'depth' => 0,
        ];
    }
}

$activecm = $activeitem ? local_heyday_courseplayer_item_cm($activeitem) : null;
$activeislocked = $activeitem ? !local_heyday_courseplayer_item_clickable($activeitem) : false;

// Quiz activities are rendered inline by the courseplayer's generic lesson
// renderer. local_heyday_quiz acts as a helper library only; its index.php
// redirects here, so no outbound redirect is issued to avoid a loop.
$activegroupname = $activeitem ? local_heyday_courseplayer_active_group_name($lessongroups, $activeitem) : '';
if ($activegroupname === '' && $activecm) {
    try {
        $activesection = $modinfo->get_section_info($activecm->sectionnum);
        if ($activesection) {
            $activegroupname = get_section_name($course, $activesection);
        }
    } catch (Throwable $e) {
        $activegroupname = '';
    }
}
$activetitle = $activeitem ? local_heyday_courseplayer_item_title($activeitem) : get_string($pagekey, 'local_heyday_courseplayer');
if ($activeitem && $activecm && $activecm->modname === 'h5pactivity') {
    // H5P content has its own embedded title and toolbar. Hide the outer shell
    // title so the page does not show duplicate headers for inline rendering.
    $activetitle = '';
}
if (in_array($pagekey, ['lessons', 'objectives', 'assignment', 'quiz'], true)) {
    $pagekey = 'lesson';
}
if (!$activeitem && in_array($pagekey, ['home', 'scores', 'discussions', 'discussion', 'gettingstarted', 'pretest', 'resources', 'finalexam', 'lessonquiz'], true)) {
    if (in_array($pagekey, ['home', 'scores', 'discussions', 'gettingstarted', 'pretest', 'resources', 'finalexam'], true)) {
        $activetitle = get_string($pagekey, 'local_heyday_courseplayer');
    } else if ($pagekey === 'discussion' && $requestedcm) {
        $activetitle = format_string($requestedcm->name, true, ['context' => $requestedcm->context]);
    } else {
        $activetitle = 'Lesson Quiz';
    }
}
$gettingstartedcm = null;
$gettingstartednext = null;
if ($pagekey === 'gettingstarted') {
    $gsdefs = local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups);
    if (!isset($gsdefs[$gspage])) {
        $gspage = 'overview';
    }
    if (isset($gsdefs[$gspage])) {
        $gsdef = $gsdefs[$gspage];
        $activetitle = $gsdef['title'];
        $activegroupname = get_string('gettingstarted', 'local_heyday_courseplayer');
        $gettingstartedcm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$gsdef['idnumber']);

        if (($gsdef['nextpage'] ?? '') === 'gettingstarted') {
            $gettingstartednext = [
                'url' => local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => $gsdef['nextkey']]),
                'title' => $gsdef['nexttitle'],
                'section' => get_string('gettingstarted', 'local_heyday_courseplayer'),
            ];
        } else {
            $gettingstartednext = [
                'url' => local_heyday_courseplayer_url($course, (string)$gsdef['nextpage']),
                'title' => $gsdef['nexttitle'],
                'section' => get_string('pretest', 'local_heyday_courseplayer'),
            ];
        }
    }
}
$nextitem = ($activeitem && !$activeislocked) ? local_heyday_courseplayer_next_available_item($lessongroups, $activeitem, $finalitem ? [$finalitem] : []) : null;

// Pretest is not part of the lesson sequence, so next_available_item returns null.
// Always point Next Up on the pretest page to the first available lesson item.
if ($pagekey === 'pretest' && !$nextitem) {
    $nextitem = local_heyday_courseplayer_first_available_item($lessongroups);
}

$brandname = local_heyday_courseplayer_cfg('brandname', 'Heyday Training LMS');
$logourl = trim((string)local_heyday_courseplayer_cfg('logourl', ''));
$searchurl = local_heyday_courseplayer_setting_url((string)local_heyday_courseplayer_cfg('searchurl', '/local/heyday_coursesearch/search.php'), '/local/heyday_coursesearch/search.php');
$helpurl = local_heyday_courseplayer_setting_url((string)local_heyday_courseplayer_cfg('helpurl', '/local/heyday_helptour/help.php'), '/local/heyday_helptour/help.php');
$toururl = local_heyday_courseplayer_setting_url((string)local_heyday_courseplayer_cfg('toururl', '/local/heyday_helptour/tour.php'), '/local/heyday_helptour/tour.php');
$showtopbarbrand = (int)local_heyday_courseplayer_cfg('showtopbarbrand', 0) === 1;

$inlinevars = [
    '--heyday-topbar-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('topbarbg', '#050505'), '#050505'),
    '--heyday-accent' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('accentcolor', '#0073a8'), '#0073a8'),
    '--heyday-page-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('pagebg', '#f4f5f7'), '#f4f5f7'),
    '--heyday-card-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('cardbg', '#ffffff'), '#ffffff'),
    '--heyday-sidebar-width' => local_heyday_courseplayer_int(local_heyday_courseplayer_cfg('sidebarwidth', 424), 424, 400, 520) . 'px',
    '--heyday-content-max' => local_heyday_courseplayer_int(local_heyday_courseplayer_cfg('contentmaxwidth', 1120), 1120, 680, 1320) . 'px',
];
$cssvartext = 'body.local-heyday-courseplayer.local-heyday-masterplayer {';
foreach ($inlinevars as $name => $value) {
    $cssvartext .= $name . ':' . $value . ';';
}
$cssvartext .= '}';

$mainnav = [
    ['key' => 'home', 'label' => get_string('home', 'local_heyday_courseplayer'), 'iconclass' => 'home'],
    ['key' => 'scores', 'label' => get_string('scores', 'local_heyday_courseplayer'), 'iconclass' => 'scores'],
    ['key' => 'discussions', 'label' => get_string('discussions', 'local_heyday_courseplayer'), 'iconclass' => 'discussions'],
    ['key' => 'gettingstarted', 'label' => get_string('gettingstarted', 'local_heyday_courseplayer'), 'iconclass' => ''],
    ['key' => 'pretest', 'label' => get_string('pretest', 'local_heyday_courseplayer'), 'iconclass' => ''],
];

$gsnavdefs = local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups);
$gsnavstatuses = [];
$gscompleted = 0;
$gstracked = 0;
$gslocked = false;
$gsstarted = false;

foreach ($gsnavdefs as $gskey => $gsdef) {
    $gscm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$gsdef['idnumber']);

    if ($gscm) {
        $gsstatus = local_heyday_courseplayer_completion_status($completion, $gscm);
    } else {
        $gsstatus = [
            'class' => 'nottracked',
            'label' => get_string('nottracked', 'local_heyday_courseplayer'),
            'icon' => '',
        ];
    }

    $gsnavstatuses[$gskey] = $gsstatus;

    if ($gsstatus['class'] !== 'nottracked') {
        $gstracked++;
    }

    if ($gsstatus['class'] === 'completed') {
        $gscompleted++;
        $gsstarted = true;
    }

    if ($gsstatus['class'] === 'inprogress') {
        $gsstarted = true;
    }

    if ($gsstatus['class'] === 'locked') {
        $gslocked = true;
    }
}

$gsnavparentclass = 'nottracked';
$gsnavparenticon = '';

if ($gslocked) {
    $gsnavparentclass = 'locked';
    $gsnavparenticon = '🔒';
} else if ($gstracked > 0 && $gscompleted === count($gsnavdefs)) {
    $gsnavparentclass = 'completed';
    $gsnavparenticon = '✓';
} else if ($gsstarted) {
    $gsnavparentclass = 'inprogress';
}

$navstatuses = [
    'gettingstarted' => [
        'class' => $gsnavparentclass,
        'icon' => $gsnavparenticon,
    ],
];

if ($pretestitem) {
    $pretestcm = local_heyday_courseplayer_item_cm($pretestitem);

    if ($pretestcm) {
        $preteststatus = local_heyday_courseplayer_completion_status($completion, $pretestcm);
        $navstatuses['pretest'] = [
            'class' => $preteststatus['class'],
            'icon' => $preteststatus['icon'],
        ];
    }
}

$pageclasses = ['heyday-courseplayer-page', 'is-page-' . $pagekey];

// Render the sidebar once, then pass it into the reusable master shell template.
// Important: do not print $OUTPUT->header() before master_shell::open().
// master_shell::open() prepares body classes and CSS for the reusable player shell.

ob_start();
?>
<nav class="heyday-main-menu heyday-primary-menu" aria-label="Main course navigation">
    <?php foreach ($mainnav as $navitem): ?>
        <?php
        $isactive = ($pagekey === $navitem['key']);
        $navclasses = ['heyday-main-nav-link', 'is-nav-' . $navitem['key']];

        if ($navitem['iconclass'] === '') {
            $navclasses[] = 'has-no-icon';
            } else {
            $navclasses[] = 'has-icon';
            }
            if ($isactive) {
            $navclasses[] = 'is-current';
            }

        $navstatus = $navstatuses[$navitem['key']] ?? null;
        if ($navstatus && $navstatus['class'] !== 'nottracked') {
            $navclasses[] = 'has-status';
            $navclasses[] = 'is-' . $navstatus['class'];
        }
        ?>

        <a class="<?php echo s(implode(' ', $navclasses)); ?>" href="<?php echo local_heyday_courseplayer_url($course, $navitem['key'])->out(false); ?>">
           <?php if ($navitem['iconclass'] !== ''): ?>
    <span class="heyday-main-nav-icon heyday-icon-<?php echo s($navitem['iconclass']); ?>" aria-hidden="true"></span>
<?php endif; ?>

            <span class="heyday-main-nav-label"><?php echo s($navitem['label']); ?></span>

            <?php if ($navstatus && $navstatus['class'] !== 'nottracked'): ?>
                <span class="heyday-nav-status <?php echo s($navstatus['class']); ?>" aria-hidden="true">
                    <?php if (!empty($navstatus['icon'])): ?>
                        <?php echo s($navstatus['icon']); ?>
                    <?php elseif ($navstatus['class'] === 'inprogress'): ?>
                        <span class="heyday-mini-dot"></span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </a>

        <?php if ($navitem['key'] === 'gettingstarted' && $pagekey === 'gettingstarted'): ?>
            <div class="heyday-gs-sidebar-subnav" aria-label="Getting Started pages">
                <?php foreach ($gsnavdefs as $gskey => $gsdef): ?>
                    <?php
                    $gsstatus = $gsnavstatuses[$gskey] ?? [
                        'class' => 'nottracked',
                        'label' => get_string('nottracked', 'local_heyday_courseplayer'),
                        'icon' => '',
                    ];

                    $gssubclasses = ['heyday-gs-sidebar-link', 'is-' . $gsstatus['class']];

                    if ($gspage === $gskey) {
                        $gssubclasses[] = 'is-current';
                    }

                    $gssubicon = '○';
                    if ($gsstatus['class'] === 'completed') {
                        $gssubicon = '✓';
                    } else if ($gsstatus['class'] === 'locked') {
                        $gssubicon = '🔒';
                    }
                    ?>

                    <a class="<?php echo s(implode(' ', $gssubclasses)); ?>" href="<?php echo local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => $gskey])->out(false); ?>">
                        <span class="heyday-current-arrow" aria-hidden="true"></span>
                        <span class="heyday-gs-sidebar-title"><?php echo s($gsdef['title']); ?></span>
                        <span class="heyday-gs-sidebar-status <?php echo s($gsstatus['class']); ?>" aria-hidden="true">
                            <?php echo s($gssubicon); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

            <div class="heyday-lessons-label"><?php echo get_string('lessons', 'local_heyday_courseplayer'); ?></div>
            <div class="heyday-lesson-list">
                <?php foreach ($lessongroups as $group): ?>
                    <?php
                    $groupurl = local_heyday_courseplayer_group_url($course, $group);
                    $groupactive = local_heyday_courseplayer_group_is_active($group, $activeitem);
                    $groupstatus = local_heyday_courseplayer_group_status($completion, $group);
                    $groupfirstcm = local_heyday_courseplayer_group_first_cm($group);
                    $grouplocked = !$groupurl;
                    $groupclasses = ['heyday-lesson-group', 'is-' . $groupstatus['class']];
                    if ($groupactive) {
                        $groupclasses[] = 'is-active';
                    }
                    if ($grouplocked) {
                        $groupclasses[] = 'is-locked';
                    }
                    ?>

                    <details class="<?php echo s(implode(' ', $groupclasses)); ?>" name="heyday-lesson" <?php echo $groupactive ? 'open' : ''; ?>>
                        <summary>
                            <span class="heyday-group-summary-inner">
                                <?php
                                // The lesson title should be clickable when the group has an
                                // available child activity. This keeps the sidebar behavior
                                // consistent with ed2go: clicking the group opens the first
                                // available child page instead of only toggling the section.
                                ?>
                                <?php if ($groupurl && !$grouplocked): ?>
                                    <a class="heyday-lesson-group-title" href="<?php echo $groupurl->out(false); ?>"><?php echo s($group['name']); ?></a>
                                <?php else: ?>
                                    <span class="heyday-lesson-group-title<?php echo $grouplocked ? ' is-disabled' : ''; ?>"><?php echo s($group['name']); ?></span>
                                <?php endif; ?>
                                <span class="heyday-group-status <?php echo s($groupstatus['class']); ?>" aria-hidden="true">
                                    <?php echo $groupstatus['icon'] !== '' ? s($groupstatus['icon']) : '<span class="heyday-progress-dot"></span>'; ?>
                                </span>
                            </span>
                            <?php if ($grouplocked && $groupfirstcm): ?>
                                <span class="heyday-release-note"><?php echo s(local_heyday_courseplayer_locked_message_for_name($group['name'], $groupfirstcm)); ?></span>
                            <?php endif; ?>
                        </summary>

                        <div class="heyday-lesson-items">
                            <?php
                            echo local_heyday_courseplayer_render_sidebar_items(
                                $group['items'],
                                $course,
                                $completion,
                                $activeitem,
                                $caneditcourse,
                                (string)($group['sectionnum'] ?? '0')
                            );
                            ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <nav class="heyday-main-menu heyday-after-lessons-menu" aria-label="More course navigation">
                <a class="<?php echo $pagekey === 'resources' ? 'is-current' : ''; ?>" href="<?php echo local_heyday_courseplayer_url($course, 'resources')->out(false); ?>">
                    <?php echo get_string('resources', 'local_heyday_courseplayer'); ?>
                </a>

                <?php
                // Final Exam group — expandable like lesson groups.
                // Contains two children: Final Exam quiz + Next Steps for Completion.
                $finalactive    = $pagekey === 'finalexam';
                $nextstepsactive = ($pagekey === 'finalexam' && $subpage === 'nextsteps');
                $finalexamactive = ($pagekey === 'finalexam' && $subpage !== 'nextsteps');
                $finallocked    = $finalitem && !local_heyday_courseplayer_item_available($finalitem);
                $finalstatus    = $finalexamcm ? local_heyday_courseplayer_completion_status($completion, $finalexamcm) : ['class' => 'not-started', 'icon' => ''];
                $groupclasses   = ['heyday-lesson-group', 'heyday-final-exam-group', 'is-' . $finalstatus['class']];
                if ($finalactive) {
                    $groupclasses[] = 'is-active';
                }
                if ($finallocked) {
                    $groupclasses[] = 'is-locked';
                }
                ?>
                <details class="<?php echo s(implode(' ', $groupclasses)); ?>" name="heyday-lesson" <?php echo $finalactive ? 'open' : ''; ?>>
                    <summary>
                        <span class="heyday-group-summary-inner">
                            <span class="heyday-lesson-group-title<?php echo $finallocked ? ' is-disabled' : ''; ?>">
                                <?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?>
                            </span>
                            <span class="heyday-group-status <?php echo s($finalstatus['class']); ?>" aria-hidden="true">
                                <?php echo $finalstatus['icon'] !== '' ? s($finalstatus['icon']) : '<span class="heyday-progress-dot"></span>'; ?>
                            </span>
                        </span>
                        <?php if ($finallocked && $finalexamcm): ?>
                            <span class="heyday-release-note"><?php echo s(local_heyday_courseplayer_locked_message_for_name(get_string('finalexam', 'local_heyday_courseplayer'), $finalexamcm)); ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="heyday-lesson-items">
                        <!-- Child: Final Exam quiz -->
                        <?php if ($finalitem && !$finallocked): ?>
                            <?php
                            $feclasses = ['heyday-sidebar-item', 'heyday-finalexam-child', 'is-' . $finalstatus['class']];
                            if ($finalexamactive) {
                                $feclasses[] = 'is-current';
                            }
                            ?>
                            <a class="<?php echo s(implode(' ', $feclasses)); ?>"
                               href="<?php echo local_heyday_courseplayer_item_url($course, $finalitem)->out(false); ?>">
                                <span class="heyday-current-arrow" aria-hidden="true"></span>
                                <span class="heyday-item-title"><?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?></span>
                                <span class="heyday-status-icon <?php echo s($finalstatus['class']); ?>" aria-hidden="true">
                                    <?php echo $finalstatus['icon'] !== '' ? s($finalstatus['icon']) : '○'; ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <div class="heyday-sidebar-item heyday-finalexam-child is-locked">
                                <span class="heyday-item-title"><?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?></span>
                                <span class="heyday-status-icon locked" aria-hidden="true">🔒</span>
                            </div>
                        <?php endif; ?>

                        <!-- Child: Next Steps for Completion -->
                        <?php
                        $nsclasses = ['heyday-sidebar-item', 'heyday-nextsteps-child'];
                        if ($nextstepsactive) {
                            $nsclasses[] = 'is-current';
                        }
                        // Next Steps is locked until the Final Exam quiz is completed.
                        $nextstepslocked = !$finalexamcm || !($finalstatus['class'] === 'completed');
                        if ($nextstepslocked) {
                            $nsclasses[] = 'is-locked';
                        }
                        ?>
                        <?php if (!$nextstepslocked): ?>
                            <a class="<?php echo s(implode(' ', $nsclasses)); ?>"
                               href="<?php echo (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'finalexam', 'subpage' => 'nextsteps']))->out(false); ?>">
                                <span class="heyday-current-arrow" aria-hidden="true"></span>
                                <span class="heyday-item-title">Next Steps for Completion</span>
                                <span class="heyday-status-icon" aria-hidden="true">○</span>
                            </a>
                        <?php else: ?>
                            <div class="<?php echo s(implode(' ', $nsclasses)); ?>">
                                <span class="heyday-item-title">Next Steps for Completion</span>
                                <span class="heyday-status-icon locked" aria-hidden="true">🔒</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            </nav>

<?php
$sidebarhtml = ob_get_clean();

// Render only the inner activity/content body. The card/header/footer now come
// from local_heyday_courseplayer\output\master_shell templates.
ob_start();
if ($pagekey === 'discussion') {
    if (!$requestedcm || $requestedcm->modname !== 'forum') {
        echo html_writer::div(html_writer::tag('p', get_string('noitemsfound', 'local_heyday_courseplayer')), 'heyday-empty-state');
    } else {
        echo local_heyday_courseplayer_render_discussion_detail($course, $requestedcm, $did);
    }
} else if (in_array($pagekey, ['home', 'scores', 'discussions', 'gettingstarted', 'pretest', 'resources', 'finalexam'], true)) {
    echo local_heyday_courseplayer_render_named_page(
        $pagekey,
        $course,
        $completion,
        $modinfo,
        $context,
        $lessongroups,
        $sequenceitems,
        $discussioncms,
        $resourceitems,
        $pretestitem,
        $finalitem,
        $gspage,
        $subpage
    );
} else if ($pagekey === 'lessonquiz') {
    if (!$activeitem) {
        echo html_writer::div(html_writer::tag('p', 'Quiz not found.'), 'heyday-empty-state');
    } else if ($activeislocked && $activecm) {
        echo local_heyday_courseplayer_render_locked_card($activetitle, local_heyday_courseplayer_locked_message_for_name($activetitle, $activecm));
    } else {
        echo local_heyday_courseplayer_render_lesson_quiz_card($course, $activeitem, $completion);
    }
} else if (!$activeitem) {
    echo html_writer::div(html_writer::tag('p', get_string('selectlesson', 'local_heyday_courseplayer')), 'heyday-intro-card');
} else if ($activeislocked && $activecm) {
    echo local_heyday_courseplayer_render_locked_card(
        get_string('locked', 'local_heyday_courseplayer'),
        local_heyday_courseplayer_locked_message_for_name($activetitle, $activecm)
    );
} else {
    echo local_heyday_courseplayer_render_item_content($course, $activeitem);
}
$contenthtml = ob_get_clean();

$completionfooter = null;
$footeractivecm = $activecm;
$footeractiveislocked = $activeislocked;

if ($pagekey === 'gettingstarted') {
    $footeractivecm = $gettingstartedcm;
    $footeractiveislocked = false;
}

if ($footeractivecm && !$footeractiveislocked) {
    $activestatus = local_heyday_courseplayer_completion_status($completion, $footeractivecm);
    $isdone = ($activestatus['class'] === 'completed');
    $completionparams = [
        'id' => $course->id,
        'page' => $pagekey,
        'cmid' => $footeractivecm->id,
        'sesskey' => sesskey(),
    ];
    if ($pageid > 0) {
        $completionparams['pageid'] = $pageid;
    }
    if ($pagekey === 'gettingstarted') {
        $completionparams['gs'] = $gspage;
    }

    $completeparams = $completionparams;
    $completeparams['completionaction'] = 'complete';
    $undoparams = $completionparams;
    $undoparams['completionaction'] = 'undo';

    $completionfooter = [
        'done' => $isdone,
        'label' => $isdone ? get_string('activitycomplete', 'local_heyday_courseplayer') : 'Activity not complete',
        'completeurl' => !$isdone ? new moodle_url('/local/heyday_courseplayer/index.php', $completeparams) : '',
        'undourl' => $isdone ? new moodle_url('/local/heyday_courseplayer/index.php', $undoparams) : '',
        'undoavailableurl' => new moodle_url('/local/heyday_courseplayer/index.php', $undoparams),
        'undolabel' => 'Undo',
    ];
}

$nextfooter = null;
if ($pagekey === 'gettingstarted' && $gettingstartednext) {
    $nextfooter = [
        'url' => $gettingstartednext['url'],
        'title' => $gettingstartednext['title'],
        'type' => get_string('activity', 'local_heyday_courseplayer'),
    ];
} else if ($activeitem && $activecm && !$activeislocked && $nextitem) {
    $nextfooter = [
        'url' => local_heyday_courseplayer_item_url($course, $nextitem),
        'title' => local_heyday_courseplayer_item_title($nextitem),
        'type' => get_string('activity', 'local_heyday_courseplayer'),
    ];
}

$pageclass = 'is-page-' . preg_replace('/[^a-z0-9_-]+/i', '-', $pagekey);

// This is the reusable master shell. Child Heyday plugins should call the same
// open()/close() methods so they inherit this header/footer/card structure.
// Build the opening shell before $OUTPUT->header(), because open() prepares Moodle
// page classes and CSS requirements. Printing first causes the Moodle error:
// "Cannot call moodle_page::add_body_class after output has been started".
$shellopenhtml = \local_heyday_courseplayer\output\master_shell::open($course, $sidebarhtml, [
    'pageclass' => $pageclass,
    'pagetitle' => $activetitle,
    'sectionline' => $activegroupname,
    'backurl' => local_heyday_courseplayer_url($course, 'home'),
    'showheading' => !($activeitem && $activecm && $activecm->modname === 'h5pactivity'),
    'showtopbar' => true,
    'topbarbrand' => $showtopbarbrand ? (string)$brandname : '',
    'topbaruser' => fullname($USER),
    'showeditingtools' => false,
    'showtopbareditingtools' => $caneditcourse,
    'printactivitylabel' => 'Print/Save activity',
    'printsectionlabel' => 'Print/Save ' . ($activegroupname !== '' ? $activegroupname : $activetitle),
    'editcourseurl' => $editcourseurl,
    'contentbankurl' => $contentbankurl,
    'editsettingsurl' => $editsettingsurl,
]);

echo $OUTPUT->header();
echo html_writer::tag('style', $cssvartext, ['id' => 'heyday-courseplayer-settings']);
echo $shellopenhtml;
echo $contenthtml;

echo \local_heyday_courseplayer\output\master_shell::close([
    'completion' => $completionfooter,
    'next' => $nextfooter,
    'supporturl' => $helpurl,
    'cookieurl' => '#',
    'copyright' => '© 2026 ' . (string)$brandname,
]);

echo $OUTPUT->footer();
