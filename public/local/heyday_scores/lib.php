<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add a Scores link to the course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_heyday_scores_extend_navigation_course($navigation, stdClass $course, context_course $context) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/heyday_scores:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/heyday_scores/index.php', ['id' => $course->id]);

    $navigation->add(
        get_string('scores', 'local_heyday_scores'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'heyday_scores',
        new pix_icon('i/grades', '')
    );
}
