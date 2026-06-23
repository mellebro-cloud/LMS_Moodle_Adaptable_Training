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
 * Version information for local_hvpreport
 *
 * @package    local_hvpreport
 * @copyright  2025 Brian A. Pool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_hvpreport';
$plugin->version   = 2025120101;        // 2025-12-01 version 1.1
$plugin->requires  = 2022041900;        // Requires Moodle 4.0 or later.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.3.1-beta';      // Version 1.3.1 - Performance optimization
$plugin->dependencies = array(
    'mod_hvp' => ANY_VERSION
);
