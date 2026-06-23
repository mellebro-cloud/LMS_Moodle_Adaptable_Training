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
$pagekey = optional_param('page', 'home', PARAM_ALPHAEXT);
$gspage = optional_param('gs', 'overview', PARAM_ALPHANUMEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$pageid = optional_param('pageid', 0, PARAM_INT);

$allowedpages = ['home', 'scores', 'discussions', 'gettingstarted', 'pretest', 'lessons', 'lesson', 'resources', 'finalexam'];
if (!in_array($pagekey, $allowedpages, true)) {
    $pagekey = 'home';
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

$params = ['id' => $course->id, 'page' => $pagekey];
if ($pagekey === 'gettingstarted') {
    $params['gs'] = $gspage;
}
if ($cmid > 0) {
    $params['cmid'] = $cmid;
}
if ($pageid > 0) {
    $params['pageid'] = $pageid;
}

$PAGE->set_url(new moodle_url('/local/heyday_courseplayer/index.php', $params));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-heyday-courseplayer');
$PAGE->set_title(format_string($course->fullname) . ' - ' . get_string('courseplayer', 'local_heyday_courseplayer'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/heyday_courseplayer/styles.css'));

$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();
$completion = new completion_info($course);

$requestedcm = null;
if ($cmid > 0) {
    try {
        $candidatecm = $modinfo->get_cm($cmid);
        if ((int)$candidatecm->course === (int)$course->id) {
            $requestedcm = $candidatecm;
        }
    } catch (Throwable $e) {
        $requestedcm = null;
    }
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
 * Check whether a section looks like a Resources section.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_courseplayer_is_resources_section(string $sectionname): bool {
    return (bool)preg_match('/\bresources?\b/i', trim($sectionname));
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
    return (bool)preg_match('/\bpre\s*test\b|\bpretest\b/i', core_text::strtolower(trim($cm->name)));
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
        return ['class' => 'locked', 'label' => get_string('locked', 'local_heyday_courseplayer'), 'icon' => '🔒'];
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
 * Get delegated child section for a Moodle Subsection activity.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param cm_info $cm Course module.
 * @return section_info|null
 */
function local_heyday_courseplayer_get_subsection_section(course_modinfo $modinfo, cm_info $cm): ?section_info {
    if ($cm->modname !== 'subsection') {
        return null;
    }

    if (method_exists($modinfo, 'get_section_info_by_component')) {
        try {
            $section = $modinfo->get_section_info_by_component('mod_subsection', $cm->instance);
            if ($section) {
                return $section;
            }
        } catch (Throwable $e) {
            // Fall back to scanning below.
        }
    }

    foreach ($modinfo->get_section_info_all() as $candidate) {
        $component = $candidate->component ?? '';
        $itemid = $candidate->itemid ?? 0;
        if ($component === 'mod_subsection' && (int)$itemid === (int)$cm->instance) {
            return $candidate;
        }
    }

    return null;
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

        if ($cm->modname === 'subsection') {
            $items[] = [
                'type' => 'heading',
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'depth' => $depth,
            ];

            $childsection = local_heyday_courseplayer_get_subsection_section($modinfo, $cm);
            if ($childsection && isset($childsection->section)) {
                $items = array_merge($items, local_heyday_courseplayer_collect_section_items(
                    $modinfo,
                    (int)$childsection->section,
                    $context,
                    $depth + 1,
                    $visited
                ));
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

        $items[] = [
            'type' => 'cm',
            'cm' => $cm,
            'depth' => $depth,
        ];
    }

    return $items;
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

    foreach ($sections as $sectionnum => $section) {
        if ((int)$sectionnum === 0) {
            continue;
        }
        if (!$section->visible && !has_capability('moodle/course:viewhiddensections', $context)) {
            continue;
        }

        $sectionname = get_section_name($course, $section);
        if (!local_heyday_courseplayer_is_lesson_section($sectionname)) {
            continue;
        }

        $items = local_heyday_courseplayer_collect_section_items($modinfo, (int)$sectionnum, $context, 0);
        if (empty($items)) {
            continue;
        }

        $groups[] = [
            'name' => format_string($sectionname),
            'sectionnum' => (int)$sectionnum,
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
    if (in_array(($item['type'] ?? ''), ['cm', 'lessonpage', 'pretest', 'finalexam', 'resource'], true)) {
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
    return $cm && $cm->available && $cm->uservisible;
}

/**
 * Build courseplayer URL for a navigation item.
 *
 * @param stdClass $course Course record.
 * @param array<string,mixed> $item Navigation item.
 * @return moodle_url
 */
function local_heyday_courseplayer_item_url(stdClass $course, array $item): moodle_url {
    $cm = local_heyday_courseplayer_item_cm($item);
    $params = [];
    if ($cm) {
        $params['cmid'] = $cm->id;
    }
    if (($item['type'] ?? '') === 'lessonpage') {
        $params['pageid'] = (int)$item['pageid'];
    }

    if (($item['type'] ?? '') === 'pretest') {
        return local_heyday_courseplayer_url($course, 'pretest', $params);
    }
    if (($item['type'] ?? '') === 'finalexam') {
        return local_heyday_courseplayer_url($course, 'finalexam', $params);
    }
    if (($item['type'] ?? '') === 'resource') {
        return local_heyday_courseplayer_url($course, 'resources', $params);
    }

    return local_heyday_courseplayer_url($course, 'lesson', $params);
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
        if ($cm->available && $cm->uservisible) {
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
function local_heyday_courseplayer_activity_card(cm_info $cm, string $buttontext, string $message): string {
    $output = html_writer::start_div('heyday-activity-fallback');
    $output .= html_writer::tag('div', s(local_heyday_courseplayer_activity_type_label($cm)), ['class' => 'heyday-activity-type']);
    $output .= html_writer::tag('p', $message);
    if ($cm->url) {
        $output .= html_writer::link($cm->url, $buttontext, ['class' => 'heyday-primary-button']);
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
function local_heyday_courseplayer_render_item_content(array $item): string {
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

    if (($item['type'] ?? '') === 'cm') {
        $pagecontent = local_heyday_courseplayer_render_page_content($cm);
        if ($pagecontent !== null) {
            return $pagecontent;
        }
    }

    $buttontext = get_string('openactivity', 'local_heyday_courseplayer');
    $message = get_string('normalactivityscreen', 'local_heyday_courseplayer');
    if (($item['type'] ?? '') === 'pretest') {
        $buttontext = get_string('openpretest', 'local_heyday_courseplayer');
        $message = get_string('interactiveactivityscreen', 'local_heyday_courseplayer');
    } else if (($item['type'] ?? '') === 'finalexam') {
        $buttontext = get_string('openfinalexam', 'local_heyday_courseplayer');
        $message = get_string('interactiveactivityscreen', 'local_heyday_courseplayer');
    } else if (($item['type'] ?? '') === 'resource') {
        $buttontext = get_string('openresource', 'local_heyday_courseplayer');
    }

    return local_heyday_courseplayer_activity_card($cm, $buttontext, $message);
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

    $gradeitem = $DB->get_record('grade_items', [
        'courseid' => $course->id,
        'itemtype' => 'course',
    ], '*', IGNORE_MISSING);

    if (!$gradeitem) {
        return '--';
    }

    $grade = $DB->get_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $USER->id,
    ], '*', IGNORE_MISSING);

    if (!$grade || $grade->finalgrade === null || $grade->finalgrade === '') {
        return '--';
    }

    $finalgrade = (float)$grade->finalgrade;
    $grademax = (float)$gradeitem->grademax;

    if ($grademax > 0) {
        return (string)((int)round(($finalgrade / $grademax) * 100)) . '%';
    }

    return format_float($finalgrade, 1);
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
    $nexturl = $next ? local_heyday_courseplayer_item_url($course, $next) : local_heyday_courseplayer_url($course, 'lessons');
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

    $output .= html_writer::start_div('heyday-home-meter');
    $output .= html_writer::div(html_writer::span(s($score)), 'heyday-home-meter-ring is-score', ['style' => '--meter-value:0;']);
    $output .= html_writer::div('score', 'heyday-home-meter-label');
    $output .= html_writer::end_div();
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

    $alloweditem = static function(string $itemname): bool {
        $name = trim($itemname);
        if (preg_match('/^pretest$/i', $name)) {
            return true;
        }
        if (preg_match('/^lesson\s*[0-9]+\s*[:\-]?\s*quiz$/i', $name)) {
            return true;
        }
        if (preg_match('/^final\s*exam$/i', $name)) {
            return true;
        }
        return false;
    };

    $sortweight = static function(string $itemname): int {
        $name = trim($itemname);
        if (preg_match('/^pretest$/i', $name)) {
            return 1;
        }
        if (preg_match('/^lesson\s*([0-9]+)\s*[:\-]?\s*quiz$/i', $name, $matches)) {
            return 100 + (int)$matches[1];
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

    foreach ($gradeitems as $gradeitem) {
        if (!empty($gradeitem->hidden) || empty($gradeitem->itemname)) {
            continue;
        }

        $name = trim((string)$gradeitem->itemname);
        if (!$alloweditem($name)) {
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

        $gradegrade = $DB->get_record('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $USER->id,
        ], '*', IGNORE_MISSING);

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
        $credit = !empty($gradeitem->aggregationcoef) || !empty($gradeitem->weightoverride);

        if ($creditonly && !$credit) {
            continue;
        }

        if (preg_match('/^pretest$/i', $name)) {
            $activityurl = local_heyday_courseplayer_url($course, 'pretest', ['cmid' => $cm->id]);
        } else if (preg_match('/^final\s*exam$/i', $name)) {
            $activityurl = local_heyday_courseplayer_url($course, 'finalexam', ['cmid' => $cm->id]);
        } else {
            $activityurl = local_heyday_courseplayer_url($course, 'lesson', ['cmid' => $cm->id]);
        }

        $items[] = [
            'name' => $name,
            'url' => $activityurl,
            'submitted' => $submitted,
            'datesubmitted' => $datesubmitted,
            'finalgrade' => $finalgrade,
            'maxgrade' => $maxgrade,
            'percent' => $percent,
            'locked' => $locked,
            'credit' => $credit,
            'weight' => $sortweight($name),
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
        $output .= ($item['submitted'] && preg_match('/pretest/i', $item['name'])) ? $checkicon : $documenticon;
        $output .= html_writer::start_div('heyday-score-main');

        if ($item['locked']) {
            $output .= html_writer::tag('span', format_string($item['name']), ['class' => 'heyday-score-name locked-name']);
        } else {
            $output .= html_writer::link($item['url'], format_string($item['name']), ['class' => 'heyday-score-name']);
        }

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
            $output .= html_writer::div('Does not count for grade', 'heyday-score-note');
            $output .= html_writer::div($cleannumber($item['finalgrade']) . ' / ' . $cleannumber($item['maxgrade']), 'heyday-score-points heyday-score-points-complete');
        } else {
            $output .= html_writer::div('Not Started', 'heyday-score-status');
            $output .= html_writer::div('Does not count for grade', 'heyday-score-note');
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

    return [$posts, $participants, $latest, $newposts];
}

/**
 * Prefer the custom Heyday Discussions view when that plugin is installed.
 *
 * @param cm_info $cm Discussion course module.
 * @return moodle_url
 */
function local_heyday_courseplayer_discussion_view_url(cm_info $cm): moodle_url {
    $customview = __DIR__ . '/../heyday_discussions/view.php';
    if (file_exists($customview)) {
        return new moodle_url('/local/heyday_discussions/view.php', ['cmid' => $cm->id]);
    }

    if ($cm->url) {
        return $cm->url;
    }

    return new moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
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

    $lessonrows = [];

    foreach ($discussioncms as $cm) {
        $lessonno = local_heyday_courseplayer_discussion_lesson_number((string)$cm->name);

        // Keep the ed2go-style discussion index clean: one Lesson N Discussion Area row per lesson.
        if ($lessonno === null || $lessonno < 1 || $lessonno > 12) {
            continue;
        }

        if (!preg_match('/discussion\s*area/i', (string)$cm->name)) {
            continue;
        }

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

        $locked = !$cm->uservisible;
        if (property_exists($cm, 'available') && !$cm->available) {
            $locked = true;
        }

        $lessonrows[$lessonno] = [
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
            'lessonno' => $lessonno,
            'url' => local_heyday_courseplayer_discussion_view_url($cm),
            'posts' => $posts,
            'participants' => $participants,
            'updated' => $updatedtime ? userdate($updatedtime, '%m/%d/%Y') : '',
            'newposts' => $newposts,
            'locked' => $locked,
            'cm' => $cm,
            'placeholder' => false,
        ];
    }

    $rows = [];
    for ($i = 1; $i <= 12; $i++) {
        if (isset($lessonrows[$i])) {
            $rows[] = $lessonrows[$i];
            continue;
        }

        $rows[] = [
            'name' => 'Lesson ' . $i . ' Discussion Area',
            'lessonno' => $i,
            'url' => null,
            'posts' => 0,
            'participants' => 0,
            'updated' => '',
            'newposts' => 0,
            'locked' => true,
            'cm' => null,
            'placeholder' => true,
        ];
    }

    $hasactualforum = false;
    foreach ($rows as $row) {
        if (empty($row['placeholder'])) {
            $hasactualforum = true;
            break;
        }
    }

    $output = html_writer::start_div('heyday-discussions-page-master');

    if (!$hasactualforum) {
        $output .= html_writer::div(
            get_string('discussion_setupneeded', 'local_heyday_courseplayer'),
            'heyday-discussion-setup'
        );
    }

    $output .= html_writer::start_div('heyday-discussion-card-list');

    foreach ($rows as $row) {
        $classes = ['heyday-discussion-card'];
        if (!empty($row['locked'])) {
            $classes[] = 'is-locked';
        }
        if (!empty($row['placeholder'])) {
            $classes[] = 'is-placeholder';
        }

        $output .= html_writer::start_div(implode(' ', $classes));

        $output .= html_writer::start_div('heyday-discussion-left');
        $output .= html_writer::span('💬', 'heyday-discussion-icon', ['aria-hidden' => 'true']);

        $output .= html_writer::start_div('heyday-discussion-main');
        if (empty($row['locked']) && !empty($row['url'])) {
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
        } else if (!empty($row['cm'])) {
            $output .= html_writer::tag('small', s(local_heyday_courseplayer_locked_message($row['cm'])), ['class' => 'heyday-release-note']);
        }

        $output .= html_writer::end_div();
        $output .= html_writer::end_div();

        $output .= html_writer::start_div('heyday-discussion-right');
        if (!empty($row['locked'])) {
            $output .= html_writer::span('🔒', 'heyday-discussion-lock', [
                'title' => get_string('locked', 'local_heyday_courseplayer'),
                'aria-hidden' => 'true',
            ]);
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
function local_heyday_courseplayer_render_gettingstarted(stdClass $course, completion_info $completion, course_modinfo $modinfo, context_course $context, array $lessongroups, string $gspage): string {
    $defs = local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups);
    if (!isset($defs[$gspage])) {
        $gspage = 'overview';
    }

    $current = $defs[$gspage];
    $currentcm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$current['idnumber']);
    $currentstatus = $currentcm ? local_heyday_courseplayer_completion_status($completion, $currentcm) : [
        'class' => 'nottracked',
        'label' => get_string('nottracked', 'local_heyday_courseplayer'),
        'icon' => '',
    ];

    $nexturl = null;
    if ($current['nextpage'] === 'gettingstarted') {
        $nexturl = local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => $current['nextkey']]);
    } else {
        $nexturl = local_heyday_courseplayer_url($course, (string)$current['nextpage']);
    }

    $output = html_writer::start_div('heyday-gs-master');

    $output .= html_writer::start_tag('nav', ['class' => 'heyday-gs-tabbar', 'aria-label' => get_string('gettingstarted', 'local_heyday_courseplayer')]);
    foreach ($defs as $key => $def) {
        $cm = local_heyday_courseplayer_gettingstarted_cm($modinfo, (string)$def['idnumber']);
        $status = $cm ? local_heyday_courseplayer_completion_status($completion, $cm) : [
            'class' => 'nottracked',
            'label' => get_string('nottracked', 'local_heyday_courseplayer'),
            'icon' => '',
        ];
        $classes = ['heyday-gs-tab', 'is-' . $status['class']];
        if ($key === $gspage) {
            $classes[] = 'is-current';
        }
        $output .= html_writer::start_tag('a', [
            'class' => implode(' ', $classes),
            'href' => local_heyday_courseplayer_url($course, 'gettingstarted', ['gs' => $key])->out(false),
        ]);
        $output .= html_writer::span(s($def['title']), 'heyday-gs-tab-title');
        $output .= html_writer::span(($status['class'] === 'completed') ? '✓' : '○', 'heyday-gs-tab-status', ['aria-hidden' => 'true']);
        $output .= html_writer::end_tag('a');
    }
    $output .= html_writer::end_tag('nav');

    $output .= html_writer::start_tag('article', ['class' => 'heyday-gs-page-card']);
    $output .= html_writer::div(s($current['sectiontitle']), 'heyday-gs-kicker');
    $output .= html_writer::tag('h2', s($current['title']), ['class' => 'heyday-gs-page-title']);
    $output .= html_writer::div($current['content'], 'heyday-gs-content');
    $output .= html_writer::end_tag('article');

    $output .= html_writer::start_div('heyday-gs-completion-row');
    if ($currentstatus['class'] === 'completed') {
        $output .= html_writer::div('✓', 'heyday-gs-completion-check', ['aria-hidden' => 'true']);
        $output .= html_writer::start_div();
        $output .= html_writer::tag('strong', get_string('activitycomplete', 'local_heyday_courseplayer'));
        $output .= html_writer::tag('div', 'Undo', ['class' => 'heyday-gs-undo']);
        $output .= html_writer::end_div();
    } else {
        $output .= html_writer::div('○', 'heyday-gs-completion-pending', ['aria-hidden' => 'true']);
        $output .= html_writer::start_div();
        $output .= html_writer::tag('strong', s($currentstatus['label']));
        $output .= html_writer::tag('div', get_string('gettingstarted', 'local_heyday_courseplayer'), ['class' => 'heyday-gs-undo']);
        $output .= html_writer::end_div();
    }
    $output .= html_writer::end_div();

    $output .= html_writer::div('End of ' . s($current['title']), 'heyday-gs-end-divider');

    $output .= html_writer::start_div('heyday-gs-next-wrap');
    $output .= html_writer::start_tag('a', ['class' => 'heyday-gs-next-card', 'href' => $nexturl->out(false)]);
    $output .= html_writer::div(get_string('nextup', 'local_heyday_courseplayer'), 'heyday-gs-next-label');
    $output .= html_writer::start_div('heyday-gs-next-body');
    $output .= html_writer::div(s($current['nextpage'] === 'gettingstarted' ? get_string('gettingstarted', 'local_heyday_courseplayer') : get_string('pretest', 'local_heyday_courseplayer')), 'heyday-gs-next-section');
    $output .= html_writer::div(s($current['nexttitle']), 'heyday-gs-next-title');
    $output .= html_writer::div(get_string('activity', 'local_heyday_courseplayer'), 'heyday-gs-next-type');
    $output .= html_writer::end_div();
    $output .= html_writer::end_tag('a');
    $output .= html_writer::end_div();

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

    $output = html_writer::start_div('heyday-card-list');
    foreach ($resourceitems as $item) {
        $cm = local_heyday_courseplayer_item_cm($item);
        if (!$cm) {
            continue;
        }
        $status = local_heyday_courseplayer_completion_status($completion, $cm);
        $output .= html_writer::start_div('heyday-list-card is-' . s($status['class']));
        $output .= html_writer::tag('h3', local_heyday_courseplayer_item_title($item));
        $output .= html_writer::tag('p', s($status['label']), ['class' => 'heyday-muted']);
        if (!local_heyday_courseplayer_item_available($item)) {
            $output .= html_writer::tag('p', s(local_heyday_courseplayer_locked_message($cm)), ['class' => 'heyday-release-note']);
        } else if ($cm->url) {
            $output .= html_writer::link($cm->url, get_string('openresource', 'local_heyday_courseplayer'), ['class' => 'heyday-secondary-button']);
        }
        $output .= html_writer::end_div();
    }
    $output .= html_writer::end_div();
    return $output;
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
    string $gspage
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
        return local_heyday_courseplayer_render_item_content($pretestitem);
    }
    if ($pagekey === 'resources') {
        return local_heyday_courseplayer_render_resources($course, $completion, $resourceitems);
    }
    if ($pagekey === 'finalexam') {
        if (!$finalitem) {
            return html_writer::div(html_writer::tag('p', get_string('finalnotfound', 'local_heyday_courseplayer')), 'heyday-empty-state');
        }
        $cm = local_heyday_courseplayer_item_cm($finalitem);
        if ($cm && !local_heyday_courseplayer_item_available($finalitem)) {
            return local_heyday_courseplayer_render_locked_card(get_string('finalexam', 'local_heyday_courseplayer'), local_heyday_courseplayer_locked_message_for_name(get_string('finalexam', 'local_heyday_courseplayer'), $cm));
        }
        return local_heyday_courseplayer_render_item_content($finalitem);
    }

    return html_writer::div(html_writer::tag('p', get_string('selectlesson', 'local_heyday_courseplayer')), 'heyday-empty-state');
}

$lessongroups = local_heyday_courseplayer_collect_lesson_groups($modinfo, $sections, $course, $context);
$pretestcm = local_heyday_courseplayer_find_pretest_cm($modinfo, $context);
$finalexamcm = local_heyday_courseplayer_find_final_exam_cm($modinfo, $context);
$resourceitems = local_heyday_courseplayer_collect_resources($modinfo, $sections, $course, $context);
$discussioncms = local_heyday_courseplayer_collect_discussions($modinfo, $context);

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

$activeitem = null;
if (in_array($pagekey, ['lesson', 'lessons'], true)) {
    $activeitem = local_heyday_courseplayer_find_requested_item($lessongroups, [], $requestedcm, $pageid);
    if (!$activeitem) {
        $activeitem = local_heyday_courseplayer_first_available_item($lessongroups);
    }
} else if ($pagekey === 'pretest') {
    $activeitem = $pretestitem;
} else if ($pagekey === 'finalexam') {
    $activeitem = $finalitem;
} else if ($pagekey === 'resources' && $requestedcm) {
    $activeitem = local_heyday_courseplayer_find_requested_item([], $resourceitems, $requestedcm, 0);
}

$activecm = $activeitem ? local_heyday_courseplayer_item_cm($activeitem) : null;
$activeislocked = $activeitem ? !local_heyday_courseplayer_item_available($activeitem) : false;
$activegroupname = $activeitem ? local_heyday_courseplayer_active_group_name($lessongroups, $activeitem) : '';
$activetitle = $activeitem ? local_heyday_courseplayer_item_title($activeitem) : get_string($pagekey, 'local_heyday_courseplayer');
if ($pagekey === 'lessons') {
    $pagekey = 'lesson';
}
if (!$activeitem && in_array($pagekey, ['home', 'scores', 'discussions', 'gettingstarted', 'pretest', 'resources', 'finalexam'], true)) {
    $activetitle = get_string($pagekey, 'local_heyday_courseplayer');
}
if ($pagekey === 'gettingstarted') {
    $gsdefs = local_heyday_courseplayer_gettingstarted_definitions($course, $context, $lessongroups);
    if (isset($gsdefs[$gspage])) {
        $activetitle = $gsdefs[$gspage]['title'];
        $activegroupname = get_string('gettingstarted', 'local_heyday_courseplayer');
    }
}
$nextitem = ($activeitem && !$activeislocked) ? local_heyday_courseplayer_next_available_item($lessongroups, $activeitem, $finalitem ? [$finalitem] : []) : null;

$brandname = local_heyday_courseplayer_cfg('brandname', 'Heyday Training LMS');
$logourl = trim((string)local_heyday_courseplayer_cfg('logourl', ''));
$searchurl = local_heyday_courseplayer_setting_url((string)local_heyday_courseplayer_cfg('searchurl', '/local/heyday_coursesearch/search.php'), '/local/heyday_coursesearch/search.php');
$helpurl = local_heyday_courseplayer_setting_url((string)local_heyday_courseplayer_cfg('helpurl', '/local/heyday_helptour/help.php'), '/local/heyday_helptour/help.php');

$inlinevars = [
    '--heyday-topbar-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('topbarbg', '#050505'), '#050505'),
    '--heyday-accent' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('accentcolor', '#0073a8'), '#0073a8'),
    '--heyday-page-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('pagebg', '#f4f5f7'), '#f4f5f7'),
    '--heyday-card-bg' => local_heyday_courseplayer_colour((string)local_heyday_courseplayer_cfg('cardbg', '#ffffff'), '#ffffff'),
    '--heyday-sidebar-width' => local_heyday_courseplayer_int(local_heyday_courseplayer_cfg('sidebarwidth', 355), 355, 280, 520) . 'px',
    '--heyday-content-max' => local_heyday_courseplayer_int(local_heyday_courseplayer_cfg('contentmaxwidth', 980), 980, 680, 1320) . 'px',
];
$cssvartext = '.local-heyday-courseplayer {';
foreach ($inlinevars as $name => $value) {
    $cssvartext .= $name . ':' . $value . ';';
}
$cssvartext .= '}';

$mainnav = [
    ['key' => 'home', 'label' => get_string('home', 'local_heyday_courseplayer'), 'icon' => '⌂'],
    ['key' => 'scores', 'label' => get_string('scores', 'local_heyday_courseplayer'), 'icon' => '☑'],
    ['key' => 'discussions', 'label' => get_string('discussions', 'local_heyday_courseplayer'), 'icon' => '◌'],
    ['key' => 'gettingstarted', 'label' => get_string('gettingstarted', 'local_heyday_courseplayer'), 'icon' => ''],
    ['key' => 'pretest', 'label' => get_string('pretest', 'local_heyday_courseplayer'), 'icon' => ''],
];

$pageclasses = ['heyday-courseplayer-page', 'is-page-' . $pagekey];

echo $OUTPUT->header();
echo html_writer::tag('style', $cssvartext, ['id' => 'heyday-courseplayer-settings']);
?>

<div class="heyday-ed2go-topbar">
    <div class="heyday-ed2go-brand">
        <?php if ($logourl !== ''): ?>
            <img src="<?php echo s($logourl); ?>" alt="" class="heyday-topbar-logo">
        <?php endif; ?>
        <span><?php echo s($brandname); ?></span>
    </div>
    <div class="heyday-ed2go-topbar-right">
        <a href="<?php echo $searchurl->out(false); ?>" aria-label="Search">⌕</a>
        <span class="heyday-topbar-separator" aria-hidden="true"></span>
        <a href="<?php echo $helpurl->out(false); ?>" aria-label="Help">?</a>
        <span class="heyday-topbar-separator" aria-hidden="true"></span>
        <span aria-hidden="true">♙</span>
        <span><?php echo s(fullname($USER)); ?></span>
        <span aria-hidden="true">⌄</span>
    </div>
</div>

<div class="<?php echo s(implode(' ', $pageclasses)); ?>">
    <div class="heyday-courseplayer-shell">
        <aside class="heyday-courseplayer-sidebar" aria-label="Course menu">
            <nav class="heyday-main-menu" aria-label="Main course navigation">
                <?php foreach ($mainnav as $navitem): ?>
                    <?php $isactive = ($pagekey === $navitem['key']); ?>
                    <a class="<?php echo $isactive ? 'is-current' : ''; ?>" href="<?php echo local_heyday_courseplayer_url($course, $navitem['key'])->out(false); ?>">
                        <?php if ($navitem['icon'] !== ''): ?><span aria-hidden="true"><?php echo s($navitem['icon']); ?></span><?php endif; ?>
                        <span><?php echo s($navitem['label']); ?></span>
                    </a>
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

                    <details class="<?php echo s(implode(' ', $groupclasses)); ?>" <?php echo $groupactive ? 'open' : ''; ?>>
                        <summary>
                            <span class="heyday-group-summary-inner">
                                <?php if ($groupurl): ?>
                                    <a class="heyday-lesson-group-title" href="<?php echo $groupurl->out(false); ?>" onclick="event.stopPropagation();">
                                        <?php echo $group['name']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="heyday-lesson-group-title is-disabled"><?php echo $group['name']; ?></span>
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
                            <?php foreach ($group['items'] as $item): ?>
                                <?php if (($item['type'] ?? '') === 'heading'): ?>
                                    <div class="heyday-subsection-title depth-<?php echo (int)$item['depth']; ?>"><?php echo $item['name']; ?></div>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <?php
                                $cm = local_heyday_courseplayer_item_cm($item);
                                if (!$cm) {
                                    continue;
                                }
                                $depth = (int)$item['depth'];
                                $isactive = $activeitem && local_heyday_courseplayer_items_same($item, $activeitem);
                                $islocked = !local_heyday_courseplayer_item_available($item);
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
                                $itemurl = local_heyday_courseplayer_item_url($course, $item);
                                $itemtitle = local_heyday_courseplayer_item_title($item);
                                ?>

                                <?php if ($islocked): ?>
                                    <div class="<?php echo s(implode(' ', $itemclasses)); ?>">
                                        <span class="heyday-current-arrow" aria-hidden="true"></span>
                                        <span class="heyday-lesson-text">
                                            <span><?php echo $itemtitle; ?></span>
                                            <small class="heyday-release-note"><?php echo s(local_heyday_courseplayer_locked_message($cm)); ?></small>
                                        </span>
                                        <span class="heyday-status-icon locked" aria-hidden="true">🔒</span>
                                    </div>
                                <?php else: ?>
                                    <a class="<?php echo s(implode(' ', $itemclasses)); ?>" href="<?php echo $itemurl->out(false); ?>">
                                        <span class="heyday-current-arrow" aria-hidden="true"></span>
                                        <span class="heyday-lesson-text">
                                            <span><?php echo $itemtitle; ?></span>
                                            <small><?php echo s($status['label']); ?></small>
                                        </span>
                                        <span class="heyday-status-icon <?php echo s($status['class']); ?>" aria-hidden="true">
                                            <?php echo $status['icon'] !== '' ? s($status['icon']) : ($status['class'] === 'inprogress' ? '<span class="heyday-mini-dot"></span>' : ''); ?>
                                        </span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <nav class="heyday-main-menu heyday-after-lessons-menu" aria-label="More course navigation">
                <a class="<?php echo $pagekey === 'resources' ? 'is-current' : ''; ?>" href="<?php echo local_heyday_courseplayer_url($course, 'resources')->out(false); ?>">
                    <?php echo get_string('resources', 'local_heyday_courseplayer'); ?>
                </a>

                <?php if ($finalitem): ?>
                    <?php
                    $finalactive = $pagekey === 'finalexam';
                    $finallocked = !local_heyday_courseplayer_item_available($finalitem);
                    $finalstatus = local_heyday_courseplayer_completion_status($completion, $finalexamcm);
                    $finalclasses = ['heyday-final-exam-link', 'is-' . $finalstatus['class']];
                    if ($finalactive) {
                        $finalclasses[] = 'is-current';
                    }
                    if ($finallocked) {
                        $finalclasses[] = 'is-locked';
                    }
                    ?>
                    <?php if ($finallocked): ?>
                        <div class="<?php echo s(implode(' ', $finalclasses)); ?>">
                            <span><?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?></span>
                            <small class="heyday-release-note"><?php echo s(local_heyday_courseplayer_locked_message_for_name(get_string('finalexam', 'local_heyday_courseplayer'), $finalexamcm)); ?></small>
                            <span class="heyday-status-icon locked" aria-hidden="true">🔒</span>
                        </div>
                    <?php else: ?>
                        <a class="<?php echo s(implode(' ', $finalclasses)); ?>" href="<?php echo local_heyday_courseplayer_item_url($course, $finalitem)->out(false); ?>">
                            <span><?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?></span>
                            <span class="heyday-status-icon <?php echo s($finalstatus['class']); ?>" aria-hidden="true">
                                <?php echo $finalstatus['icon'] !== '' ? s($finalstatus['icon']) : ''; ?>
                            </span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="heyday-final-exam-link is-missing"><?php echo get_string('finalexam', 'local_heyday_courseplayer'); ?></span>
                <?php endif; ?>
            </nav>
        </aside>

        <main class="heyday-courseplayer-main">
            <div class="heyday-player-card">
                <div class="heyday-player-topbar">
                    <a class="heyday-back-link" href="<?php echo local_heyday_courseplayer_url($course, 'home')->out(false); ?>" aria-label="<?php echo get_string('back', 'local_heyday_courseplayer'); ?>">←</a>
                    <span class="heyday-bookmark" aria-label="<?php echo get_string('bookmark', 'local_heyday_courseplayer'); ?>">♡</span>
                    <span class="heyday-topbar-spacer"></span>
                    <button type="button" class="heyday-icon-button" onclick="window.print()" aria-label="<?php echo get_string('print', 'local_heyday_courseplayer'); ?>">▣</button>
                    <button type="button" class="heyday-icon-button" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()" aria-label="<?php echo get_string('fullscreen', 'local_heyday_courseplayer'); ?>">⛶</button>
                </div>

                <div class="heyday-player-heading">
                    <div class="heyday-course-kicker"><?php echo format_string($course->fullname); ?></div>
                    <?php if ($activegroupname !== ''): ?>
                        <div class="heyday-lesson-kicker"><?php echo format_string($activegroupname); ?></div>
                    <?php endif; ?>
                    <h1><?php echo s($activetitle); ?></h1>
                </div>

                <?php if (in_array($pagekey, ['home', 'scores', 'discussions', 'gettingstarted', 'pretest', 'resources', 'finalexam'], true)): ?>
                    <div class="heyday-content-body">
                        <?php echo local_heyday_courseplayer_render_named_page(
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
                            $gspage
                        ); ?>
                    </div>
                <?php elseif (!$activeitem): ?>
                    <div class="heyday-intro-card"><p><?php echo get_string('selectlesson', 'local_heyday_courseplayer'); ?></p></div>
                <?php elseif ($activeislocked && $activecm): ?>
                    <?php echo local_heyday_courseplayer_render_locked_card(get_string('locked', 'local_heyday_courseplayer'), local_heyday_courseplayer_locked_message_for_name($activetitle, $activecm)); ?>
                <?php else: ?>
                    <div class="heyday-content-body"><?php echo local_heyday_courseplayer_render_item_content($activeitem); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($activeitem && $activecm && !$activeislocked): ?>
                <?php $activestatus = local_heyday_courseplayer_completion_status($completion, $activecm); ?>
                <?php if ($activestatus['class'] === 'completed'): ?>
                    <div class="heyday-completion-row">
                        <div class="heyday-completion-check" aria-hidden="true">✓</div>
                        <div><strong><?php echo get_string('activitycomplete', 'local_heyday_courseplayer'); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ($nextitem): ?>
                    <div class="heyday-nextup-row">
                        <div class="heyday-nextup-label"><?php echo get_string('nextup', 'local_heyday_courseplayer'); ?></div>
                        <a href="<?php echo local_heyday_courseplayer_item_url($course, $nextitem)->out(false); ?>">
                            <?php echo local_heyday_courseplayer_item_title($nextitem); ?>
                            <span><?php echo get_string('activity', 'local_heyday_courseplayer'); ?></span>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <footer class="heyday-player-footer">
                <a href="<?php echo $helpurl->out(false); ?>"><?php echo get_string('coursesupport', 'local_heyday_courseplayer'); ?></a>
                <span aria-hidden="true"></span>
                <a href="#"><?php echo get_string('cookiesettings', 'local_heyday_courseplayer'); ?></a>
                <small>© 2026 <?php echo s($brandname); ?></small>
            </footer>
        </main>
    </div>
</div>

<?php
echo $OUTPUT->footer();
