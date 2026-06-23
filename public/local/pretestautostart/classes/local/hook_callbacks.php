<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Hook callbacks for local_pretestautostart.
 *
 * @package    local_pretestautostart
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pretestautostart\local;

use context_module;
use core\hook\output\before_standard_footer_html_generation;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks.
 */
class hook_callbacks {
    /**
     * Add the autostart JavaScript only on configured quiz front pages.
     *
     * @param before_standard_footer_html_generation $hook The output hook.
     */
    public static function before_standard_footer_html_generation(
        before_standard_footer_html_generation $hook
    ): void {
        global $CFG, $PAGE;

        if (function_exists('during_initial_install') && during_initial_install()) {
            return;
        }

        if (!empty($CFG->upgraderunning)) {
            return;
        }

        if ($PAGE->pagetype !== 'mod-quiz-view') {
            return;
        }

        if (empty($PAGE->cm) || $PAGE->cm->modname !== 'quiz') {
            return;
        }

        $cmid = (int) $PAGE->cm->id;
        $configuredcmids = get_config('local_pretestautostart', 'cmids');

        if (!self::cmid_is_enabled($cmid, $configuredcmids)) {
            return;
        }

        $context = context_module::instance($cmid);
        $canattempt = has_capability('mod/quiz:attempt', $context);
        $canpreview = has_capability('mod/quiz:preview', $context);
        $autopreview = !empty(get_config('local_pretestautostart', 'autopreview'));

        if (!$canattempt && !($canpreview && $autopreview)) {
            return;
        }

        // Hide the start/preview button while the JS redirects into the attempt page.
        $hook->add_html('<style>.path-mod-quiz .quizstartbuttondiv{display:none!important;}</style><noscript><style>.path-mod-quiz .quizstartbuttondiv{display:block!important;}</style></noscript>');

        $PAGE->requires->js_call_amd('local_pretestautostart/autostart', 'init', [[
            'autopreview' => $autopreview,
        ]]);
    }

    /**
     * Check whether a quiz course module id is enabled in the plugin settings.
     *
     * @param int $cmid Course module id from mod/quiz/view.php?id=CMID.
     * @param string|false|null $raw Comma, newline, or space separated ids.
     * @return bool True when enabled.
     */
    private static function cmid_is_enabled(int $cmid, $raw): bool {
        if (empty($raw) || !is_string($raw)) {
            return false;
        }

        $ids = preg_split('/[^0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($ids)) {
            return false;
        }

        return in_array((string) $cmid, $ids, true);
    }
}
