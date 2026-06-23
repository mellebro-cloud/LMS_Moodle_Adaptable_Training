<?php
// This file is part of Moodle - http://moodle.org/

namespace local_heyday_pretest\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider for local_heyday_pretest.
 *
 * @package    local_heyday_pretest
 * @copyright  2026 Heyday LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Return a language string explaining that this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
