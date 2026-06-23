<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Admin settings for local_heyday_courseplayer.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_heyday_courseplayer', get_string('pluginname', 'local_heyday_courseplayer'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_heyday_courseplayer/appearanceheading',
        get_string('appearanceheading', 'local_heyday_courseplayer'),
        get_string('appearanceheading_desc', 'local_heyday_courseplayer')
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/brandname',
        get_string('brandname', 'local_heyday_courseplayer'),
        get_string('brandname_desc', 'local_heyday_courseplayer'),
        'Heyday Training LMS',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_heyday_courseplayer/showtopbarbrand',
        get_string('showtopbarbrand', 'local_heyday_courseplayer'),
        get_string('showtopbarbrand_desc', 'local_heyday_courseplayer'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/logourl',
        get_string('logourl', 'local_heyday_courseplayer'),
        get_string('logourl_desc', 'local_heyday_courseplayer'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/topbarbg',
        get_string('topbarbg', 'local_heyday_courseplayer'),
        get_string('colour_desc', 'local_heyday_courseplayer'),
        '#050505',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/accentcolor',
        get_string('accentcolor', 'local_heyday_courseplayer'),
        get_string('colour_desc', 'local_heyday_courseplayer'),
        '#0073a8',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/pagebg',
        get_string('pagebg', 'local_heyday_courseplayer'),
        get_string('colour_desc', 'local_heyday_courseplayer'),
        '#f4f5f7',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/cardbg',
        get_string('cardbg', 'local_heyday_courseplayer'),
        get_string('colour_desc', 'local_heyday_courseplayer'),
        '#ffffff',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/sidebarwidth',
        get_string('sidebarwidth', 'local_heyday_courseplayer'),
        get_string('sidebarwidth_desc', 'local_heyday_courseplayer'),
        '424',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/contentmaxwidth',
        get_string('contentmaxwidth', 'local_heyday_courseplayer'),
        get_string('contentmaxwidth_desc', 'local_heyday_courseplayer'),
        '1120',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_heyday_courseplayer/sequenceheading',
        get_string('sequenceheading', 'local_heyday_courseplayer'),
        get_string('sequenceheading_desc', 'local_heyday_courseplayer')
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/defaultcourseid',
        get_string('defaultcourseid', 'local_heyday_courseplayer'),
        get_string('defaultcourseid_desc', 'local_heyday_courseplayer'),
        '105',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/searchurl',
        get_string('searchurl', 'local_heyday_courseplayer'),
        get_string('searchurl_desc', 'local_heyday_courseplayer'),
        '/local/heyday_coursesearch/search.php',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/helpurl',
        get_string('helpurl', 'local_heyday_courseplayer'),
        get_string('helpurl_desc', 'local_heyday_courseplayer'),
        '/local/heyday_helptour/help.php',
        PARAM_TEXT
    ));


    $settings->add(new admin_setting_configtext(
        'local_heyday_courseplayer/toururl',
        get_string('toururl', 'local_heyday_courseplayer'),
        get_string('toururl_desc', 'local_heyday_courseplayer'),
        '/local/heyday_helptour/tour.php',
        PARAM_TEXT
    ));
}
