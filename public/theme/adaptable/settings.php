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
 * Settings
 *
 * @package    theme_adaptable
 * @copyright  2015-2019 Jeremy Hopkins (Coventry University)
 * @copyright  2015-2019 Fernando Acedo (3-bits.com)
 * @copyright  2017-2019 Manoj Solanki (Coventry University)
 * @copyright  2019 G J Barnard
 *               {@link https://moodle.org/user/profile.php?id=442195}
 *               {@link https://gjbarnard.co.uk}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

// Moodle 5.2 compatibility for legacy Mustache class name used by older settings code.
// The real Moodle 5.2 class exists in public/lib/mustache/src/Loader/StringLoader.php,
// but some theme/settings code may still request Mustache_Loader_StringLoader.
if (!class_exists('Mustache_Loader_StringLoader', false)) {
    $mustachebase = $CFG->dirroot . '/lib/mustache/src';

    if (is_readable($mustachebase . '/Loader/Loader.php')) {
        require_once($mustachebase . '/Loader/Loader.php');
    }

    if (is_readable($mustachebase . '/Loader/StringLoader.php')) {
        require_once($mustachebase . '/Loader/StringLoader.php');
    }

    if (class_exists('\\Mustache\\Loader\\StringLoader', false)
            && !class_exists('Mustache_Loader_StringLoader', false)) {
        class_alias('\\Mustache\\Loader\\StringLoader', 'Mustache_Loader_StringLoader');
    }
}

unset($settings);
$settings = null;

$ADMIN->add('appearance', new admin_category('theme_adaptable', get_string('configtitle', 'theme_adaptable')));

\theme_adaptable\settings::add_settings();