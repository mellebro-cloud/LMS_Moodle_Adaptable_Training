<?php
// Local plugin: Heyday Pretest landing page.
// Moodle 5.0.6+ / Adaptable 500.2.6.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

$PAGE->set_url(new moodle_url('/local/heyday_pretest/view.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title('Pretest');
$PAGE->set_heading('');
$PAGE->add_body_class('heyday-pretest-shell');

if (isset($PAGE->activityheader) && method_exists($PAGE->activityheader, 'disable')) {
    $PAGE->activityheader->disable();
}

function local_heyday_pretest_view_cm_url($cm): moodle_url {
    if (!empty($cm->url)) {
        return $cm->url;
    }

    return new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
}

function local_heyday_pretest_view_display_type($cm): string {
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

function local_heyday_pretest_view_find_l1_learning_objectives($course, int $currentcmid = 0) {
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

// Button logic.
$inprogressrecords = $DB->get_records_sql(
    "SELECT *
       FROM {quiz_attempts}
      WHERE quiz = :quizid
        AND userid = :userid
        AND state = :state
        AND preview = 0
   ORDER BY timemodified DESC, attempt DESC",
    [
        'quizid' => $quiz->id,
        'userid' => $USER->id,
        'state' => 'inprogress',
    ],
    0,
    1
);

$inprogressattempt = reset($inprogressrecords);
if (!$inprogressattempt) {
    $inprogressattempt = null;
}

$finishedrecords = $DB->get_records_sql(
    "SELECT *
       FROM {quiz_attempts}
      WHERE quiz = :quizid
        AND userid = :userid
        AND state = :state
        AND preview = 0
   ORDER BY timemodified DESC, attempt DESC",
    [
        'quizid' => $quiz->id,
        'userid' => $USER->id,
        'state' => 'finished',
    ],
    0,
    1
);

$finishedattempt = reset($finishedrecords);
if (!$finishedattempt) {
    $finishedattempt = null;
}

$buttontext = 'Start';
$buttonurl = new moodle_url('/mod/quiz/startattempt.php', [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
]);

if ($inprogressattempt) {
    $buttontext = 'Resume';
    $buttonurl = new moodle_url('/mod/quiz/attempt.php', [
        'attempt' => $inprogressattempt->id,
        'cmid' => $cm->id,
    ]);
} else if ($finishedattempt) {
    $buttontext = 'Review Results';
    $buttonurl = new moodle_url('/mod/quiz/review.php', [
        'attempt' => $finishedattempt->id,
    ]);
}

// Next Up card.
$nextcm = local_heyday_pretest_view_find_l1_learning_objectives($course, (int)$cm->id);

if ($nextcm) {
    $nexturl = local_heyday_pretest_view_cm_url($nextcm);
    $nextname = format_string($nextcm->name);
    $nexttype = local_heyday_pretest_view_display_type($nextcm);
    $nextsection = 'Lesson 1';
} else {
    $nexturl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $nextname = 'Learning Objectives';
    $nexttype = 'activity';
    $nextsection = 'Lesson 1';
}

$courseheading = format_string($course->fullname);
$quiztitle = 'Pretest';
$skipurl = $nexturl;

echo $OUTPUT->header();
?>

<style>
body.heyday-pretest-shell {
    background: #f4f6f8 !important;
}

body.heyday-pretest-shell #page-header,
body.heyday-pretest-shell .page-header-headings,
body.heyday-pretest-shell .page-context-header,
body.heyday-pretest-shell #page-navbar,
body.heyday-pretest-shell .breadcrumb,
body.heyday-pretest-shell .breadcrumb-nav,
body.heyday-pretest-shell .secondary-navigation,
body.heyday-pretest-shell .activity-header,
body.heyday-pretest-shell .activity-navigation,
body.heyday-pretest-shell .moodle-activity-navigation,
body.heyday-pretest-shell .prevnext,
body.heyday-pretest-shell .activityprev,
body.heyday-pretest-shell .activitynext,
body.heyday-pretest-shell .activity-nav,
body.heyday-pretest-shell .navguide,
body.heyday-pretest-shell .urlselect,
body.heyday-pretest-shell .jumpmenu,
body.heyday-pretest-shell select[name="jump"],
body.heyday-pretest-shell #page-footer,
body.heyday-pretest-shell footer,
body.heyday-pretest-shell .homelink,
body.heyday-pretest-shell .helplink,
body.heyday-pretest-shell .footer-popover,
body.heyday-pretest-shell .footer-content-popover,
body.heyday-pretest-shell .block-region,
body.heyday-pretest-shell .block-add,
body.heyday-pretest-shell .block-controls,
body.heyday-pretest-shell [data-region="blocks-column"],
body.heyday-pretest-shell .block-region-side-post,
body.heyday-pretest-shell .block-region-side-pre {
    display: none !important;
}

body.heyday-pretest-shell #region-main {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    max-width: 920px !important;
    margin: 0 auto !important;
    padding: 0 !important;
}

.hd-pretest-page {
    max-width: 920px;
    margin: 26px auto 80px auto;
}

.hd-pretest-card {
    background: #fff;
    border: 1px solid #d6dce2;
    min-height: 420px;
    padding: 34px 32px 30px 32px;
}

.hd-pretest-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.hd-pretest-topbar-left,
.hd-pretest-topbar-right {
    display: flex;
    gap: 16px;
    align-items: center;
}

.hd-icon-btn {
    border: 0;
    background: transparent;
    color: #0074ad;
    font-size: 22px;
    line-height: 1;
    padding: 0;
    cursor: pointer;
    text-decoration: none;
}

.hd-icon-btn:hover {
    color: #004f76;
    text-decoration: none;
}

.hd-pretest-course-title {
    text-align: center;
    font-size: 13px;
    color: #666;
    margin-top: -14px;
    margin-bottom: 6px;
}

.hd-pretest-title {
    text-align: center;
    font-size: 26px;
    font-weight: 400;
    margin: 0 0 24px 0;
    color: #111;
}

.hd-print-wrapper {
    position: relative;
}

.hd-print-menu {
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

.hd-print-menu.is-open {
    display: block;
}

.hd-print-menu button {
    display: block;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    background: #fff;
    text-align: left;
    font-size: 13px;
    cursor: pointer;
}

.hd-print-menu button:hover {
    background: #f2f2f2;
}

.hd-instructions-toggle-wrap {
    margin: 0 0 18px 0;
}

.hd-instructions-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 6px 12px;
    background: #fff;
    border: 1px solid #a9cfe5;
    border-radius: 4px;
    color: #006fae;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
}

.hd-instructions-toggle:hover,
.hd-instructions-toggle:focus {
    background: #f5fbff;
    color: #005d8c;
    outline: none;
}

.hd-instructions-panel {
    display: block;
}

.hd-instructions-panel.is-hidden {
    display: none;
}

.hd-pretest-body {
    font-size: 14px;
    line-height: 1.45;
    color: #111;
}

.hd-pretest-body p {
    margin: 0 0 16px 0;
}

.hd-pretest-rules {
    margin-top: 18px;
    border: 1px solid #d7dce0;
    border-radius: 4px;
    padding: 14px 18px;
    background: #fff;
}

.hd-pretest-rules ul {
    margin-bottom: 0;
}

.hd-pretest-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 18px;
    margin-top: 28px;
}

.hd-skip-link {
    color: #006fae;
    text-decoration: underline;
}

.hd-primary-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    min-height: 42px;
    padding: 9px 18px;
    background: #3f8b2b;
    border-radius: 4px;
    color: #fff !important;
    font-weight: 700;
    text-decoration: none !important;
}

.hd-primary-btn:hover {
    background: #327121;
}

.hd-end-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 34px auto 18px auto;
    color: #333;
    font-size: 14px;
}

.hd-end-separator span {
    width: 70px;
    height: 1px;
    background: #9aa8b1;
}

.hd-next-card {
    display: flex;
    width: 300px;
    margin: 0 auto 40px auto;
    text-decoration: none !important;
    color: inherit !important;
}

.hd-next-label {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 82px;
    background: #0077a6;
    color: #fff;
    font-weight: 700;
    min-height: 64px;
}

.hd-next-content {
    flex: 1;
    border: 1px solid #d7d7d7;
    border-left: 0;
    background: #fff;
    padding: 10px 12px;
    font-size: 12px;
}

.hd-next-section {
    display: block;
    color: #777;
}

.hd-next-title {
    display: block;
    color: #006fae;
    text-decoration: underline;
    font-size: 13px;
}

.hd-next-type {
    display: block;
    color: #444;
}
</style>

<div class="hd-pretest-page" id="hd-pretest-page">

    <section class="hd-pretest-card" id="hd-pretest-card">

        <div class="hd-pretest-topbar">
            <div class="hd-pretest-topbar-left">
                <a href="javascript:history.back();" class="hd-icon-btn" aria-label="Back">
                    <i class="fa fa-arrow-left" aria-hidden="true"></i>
                </a>

                <button type="button" class="hd-icon-btn hd-bookmark-btn" id="hdBookmarkBtn" aria-label="Bookmark">
                    <i class="fa fa-bookmark-o" aria-hidden="true"></i>
                </button>
            </div>

            <div class="hd-pretest-topbar-right">
                <div class="hd-print-wrapper">
                    <button type="button" class="hd-icon-btn" id="hdPrintBtn" aria-label="Print">
                        <i class="fa fa-print" aria-hidden="true"></i>
                    </button>

                    <div class="hd-print-menu" id="hdPrintMenu">
                        <button type="button" id="hdPrintActivity">Print/Save activity</button>
                        <button type="button" id="hdPrintLesson">Print/Save entire lesson</button>
                    </div>
                </div>

                <button type="button" class="hd-icon-btn" id="hdFullscreenBtn" aria-label="Fullscreen">
                    <i class="fa fa-expand" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="hd-pretest-course-title">
            <?php echo s($courseheading); ?>
        </div>

        <h1 class="hd-pretest-title">
            <?php echo s($quiztitle); ?>
        </h1>

        <div class="hd-instructions-toggle-wrap">
            <button type="button" class="hd-instructions-toggle" id="hdInstructionsToggle" aria-expanded="true">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <span>Show / Hide Instructions</span>
            </button>
        </div>

        <div class="hd-pretest-body hd-instructions-panel" id="hdInstructionsPanel">

            <p>
                This pretest is optional, and it's meant to help you gauge how much you already know about the subject matter of this course.
            </p>

            <p>
                As you go through the pretest, you'll be able to save your answer choices and change them up until you submit your pretest for a score.
                To exit the pretest, click the <strong>Save and Close</strong> button at the bottom of the page.
                To submit the pretest, click the <strong>Submit</strong> button at the bottom of the page.
                Once you click Submit you will be asked to confirm you are ready to submit the pretest.
                Upon clicking Submit, you will be presented with your score for the pretest.
            </p>

            <div class="hd-pretest-rules">
                <ul>
                    <li>You have one attempt.
                        <ul>
                            <li>Your grade is determined by your only attempt.</li>
                            <li>This is not for credit and does not affect your overall grade.</li>
                        </ul>
                    </li>
                </ul>
            </div>

        </div>

        <div class="hd-pretest-actions">
            <a class="hd-skip-link" href="<?php echo $skipurl->out(false); ?>">Skip It</a>

            <a class="hd-primary-btn" href="<?php echo $buttonurl->out(false); ?>">
                <?php echo s($buttontext); ?>
            </a>
        </div>

    </section>

    <div class="hd-end-separator">
        <span></span>
        <strong>End of Pretest</strong>
        <span></span>
    </div>

    <a class="hd-next-card" href="<?php echo $nexturl->out(false); ?>">
        <span class="hd-next-label">Next Up</span>
        <span class="hd-next-content">
            <span class="hd-next-section"><?php echo s($nextsection); ?></span>
            <span class="hd-next-title"><?php echo s($nextname); ?></span>
            <span class="hd-next-type"><?php echo s($nexttype); ?></span>
        </span>
    </a>

</div>

<script>
(function() {
    function hideExtraMoodlePretestHeading() {
        document.querySelectorAll('h1, h2, .page-header-headings, .page-context-header').forEach(function(el) {
            const text = (el.textContent || '').trim();

            if (text === 'Pretest' && !el.closest('.hd-pretest-card')) {
                el.style.display = 'none';
            }
        });

        document.querySelectorAll(
            '#page-header, #page-navbar, .breadcrumb, .breadcrumb-nav, .secondary-navigation, .activity-header, ' +
            '.activity-navigation, .moodle-activity-navigation, .prevnext, .activityprev, .activitynext, ' +
            '.activity-nav, .navguide, .urlselect, .jumpmenu, select[name="jump"], ' +
            '#page-footer, footer, .homelink, .helplink, .footer-popover, .footer-content-popover'
        ).forEach(function(el) {
            el.style.display = 'none';
        });
    }

    hideExtraMoodlePretestHeading();
    document.addEventListener('DOMContentLoaded', hideExtraMoodlePretestHeading);
    setTimeout(hideExtraMoodlePretestHeading, 300);
    setTimeout(hideExtraMoodlePretestHeading, 1000);

    const instructionsToggle = document.getElementById('hdInstructionsToggle');
    const instructionsPanel = document.getElementById('hdInstructionsPanel');

    if (instructionsToggle && instructionsPanel) {
        instructionsToggle.addEventListener('click', function() {
            const isHidden = instructionsPanel.classList.toggle('is-hidden');
            instructionsToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        });
    }

    const bookmarkBtn = document.getElementById('hdBookmarkBtn');
    const fullscreenBtn = document.getElementById('hdFullscreenBtn');
    const printBtn = document.getElementById('hdPrintBtn');
    const printMenu = document.getElementById('hdPrintMenu');
    const printActivity = document.getElementById('hdPrintActivity');
    const printLesson = document.getElementById('hdPrintLesson');
    const pretestPage = document.getElementById('hd-pretest-page');

    if (bookmarkBtn) {
        bookmarkBtn.addEventListener('click', function() {
            bookmarkBtn.classList.toggle('is-active');

            const icon = bookmarkBtn.querySelector('i');

            if (icon) {
                icon.classList.toggle('fa-bookmark-o');
                icon.classList.toggle('fa-bookmark');
            }
        });
    }

    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                if (pretestPage && pretestPage.requestFullscreen) {
                    pretestPage.requestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        });
    }

    if (printBtn && printMenu) {
        printBtn.addEventListener('click', function(event) {
            event.stopPropagation();
            printMenu.classList.toggle('is-open');
        });

        document.addEventListener('click', function() {
            printMenu.classList.remove('is-open');
        });

        printMenu.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }

    if (printActivity) {
        printActivity.addEventListener('click', function() {
            document.body.classList.add('hd-print-activity');
            window.print();

            setTimeout(function() {
                document.body.classList.remove('hd-print-activity');
            }, 500);
        });
    }

    if (printLesson) {
        printLesson.addEventListener('click', function() {
            window.print();
        });
    }
})();
</script>

<?php
echo $OUTPUT->footer();