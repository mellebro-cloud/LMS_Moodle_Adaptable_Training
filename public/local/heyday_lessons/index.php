<?php
// This file is part of Moodle - http://moodle.org/
//
// local_heyday_lessons front controller.
// It keeps the local_heyday_lessons course-structure/sidebar logic,
// but inherits the reusable ed2go-style master shell from local_heyday_courseplayer.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

/**
 * Remove the outer local_heyday_lessons sidebar wrapper because the master shell
 * already provides <aside class="heyday-courseplayer-sidebar">.
 *
 * @param string $sidebarhtml
 * @return string
 */
function local_heyday_lessons_index_sidebar_inner(string $sidebarhtml): string {
    $trimmed = trim($sidebarhtml);
    if (preg_match('/^<aside\b[^>]*class="[^"]*\bhd-sidebar\b[^"]*"[^>]*>(.*)<\/aside>\s*$/s', $trimmed, $matches)) {
        return $matches[1];
    }
    return $sidebarhtml;
}

/**
 * Render a circular home meter using the courseplayer master-shell classes.
 *
 * @param int $value
 * @param string $label
 * @param bool $score
 * @return string
 */
function local_heyday_lessons_index_meter(int $value, string $label, bool $score = false): string {
    $value = max(0, min(100, $value));
    $classes = 'heyday-home-meter-ring' . ($score ? ' is-score' : '');
    $inside = html_writer::span($score && $value === 0 ? '- -' : $value . '%') .
        html_writer::span(s($label), 'heyday-home-meter-label');
    return html_writer::div(
        html_writer::div($inside, $classes, ['style' => '--meter-value:' . $value]),
        'heyday-home-meter'
    );
}

/**
 * Render the home/dashboard body for the master shell.
 *
 * @param stdClass $course
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_index_home_content(stdClass $course, array $structure): string {
    $coursecontext = context_course::instance($course->id);
    $first = function_exists('local_heyday_lessons_first_item') ? local_heyday_lessons_first_item($structure) : null;
    $progress = function_exists('local_heyday_lessons_progress_percent') ? local_heyday_lessons_progress_percent($structure) : 0;
    $score = function_exists('local_heyday_lessons_score_percent') ? local_heyday_lessons_score_percent($course) : 0;
    $prefix = function_exists('local_heyday_lessons_cfg') ? (string)local_heyday_lessons_cfg('coursecodeprefix', 'Section:') : 'Section:';
    $code = trim((string)$course->shortname) !== '' ? trim((string)$course->shortname) : (string)$course->id;

    $html = html_writer::start_div('heyday-home-dashboard');
    $html .= html_writer::start_div('heyday-home-hero');
    $html .= html_writer::start_div('heyday-home-hero-title');
    $html .= html_writer::tag('h2', format_string($course->fullname, true, ['context' => $coursecontext]));
    $html .= html_writer::div(s(trim($prefix . ' ' . $code)), 'heyday-home-section-code');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('heyday-home-meters');
    $html .= local_heyday_lessons_index_meter((int)$progress, 'complete');
    $html .= local_heyday_lessons_index_meter((int)$score, 'score', true);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('heyday-home-content');
    $html .= html_writer::tag('h1', get_string('welcome', 'local_heyday_lessons'), ['class' => 'heyday-home-welcome']);
    if ($first !== null) {
        $html .= html_writer::start_div('heyday-home-next-card');
        $html .= html_writer::start_div('heyday-home-next-main');
        $html .= html_writer::tag('h3', s($first['lesson'] ?? $first['name']));
        $html .= html_writer::start_div('heyday-home-progress-row');
        $html .= html_writer::div(
            html_writer::span('', 'heyday-home-progress-fill', ['style' => 'width:' . (int)$progress . '%']),
            'heyday-home-progress-track'
        );
        $html .= html_writer::span((int)$progress . '% complete');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('heyday-home-next-action');
        $html .= html_writer::span(get_string('nextactivity', 'local_heyday_lessons'), 'heyday-home-next-label');
        $html .= html_writer::span(s($first['name']), 'heyday-home-next-name');
        $html .= html_writer::link($first['url'], get_string('continue', 'local_heyday_lessons'), ['class' => 'heyday-home-continue-button']);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
    } else {
        $html .= html_writer::div(get_string('noitems', 'local_heyday_lessons'), 'heyday-empty-state');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render a simple section/group content page body.
 *
 * @param array $group
 * @return string
 */
function local_heyday_lessons_index_group_content(array $group): string {
    $html = html_writer::start_div('heyday-list-card');
    if (!empty($group['items'])) {
        $html .= html_writer::start_tag('ul', ['class' => 'heyday-lesson-summary-list']);
        foreach ($group['items'] as $item) {
            $label = s($item['name']);
            if (!empty($item['url']) && empty($item['locked'])) {
                $row = html_writer::link($item['url'], $label);
            } else {
                $row = html_writer::span($label, 'heyday-muted');
            }
            if (function_exists('local_heyday_lessons_status_icon')) {
                $row .= ' ' . local_heyday_lessons_status_icon((string)($item['status'] ?? 'none'), true);
            }
            $html .= html_writer::tag('li', $row);
        }
        $html .= html_writer::end_tag('ul');
    } else {
        $html .= html_writer::div(get_string('noitems', 'local_heyday_lessons'), 'heyday-empty-state');
    }
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render Scores page content for the master shell.
 *
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_index_scores_content(array $structure): string {
    $html = html_writer::start_div('heyday-scores-wrapper');
    $html .= html_writer::start_div('heyday-scores-title-row');
    $html .= html_writer::tag('h1', get_string('scores', 'local_heyday_lessons'), ['class' => 'heyday-scores-title']);
    $html .= html_writer::tag('button', get_string('downloadgrades', 'local_heyday_lessons'), ['type' => 'button', 'class' => 'heyday-download-btn']);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('heyday-score-list');

    $found = false;
    foreach (($structure['flat'] ?? []) as $item) {
        if (!preg_match('/quiz|assignment|exam|test|check/i', ($item['name'] ?? '') . ' ' . ($item['modname'] ?? ''))) {
            continue;
        }
        $found = true;
        $locked = !empty($item['locked']);
        $classes = 'heyday-score-row' . ($locked ? ' is-locked' : '');
        $html .= html_writer::start_div($classes);
        $html .= html_writer::start_div('heyday-score-left');
        $html .= html_writer::span('', 'hd-score-icon hd-score-icon-check', ['aria-hidden' => 'true']);
        $html .= html_writer::start_div('heyday-score-main');
        if ($locked || empty($item['url'])) {
            $html .= html_writer::span(s($item['name']), 'heyday-score-name locked-name');
        } else {
            $html .= html_writer::link($item['url'], s($item['name']), ['class' => 'heyday-score-name']);
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $status = (string)($item['status'] ?? 'none');
        $label = $locked ? get_string('locked', 'local_heyday_lessons') : ($status === 'completed' ? 'Complete' : 'Not submitted');
        $html .= html_writer::div(s($label), 'heyday-score-right');
        $html .= html_writer::end_div();
    }

    if (!$found) {
        $html .= html_writer::div('No graded activities were found yet.', 'heyday-empty-state');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

/**
 * Render Discussions page content for the master shell.
 *
 * @param array $structure
 * @return string
 */
function local_heyday_lessons_index_discussions_content(array $structure): string {
    $html = html_writer::start_div('heyday-discussions-page-master');
    $html .= html_writer::start_div('heyday-discussion-card-list');
    $found = false;
    foreach (($structure['flat'] ?? []) as $item) {
        if (!preg_match('/forum|discussion/i', ($item['name'] ?? '') . ' ' . ($item['modname'] ?? ''))) {
            continue;
        }
        $found = true;
        $locked = !empty($item['locked']);
        $html .= html_writer::start_div('heyday-discussion-card' . ($locked ? ' is-locked' : ''));
        $html .= html_writer::start_div('heyday-discussion-left');
        $html .= html_writer::span('', 'heyday-discussion-icon', ['aria-hidden' => 'true']);
        $html .= html_writer::start_div('heyday-discussion-main');
        if ($locked || empty($item['url'])) {
            $html .= html_writer::span(s($item['name']), 'heyday-discussion-title');
        } else {
            $html .= html_writer::link($item['url'], s($item['name']), ['class' => 'heyday-discussion-title']);
        }
        $html .= html_writer::div(s($item['lesson'] ?? ''), 'heyday-discussion-meta');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::div(function_exists('local_heyday_lessons_status_icon') ? local_heyday_lessons_status_icon((string)($item['status'] ?? 'none')) : '', 'heyday-discussion-right');
        $html .= html_writer::end_div();
    }
    if (!$found) {
        $html .= html_writer::div('No discussions were found yet.', 'heyday-empty-state');
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
}

$id = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$pagekey = optional_param('page', '', PARAM_ALPHANUMEXT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
if (!has_capability('local/heyday_lessons:view', $context)) {
    require_capability('moodle/course:view', $context);
}

if ((int)local_heyday_lessons_cfg('enabled', 1) !== 1) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$pagekey = $pagekey === '' ? ($cmid > 0 ? 'content' : 'home') : $pagekey;
$cm = local_heyday_lessons_get_cm($course, $cmid);
if ($cm === null && $pagekey === 'content') {
    $pagekey = 'home';
}
$currentcmid = $cm ? (int)$cm->id : 0;
$structure = local_heyday_lessons_build_structure($course, $currentcmid);

$params = ['id' => $course->id];
if ($currentcmid > 0) {
    $params['cmid'] = $currentcmid;
}
if ($pagekey !== '' && $pagekey !== 'content') {
    $params['page'] = $pagekey;
}

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/heyday_lessons/index.php', $params));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

// Keep the child-plugin class for its own CSS, and add the provider class for the master shell CSS.
$PAGE->add_body_class('local-heyday-lessons');
$PAGE->add_body_class('local-heyday-courseplayer');
$PAGE->add_body_class('local-heyday-masterplayer');

$PAGE->requires->css(new moodle_url('/local/heyday_courseplayer/styles.css', ['v' => 2026061400]));
$PAGE->requires->css(new moodle_url('/local/heyday_lessons/styles.css', ['v' => 2026061402]));

$masterclass = '\\local_heyday_courseplayer\\output\\master_shell';
if (!class_exists($masterclass)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('local_heyday_courseplayer master shell is not installed or Moodle caches need to be purged.', 'notifyproblem');
    echo local_heyday_lessons_render_shell($course, $currentcmid, $pagekey);
    echo $OUTPUT->footer();
    exit;
}

$sidebarhtml = local_heyday_lessons_render_sidebar($course, $structure, $currentcmid, $pagekey);
$sidebarhtml = local_heyday_lessons_index_sidebar_inner($sidebarhtml);

$pageclass = 'is-page-lessons is-page-' . preg_replace('/[^a-z0-9_-]/', '', core_text::strtolower($pagekey));
$pagetitle = '';
$sectionline = '';
$contenthtml = '';
$footeroptions = [
    'supporturl' => '#',
    'cookieurl' => '#',
];

if ($cm !== null) {
    $coursecontext = context_course::instance($course->id);
    $cmcontext = context_module::instance($cm->id);
    $lesson = local_heyday_lessons_current_lesson_title($structure, $currentcmid);
    $chapter = local_heyday_lessons_current_chapter_title($structure, $currentcmid);
    $crumb = [];
    if ($lesson !== '') {
        $crumb[] = $lesson;
    }
    if ($chapter !== '' && $chapter !== $cm->name) {
        $crumb[] = $chapter;
    }

    $pageclass = 'is-page-lessons is-page-content';
    $pagetitle = format_string($cm->name, true, ['context' => $cmcontext]);
    $sectionline = implode(' / ', $crumb);
    $contenthtml = local_heyday_lessons_render_cm_body($course, $cm);

    $status = local_heyday_lessons_cm_status($course, $cm);
    $next = local_heyday_lessons_next_item($structure, $currentcmid);
    $footeroptions['completion'] = [
        'done' => $status === 'completed',
        'label' => $status === 'completed' ? get_string('activitycomplete', 'local_heyday_lessons') : get_string('activitynotcomplete', 'local_heyday_lessons'),
        'undourl' => '',
        'undolabel' => get_string('undo', 'local_heyday_lessons'),
    ];
    if ($next !== null) {
        $footeroptions['next'] = [
            'url' => (string)$next['url'],
            'title' => (string)$next['name'],
            'type' => 'activity',
        ];
    }
} else if ($pagekey === 'scores') {
    $pageclass .= ' is-page-scores';
    $pagetitle = get_string('scores', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_scores_content($structure);
} else if ($pagekey === 'discussions') {
    $pageclass .= ' is-page-discussions';
    $pagetitle = get_string('discussions', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_discussions_content($structure);
} else if ($pagekey === 'gettingstarted') {
    $pagetitle = get_string('gettingstarted', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_group_content($structure['gettingstarted']);
} else if ($pagekey === 'pretest') {
    $pagetitle = get_string('pretest', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_group_content($structure['pretest']);
} else if ($pagekey === 'resources') {
    $pagetitle = get_string('resources', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_group_content($structure['resources']);
} else if ($pagekey === 'finalexam') {
    $pagetitle = get_string('finalexam', 'local_heyday_lessons');
    $contenthtml = local_heyday_lessons_index_group_content($structure['final']);
} else {
    $pageclass = 'is-page-lessons is-page-home';
    $pagetitle = '';
    $sectionline = '';
    $contenthtml = local_heyday_lessons_index_home_content($course, $structure);
}

$headeroptions = [
    'pageclass' => $pageclass,
    'pagetitle' => $pagetitle,
    'sectionline' => $sectionline,
    'backurl' => new moodle_url('/local/heyday_lessons/index.php', ['id' => $course->id, 'page' => 'home']),
];

echo $OUTPUT->header();
echo $masterclass::open($course, $sidebarhtml, $headeroptions);
echo $contenthtml;
echo $masterclass::close($footeroptions);
echo $OUTPUT->footer();
