<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin settings for local_pretestautostart.
 *
 * @package    local_pretestautostart
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_pretestautostart',
        get_string('pluginname', 'local_pretestautostart')
    );

    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtextarea(
        'local_pretestautostart/cmids',
        get_string('cmids', 'local_pretestautostart'),
        get_string('cmids_desc', 'local_pretestautostart'),
        '',
        PARAM_TEXT,
        60,
        6
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_pretestautostart/autopreview',
        get_string('autopreview', 'local_pretestautostart'),
        get_string('autopreview_desc', 'local_pretestautostart'),
        0
    ));
}
