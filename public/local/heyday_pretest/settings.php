<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Settings placeholder for local_heyday_pretest.
 *
 * @package   local_heyday_pretest
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_heyday_pretest', get_string('pluginname', 'local_heyday_pretest'));
    $ADMIN->add('localplugins', $settings);
}
