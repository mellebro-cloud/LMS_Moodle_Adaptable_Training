<?php
// This file is part of Moodle - http://moodle.org/
// local_heyday_quiz: ed2go-style skin for Moodle lesson quiz attempt/review/summary pages.

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve the current quiz context from the page URL parameters.
 *
 * @return array|null [$cm, $quiz, $course] or null when not a quiz page.
 */
function local_heyday_quiz_get_context(): ?array {
    global $DB;

    $cmid      = optional_param('cmid', 0, PARAM_INT);
    $attemptid = optional_param('attempt', 0, PARAM_INT);

    $cm     = null;
    $quiz   = null;
    $course = null;

    if ($attemptid > 0) {
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if ($attempt) {
            $quiz   = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', IGNORE_MISSING);
            if ($quiz) {
                $course = get_course($quiz->course);
                $cm     = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, IGNORE_MISSING);
            }
        }
    }

    if (!$cm && $cmid > 0) {
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $course = get_course($cm->course);
            $quiz   = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
        }
    }

    // Also try id param (native Moodle quiz view URL uses ?id=CMID).
    if (!$cm) {
        $id = optional_param('id', 0, PARAM_INT);
        if ($id > 0) {
            $cm = get_coursemodule_from_id('quiz', $id, 0, false, IGNORE_MISSING);
            if ($cm) {
                $course = get_course($cm->course);
                $quiz   = $DB->get_record('quiz', ['id' => $cm->instance], '*', IGNORE_MISSING);
            }
        }
    }

    if (!$cm || !$quiz || !$course) {
        return null;
    }

    return [$cm, $quiz, $course];
}

/**
 * Return true only when the current page is a lesson quiz attempt/review/summary
 * that should receive the HeyDay skin.
 *
 * Targeting rules (first match wins):
 *  1. CM idnumber starts with HEYDAY_QUIZ (explicit opt-in).
 *  2. CM idnumber is HEYDAY_PRETEST or quiz name contains "pretest" → skip (handled by heyday_quizskin).
 *  3. Quiz name contains "final exam" → skip (handled by courseplayer finalexam view).
 *  4. Quiz is inside a section named "Lesson N …" → skin it.
 *  5. Quiz name contains "quiz" or "learning check" → skin it as a fallback.
 *
 * @return bool
 */
function local_heyday_quiz_is_target(): bool {
    global $PAGE;

    $allowedpagetypes = ['mod-quiz-attempt', 'mod-quiz-review', 'mod-quiz-summary'];
    if (!in_array($PAGE->pagetype, $allowedpagetypes, true)) {
        return false;
    }

    $ctx = local_heyday_quiz_get_context();
    if (!$ctx) {
        return false;
    }

    [$cm, $quiz, $course] = $ctx;

    $idnumber = strtoupper(trim((string)($cm->idnumber ?? '')));
    $quizname = strtolower(trim(format_string($quiz->name)));

    // Explicit opt-in: HEYDAY_LESSON<N>_QUIZ (lesson quiz) or HEYDAY_QUIZ* (generic).
    if (preg_match('/^HEYDAY_LESSON\d+_QUIZ$/i', $idnumber)) {
        return true;
    }

    if (str_starts_with($idnumber, 'HEYDAY_QUIZ')) {
        return true;
    }

    // Pretest: handled exclusively by local_heyday_quizskin — skip here.
    if ($idnumber === 'HEYDAY_PRETEST' || str_contains($quizname, 'pretest')) {
        return false;
    }

    // Final exam: handled by courseplayer finalexam view — skip here.
    if (str_contains($quizname, 'final exam') || str_contains($quizname, 'final-exam')) {
        return false;
    }

    // Section-name check: any quiz inside a "Lesson N …" section.
    try {
        $modinfo  = get_fast_modinfo($course);
        $cmobject = $modinfo->get_cm((int)$cm->id);
        $sectionnum = (int)$cmobject->sectionnum;
        $sectionname = strtolower(trim((string)get_section_name($course, $sectionnum)));

        if (preg_match('/\blesson\s*\d+/i', $sectionname)) {
            return true;
        }
    } catch (Throwable $e) {
        // Fall through to name-based fallback.
    }

    // Name-based fallback: "quiz" or "learning check" in the quiz name.
    return str_contains($quizname, 'quiz') || str_contains($quizname, 'learning check');
}

/**
 * Find the next available activity after $currentcmid in course sequence.
 *
 * Skips labels and subsection containers. Returns the cm_info object or null.
 *
 * @param stdClass $course  Course record.
 * @param int      $currentcmid  Current course module id.
 * @return cm_info|null
 */
function local_heyday_quiz_find_next_cm(stdClass $course, int $currentcmid): ?object {
    try {
        $modinfo  = get_fast_modinfo($course);
        $allcmids = [];

        // Flatten sections in section order.
        $sections = $modinfo->get_section_info_all();
        ksort($sections);
        foreach ($sections as $section) {
            $snum = (int)$section->section;
            foreach (($modinfo->sections[$snum] ?? []) as $cmid) {
                $allcmids[] = $cmid;
            }
        }

        $found = false;
        foreach ($allcmids as $cmid) {
            try {
                $candidate = $modinfo->get_cm($cmid);
            } catch (Throwable $e) {
                continue;
            }

            if (!$candidate->uservisible) {
                continue;
            }

            if (in_array($candidate->modname, ['label', 'subsection'], true)) {
                continue;
            }

            if ($found) {
                return $candidate;
            }

            if ((int)$candidate->id === $currentcmid) {
                $found = true;
            }
        }
    } catch (Throwable $e) {
        // Ignore.
    }

    return null;
}

/**
 * Return display type label for Next Up card.
 *
 * @param object $cm  cm_info or stdClass with modname.
 * @return string
 */
function local_heyday_quiz_display_type(object $cm): string {
    $mod = (string)($cm->modname ?? '');
    if (in_array($mod, ['page', 'book', 'lesson', 'resource', 'url'], true)) {
        return 'activity';
    }
    if ($mod === 'quiz') {
        return 'quiz';
    }
    if ($mod === 'forum') {
        return 'discussion';
    }
    return $mod;
}

/**
 * Build the courseplayer URL to return to after "Save and Close".
 *
 * Returns the courseplayer lesson view for this cm when the courseplayer plugin
 * is installed, otherwise falls back to the native Moodle course view.
 *
 * @param stdClass $course  Course record.
 * @param int      $cmid    Course module id.
 * @return string  URL string (not escaped).
 */
function local_heyday_quiz_return_url(stdClass $course, int $cmid): string {
    global $CFG;

    // Primary: heyday_quiz standalone player — "Save and Close" returns to the same player page.
    if (is_file($CFG->dirroot . '/local/heyday_quiz/index.php')) {
        return (new moodle_url('/local/heyday_quiz/index.php', [
            'id'   => $course->id,
            'cmid' => $cmid,
        ]))->out(false);
    }

    // Fallback: heyday_courseplayer lessonquiz page.
    if (is_file($CFG->dirroot . '/local/heyday_courseplayer/index.php')) {
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $idnumber = strtoupper(trim((string)($cm->idnumber ?? '')));
            if (preg_match('/^HEYDAY_LESSON\d+_QUIZ$/i', $idnumber)) {
                return (new moodle_url('/local/heyday_courseplayer/index.php', [
                    'id'   => $course->id,
                    'page' => 'lessonquiz',
                    'cmid' => $cmid,
                ]))->out(false);
            }
        }
        return (new moodle_url('/local/heyday_courseplayer/index.php', [
            'id'   => $course->id,
            'page' => 'lesson',
            'cmid' => $cmid,
        ]))->out(false);
    }

    return (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
}

/**
 * Determine which lesson number the current section belongs to.
 *
 * @param stdClass $course Course record.
 * @param int      $cmid   Course module id.
 * @return string  E.g. "Lesson 1" or empty string.
 */
function local_heyday_quiz_lesson_label(stdClass $course, int $cmid): string {
    try {
        $modinfo    = get_fast_modinfo($course);
        $cmobject   = $modinfo->get_cm($cmid);
        $sectionnum = (int)$cmobject->sectionnum;
        $sectionname = (string)get_section_name($course, $sectionnum);

        if (preg_match('/lesson\s*(\d+)/i', $sectionname, $m)) {
            return 'Lesson ' . $m[1];
        }

        return format_string($sectionname);
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Inject CSS into <head> for the quiz skin.
 * Called by the before_standard_head_html_generation hook.
 *
 * @return string HTML <style> block or empty string.
 */
function local_heyday_quiz_before_standard_html_head(): string {
    if (!local_heyday_quiz_is_target()) {
        return '';
    }

    return <<<'HTML'
<style id="heyday-quiz-css">
/* =================================================================
   HEYDAY LESSON QUIZ SKIN  –  ed2go-style learning check
   Scoped to body.heyday-quiz-page
   ================================================================= */

body.heyday-quiz-page {
    background: #f4f6f8 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    color: #111 !important;
}

/* Hide Moodle chrome that conflicts with the learner card view. */
body.heyday-quiz-page #page-header,
body.heyday-quiz-page .page-header-headings,
body.heyday-quiz-page .page-context-header,
body.heyday-quiz-page #page-navbar,
body.heyday-quiz-page .breadcrumb,
body.heyday-quiz-page .breadcrumb-nav,
body.heyday-quiz-page .secondary-navigation,
body.heyday-quiz-page .tertiary-navigation,
body.heyday-quiz-page .activity-header,
body.heyday-quiz-page .activity-navigation,
body.heyday-quiz-page .moodle-activity-navigation,
body.heyday-quiz-page .prevnext,
body.heyday-quiz-page .activityprev,
body.heyday-quiz-page .activitynext,
body.heyday-quiz-page .activity-nav,
body.heyday-quiz-page .navguide,
body.heyday-quiz-page .urlselect,
body.heyday-quiz-page .jumpmenu,
body.heyday-quiz-page select[name="jump"],
body.heyday-quiz-page .quizattemptcounts,
body.heyday-quiz-page .quizattemptsummary,
body.heyday-quiz-page .quizreviewsummary,
body.heyday-quiz-page .mod_quiz-prev-nav,
body.heyday-quiz-page .continuebutton {
    display: none !important;
}

/* Hide teacher/admin debug clutter. */
body.heyday-quiz-page .questionflag,
body.heyday-quiz-page .editquestion,
body.heyday-quiz-page .history,
body.heyday-quiz-page .state,
body.heyday-quiz-page .grade,
body.heyday-quiz-page .outcome,
body.heyday-quiz-page .version,
body.heyday-quiz-page .questionversion,
body.heyday-quiz-page .questionversionnumber,
body.heyday-quiz-page .question-version,
body.heyday-quiz-page .badge-dark,
body.heyday-quiz-page .badge-secondary,
body.heyday-quiz-page .que .info small,
body.heyday-quiz-page .que .info a,
body.heyday-quiz-page .que .info .badge,
body.heyday-quiz-page .que .info [class*="version"],
body.heyday-quiz-page .que .info .questionflag,
body.heyday-quiz-page .que .info .editquestion {
    display: none !important;
}

/* ---- Main content area ---- */
body.heyday-quiz-page #region-main {
    width: 1130px !important;
    max-width: 1130px !important;
    margin: 0 auto 90px auto !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

/* ---- White quiz card ---- */
.hdqz-card {
    width: 1090px !important;
    max-width: 1090px !important;
    min-height: 520px !important;
    margin: 26px auto 38px auto !important;
    padding: 34px 32px 30px 32px !important;
    background: #fff !important;
    border: 1px solid #d7dce0 !important;
    box-shadow: none !important;
}

/* ---- Top icon bar ---- */
.hdqz-topbar {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 8px !important;
}

.hdqz-left,
.hdqz-right {
    display: flex !important;
    align-items: center !important;
    gap: 18px !important;
}

.hdqz-icon {
    border: 0 !important;
    background: transparent !important;
    color: #0073a8 !important;
    font-size: 23px !important;
    line-height: 1 !important;
    padding: 0 !important;
    cursor: pointer !important;
    text-decoration: none !important;
}

.hdqz-icon:hover,
.hdqz-icon:focus {
    color: #004f76 !important;
    outline: none !important;
}

/* Print dropdown. */
.hdqz-print-wrap { position: relative !important; }

.hdqz-print-menu {
    display: none !important;
    position: absolute !important;
    top: 28px !important;
    right: 0 !important;
    width: 200px !important;
    z-index: 9999 !important;
    background: #fff !important;
    border: 1px solid #ddd !important;
    box-shadow: 0 4px 14px rgba(0,0,0,.14) !important;
}

.hdqz-print-menu.is-open { display: block !important; }

.hdqz-print-menu button {
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

.hdqz-print-menu button:hover { background: #f2f2f2 !important; }

/* ---- Course + quiz titles ---- */
.hdqz-course-title {
    text-align: center !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    color: #66727c !important;
    margin: -10px 0 8px 0 !important;
    line-height: 1.35 !important;
}

.hdqz-title {
    text-align: center !important;
    font-size: 29px !important;
    font-weight: 400 !important;
    color: #111 !important;
    margin: 0 0 30px 0 !important;
    line-height: 1.25 !important;
}

/* ---- Show Instructions toggle ---- */
.hdqz-instructions-toggle {
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

.hdqz-instructions-toggle:hover,
.hdqz-instructions-toggle:focus {
    color: #005d8c !important;
    outline: none !important;
}

.hdqz-instructions-panel {
    display: block !important;
    margin: 0 0 24px 0 !important;
    font-size: 16px !important;
    line-height: 1.5 !important;
    color: #111 !important;
}

.hdqz-instructions-panel.is-hidden { display: none !important; }

/* ---- Quiz form width ---- */
body.heyday-quiz-page form#responseform,
body.heyday-quiz-page .quizattempt,
body.heyday-quiz-page .quizreview {
    max-width: 1000px !important;
    width: 1000px !important;
    margin: 0 auto !important;
}

/* Remove duplicate Moodle headings. */
body.heyday-quiz-page #region-main > h1,
body.heyday-quiz-page #region-main > h2,
body.heyday-quiz-page #region-main > h3,
body.heyday-quiz-page .hdqz-card > h1:not(.hdqz-title),
body.heyday-quiz-page .hdqz-card > h2:not(.hdqz-title),
body.heyday-quiz-page .hdqz-card > h3:not(.hdqz-title) {
    display: none !important;
}

/* ---- Question block ---- */
body.heyday-quiz-page .que {
    position: relative !important;
    margin: 0 !important;
    padding: 28px 0 30px 0 !important;
    border: 0 !important;
    border-top: 1px dashed #c8d0d6 !important;
    background: transparent !important;
    box-shadow: none !important;
}

body.heyday-quiz-page .que:first-of-type { border-top: 0 !important; }

/* Question number badge. */
body.heyday-quiz-page .que .info {
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

body.heyday-quiz-page .que .info .no {
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
body.heyday-quiz-page .que .content {
    margin-left: 58px !important;
    padding: 0 !important;
}

body.heyday-quiz-page .que .formulation {
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

/* Question text. */
body.heyday-quiz-page .qtext {
    margin: 0 0 16px 0 !important;
    padding: 0 !important;
    color: #111 !important;
    font-size: 16px !important;
    font-weight: 400 !important;
    line-height: 1.48 !important;
}

/* ---- Answer list ---- */
body.heyday-quiz-page .answer {
    margin: 10px 0 0 0 !important;
    padding: 0 !important;
}

/* Each answer row. */
body.heyday-quiz-page .answer .r0,
body.heyday-quiz-page .answer .r1,
body.heyday-quiz-page .answer > div {
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

/* Ed2go hover glow. */
body.heyday-quiz-page .answer .r0:hover,
body.heyday-quiz-page .answer .r1:hover,
body.heyday-quiz-page .answer > div:hover,
body.heyday-quiz-page .answer .r0:focus-within,
body.heyday-quiz-page .answer .r1:focus-within,
body.heyday-quiz-page .answer > div:focus-within {
    background: #f7f7f7 !important;
    border-color: #b8d8e8 !important;
    box-shadow: 0 0 8px rgba(0, 116, 173, 0.28) !important;
    cursor: pointer !important;
}

/* Radio / checkbox position. */
body.heyday-quiz-page .answer input[type="radio"],
body.heyday-quiz-page .answer input[type="checkbox"] {
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

/* A/B/C/D capsule. */
body.heyday-quiz-page .answer .answernumber {
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

/* Moodle 5 answer label wrapper. */
body.heyday-quiz-page .answer [data-region="answer-label"],
body.heyday-quiz-page .answer .d-flex.w-auto,
body.heyday-quiz-page .answer .d-flex {
    display: block !important;
    width: 100% !important;
    min-height: 42px !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
}

/* Answer text. */
body.heyday-quiz-page .answer label,
body.heyday-quiz-page .answer .flex-fill,
body.heyday-quiz-page .answer .flex-fill p,
body.heyday-quiz-page .answer .ml-1,
body.heyday-quiz-page .answer p {
    display: block !important;
    margin: 0 !important;
    color: #006fae !important;
    font-size: 15px !important;
    font-weight: 400 !important;
    line-height: 1.42 !important;
    background: transparent !important;
    border: 0 !important;
}

body.heyday-quiz-page .answer label {
    width: 100% !important;
    min-height: 42px !important;
    padding: 11px 14px 10px 80px !important;
    cursor: pointer !important;
}

body.heyday-quiz-page .answer .flex-fill { padding: 11px 14px 10px 80px !important; }

body.heyday-quiz-page .answer .r0:hover label,
body.heyday-quiz-page .answer .r1:hover label,
body.heyday-quiz-page .answer > div:hover label,
body.heyday-quiz-page .answer .r0:hover .flex-fill,
body.heyday-quiz-page .answer .r1:hover .flex-fill,
body.heyday-quiz-page .answer > div:hover .flex-fill,
body.heyday-quiz-page .answer .r0:hover p,
body.heyday-quiz-page .answer .r1:hover p,
body.heyday-quiz-page .answer > div:hover p {
    color: #006fae !important;
    background: transparent !important;
}

/* ---- Live-attempt: suppress premature Moodle highlighting ---- */
body#page-mod-quiz-attempt.heyday-quiz-page .answer .correct,
body#page-mod-quiz-attempt.heyday-quiz-page .answer .incorrect,
body#page-mod-quiz-attempt.heyday-quiz-page .answer .partiallycorrect,
body#page-mod-quiz-attempt.heyday-quiz-page .answer .notanswered,
body#page-mod-quiz-attempt.heyday-quiz-page .answer span.correct,
body#page-mod-quiz-attempt.heyday-quiz-page .answer span.incorrect,
body#page-mod-quiz-attempt.heyday-quiz-page .answer label.correct,
body#page-mod-quiz-attempt.heyday-quiz-page .answer label.incorrect,
body#page-mod-quiz-attempt.heyday-quiz-page .answer .flex-fill,
body#page-mod-quiz-attempt.heyday-quiz-page .answer label span,
body#page-mod-quiz-attempt.heyday-quiz-page .answer div[class*="correct"],
body#page-mod-quiz-attempt.heyday-quiz-page .answer div[class*="incorrect"] {
    background: transparent !important;
    color: #006fae !important;
    font-weight: 400 !important;
}

body#page-mod-quiz-attempt.heyday-quiz-page .answer .r0,
body#page-mod-quiz-attempt.heyday-quiz-page .answer .r1,
body#page-mod-quiz-attempt.heyday-quiz-page .answer > div {
    background: #f5f5f5 !important;
}

body#page-mod-quiz-attempt.heyday-quiz-page .que .feedback,
body#page-mod-quiz-attempt.heyday-quiz-page .que .rightanswer,
body#page-mod-quiz-attempt.heyday-quiz-page .que .specificfeedback,
body#page-mod-quiz-attempt.heyday-quiz-page .que .generalfeedback {
    display: none !important;
}

/* ---- Review page colour coding ---- */
body#page-mod-quiz-review.heyday-quiz-page .que.correct .info .no     { background: #3f8b2b !important; }
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .info .no,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .info .no { background: #b73434 !important; }

body#page-mod-quiz-review.heyday-quiz-page .que.correct .answer .correct,
body#page-mod-quiz-review.heyday-quiz-page .que.correct .answer .correct label,
body#page-mod-quiz-review.heyday-quiz-page .que.correct .answer .correct .flex-fill {
    background: #3f8b2b !important;
    color: #fff !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .incorrect,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .incorrect label,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .incorrect .flex-fill,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .incorrect,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .incorrect label,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .incorrect .flex-fill {
    background: #b73434 !important;
    color: #fff !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .correct,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .correct label,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .answer .correct .flex-fill,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .correct,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .correct label,
body#page-mod-quiz-review.heyday-quiz-page .que.partiallycorrect .answer .correct .flex-fill {
    background: #087aa1 !important;
    color: #fff !important;
}

/* Feedback boxes. */
body.heyday-quiz-page .que .feedback,
body.heyday-quiz-page .que .rightanswer,
body.heyday-quiz-page .que .specificfeedback,
body.heyday-quiz-page .que .generalfeedback {
    margin: 8px 0 0 58px !important;
    padding: 10px 14px !important;
    border-radius: 3px !important;
    background: #cfe6f3 !important;
    border: 1px solid #b8d8e8 !important;
    color: #1f4f69 !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que.correct .feedback,
body#page-mod-quiz-review.heyday-quiz-page .que.correct .specificfeedback,
body#page-mod-quiz-review.heyday-quiz-page .que.correct .generalfeedback {
    background: #dfeeda !important;
    border-color: #c9dfc1 !important;
    color: #2d6422 !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .feedback,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .specificfeedback,
body#page-mod-quiz-review.heyday-quiz-page .que.incorrect .generalfeedback {
    background: #f1d6d6 !important;
    border-color: #e6bcbc !important;
    border-left: 4px solid #b73434 !important;
    color: #7b1e1e !important;
}

/* ---- Review: un-hide .outcome so feedback text is visible ---- */
body#page-mod-quiz-review.heyday-quiz-page .que .outcome {
    display: block !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que .outcome .grade,
body#page-mod-quiz-review.heyday-quiz-page .que .outcome .rightanswer {
    display: none !important;
}

/* ---- Review: X / check icons inside A B C D capsule ---- */
.hdqz-ans-x,
.hdqz-ans-check {
    margin-left: 3px !important;
    font-size: 11px !important;
    vertical-align: middle !important;
}

/* ---- Review: "Incorrect." bold line at top of red feedback box ---- */
.hdqz-incorrect-prefix {
    display: block !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    margin-bottom: 6px !important;
}

/* ---- Review: "This was the correct answer." bar under correct row ---- */
.hdqz-correct-note {
    margin: 0 0 8px 0 !important;
    padding: 9px 14px !important;
    background: #087aa1 !important;
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 0 0 3px 3px !important;
}

.hdqz-correct-note .fa {
    margin-right: 5px !important;
}

/* ---- Submit button row ---- */
body.heyday-quiz-page form#responseform .submitbtns,
body.heyday-quiz-page .submitbtns {
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

body.heyday-quiz-page .hdqz-button-row {
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

body.heyday-quiz-page .submitbtns input,
body.heyday-quiz-page .submitbtns button,
body.heyday-quiz-page .submitbtns a,
body.heyday-quiz-page .hdqz-button-row input,
body.heyday-quiz-page .hdqz-button-row button,
body.heyday-quiz-page .hdqz-button-row a {
    float: none !important;
    margin: 0 !important;
    position: static !important;
}

.hdqz-save-close {
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

.hdqz-save-close:hover,
.hdqz-save-close:focus {
    background: #f5f5f5 !important;
    color: #222 !important;
    text-decoration: none !important;
}

body.heyday-quiz-page .hdqz-submit-answer,
body.heyday-quiz-page input[type="submit"].hdqz-submit-answer,
body.heyday-quiz-page button.hdqz-submit-answer {
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

body.heyday-quiz-page .hdqz-submit-answer:hover,
body.heyday-quiz-page input[type="submit"].hdqz-submit-answer:hover,
body.heyday-quiz-page button.hdqz-submit-answer:hover {
    background: #367825 !important;
    border-color: #367825 !important;
}

/* ---- End-of-quiz divider ---- */
.hdqz-end {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
    margin: 38px auto 18px auto !important;
    color: #333 !important;
    font-size: 14px !important;
}

.hdqz-end span {
    width: 72px !important;
    height: 1px !important;
    background: #9aa8b1 !important;
}

/* ---- Next Up card ---- */
.hdqz-next {
    display: flex !important;
    width: 330px !important;
    margin: 0 auto 40px auto !important;
    text-decoration: none !important;
    color: inherit !important;
}

.hdqz-next-label {
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

.hdqz-next-content {
    flex: 1 !important;
    min-height: 68px !important;
    padding: 11px 13px !important;
    border: 1px solid #d7d7d7 !important;
    border-left: 0 !important;
    background: #fff !important;
    font-size: 13px !important;
}

.hdqz-next-section { display: block !important; color: #777 !important; font-size: 12px !important; }
.hdqz-next-title   { display: block !important; color: #006fae !important; text-decoration: underline !important; font-size: 14px !important; line-height: 1.25 !important; }
.hdqz-next-type    { display: block !important; color: #444 !important; font-size: 12px !important; }

/* ---- Remove leftover Moodle navigation ---- */
body.heyday-quiz-page .activity-navigation,
body.heyday-quiz-page .moodle-activity-navigation,
body.heyday-quiz-page .nav_guide,
body.heyday-quiz-page .submitbtns + .activity-navigation {
    display: none !important;
}

/* ---- Responsive ---- */
@media (max-width: 1300px) {
    body.heyday-quiz-page #region-main {
        width: 1000px !important;
        max-width: 1000px !important;
    }

    .hdqz-card {
        width: 960px !important;
        max-width: 960px !important;
    }

    body.heyday-quiz-page form#responseform,
    body.heyday-quiz-page .quizattempt,
    body.heyday-quiz-page .quizreview {
        width: 880px !important;
        max-width: 880px !important;
    }
}

@media (max-width: 1080px) {
    body.heyday-quiz-page #region-main,
    .hdqz-card {
        width: 100% !important;
        max-width: 100% !important;
    }

    body.heyday-quiz-page form#responseform,
    body.heyday-quiz-page .quizattempt,
    body.heyday-quiz-page .quizreview {
        width: 100% !important;
        max-width: 100% !important;
    }
}

/* ---- Summary page — hide content, show submitting overlay ---- */
body#page-mod-quiz-summary.heyday-quiz-page #region-main {
    visibility: hidden !important;
    pointer-events: none !important;
}

body#page-mod-quiz-summary.heyday-quiz-page::after {
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

/* ---- Review: ed2go score badge above questions ---- */
.hdqz-score-bar {
    text-align: center !important;
    padding: 20px 0 24px 0 !important;
    border-bottom: 1px solid #e0e6ea !important;
    margin: -4px 0 28px 0 !important;
}

.hdqz-score-label {
    font-size: 13px !important;
    color: #66727c !important;
    text-transform: uppercase !important;
    letter-spacing: 0.07em !important;
    margin: 0 0 6px 0 !important;
}

.hdqz-score-pct {
    font-size: 56px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    color: #3f8b2b !important;
    margin: 0 !important;
}

.hdqz-score-pct.hdqz-score-fail {
    color: #b73434 !important;
}

.hdqz-score-detail {
    font-size: 15px !important;
    color: #555 !important;
    margin: 8px 0 0 0 !important;
}

.hdqz-score-status {
    display: inline-block !important;
    margin-top: 12px !important;
    padding: 3px 18px !important;
    border-radius: 20px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
}

.hdqz-score-status.hdqz-pass {
    background: #dfeeda !important;
    color: #2d6422 !important;
}

.hdqz-score-status.hdqz-fail {
    background: #f1d6d6 !important;
    color: #7b1e1e !important;
}

/* ---- Print ---- */
@media print {
    body.heyday-quiz-page .drawer,
    body.heyday-quiz-page .drawer-left,
    body.heyday-quiz-page .drawer-right,
    body.heyday-quiz-page .hdqz-topbar,
    body.heyday-quiz-page .hdqz-next,
    body.heyday-quiz-page .hdqz-end {
        display: none !important;
    }

    body.heyday-quiz-page #region-main,
    .hdqz-card {
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
 * Inject the quiz shell JavaScript after Moodle renders the page.
 * Called by the before_footer_html_generation hook.
 *
 * @return string HTML <script> block or empty string.
 */
function local_heyday_quiz_before_footer(): string {
    global $CFG;

    if (!local_heyday_quiz_is_target()) {
        return '';
    }

    $ctx = local_heyday_quiz_get_context();
    if (!$ctx) {
        return '';
    }

    [$cm, $quiz, $course] = $ctx;

    require_once($CFG->dirroot . '/course/lib.php');

    $coursefullname = format_string($course->fullname);
    $quiztitle      = format_string($quiz->name);
    $returnurl      = local_heyday_quiz_return_url($course, (int)$cm->id);
    $lessonlabel    = local_heyday_quiz_lesson_label($course, (int)$cm->id);

    $introhtml = trim(format_module_intro('quiz', $quiz, $cm->id, false));

    if ($introhtml === '') {
        $introhtml =
            '<p>This learning check helps you review the key concepts from ' . htmlspecialchars($lessonlabel ?: 'this lesson', ENT_QUOTES, 'UTF-8') . '.</p>' .
            '<p>Select your answer for each question. You can save your progress and return later using <strong>Save and Close</strong>, ' .
            'or submit all answers at once with <strong>Submit Answers</strong>.</p>';
    }

    $nextcm = local_heyday_quiz_find_next_cm($course, (int)$cm->id);

    if ($nextcm) {
        $nexturl   = (!empty($nextcm->url) ? $nextcm->url : new moodle_url('/mod/' . $nextcm->modname . '/view.php', ['id' => $nextcm->id]))->out(false);
        $nextname  = format_string($nextcm->name);
        $nexttype  = local_heyday_quiz_display_type($nextcm);

        // Try to determine the next item's lesson label.
        try {
            $modinfo      = get_fast_modinfo($course);
            $nextcmobject = $modinfo->get_cm((int)$nextcm->id);
            $nextsection  = (string)get_section_name($course, (int)$nextcmobject->sectionnum);
        } catch (Throwable $e) {
            $nextsection = $lessonlabel;
        }
    } else {
        $nexturl     = (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'home']))->out(false);
        $nextname    = 'Course Home';
        $nexttype    = 'activity';
        $nextsection = '';
    }

    // Derive end-of-quiz label.
    $endlabel = $lessonlabel !== '' ? 'End of ' . $lessonlabel . ' Quiz' : 'End of Quiz';

    $data = [
        'course'      => $coursefullname,
        'quiz'        => $quiztitle,
        'returnurl'   => $returnurl,
        'introhtml'   => $introhtml,
        'endlabel'    => $endlabel,
        'nexturl'     => $nexturl,
        'nextsection' => $nextsection,
        'nextname'    => $nextname,
        'nexttype'    => $nexttype,
    ];

    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    return <<<HTML
<script id="heyday-quiz-js">
(function() {
    'use strict';

    var HDQ = {$json};

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    /* ── 1. Add body class ─────────────────────────────────────── */
    document.body.classList.add('heyday-quiz-page');

    /* ── 2. Wrap all #region-main content in a white card ─────── */
    function buildShell() {
        var main = document.querySelector('#region-main');
        if (!main || main.querySelector('.hdqz-card')) {
            return;
        }

        var card = document.createElement('section');
        card.className = 'hdqz-card';

        card.innerHTML =
            '<div class="hdqz-topbar">' +
                '<div class="hdqz-left">' +
                    '<a class="hdqz-icon" href="' + HDQ.returnurl + '" aria-label="Back">' +
                        '<i class="fa fa-arrow-left" aria-hidden="true"></i>' +
                    '</a>' +
                    '<button type="button" class="hdqz-icon" id="hdqzBookmark" aria-label="Bookmark">' +
                        '<i class="fa fa-bookmark-o" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="hdqz-right">' +
                    '<div class="hdqz-print-wrap">' +
                        '<button type="button" class="hdqz-icon" id="hdqzPrint" aria-label="Print">' +
                            '<i class="fa fa-print" aria-hidden="true"></i>' +
                        '</button>' +
                        '<div class="hdqz-print-menu" id="hdqzPrintMenu">' +
                            '<button type="button" id="hdqzPrintActivity">Print/Save activity</button>' +
                            '<button type="button" id="hdqzPrintLesson">Print/Save entire lesson</button>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="hdqz-icon" id="hdqzFullscreen" aria-label="Fullscreen">' +
                        '<i class="fa fa-expand" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="hdqz-course-title"></div>' +
            '<h1 class="hdqz-title"></h1>' +
            '<button type="button" class="hdqz-instructions-toggle" id="hdqzInstructionsToggle" aria-expanded="false">' +
                '<i class="fa fa-info-circle" aria-hidden="true"></i> <span>Show Instructions</span>' +
            '</button>' +
            '<div class="hdqz-instructions-panel is-hidden" id="hdqzInstructionsPanel"></div>';

        card.querySelector('.hdqz-course-title').textContent = HDQ.course;
        card.querySelector('.hdqz-title').textContent        = HDQ.quiz;
        card.querySelector('#hdqzInstructionsPanel').innerHTML = HDQ.introhtml;

        /* Move existing children into the card. */
        while (main.firstChild) {
            card.appendChild(main.firstChild);
        }

        main.appendChild(card);
    }

    /* ── 3. Clean up Moodle chrome inside the card ─────────────── */
    function cleanQuiz() {
        var main = document.querySelector('#region-main');
        if (!main) { return; }

        /* Hide redundant headings. */
        main.querySelectorAll('h1, h2, h3').forEach(function(el) {
            if (!el.classList.contains('hdqz-title') && el.closest('.hdqz-card')) {
                var text = (el.textContent || '').trim();
                if (text !== '') {
                    el.style.display = 'none';
                }
            }
        });

        /* Hide admin badges / version info inside question info panels. */
        main.querySelectorAll(
            '.questionflag, .editquestion, .state, .grade, .history, .outcome, ' +
            '.info .badge, .info small, .info a, .info [class*="version"], ' +
            '.version, .questionversion, .questionversionnumber, .question-version, .badge-dark'
        ).forEach(function(el) { el.style.display = 'none'; });

        /* Clean question number badges to show only the number. */
        main.querySelectorAll('.que .info .no').forEach(function(el) {
            var match = (el.textContent || '').match(/\d+/);
            if (match) {
                el.textContent = match[0];
                el.style.display = 'inline-flex';
            }
        });

        /* Strip trailing period from A. B. C. D. capsules. */
        main.querySelectorAll('.answer .answernumber').forEach(function(el) {
            el.textContent = (el.textContent || '').replace('.', '').trim().toUpperCase();
        });

        /* Reset answer row backgrounds. */
        main.querySelectorAll('.answer .r0, .answer .r1').forEach(function(row) {
            row.style.background = '#f5f5f5';
        });
    }

    /* ── 4. Suppress premature Moodle correct/incorrect highlighting ── */
    function cleanLiveAttemptHighlights() {
        if (document.body.id !== 'page-mod-quiz-attempt') { return; }

        document.querySelectorAll(
            '.answer .correct, .answer .incorrect, .answer .partiallycorrect, ' +
            '.answer .notanswered, .answer span.correct, .answer span.incorrect, ' +
            '.answer label.correct, .answer label.incorrect'
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

        document.querySelectorAll(
            '.que .feedback, .que .rightanswer, .que .specificfeedback, .que .generalfeedback'
        ).forEach(function(el) { el.style.display = 'none'; });
    }

    /* ── 5. Replace Moodle submit/next buttons with ed2go layout ── */
    function improveButtons() {
        var form = document.querySelector('form#responseform');
        if (!form) { return; }

        var submitArea = form.querySelector('.submitbtns');
        if (!submitArea) {
            submitArea = document.createElement('div');
            submitArea.className = 'submitbtns';
            form.appendChild(submitArea);
        }

        if (submitArea.dataset.hdqzFixed === '1') { return; }
        submitArea.dataset.hdqzFixed = '1';

        /* Find the primary submit / next button. */
        var submitBtn = null;
        form.querySelectorAll(
            '.submitbtns input[type="submit"], .submitbtns button[type="submit"], ' +
            'input[type="submit"][name="next"], button[type="submit"][name="next"]'
        ).forEach(function(btn) {
            var text = (btn.value || btn.textContent || '').toLowerCase();
            if (text.indexOf('submit') !== -1 || text.indexOf('finish') !== -1 || text.indexOf('next') !== -1) {
                submitBtn = btn;
            }
        });

        if (!submitBtn) {
            var all = form.querySelectorAll('input[type="submit"], button[type="submit"]');
            if (all.length > 0) { submitBtn = all[all.length - 1]; }
        }

        if (!submitBtn) { return; }

        /* Relabel and style the submit button. */
        if (submitBtn.value !== undefined && submitBtn.tagName === 'INPUT') {
            submitBtn.value = 'Submit Answers';
        } else {
            submitBtn.textContent = 'Submit Answers';
        }
        submitBtn.classList.add('hdqz-submit-answer');

        /* On the attempt page: intercept click in capture phase so we fire
           before Moodle's AMD bubble handlers.  Add finishattempt=1 and call
           form.submit() — this jumps directly to processattempt.php, skipping
           the summary/confirmation page entirely. */
        if (document.body.id === 'page-mod-quiz-attempt' && !submitBtn.dataset.hdqzIntercept) {
            submitBtn.dataset.hdqzIntercept = '1';
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

        /* Build the two-button row: [Save and Close] [Submit Answers]. */
        var buttonRow = document.createElement('div');
        buttonRow.className = 'hdqz-button-row';

        var saveClose = document.createElement('a');
        saveClose.href = HDQ.returnurl;
        saveClose.className = 'hdqz-save-close';
        saveClose.textContent = 'Save and Close';

        buttonRow.appendChild(saveClose);
        buttonRow.appendChild(submitBtn);

        submitArea.appendChild(buttonRow);
    }

    /* ── 6. Append End-of-Quiz divider and Next Up card ────────── */
    function addNextUp() {
        /* Only show on review/summary, not during a live attempt. */
        if (document.body.id === 'page-mod-quiz-attempt') { return; }

        var main = document.querySelector('#region-main');
        if (!main || main.querySelector('.hdqz-end')) { return; }

        var end = document.createElement('div');
        end.className = 'hdqz-end';
        end.innerHTML = '<span></span><strong></strong><span></span>';
        end.querySelector('strong').textContent = HDQ.endlabel;

        var next = document.createElement('a');
        next.className = 'hdqz-next';
        next.href = HDQ.nexturl;
        next.innerHTML =
            '<span class="hdqz-next-label">Next Up</span>' +
            '<span class="hdqz-next-content">' +
                '<span class="hdqz-next-section"></span>' +
                '<span class="hdqz-next-title"></span>' +
                '<span class="hdqz-next-type"></span>' +
            '</span>';

        next.querySelector('.hdqz-next-section').textContent = HDQ.nextsection;
        next.querySelector('.hdqz-next-title').textContent   = HDQ.nextname;
        next.querySelector('.hdqz-next-type').textContent    = HDQ.nexttype;

        main.appendChild(end);
        main.appendChild(next);
    }

    /* ── 7. Wire interactive controls ──────────────────────────── */
    function wireControls() {
        /* Show / Hide Instructions. */
        var toggle = document.getElementById('hdqzInstructionsToggle');
        var panel  = document.getElementById('hdqzInstructionsPanel');

        if (toggle && panel && !toggle.dataset.wired) {
            toggle.dataset.wired = '1';
            toggle.addEventListener('click', function() {
                var hidden = panel.classList.toggle('is-hidden');
                toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                var label = toggle.querySelector('span');
                if (label) { label.textContent = hidden ? 'Show Instructions' : 'Hide Instructions'; }
            });
        }

        /* Bookmark icon toggle. */
        var bookmark = document.getElementById('hdqzBookmark');
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

        /* Print dropdown. */
        var printBtn  = document.getElementById('hdqzPrint');
        var printMenu = document.getElementById('hdqzPrintMenu');

        if (printBtn && printMenu && !printBtn.dataset.wired) {
            printBtn.dataset.wired = '1';
            printBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                printMenu.classList.toggle('is-open');
            });
            document.addEventListener('click', function() {
                printMenu.classList.remove('is-open');
            });
        }

        var printActivity = document.getElementById('hdqzPrintActivity');
        var printLesson   = document.getElementById('hdqzPrintLesson');

        if (printActivity && !printActivity.dataset.wired) {
            printActivity.dataset.wired = '1';
            printActivity.addEventListener('click', function() { window.print(); });
        }
        if (printLesson && !printLesson.dataset.wired) {
            printLesson.dataset.wired = '1';
            printLesson.addEventListener('click', function() { window.print(); });
        }

        /* Fullscreen toggle. */
        var fs = document.getElementById('hdqzFullscreen');
        if (fs && !fs.dataset.wired) {
            fs.dataset.wired = '1';
            fs.addEventListener('click', function() {
                var target = document.querySelector('#region-main') || document.documentElement;
                if (!document.fullscreenElement) {
                    if (target.requestFullscreen) { target.requestFullscreen(); }
                } else if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            });
        }
    }

    /* ── 8. Skip summary confirmation — direct form submit ─────── */
    function bypassSummary() {
        if (document.body.id !== 'page-mod-quiz-summary') { return; }

        /* Use form.submit() — bypasses Moodle's JS confirmation modal.
           btn.click() triggers AMD handlers that open a dialog and stall. */
        var form =
            document.querySelector('form[action*="processattempt"]') ||
            document.querySelector('form');

        if (!form) { return; }

        /* Ensure finishattempt=1 is sent — submit buttons are excluded
           from form.submit(), so we inject it as a hidden field. */
        var fi = form.querySelector('input[name="finishattempt"]');
        if (!fi) {
            fi = document.createElement('input');
            fi.type  = 'hidden';
            fi.name  = 'finishattempt';
            form.appendChild(fi);
        }
        fi.value = '1';

        form.submit();
    }

    /* ── 9. Score badge on review page ─────────────────────────── */
    function showScore() {
        if (document.body.id !== 'page-mod-quiz-review') { return; }

        var card = document.querySelector('.hdqz-card');
        if (!card || card.querySelector('.hdqz-score-bar')) { return; }

        /* quizreviewsummary is hidden by CSS but still in the DOM. */
        var summaryEl = document.querySelector('.quizreviewsummary');
        var rawText   = summaryEl ? (summaryEl.textContent || '') : '';

        var pct    = null;
        var detail = '';

        var pctMatch    = rawText.match(/\((\d+(?:\.\d+)?)%\)/);
        var scoreMatch  = rawText.match(/([\d.]+)\s*(?:out of|\/)\s*([\d.]+)/i);
        var marksMatch  = rawText.match(/[Mm]arks?\s*:?\s*([\d.]+)\s*\/\s*([\d.]+)/);
        var gradeMatch  = rawText.match(/[Gg]rade\s*:?\s*([\d.]+)\s*\/\s*([\d.]+)/);

        if (pctMatch) {
            pct = Math.round(parseFloat(pctMatch[1]));
        }

        var fromMatch = scoreMatch || gradeMatch || marksMatch;
        if (fromMatch) {
            var sc  = parseFloat(fromMatch[1]);
            var tot = parseFloat(fromMatch[2]);
            if (pct === null && tot > 0) { pct = Math.round(sc / tot * 100); }
            if (!detail) { detail = fromMatch[1] + ' out of ' + fromMatch[2] + ' correct'; }
        }

        if (pct === null) { return; }

        var isPass = pct >= 70;

        var bar = document.createElement('div');
        bar.className = 'hdqz-score-bar';
        bar.innerHTML =
            '<div class="hdqz-score-label">Your Score</div>' +
            '<div class="hdqz-score-pct' + (isPass ? '' : ' hdqz-score-fail') + '">' + pct + '%</div>' +
            (detail ? '<div class="hdqz-score-detail">' + detail + '</div>' : '') +
            '<span class="hdqz-score-status ' + (isPass ? 'hdqz-pass">Passed' : 'hdqz-fail">Did not pass') + '</span>';

        /* Insert right after the Show Instructions toggle (or after the title). */
        var anchor = card.querySelector('.hdqz-instructions-toggle') ||
                     card.querySelector('.hdqz-title');

        if (anchor && anchor.parentNode === card) {
            anchor.parentNode.insertBefore(bar, anchor.nextSibling);
        } else {
            card.appendChild(bar);
        }
    }

    /* ── 10. Annotate review: X on wrong, check + note on correct ─ */
    function annotateReview() {
        if (document.body.id !== 'page-mod-quiz-review') { return; }

        /* Incorrect / partially correct questions */
        document.querySelectorAll('.que.incorrect, .que.partiallycorrect').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqzReviewed) { return; }
            content.dataset.hdqzReviewed = '1';

            /* ✕ on the wrong selected answer capsule */
            var wrongRow = que.querySelector('.answer .incorrect');
            if (wrongRow) {
                var num = wrongRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdqz-ans-x')) {
                    var xEl = document.createElement('i');
                    xEl.className = 'fa fa-times hdqz-ans-x';
                    xEl.setAttribute('aria-hidden', 'true');
                    num.appendChild(xEl);
                }
            }

            /* ✓ on correct answer capsule + "This was the correct answer." bar */
            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                var num2 = correctRow.querySelector('.answernumber');
                if (num2 && !num2.querySelector('.hdqz-ans-check')) {
                    var chk = document.createElement('i');
                    chk.className = 'fa fa-check hdqz-ans-check';
                    chk.setAttribute('aria-hidden', 'true');
                    num2.appendChild(chk);
                }
                if (!que.querySelector('.hdqz-correct-note')) {
                    var note = document.createElement('div');
                    note.className = 'hdqz-correct-note';
                    note.innerHTML = '<i class="fa fa-check-circle" aria-hidden="true"></i> This was the correct answer.';
                    correctRow.insertAdjacentElement('afterend', note);
                }
            }

            /* "Incorrect." bold prefix inside the red feedback box.
               If no Moodle feedback exists, create a standalone box. */
            var feedbackEl = que.querySelector(
                '.outcome .feedback, .outcome .specificfeedback, .outcome .generalfeedback, ' +
                '.feedback, .specificfeedback, .generalfeedback'
            );
            if (feedbackEl && !feedbackEl.querySelector('.hdqz-incorrect-prefix')) {
                var pfx = document.createElement('span');
                pfx.className = 'hdqz-incorrect-prefix';
                pfx.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                feedbackEl.insertBefore(pfx, feedbackEl.firstChild);
            } else if (!feedbackEl) {
                var formulation = que.querySelector('.formulation');
                if (formulation && !formulation.querySelector('.hdqz-fallback-incorrect')) {
                    var fb = document.createElement('div');
                    fb.className = 'hdqz-fallback-incorrect';
                    fb.style.cssText = 'margin:8px 0 0 58px;padding:10px 14px;background:#f1d6d6;border:1px solid #e6bcbc;border-left:4px solid #b73434;color:#7b1e1e;font-size:14px;border-radius:3px;font-weight:700;';
                    fb.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                    formulation.appendChild(fb);
                }
            }
        });

        /* Correct questions: ✓ on answer capsule only */
        document.querySelectorAll('.que.correct').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqzReviewed) { return; }
            content.dataset.hdqzReviewed = '1';

            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                var num = correctRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdqz-ans-check')) {
                    var chk = document.createElement('i');
                    chk.className = 'fa fa-check hdqz-ans-check';
                    chk.setAttribute('aria-hidden', 'true');
                    num.appendChild(chk);
                }
            }
        });
    }

    /* ── Main init ──────────────────────────────────────────────── */
    var inIframe = (window.self !== window.top);

    function init() {
        if (!inIframe) { buildShell(); }
        cleanQuiz();
        cleanLiveAttemptHighlights();
        improveButtons();
        if (!inIframe) { addNextUp(); }
        if (!inIframe) { wireControls(); }
        bypassSummary();
        if (!inIframe) { showScore(); }
        if (!inIframe) { annotateReview(); }
    }

    ready(init);
    /* Re-run after Moodle's AMD modules may have mutated the DOM. */
    setTimeout(init, 300);
    setTimeout(init, 1000);
    setTimeout(init, 2000);
})();
</script>
HTML;
}
