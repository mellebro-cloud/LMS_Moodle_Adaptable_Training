<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * ed2go-style Pretest shell for Heyday LMS.
 *
 * The actual quiz is opened in Moodle's own quiz page inside the player frame.
 * This preserves Moodle quiz attempts, saving, grading, availability, and submission.
 *
 * @package   local_heyday_pretest
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/modinfolib.php');

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

$PAGE->set_url(new moodle_url('/local/heyday_pretest/index.php', $params));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-heyday-pretest-clean');
$PAGE->set_title(format_string($course->fullname) . ' - ' . get_string('pretest', 'local_heyday_pretest'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/heyday_pretest/styles.css'));

$modinfo = get_fast_modinfo($course);
$completion = new completion_info($course);
$sections = $modinfo->get_section_info_all();

/**
 * Check whether a course section is a real lesson section.
 *
 * @param string $sectionname Section name.
 * @return bool
 */
function local_heyday_pretest_is_lesson_section(string $sectionname): bool {
    return (bool)preg_match('/^\s*lesson\s+\d+/i', trim($sectionname));
}

/**
 * Decide whether to show a course module in the learner sidebar.
 *
 * @param cm_info $cm Course module.
 * @param context_course $context Course context.
 * @return bool
 */
function local_heyday_pretest_should_show_cm(cm_info $cm, context_course $context): bool {
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
 * Return the first Moodle availability date timestamp found in availability JSON.
 *
 * @param mixed $node Decoded JSON node.
 * @return int|null
 */
function local_heyday_pretest_find_availability_date($node): ?int {
    if (empty($node)) {
        return null;
    }

    if (is_object($node)) {
        if (isset($node->type) && $node->type === 'date' && isset($node->t) && is_numeric($node->t)) {
            return (int)$node->t;
        }

        foreach (get_object_vars($node) as $value) {
            $found = local_heyday_pretest_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    if (is_array($node)) {
        foreach ($node as $value) {
            $found = local_heyday_pretest_find_availability_date($value);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * Build locked/release message for a course module.
 *
 * @param cm_info $cm Course module.
 * @return string
 */
function local_heyday_pretest_locked_message(cm_info $cm): string {
    $name = format_string($cm->name, true, ['context' => $cm->context]);

    $date = null;
    if (!empty($cm->availability)) {
        $availability = json_decode($cm->availability);
        $date = local_heyday_pretest_find_availability_date($availability);
    }

    if ($date) {
        return get_string('availableon', 'local_heyday_pretest', [
            'name' => $name,
            'date' => userdate($date, '%b %d, %Y %I:%M %p %Z'),
        ]);
    }

    if (!empty($cm->availableinfo)) {
        return trim(strip_tags($cm->availableinfo));
    }

    return get_string('locked', 'local_heyday_pretest');
}

/**
 * Determine completion class and label.
 *
 * @param completion_info $completion Completion object.
 * @param cm_info $cm Course module.
 * @return array{class:string,label:string}
 */
function local_heyday_pretest_completion_status(completion_info $completion, cm_info $cm): array {
    global $USER;

    if (!$completion->is_enabled($cm)) {
        return ['class' => 'notstarted', 'label' => get_string('notstarted', 'local_heyday_pretest')];
    }

    $data = $completion->get_data($cm, false, $USER->id);

    if (!empty($data->completionstate)) {
        return ['class' => 'completed', 'label' => get_string('completed', 'local_heyday_pretest')];
    }

    return ['class' => 'inprogress', 'label' => get_string('inprogress', 'local_heyday_pretest')];
}

/**
 * Get the delegated child section for a Moodle Subsection activity.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param cm_info $cm Course module.
 * @return section_info|null
 */
function local_heyday_pretest_get_subsection_section(course_modinfo $modinfo, cm_info $cm): ?section_info {
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
 * Find the first available real activity inside a section, following Moodle subsections.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param int $sectionnum Section number.
 * @param context_course $context Course context.
 * @return cm_info|null
 */
function local_heyday_pretest_first_available_cm_in_section(
    course_modinfo $modinfo,
    int $sectionnum,
    context_course $context
): ?cm_info {
    $cmids = $modinfo->sections[$sectionnum] ?? [];

    foreach ($cmids as $sectioncmid) {
        $cm = $modinfo->get_cm($sectioncmid);

        if (!local_heyday_pretest_should_show_cm($cm, $context)) {
            continue;
        }

        if ($cm->modname === 'subsection') {
            $childsection = local_heyday_pretest_get_subsection_section($modinfo, $cm);
            if ($childsection && isset($childsection->section)) {
                $childcm = local_heyday_pretest_first_available_cm_in_section($modinfo, (int)$childsection->section, $context);
                if ($childcm) {
                    return $childcm;
                }
            }
            continue;
        }

        if ($cm->available && $cm->uservisible) {
            return $cm;
        }
    }

    return null;
}

/**
 * Find the pretest quiz in the course.
 *
 * Priority:
 * 1. explicit cmid if it is a quiz in this course
 * 2. quiz with Pretest in the name
 * 3. first quiz inside a section named Pretest
 * 4. first visible quiz in the course
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param array $sections Course sections.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @param int $explicitcmid Optional explicit cmid.
 * @return cm_info|null
 */
function local_heyday_pretest_find_quiz_cm(
    course_modinfo $modinfo,
    array $sections,
    stdClass $course,
    context_course $context,
    int $explicitcmid = 0
): ?cm_info {
    if ($explicitcmid) {
        try {
            $cm = $modinfo->get_cm($explicitcmid);
            if ((int)$cm->course === (int)$course->id && $cm->modname === 'quiz') {
                return $cm;
            }
        } catch (moodle_exception $e) {
            // Continue to automatic discovery.
        }
    }

    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname !== 'quiz') {
            continue;
        }
        if (!local_heyday_pretest_should_show_cm($cm, $context)) {
            continue;
        }
        if (preg_match('/pre\s*test|pretest/i', $cm->name)) {
            return $cm;
        }
    }

    foreach ($sections as $sectionnum => $section) {
        if ($sectionnum == 0) {
            continue;
        }
        if (!$section->visible && !has_capability('moodle/course:viewhiddensections', $context)) {
            continue;
        }

        $sectionname = get_section_name($course, $section);
        if (!preg_match('/pre\s*test|pretest/i', $sectionname)) {
            continue;
        }

        $cmids = $modinfo->sections[$sectionnum] ?? [];
        foreach ($cmids as $sectioncmid) {
            $cm = $modinfo->get_cm($sectioncmid);
            if ($cm->modname === 'quiz' && local_heyday_pretest_should_show_cm($cm, $context)) {
                return $cm;
            }
        }
    }

    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname === 'quiz' && local_heyday_pretest_should_show_cm($cm, $context)) {
            return $cm;
        }
    }

    return null;
}

/**
 * Collect lesson groups for the sidebar.
 *
 * @param course_modinfo $modinfo Course modinfo.
 * @param array $sections Course sections.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return array
 */
function local_heyday_pretest_collect_lesson_groups(
    course_modinfo $modinfo,
    array $sections,
    stdClass $course,
    context_course $context
): array {
    $groups = [];

    foreach ($sections as $sectionnum => $section) {
        if ($sectionnum == 0) {
            continue;
        }

        if (!$section->visible && !has_capability('moodle/course:viewhiddensections', $context)) {
            continue;
        }

        $sectionname = get_section_name($course, $section);
        if (!local_heyday_pretest_is_lesson_section($sectionname)) {
            continue;
        }

        $firstcm = local_heyday_pretest_first_available_cm_in_section($modinfo, (int)$sectionnum, $context);

        $groups[] = [
            'name' => format_string($sectionname),
            'url' => $firstcm ? new moodle_url('/local/heyday_lessons/index.php', ['id' => $course->id, 'cmid' => $firstcm->id]) : null,
            'locked' => $firstcm ? false : true,
            'cm' => $firstcm,
        ];
    }

    return $groups;
}

/**
 * Find the next lesson URL after Pretest.
 *
 * @param array $lessongroups Lesson groups.
 * @return moodle_url|null
 */
function local_heyday_pretest_first_lesson_url(array $lessongroups): ?moodle_url {
    foreach ($lessongroups as $group) {
        if (!empty($group['url']) && $group['url'] instanceof moodle_url) {
            return $group['url'];
        }
    }

    return null;
}

$quizcm = local_heyday_pretest_find_quiz_cm($modinfo, $sections, $course, $context, $cmid);
$quiz = null;
$quizintro = '';
$quizlocked = false;
$quizlockedmessage = '';
$quizurl = null;
$quizstatus = null;

if ($quizcm) {
    $quiz = $DB->get_record('quiz', ['id' => $quizcm->instance], '*', IGNORE_MISSING);
    $quizlocked = !$quizcm->available || !$quizcm->uservisible;
    $quizlockedmessage = $quizlocked ? local_heyday_pretest_locked_message($quizcm) : '';
    $quizurl = $quizcm->url ?: new moodle_url('/mod/quiz/view.php', ['id' => $quizcm->id]);
    $quizstatus = local_heyday_pretest_completion_status($completion, $quizcm);

    if ($quiz) {
        $quizintro = trim(format_module_intro('quiz', $quiz, $quizcm->id));
    }
}

$lessongroups = local_heyday_pretest_collect_lesson_groups($modinfo, $sections, $course, $context);
$firstlessonurl = local_heyday_pretest_first_lesson_url($lessongroups);

$coursehomeurl = new moodle_url('/local/heyday_coursehome/index.php', ['id' => $course->id]);
$scoresurl = new moodle_url('/local/heyday_scores/index.php', ['id' => $course->id]);
$discussionsurl = new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id]);
$gettingstartedurl = new moodle_url('/local/heyday_gettingstarted/view.php', [
    'courseid' => $course->id,
    'page' => 'overview',
]);
$pretesturl = new moodle_url('/local/heyday_pretest/index.php', ['id' => $course->id]);
$resourcesurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$finalexamurl = new moodle_url('/local/heyday_finalexam/index.php', ['id' => $course->id]);

if ($quizcm) {
    $pretesturl->param('cmid', $quizcm->id);
}

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

<div class="heyday-pretest-page">
    <div class="heyday-pretest-shell">
        <aside class="heyday-pretest-sidebar" aria-label="Course menu">
            <nav class="heyday-main-menu" aria-label="Main course navigation">
                <a href="<?php echo $coursehomeurl->out(false); ?>"><?php echo get_string('coursehome', 'local_heyday_pretest'); ?></a>
                <a href="<?php echo $scoresurl->out(false); ?>"><?php echo get_string('scores', 'local_heyday_pretest'); ?></a>
                <a href="<?php echo $discussionsurl->out(false); ?>"><?php echo get_string('discussions', 'local_heyday_pretest'); ?></a>
                <a href="<?php echo $gettingstartedurl->out(false); ?>"><?php echo get_string('gettingstarted', 'local_heyday_pretest'); ?></a>
                <a class="is-active" href="<?php echo $pretesturl->out(false); ?>"><?php echo get_string('pretest', 'local_heyday_pretest'); ?></a>
            </nav>

            <div class="heyday-lesson-list">
                <?php foreach ($lessongroups as $group): ?>
                    <?php
                    $groupclasses = ['heyday-lesson-group-link'];
                    if (!empty($group['locked'])) {
                        $groupclasses[] = 'is-locked';
                    }
                    $status = null;
                    if (!empty($group['cm']) && $group['cm'] instanceof cm_info) {
                        $status = local_heyday_pretest_completion_status($completion, $group['cm']);
                    }
                    ?>
                    <?php if (!empty($group['url']) && $group['url'] instanceof moodle_url): ?>
                        <a class="<?php echo implode(' ', $groupclasses); ?>" href="<?php echo $group['url']->out(false); ?>">
                            <span><?php echo $group['name']; ?></span>
                            <span class="heyday-group-progress <?php echo $status ? s($status['class']) : ''; ?>" aria-hidden="true"></span>
                        </a>
                    <?php else: ?>
                        <div class="<?php echo implode(' ', $groupclasses); ?>">
                            <span><?php echo $group['name']; ?></span>
                            <span class="heyday-group-lock" aria-hidden="true">🔒</span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <nav class="heyday-main-menu heyday-after-lessons-menu" aria-label="More course navigation">
                <a href="<?php echo $resourcesurl->out(false); ?>"><?php echo get_string('resources', 'local_heyday_pretest'); ?></a>
                <a href="<?php echo $finalexamurl->out(false); ?>"><?php echo get_string('finalexam', 'local_heyday_pretest'); ?></a>
            </nav>
        </aside>

        <main class="heyday-pretest-main">
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
                    <h1><?php echo $quizcm ? format_string($quizcm->name, true, ['context' => $quizcm->context]) : get_string('pretest', 'local_heyday_pretest'); ?></h1>
                </div>

                <?php if (!$quizcm): ?>
                    <div class="heyday-empty-card">
                        <h2><?php echo get_string('nopretestfound', 'local_heyday_pretest'); ?></h2>
                        <p><?php echo get_string('nopretestfound_help', 'local_heyday_pretest'); ?></p>
                    </div>
                <?php elseif ($quizlocked): ?>
                    <div class="heyday-locked-card">
                        <div class="heyday-locked-icon">🔒</div>
                        <h2><?php echo get_string('locked', 'local_heyday_pretest'); ?></h2>
                        <p><?php echo s($quizlockedmessage); ?></p>
                    </div>
                <?php else: ?>
                    <div class="heyday-instructions-panel">
                        <button type="button" class="heyday-instructions-toggle" data-show-label="<?php echo s(get_string('showinstructions', 'local_heyday_pretest')); ?>" data-hide-label="<?php echo s(get_string('hideinstructions', 'local_heyday_pretest')); ?>" aria-expanded="false">
                            ⓘ <?php echo get_string('showinstructions', 'local_heyday_pretest'); ?>
                        </button>
                        <div class="heyday-instructions-content" hidden>
                            <?php if ($quizintro !== ''): ?>
                                <?php echo $quizintro; ?>
                            <?php else: ?>
                                <p><?php echo get_string('instructionsfallback', 'local_heyday_pretest'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="heyday-quiz-frame-wrap">
                        <iframe id="heydayPretestFrame" class="heyday-pretest-frame" src="<?php echo $quizurl->out(false); ?>" title="<?php echo s(format_string($quizcm->name, true, ['context' => $quizcm->context])); ?>"></iframe>
                    </div>

                    <div class="heyday-open-moodle-link">
                        <a href="<?php echo $quizurl->out(false); ?>"><?php echo get_string('openactivity', 'local_heyday_pretest'); ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($quizcm && !$quizlocked && $quizstatus && $quizstatus['class'] === 'completed'): ?>
                <div class="heyday-completion-row">
                    <div class="heyday-completion-check">✓</div>
                    <div><strong><?php echo get_string('completed', 'local_heyday_pretest'); ?></strong></div>
                </div>
            <?php endif; ?>

            <?php if ($firstlessonurl): ?>
                <div class="heyday-nextup-row">
                    <div class="heyday-nextup-label"><?php echo get_string('nextup', 'local_heyday_pretest'); ?></div>
                    <a href="<?php echo $firstlessonurl->out(false); ?>">
                        <?php echo count($lessongroups) ? $lessongroups[0]['name'] : 'Lesson 1'; ?>
                        <span><?php echo get_string('activity', 'local_heyday_pretest'); ?></span>
                    </a>
                </div>
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

<script>
(function() {
    var toggle = document.querySelector('.heyday-instructions-toggle');
    var content = document.querySelector('.heyday-instructions-content');
    if (toggle && content) {
        toggle.addEventListener('click', function() {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            content.hidden = expanded;
            toggle.textContent = 'ⓘ ' + (expanded ? toggle.dataset.showLabel : toggle.dataset.hideLabel);
        });
    }

    var frame = document.getElementById('heydayPretestFrame');
    if (!frame) {
        return;
    }

    function cleanQuizFrame() {
        try {
            var doc = frame.contentDocument || frame.contentWindow.document;
            if (!doc || !doc.body) {
                return;
            }

            doc.documentElement.classList.add('heyday-pretest-iframe-root');
            doc.body.classList.add('heyday-pretest-iframe-body');

            if (!doc.getElementById('heyday-pretest-frame-css')) {
                var style = doc.createElement('style');
                style.id = 'heyday-pretest-frame-css';
                style.textContent = `
                    body.heyday-pretest-iframe-body {
                        background: #ffffff !important;
                        padding: 0 !important;
                        margin: 0 !important;
                        overflow-x: hidden !important;
                    }
                    body.heyday-pretest-iframe-body .navbar,
                    body.heyday-pretest-iframe-body nav.navbar,
                    body.heyday-pretest-iframe-body #page-header,
                    body.heyday-pretest-iframe-body #page-navbar,
                    body.heyday-pretest-iframe-body .secondary-navigation,
                    body.heyday-pretest-iframe-body .activity-navigation,
                    body.heyday-pretest-iframe-body #theme_boost-drawers-courseindex,
                    body.heyday-pretest-iframe-body .drawer-toggles,
                    body.heyday-pretest-iframe-body .drawer-left,
                    body.heyday-pretest-iframe-body .drawer-right,
                    body.heyday-pretest-iframe-body footer#page-footer,
                    body.heyday-pretest-iframe-body .breadcrumb,
                    body.heyday-pretest-iframe-body .tertiary-navigation {
                        display: none !important;
                    }
                    body.heyday-pretest-iframe-body #page,
                    body.heyday-pretest-iframe-body #page.drawers,
                    body.heyday-pretest-iframe-body #page-content,
                    body.heyday-pretest-iframe-body #region-main,
                    body.heyday-pretest-iframe-body .main-inner,
                    body.heyday-pretest-iframe-body #topofscroll {
                        margin: 0 !important;
                        padding: 0 !important;
                        max-width: none !important;
                        width: 100% !important;
                        border: 0 !important;
                        background: #ffffff !important;
                        box-shadow: none !important;
                    }
                    body.heyday-pretest-iframe-body h1,
                    body.heyday-pretest-iframe-body .page-header-headings,
                    body.heyday-pretest-iframe-body .quizinfo,
                    body.heyday-pretest-iframe-body .activity-header,
                    body.heyday-pretest-iframe-body .activity-information {
                        display: none !important;
                    }
                    body.heyday-pretest-iframe-body .que {
                        border: 0 !important;
                        border-bottom: 1px dashed #d6dde4 !important;
                        margin: 0 !important;
                        padding: 20px 0 22px !important;
                        background: transparent !important;
                    }
                    body.heyday-pretest-iframe-body .que .info {
                        float: left !important;
                        width: 44px !important;
                        min-height: 38px !important;
                        margin: 0 18px 0 0 !important;
                        padding: 0 !important;
                        background: transparent !important;
                        border: 0 !important;
                    }
                    body.heyday-pretest-iframe-body .que .info .qno,
                    body.heyday-pretest-iframe-body .que .info .no {
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        width: 42px !important;
                        height: 38px !important;
                        border-radius: 0 20px 20px 0 !important;
                        background: #667380 !important;
                        color: #ffffff !important;
                        font-weight: 700 !important;
                    }
                    body.heyday-pretest-iframe-body .que .content {
                        margin-left: 62px !important;
                    }
                    body.heyday-pretest-iframe-body .qtext {
                        margin: 0 0 16px !important;
                        font-size: 16px !important;
                        line-height: 1.55 !important;
                        color: #111827 !important;
                    }
                    body.heyday-pretest-iframe-body .answer div.r0,
                    body.heyday-pretest-iframe-body .answer div.r1,
                    body.heyday-pretest-iframe-body .answer .r0,
                    body.heyday-pretest-iframe-body .answer .r1 {
                        display: flex !important;
                        align-items: center !important;
                        min-height: 40px !important;
                        margin: 10px 0 !important;
                        padding: 0 !important;
                        background: #f3f3f3 !important;
                        border: 0 !important;
                    }
                    body.heyday-pretest-iframe-body .answer .answernumber {
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        min-width: 48px !important;
                        align-self: stretch !important;
                        margin: 0 16px 0 0 !important;
                        background: #d8dde2 !important;
                        color: #006997 !important;
                        font-weight: 500 !important;
                    }
                    body.heyday-pretest-iframe-body .answer input[type="radio"] {
                        margin-right: 12px !important;
                        accent-color: #0076a8 !important;
                    }
                    body.heyday-pretest-iframe-body .submitbtns,
                    body.heyday-pretest-iframe-body .quizattemptcounts,
                    body.heyday-pretest-iframe-body .continuebutton {
                        text-align: right !important;
                        margin: 26px 0 0 !important;
                    }
                    body.heyday-pretest-iframe-body input[type="submit"],
                    body.heyday-pretest-iframe-body button[type="submit"],
                    body.heyday-pretest-iframe-body .btn-primary {
                        border-radius: 3px !important;
                    }
                `;
                doc.head.appendChild(style);
            }

            var height = Math.max(
                doc.body.scrollHeight,
                doc.documentElement.scrollHeight,
                720
            );
            frame.style.height = Math.min(Math.max(height + 40, 720), 6000) + 'px';
        } catch (e) {
            frame.style.height = '900px';
        }
    }

    frame.addEventListener('load', function() {
        cleanQuizFrame();
        setTimeout(cleanQuizFrame, 200);
        setTimeout(cleanQuizFrame, 800);
        setTimeout(cleanQuizFrame, 1600);
    });
})();
</script>

<?php
echo $OUTPUT->footer();
