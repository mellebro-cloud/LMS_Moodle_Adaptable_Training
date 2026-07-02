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
 * Return a tiny inline <script> to inject into <head> on attempt/summary pages.
 *
 * The script patches EventTarget.prototype.addEventListener to track every
 * beforeunload handler that Moodle's AMD modules register on window, and
 * exposes window._hdqzDropBU() which removes them all (plus window.onbeforeunload).
 * This lets us suppress the "Leave site?" dialog reliably without relying on
 * stopImmediatePropagation, which Chrome may still honour even when e.returnValue
 * is cleared afterward.
 *
 * @return string  HTML <script> tag, or empty string for non-target pages.
 */
function local_heyday_quiz_early_head_script(): string {
    global $PAGE;

    if (!in_array($PAGE->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'], true)) {
        return '';
    }

    if (!local_heyday_quiz_is_target()) {
        return '';
    }

    return '<script id="hdqz-bu-interceptor">' .
           '(function(){' .
               'if(typeof EventTarget==="undefined"){return;}' .
               'var _a=EventTarget.prototype.addEventListener,_h=[];' .
               'EventTarget.prototype.addEventListener=function(t,f,o){' .
                   'if(this===window&&t==="beforeunload"){' .
                       '_h.push([f,typeof o==="object"?o:{capture:!!o}]);' .
                   '}' .
                   'return _a.call(this,t,f,o);' .
               '};' .
               'window._hdqzDropBU=function(){' .
                   'window.onbeforeunload=null;' .
                   '_h.forEach(function(e){' .
                       'try{window.removeEventListener("beforeunload",e[0],e[1]);}catch(x){}' .
                   '});' .
                   '_h.length=0;' .
               '};' .
           '}());' .
           '</script>' . "\n";
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

    if (is_file($CFG->dirroot . '/local/heyday_courseplayer/index.php')) {
        return (new moodle_url('/local/heyday_courseplayer/index.php', [
            'id'   => $course->id,
            'page' => 'lessonquiz',
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
    global $PAGE;

    if (!local_heyday_quiz_is_target()) {
        return '';
    }

    // On the native quiz view page, redirect learners to the HeyDay quiz player
    // immediately (before any page content renders).  Teachers keep native access.
    if ($PAGE->pagetype === 'mod-quiz-view') {
        $ctx = local_heyday_quiz_get_context();
        if (!$ctx) {
            return '';
        }
        [$cm, $quiz, $course] = $ctx;
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            return '';  // Editors see the native view page.
        }
        $dest = json_encode(local_heyday_quiz_return_url($course, (int)$cm->id));
        return "<script>window.location.replace({$dest});</script>\n";
    }

    $cpstyles = (new moodle_url('/local/heyday_courseplayer/styles.css'))->out(false);

    return <<<HTML
<link rel="stylesheet" href="{$cpstyles}">
<style id="heyday-quiz-css">
/* =================================================================
   HEYDAY LESSON QUIZ SKIN  –  Uses courseplayer CSS classes.
   Bridge overrides scoped to body.heyday-quiz-page.
   Quiz-specific styles (questions, answers, buttons, score ring).
   ================================================================= */

body.heyday-quiz-page {
    background: #f4f6f8 !important;
    font-family: Arial, Helvetica, sans-serif !important;
    color: #111 !important;
}

/* ── Bridge: courseplayer topbar/sidebar use sticky inside a grid.
      Quiz page injects them at body-level, so we use fixed instead. ── */
body.heyday-quiz-page.local-heyday-courseplayer .heyday-ed2go-topbar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    z-index: 9900 !important;
    margin: 0 !important;
}

body.heyday-quiz-page.local-heyday-courseplayer .heyday-courseplayer-sidebar {
    position: fixed !important;
    top: 42px !important;
    left: 0 !important;
    bottom: 0 !important;
    width: var(--heyday-sidebar-width, 424px) !important;
    height: auto !important;
    z-index: 9800 !important;
}

/* Page padding to make room for fixed topbar + sidebar. */
body.heyday-quiz-page.local-heyday-courseplayer #page {
    padding-top: 42px !important;
    padding-left: var(--heyday-sidebar-width, 424px) !important;
    margin: 0 !important;
    min-height: 100vh !important;
    box-sizing: border-box !important;
}

body.heyday-quiz-page.local-heyday-courseplayer.hdqz-no-sidebar #page {
    padding-left: 0 !important;
}

/* Strip Adaptable outer containers. */
body.heyday-quiz-page.local-heyday-courseplayer #region-main,
body.heyday-quiz-page.local-heyday-courseplayer #region-main-box,
body.heyday-quiz-page.local-heyday-courseplayer .region-main-content {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
}

/* Content area padding around the player card. */
body.heyday-quiz-page.local-heyday-courseplayer #region-main {
    width: auto !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 26px 32px 90px 32px !important;
    background: var(--heyday-page-bg, #f4f6f8) !important;
}

/* Hide everything in #region-main that is not part of the HeyDay quiz shell. */
body.heyday-quiz-page.local-heyday-courseplayer #region-main > *:not(.heyday-player-card):not(.heyday-nextup-row):not(.heyday-player-footer) {
    display: none !important;
}

/* ---- Hide Moodle navigation chrome ---- */
body.heyday-quiz-page nav.navbar,
body.heyday-quiz-page .navbar,
body.heyday-quiz-page #theme_boost-drawers-courseindex,
body.heyday-quiz-page [data-region="courseindex"],
body.heyday-quiz-page .drawer,
body.heyday-quiz-page .drawer-left,
body.heyday-quiz-page .drawer-right,
body.heyday-quiz-page .drawer-toggles,
body.heyday-quiz-page [data-region="blocks-column"],
body.heyday-quiz-page #block-region-side-pre,
body.heyday-quiz-page #block-region-side-post,
body.heyday-quiz-page .block,
body.heyday-quiz-page footer,
body.heyday-quiz-page #page-footer { display: none !important; }

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
body.heyday-quiz-page .activity_footer,
body.heyday-quiz-page #adaptable-activity-navigation,
body.heyday-quiz-page .jumpnav,
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
body.heyday-quiz-page .continuebutton,
body.heyday-quiz-page #quiz-timer-wrapper,
body.heyday-quiz-page #maincontent,
body.heyday-quiz-page .course_category_tree,
body.heyday-quiz-page .course-content-header,
body.heyday-quiz-page .course-content-footer {
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

/* ---- Print dropdown (quiz-specific, kept as hdqz-*) ---- */
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

/* ---- Show Instructions toggle (quiz-specific) ---- */
.hdqz-instructions-toggle {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    min-height: 24px !important;
    margin: 0 0 22px 0 !important;
    padding: 0 !important;
    background: transparent !important;
    border: 0 !important;
    color: #007acc !important;
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
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 auto !important;
}

/* Remove duplicate Moodle headings inside the player card. */
body.heyday-quiz-page .heyday-player-card > h1,
body.heyday-quiz-page .heyday-player-card > h2,
body.heyday-quiz-page .heyday-player-card > h3,
body.heyday-quiz-page .heyday-content-body > h1:not(.heyday-player-heading h1),
body.heyday-quiz-page .heyday-content-body > h2,
body.heyday-quiz-page .heyday-content-body > h3 {
    display: none !important;
}

/* ---- Question block: block layout (no flex side-by-side). ---- */
body.heyday-quiz-page .que {
    position: relative !important;
    display: block !important;           /* disable Moodle's flex layout */
    margin: 0 !important;
    padding: 24px 0 24px 58px !important;
    border: 0 !important;
    border-top: 1px dashed #c8d0d6 !important;
    background: transparent !important;
    box-shadow: none !important;
    clear: both !important;
    overflow: visible !important;
    gap: 0 !important;
}

body.heyday-quiz-page .que:first-of-type { border-top: 0 !important; }

/* Question number badge: absolutely positioned inside the 58px left padding lane. */
body.heyday-quiz-page .que .info {
    position: absolute !important;
    display: block !important;
    left: 0 !important;
    top: 24px !important;
    width: 46px !important;
    min-width: 0 !important;
    flex-shrink: unset !important;
    margin: 0 !important;
    padding: 0 !important;
    float: none !important;
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
}

body.heyday-quiz-page .que .info .no,
body.heyday-quiz-page .que .info h3.no,
body.heyday-quiz-page .que .info div.no {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px !important;
    min-width: 38px !important;
    height: 36px !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 19px !important;
    background: #6d7a83 !important;
    color: #fff !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    line-height: 36px !important;
    border: 0 !important;
    box-shadow: none !important;
}

/* Question content fills the block width after the left padding lane. */
body.heyday-quiz-page .que .content {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    flex-grow: unset !important;
    width: auto !important;
}

body.heyday-quiz-page .que .formulation {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    border-radius: 0 !important;
}

/* Neutralise the plain <div> wrapper inside form#responseform. */
body.heyday-quiz-page form#responseform > div {
    background: transparent !important;
    box-shadow: none !important;
    border: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
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
    color: #007acc !important;
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
    color: #007acc !important;
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
    color: #007acc !important;
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

/* ---- Review: un-hide .outcome so feedback text is visible, but strip
       Moodle's default pale-yellow wrapper so it is only a transparent
       layout container — the inner .feedback box supplies the colour. ---- */
body#page-mod-quiz-review.heyday-quiz-page .que .outcome {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
    background-color: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
}

body#page-mod-quiz-review.heyday-quiz-page .que .outcome .grade,
body#page-mod-quiz-review.heyday-quiz-page .que .outcome .rightanswer {
    display: none !important;
}

/* Also flatten any generic feedback wrapper Moodle nests around the box. */
body#page-mod-quiz-review.heyday-quiz-page .que .formulation > .outcome,
body#page-mod-quiz-review.heyday-quiz-page .que .content > .outcome,
body#page-mod-quiz-review.heyday-quiz-page .que .feedback.clearfix,
body#page-mod-quiz-review.heyday-quiz-page .que .im-feedback {
    background: transparent !important;
    background-color: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
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
    max-width: 100% !important;
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

/* ---- Review page: "Finish review" link styled as ed2go button ---- */
body#page-mod-quiz-review.heyday-quiz-page .submitbtns a,
body#page-mod-quiz-review.heyday-quiz-page .submitbtns input[type="submit"],
body#page-mod-quiz-review.heyday-quiz-page .submitbtns button,
body#page-mod-quiz-review.heyday-quiz-page a.endtestlink {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 36px !important;
    padding: 8px 18px !important;
    background: #007acc !important;
    border: 1px solid #007acc !important;
    border-radius: 3px !important;
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    text-decoration: none !important;
    cursor: pointer !important;
}

body#page-mod-quiz-review.heyday-quiz-page .submitbtns a:hover,
body#page-mod-quiz-review.heyday-quiz-page a.endtestlink:hover {
    background: #005d8c !important;
    border-color: #005d8c !important;
    color: #fff !important;
    text-decoration: none !important;
}


/* ---- Remove leftover Moodle navigation ---- */
body.heyday-quiz-page .activity-navigation,
body.heyday-quiz-page .moodle-activity-navigation,
body.heyday-quiz-page .nav_guide,
body.heyday-quiz-page .submitbtns + .activity-navigation {
    display: none !important;
}

/* ---- Responsive ---- */
@media (max-width: 900px) {
    body.heyday-quiz-page.local-heyday-courseplayer #page { padding-left: 0 !important; }
    body.heyday-quiz-page.local-heyday-courseplayer .heyday-courseplayer-sidebar { display: none !important; }
    body.heyday-quiz-page.local-heyday-courseplayer #region-main { padding-left: 12px !important; padding-right: 12px !important; }
    body.heyday-quiz-page.local-heyday-courseplayer .heyday-player-card { padding: 18px 14px !important; }
}

@media print {
    body.heyday-quiz-page.local-heyday-courseplayer .heyday-ed2go-topbar,
    body.heyday-quiz-page.local-heyday-courseplayer .heyday-courseplayer-sidebar { display: none !important; }
    body.heyday-quiz-page.local-heyday-courseplayer #page { padding: 0 !important; }
    body.heyday-quiz-page.local-heyday-courseplayer #region-main { padding: 0 !important; }
    .heyday-player-card { max-width: 100% !important; border: 0 !important; padding: 0 !important; }
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

/* ── Confirmation modal ── */
.hdqz-modal-backdrop {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(0,0,0,0.5) !important;
    z-index: 9999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.hdqz-modal {
    background: #fff !important;
    border-radius: 4px !important;
    box-shadow: 0 8px 32px rgba(0,0,0,0.28) !important;
    padding: 32px 36px 28px !important;
    max-width: 480px !important;
    width: 90% !important;
    text-align: center !important;
}
.hdqz-modal-title {
    font-size: 18px !important;
    font-weight: 600 !important;
    color: #1f2937 !important;
    margin: 0 0 10px !important;
    line-height: 1.4 !important;
}
.hdqz-modal-note {
    font-size: 14px !important;
    color: #6b7280 !important;
    margin: 0 0 24px !important;
}
.hdqz-modal-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
}
.hdqz-modal-cancel {
    padding: 9px 20px !important;
    background: #fff !important;
    border: 1px solid #b7c0c9 !important;
    border-radius: 3px !important;
    color: #334155 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}
.hdqz-modal-cancel:hover { background: #f3f4f6 !important; }
.hdqz-modal-confirm {
    padding: 9px 20px !important;
    background: #4f922d !important;
    border: 1px solid #4f922d !important;
    border-radius: 3px !important;
    color: #fff !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}
.hdqz-modal-confirm:hover { background: #3e7523 !important; border-color: #3e7523 !important; }

/* ── Review header: attempt bar ── */
.hdqz-review-header { margin: 0 0 24px !important; }
.hdqz-attempt-bar {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 10px 16px !important;
    background: #f3f5f7 !important;
    border: 1px solid #d7dce2 !important;
    border-radius: 3px !important;
    margin-bottom: 12px !important;
    font-size: 14px !important;
    color: #334155 !important;
    flex-wrap: wrap !important;
}
.hdqz-attempt-label {
    font-weight: 700 !important;
    color: #1f2937 !important;
}
.hdqz-attempt-date { color: #6b7280 !important; flex: 1 1 auto !important; }
.hdqz-pct-badge {
    padding: 3px 12px !important;
    border-radius: 20px !important;
    font-weight: 700 !important;
    font-size: 13px !important;
}
.hdqz-badge-pass { background: #dfeeda !important; color: #2d6422 !important; }
.hdqz-badge-fail { background: #f1d6d6 !important; color: #7b1e1e !important; }

/* ── Retake row ── */
.hdqz-retake-row {
    margin-bottom: 20px !important;
    padding: 12px 16px !important;
    border: 1px solid #4f922d !important;
    border-radius: 3px !important;
    background: #f6fbf3 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.hdqz-retake-btn {
    color: #4f922d !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    text-decoration: none !important;
}
.hdqz-retake-btn:hover { color: #3e7523 !important; text-decoration: underline !important; }
.hdqz-retake-btn .fa { margin-right: 6px !important; }

/* ── Score ring ── */
.hdqz-score-ring-wrap {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    padding: 20px 0 !important;
}
.hdqz-score-ring { display: block !important; overflow: visible !important; }
.hdqz-score-count {
    margin: 12px 0 0 !important;
    font-size: 16px !important;
    color: #334155 !important;
    font-weight: 500 !important;
    text-align: center !important;
}

/* ── Per-question review row coloring ── */
.hdqz-ans-correct-row { background: #dfeeda !important; }
.hdqz-ans-correct-row .answernumber { background: #4f922d !important; color: #fff !important; }
.hdqz-ans-correct-row label,
.hdqz-ans-correct-row .flex-fill,
.hdqz-ans-correct-row .flex-fill p { color: #2d6422 !important; }

.hdqz-ans-wrong-row { background: #f8d7da !important; }
.hdqz-ans-wrong-row .answernumber { background: #b73434 !important; color: #fff !important; }
.hdqz-ans-wrong-row label,
.hdqz-ans-wrong-row .flex-fill,
.hdqz-ans-wrong-row .flex-fill p { color: #7b1e1e !important; }

.hdqz-ans-right-unselected-row { background: #e8f4fc !important; }
.hdqz-ans-right-unselected-row .answernumber { background: #0076a8 !important; color: #fff !important; }
.hdqz-ans-right-unselected-row label,
.hdqz-ans-right-unselected-row .flex-fill,
.hdqz-ans-right-unselected-row .flex-fill p { color: #005c87 !important; }

/* ── Result labels ── */
.hdqz-result-label {
    margin: 12px 0 4px !important;
    font-weight: 700 !important;
    font-size: 14px !important;
}
.hdqz-label-correct { color: #2d6422 !important; }
.hdqz-label-incorrect { color: #7b1e1e !important; }

/* ── "This was the correct answer." note ── */
.hdqz-correct-note {
    margin: 6px 0 0 !important;
    padding: 8px 14px !important;
    background: #e8f4fc !important;
    border: 1px solid #0076a8 !important;
    border-left: 4px solid #0076a8 !important;
    border-radius: 0 3px 3px 0 !important;
    color: #005c87 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
}
.hdqz-correct-note .fa { margin-right: 5px !important; }

/* ── Feedback panel: incorrect styling ── */
.hdqz-feedback-incorrect {
    background: #f8d7da !important;
    border: 1px solid #e6bcbc !important;
    border-left: 4px solid #b73434 !important;
    border-radius: 0 3px 3px 0 !important;
    color: #7b1e1e !important;
    padding: 10px 14px !important;
    margin-top: 8px !important;
}
.hdqz-incorrect-prefix {
    display: block !important;
    font-weight: 700 !important;
    margin-bottom: 4px !important;
}

/* ---- Print ---- */
@media print {
    .heyday-ed2go-topbar, .heyday-courseplayer-sidebar,
    body.heyday-quiz-page .heyday-nextup-row,
    body.heyday-quiz-page .heyday-player-footer { display: none !important; }

    body.heyday-quiz-page #page { padding: 0 !important; }
    body.heyday-quiz-page #region-main { padding: 0 !important; }

    .heyday-player-card {
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

    // Review-page attempt data (attempt number, date, retake eligibility).
    global $DB, $USER, $PAGE;
    $attemptno   = 0;
    $attemptdate = '';
    $canretake   = false;
    if ($PAGE->pagetype === 'mod-quiz-review') {
        $reviewattemptid = optional_param('attempt', 0, PARAM_INT);
        if ($reviewattemptid > 0) {
            try {
                $arecord = $DB->get_record('quiz_attempts',
                    ['id' => $reviewattemptid], 'id,attempt,timefinish,preview');
                if ($arecord && !(int)$arecord->preview && (int)$arecord->timefinish > 0) {
                    $attemptno   = (int)$arecord->attempt;
                    $attemptdate = userdate((int)$arecord->timefinish,
                        get_string('strftimedatetime', 'langconfig'));
                    $maxattempts = (int)$quiz->attempts;
                    $donecount   = $DB->count_records('quiz_attempts',
                        ['quiz' => $quiz->id, 'userid' => $USER->id, 'preview' => 0, 'state' => 'finished']);
                    $canretake   = ($maxattempts === 0) || ($donecount < $maxattempts);
                }
            } catch (Throwable $e) {
                // ignore — leave defaults
            }
        }
    }

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
        $nexturl   = (new moodle_url('/local/heyday_courseplayer/index.php', [
            'id'   => $course->id,
            'page' => 'lesson',
            'cmid' => $nextcm->id,
        ]))->out(false);
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

    // ---- Build sidebar navigation data (subsection-aware, deduplicated) ----
    //
    // Groups are keyed by lesson number so each "Lesson N" appears exactly once,
    // even when Moodle splits a lesson across multiple sections.  Delegated
    // subsection sections are skipped at the top level (they are reached only by
    // recursing through their parent's subsection module), which prevents the
    // "Lesson 5 Review / Lesson 6 Review repeated many times" duplication.
    $sidebargroups = [];
    try {
        $modinfoSb   = get_fast_modinfo($course);
        $allsections = $modinfoSb->get_section_info_all();

        // Collect visible cms in a section, following subsection delegates.
        $collect = function (int $snum, array &$seen) use (
            &$collect, $modinfoSb, $allsections, $course, $cm, $CFG
        ): array {
            $out = [];
            foreach (($modinfoSb->sections[$snum] ?? []) as $scmid) {
                try {
                    $scm = $modinfoSb->get_cm($scmid);
                } catch (Throwable $e) {
                    continue;
                }
                if (!$scm->uservisible || $scm->modname === 'label') {
                    continue;
                }

                // Subsection: recurse into its delegated section, don't list the wrapper.
                if ($scm->modname === 'subsection') {
                    foreach ($allsections as $cand) {
                        if (!empty($cand->component)
                            && $cand->component === 'mod_subsection'
                            && (int)$cand->itemid === (int)$scm->instance) {
                            $out = array_merge($out, $collect((int)$cand->section, $seen));
                            break;
                        }
                    }
                    continue;
                }

                // De-duplicate each cm across the whole sidebar.
                if (isset($seen[(int)$scm->id])) {
                    continue;
                }
                $seen[(int)$scm->id] = true;

                if (file_exists($CFG->dirroot . '/local/heyday_courseplayer/index.php')) {
                    // Route quiz CMs to the dedicated lessonquiz intro card;
                    // forum CMs to the discussion view; everything else to lesson.
                    if ($scm->modname === 'quiz') {
                        $scmpage = 'lessonquiz';
                    } else if ($scm->modname === 'forum') {
                        $scmpage = 'discussion';
                    } else {
                        $scmpage = 'lesson';
                    }
                    $iurl = (new moodle_url('/local/heyday_courseplayer/index.php', [
                        'id' => $course->id, 'page' => $scmpage, 'cmid' => $scm->id,
                    ]))->out(false);
                } else {
                    $iurl = !empty($scm->url)
                        ? $scm->url->out(false)
                        : (new moodle_url('/mod/' . $scm->modname . '/view.php', ['id' => $scm->id]))->out(false);
                }
                $out[] = [
                    'name'      => format_string($scm->name),
                    'url'       => $iurl,
                    'isCurrent' => ((int)$scm->id === (int)$cm->id),
                    'isLocked'  => !$scm->available,
                ];
            }
            return $out;
        };

        $bylesson = [];
        $seen     = [];
        foreach ($allsections as $snum => $section) {
            if ($snum == 0) {
                continue;
            }
            // Skip delegated subsection sections at top level — reached via recursion.
            if (!empty($section->component)) {
                continue;
            }
            $sname = get_section_name($course, $section);
            if (!preg_match('/^\s*lesson\s+(\d+)/i', $sname, $m)) {
                continue;
            }
            $lessonnum = (int)$m[1];
            $items = $collect((int)$snum, $seen);
            if (!$items) {
                continue;
            }
            if (!isset($bylesson[$lessonnum])) {
                $bylesson[$lessonnum] = ['name' => $sname, 'items' => []];
            }
            $bylesson[$lessonnum]['items'] = array_merge($bylesson[$lessonnum]['items'], $items);
        }
        ksort($bylesson);
        $sidebargroups = array_values($bylesson);
    } catch (Throwable $e) {
        $sidebargroups = [];
    }

    $hasCp  = file_exists($CFG->dirroot . '/local/heyday_courseplayer/index.php');

    $sbnavlinks = [];
    if ($hasCp) {
        $sbnavlinks[] = ['label' => 'Home',          'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'home']))->out(false)];
        $sbnavlinks[] = ['label' => 'Scores',         'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'scores']))->out(false)];
        $sbnavlinks[] = ['label' => 'Discussions',    'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'discussions']))->out(false)];
        $sbnavlinks[] = ['label' => 'Getting Started','url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'gettingstarted']))->out(false)];
        $sbnavlinks[] = ['label' => 'Pretest',        'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'pretest']))->out(false)];
    }

    $sbafterlinks = [];
    if ($hasCp) {
        $sbafterlinks[] = ['label' => 'Resources',  'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'resources']))->out(false)];
        $sbafterlinks[] = ['label' => 'Final Exam', 'url' => (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'finalexam']))->out(false)];
    }

    $supporturl = $hasCp
        ? (new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id, 'page' => 'home']))->out(false)
        : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

    $data = [
        'course'       => $coursefullname,
        'lessonlabel'  => $lessonlabel,
        'quiz'         => $quiztitle,
        'returnurl'    => $returnurl,
        'introhtml'    => $introhtml,
        'endlabel'     => $endlabel,
        'nexturl'      => $nexturl,
        'nextsection'  => $nextsection,
        'nextname'     => $nextname,
        'nexttype'     => $nexttype,
        'attemptno'    => $attemptno,
        'attemptdate'  => $attemptdate,
        'canretake'    => $canretake,
        'supporturl'   => $supporturl,
        'cookieurl'    => '#',
        'copyright'    => '© ' . date('Y') . ' Cengage Learning, Inc. All Rights Reserved.',
        'sidebar'      => [
            'navlinks'   => $sbnavlinks,
            'groups'     => $sidebargroups,
            'afterlinks' => $sbafterlinks,
        ],
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

    /* ── 1. Add body classes ───────────────────────────────────── */
    document.body.classList.add('heyday-quiz-page');
    document.body.classList.add('local-heyday-quiz');
    document.body.classList.add('local-heyday-courseplayer');
    document.body.classList.add('local-heyday-masterplayer');

    /* ── 1a. HTML-escape helper ───────────────────────────────── */
    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── 1b. Black topnav bar ─────────────────────────────────── */
    function buildTopnav() {
        if (document.querySelector('.heyday-ed2go-topbar')) { return; }
        var nav = document.createElement('div');
        nav.className = 'heyday-ed2go-topbar';
        nav.innerHTML =
            '<div class="heyday-ed2go-brand"><span>' + esc(HDQ.course) + '</span></div>' +
            '<div class="heyday-ed2go-topbar-right">' +
                '<a href="' + esc(HDQ.returnurl) + '" style="color:#fff;text-decoration:none;font-size:13px;">Exit Quiz</a>' +
            '</div>';
        document.body.insertBefore(nav, document.body.firstChild);
    }

    /* ── 1c. Left sidebar ─────────────────────────────────────── */
    function buildSidenav() {
        if (document.querySelector('.heyday-courseplayer-sidebar') || !HDQ.sidebar) { return; }
        var sb = document.createElement('aside');
        sb.className = 'heyday-courseplayer-sidebar';
        var html = '';

        // Primary nav: <a> directly in <nav> — no <ul><li> (matches PHP sidebar).
        html += '<nav class="heyday-main-menu heyday-primary-menu" aria-label="Main course navigation">';
        (HDQ.sidebar.navlinks || []).forEach(function(link) {
            if (!link.url) { return; }
            var cls = 'heyday-main-nav-link has-no-icon' + (link.isCurrent ? ' is-current' : '');
            html += '<a class="' + cls + '" href="' + esc(link.url) + '">' +
                        '<span class="heyday-main-nav-label">' + esc(link.label) + '</span>' +
                    '</a>';
        });
        html += '</nav>';

        // Lesson groups — match PHP: heyday-lesson-group / heyday-lesson-items (div, not ul).
        if ((HDQ.sidebar.groups || []).length > 0) {
            html += '<div class="heyday-lessons-label">Lessons</div>';
            html += '<div class="heyday-lesson-list">';
            (HDQ.sidebar.groups || []).forEach(function(group) {
                var hasActive = group.items.some(function(i) { return i.isCurrent; });
                var groupUrl = '';
                for (var g = 0; g < (group.items || []).length; g++) {
                    if (!group.items[g].isLocked && group.items[g].url) {
                        groupUrl = group.items[g].url; break;
                    }
                }
                var groupCls = 'heyday-lesson-group' + (hasActive ? ' is-active' : '');
                html += '<details class="' + groupCls + '" name="heyday-lesson"' + (hasActive ? ' open' : '') + '>';
                html += '<summary><span class="heyday-group-summary-inner">';
                if (groupUrl) {
                    html += '<a class="heyday-lesson-group-title" href="' + esc(groupUrl) + '">' + esc(group.name) + '</a>';
                } else {
                    html += '<span class="heyday-lesson-group-title is-disabled">' + esc(group.name) + '</span>';
                }
                html += '<span class="heyday-group-status" aria-hidden="true"><span class="heyday-progress-dot"></span></span>';
                html += '</span></summary>';
                html += '<div class="heyday-lesson-items">';
                (group.items || []).forEach(function(item) {
                    var cls = 'heyday-lesson-item depth-1';
                    if (item.isCurrent) { cls += ' is-current'; }
                    if (item.isLocked)  { cls += ' is-locked'; }
                    if (item.isLocked) {
                        html += '<div class="' + cls + '">';
                        html += '<span class="heyday-current-arrow" aria-hidden="true"></span>';
                        html += '<span class="heyday-lesson-text"><span>' + esc(item.name) + '</span></span>';
                        html += '<span class="heyday-status-icon locked" aria-hidden="true">🔒</span>';
                        html += '</div>';
                    } else {
                        html += '<a class="' + cls + '" href="' + esc(item.url) + '">';
                        html += '<span class="heyday-current-arrow" aria-hidden="true"></span>';
                        html += '<span class="heyday-lesson-text"><span>' + esc(item.name) + '</span></span>';
                        html += '<span class="heyday-status-icon" aria-hidden="true"></span>';
                        html += '</a>';
                    }
                });
                html += '</div></details>';
            });
            html += '</div>';
        }

        // After lessons nav (Resources, Final Exam): <a> directly in <nav>.
        if ((HDQ.sidebar.afterlinks || []).some(function(l) { return !!l.url; })) {
            html += '<nav class="heyday-main-menu heyday-after-lessons-menu" aria-label="More course navigation">';
            (HDQ.sidebar.afterlinks || []).forEach(function(link) {
                if (!link.url) { return; }
                var linkCls = 'heyday-main-nav-link has-no-icon' + (link.isCurrent ? ' is-current' : '');
                html += '<a class="' + linkCls + '" href="' + esc(link.url) + '">' +
                            '<span class="heyday-main-nav-label">' + esc(link.label) + '</span>' +
                        '</a>';
            });
            html += '</nav>';
        }

        sb.innerHTML = html;

        // Insert after topbar (or at body start).
        var topbar = document.querySelector('.heyday-ed2go-topbar');
        if (topbar && topbar.nextSibling) {
            topbar.parentNode.insertBefore(sb, topbar.nextSibling);
        } else {
            document.body.insertBefore(sb, document.body.firstChild);
        }
    }

    /* ── 1d. Force #page offset so the fixed sidebar does not cover content.
             Moodle's Boost drawer JS may reset #page inline styles after our
             CSS rules apply; using style.setProperty overrides that. ─────── */
    function fixLayout() {
        if (inIframe) { return; }
        var sb = document.querySelector('.heyday-courseplayer-sidebar');
        if (!sb) { return; }
        /* Use the sidebar's actual rendered width so any CSS-overridden value
           is respected; fall back to 424 if the sidebar hasn't painted yet. */
        var sbW = sb.offsetWidth > 0 ? sb.offsetWidth : 424;
        var page = document.getElementById('page');
        if (page) {
            page.style.setProperty('padding-top',    '42px',        'important');
            page.style.setProperty('padding-left',   sbW + 'px',    'important');
            page.style.setProperty('box-sizing',     'border-box',  'important');
            page.style.setProperty('margin',         '0',           'important');
        }
        /* Strip constraining widths on Moodle's inner scroll/content wrappers
           so the quiz card can expand to fill the full content column. */
        ['topofscroll', 'page-content', 'page-wrapper'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) { return; }
            el.style.setProperty('max-width',   'none',  'important');
            el.style.setProperty('width',       '100%',  'important');
            el.style.setProperty('margin-left', '0',     'important');
        });
    }

    /* ── 2. Wrap all #region-main content in a white card ─────── */
    function buildShell() {
        var main = document.querySelector('#region-main');
        if (!main || main.querySelector('.heyday-player-card')) {
            return;
        }

        var card = document.createElement('section');
        card.className = 'heyday-player-card';

        card.innerHTML =
            '<div class="heyday-player-topbar">' +
                '<div class="heyday-topbar-left">' +
                    '<a class="heyday-back-link" href="' + HDQ.returnurl + '" aria-label="Back">' +
                        '<i class="fa fa-arrow-left" aria-hidden="true"></i>' +
                    '</a>' +
                    '<button type="button" class="heyday-icon-button" id="hdqzBookmark" aria-label="Bookmark">' +
                        '<i class="fa fa-bookmark-o" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>' +
                '<div class="heyday-topbar-spacer"></div>' +
                '<div class="heyday-topbar-right">' +
                    '<div class="hdqz-print-wrap">' +
                        '<button type="button" class="heyday-icon-button" id="hdqzPrint" aria-label="Print">' +
                            '<i class="fa fa-print" aria-hidden="true"></i>' +
                        '</button>' +
                        '<div class="hdqz-print-menu" id="hdqzPrintMenu">' +
                            '<button type="button" id="hdqzPrintActivity">Print/Save activity</button>' +
                            '<button type="button" id="hdqzPrintLesson">Print/Save entire lesson</button>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="heyday-icon-button" id="hdqzFullscreen" aria-label="Fullscreen">' +
                        '<i class="fa fa-expand" aria-hidden="true"></i>' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<div class="heyday-player-heading">' +
                '<p class="heyday-course-kicker"></p>' +
                '<p class="heyday-lesson-kicker"></p>' +
                '<h1></h1>' +
            '</div>' +
            '<button type="button" class="hdqz-instructions-toggle" id="hdqzInstructionsToggle" aria-expanded="false">' +
                '<i class="fa fa-info-circle" aria-hidden="true"></i> <span>Show Instructions</span>' +
            '</button>' +
            '<div class="hdqz-instructions-panel is-hidden" id="hdqzInstructionsPanel"></div>';

        card.querySelector('.heyday-course-kicker').textContent = HDQ.course;
        var kickerEl = card.querySelector('.heyday-lesson-kicker');
        if (kickerEl) {
            if (HDQ.lessonlabel) {
                kickerEl.textContent = HDQ.lessonlabel;
            } else {
                kickerEl.style.display = 'none';
            }
        }
        card.querySelector('.heyday-player-heading h1').textContent = HDQ.quiz;
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

        /* Hide redundant headings — but skip the .no badge inside .que .info,
           which IS an h3 but must remain visible as the question number circle. */
        main.querySelectorAll('h1, h2, h3').forEach(function(el) {
            if (!el.closest('.heyday-player-heading') && el.closest('.heyday-player-card')) {
                /* Skip the question-number badge. */
                if (el.classList.contains('no') && el.closest('.que .info')) {
                    return;
                }
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
            el.style.color = '#007acc';
            el.style.fontWeight = 'normal';
        });

        document.querySelectorAll('.answer .r0, .answer .r1, .answer > div').forEach(function(row) {
            row.style.background = '#f5f5f5';
        });

        document.querySelectorAll('.answer .flex-fill, .answer .flex-fill p, .answer label, .answer label span').forEach(function(el) {
            el.style.background = 'transparent';
            el.style.color = '#007acc';
            el.style.fontWeight = 'normal';
        });

        document.querySelectorAll(
            '.que .feedback, .que .rightanswer, .que .specificfeedback, .que .generalfeedback'
        ).forEach(function(el) { el.style.display = 'none'; });
    }

    /* ── Drop all beforeunload handlers before HeyDay navigates ──
       Primary path: call window._hdqzDropBU() which the early head script
       built by local_heyday_quiz_early_head_script() injects.  It removes
       every addEventListener('beforeunload') handler that AMD modules
       registered (tracked via an EventTarget.prototype.addEventListener
       patch) plus window.onbeforeunload.
       Fallback: if _hdqzDropBU is not available (e.g. on review.php where
       the early script is not injected), clear onbeforeunload and add a
       capture-phase suppressor that fires before Moodle's bubble handler. */
    function dropBeforeunload() {
        window.onbeforeunload = null;
        if (typeof window._hdqzDropBU === 'function') {
            window._hdqzDropBU();
            return;
        }
        /* Fallback capture suppressor. */
        var fn = function(e) {
            e.stopImmediatePropagation();
            e.returnValue = '';
            window.removeEventListener('beforeunload', fn, true);
        };
        window.addEventListener('beforeunload', fn, true);
    }

    /* ── 4b. HeyDay confirmation modal ─────────────────────────── */
    function showSubmitModal(onConfirm) {
        if (document.getElementById('hdqzModal')) { return; }
        var backdrop = document.createElement('div');
        backdrop.id        = 'hdqzModal';
        backdrop.className = 'hdqz-modal-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.innerHTML =
            '<div class="hdqz-modal">' +
                '<p class="hdqz-modal-title">Are you sure you want to submit your answers for this assessment?</p>' +
                '<p class="hdqz-modal-note">Once submitted, you cannot change your answers.</p>' +
                '<div class="hdqz-modal-actions">' +
                    '<button class="hdqz-modal-cancel" type="button">Cancel</button>' +
                    '<button class="hdqz-modal-confirm" type="button">Yes, please submit</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(backdrop);

        backdrop.querySelector('.hdqz-modal-cancel').addEventListener('click', function() {
            backdrop.remove();
        });
        backdrop.querySelector('.hdqz-modal-confirm').addEventListener('click', function() {
            backdrop.remove();
            onConfirm();
        });
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) { backdrop.remove(); }
        });
        document.addEventListener('keydown', function hdqzEsc(e) {
            if (e.key === 'Escape') { backdrop.remove(); document.removeEventListener('keydown', hdqzEsc); }
        });
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
           before Moodle's AMD bubble handlers.  Set finishattempt=1, suppress
           the quiz module's beforeunload warning, then call form.submit().
           Moodle processes the attempt via processattempt.php normally.  If the
           quiz requires a summary/confirmation step, bypassSummary() auto-submits
           it; either way the browser lands on review.php where the HeyDay shell
           renders the full result/review screen. */
        if (document.body.id === 'page-mod-quiz-attempt' && !submitBtn.dataset.hdqzIntercept) {
            submitBtn.dataset.hdqzIntercept = '1';
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showSubmitModal(function() {
                    var fi = form.querySelector('input[name="finishattempt"]');
                    if (!fi) {
                        fi = document.createElement('input');
                        fi.type = 'hidden';
                        fi.name = 'finishattempt';
                        form.appendChild(fi);
                    }
                    fi.value = '1';
                    dropBeforeunload();
                    form.submit();
                });
            }, true);
        }

        /* Build the two-button row: [Save and Close] [Submit Answers]. */
        var buttonRow = document.createElement('div');
        buttonRow.className = 'hdqz-button-row';

        var saveClose = document.createElement('a');
        saveClose.href = HDQ.returnurl;
        saveClose.className = 'hdqz-save-close';
        saveClose.textContent = 'Save and Close';

        /* Save and Close: POST current answers without finishing the attempt
           (finishattempt=0), then navigate back to the HeyDay quiz player. */
        if (document.body.id === 'page-mod-quiz-attempt') {
            saveClose.addEventListener('click', function(e) {
                e.preventDefault();
                var fd = new FormData(form);
                fd.set('finishattempt', '0');
                fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: fd,
                    redirect: 'follow',
                    credentials: 'same-origin'
                }).finally(function() {
                    dropBeforeunload();
                    window.location.replace(HDQ.returnurl);
                });
            });
        }

        buttonRow.appendChild(saveClose);
        buttonRow.appendChild(submitBtn);

        submitArea.appendChild(buttonRow);
    }

    /* ── 6. Append End-of-Quiz divider and Next Up card ────────── */
    function addNextUp() {
        var main = document.querySelector('#region-main');
        if (!main || main.querySelector('.heyday-nextup-row')) { return; }
        if (!HDQ.nexturl) { return; }

        var next = document.createElement('a');
        next.className = 'heyday-nextup-row';
        next.href = HDQ.nexturl;
        next.target = '_top';
        next.setAttribute('aria-label', 'Next Up: ' + HDQ.nextname);
        next.innerHTML =
            '<span class="heyday-nextup-label">Next Up</span>' +
            '<span class="heyday-nextup-body">' +
                '<span class="heyday-nextup-title"></span>' +
                '<span class="heyday-nextup-type"></span>' +
            '</span>';

        next.querySelector('.heyday-nextup-title').textContent = HDQ.nextname;
        next.querySelector('.heyday-nextup-type').textContent  = HDQ.nexttype;

        main.appendChild(next);
    }

    /* ── 6b. Footer: Course Support / Cookie Settings / copyright ── */
    function addFooter() {
        var main = document.querySelector('#region-main');
        if (!main || main.querySelector('.heyday-player-footer')) { return; }

        var footer = document.createElement('footer');
        footer.className = 'heyday-player-footer';
        footer.setAttribute('aria-label', 'Course footer');
        footer.innerHTML =
            '<a href="' + esc(HDQ.supporturl) + '">Course Support</a>' +
            '<span aria-hidden="true"></span>' +
            '<a href="' + esc(HDQ.cookieurl) + '">Cookie Settings</a>' +
            '<div class="heyday-footer-copyright">' + esc(HDQ.copyright) + '</div>';

        main.appendChild(footer);
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

    /* ── 8. Skip summary confirmation — suppress beforeunload, native submit ── */
    function bypassSummary() {
        if (document.body.id !== 'page-mod-quiz-summary') { return; }

        var form =
            document.querySelector('form[action*="processattempt"]') ||
            document.querySelector('form');

        if (!form) { return; }

        var fi = form.querySelector('input[name="finishattempt"]');
        if (!fi) {
            fi = document.createElement('input');
            fi.type  = 'hidden';
            fi.name  = 'finishattempt';
            form.appendChild(fi);
        }
        fi.value = '1';

        // Drop all beforeunload handlers then submit natively.
        // processattempt.php finishes the attempt and redirects to review.php
        // where the HeyDay shell renders the result/review screen directly.
        dropBeforeunload();
        form.submit();
    }

    /* ── 9. Review header: attempt bar + score ring + retake row ── */
    function showScore() {
        if (document.body.id !== 'page-mod-quiz-review') { return; }

        var card = document.querySelector('.heyday-player-card');
        if (!card || card.querySelector('.hdqz-review-header')) { return; }

        /* quizreviewsummary is hidden by CSS but still in the DOM. */
        var summaryEl = document.querySelector('.quizreviewsummary');
        var rawText   = summaryEl ? (summaryEl.textContent || '') : '';

        var pct     = null;
        var correct = null;
        var total   = null;

        var pctMatch   = rawText.match(/\((\d+(?:\.\d+)?)%\)/);
        var scoreMatch = rawText.match(/([\d.]+)\s*(?:out of|\/)\s*([\d.]+)/i);
        var marksMatch = rawText.match(/[Mm]arks?\s*:?\s*([\d.]+)\s*\/\s*([\d.]+)/);
        var gradeMatch = rawText.match(/[Gg]rade\s*:?\s*([\d.]+)\s*\/\s*([\d.]+)/);

        if (pctMatch) { pct = Math.round(parseFloat(pctMatch[1])); }

        var fromMatch = scoreMatch || gradeMatch || marksMatch;
        if (fromMatch) {
            var sc  = parseFloat(fromMatch[1]);
            var tot = parseFloat(fromMatch[2]);
            if (pct === null && tot > 0) { pct = Math.round(sc / tot * 100); }
            correct = Math.round(sc);
            total   = Math.round(tot);
        }

        if (pct === null) { return; }

        var isPass  = pct >= 70;
        var pctSafe = Math.min(100, Math.max(0, pct));
        var radius  = 82;
        var circ    = Math.round(2 * Math.PI * radius);
        var dash    = Math.round(pctSafe / 100 * circ);
        var fillColor = isPass ? '#4f922d' : '#b73434';

        /* Attempt bar */
        var attemptBarHtml = '';
        if (HDQ.attemptno || HDQ.attemptdate) {
            attemptBarHtml =
                '<div class="hdqz-attempt-bar">' +
                    (HDQ.attemptno   ? '<span class="hdqz-attempt-label">Attempt #' + HDQ.attemptno + '</span>' : '') +
                    (HDQ.attemptdate ? '<span class="hdqz-attempt-date">' + esc(HDQ.attemptdate) + '</span>' : '') +
                    '<span class="hdqz-pct-badge ' + (isPass ? 'hdqz-badge-pass' : 'hdqz-badge-fail') + '">' + pct + '%</span>' +
                '</div>';
        }

        /* Retake row */
        var retakeHtml = '';
        if (HDQ.canretake) {
            retakeHtml =
                '<div class="hdqz-retake-row">' +
                    '<a class="hdqz-retake-btn" href="' + HDQ.returnurl + '">' +
                        '<i class="fa fa-refresh" aria-hidden="true"></i> Retake Assessment' +
                    '</a>' +
                '</div>';
        }

        /* Score ring (SVG) — 192px gauge, centred. */
        var ringHtml =
            '<div class="hdqz-score-ring-wrap">' +
                '<svg class="hdqz-score-ring" viewBox="0 0 192 192" width="192" height="192" aria-hidden="true">' +
                    '<circle cx="96" cy="96" r="' + radius + '" fill="none" stroke="#e5e7eb" stroke-width="14"/>' +
                    '<circle cx="96" cy="96" r="' + radius + '" fill="none"' +
                        ' stroke="' + fillColor + '" stroke-width="14"' +
                        ' stroke-dasharray="' + dash + ' ' + (circ - dash) + '"' +
                        ' stroke-linecap="round"' +
                        ' transform="rotate(-90 96 96)"/>' +
                    '<text x="96" y="104" text-anchor="middle" dominant-baseline="middle"' +
                        ' font-size="40" font-weight="700" fill="' + fillColor + '">' + pct + '%</text>' +
                '</svg>' +
                (correct !== null && total !== null
                    ? '<p class="hdqz-score-count">' + correct + ' correct out of ' + total + ' questions</p>'
                    : '') +
            '</div>';

        var header = document.createElement('div');
        header.className = 'hdqz-review-header';
        header.innerHTML = attemptBarHtml + retakeHtml + ringHtml;

        var anchor = card.querySelector('#hdqzInstructionsToggle') ||
                     card.querySelector('.heyday-player-heading');
        if (anchor && anchor.parentNode === card) {
            anchor.parentNode.insertBefore(header, anchor.nextSibling);
        } else {
            card.insertBefore(header, card.firstChild);
        }
    }

    /* ── 10. Annotate review: colour-coded rows + result labels ─── */
    function annotateReview() {
        if (document.body.id !== 'page-mod-quiz-review') { return; }

        /* Correct questions: green row + ✓ icon + "Correct!" label */
        document.querySelectorAll('.que.correct').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqzReviewed) { return; }
            content.dataset.hdqzReviewed = '1';

            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                correctRow.classList.add('hdqz-ans-correct-row');
                var num = correctRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdqz-ans-check')) {
                    var chk = document.createElement('i');
                    chk.className = 'fa fa-check hdqz-ans-check';
                    chk.setAttribute('aria-hidden', 'true');
                    num.appendChild(chk);
                }
            }

            /* "Correct!" label below the answer block */
            var formulation = que.querySelector('.formulation');
            if (formulation && !formulation.querySelector('.hdqz-result-label')) {
                var lbl = document.createElement('div');
                lbl.className = 'hdqz-result-label hdqz-label-correct';
                lbl.innerHTML = '<i class="fa fa-check-circle" aria-hidden="true"></i> Correct!';
                formulation.appendChild(lbl);
            }
        });

        /* Incorrect / partially correct questions */
        document.querySelectorAll('.que.incorrect, .que.partiallycorrect').forEach(function(que) {
            var content = que.querySelector('.content');
            if (!content || content.dataset.hdqzReviewed) { return; }
            content.dataset.hdqzReviewed = '1';

            /* Red background on wrong selected answer row + ✕ icon */
            var wrongRow = que.querySelector('.answer .incorrect');
            if (wrongRow) {
                wrongRow.classList.add('hdqz-ans-wrong-row');
                var num = wrongRow.querySelector('.answernumber');
                if (num && !num.querySelector('.hdqz-ans-x')) {
                    var xEl = document.createElement('i');
                    xEl.className = 'fa fa-times hdqz-ans-x';
                    xEl.setAttribute('aria-hidden', 'true');
                    num.appendChild(xEl);
                }
            }

            /* Blue background on correct unselected answer + ✓ icon + "This was the correct answer." */
            var correctRow = que.querySelector('.answer .correct');
            if (correctRow) {
                correctRow.classList.add('hdqz-ans-right-unselected-row');
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

            /* "Incorrect." label below the answer block */
            var formulation = que.querySelector('.formulation');
            if (formulation && !formulation.querySelector('.hdqz-result-label')) {
                var lbl = document.createElement('div');
                lbl.className = 'hdqz-result-label hdqz-label-incorrect';
                lbl.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                formulation.appendChild(lbl);
            }

            /* Style the existing Moodle feedback panel red */
            var feedbackEl = que.querySelector(
                '.outcome .feedback, .outcome .specificfeedback, .outcome .generalfeedback, ' +
                '.feedback, .specificfeedback, .generalfeedback'
            );
            if (feedbackEl && !feedbackEl.querySelector('.hdqz-incorrect-prefix')) {
                feedbackEl.classList.add('hdqz-feedback-incorrect');
                var pfx = document.createElement('span');
                pfx.className = 'hdqz-incorrect-prefix';
                pfx.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect. ';
                feedbackEl.insertBefore(pfx, feedbackEl.firstChild);
            } else if (!feedbackEl) {
                var formulation2 = que.querySelector('.formulation');
                if (formulation2 && !formulation2.querySelector('.hdqz-fallback-incorrect')) {
                    var fb = document.createElement('div');
                    fb.className = 'hdqz-fallback-incorrect hdqz-feedback-incorrect';
                    fb.innerHTML = '<i class="fa fa-times-circle" aria-hidden="true"></i> Incorrect.';
                    formulation2.appendChild(fb);
                }
            }
        });
    }

    /* ── Main init ──────────────────────────────────────────────── */
    var inIframe  = (window.self !== window.top);
    var isAttempt = (document.body.id === 'page-mod-quiz-attempt');

    function init() {
        if (!inIframe) {
            buildTopnav();
            buildSidenav();   /* sidebar on all pages, including active attempt */
            fixLayout();      /* push #page right so sidebar never covers content */
            buildShell();
        }
        cleanQuiz();
        cleanLiveAttemptHighlights();
        improveButtons();
        if (!inIframe) {
            addNextUp();      /* Next Up shown on attempt page as well */
            addFooter();      /* Course Support / Cookie Settings / copyright */
            wireControls();
            showScore();
            annotateReview();
        }
        bypassSummary();
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
