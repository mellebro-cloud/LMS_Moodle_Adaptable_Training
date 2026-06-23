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
 * Report view renderable
 *
 * @package    local_hvpreport
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_hvpreport\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Report view renderable class
 *
 * @package    local_hvpreport
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view implements renderable, templatable {

    /** @var string The activity name */
    protected $activityname;

    /** @var array The groups available */
    protected $groups;

    /** @var int The selected group ID */
    protected $groupid;

    /** @var int The course module ID */
    protected $cmid;

    /** @var array The student results data */
    protected $students;

    /** @var string Back URL */
    protected $backurl;

    /**
     * Constructor
     *
     * @param string $activityname The activity name
     * @param array $groups The groups
     * @param int $groupid The selected group ID
     * @param int $cmid The course module ID
     * @param array $students The student data
     * @param string $backurl The back URL
     */
    public function __construct($activityname, $groups, $groupid, $cmid, $students, $backurl) {
        $this->activityname = $activityname;
        $this->groups = $groups;
        $this->groupid = $groupid;
        $this->cmid = $cmid;
        $this->students = $students;
        $this->backurl = $backurl;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for template
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->activityname = $this->activityname;
        $data->cmid = $this->cmid;
        $data->hasgroups = !empty($this->groups);
        $data->groupid = $this->groupid;
        $data->backurl = $this->backurl;
        
        // Prepare groups for template.
        if ($data->hasgroups) {
            $data->groups = [];
            foreach ($this->groups as $group) {
                $data->groups[] = [
                    'id' => $group->id,
                    'name' => format_string($group->name),
                    'selected' => ($this->groupid == $group->id)
                ];
            }
        }
        
        // Prepare students for template.
        $data->students = $this->students;
        $data->hasstudents = !empty($this->students);
        
        // Language strings.
        $data->str_filterbygroup = get_string('filterbygroup', 'local_hvpreport');
        $data->str_allgroups = get_string('allgroups');
        $data->str_go = get_string('go');
        $data->str_fullname = get_string('fullname');
        $data->str_email = get_string('email');
        $data->str_attempts = get_string('attempts', 'quiz');
        $data->str_percentage = get_string('percentage', 'grades');
        $data->str_grade = get_string('grade', 'local_hvpreport');
        $data->str_lastmodified = get_string('lastmodified');
        $data->str_actions = get_string('actions');
        $data->str_viewdetails = get_string('viewdetails', 'local_hvpreport');
        $data->str_back = get_string('back', 'local_hvpreport');
        $data->str_nostudents = get_string('nostudentsfound', 'moodle');
        
        return $data;
    }
}
