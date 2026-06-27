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

    // Player pretest URL — used by Save and Close when the quiz is inside the player iframe.
    $playerurl = (new moodle_url('/local/heyday_courseplayer/index.php', [
        'id'   => $course->id,
        'page' => 'pretest',
    ]))->out(false);

    $coursejson     = json_encode($coursefullname, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $quizjson       = json_encode($quizname,       JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $viewurljson    = json_encode($viewurl,         JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $playerurljson  = json_encode($playerurl,       JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nexturljson    = json_encode($nexturl,         JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nextnamejson   = json_encode($nextname,        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nexttypejson   = json_encode($nexttype,        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $nextsectionjson = json_encode($nextsection,    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    echo <<<HTML
<style>
body.path-mod-quiz.heyday-pretest-core-page {
    background: #f4f6f8 !important;
}

/* Suppress horizontal scroll inside the iframe — the player outer page scrolls instead */
body.heyday-pretest-core-page,
body.heyday-pretest-core-page #page,
body.heyday-pretest-core-page #page-wrapper,
body.heyday-pretest-core-page .main-inner,
body.heyday-pretest-core-page #region-main-box,
body.heyday-pretest-core-page #region-main {
    overflow-x: hidden !important;
    max-width: 100% !important;
}

/* Constrain wide media / tables so they don't break out of the quiz container */
body.heyday-pretest-core-page img,
body.heyday-pretest-core-page table,
body.heyday-pretest-core-page pre,
body.heyday-pretest-core-page video,
body.heyday-pretest-core-page canvas {
    max-width: 100% !important;
    box-sizing: border-box !important;
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
body.heyday-pretest-core-page .mod_quiz-prev-nav,
body.heyday-pretest-core-page header.navbar,
body.heyday-pretest-core-page nav.navbar,
body.heyday-pretest-core-page .fixed-top,
body.heyday-pretest-core-page [data-region="drawers"],
body.heyday-pretest-core-page .drawers,
body.heyday-pretest-core-page .drawer,
body.heyday-pretest-core-page .drawer-left,
body.heyday-pretest-core-page .drawer-right,
body.heyday-pretest-core-page [data-block="quiz_navblock"],
body.heyday-pretest-core-page .block_quiz_navblock,
body.heyday-pretest-core-page .qn_buttons,
body.heyday-pretest-core-page .questionflagpostfix,
body.heyday-pretest-core-page .othernav,
body.heyday-pretest-core-page #mod_quiz_navblock,
body.heyday-pretest-core-page .quiznavblock {
    display: none !important;
}

/* Remove top padding Adaptable adds for its fixed navbar */
body.heyday-pretest-core-page #page,
body.heyday-pretest-core-page #page-wrapper,
body.heyday-pretest-core-page .main-inner {
    padding-top: 0 !important;
    margin-top: 0 !important;
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

body.heyday-pretest-core-page .que {
    display: flex !important;
    gap: 14px !important;
    align-items: flex-start !important;
}

body.heyday-pretest-core-page .que .info {
    float: none !important;
    flex-shrink: 0 !important;
    width: 36px !important;
    position: static !important;
    background: transparent !important;
    border: 0 !important;
    padding: 0 !important;
    margin: 2px 0 0 0 !important;
}

body.heyday-pretest-core-page .que .info .no {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px !important;
    height: 36px !important;
    border-radius: 50% !important;
    background: #6f7c85 !important;
    color: #fff !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    padding: 0 !important;
}

body.heyday-pretest-core-page .que.correct .info .no {
    background: #3f8b2b !important;
}

body.heyday-pretest-core-page .que.incorrect .info .no,
body.heyday-pretest-core-page .que.partiallycorrect .info .no {
    background: #b73434 !important;
}

body.heyday-pretest-core-page .que .content {
    flex: 1 !important;
    min-width: 0 !important;
    margin-left: 0 !important;
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

/* ── fieldset.ablock (wraps the answer list) ─────────────────────────────── */
/* Reset browser fieldset defaults: border, padding, min-width:min-content.   */
body.heyday-pretest-core-page .ablock {
    border: 0 !important;
    padding: 0 !important;
    margin: 8px 0 0 0 !important;
    min-width: 0 !important;
    overflow: visible !important;
}

body.heyday-pretest-core-page .ablock > legend,
body.heyday-pretest-core-page .ablock .prompt {
    display: none !important;
}

/* ── Answer list ─────────────────────────────────────────────────────────── */
body.heyday-pretest-core-page .answer {
    margin: 0 !important;
}

/*
 * Moodle 5.2 multichoice row (from renderer.php):
 *
 *   div.r0                               ← answer row
 *     input[type=radio]                  ← flex child 1
 *     div.d-flex.w-auto                  ← flex child 2  [data-region="answer-label"]
 *       span.answernumber                ← "a." letter badge
 *       div.flex-fill.ms-1              ← answer text  (may contain <p>)
 *
 * Strategy:
 *   - Row: align-items:stretch  → children fill full row height
 *   - Radio: align-self:center  → stays vertically centred in the stretched row
 *   - [data-region="answer-label"]: align-items:stretch → answernumber fills height
 *   - .answernumber: align-self:stretch → gray badge spans full row
 *   - .flex-fill: flex-direction:column + justify-content:center → <p> stacks vertically, centred
 */
body.heyday-pretest-core-page .answer > div,
body.heyday-pretest-core-page .answer .r0,
body.heyday-pretest-core-page .answer .r1 {
    display: flex !important;
    flex-direction: row !important;
    align-items: stretch !important;
    min-height: 44px !important;
    margin: 5px 0 !important;
    padding: 0 !important;
    background: #f5f7f9 !important;
    border: 1px solid #dde2e7 !important;
    border-radius: 3px !important;
    overflow: hidden !important;      /* clips .answernumber to border-radius */
    cursor: pointer !important;
    text-align: left !important;
    transition: background 0.1s, border-color 0.1s !important;
    /* Reset anything Bootstrap/Moodle may have set */
    list-style: none !important;
    box-shadow: none !important;
}

body.heyday-pretest-core-page .answer > div:hover,
body.heyday-pretest-core-page .answer .r0:hover,
body.heyday-pretest-core-page .answer .r1:hover {
    background: #e4edf6 !important;
    border-color: #9bbdd4 !important;
}

body.heyday-pretest-core-page .answer > div:has(input:checked),
body.heyday-pretest-core-page .answer .r0:has(input:checked),
body.heyday-pretest-core-page .answer .r1:has(input:checked) {
    background: #d8ecfb !important;
    border-color: #006fae !important;
    border-left-width: 4px !important;
}

/* ── Radio / checkbox ────────────────────────────────────────────────────── */
body.heyday-pretest-core-page .answer input[type="radio"],
body.heyday-pretest-core-page .answer input[type="checkbox"] {
    /* Force native appearance — Bootstrap/Adaptable may override it */
    -webkit-appearance: auto !important;
    appearance: auto !important;
    display: block !important;
    opacity: 1 !important;
    position: static !important;
    visibility: visible !important;
    /* Sizing and position within the row */
    flex-shrink: 0 !important;
    align-self: center !important;
    width: 16px !important;
    height: 16px !important;
    margin: 0 10px 0 14px !important;
    padding: 0 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
    /* No interference from theme */
    border: revert !important;
    background: revert !important;
    accent-color: #006fae !important;
}

/* ── Label wrapper div ───────────────────────────────────────────────────── */
body.heyday-pretest-core-page .answer [data-region="answer-label"] {
    display: flex !important;
    flex-direction: row !important;
    align-items: stretch !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    width: auto !important;           /* override Bootstrap w-auto */
}

/* ── Letter badge (a / b / c / d) ───────────────────────────────────────── */
body.heyday-pretest-core-page .answer .answernumber {
    display: flex !important;
    align-self: stretch !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    width: 40px !important;
    min-width: 40px !important;
    padding: 0 !important;
    margin: 0 !important;
    background: #d7dde1 !important;
    color: #444 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    border-right: 1px solid #c2c9cf !important;
}

/* ── Answer text (div.flex-fill.ms-1) ───────────────────────────────────── */
/*
 * Use display:block (not flex) so the <p> inside remains a normal block element
 * that fills 100% of the available width and wraps naturally. Making .flex-fill
 * a flex container (display:flex + flex-direction:column) caused the <p> to
 * be treated as a flex item with align-items:flex-start, which broke text
 * wrapping for long answers (Q2, Q3, Q4, Q6, Q8).
 *
 * flex:1 1 auto stays so this element still grows within [data-region="answer-label"].
 * padding-top/bottom of 11px centres single-line text in the 44px min-height row.
 */
body.heyday-pretest-core-page .answer .flex-fill {
    display: block !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    padding: 11px 14px 11px 10px !important;
    margin: 0 !important;
    font-size: 14px !important;
    color: #1a1a1a !important;
    line-height: 1.45 !important;
}

/* Strip <p> margins and reset display — they inflate row height */
body.heyday-pretest-core-page .answer .flex-fill p,
body.heyday-pretest-core-page .answer .flex-fill p:last-child {
    display: block !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    color: inherit !important;
    line-height: inherit !important;
}

/* ── Fallback: plain <label> for older question types ────────────────────── */
body.heyday-pretest-core-page .answer label {
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: flex-start !important;
    flex: 1 !important;
    min-height: 44px !important;
    margin: 0 !important;
    padding: 8px 14px !important;
    color: #1a1a1a !important;
    font-size: 14px !important;
    line-height: 1.45 !important;
    cursor: pointer !important;
}

/* ── Review: correct / incorrect row colouring ───────────────────────────── */
/* Target .answernumber + .flex-fill (Moodle 5.2) as well as label (fallback) */
body.heyday-pretest-core-page .que.correct .answer .correct,
body.heyday-pretest-core-page .que.correct .answer .correct .flex-fill,
body.heyday-pretest-core-page .que.correct .answer .correct .answernumber,
body.heyday-pretest-core-page .que.correct .answer .correct label {
    background: #3f8b2b !important;
    color: #fff !important;
}

body.heyday-pretest-core-page .que.incorrect .answer .incorrect,
body.heyday-pretest-core-page .que.incorrect .answer .incorrect .flex-fill,
body.heyday-pretest-core-page .que.incorrect .answer .incorrect .answernumber,
body.heyday-pretest-core-page .que.incorrect .answer .incorrect label,
body.heyday-pretest-core-page .que.partiallycorrect .answer .incorrect,
body.heyday-pretest-core-page .que.partiallycorrect .answer .incorrect .flex-fill,
body.heyday-pretest-core-page .que.partiallycorrect .answer .incorrect .answernumber,
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
    justify-content: space-between !important;
    align-items: center !important;
    gap: 10px !important;
    margin-top: 32px !important;
    padding-top: 20px !important;
    border-top: 1px solid #e0e5ea !important;
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
    display: inline-flex !important;
    align-items: center !important;
    background: #fff !important;
    border: 1px solid #8a9baa !important;
    border-radius: 3px !important;
    color: #222 !important;
    padding: 8px 18px !important;
    font-size: 14px !important;
    font-weight: 400 !important;
    text-decoration: none !important;
    cursor: pointer !important;
}

body.heyday-pretest-core-page .hd-save-close:hover {
    background: #f4f6f8 !important;
    border-color: #555 !important;
    color: #111 !important;
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

    var courseName  = {$coursejson};
    var quizName    = {$quizjson};
    var viewUrl     = {$viewurljson};
    var playerUrl   = {$playerurljson};
    var nextUrl     = {$nexturljson};
    var nextName    = {$nextnamejson};
    var nextType    = {$nexttypejson};
    var nextSection = {$nextsectionjson};
    // When running inside the player iframe, navigate the top frame.
    var inIframe = (window !== window.top);
    var closeUrl = inIframe ? playerUrl : viewUrl;

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
            '.footer-content-popover',
            /* Adaptable / Moodle 5.x */
            'header.navbar',
            'nav.navbar',
            '.fixed-top',
            '[data-region="drawers"]',
            '.drawers',
            '.drawer',
            '.drawer-left',
            '.drawer-right',
            '[data-block="quiz_navblock"]',
            '.block_quiz_navblock',
            '.qn_buttons',
            '#mod_quiz_navblock',
            '.quiznavblock',
            '.questionflagpostfix',
            '.othernav',
            '.tertiary-navigation',
            '.quizattemptcounts',
            '.quizattemptsummary',
            '.quizreviewsummary',
            '.mod_quiz-prev-nav'
        ];

        selectors.forEach(function(selector) {
            document.querySelectorAll(selector).forEach(function(el) {
                el.style.setProperty('display', 'none', 'important');
            });
        });

        /* Remove top padding Adaptable adds for fixed navbar */
        var pageEl = document.getElementById('page') || document.getElementById('page-wrapper');
        if (pageEl) {
            pageEl.style.setProperty('padding-top', '0', 'important');
            pageEl.style.setProperty('margin-top', '0', 'important');
        }
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

        // Clean blank <p> tags from answers AND question text (Q11/Q12 etc.)
        var cleanEmptyP = function(container) {
            container.querySelectorAll('p').forEach(function(p) {
                var t = (p.textContent || '').replace(/ /g, '').trim();
                if (t === '') { p.parentNode && p.parentNode.removeChild(p); }
            });
        };
        document.querySelectorAll('.answer .flex-fill').forEach(cleanEmptyP);
        document.querySelectorAll('.qtext').forEach(cleanEmptyP);
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
            saveClose.href = closeUrl;
            saveClose.className = 'hd-save-close';
            saveClose.textContent = 'Save and Close';
            saveClose.addEventListener('click', function(e) {
                if (inIframe) {
                    e.preventDefault();
                    window.top.location.href = playerUrl;
                }
            });
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
        if (inIframe) { next.target = '_top'; }
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