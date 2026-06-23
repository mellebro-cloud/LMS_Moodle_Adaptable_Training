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
 * Post-installation script for EduGears AI LTI.
 *
 * This script configures EduGears AI as a preconfigured LTI 1.3 external tool
 * so that instructors can immediately add AI tools to their courses.
 *
 * @package    local_edugears
 * @copyright  2026 EduGears AI <support@edugears.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs after the plugin is installed.
 *
 * Displays a notification guiding the admin to complete the LTI 1.3
 * dynamic registration via Moodle's built-in Manage tools page.
 *
 * @return bool
 */
function xmldb_local_edugears_install() {
    global $CFG;

    // Store the registration URL in plugin config for easy reference.
    set_config('registration_url', 'https://lti-api.edugears.ai/lti/register', 'local_edugears');

    // Send a notification directing admin to the one-click registration page.
    $registerurl = new moodle_url('/local/edugears/register.php');
    \core\notification::info(
        get_string('setup_complete', 'local_edugears')
        . ' <a href="' . $registerurl->out() . '" class="btn btn-primary" '
        . 'style="margin-left:8px; font-size:14px; color:#fff !important; text-decoration:none;">'
        . get_string('register_button', 'local_edugears') . '</a>'
    );

    // Anonymous adoption telemetry — see classes/telemetry.php for details.
    \local_edugears\telemetry::ping('install');

    return true;
}
