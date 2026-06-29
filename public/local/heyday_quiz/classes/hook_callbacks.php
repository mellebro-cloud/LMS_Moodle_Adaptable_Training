<?php
// This file is part of Moodle - http://moodle.org/

namespace local_heyday_quiz;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_standard_head_html_generation;

require_once(__DIR__ . '/../lib.php');

/**
 * Hook callbacks for the Heyday lesson quiz skin plugin.
 */
final class hook_callbacks {

    /**
     * Inject CSS into <head> on quiz attempt/review/summary pages.
     *
     * @param before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
        before_standard_head_html_generation $hook
    ): void {
        // Early script: patch EventTarget.prototype.addEventListener so we can
        // remove all beforeunload handlers before HeyDay navigates.  Must run
        // before any AMD module (including the quiz module) registers handlers.
        $earlyjs = local_heyday_quiz_early_head_script();
        if ($earlyjs !== '') {
            $hook->add_html($earlyjs);
        }

        $html = local_heyday_quiz_before_standard_html_head();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }

    /**
     * Inject the quiz shell JavaScript before the closing </body>.
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(
        before_footer_html_generation $hook
    ): void {
        $html = local_heyday_quiz_before_footer();
        if ($html !== '') {
            $hook->add_html($html);
        }
    }
}
