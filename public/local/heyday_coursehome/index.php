<?php
// This file is part of Moodle - https://moodle.org/
//
// Local plugin: Heyday Course Home.
// URL: /local/heyday_coursehome/index.php?id=COURSEID

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:view', $context);

$PAGE->set_url(new moodle_url('/local/heyday_coursehome/index.php', array('id' => $course->id)));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('course');
$PAGE->set_title(format_string($course->fullname, true, array('context' => $context)));
$PAGE->set_heading(format_string($course->fullname, true, array('context' => $context)));
$PAGE->add_body_class('path-local-heyday_coursehome');
$PAGE->requires->css(new moodle_url('/local/heyday_coursehome/styles.css'));

/**
 * Get the first course overview image URL.
 *
 * @param stdClass $course
 * @param context_course $context
 * @return moodle_url|null
 */
function local_heyday_coursehome_get_course_image(stdClass $course, context_course $context) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder, id', false);

    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }

        $mimetype = $file->get_mimetype();
        if (strpos($mimetype, 'image/') !== 0) {
            continue;
        }

        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
    }

    return null;
}

/**
 * Return true when a completion state should count as complete.
 *
 * @param int $state
 * @return bool
 */
function local_heyday_coursehome_is_complete_state($state) {
    return in_array((int)$state, array(
        COMPLETION_COMPLETE,
        COMPLETION_COMPLETE_PASS,
        COMPLETION_COMPLETE_FAIL
    ), true);
}

/**
 * Return true for a visible activity that can be shown on the dashboard.
 *
 * @param cm_info $cm
 * @return bool
 */
function local_heyday_coursehome_is_dashboard_activity(cm_info $cm) {
    if (!$cm->uservisible) {
        return false;
    }

    if (empty($cm->url)) {
        return false;
    }

    if ($cm->modname === 'label') {
        return false;
    }

    return true;
}

/**
 * Return the display name of a course section.
 *
 * @param stdClass $course
 * @param course_modinfo $modinfo
 * @param int $sectionnum
 * @return string
 */
function local_heyday_coursehome_get_section_name(stdClass $course, course_modinfo $modinfo, $sectionnum) {
    try {
        $section = $modinfo->get_section_info((int)$sectionnum);
        if ($section) {
            return format_string(get_section_name($course, $section), true);
        }
    } catch (Exception $e) {
        // Fall through to a safe fallback.
    }

    if ((int)$sectionnum === 0) {
        return get_string('general');
    }

    return get_string('section') . ' ' . (int)$sectionnum;
}

/**
 * True when the section is a lesson section.
 *
 * @param string $sectionname
 * @return bool
 */
function local_heyday_coursehome_is_lesson_section($sectionname) {
    return (bool)preg_match('/^\s*lesson\s*\d+/i', (string)$sectionname);
}

/**
 * Calculate completion percentage for tracked, visible course modules.
 *
 * @param stdClass $course
 * @param int $userid
 * @return int
 */
function local_heyday_coursehome_get_completion_percentage(stdClass $course, $userid) {
    $completion = new completion_info($course);

    if (!$completion->is_enabled()) {
        return 0;
    }

    $modinfo = get_fast_modinfo($course, $userid);
    $total = 0;
    $complete = 0;

    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_coursehome_is_dashboard_activity($cm)) {
            continue;
        }

        if ((int)$cm->completion === COMPLETION_TRACKING_NONE) {
            continue;
        }

        $total++;
        $data = $completion->get_data($cm, false, $userid);

        if (local_heyday_coursehome_is_complete_state($data->completionstate)) {
            $complete++;
        }
    }

    if ($total === 0) {
        return 0;
    }

    return (int)round(($complete / $total) * 100);
}

/**
 * Calculate section completion percentage for visible tracked activities.
 *
 * @param stdClass $course
 * @param int $userid
 * @param int $sectionnum
 * @return int
 */
function local_heyday_coursehome_get_section_completion_percentage(stdClass $course, $userid, $sectionnum) {
    $completion = new completion_info($course);

    if (!$completion->is_enabled()) {
        return 0;
    }

    $modinfo = get_fast_modinfo($course, $userid);
    $total = 0;
    $complete = 0;

    foreach ($modinfo->get_cms() as $cm) {
        if ((int)$cm->sectionnum !== (int)$sectionnum) {
            continue;
        }

        if (!local_heyday_coursehome_is_dashboard_activity($cm)) {
            continue;
        }

        if ((int)$cm->completion === COMPLETION_TRACKING_NONE) {
            continue;
        }

        $total++;
        $data = $completion->get_data($cm, false, $userid);

        if (local_heyday_coursehome_is_complete_state($data->completionstate)) {
            $complete++;
        }
    }

    if ($total === 0) {
        return 0;
    }

    return (int)round(($complete / $total) * 100);
}

/**
 * Find the next visible incomplete lesson activity.
 * The ed2go dashboard card displays the active lesson and the next activity in that lesson.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array{sectiontitle:string,sectionprogress:int,nexttitle:string,url:moodle_url|null}|null
 */
function local_heyday_coursehome_get_lesson_dashboard_state(stdClass $course, $userid) {
    $completion = new completion_info($course);
    $modinfo = get_fast_modinfo($course, $userid);
    $fallbacklesson = null;
    $fallbackany = null;

    foreach ($modinfo->get_cms() as $cm) {
        if (!local_heyday_coursehome_is_dashboard_activity($cm)) {
            continue;
        }

        $sectionname = local_heyday_coursehome_get_section_name($course, $modinfo, $cm->sectionnum);
        $islesson = local_heyday_coursehome_is_lesson_section($sectionname);

        if ($fallbackany === null) {
            $fallbackany = array(
                'sectiontitle' => $sectionname,
                'sectionprogress' => local_heyday_coursehome_get_section_completion_percentage($course, $userid, $cm->sectionnum),
                'nexttitle' => format_string($cm->name),
                'url' => $cm->url
            );
        }

        if ($islesson && $fallbacklesson === null) {
            $fallbacklesson = array(
                'sectiontitle' => $sectionname,
                'sectionprogress' => local_heyday_coursehome_get_section_completion_percentage($course, $userid, $cm->sectionnum),
                'nexttitle' => format_string($cm->name),
                'url' => $cm->url
            );
        }

        if (!$completion->is_enabled()) {
            continue;
        }

        if ((int)$cm->completion === COMPLETION_TRACKING_NONE) {
            continue;
        }

        $data = $completion->get_data($cm, false, $userid);
        if (local_heyday_coursehome_is_complete_state($data->completionstate)) {
            continue;
        }

        if ($islesson) {
            return array(
                'sectiontitle' => $sectionname,
                'sectionprogress' => local_heyday_coursehome_get_section_completion_percentage($course, $userid, $cm->sectionnum),
                'nexttitle' => format_string($cm->name),
                'url' => $cm->url
            );
        }
    }

    if ($fallbacklesson !== null) {
        return $fallbacklesson;
    }

    return $fallbackany;
}

/**
 * Get course score/grade display for the current user.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array{display:string,percent:int|null}
 */
function local_heyday_coursehome_get_course_grade(stdClass $course, $userid) {
    $result = array(
        'display' => '--',
        'percent' => null
    );

    $gradeitem = grade_item::fetch_course_item($course->id);

    if (!$gradeitem) {
        return $result;
    }

    $gradegrade = grade_grade::fetch(array(
        'itemid' => $gradeitem->id,
        'userid' => $userid
    ));

    if (!$gradegrade || $gradegrade->finalgrade === null || $gradegrade->finalgrade === false || $gradegrade->finalgrade === '') {
        return $result;
    }

    $finalgrade = (float)$gradegrade->finalgrade;
    $display = grade_format_gradevalue($finalgrade, $gradeitem, true);

    $percent = null;
    if ((float)$gradeitem->grademax > (float)$gradeitem->grademin) {
        $percent = (($finalgrade - (float)$gradeitem->grademin) / ((float)$gradeitem->grademax - (float)$gradeitem->grademin)) * 100;
        $percent = max(0, min(100, (int)round($percent)));
    }

    $result['display'] = trim(strip_tags($display));
    $result['percent'] = $percent;

    return $result;
}

$fullname = format_string($course->fullname, true, array('context' => $context));
$shortname = format_string($course->shortname, true, array('context' => $context));
$courseimage = local_heyday_coursehome_get_course_image($course, $context);
$courseprogress = local_heyday_coursehome_get_completion_percentage($course, $USER->id);
$grade = local_heyday_coursehome_get_course_grade($course, $USER->id);
$dashboardstate = local_heyday_coursehome_get_lesson_dashboard_state($course, $USER->id);

if ($dashboardstate && !empty($dashboardstate['url'])) {
    $continueurl = $dashboardstate['url'];
    $cardtitle = $dashboardstate['sectiontitle'];
    $nexttitle = $dashboardstate['nexttitle'];
    $cardprogress = $dashboardstate['sectionprogress'];
} else {
    $continueurl = new moodle_url('/course/view.php', array('id' => $course->id));
    $cardtitle = get_string('nonextactivity', 'local_heyday_coursehome');
    $nexttitle = get_string('nonextactivity', 'local_heyday_coursehome');
    $cardprogress = $courseprogress;
}

$gradecirclevalue = $grade['percent'];
if ($gradecirclevalue === null) {
    $gradecirclevalue = 0;
}

$courseimageurl = $courseimage ? $courseimage->out(false) : '';
$gradesurl = new moodle_url('/grade/report/user/index.php', array('id' => $course->id));

$templatecontext = array(
    'fullname' => $fullname,
    'shortname' => $shortname,
    'courseimageurl' => $courseimageurl,
    'courseprogress' => $courseprogress,
    'cardprogress' => $cardprogress,
    'progressstyle' => '--value: ' . $courseprogress . ';',
    'gradestyle' => '--value: ' . $gradecirclevalue . ';',
    'gradedisplay' => $grade['display'],
    'gradeurl' => $gradesurl->out(false),
    'cardtitle' => $cardtitle,
    'nexttitle' => $nexttitle,
    'continueurl' => $continueurl->out(false),
    'hasimage' => !empty($courseimageurl)
);

echo $OUTPUT->header();
?>

<div class="hch-wrapper" id="heyday-course-home">
    <div class="hch-hero">
        <?php if ($templatecontext['hasimage']) : ?>
            <div class="hch-hero-image" style="background-image: url('<?php echo s($templatecontext['courseimageurl']); ?>');"></div>
        <?php else : ?>
            <div class="hch-hero-fallback"></div>
        <?php endif; ?>

        <div class="hch-hero-title">
            <h1><?php echo $templatecontext['fullname']; ?></h1>
            <div class="hch-shortname">
                <?php echo get_string('section'); ?>: <?php echo $templatecontext['shortname']; ?>
            </div>
        </div>

        <div class="hch-hero-cut" aria-hidden="true"></div>

        <div class="hch-meters" aria-label="Course status">
            <div class="hch-circle" style="<?php echo s($templatecontext['progressstyle']); ?>">
                <div class="hch-circle-inner">
                    <span>
                        <span class="hch-circle-main"><?php echo (int)$templatecontext['courseprogress']; ?>%</span>
                        <span class="hch-circle-label"><?php echo get_string('complete', 'local_heyday_coursehome'); ?></span>
                    </span>
                </div>
            </div>

            <a class="hch-circle hch-score-circle" style="<?php echo s($templatecontext['gradestyle']); ?>" href="<?php echo s($templatecontext['gradeurl']); ?>" title="Open score" aria-label="Open score">
                <span class="hch-circle-inner">
                    <span>
                        <span class="hch-circle-main"><?php echo s($templatecontext['gradedisplay']); ?></span>
                        <span class="hch-circle-label"><?php echo get_string('score', 'local_heyday_coursehome'); ?></span>
                    </span>
                </span>
            </a>
        </div>
    </div>

    <main class="hch-main">
        <h2><?php echo get_string('welcome', 'local_heyday_coursehome'); ?></h2>

        <section class="hch-welcome-card" aria-label="Continue course">
            <div class="hch-progress-panel">
                <h3><?php echo $templatecontext['cardtitle']; ?></h3>

                <div class="hch-progress-row">
                    <div class="hch-progress-track" aria-hidden="true">
                        <div class="hch-progress-fill" style="width: <?php echo (int)$templatecontext['cardprogress']; ?>%;"></div>
                    </div>
                    <div class="hch-progress-label">
                        <?php echo (int)$templatecontext['cardprogress']; ?>% <?php echo get_string('complete', 'local_heyday_coursehome'); ?>
                    </div>
                </div>
            </div>

            <aside class="hch-next-panel">
                <div class="hch-next-label"><?php echo get_string('nextactivity', 'local_heyday_coursehome'); ?></div>
                <div class="hch-next-title"><?php echo $templatecontext['nexttitle']; ?></div>
                <a class="hch-continue-button" href="<?php echo s($templatecontext['continueurl']); ?>">
                    <?php echo get_string('continue', 'local_heyday_coursehome'); ?>
                </a>
            </aside>
        </section>

        <div class="hch-footer-links" aria-hidden="true">
            <span>Course Support</span>
            <span>|</span>
            <span>Cookie Settings</span>
        </div>
    </main>
</div>

<?php
echo $OUTPUT->footer();
