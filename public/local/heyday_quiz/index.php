<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Heyday ed2go-style quiz player shell.
 *
 * This plugin does not replace Moodle quiz processing. It keeps the real
 * Moodle quiz page inside a same-origin player frame and applies scoped
 * presentation styling to reduce Moodle clutter and match the Heyday shell.
 *
 * @package   local_heyday_quiz
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('id', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('moodle/course:view', $context);

$params = ['id' => $course->id];
if ($cmid) {
    $params['cmid'] = $cmid;
}

$PAGE->set_url(new moodle_url('/local/heyday_quiz/index.php', $params));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-heyday-quiz-clean');
$PAGE->set_title(format_string($course->fullname) . ' - Quiz');
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/heyday_quiz/styles.css'));
$PAGE->requires->js(new moodle_url('/local/heyday_quiz/quizframe.js'));

$modinfo = get_fast_modinfo($course);
$completion = new completion_info($course);
$sections = $modinfo->get_section_info_all();

$activecm = null;
if ($cmid) {
    try {
        $candidate = $modinfo->get_cm($cmid);
        if ((int)$candidate->course === (int)$course->id && $candidate->modname === 'quiz') {
            $activecm = $candidate;
        }
    } catch (moodle_exception $e) {
        $activecm = null;
    }
}

/**
 * Check whether a course section is a real lesson section.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_quiz_is_lesson_section(string $sectionname): bool {
    return (bool)preg_match('/^\s*lesson\s+\d+/i', trim($sectionname));
}

/**
 * Check whether a section is likely Resources.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_quiz_is_resources_section(string $sectionname): bool {
    return (bool)preg_match('/\bresources?\b/i', trim($sectionname));
}

/**
 * Check whether a section is likely Final Exam.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_quiz_is_finalexam_section(string $sectionname): bool {
    return (bool)preg_match('/\bfinal\s+exam\b/i', trim($sectionname));
}

/**
 * Return the first Moodle availability date timestamp found in availability JSON.
 *
 * @param mixed $node Decoded availability JSON node.
 * @return int|null
 */
function local_heyday_quiz_find_availability_date($node): ?int {
    if (empty($node)) {
        return null;
    }

    if (is_object($node)) {
        if (isset($node->type) && $node->type === 'date' && isset($node->t) && is_numeric($node->t)) {
            return (int)$node->t;
        }

        foreach (get_object_vars($node) as $value) {
            $found = local_heyday_quiz_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    if (is_array($node)) {
        foreach ($node as $value) {
            $found = local_heyday_quiz_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * Build a locked/release message for a course module.
 *
 * @param cm_info $cm Course module.
 * @return string
 */
function local_heyday_quiz_locked_message(cm_info $cm): string {
    $name = format_string($cm->name, true, ['context' => $cm->context]);

    $date = null;
    if (!empty($cm->availability)) {
        $availability = json_decode($cm->availability);
        $date = local_heyday_quiz_find_availability_date($availability);
    }

    if ($date) {
        $formatteddate = userdate($date, '%b %d, %Y %I:%M %p %Z');
        return get_string('availableon', 'local_heyday_quiz', [
            'name' => $name,
            'date' => $formatteddate,
        ]);
    }

    if (!empty($cm->availableinfo)) {
        return trim(strip_tags($cm->availableinfo));
    }

    return get_string('locked', 'local_heyday_quiz');
}

/**
 * Determine completion class and label.
 *
 * @param completion_info $completion Completion object.
 * @param cm_info $cm Course module.
 * @return array
 */
function local_heyday_quiz_completion_status(completion_info $completion, cm_info $cm): array {
    global $USER;

    if (!$completion->is_enabled($cm)) {
        return ['class' => 'notstarted', 'label' => ''];
    }

    $data = $completion->get_data($cm, false, $USER->id);

    if (!empty($data->completionstate)) {
        return ['class' => 'completed', 'label' => get_string('completed', 'completion')];
    }

    return ['class' => 'inprogress', 'label' => ''];
}

/**
 * Decide whether to show a module in the custom learner sidebar.
 *
 * @param cm_info $cm Course module.
 * @param context_course $context Course context.
 * @return bool
 */
function local_heyday_quiz_should_show_cm(cm_info $cm, context_course $context): bool {
    if ($cm->deletioninprogress) {
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
 * Get the delegated child section for a Moodle Subsection activity.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param cm_info $cm Course module.
 * @return section_info|null
 */
function local_heyday_quiz_get_subsection_section(course_modinfo $modinfo, cm_info $cm): ?section_info {
    if ($cm->modname !== 'subsection') {
        return null;
    }

    if (!method_exists($modinfo, 'get_section_info_by_component')) {
        return null;
    }

    try {
        $section = $modinfo->get_section_info_by_component('mod_subsection', $cm->instance);
        return $section ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Collect items from a section, following Moodle Subsection delegated sections.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param int $sectionnum Section number.
 * @param context_course $context Course context.
 * @param int $depth Sidebar depth.
 * @return array
 */
function local_heyday_quiz_collect_section_items(
    course_modinfo $modinfo,
    int $sectionnum,
    context_course $context,
    int $depth = 0
): array {
    $items = [];
    $cmids = $modinfo->sections[$sectionnum] ?? [];

    foreach ($cmids as $sectioncmid) {
        $cm = $modinfo->get_cm($sectioncmid);

        if (!local_heyday_quiz_should_show_cm($cm, $context)) {
            continue;
        }

        if ($cm->modname === 'subsection') {
            $items[] = [
                'type' => 'heading',
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'depth' => $depth,
            ];

            $childsection = local_heyday_quiz_get_subsection_section($modinfo, $cm);

            if ($childsection && isset($childsection->section)) {
                $childitems = local_heyday_quiz_collect_section_items(
                    $modinfo,
                    (int)$childsection->section,
                    $context,
                    $depth + 1
                );
                $items = array_merge($items, $childitems);
            }

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
 * Collect lesson groups — quiz-centric.
 *
 * Scans every section for quiz modules, skips pretest/final-exam, derives
 * a lesson number from the quiz name ("Lesson N …"), the section name, or
 * the CM idnumber (HEYDAY_LESSON<N>_QUIZ), then returns one group per lesson
 * number containing only quiz items.  This keeps the quiz-player sidebar lean:
 * one collapsible row per lesson, one link per quiz inside it.
 *
 * @param course_modinfo  $modinfo  Course modinfo.
 * @param array           $sections Course sections (from get_section_info_all).
 * @param stdClass        $course   Course record.
 * @param context_course  $context  Course context.
 * @return array
 */
function local_heyday_quiz_collect_lesson_groups(
    course_modinfo $modinfo,
    array $sections,
    stdClass $course,
    context_course $context
): array {

    $bylessonnum = [];  // int lesson_num => [items]

    foreach ($sections as $sectionnum => $section) {
        if ($sectionnum == 0) {
            continue;
        }

        $sectionname = get_section_name($course, $section);

        $cmids = $modinfo->sections[$sectionnum] ?? [];
        foreach ($cmids as $cmid) {
            try {
                $cm = $modinfo->get_cm($cmid);
            } catch (Throwable $e) {
                continue;
            }

            if (!local_heyday_quiz_should_show_cm($cm, $context)) {
                continue;
            }

            if ($cm->modname !== 'quiz') {
                continue;
            }

            $idnumber = strtoupper(trim((string)($cm->idnumber ?? '')));
            $quizname = strtolower(trim(format_string($cm->name)));

            // Skip pretest and final exam — handled by dedicated pages.
            if ($idnumber === 'HEYDAY_PRETEST' || str_contains($quizname, 'pretest')) {
                continue;
            }
            if (str_contains($quizname, 'final exam') || str_contains($quizname, 'final-exam')
                    || preg_match('/\bfinal\s*exam\b/i', $idnumber)) {
                continue;
            }

            // Derive lesson number.  Try quiz name first ("Lesson 3 Quiz"),
            // then section name ("Lesson 3 Introduction"), then idnumber.
            $lessonnum = null;

            if (preg_match('/\blesson\s*(\d+)/i', $quizname, $m)) {
                $lessonnum = (int)$m[1];
            } elseif (preg_match('/\blesson\s*(\d+)/i', $sectionname, $m)) {
                $lessonnum = (int)$m[1];
            } elseif (preg_match('/LESSON(\d+)/i', $idnumber, $m)) {
                $lessonnum = (int)$m[1];
            }

            if ($lessonnum === null) {
                continue;
            }

            $bylessonnum[$lessonnum][] = ['type' => 'cm', 'cm' => $cm, 'depth' => 0];
        }
    }

    if (empty($bylessonnum)) {
        return [];
    }

    ksort($bylessonnum);

    $groups = [];
    foreach ($bylessonnum as $lessonnum => $items) {
        $groups[] = [
            'name'  => 'Lesson ' . $lessonnum,
            'items' => $items,
        ];
    }

    return $groups;
}

/**
 * Check if a collected sidebar item is available.
 *
 * @param array $item Collected sidebar item.
 * @return bool
 */
function local_heyday_quiz_item_available(array $item): bool {
    if (($item['type'] ?? '') !== 'cm') {
        return false;
    }

    $cm = $item['cm'];
    return $cm->available && $cm->uservisible;
}

/**
 * URL for a collected sidebar item.
 *
 * Quiz activities open in this plugin. Other activities open in local_heyday_lessons
 * when available, otherwise in their normal Moodle page.
 *
 * @param stdClass $course Course record.
 * @param array $item Collected sidebar item.
 * @return moodle_url
 */
function local_heyday_quiz_item_url(stdClass $course, array $item): moodle_url {
    global $CFG;

    $cm = $item['cm'];

    if ($cm->modname === 'quiz') {
        return new moodle_url('/local/heyday_quiz/index.php', [
            'id' => $course->id,
            'cmid' => $cm->id,
        ]);
    }

    if (file_exists($CFG->dirroot . '/local/heyday_lessons/index.php')) {
        return new moodle_url('/local/heyday_lessons/index.php', [
            'id' => $course->id,
            'cmid' => $cm->id,
        ]);
    }

    return $cm->url ?: new moodle_url('/course/view.php', ['id' => $course->id]);
}

/**
 * Get the first available learner URL for a lesson group.
 *
 * @param stdClass $course Course record.
 * @param array $group Lesson group.
 * @return moodle_url|null
 */
function local_heyday_quiz_group_url(stdClass $course, array $group): ?moodle_url {
    foreach ($group['items'] as $item) {
        if (($item['type'] ?? '') === 'heading') {
            continue;
        }

        if (local_heyday_quiz_item_available($item)) {
            return local_heyday_quiz_item_url($course, $item);
        }
    }

    return null;
}

/**
 * Find first available quiz from lesson groups.
 *
 * @param array $lessongroups Lesson groups.
 * @return cm_info|null
 */
function local_heyday_quiz_first_available_quiz(array $lessongroups): ?cm_info {
    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') !== 'cm') {
                continue;
            }

            $cm = $item['cm'];
            if ($cm->modname === 'quiz' && $cm->available && $cm->uservisible) {
                return $cm;
            }
        }
    }

    return null;
}

/**
 * Find next available activity after active item.
 *
 * @param stdClass $course Course record.
 * @param array $lessongroups Lesson groups.
 * @param cm_info $activecm Active course module.
 * @return array|null
 */
function local_heyday_quiz_next_available_item(stdClass $course, array $lessongroups, cm_info $activecm): ?array {
    $foundactive = false;

    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') !== 'cm') {
                continue;
            }

            $cm = $item['cm'];
            if (!$cm->available || !$cm->uservisible) {
                continue;
            }

            if ($foundactive) {
                return [
                    'title' => format_string($cm->name, true, ['context' => $cm->context]),
                    'url' => local_heyday_quiz_item_url($course, $item),
                ];
            }

            if ((int)$cm->id === (int)$activecm->id) {
                $foundactive = true;
            }
        }
    }

    return null;
}

/**
 * Find the parent lesson group name for the active cm.
 *
 * @param array $lessongroups Lesson groups.
 * @param cm_info $activecm Active module.
 * @return string
 */
function local_heyday_quiz_active_group_name(array $lessongroups, cm_info $activecm): string {
    foreach ($lessongroups as $group) {
        foreach ($group['items'] as $item) {
            if (($item['type'] ?? '') !== 'cm') {
                continue;
            }

            if ((int)$item['cm']->id === (int)$activecm->id) {
                return $group['name'];
            }
        }
    }

    return '';
}

/**
 * Find a course section URL by regex helper.
 *
 * @param array $sections Course sections.
 * @param stdClass $course Course record.
 * @param callable $matcher Matcher callback.
 * @return moodle_url
 */
function local_heyday_quiz_section_url(array $sections, stdClass $course, callable $matcher): moodle_url {
    foreach ($sections as $sectionnum => $section) {
        if ($sectionnum == 0) {
            continue;
        }
        $sectionname = get_section_name($course, $section);
        if ($matcher($sectionname)) {
            return new moodle_url('/course/view.php', [
                'id' => $course->id,
                'section' => $sectionnum,
            ]);
        }
    }

    return new moodle_url('/course/view.php', ['id' => $course->id]);
}

$lessongroups = local_heyday_quiz_collect_lesson_groups($modinfo, $sections, $course, $context);

if (!$activecm) {
    $activecm = local_heyday_quiz_first_available_quiz($lessongroups);
}

$quizrecord = null;
$frameurl = null;
$activeislocked = false;
$activestatus = null;
$activegroupname = '';
$nextitem = null;

if ($activecm) {
    $activeislocked = !$activecm->available || !$activecm->uservisible;
    $activestatus = local_heyday_quiz_completion_status($completion, $activecm);
    $activegroupname = local_heyday_quiz_active_group_name($lessongroups, $activecm);
    $nextitem = local_heyday_quiz_next_available_item($course, $lessongroups, $activecm);
    $quizrecord = $DB->get_record('quiz', ['id' => $activecm->instance], '*', IGNORE_MISSING);

    if (!$activeislocked) {
        $autoopenattempt = (int)get_config('local_heyday_quiz', 'autoopenattempt');
        if ($autoopenattempt) {
            $frameurl = new moodle_url('/mod/quiz/attempt.php', ['cmid' => $activecm->id]);
        } else {
            $frameurl = new moodle_url('/mod/quiz/view.php', ['id' => $activecm->id]);
        }
    }
}

$coursehomeurl = file_exists($CFG->dirroot . '/local/heyday_coursehome/index.php')
    ? new moodle_url('/local/heyday_coursehome/index.php', ['id' => $course->id])
    : new moodle_url('/course/view.php', ['id' => $course->id]);
$scoresurl = file_exists($CFG->dirroot . '/local/heyday_scores/index.php')
    ? new moodle_url('/local/heyday_scores/index.php', ['id' => $course->id])
    : new moodle_url('/grade/report/user/index.php', ['id' => $course->id]);
$discussionsurl = file_exists($CFG->dirroot . '/local/heyday_discussions/index.php')
    ? new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id])
    : new moodle_url('/mod/forum/index.php', ['id' => $course->id]);
$gettingstartedurl = file_exists($CFG->dirroot . '/local/heyday_gettingstarted/view.php')
    ? new moodle_url('/local/heyday_gettingstarted/view.php', ['courseid' => $course->id, 'page' => 'overview'])
    : new moodle_url('/course/view.php', ['id' => $course->id]);
$pretesturl = file_exists($CFG->dirroot . '/local/heyday_pretest/index.php')
    ? new moodle_url('/local/heyday_pretest/index.php', ['id' => $course->id])
    : new moodle_url('/course/view.php', ['id' => $course->id]);
$resourcesurl = local_heyday_quiz_section_url($sections, $course, 'local_heyday_quiz_is_resources_section');
$finalexamurl = local_heyday_quiz_section_url($sections, $course, 'local_heyday_quiz_is_finalexam_section');


echo $OUTPUT->header();
?>

<div class="heyday-ed2go-topbar">
    <div class="heyday-ed2go-topbar-left"></div>
    <div class="heyday-ed2go-topbar-right">
        <span aria-hidden="true">⌕</span>
        <span class="heyday-topbar-separator"></span>
        <span aria-hidden="true">?</span>
        <span class="heyday-topbar-separator"></span>
        <span aria-hidden="true">♙</span>
        <span><?php echo fullname($USER); ?></span>
        <span aria-hidden="true">⌄</span>
    </div>
</div>

<div id="hdQzStickyBar" class="heyday-quiz-sticky-bar" aria-hidden="true">
    <span class="heyday-quiz-sticky-left">
        <?php echo $activegroupname ? format_string($activegroupname) : format_string($course->fullname); ?>
    </span>
    <span class="heyday-quiz-sticky-right">
        <strong><?php echo $activecm ? format_string($activecm->name, true, ['context' => $activecm->context]) : ''; ?></strong>
        <button type="button" onclick="window.print()" aria-label="Print">▣</button>
    </span>
</div>

<div class="heyday-quiz-page">
    <div class="heyday-quiz-shell">
        <aside class="heyday-quiz-sidebar" aria-label="Course menu">
            <nav class="heyday-main-menu" aria-label="Main course navigation">
                <a href="<?php echo $coursehomeurl->out(false); ?>"><?php echo get_string('coursehome', 'local_heyday_quiz'); ?></a>
                <a href="<?php echo $scoresurl->out(false); ?>"><?php echo get_string('scores', 'local_heyday_quiz'); ?></a>
                <a href="<?php echo $discussionsurl->out(false); ?>"><?php echo get_string('discussions', 'local_heyday_quiz'); ?></a>
                <a href="<?php echo $gettingstartedurl->out(false); ?>"><?php echo get_string('gettingstarted', 'local_heyday_quiz'); ?></a>
                <a href="<?php echo $pretesturl->out(false); ?>"><?php echo get_string('pretest', 'local_heyday_quiz'); ?></a>
            </nav>

            <div class="heyday-lesson-list">
                <?php foreach ($lessongroups as $group): ?>
                    <?php
                    $groupurl = local_heyday_quiz_group_url($course, $group);
                    $groupclasses = ['heyday-lesson-group'];
                    if (!$groupurl) {
                        $groupclasses[] = 'is-locked';
                    }
                    ?>
                    <section class="<?php echo implode(' ', $groupclasses); ?>">
                        <h3>
                            <?php if ($groupurl): ?>
                                <a class="heyday-lesson-group-title" href="<?php echo $groupurl->out(false); ?>">
                                    <span><?php echo $group['name']; ?></span>
                                    <span class="heyday-group-progress" aria-hidden="true"></span>
                                </a>
                            <?php else: ?>
                                <span class="heyday-lesson-group-title is-disabled">
                                    <span><?php echo $group['name']; ?></span>
                                    <span class="heyday-group-lock" aria-hidden="true">🔒</span>
                                </span>
                            <?php endif; ?>
                        </h3>

                        <?php foreach ($group['items'] as $item): ?>
                            <?php if (($item['type'] ?? '') === 'heading'): ?>
                                <div class="heyday-subsection-title depth-<?php echo (int)$item['depth']; ?>">
                                    <?php echo $item['name']; ?>
                                </div>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php
                            $cm = $item['cm'];
                            $depth = (int)$item['depth'];
                            $isactive = $activecm && ((int)$activecm->id === (int)$cm->id);
                            $islocked = !$cm->available || !$cm->uservisible;
                            $status = local_heyday_quiz_completion_status($completion, $cm);
                            $itemclasses = [
                                'heyday-lesson-item',
                                'depth-' . $depth,
                                $status['class'],
                            ];
                            if ($cm->modname === 'quiz') {
                                $itemclasses[] = 'is-quiz';
                            }
                            if ($isactive) {
                                $itemclasses[] = 'is-current';
                            }
                            if ($islocked) {
                                $itemclasses[] = 'is-locked';
                            }
                            $itemurl = local_heyday_quiz_item_url($course, $item);
                            ?>

                            <?php if ($islocked): ?>
                                <div class="<?php echo implode(' ', $itemclasses); ?>">
                                    <span class="heyday-current-arrow" aria-hidden="true"></span>
                                    <span class="heyday-lesson-text">
                                        <?php echo format_string($cm->name, true, ['context' => $cm->context]); ?>
                                        <small><?php echo s(local_heyday_quiz_locked_message($cm)); ?></small>
                                    </span>
                                    <span class="heyday-status-icon locked" aria-hidden="true">🔒</span>
                                </div>
                            <?php else: ?>
                                <a class="<?php echo implode(' ', $itemclasses); ?>" href="<?php echo $itemurl->out(false); ?>">
                                    <span class="heyday-current-arrow" aria-hidden="true"></span>
                                    <span class="heyday-lesson-text">
                                        <?php echo format_string($cm->name, true, ['context' => $cm->context]); ?>
                                        <small><?php echo s($status['label']); ?></small>
                                    </span>
                                    <span class="heyday-status-icon" aria-hidden="true">
                                        <?php echo $status['class'] === 'completed' ? '✓' : ''; ?>
                                    </span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <nav class="heyday-main-menu heyday-after-lessons-menu" aria-label="More course navigation">
                    <a href="<?php echo $resourcesurl->out(false); ?>"><?php echo get_string('resources', 'local_heyday_quiz'); ?></a>
                    <a href="<?php echo $finalexamurl->out(false); ?>"><?php echo get_string('finalexam', 'local_heyday_quiz'); ?></a>
                </nav>
            </div>
        </aside>

        <main class="heyday-quiz-main">
            <div class="heyday-player-card">
                <div class="heyday-player-topbar">
                    <a class="heyday-back-link" href="<?php echo $coursehomeurl->out(false); ?>" aria-label="Back">←</a>
                    <span class="heyday-bookmark" aria-hidden="true">♡</span>
                    <span class="heyday-topbar-spacer"></span>
                    <button type="button" class="heyday-icon-button" onclick="window.print()" aria-label="Print">▣</button>
                    <button type="button" class="heyday-icon-button" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()" aria-label="Fullscreen">⛶</button>
                </div>

                <div class="heyday-player-heading">
                    <div class="heyday-course-kicker"><?php echo format_string($course->fullname); ?></div>
                    <?php if ($activegroupname): ?>
                        <div class="heyday-lesson-kicker"><?php echo format_string($activegroupname); ?></div>
                    <?php endif; ?>
                    <h1><?php echo $activecm ? format_string($activecm->name, true, ['context' => $activecm->context]) : get_string('quiz', 'local_heyday_quiz'); ?></h1>
                </div>

                <?php if (!$activecm): ?>
                    <div class="heyday-intro-card">
                        <p><?php echo get_string('selectquiz', 'local_heyday_quiz'); ?></p>
                    </div>
                <?php elseif ($activeislocked): ?>
                    <div class="heyday-locked-card">
                        <div class="heyday-locked-icon">🔒</div>
                        <h2><?php echo get_string('locked', 'local_heyday_quiz'); ?></h2>
                        <p><?php echo s(local_heyday_quiz_locked_message($activecm)); ?></p>
                    </div>
                <?php else: ?>
                    <?php if ($quizrecord && trim((string)$quizrecord->intro) !== ''): ?>
                        <button type="button" class="heyday-instructions-toggle" data-heyday-toggle-instructions>
                            ⓘ <?php echo get_string('showinstructions', 'local_heyday_quiz'); ?>
                        </button>
                        <div class="heyday-quiz-instructions" data-heyday-instructions hidden>
                            <?php
                            echo format_text($quizrecord->intro, $quizrecord->introformat, [
                                'context' => $activecm->context,
                                'overflowdiv' => true,
                            ]);
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="heyday-quiz-frame-wrap">
                        <iframe
                            id="heyday-quiz-frame"
                            class="heyday-quiz-frame"
                            src="<?php echo $frameurl->out(false); ?>"
                            title="<?php echo s(format_string($activecm->name, true, ['context' => $activecm->context])); ?>"
                            data-heyday-quiz-frame="1"
                        ></iframe>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($activecm && !$activeislocked): ?>
                <?php if ($activestatus && $activestatus['class'] === 'completed'): ?>
                    <div class="heyday-completion-row">
                        <div class="heyday-completion-check">✓</div>
                        <div><strong>Activity complete</strong></div>
                    </div>
                <?php endif; ?>

                <?php if ($nextitem): ?>
                    <div class="heyday-nextup-row">
                        <div class="heyday-nextup-label">Next Up</div>
                        <a href="<?php echo $nextitem['url']->out(false); ?>">
                            <?php echo $nextitem['title']; ?>
                            <span>activity</span>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <footer class="heyday-player-footer">
                <a href="#">Course Support</a>
                <span></span>
                <a href="#">Cookie Settings</a>
                <small>© 2026 Heyday Training LMS</small>
            </footer>
        </main>
    </div>
</div>

<?php
?>
<script>
(function () {
    'use strict';
    var bar     = document.getElementById('hdQzStickyBar');
    var heading = document.querySelector('.heyday-player-heading');
    if (bar && heading) {
        window.addEventListener('scroll', function () {
            var bottom  = heading.getBoundingClientRect().bottom;
            var visible = bottom < 48;
            bar.classList.toggle('is-visible', visible);
            bar.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }, { passive: true });
    }
    var frame = document.getElementById('heyday-quiz-frame');
    if (frame) {
        frame.addEventListener('load', function () {
            try {
                var loc = frame.contentWindow.location.href;
                if (loc && loc.indexOf('/local/heyday_quiz/index.php') !== -1) {
                    window.location.reload();
                }
            } catch (e) {}
        });
    }
}());
</script>
<?php
echo $OUTPUT->footer();
