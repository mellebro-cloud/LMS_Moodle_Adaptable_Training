<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_heyday_courseplayer\privacy;

use core_privacy\local\metadata\null_provider;
use core_privacy\local\legacy_polyfill;

/**
 * Privacy provider for local_heyday_courseplayer.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {
    use legacy_polyfill;

    /**
     * This plugin does not store personal data.
     *
     * @return string
     */
    public static function _get_reason() {
        return 'privacy:metadata';
    }
}
