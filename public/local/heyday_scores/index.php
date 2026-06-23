<?php
// Heyday Scores - ed2go-style learner scores page.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = required_param('id', PARAM_INT);
$pagenum = optional_param('p', 1, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);

$PAGE->set_url(new moodle_url('/local/heyday_scores/index.php', [
    'id' => $courseid,
    'p' => $pagenum
]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title('Scores');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('course');
$PAGE->add_body_class('heyday-scores-page-body');

$PAGE->requires->css(new moodle_url('/local/heyday_scores/styles.css'));

global $DB, $USER, $OUTPUT;

function heyday_scores_format_date($timestamp) {
    if (empty($timestamp)) {
        return '';
    }

    $timezone = core_date::get_user_timezone_object();
    $date = new DateTime('@' . $timestamp);
    $date->setTimezone($timezone);

    return $date->format('n/j/Y');
}

function heyday_scores_clean_number($number) {
    if ($number === null || $number === '') {
        return '--';
    }

    $number = (float)$number;

    if (floor($number) == $number) {
        return (string)(int)$number;
    }

    return rtrim(rtrim(number_format($number, 2), '0'), '.');
}

function heyday_scores_percentage($grade, $maxgrade) {
    if ($grade === null || $grade === '' || empty($maxgrade)) {
        return null;
    }

    return round(((float)$grade / (float)$maxgrade) * 100);
}

/**
 * This is the important filter.
 * It removes Instructions, Syllabus, Course Overview, Chapter items, pages, etc.
 */
function heyday_scores_allowed_item($itemname) {
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
}

function heyday_scores_sort_weight($itemname) {
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
}

function heyday_scores_icon_type($itemname, $submitted, $locked) {
    $lower = strtolower($itemname);

    if ($locked) {
        return 'document';
    }

    if ($submitted && strpos($lower, 'pretest') !== false) {
        return 'check';
    }

    return 'document';
}

function heyday_scores_icon($type) {
    if ($type === 'check') {
        return '<span class="hd-score-icon hd-score-icon-check" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M9.1 16.6L4.9 12.4L3.5 13.8L9.1 19.4L20.8 7.7L19.4 6.3L9.1 16.6Z"></path>
            </svg>
        </span>';
    }

    return '<span class="hd-score-icon hd-score-icon-document" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
            <path d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM6 4V20H18V4H6ZM8 7H16V9H8V7ZM8 11H16V13H8V11ZM8 15H14V17H8V15Z"></path>
        </svg>
    </span>';
}

function heyday_scores_lock_icon() {
    return '<span class="hd-score-lock" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
            <path d="M17 9H16V7C16 4.8 14.2 3 12 3C9.8 3 8 4.8 8 7V9H7C5.9 9 5 9.9 5 11V20C5 21.1 5.9 22 7 22H17C18.1 22 19 21.1 19 20V11C19 9.9 18.1 9 17 9ZM10 7C10 5.9 10.9 5 12 5C13.1 5 14 5.9 14 7V9H10V7Z"></path>
        </svg>
    </span>';
}

function heyday_scores_activity_url($cm) {
    if (!$cm) {
        return '#';
    }

    return new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
}

$modinfo = get_fast_modinfo($course, $USER->id);
$items = [];

$gradeitems = $DB->get_records('grade_items', [
    'courseid' => $courseid,
    'itemtype' => 'mod'
], 'sortorder ASC');

foreach ($gradeitems as $gradeitem) {
    if (empty($gradeitem->itemname)) {
        continue;
    }

    if (!heyday_scores_allowed_item($gradeitem->itemname)) {
        continue;
    }

    if (empty($gradeitem->itemmodule) || empty($gradeitem->iteminstance)) {
        continue;
    }

    $cm = null;

    foreach ($modinfo->cms as $candidatecm) {
        if ($candidatecm->modname === $gradeitem->itemmodule &&
            (int)$candidatecm->instance === (int)$gradeitem->iteminstance) {
            $cm = $candidatecm;
            break;
        }
    }

    if (!$cm) {
        continue;
    }

    $gradegrade = $DB->get_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $USER->id
    ]);

    $finalgrade = null;
    $datesubmitted = null;

    if ($gradegrade && $gradegrade->finalgrade !== null) {
        $finalgrade = $gradegrade->finalgrade;
        $datesubmitted = !empty($gradegrade->timemodified)
            ? $gradegrade->timemodified
            : $gradegrade->timecreated;
    }

    $submitted = ($finalgrade !== null);
    $locked = !$cm->uservisible;

    $maxgrade = !empty($gradeitem->grademax) ? $gradeitem->grademax : 100;
    $percent = heyday_scores_percentage($finalgrade, $maxgrade);

    $items[] = [
        'name' => $gradeitem->itemname,
        'url' => heyday_scores_activity_url($cm),
        'submitted' => $submitted,
        'datesubmitted' => $datesubmitted,
        'finalgrade' => $finalgrade,
        'maxgrade' => $maxgrade,
        'percent' => $percent,
        'locked' => $locked,
        'credit' => !empty($gradeitem->aggregationcoef) || !empty($gradeitem->weightoverride),
        'weight' => heyday_scores_sort_weight($gradeitem->itemname),
    ];
}

usort($items, function($a, $b) {
    if ($a['weight'] === $b['weight']) {
        return strnatcasecmp($a['name'], $b['name']);
    }

    return $a['weight'] <=> $b['weight'];
});

/**
 * ed2go-style pagination:
 * Page 1 = Pretest + Lesson 1 Quiz to Lesson 9 Quiz.
 * Page 2 = Lesson 10, Lesson 11, Lesson 12, Final Exam.
 */
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

echo $OUTPUT->header();

echo html_writer::start_div('heyday-scores-wrapper');

echo html_writer::start_div('heyday-scores-title-row');
echo html_writer::tag('h1', 'Scores', ['class' => 'heyday-scores-title']);

echo html_writer::link(
    new moodle_url('/local/heyday_scores/download.php', ['id' => $courseid]),
    'Download scores',
    ['class' => 'btn btn-outline-primary heyday-download-btn']
);

echo html_writer::end_div();

echo html_writer::start_div('heyday-score-toolbar');

echo html_writer::start_tag('label', ['class' => 'heyday-credit-filter']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'heyday-credit-only'
]);
echo html_writer::span('Credit Assignments Only');
echo html_writer::end_tag('label');

echo html_writer::tag('button', 'Sort By', [
    'type' => 'button',
    'class' => 'heyday-sort-btn',
    'id' => 'heyday-sort-button'
]);

echo html_writer::start_div('heyday-search-wrap');
echo html_writer::tag('span', '&#128269;', ['class' => 'heyday-search-icon']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'heyday-score-search',
    'placeholder' => 'Assignment Name',
    'class' => 'heyday-score-search'
]);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::start_div('heyday-score-list', ['id' => 'heyday-score-list']);

if (empty($pageditems)) {
    echo html_writer::div('No score items are available yet.', 'alert alert-info');
}

foreach ($pageditems as $item) {
    $iconType = heyday_scores_icon_type($item['name'], $item['submitted'], $item['locked']);

    $rowclasses = ['heyday-score-row'];

    if ($item['locked']) {
        $rowclasses[] = 'is-locked';
    }

    if ($item['submitted']) {
        $rowclasses[] = 'is-submitted';
    }

    echo html_writer::start_div('', [
        'class' => implode(' ', $rowclasses),
        'data-name' => strtolower($item['name']),
        'data-credit' => $item['credit'] ? '1' : '0'
    ]);

    echo html_writer::start_div('heyday-score-left');
    echo heyday_scores_icon($iconType);

    echo html_writer::start_div('heyday-score-main');

    if ($item['locked']) {
        echo html_writer::tag('span', format_string($item['name']), [
            'class' => 'heyday-score-name locked-name'
        ]);
    } else {
        echo html_writer::link($item['url'], format_string($item['name']), [
            'class' => 'heyday-score-name'
        ]);
    }

    if ($item['submitted'] && !empty($item['datesubmitted'])) {
        echo html_writer::div(
            'Submitted on: ' . heyday_scores_format_date($item['datesubmitted']),
            'heyday-score-submitted-date'
        );
    }

    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('heyday-score-right');

    if ($item['locked']) {
        echo heyday_scores_lock_icon();
    } else if ($item['submitted']) {
        echo html_writer::div($item['percent'] . '%', 'heyday-score-percent');
        echo html_writer::div('Does not count for grade', 'heyday-score-note');
        echo html_writer::div(
            heyday_scores_clean_number($item['finalgrade']) . ' / ' . heyday_scores_clean_number($item['maxgrade']),
            'heyday-score-points heyday-score-points-complete'
        );
    } else {
        echo html_writer::div('Not Started', 'heyday-score-status');
        echo html_writer::div('Does not count for grade', 'heyday-score-note');
        echo html_writer::div('-- / ' . heyday_scores_clean_number($item['maxgrade']), 'heyday-score-points');
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

/**
 * Pagination buttons.
 */
if ($totalpages > 1) {
    echo html_writer::start_div('heyday-score-pagination');

    if ($pagenum > 1) {
        echo html_writer::link(
            new moodle_url('/local/heyday_scores/index.php', [
                'id' => $courseid,
                'p' => $pagenum - 1
            ]),
            'Prev',
            ['class' => 'heyday-page-btn']
        );
    } else {
        echo html_writer::span('Prev', 'heyday-page-btn is-disabled');
    }

    for ($i = 1; $i <= $totalpages; $i++) {
        if ($i == $pagenum) {
            echo html_writer::span((string)$i, 'heyday-page-btn is-active');
        } else {
            echo html_writer::link(
                new moodle_url('/local/heyday_scores/index.php', [
                    'id' => $courseid,
                    'p' => $i
                ]),
                (string)$i,
                ['class' => 'heyday-page-btn']
            );
        }
    }

    if ($pagenum < $totalpages) {
        echo html_writer::link(
            new moodle_url('/local/heyday_scores/index.php', [
                'id' => $courseid,
                'p' => $pagenum + 1
            ]),
            'Next',
            ['class' => 'heyday-page-btn']
        );
    } else {
        echo html_writer::span('Next', 'heyday-page-btn is-disabled');
    }

    echo html_writer::end_div();
}

echo html_writer::end_div();

?>
<script>
(function () {
    'use strict';

    const searchInput = document.getElementById('heyday-score-search');
    const creditOnly = document.getElementById('heyday-credit-only');
    const sortButton = document.getElementById('heyday-sort-button');
    const list = document.getElementById('heyday-score-list');

    function applyFilters() {
        const search = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const onlyCredit = creditOnly ? creditOnly.checked : false;

        document.querySelectorAll('.heyday-score-row').forEach(function (row) {
            const name = row.getAttribute('data-name') || '';
            const credit = row.getAttribute('data-credit') === '1';

            const matchesSearch = !search || name.indexOf(search) !== -1;
            const matchesCredit = !onlyCredit || credit;

            row.style.display = (matchesSearch && matchesCredit) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (creditOnly) {
        creditOnly.addEventListener('change', applyFilters);
    }

    if (sortButton && list) {
        sortButton.addEventListener('click', function () {
            const rows = Array.from(list.querySelectorAll('.heyday-score-row'));

            rows.sort(function (a, b) {
                const an = a.getAttribute('data-name') || '';
                const bn = b.getAttribute('data-name') || '';
                return an.localeCompare(bn);
            });

            rows.forEach(function (row) {
                list.appendChild(row);
            });
        });
    }
})();
</script>
<?php

echo $OUTPUT->footer();