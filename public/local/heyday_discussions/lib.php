<?php
// Navigation callback for Heyday Discussions.

defined('MOODLE_INTERNAL') || die();

/**
 * Add Discussions link to course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_heyday_discussions_extend_navigation_course($navigation, stdClass $course, context_course $context) {
    global $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/heyday_discussions:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/heyday_discussions/index.php', ['id' => $course->id]);

    $navigation->add(
        get_string('discussions', 'local_heyday_discussions'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'heyday_discussions',
        new pix_icon('t/message', '')
    );

    if (!empty($course->id) && (int)$course->id > SITEID) {
        $PAGE->requires->js_call_amd('local_heyday_discussions/section_redirect', 'init', [
            (int)$course->id
        ]);
    }
}