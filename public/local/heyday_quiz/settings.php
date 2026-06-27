<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Admin settings for local_heyday_quiz.
 *
 * @package   local_heyday_quiz
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_heyday_quiz', get_string('pluginname', 'local_heyday_quiz'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_heyday_quiz/autoopenattempt',
        get_string('autoopenattempt', 'local_heyday_quiz'),
        get_string('autoopenattempt_desc', 'local_heyday_quiz'),
        0
    ));
}
