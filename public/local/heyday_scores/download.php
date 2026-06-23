<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/heyday_scores:view', $context);

$userid = $USER->id;

$filename = clean_filename($course->shortname . '_scores_' . userdate(time(), '%Y%m%d') . '.csv');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

fputcsv($out, [
    'Activity',
    'Status',
    'Score',
    'Maximum',
    'Counts for grade',
]);

$gradeitems = $DB->get_records_sql("
    SELECT gi.*
      FROM {grade_items} gi
     WHERE gi.courseid = :courseid
       AND gi.itemtype = 'mod'
       AND gi.itemmodule IN ('quiz', 'assign')
  ORDER BY gi.sortorder ASC
", ['courseid' => $course->id]);

foreach ($gradeitems as $item) {
    $categoryname = '';

    if (!empty($item->categoryid)) {
        $category = $DB->get_record('grade_categories', ['id' => $item->categoryid], '*', IGNORE_MISSING);
        if ($category) {
            $categoryname = $category->fullname;
        }
    }

    $idnumber = $item->idnumber ?? '';
    $categorylower = core_text::strtolower($categoryname);

    $noncredit = str_starts_with($idnumber, 'NC_') ||
        str_contains($categorylower, 'diagnostic') ||
        str_contains($categorylower, 'practice') ||
        str_contains($categorylower, 'pretest');

    $grade = grade_grade::fetch([
        'itemid' => $item->id,
        'userid' => $userid,
    ]);

    $maxgrade = (int)round($item->grademax);
    $status = 'Not Started';
    $score = '--';

    if ($grade && $grade->finalgrade !== null) {
        $score = round((float)$grade->finalgrade, 1);
        $status = 'Completed';
    }

    fputcsv($out, [
        format_string($item->itemname),
        $status,
        $score,
        $maxgrade,
        $noncredit ? 'No' : 'Yes',
    ]);
}

fclose($out);
exit;
