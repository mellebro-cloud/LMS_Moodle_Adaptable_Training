<?php
// Local plugin: Heyday Quiz Skin.
// Skins selected Moodle quiz attempt/review/summary pages without redirecting away from Moodle core quiz engine.

defined('MOODLE_INTERNAL') || die();

/**
 * Get current quiz context from cmid or attempt id.
 *
 * @return array|null [$cm, $quiz, $course]
 */
function local_heyday_quizskin_get_context(): ?array {
    global $DB;

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
        return null;
    }

    return [$cm, $quiz, $course];
}

/**
 * Apply only to selected Pretest quiz pages.
 */
function local_heyday_quizskin_is_target(): bool {
    global $PAGE;

    $allowedpages = [
        'mod-quiz-attempt',
        'mod-quiz-review',
        'mod-quiz-summary',
    ];

    if (!in_array($PAGE->pagetype, $allowedpages, true)) {
        return false;
    }

    $ctx = local_heyday_quizskin_get_context();

    if (!$ctx) {
        return false;
    }

    [$cm, $quiz, $course] = $ctx;

    $idnumber = '';
    if (!empty($cm->idnumber)) {
        $idnumber = strtoupper(trim($cm->idnumber));
    }

    if ($idnumber === 'HEYDAY_PRETEST') {
        return true;
    }

    // Lesson quizzes (HEYDAY_LESSON<N>_QUIZ) are handled by local_heyday_quiz — skip them here.
    if (preg_match('/^HEYDAY_LESSON\d+_QUIZ$/i', $idnumber)) {
        return false;
    }

    $combined = strtolower($cm->name . ' ' . $quiz->name);

    return strpos($combined, 'pretest') !== false;
}

/**
 * Find Lesson 1 Learning Objectives for the Next Up card.
 */
function local_heyday_quizskin_find_l1_learning_objectives($course, int $currentcmid = 0) {
    $modinfo = get_fast_modinfo($course);

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

        if ($idnumber === 'L1_LEARNING_OBJECTIVES' || strpos($idnumber, 'L1_LEARNING_OBJECTIV') === 0) {
            return $candidate;
        }
    }

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

/**
 * Build module URL.
 */
function local_heyday_quizskin_cm_url($cm): moodle_url {
    if (!empty($cm->url)) {
        return $cm->url;
    }

    return new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
}

/**
 * Display type for Next Up card.
 */
function local_heyday_quizskin_display_type($cm): string {
    if (in_array($cm->modname, ['page', 'book', 'lesson', 'resource', 'url'], true)) {
        return 'activity';
    }

    if ($cm->modname === 'forum') {
        return 'forum';
    }

    if ($cm->modname === 'quiz') {
        return 'quiz';
    }

    return $cm->modname;
}

/**
 * Inject CSS early.
 * Moodle expects this callback to return HTML.
 */
function local_heyday_quizskin_before_standard_html_head(): string {
    if (!local_heyday_quizskin_is_target()) {
        return '';
    }

    return <<<'HTML'
<style id="heyday-quizskin-css">
/* =========================================================
   HEYDAY QUIZ SKIN - Ed2Go-style Pretest attempt/review
   Page and text size corrected
   ========================================================= */

body.heyday-quizskin-page {
    background: #f4f6f8 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    color: #111 !important;
}

/* Hide Moodle original quiz/course chrome that conflicts with learner view. */
body.heyday-quizskin-page #page-header,
body.heyday-quizskin-page .page-header-headings,
body.heyday-quizskin-page .page-context-header,
body.heyday-quizskin-page #page-navbar,
body.heyday-quizskin-page .breadcrumb,
body.heyday-quizskin-page .breadcrumb-nav,
body.heyday-quizskin-page .secondary-navigation,
body.heyday-quizskin-page .tertiary-navigation,
body.heyday-quizskin-page .activity-header,
body.heyday-quizskin-page .activity-navigation,
body.heyday-quizskin-page .moodle-activity-navigation,
body.heyday-quizskin-page .prevnext,
body.heyday-quizskin-page .activityprev,
body.heyday-quizskin-page .activitynext,
body.heyday-quizskin-page .activity-nav,
body.heyday-quizskin-page .navguide,
body.heyday-quizskin-page .urlselect,
body.heyday-quizskin-page .jumpmenu,
body.heyday-quizskin-page select[name="jump"],
body.heyday-quizskin-page .quizattemptcounts,
body.heyday-quizskin-page .quizattemptsummary,
body.heyday-quizskin-page .quizreviewsummary,
body.heyday-quizskin-page .mod_quiz-prev-nav,
body.heyday-quizskin-page .continuebutton {
    display: none !important;
}

/* Hide teacher/admin preview clutter. */
body.heyday-quizskin-page .questionflag,
body.heyday-quizskin-page .editquestion,
body.heyday-quizskin-page .history,
body.heyday-quizskin-page .state,
body.heyday-quizskin-page .grade,
body.heyday-quizskin-page .outcome,
body.heyday-quizskin-page .version,
body.heyday-quizskin-page .questionversion,
body.heyday-quizskin-page .questionversionnumber,
body.heyday-quizskin-page .question-version,
body.heyday-quizskin-page .badge-dark,
body.heyday-quizskin-page .badge-secondary,
body.heyday-quizskin-page .que .info small,
body.heyday-quizskin-page .que .info a,
body.heyday-quizskin-page .que .info .badge,
body.heyday-quizskin-page .que .info [class*="version"],
body.heyday-quizskin-page .que .info .questionflag,
body.heyday-quizskin-page .que .info .editquestion {
    display: none !important;
}

/* Main page width - corrected wider page. */
body.heyday-quizskin-page #region-main {
    width: 1130px !important;
    max-width: 1130px !important;
    margin: 0 auto 90px auto !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

/* White quiz card - corrected wider card. */
.hdq-card {
    width: 1090px !important;
    max-width: 1090px !important;
    min-height: 520px !important;
    margin: 26px auto 38px auto !important;
    padding: 34px 32px 30px 32px !important;
    background: #fff !important;
    border: 1px solid #d7dce0 !important;
    box-shadow: none !important;
}

/* Top icons. */
.hdq-topbar {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 8px !important;
}

.hdq-left,
.hdq-right {
    display: flex !important;
    align-items: center !important;
    gap: 18px !important;
}

.hdq-icon {
    border: 0 !important;
    background: transparent !important;
    color: #0073a8 !important;
    font-size: 23px !important;
    line-height: 1 !important;
    padding: 0 !important;
    cursor: pointer !important;
    text-decoration: none !important;
}

.hdq-icon:hover,
.hdq-icon:focus {
    color: #004f76 !important;
    outline: none !important;
}

/* Print dropdown. */
.hdq-print-wrap {
    position: relative !important;
}

.hdq-print-menu {
    display: none !important;
    position: absolute !important;
    top: 28px !important;
    right: 0 !important;
    width: 190px !important;
    z-index: 9999 !important;
    background: #fff !important;
    border: 1px solid #ddd !important;
    box-shadow: 0 4px 14px rgba(0,0,0,.14) !important;
}

.hdq-print-menu.is-open {
    display: block !important;
}

.hdq-print-menu button {
    display: block !important;
    width: 100% !important;
    padding: 10px 12px !important;
    border: 0 !important;
    background: #fff !important;
    text-align: left !important;
    color: #222 !important;
    font-size: 13px !important;
    cursor: pointer !important;
}

.hdq-print-menu button:hover {
    background: #f2f2f2 !important;
}

/* Course title and Pretest title - corrected size. */
.hdq-course-title {
    text-align: center !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    color: #66727c !important;
    margin: -10px 0 8px 0 !important;
    line-height: 1.35 !important;
}

.hdq-title {
    text-align: center !important;
    font-size: 29px !important;
    font-weight: 400 !important;
    color: #111 !important;
    margin: 0 0 30px 0 !important;
    line-height: 1.25 !important;
}

/* Show Instructions link - corrected size. */
.hdq-instructions-toggle {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    min-height: 24px !important;
    margin: 0 0 22px 0 !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    color: #0073a8 !important;
    font-size: 15px !important;
    line-height: 1.25 !important;
    cursor: pointer !important;
    text-decoration: underline !important;
}

.hdq-instructions-toggle:hover,
.hdq-instructions-toggle:focus {
    color: #005d8c !important;
    outline: none !important;
}

.hdq-instructions-panel {
    display: block !important;
    margin: 0 0 24px 0 !important;
    font-size: 16px !important;
    line-height: 1.5 !important;
    color: #111 !important;
}

.hdq-instructions-panel.is-hidden {
    display: none !important;
}

.hdq-rules {
    margin-top: 18px !important;
    padding: 14px 18px !important;
    border: 1px solid #d7dce0 !important;
    border-radius: 4px !important;
    background: #fff !important;
}

.hdq-rules ul {
    margin-bottom: 0 !important;
}

/* Quiz form width - corrected wider question area. */
body.heyday-quizskin-page form#responseform,
body.heyday-quizskin-page .quizattempt,
body.heyday-quizskin-page .quizreview {
    max-width: 1000px !important;
    width: 1000px !important;
    margin: 0 auto !important;
}

/* Remove duplicate original Moodle headings. */
body.heyday-quizskin-page #region-main > h1,
body.heyday-quizskin-page #region-main > h2,
body.heyday-quizskin-page #region-main > h3,
body.heyday-quizskin-page .hdq-card > h1:not(.hdq-title),
body.heyday-quizskin-page .hdq-card > h2:not(.hdq-title),
body.heyday-quizskin-page .hdq-card > h3:not(.hdq-title) {
    display: none !important;
}

/* Question block - corrected spacing. */
body.heyday-quizskin-page .que {
    position: relative !important;
    margin: 0 !important;
    padding: 28px 0 30px 0 !important;
    border: 0 !important;
    border-top: 1px dashed #c8d0d6 !important;
    background: transparent !important;
    box-shadow: none !important;
}

body.heyday-quizskin-page .que:first-of-type {
    border-top: 0 !important;
}

/* Question number badge - corrected size. */
body.heyday-quizskin-page .que .info {
    position: absolute !important;
    left: -30px !important;
    top: 28px !important;
    width: 46px !important;
    margin: 0 !important;
    padding: 0 !important;
    float: none !important;
    background: transparent !important;
    border: 0 !important;
}

body.heyday-quizskin-page .que .info .no {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px !important;
    min-width: 38px !important;
    height: 36px !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 0 17px 17px 0 !important;
    background: #6d7a83 !important;
    color: #fff !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    line-height: 36px !important;
}

/* Question content alignment. */
body.heyday-quizskin-page .que .content {
    margin-left: 58px !important;
    padding: 0 !important;
}

body.heyday-quizskin-page .que .formulation {
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

/* Question text - corrected readable size. */
body.heyday-quizskin-page .qtext {
    margin: 0 0 16px 0 !important;
    padding: 0 !important;
    color: #111 !important;
    font-size: 16px !important;
    font-weight: 400 !important;
    line-height: 1.48 !important;
}

/* Answer list. */
body.heyday-quizskin-page .answer {
    margin: 10px 0 0 0 !important;
    padding: 0 !important;
}

/* Each answer row - corrected height and width. */
body.heyday-quizskin-page .answer .r0,
body.heyday-quizskin-page .answer .r1,
body.heyday-quizskin-page .answer > div {
    position: relative !important;
    display: block !important;
    min-height: 42px !important;
    margin: 9px 0 !important;
    padding: 0 !important;
    background: #f5f5f5 !important;
    border: 1px solid transparent !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    overflow: visible !important;
    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease !important;
}

/* Ed2Go hover effect. */
body.heyday-quizskin-page .answer .r0:hover,
body.heyday-quizskin-page .answer .r1:hover,
body.heyday-quizskin-page .answer > div:hover,
body.heyday-quizskin-page .answer .r0:focus-within,
body.heyday-quizskin-page .answer .r1:focus-within,
body.heyday-quizskin-page .answer > div:focus-within {
    background: #f7f7f7 !important;
    border-color: #b8d8e8 !important;
    box-shadow: 0 0 8px rgba(0, 116, 173, 0.28) !important;
    cursor: pointer !important;
}

/* Radio/check input position. */
body.heyday-quizskin-page .answer input[type="radio"],
body.heyday-quizskin-page .answer input[type="checkbox"] {
    position: absolute !important;
    left: 15px !important;
    top: 50% !important;
    transform: translateY(-50%) scale(1.15) !important;
    z-index: 5 !important;
    margin: 0 !important;
    width: 15px !important;
    height: 15px !important;
    cursor: pointer !important;
}

/* A/B/C/D capsule - corrected size. */
body.heyday-quizskin-page .answer .answernumber {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 64px !important;
    min-width: 64px !important;
    height: 42px !important;
    padding-left: 28px !important;
    margin: 0 !important;
    background: #d8dde0 !important;
    color: #333 !important;
    border-radius: 21px 0 0 21px !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    text-transform: uppercase !important;
    line-height: 42px !important;
}

body.heyday-quizskin-page .answer .r0:hover .answernumber,
body.heyday-quizskin-page .answer .r1:hover .answernumber,
body.heyday-quizskin-page .answer > div:hover .answernumber {
    background: #d8dde0 !important;
    color: #333 !important;
}

/* Moodle 5 answer label wrapper. */
body.heyday-quizskin-page .answer [data-region="answer-label"],
body.heyday-quizskin-page .answer .d-flex.w-auto,
body.heyday-quizskin-page .answer .d-flex {
    display: block !important;
    width: 100% !important;
    min-height: 42px !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
}

/* Answer text - corrected readable size. */
body.heyday-quizskin-page .answer label,
body.heyday-quizskin-page .answer .flex-fill,
body.heyday-quizskin-page .answer .flex-fill p,
body.heyday-quizskin-page .answer .ml-1,
body.heyday-quizskin-page .answer p {
    display: block !important;
    margin: 0 !important;
    color: #006fae !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    line-height: 1.42 !important;
    background: transparent !important;
    border: 0 !important;
}

body.heyday-quizskin-page .answer label {
    width: 100% !important;
    min-height: 42px !important;
    padding: 11px 14px 10px 80px !important;
    cursor: pointer !important;
}

body.heyday-quizskin-page .answer .flex-fill {
    padding: 11px 14px 10px 80px !important;
}

body.heyday-quizskin-page .answer .r0:hover label,
body.heyday-quizskin-page .answer .r1:hover label,
body.heyday-quizskin-page .answer > div:hover label,
body.heyday-quizskin-page .answer .r0:hover .flex-fill,
body.heyday-quizskin-page .answer .r1:hover .flex-fill,
body.heyday-quizskin-page .answer > div:hover .flex-fill,
body.heyday-quizskin-page .answer .r0:hover p,
body.heyday-quizskin-page .answer .r1:hover p,
body.heyday-quizskin-page .answer > div:hover p {
    color: #006fae !important;
    background: transparent !important;
}

/* Clean Moodle answer highlights during live attempt. */
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .correct,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .incorrect,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .partiallycorrect,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .notanswered,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer span.correct,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer span.incorrect,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer label.correct,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer label.incorrect,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .flex-fill,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer label span,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer div[class*="correct"],
body#page-mod-quiz-attempt.heyday-quizskin-page .answer div[class*="incorrect"] {
    background: transparent !important;
    color: #006fae !important;
    font-weight: 400 !important;
}

/* Keep live attempt rows grey. */
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .r0,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer .r1,
body#page-mod-quiz-attempt.heyday-quizskin-page .answer > div {
    background: #f5f5f5 !important;
}

/* Hide feedback during live attempt. */
body#page-mod-quiz-attempt.heyday-quizskin-page .que .feedback,
body#page-mod-quiz-attempt.heyday-quizskin-page .que .rightanswer,
body#page-mod-quiz-attempt.heyday-quizskin-page .que .specificfeedback,
body#page-mod-quiz-attempt.heyday-quizskin-page .que .generalfeedback {
    display: none !important;
}

/* Review page colors. */
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .info .no {
    background: #3f8b2b !important;
}

body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .info .no,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .info .no {
    background: #b73434 !important;
}

/* Correct selected answer in review. */
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .answer .correct,
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .answer .correct label,
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .answer .correct .flex-fill {
    background: #3f8b2b !important;
    color: #fff !important;
}

/* Wrong selected answer in review. */
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .incorrect,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .incorrect label,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .incorrect .flex-fill,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .incorrect,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .incorrect label,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .incorrect .flex-fill {
    background: #b73434 !important;
    color: #fff !important;
}

/* Correct answer shown in blue when learner selected wrong answer. */
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .correct,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .correct label,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .answer .correct .flex-fill,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .correct,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .correct label,
body#page-mod-quiz-review.heyday-quizskin-page .que.partiallycorrect .answer .correct .flex-fill {
    background: #087aa1 !important;
    color: #fff !important;
}

/* Feedback boxes. */
body.heyday-quizskin-page .que .feedback,
body.heyday-quizskin-page .que .rightanswer,
body.heyday-quizskin-page .que .specificfeedback,
body.heyday-quizskin-page .que .generalfeedback {
    margin: 8px 0 0 58px !important;
    padding: 10px 14px !important;
    border-radius: 3px !important;
    background: #cfe6f3 !important;
    border: 1px solid #b8d8e8 !important;
    color: #1f4f69 !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
}

/* Correct feedback. */
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .feedback,
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .specificfeedback,
body#page-mod-quiz-review.heyday-quizskin-page .que.correct .generalfeedback {
    background: #dfeeda !important;
    border-color: #c9dfc1 !important;
    color: #2d6422 !important;
}

/* Incorrect feedback. */
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .feedback,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .specificfeedback,
body#page-mod-quiz-review.heyday-quizskin-page .que.incorrect .generalfeedback {
    background: #f1d6d6 !important;
    border-color: #e6bcbc !important;
    border-left: 4px solid #b73434 !important;
    color: #7b1e1e !important;
}

/* Review: un-hide .outcome block so feedback text is visible. */
body#page-mod-quiz-review.heyday-quizskin-page .que .outcome {
    display: block !important;
}

body#page-mod-quiz-review.heyday-quizskin-page .que .outcome .grade,
body#page-mod-quiz-review.heyday-quizskin-page .que .outcome .rightanswer {
    display: none !important;
}

/* X / check icons inside A B C D capsule. */
.hdq-ans-x,
.hdq-ans-check {
    margin-left: 3px !important;
    font-size: 11px !important;
    vertical-align: middle !important;
}

/* "Incorrect." bold line at top of red feedback box. */
.hdq-incorrect-prefix {
    display: block !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    margin-bottom: 6px !important;
}

/* "This was the correct answer." bar under correct answer row. */
.hdq-correct-note {
    margin: 0 0 8px 0 !important;
    padding: 9px 14px !important;
    background: #087aa1 !important;
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 0 0 3px 3px !important;
}

.hdq-correct-note .fa {
    margin-right: 5px !important;
}

/* Submit buttons - right aligned together. */
body.heyday-quizskin-page form#responseform .submitbtns,
body.heyday-quizskin-page .submitbtns {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    width: 100% !important;
    max-width: 1000px !important;
    margin: 28px auto 0 auto !important;
    padding: 0 !important;
    gap: 10px !important;
    text-align: right !important;
    float: none !important;
    clear: both !important;
}

body.heyday-quizskin-page .hdq-button-row {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    width: 100% !important;
    gap: 10px !important;
    margin: 0 !important;
    padding: 0 !important;
    float: none !important;
    text-align: right !important;
}

body.heyday-quizskin-page .submitbtns input,
body.heyday-quizskin-page .submitbtns button,
body.heyday-quizskin-page .submitbtns a,
body.heyday-quizskin-page .hdq-button-row input,
body.heyday-quizskin-page .hdq-button-row button,
body.heyday-quizskin-page .hdq-button-row a {
    float: none !important;
    margin: 0 !important;
    position: static !important;
}

.hdq-save-close {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 36px !important;
    padding: 8px 15px !important;
    background: #fff !important;
    border: 1px solid #333 !important;
    border-radius: 3px !important;
    color: #222 !important;
    font-size: 14px !important;
    font-weight: 400 !important;
    line-height: 1.2 !important;
    text-decoration: none !important;
    cursor: pointer !important;
}

.hdq-save-close:hover,
.hdq-save-close:focus {
    background: #f5f5f5 !important;
    color: #222 !important;
    text-decoration: none !important;
}

body.heyday-quizskin-page .hdq-submit-answer,
body.heyday-quizskin-page input[type="submit"].hdq-submit-answer,
body.heyday-quizskin-page button.hdq-submit-answer {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 36px !important;
    padding: 8px 15px !important;
    background: #3f8b2b !important;
    border: 1px solid #3f8b2b !important;
    border-radius: 3px !important;
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    text-decoration: none !important;
    cursor: pointer !important;
}

body.heyday-quizskin-page .hdq-submit-answer:hover,
body.heyday-quizskin-page input[type="submit"].hdq-submit-answer:hover,
body.heyday-quizskin-page button.hdq-submit-answer:hover {
    background: #367825 !important;
    border-color: #367825 !important;
    color: #fff !important;
}

/* End of Pretest. */
.hdq-end {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
    margin: 38px auto 18px auto !important;
    color: #333 !important;
    font-size: 14px !important;
}

.hdq-end span {
    width: 72px !important;
    height: 1px !important;
    background: #9aa8b1 !important;
}

/* Next Up card. */
.hdq-next {
    display: flex !important;
    width: 330px !important;
    margin: 0 auto 40px auto !important;
    text-decoration: none !important;
    color: inherit !important;
}

.hdq-next-label {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 88px !important;
    min-height: 68px !important;
    background: #0077a6 !important;
    color: #fff !important;
    font-weight: 700 !important;
    font-size: 15px !important;
}

.hdq-next-content {
    flex: 1 !important;
    min-height: 68px !important;
    padding: 11px 13px !important;
    border: 1px solid #d7d7d7 !important;
    border-left: 0 !important;
    background: #fff !important;
    font-size: 13px !important;
}

.hdq-next-section {
    display: block !important;
    color: #777 !important;
    font-size: 12px !important;
}

.hdq-next-title {
    display: block !important;
    color: #006fae !important;
    text-decoration: underline !important;
    font-size: 14px !important;
    line-height: 1.25 !important;
}

.hdq-next-type {
    display: block !important;
    color: #444 !important;
    font-size: 12px !important;
}

/* Remove Moodle default bottom navigation if it appears. */
body.heyday-quizskin-page .activity-navigation,
body.heyday-quizskin-page .moodle-activity-navigation,
body.heyday-quizskin-page .nav_guide,
body.heyday-quizskin-page .submitbtns + .activity-navigation {
    display: none !important;
}

/* Responsive correction. */
@media (max-width: 1300px) {
    body.heyday-quizskin-page #region-main {
        width: 1000px !important;
        max-width: 1000px !important;
    }

    .hdq-card {
        width: 960px !important;
        max-width: 960px !important;
    }

    body.heyday-quizskin-page form#responseform,
    body.heyday-quizskin-page .quizattempt,
    body.heyday-quizskin-page .quizreview {
        width: 880px !important;
        max-width: 880px !important;
    }
}

/* Summary page — hide content, show submitting overlay. */
body#page-mod-quiz-summary.heyday-quizskin-page #region-main {
    visibility: hidden !important;
    pointer-events: none !important;
}

body#page-mod-quiz-summary.heyday-quizskin-page::after {
    content: 'Submitting\2026' !important;
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    font-size: 20px !important;
    color: #555 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    letter-spacing: 0.03em !important;
}

/* Review score badge — ed2go style above questions. */
.hdq-score-bar {
    text-align: center !important;
    padding: 20px 0 22px 0 !important;
    border-bottom: 1px solid #e0e6ea !important;
    margin: -4px 0 26px 0 !important;
}

.hdq-score-label {
    font-size: 13px !important;
    color: #66727c !important;
    text-transform: uppercase !important;
    letter-spacing: 0.06em !important;
    margin: 0 0 6px 0 !important;
}

.hdq-score-pct {
    font-size: 54px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    color: #3f8b2b !important;
    margin: 0 !important;
}

.hdq-score-pct.hdq-score-fail {
    color: #b73434 !important;
}

.hdq-score-detail {
    font-size: 15px !important;
    color: #555 !important;
    margin: 8px 0 0 0 !important;
}

.hdq-score-status {
    display: inline-block !important;
    margin-top: 10px !important;
    padding: 3px 16px !important;
    border-radius: 20px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
}

.hdq-score-status.hdq-pass {
    background: #dfeeda !important;
    color: #2d6422 !important;
}

.hdq-score-status.hdq-fail {
    background: #f1d6d6 !important;
    color: #7b1e1e !important;
}

/* Print mode. */
@media print {
    body.heyday-quizskin-page .drawer,
    body.heyday-quizskin-page .drawer-left,
    body.heyday-quizskin-page .drawer-right,
    body.heyday-quizskin-page .hdq-topbar,
    body.heyday-quizskin-page .hdq-next,
    body.heyday-quizskin-page .hdq-end {
        display: none !important;
    }

    body.heyday-quizskin-page #region-main,
    .hdq-card {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        border: 0 !important;
        padding: 0 !important;
    }
}
</style>
HTML;
}

/**
 * Inject JavaScript after Moodle has rendered the quiz.
 * Moodle expects this callback to return HTML.
 */
function local_heyday_quizskin_before_footer(): string {
    global $CFG;

    if (!local_heyday_quizskin_is_target()) {
        return '';
    }

    $ctx = local_heyday_quizskin_get_context();

    if (!$ctx) {
        return '';
    }

    [$cm, $quiz, $course] = $ctx;

    require_once($CFG->dirroot . '/course/lib.php');

    $coursefullname = format_string($course->fullname);
    $quiztitle      = format_string($quiz->name);
    $viewurl        = (new moodle_url('/local/heyday_courseplayer/index.php', [
        'id' => $course->id, 'page' => 'pretest', 'cmid' => $cm->id,
    ]))->out(false);

    $introhtml = trim(format_module_intro('quiz', $quiz, $cm->id, false));

    if ($introhtml === '') {
        $introhtml =
            '<p>This pretest is optional, and it is meant to help you gauge how much you already know about the subject matter of this course.</p>' .
            '<p>As you go through the pretest, you will be able to save your answer choices and change them up until you submit your pretest for a score. To exit the pretest, click the <strong>Save and Close</strong> button at the bottom of the page. To submit the pretest, click the <strong>Submit Answers</strong> button at the bottom of the page.</p>' .
            '<div class="hdq-rules"><ul><li>You have one attempt.<ul><li>Your grade is determined by your only attempt.</li><li>This is not for credit and does not affect your overall grade.</li></ul></li></ul></div>';
    }

    $nextcm = local_heyday_quizskin_find_l1_learning_objectives($course, (int)$cm->id);

    if ($nextcm) {
        $nexturl  = (new moodle_url('/local/heyday_courseplayer/index.php', [
            'id'   => $course->id,
            'page' => 'lesson',
            'cmid' => $nextcm->id,
        ]))->out(false);
        $nextname = format_string($nextcm->name);
        $nexttype = local_heyday_quizskin_display_type($nextcm);
    } else {
        $nexturl  = (new moodle_url('/local/heyday_courseplayer/index.php', [
            'id'   => $course->id,
            'page' => 'home',
        ]))->out(false);
        $nextname = 'Learning Objectives';
        $nexttype = 'activity';
    }

    $data = [
        'course'      => $coursefullname,
        'quiz'        => $quiztitle,
        'viewurl'     => $viewurl,
        'introhtml'   => $introhtml,
        'quizendtext' => 'End of Pretest',
        'nexturl'     => $nexturl,
        'nextsection' => 'Lesson 1',
        'nextname'    => $nextname,
        'nexttype'    => $nexttype,
    ];

    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    return <<<HTML
<script id="heyday-quizskin-js">
(function() {
    var HDQ = {$json};

    document.body.classList.add('heyday-quizskin-page');

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function buildShell() {
        var main = document.querySelector('#region-main');

        if (!main || main.querySelector('.hdq-card')) {
            return;
        }

        var card = document.createElement('section');
        card.className = 'hdq-card';

        card.innerHTML =
            '<div class="hdq-topbar">' +
                '<div class="hdq-left">' +
                    '<a class="hdq-icon" href="javascript:history.back();" aria-label="Back"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>' +
                    '<button type="button" class="hdq-icon" id="hdqBookmark" aria-label="Bookmark"><i class="fa fa-bookmark-o" aria-hidden="true"></i></button>' +
                '</div>' +
                '<div class="hdq-right">' +
                    '<div class="hdq-print-wrap">' +
                        '<button type="button" class="hdq-icon" id="hdqPrint" aria-label="Print"><i class="fa fa-print" aria-hidden="true"></i></button>' +
                        '<div class="hdq-print-menu" id="hdqPrintMenu">' +
                            '<button type="button" id="hdqPrintActivity">Print/Save activity</button>' +
                            '<button type="button" id="hdqPrintLesson">Print/Save entire lesson</button>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="hdq-icon" id="hdqFullscreen" aria-label="Fullscreen"><i class="fa fa-expand" aria-hidden="true"></i></button>' +
                '</div>' +
            '</div>' +
            '<div class="hdq-course-title"></div>' +
            '<h1 class="hdq-title"></h1>' +
            '<button type="button" class="hdq-instructions-toggle" id="hdqInstructionsToggle" aria-expanded="false"><i class="fa fa-info-circle" aria-hidden="true"></i> <span>Show Instructions</span></button>' +
            '<div class="hdq-instructions-panel is-hidden" id="hdqInstructionsPanel"></div>';

        card.querySelector('.hdq-course-title').textContent = HDQ.course;
        card.querySelector('.hdq-title').textContent = HDQ.quiz;
        card.querySelector('#hdqInstructionsPanel').innerHTML = HDQ.introhtml;

        while (main.firstChild) {
            card.appendChild(main.firstChild);
        }

        main.appendChild(card);
    }

    function cleanQuiz() {
        var main = document.querySelector('#region-main');

        if (!main) {
            return;
        }

        main.querySelectorAll('h1, h2, h3').forEach(function(el) {
            var text = (el.textContent || '').trim().toLowerCase();

            if (text === 'pretest' && !el.classList.contains('hdq-title')) {
                el.style.display = 'none';
            }
        });

        main.querySelectorAll(
            '.questionflag, .editquestion, .state, .grade, .history, .outcome, ' +
            '.info .badge, .info small, .info a, .info [class*="version"], ' +
            '.version, .questionversion, .questionversionnumber, .question-version, .badge-dark'
        ).forEach(function(el) {
            el.style.display = 'none';
        });

        main.querySelectorAll('.que .info .no').forEach(function(el) {
            var match = (el.textContent || '').match(/\d+/);

            if (match) {
                el.textContent = match[0];
                el.style.display = 'inline-flex';
            }
        });

        main.querySelectorAll('.answer .answernumber').forEach(function(el) {
            el.textContent = (el.textContent || '').replace('.', '').trim().toUpperCase();
        });

        main.querySelectorAll('.answer .r0, .answer .r1').forEach(function(row) {
            row.style.background = '#f5f5f5';
        });
    }

    function cleanLiveAttemptHighlights() {
        if (document.body.id !== 'page-mod-quiz-attempt') {
            return;
        }

        document.querySelectorAll(
            '.answer .correct, .answer .incorrect, .answer .partiallycorrect, .answer .notanswered, ' +
            '.answer span.correct, .answer span.incorrect, .answer label.correct, .answer label.incorrect'
        ).forEach(function(el) {
            el.style.background = 'transparent';
            el.style.color = '#006fae';
            el.style.fontWeight = 'normal';
        });

        document.querySelectorAll('.answer .r0, .answer .r1, .answer > div').forEach(function(row) {
            row.style.background = '#f5f5f5';
        });

        document.querySelectorAll('.answer .flex-fill, .answer .flex-fill p, .answer label, .answer label span').forEach(function(el) {
            el.style.background = 'transparent';
            el.style.color = '#006fae';
            el.style.fontWeight = 'normal';
        });

        document.querySelectorAll('.que .feedback, .que .rightanswer, .que .specificfeedback, .que .generalfeedback').forEach(function(el) {
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

        if (submitArea.dataset.hdqFixed === '1') {
            return;
        }

        submitArea.dataset.hdqFixed = '1';

        var oldSaveClose = submitArea.querySelector('.hdq-save-close');
        if (oldSaveClose) {
            oldSaveClose.remove();
        }

        var submitBtn = null;
        var candidates = form.querySelectorAll(
            '.submitbtns input[type="submit"], ' +
            '.submitbtns button[type="submit"], ' +
            'input[type="submit"][name="next"], ' +
            'button[type="submit"][name="next"]'
        );

        candidates.forEach(function(btn) {
            var text = btn.value || btn.textContent || '';
            var lower = text.toLowerCase();

            if (
                lower.indexOf('submit') !== -1 ||
                lower.indexOf('finish') !== -1 ||
                lower.indexOf('next') !== -1
            ) {
                submitBtn = btn;
            }
        });

        if (!submitBtn && candidates.length > 0) {
            submitBtn = candidates[candidates.length - 1];
        }

        if (!submitBtn) {
            return;
        }

        if (submitBtn.value !== undefined) {
            submitBtn.value = 'Submit Answers';
        } else {
            submitBtn.textContent = 'Submit Answers';
        }

        submitBtn.classList.add('hdq-submit-answer');

        /* On the attempt page: intercept click in capture phase so we fire
           before Moodle's AMD bubble handlers.  Add finishattempt=1 and call
           form.submit() — jumps directly to processattempt.php, skipping
           the summary/confirmation page entirely. */
        if (document.body.id === 'page-mod-quiz-attempt' && !submitBtn.dataset.hdqIntercept) {
            submitBtn.dataset.hdqIntercept = '1';
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var fi = form.querySelector('input[name="finishattempt"]');
                if (!fi) {
                    fi = document.createElement('input');
                    fi.type = 'hidden';
                    fi.name = 'finishattempt';
                    form.appendChild(fi);
                }
                fi.value = '1';
                form.submit();
            }, true);
        }

        var oldButtonRow = submitArea.querySelector('.hdq-button-row');
        if (oldButtonRow && !oldButtonRow.contains(submitBtn)) {
            oldButtonRow.remove();
        }

        var buttonRow = document.createElement('div');
        buttonRow.className = 'hdq-button-row';

        var saveClose = document.createElement('a');
        saveClose.href = HDQ.viewurl;
        saveClose.className = 'hdq-save-close';
        saveClose.textContent = 'Save and Close';

        buttonRow.appendChild(saveClose);
        buttonRow.appendChild(submitBtn);

        submitArea.appendChild(buttonRow);
    }

    function addNextUp() {
        var main = document.querySelector('#region-main');

        if (!main || main.querySelector('.hdq-end')) {
            return;
        }

        var end = document.createElement('div');
        end.className = 'hdq-end';
        end.innerHTML = '<span></span><strong>' + (HDQ.quizendtext || 'End of Quiz') + '</strong><span></span>';

        var next = document.createElement('a');
        next.className = 'hdq-next';
        next.href = HDQ.nexturl;
        next.innerHTML =
            '<span class="hdq-next-label">Next Up</span>' +
            '<span class="hdq-next-content">' +
                '<span class="hdq-next-section"></span>' +
                '<span class="hdq-next-title"></span>' +
                '<span class="hdq-next-type"></span>' +
            '</span>';

        next.querySelector('.hdq-next-section').textContent = HDQ.nextsection;
        next.querySelector('.hdq-next-title').textContent = HDQ.nextname;
        next.querySelector('.hdq-next-type').textContent = HDQ.nexttype;

        main.appendChild(end);
        main.appendChild(next);
    }

    function wireControls() {
        var instructionsToggle = document.getElementById('hdqInstructionsToggle');
        var instructionsPanel = document.getElementById('hdqInstructionsPanel');

        if (instructionsToggle && instructionsPanel && !instructionsToggle.dataset.wired) {
            instructionsToggle.dataset.wired = '1';

            instructionsToggle.addEventListener('click', function() {
                var isHidden = instructionsPanel.classList.toggle('is-hidden');
                instructionsToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');

                var label = instructionsToggle.querySelector('span');

                if (label) {
                    label.textContent = isHidden ? 'Show Instructions' : 'Hide Instructions';
                }
            });
        }

        var bookmark = document.getElementById('hdqBookmark');

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

        var print = document.getElementById('hdqPrint');
        var printMenu = document.getElementById('hdqPrintMenu');

        if (print && printMenu && !print.dataset.wired) {
            print.dataset.wired = '1';

            print.addEventListener('click', function(e) {
                e.stopPropagation();
                printMenu.classList.toggle('is-open');
            });

            document.addEventListener('click', function() {
                printMenu.classList.remove('is-open');
            });
        }

        var printActivity = document.getElementById('hdqPrintActivity');
        var printLesson = document.getElementById('hdqPrintLesson');

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

        var fullscreen = document.getElementById('hdqFullscreen');

        if (fullscreen && !fullscreen.dataset.wired) {
            fullscreen.dataset.wired = '1';

            fullscreen.addEventListener('click', function() {
                var target = document.querySelector('#region-main') || document.documentElement;

                if (!document.fullscreenElement) {
                    if (target.requestFullscreen) {
                        target.requestFullscreen();
                    }
                } else if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            });
        }
    }

    function bypassSummary() {
        if (document.body.id !== 'page-mod-quiz-summary') {
            return;
        }

        /* Use form.submit() — bypasses Moodle's JS confirmation modal.
           btn.click() triggers AMD handlers that open a dialog and stall. */
        var form =
            document.querySelector('form[action*="processattempt"]') ||
            document.querySelector('form');

        if (!form) { return; }

        /* Ensure finishattempt=1 reaches the server — hidden inputs survive
           form.submit() but submit button values do not. */
        var fi = form.querySelector('input[name="finishattempt"]');
        if (!fi) {
            fi = document.createElement('input');
            fi.type = 'hidden';
            fi.name = 'finishattempt';
            form.appendChild(fi);
        }
        fi.value = '1';

        form.submit();
    }

    function showScore() {
        if (document.body.id !== 'page-mod-quiz-review') {
            return;
        }

        var card = document.querySelector('.hdq-card');
        if (!card || card.querySelector('.hdq-score-bar')) {
            return;
        }

        var summaryEl = document.querySelector('.quizreviewsummary');
        var rawText = summaryEl ? (summaryEl.textContent || '') : '';

        var pct = null;
        var detail = '';

        var pctMatch = rawText.match(/\((\d+(?:\.\d+)?)%\)/);
        var scoreMatch = rawText.match(/([\d.]+)\s*(?:out of|\/)\s*([\d.]+)/i);
        var marksMatch = rawText.match(/Marks\s*:?\s*([\d.]+)\s*\/\s*([\d.]+)/i);

        if (pctMatch) {
            pct = Math.round(parseFloat(pctMatch[1]));
        }

        if (scoreMatch) {
            if (pct === null) {
                var sc = parseFloat(scoreMatch[1]);
                var tot = parseFloat(scoreMatch[2]);
                if (tot > 0) { pct = Math.round(sc / tot * 100); }
            }
            detail = scoreMatch[1] + ' out of ' + scoreMatch[2] + ' correct';
        }

        if (marksMatch && pct === null) {
            var sc2 = parseFloat(marksMatch[1]);
            var tot2 = parseFloat(marksMatch[2]);
            if (tot2 > 0) { pct = Math.round(sc2 / tot2 * 100); detail = marksMatch[1] + ' / ' + marksMatch[2] + ' marks'; }
        }

        if (pct === null) {
            return;
        }

        var isPass = pct >= 70;
        var bar = document.createElement('div');
        bar.className = 'hdq-score-bar';
        bar.innerHTML =
            '<div class="hdq-score-label">Your Score</div>' +
            '<div class="hdq-score-pct' + (isPass ? '' : ' hdq-score-fail') + '">' + pct + '%</div>' +
            (detail ? '<div class="hdq-score-detail">' + detail + '</div>' : '') +
            '<span class="hdq-score-status ' + (isPass ? 'hdq-pass">Passed' : 'hdq-fail">Did not pass') + '</span>';

        var insertPoint = card.querySelector('.hdq-instructions-toggle') ||
                          card.querySelector('.hdq-title');

        if (insertPoint && insertPoint.parentNode === card) {
            insertPoint.parentNode.insertBefore(bar, insertPoint.nextSibling);
        } else {
            card.appendChild(bar);
        }
    }

    /* ── Annotate review: X on wrong, check + note on correct ───── */
    function annotateReview() {
        if (document.body.id !== 'page-mod-quiz-review') { return; }

        document.querySelectorAll('.que.incorrect, .que.partiallycorrect').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqReviewed) { return; }
            content.dataset.hdqReviewed = '1';

            /* ✕ on the wrong selected answer capsule */
            var wrongRow = que.querySelector('.answer .incorrect');
            if (wrongRow) {
                var num = wrongRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdq-ans-x')) {
                    var xEl = document.createElement('i');
                    xEl.className = 'fa fa-times hdq-ans-x';
                    xEl.setAttribute('aria-hidden', 'true');
                    num.appendChild(xEl);
                }
            }

            /* ✓ on correct answer capsule + "This was the correct answer." bar */
            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                var num2 = correctRow.querySelector('.answernumber');
                if (num2 && !num2.querySelector('.hdq-ans-check')) {
                    var chk = document.createElement('i');
                    chk.className = 'fa fa-check hdq-ans-check';
                    chk.setAttribute('aria-hidden', 'true');
                    num2.appendChild(chk);
                }
                if (!que.querySelector('.hdq-correct-note')) {
                    var note = document.createElement('div');
                    note.className = 'hdq-correct-note';
                    note.innerHTML = '<i class="fa fa-check-circle" aria-hidden="true"></i> This was the correct answer.';
                    correctRow.insertAdjacentElement('afterend', note);
                }
            }

            /* "Incorrect." prefix inside the red feedback box */
            var feedbackEl = que.querySelector(
                '.outcome .feedback, .outcome .specificfeedback, .outcome .generalfeedback, ' +
                '.feedback, .specificfeedback, .generalfeedback'
            );
            if (feedbackEl && !feedbackEl.querySelector('.hdq-incorrect-prefix')) {
                var pfx = document.createElement('span');
                pfx.className = 'hdq-incorrect-prefix';
                pfx.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                feedbackEl.insertBefore(pfx, feedbackEl.firstChild);
            } else if (!feedbackEl) {
                var formulation = que.querySelector('.formulation');
                if (formulation && !formulation.querySelector('.hdq-fallback-incorrect')) {
                    var fb = document.createElement('div');
                    fb.className = 'hdq-fallback-incorrect';
                    fb.style.cssText = 'margin:8px 0 0 58px;padding:10px 14px;background:#f1d6d6;border:1px solid #e6bcbc;border-left:4px solid #b73434;color:#7b1e1e;font-size:14px;border-radius:3px;font-weight:700;';
                    fb.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                    formulation.appendChild(fb);
                }
            }
        });

        /* Correct questions: ✓ on answer capsule only */
        document.querySelectorAll('.que.correct').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqReviewed) { return; }
            content.dataset.hdqReviewed = '1';

            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                var num = correctRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdq-ans-check')) {
                    var chk = document.createElement('i');
                    chk.className = 'fa fa-check hdq-ans-check';
                    chk.setAttribute('aria-hidden', 'true');
                    num.appendChild(chk);
                }
            }
        });
    }

    function init() {
        buildShell();
        cleanQuiz();
        cleanLiveAttemptHighlights();
        improveButtons();
        addNextUp();
        wireControls();
        bypassSummary();
        showScore();
        annotateReview();
    }

    ready(init);
    setTimeout(init, 300);
    setTimeout(init, 1000);
    setTimeout(init, 2000);
})();
</script>
HTML;
}