<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Anonymous install/uninstall telemetry for local_edugears.
 *
 * Sends a small POST to lti-api.edugears.ai so we can measure how many
 * Moodle sites install the plugin vs how many complete LTI registration.
 * No personal data, no site URL, no user info — only a hashed site
 * identifier and version strings.
 *
 * Honors $CFG->local_edugears_telemetry = false; opt-out in config.php.
 *
 * @package    local_edugears
 * @copyright  2026 EduGears AI <support@edugears.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edugears;

/**
 * Anonymous install/uninstall telemetry sender.
 */
class telemetry {
    /** @var string Endpoint URL. */
    const ENDPOINT = 'https://lti-api.edugears.ai/api/plugin/telemetry';

    /**
     * Fire-and-forget ping. Never throws, never blocks meaningfully.
     *
     * @param string $event 'install' or 'uninstall'
     * @return void
     */
    public static function ping($event) {
        global $CFG;

        // Opt-out switch.
        if (isset($CFG->local_edugears_telemetry) && $CFG->local_edugears_telemetry === false) {
            return;
        }

        try {
            require_once($CFG->libdir . '/filelib.php');

            $siteid = isset($CFG->siteidentifier) ? $CFG->siteidentifier : '';
            $wwwroot = isset($CFG->wwwroot) ? $CFG->wwwroot : '';
            $sitehash = substr(hash('sha256', $siteid . '|' . $wwwroot), 0, 32);

            $plugin = new \stdClass();
            require($CFG->dirroot . '/local/edugears/version.php');

            $payload = [
                'event'           => $event,
                'site_hash'       => $sitehash,
                'plugin_version'  => isset($plugin->release) ? $plugin->release : '',
                'moodle_version'  => isset($CFG->release) ? $CFG->release : '',
                'php_version'     => PHP_VERSION,
            ];

            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_TIMEOUT'        => 2,
                'CURLOPT_CONNECTTIMEOUT' => 2,
                'CURLOPT_RETURNTRANSFER' => true,
            ]);
            $curl->setHeader('Content-Type: application/json');
            $curl->post(self::ENDPOINT, json_encode($payload));
        } catch (\Throwable $e) {
            // Swallow all errors — telemetry must never break install/uninstall.
            return;
        }
    }
}
