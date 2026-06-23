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
 * Library functions for local_hvpreport
 *
 * @package    local_hvpreport
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a link to view all student results on the HVP activity page.
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context of the current page
 */
function local_hvpreport_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    // Only add to HVP module pages.
    if ($PAGE->pagetype !== 'mod-hvp-view') {
        return;
    }

    // Check if user has permission to view all results.
    if (!has_capability('mod/hvp:viewallresults', $context)) {
        return;
    }

    // Get the course module.
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'hvp') {
        return;
    }

    // Find the activity settings node.
    $node = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if ($node) {
        // Add the link to view student results.
        $url = new moodle_url('/local/hvpreport/report.php', array('id' => $cm->id));
        $node->add(
            get_string('viewstudentresults', 'local_hvpreport'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'hvpreport',
            new pix_icon('i/report', '')
        );
    }
}

