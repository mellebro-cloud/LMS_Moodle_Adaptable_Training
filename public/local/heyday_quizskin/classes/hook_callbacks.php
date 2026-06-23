<?php
// This file is part of Moodle - http://moodle.org/

namespace local_heyday_quizskin;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

require_once(__DIR__ . '/../lib.php');

/**
 * Hook callbacks for the Heyday Quiz Skin plugin.
 */
final class hook_callbacks {

    /**
     * Replacement for local_heyday_quizskin_before_standard_html_head().
     *
     * @param before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
        before_standard_head_html_generation $hook
    ): void {
        $html = local_heyday_quizskin_before_standard_html_head();

        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Replacement for local_heyday_quizskin_before_footer().
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(
        before_footer_html_generation $hook
    ): void {
        $html = local_heyday_quizskin_before_footer();

        if ($html !== '') {
            $hook->add_html($html);
        }
    }
}