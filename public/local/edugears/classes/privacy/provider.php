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
 * Privacy provider for EduGears AI LTI.
 *
 * @package    local_edugears
 * @copyright  2026 EduGears AI <support@edugears.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edugears\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy provider implementation.
 *
 * This plugin transmits user data to an external system (EduGears AI)
 * via LTI 1.3 but does not store personal data locally.
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Returns metadata about the external data sent via LTI.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'edugears_ai',
            [
                'fullname'  => 'privacy:metadata:externalpurpose',
                'email'     => 'privacy:metadata:externalpurpose',
                'courseid'  => 'privacy:metadata:externalpurpose',
                'userrole'  => 'privacy:metadata:externalpurpose',
            ],
            'privacy:metadata'
        );

        return $collection;
    }
}
