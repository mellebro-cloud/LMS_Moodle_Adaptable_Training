<?php
// Admin settings for local_heyday_lessons.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_heyday_lessons', get_string('pluginname', 'local_heyday_lessons'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_heyday_lessons/settingsheading',
        get_string('settingsheading', 'local_heyday_lessons'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_heyday_lessons/enabled',
        get_string('enabled', 'local_heyday_lessons'),
        get_string('enabled_desc', 'local_heyday_lessons'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/accentcolor',
        get_string('accentcolor', 'local_heyday_lessons'),
        get_string('accentcolor_desc', 'local_heyday_lessons'),
        '#0073a8',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/sidebarwidth',
        get_string('sidebarwidth', 'local_heyday_lessons'),
        get_string('sidebarwidth_desc', 'local_heyday_lessons'),
        '356',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/topbarheight',
        get_string('topbarheight', 'local_heyday_lessons'),
        get_string('topbarheight_desc', 'local_heyday_lessons'),
        '42',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_heyday_lessons/hidebrand',
        get_string('hidebrand', 'local_heyday_lessons'),
        get_string('hidebrand_desc', 'local_heyday_lessons'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/supporturl',
        get_string('supporturl', 'local_heyday_lessons'),
        get_string('supporturl_desc', 'local_heyday_lessons'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/coursecodeprefix',
        get_string('coursecodeprefix', 'local_heyday_lessons'),
        get_string('coursecodeprefix_desc', 'local_heyday_lessons'),
        'Section:',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_lessons/pagebackground',
        get_string('pagebackground', 'local_heyday_lessons'),
        get_string('pagebackground_desc', 'local_heyday_lessons'),
        '#f4f5f7',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_heyday_lessons/menutitlemap',
        get_string('menutitlemap', 'local_heyday_lessons'),
        get_string('menutitlemap_desc', 'local_heyday_lessons'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_heyday_lessons/ignoredmenupatterns',
        get_string('ignoredmenupatterns', 'local_heyday_lessons'),
        get_string('ignoredmenupatterns_desc', 'local_heyday_lessons'),
        '',
        PARAM_RAW
    ));
}
