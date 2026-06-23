<?php
// This file is part of Moodle - http://moodle.org/

namespace local_heyday_helptour;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;

require_once(__DIR__ . '/../lib.php');

/**
 * Hook callbacks for local_heyday_helptour.
 */
final class hook_callbacks {

    /**
     * Replacement for local_heyday_helptour_before_footer().
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(
        before_footer_html_generation $hook
    ): void {
        $html = \local_heyday_helptour_before_footer();

        if ($html !== '') {
            $hook->add_html($html);
        }
    }
}