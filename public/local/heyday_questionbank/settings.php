<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Admin settings link for local_heyday_questionbank.
 *
 * @package    local_heyday_questionbank
 * @copyright  2026 HeyDayTraining
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'localplugins',
        new admin_externalpage(
            'local_heyday_questionbank',
            get_string('pluginname', 'local_heyday_questionbank'),
            new moodle_url('/local/heyday_questionbank/index.php')
        )
    );
}
