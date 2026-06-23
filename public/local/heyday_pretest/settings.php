<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Admin settings for local_heyday_pretest.
 *
 * @package    local_heyday_pretest
 * @copyright  2026 Heyday LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_heyday_pretest', get_string('pluginname', 'local_heyday_pretest'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_heyday_pretest/settingsheading',
        get_string('settingsheading', 'local_heyday_pretest'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_pretest/quiznamematch',
        get_string('quiznamematch', 'local_heyday_pretest'),
        get_string('quiznamematch_desc', 'local_heyday_pretest'),
        'Pretest',
        PARAM_TEXT
    ));
}
