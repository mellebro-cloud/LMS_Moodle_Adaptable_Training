<?php
// Local plugin callbacks for Heyday Pretest.
// This file restyles Moodle core quiz attempt/review/summary pages for Pretest only.

defined('MOODLE_INTERNAL') || die();
if (!function_exists('local_heyday_pretest_is_pretest_quiz')) {
    /**
     * Decide whether a quiz activity should be treated as the course Pretest.
     *
     * @param stdClass $quiz Quiz database record.
     * @return bool
     */
    function local_heyday_pretest_is_pretest_quiz($quiz): bool {
        global $DB;

        if (empty($quiz) || empty($quiz->id)) {
            return false;
        }

        $quizname = '';
        if (!empty($quiz->name)) {
            $quizname = core_text::strtolower(trim($quiz->name));
        }

        if ($quizname === 'pretest'
                || strpos($quizname, 'pretest') !== false
                || preg_match('/\bpre[\s\-_]*test\b/i', $quizname)) {
            return true;
        }

        // Also check the course module ID number, if one is configured.
        $quizmoduleid = $DB->get_field('modules', 'id', ['name' => 'quiz'], IGNORE_MISSING);

        if ($quizmoduleid) {
            $cms = $DB->get_records('course_modules', [
                'module' => $quizmoduleid,
                'instance' => $quiz->id,
            ], '', 'id, idnumber');

            foreach ($cms as $cm) {
                if (empty($cm->idnumber)) {
                    continue;
                }

                $idnumber = core_text::strtolower(trim($cm->idnumber));

                if ($idnumber === 'pretest'
                        || strpos($idnumber, 'pretest') !== false
                        || preg_match('/\bpre[\s\-_]*test\b/i', $idnumber)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('local_heyday_pretest_core_cm_url')) {
    function local_heyday_pretest_core_cm_url($cm): moodle_url {
        if (!empty($cm->url)) {
            return $cm->url;
        }

        return new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
    }
}

if (!function_exists('local_heyday_pretest_core_display_type')) {
    function local_heyday_pretest_core_display_type($cm): string {
        if (in_array($cm->modname, ['page', 'book', 'lesson', 'resource', 'url'], true)) {
            return 'activity';
        }

        if ($cm->modname === 'quiz') {
            return 'quiz';
        }

        if ($cm->modname === 'forum') {
            return 'forum';
        }

        return $cm->modname;
    }
}

if (!function_exists('local_heyday_pretest_core_find_l1_learning_objectives')) {
    function local_heyday_pretest_core_find_l1_learning_objectives($course, int $currentcmid = 0) {
        $modinfo = get_fast_modinfo($course);

        // Best method: exact Moodle activity ID number.
        foreach ($modinfo->cms as $candidate) {
            if ((int)$candidate->id === $currentcmid) {
                continue;
            }

            if (!$candidate->uservisible) {
                continue;
            }

            $idnumber = '';
            if (!empty($candidate->idnumber)) {
                $idnumber = strtoupper(trim($candidate->idnumber));
            }

            if ($idnumber === 'L1_LEARNING_OBJECTIVES') {
                return $candidate;
            }
        }

        // Safety fallback: tolerate a visually/copy truncated ID number.
        foreach ($modinfo->cms as $candidate) {
            if ((int)$candidate->id === $currentcmid) {
                continue;
            }

            if (!$candidate->uservisible) {
                continue;
            }

            $idnumber = '';
            if (!empty($candidate->idnumber)) {
                $idnumber = strtoupper(trim($candidate->idnumber));
            }

            if (strpos($idnumber, 'L1_LEARNING_OBJECTIV') === 0) {
                return $candidate;
            }
        }

        // Final fallback: search inside the Lesson 1 section only.
        $inside_lesson_1 = false;

        foreach ($modinfo->sections as $sectionnum => $sectioncms) {
            $sectionname = trim(get_section_name($course, $sectionnum));

            if (preg_match('/\blesson\s*1\b/i', $sectionname)) {
                $inside_lesson_1 = true;
            }

            if ($inside_lesson_1 && preg_match('/\blesson\s*2\b/i', $sectionname)) {
                break;
            }

            if (!$inside_lesson_1) {
                continue;
            }

            foreach ($sectioncms as $candidateid) {
                if (empty($modinfo->cms[$candidateid])) {
                    continue;
                }

                $candidate = $modinfo->cms[$candidateid];

                if ((int)$candidate->id === $currentcmid) {
                    continue;
                }

                if (!$candidate->uservisible) {
                    continue;
                }

                if (in_array($candidate->modname, ['label', 'subsection'], true)) {
                    continue;
                }

                $name = strtolower(format_string($candidate->name));

                if ($name === 'learning objectives' || strpos($name, 'learning objectives') !== false) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}

/**
 * Inject custom learner Pretest shell into Moodle core quiz attempt/review/summary pages.
 */
function local_heyday_pretest_before_footer() {
    global $PAGE, $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');

    $allowedpages = [
        'mod-quiz-attempt',
        'mod-quiz-review',
        'mod-quiz-summary',
    ];

    if (!in_array($PAGE->pagetype, $allowedpages, true)) {
        return;
    }

    $cmid = optional_param('cmid', 0, PARAM_INT);
    $attemptid = optional_param('attempt', 0, PARAM_INT);

    $cm = null;
    $quiz = null;
    $course = null;

    if ($attemptid) {
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);

        if ($attempt) {
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', IGNORE_MISSING);

            if ($quiz) {
                $course = get_course($quiz->course);
                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, IGNORE_MISSING);
            }
        }
    }

    if (!$cm && $cmid) {
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);

        if ($cm) {
            $course = get_course($cm->course);
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
        }
    }

    if (!$cm || !$quiz || !$course) {
        return;
    }

    // Only apply this design to Pretest quiz activities.
    $combinedname = strtolower($cm->name . ' ' . $quiz->name);

    if (strpos($combinedname, 'pretest') === false) {
        return;
    }

    $coursefullname = format_string($course->fullname);
    $quizname = 'Pretest';
    $viewurl = (new moodle_url('/local/heyday_pretest/view.php', ['cmid' => $cm->id]))->out(false);

    $nextcm = local_heyday_pretest_core_find_l1_learning_objectives($course, (int)$cm->id);

    if ($nextcm) {
        $nexturl = local_heyday_pretest_core_cm_url($nextcm)->out(false);
        $nextname = format_string($nextcm->name);
        $nexttype = local_heyday_pretest_core_display_type($nextcm);
        $nextsection = 'Lesson 1';
    } else {
        $nexturl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $nextname = 'Learning Objectives';
        $nexttype = 'activity';
        $nextsection = 'Lesson 1';
    }

    $coursejson = json_encode($coursefullname, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $quizjson = json_encode($quizname, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $viewurljson = json_encode($viewurl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nexturljson = json_encode($nexturl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nextnamejson = json_encode($nextname, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nexttypejson = json_encode($nexttype, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nextsectionjson = json_encode($nextsection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    echo <<<HTML
<style>
body.path-mod-quiz.heyday-pretest-core-page {
    background: #f4f6f8 !important;
}

body.heyday-pretest-core-page #page-header,
body.heyday-pretest-core-page .page-header-headings,
body.heyday-pretest-core-page .page-context-header,
body.heyday-pretest-core-page #page-navbar,
body.heyday-pretest-core-page .breadcrumb,
body.heyday-pretest-core-page .breadcrumb-nav,
body.heyday-pretest-core-page .secondary-navigation,
body.heyday-pretest-core-page .activity-header,
body.heyday-pretest-core-page .activity-navigation,
body.heyday-pretest-core-page .moodle-activity-navigation,
body.heyday-pretest-core-page .prevnext,
body.heyday-pretest-core-page .activityprev,
body.heyday-pretest-core-page .activitynext,
body.heyday-pretest-core-page .activity-nav,
body.heyday-pretest-core-page .navguide,
body.heyday-pretest-core-page .urlselect,
body.heyday-pretest-core-page .jumpmenu,
body.heyday-pretest-core-page select[name="jump"],
body.heyday-pretest-core-page #page-footer,
body.heyday-pretest-core-page footer,
body.heyday-pretest-core-page .homelink,
body.heyday-pretest-core-page .helplink,
body.heyday-pretest-core-page .footer-popover,
body.heyday-pretest-core-page .footer-content-popover,
body.heyday-pretest-core-page .tertiary-navigation,
body.heyday-pretest-core-page .quizattemptcounts,
body.heyday-pretest-core-page .quizattemptsummary,
body.heyday-pretest-core-page .quizreviewsummary,
body.heyday-pretest-core-page .mod_quiz-prev-nav {
    display: none !important;
}

body.heyday-pretest-core-page .questionflag,
body.heyday-pretest-core-page .editquestion,
body.heyday-pretest-core-page .commentlink,
body.heyday-pretest-core-page .history,
body.heyday-pretest-core-page .grade,
body.heyday-pretest-core-page .state,
body.heyday-pretest-core-page .outcome {
    display: none !important;
}

body.heyday-pretest-core-page #region-main {
    max-width: 920px !important;
    margin: 0 auto 90px auto !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

.hd-core-pretest-card {
    background: #fff;
    border: 1px solid #d6dce2;
    min-height: 420px;
    padding: 34px 32px 30px 32px;
    margin: 26px auto 40px auto;
}

.hd-core-pretest-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.hd-core-pretest-left,
.hd-core-pretest-right {
    display: flex;
    gap: 16px;
    align-items: center;
}

.hd-core-icon {
    border: 0;
    background: transparent;
    color: #0074ad;
    font-size: 22px;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    text-decoration: none;
}

.hd-core-icon:hover {
    color: #004f76;
    text-decoration: none;
}

.hd-core-pretest-course {
    text-align: center;
    font-size: 13px;
    color: #666;
    margin-top: -14px;
    margin-bottom: 6px;
}

.hd-core-pretest-title {
    text-align: center;
    font-size: 26px;
    font-weight: 400;
    margin: 0 0 24px 0;
    color: #111;
}

.hd-core-print-wrap {
    position: relative;
}

.hd-core-print-menu {
    display: none;
    position: absolute;
    top: 28px;
    right: 0;
    z-index: 9999;
    width: 190px;
    background: #fff;
    border: 1px solid #ddd;
    box-shadow: 0 4px 14px rgba(0,0,0,.12);
}

.hd-core-print-menu.is-open {
    display: block;
}

.hd-core-print-menu button {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    background: #fff;
    text-align: left;
    font-size: 13px;
    cursor: pointer;
}

.hd-core-print-menu button:hover {
    background: #f2f2f2;
}

body.heyday-pretest-core-page .hd-core-instructions-toggle,
body.heyday-pretest-core-page a[href*="show"],
body.heyday-pretest-core-page .quizinfo a,
body.heyday-pretest-core-page .collapsibleregioncaption {
    color: #0074ad !important;
    font-size: 13px !important;
    text-decoration: underline !important;
}

body.heyday-pretest-core-page form#responseform {
    margin-top: 10px !important;
}

body.heyday-pretest-core-page .que {
    position: relative !important;
    margin: 0 !important;
    padding: 26px 0 28px 0 !important;
    border: 0 !important;
    border-top: 1px dashed #cfd6dc !important;
    background: transparent !important;
    box-shadow: none !important;
}

body.heyday-pretest-core-page .que:first-of-type {
    border-top: 0 !important;
}

body.heyday-pretest-core-page .que .info {
    float: none !important;
    width: 46px !important;
    position: absolute !important;
    left: -18px !important;
    top: 24px !important;
    background: transparent !important;
    border: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

body.heyday-pretest-core-page .que .info .no {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 34px !important;
    height: 34px !important;
    border-radius: 0 14px 14px 0 !important;
    background: #6f7c85 !important;
    color: #fff !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    padding: 0 8px !important;
}

body.heyday-pretest-core-page .que.correct .info .no {
    background: #3f8b2b !important;
}

body.heyday-pretest-core-page .que.incorrect .info .no,
body.heyday-pretest-core-page .que.partiallycorrect .info .no {
    background: #b73434 !important;
}

body.heyday-pretest-core-page .que .content {
    margin-left: 54px !important;
    padding: 0 !important;
}

body.heyday-pretest-core-page .que .formulation {
    background: transparent !important;
    border: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

body.heyday-pretest-core-page .qtext {
    font-size: 15px !important;
    line-height: 1.45 !important;
    color: #222 !important;
    margin-bottom: 14px !important;
}

body.heyday-pretest-core-page .answer {
    margin-top: 8px !important;
}

body.heyday-pretest-core-page .answer > div,
body.heyday-pretest-core-page .answer .r0,
body.heyday-pretest-core-page .answer .r1 {
    min-height: 38px !important;
    display: flex !important;
    align-items: center !important;
    margin: 9px 0 !important;
    padding: 0 !important;
    background: #f5f5f5 !important;
    border: 0 !important;
    border-radius: 0 !important;
}

body.heyday-pretest-core-page .answer label {
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
    min-height: 38px !important;
    margin: 0 !important;
    padding: 0 12px !important;
    color: #006fae !important;
    font-size: 14px !important;
    line-height: 1.35 !important;
}

body.heyday-pretest-core-page .answer input[type="radio"],
body.heyday-pretest-core-page .answer input[type="checkbox"] {
    margin: 0 8px 0 12px !important;
    transform: scale(1.05);
}

body.heyday-pretest-core-page .answer .answernumber {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 36px !important;
    height: 38px !important;
    background: #d7dde1 !important;
    color: #333 !important;
    margin-right: 10px !important;
    font-weight: 400 !important;
}

body.heyday-pretest-core-page .que.correct .answer .correct,
body.heyday-pretest-core-page .que.correct .answer .correct label {
    background: #3f8b2b !important;
    color: #fff !important;
}

body.heyday-pretest-core-page .que.incorrect .answer .incorrect,
body.heyday-pretest-core-page .que.incorrect .answer .incorrect label,
body.heyday-pretest-core-page .que.partiallycorrect .answer .incorrect,
body.heyday-pretest-core-page .que.partiallycorrect .answer .incorrect label {
    background: #b73434 !important;
    color: #fff !important;
}

body.heyday-pretest-core-page .que .feedback,
body.heyday-pretest-core-page .que .rightanswer {
    margin: 8px 0 0 54px !important;
    padding: 10px 14px !important;
    border-radius: 3px !important;
    background: #cfe6f3 !important;
    color: #1f4f69 !important;
    border: 1px solid #b8d8e8 !important;
}

body.heyday-pretest-core-page .submitbtns,
body.heyday-pretest-core-page .quizattemptsummary .submitbtns {
    display: flex !important;
    justify-content: flex-end !important;
    gap: 10px !important;
    margin-top: 28px !important;
}

body.heyday-pretest-core-page input[type="submit"],
body.heyday-pretest-core-page button,
body.heyday-pretest-core-page .btn {
    border-radius: 3px !important;
    font-size: 13px !important;
}

body.heyday-pretest-core-page input[type="submit"][name="next"],
body.heyday-pretest-core-page input[value*="Submit"],
body.heyday-pretest-core-page button[name="submitbutton"] {
    background: #3f8b2b !important;
    border-color: #3f8b2b !important;
    color: #fff !important;
}

body.heyday-pretest-core-page .hd-save-close {
    background: #fff !important;
    border: 1px solid #333 !important;
    color: #222 !important;
    padding: 8px 14px !important;
    text-decoration: none !important;
}

.hd-core-end {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 34px auto 18px auto;
    color: #333;
    font-size: 14px;
}

.hd-core-end span {
    width: 70px;
    height: 1px;
    background: #9aa8b1;
}

.hd-core-next {
    display: flex;
    width: 260px;
    margin: 0 auto 40px auto;
    text-decoration: none !important;
    color: inherit !important;
}

.hd-core-next-label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 82px;
    background: #0077a6;
    color: #fff;
    font-weight: 700;
    min-height: 64px;
}

.hd-core-next-content {
    flex: 1;
    border: 1px solid #d7d7d7;
    border-left: 0;
    background: #fff;
    padding: 10px 12px;
    font-size: 12px;
}

.hd-core-next-section {
    display: block;
    color: #777;
}

.hd-core-next-title {
    display: block;
    color: #006fae;
    text-decoration: underline;
    font-size: 13px;
}

.hd-core-next-type {
    display: block;
    color: #444;
}

@media print {
    body.heyday-pretest-core-page .drawer,
    body.heyday-pretest-core-page .drawer-left,
    body.heyday-pretest-core-page .drawer-right,
    body.heyday-pretest-core-page .hd-core-pretest-topbar,
    body.heyday-pretest-core-page .hd-core-next,
    body.heyday-pretest-core-page .hd-core-end {
        display: none !important;
    }

    body.heyday-pretest-core-page #region-main {
        max-width: 100% !important;
        margin: 0 !important;
    }

    .hd-core-pretest-card {
        border: 0 !important;
        padding: 0 !important;
    }
}
</style>

<script>
(function() {
    document.body.classList.add('heyday-pretest-core-page');

    var courseName = {$coursejson};
    var quizName = {$quizjson};
    var viewUrl = {$viewurljson};
    var nextUrl = {$nexturljson};
    var nextName = {$nextnamejson};
    var nextType = {$nexttypejson};
    var nextSection = {$nextsectionjson};

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function hideMoodleFurniture() {
        var selectors = [
            '#page-header',
            '.page-header-headings',
            '.page-context-header',
            '#page-navbar',
            '.breadcrumb',
            '.breadcrumb-nav',
            '.secondary-navigation',
            '.activity-header',
            '.activity-navigation',
            '.moodle-activity-navigation',
            '.prevnext',
            '.activityprev',
            '.activitynext',
            '.activity-nav',
            '.navguide',
            '.urlselect',
            '.jumpmenu',
            'select[name="jump"]',
            '#page-footer',
            'footer',
            '.homelink',
            '.helplink',
            '.footer-popover',
            '.footer-content-popover'
        ];

        selectors.forEach(function(selector) {
            document.querySelectorAll(selector).forEach(function(el) {
                el.style.display = 'none';
            });
        });
    }

    function buildTopShell() {
        var main = document.querySelector('#region-main');

        if (!main || main.querySelector('.hd-core-pretest-card')) {
            return;
        }

        var card = document.createElement('div');
        card.className = 'hd-core-pretest-card';

        var topbar = document.createElement('div');
        topbar.className = 'hd-core-pretest-topbar';

        topbar.innerHTML =
            '<div class="hd-core-pretest-left">' +
                '<a class="hd-core-icon" href="javascript:history.back();" aria-label="Back">' +
                    '<i class="fa fa-arrow-left" aria-hidden="true"></i>' +
                '</a>' +
                '<button type="button" class="hd-core-icon" id="hdCoreBookmark" aria-label="Bookmark">' +
                    '<i class="fa fa-bookmark-o" aria-hidden="true"></i>' +
                '</button>' +
            '</div>' +
            '<div class="hd-core-pretest-right">' +
                '<div class="hd-core-print-wrap">' +
                    '<button type="button" class="hd-core-icon" id="hdCorePrint" aria-label="Print">' +
                        '<i class="fa fa-print" aria-hidden="true"></i>' +
                    '</button>' +
                    '<div class="hd-core-print-menu" id="hdCorePrintMenu">' +
                        '<button type="button" id="hdCorePrintActivity">Print/Save activity</button>' +
                        '<button type="button" id="hdCorePrintLesson">Print/Save entire lesson</button>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="hd-core-icon" id="hdCoreFullscreen" aria-label="Fullscreen">' +
                    '<i class="fa fa-expand" aria-hidden="true"></i>' +
                '</button>' +
            '</div>';

        var course = document.createElement('div');
        course.className = 'hd-core-pretest-course';
        course.textContent = courseName;

        var title = document.createElement('h1');
        title.className = 'hd-core-pretest-title';
        title.textContent = quizName;

        card.appendChild(topbar);
        card.appendChild(course);
        card.appendChild(title);

        while (main.firstChild) {
            card.appendChild(main.firstChild);
        }

        main.appendChild(card);
    }

    function cleanDefaultQuizText() {
        var main = document.querySelector('#region-main');

        if (!main) {
            return;
        }

        main.querySelectorAll('h1, h2, h3').forEach(function(el) {
            var text = (el.textContent || '').trim().toLowerCase();

            if (text === 'pretest' && !el.classList.contains('hd-core-pretest-title')) {
                el.style.display = 'none';
            }
        });

        document.querySelectorAll('a, button, .collapsibleregioncaption').forEach(function(el) {
            var text = (el.textContent || '').trim().toLowerCase();

            if (text.indexOf('show') !== -1 && text.indexOf('instruction') !== -1) {
                el.innerHTML = '<i class="fa fa-info-circle" aria-hidden="true"></i> Show Instructions';
                el.classList.add('hd-core-instructions-toggle');
            }
        });

        document.querySelectorAll('.questionflag, .editquestion').forEach(function(el) {
            el.style.display = 'none';
        });
    }

    function improveButtons() {
        var form = document.querySelector('form#responseform');

        if (!form) {
            return;
        }

        var submitArea = form.querySelector('.submitbtns');

        if (!submitArea) {
            submitArea = document.createElement('div');
            submitArea.className = 'submitbtns';
            form.appendChild(submitArea);
        }

        if (!submitArea.querySelector('.hd-save-close')) {
            var saveClose = document.createElement('a');
            saveClose.href = viewUrl;
            saveClose.className = 'hd-save-close';
            saveClose.textContent = 'Save and Close';
            submitArea.insertBefore(saveClose, submitArea.firstChild);
        }

        document.querySelectorAll('input[type="submit"], button[type="submit"]').forEach(function(btn) {
            var val = btn.value || btn.textContent || '';

            if (val.toLowerCase().indexOf('finish') !== -1 || val.toLowerCase().indexOf('submit') !== -1) {
                if (btn.value) {
                    btn.value = 'Submit Answers';
                } else {
                    btn.textContent = 'Submit Answers';
                }
            }
        });
    }

    function addEndAndNextUp() {
        var main = document.querySelector('#region-main');

        if (!main || main.querySelector('.hd-core-end')) {
            return;
        }

        var end = document.createElement('div');
        end.className = 'hd-core-end';
        end.innerHTML = '<span></span><strong>End of Pretest</strong><span></span>';

        var next = document.createElement('a');
        next.className = 'hd-core-next';
        next.href = nextUrl;
        next.innerHTML =
            '<span class="hd-core-next-label">Next Up</span>' +
            '<span class="hd-core-next-content">' +
                '<span class="hd-core-next-section">' + nextSection + '</span>' +
                '<span class="hd-core-next-title">' + nextName + '</span>' +
                '<span class="hd-core-next-type">' + nextType + '</span>' +
            '</span>';

        main.appendChild(end);
        main.appendChild(next);
    }

    function wireButtons() {
        var bookmark = document.getElementById('hdCoreBookmark');
        var print = document.getElementById('hdCorePrint');
        var printMenu = document.getElementById('hdCorePrintMenu');
        var printActivity = document.getElementById('hdCorePrintActivity');
        var printLesson = document.getElementById('hdCorePrintLesson');
        var fullscreen = document.getElementById('hdCoreFullscreen');

        if (bookmark && !bookmark.dataset.wired) {
            bookmark.dataset.wired = '1';

            bookmark.addEventListener('click', function() {
                var icon = bookmark.querySelector('i');

                if (icon) {
                    icon.classList.toggle('fa-bookmark-o');
                    icon.classList.toggle('fa-bookmark');
                }
            });
        }

        if (print && printMenu && !print.dataset.wired) {
            print.dataset.wired = '1';

            print.addEventListener('click', function(e) {
                e.stopPropagation();
                printMenu.classList.toggle('is-open');
            });

            document.addEventListener('click', function() {
                printMenu.classList.remove('is-open');
            });

            printMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        if (printActivity && !printActivity.dataset.wired) {
            printActivity.dataset.wired = '1';
            printActivity.addEventListener('click', function() {
                window.print();
            });
        }

        if (printLesson && !printLesson.dataset.wired) {
            printLesson.dataset.wired = '1';
            printLesson.addEventListener('click', function() {
                window.print();
            });
        }

        if (fullscreen && !fullscreen.dataset.wired) {
            fullscreen.dataset.wired = '1';

            fullscreen.addEventListener('click', function() {
                var target = document.querySelector('#region-main') || document.documentElement;

                if (!document.fullscreenElement) {
                    if (target.requestFullscreen) {
                        target.requestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            });
        }
    }

    function initHeydayPretestAttempt() {
        hideMoodleFurniture();
        buildTopShell();
        cleanDefaultQuizText();
        improveButtons();
        addEndAndNextUp();
        wireButtons();
    }

    ready(initHeydayPretestAttempt);
    setTimeout(initHeydayPretestAttempt, 300);
    setTimeout(initHeydayPretestAttempt, 1000);
})();
</script>
HTML;
}