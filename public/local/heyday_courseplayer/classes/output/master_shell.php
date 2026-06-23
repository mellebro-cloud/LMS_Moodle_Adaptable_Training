<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Reusable ed2go-style master learner shell helpers.
 *
 * Other Heyday local plugins can call this class to use the same dark topbar,
 * sticky sidebar, content-card spacing, CSS variables, completion footer, and
 * Next Up footer as local_heyday_courseplayer without editing Moodle core or
 * Adaptable files.
 *
 * @package   local_heyday_courseplayer
 * @copyright 2026 Heyday Training LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_heyday_courseplayer\output;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_page;
use moodle_url;
use stdClass;

/**
 * Master player shell helper.
 */
final class master_shell {
    /** Main body class used by all Heyday learner-player pages. */
    public const BODY_CLASS = 'local-heyday-courseplayer';

    /** Body class that marks a page as using the reusable master shell. */
    public const MASTER_BODY_CLASS = 'local-heyday-masterplayer';

    /** CSS file relative path. */
    public const CSS_PATH = '/local/heyday_courseplayer/styles.css';

    /**
     * Prepare a Moodle page to render inside the reusable shell.
     *
     * @param moodle_page $page Moodle page object.
     * @param stdClass $course Course record.
     * @param string $title Page title.
     * @param string $pagetype Extra page key such as home, scores, lesson.
     */
    public static function prepare_page(moodle_page $page, stdClass $course, string $title, string $pagetype = 'home'): void {
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->add_body_class(self::BODY_CLASS);
        $page->add_body_class(self::MASTER_BODY_CLASS);
        $page->add_body_class('local-heyday-page-' . preg_replace('/[^a-z0-9_-]+/i', '-', $pagetype));
        $page->set_title(format_string($course->fullname) . ' - ' . $title);
        $page->set_heading(format_string($course->fullname));
        $page->requires->css(new moodle_url(self::CSS_PATH));
    }

    /**
     * Return sanitized CSS variable text for the player shell.
     *
     * @param array<string,string|int> $overrides Optional CSS variable overrides.
     * @return string Complete style tag.
     */
    public static function css_variables(array $overrides = []): string {
        $vars = array_merge([
            '--heyday-topbar-bg' => self::colour((string)get_config('local_heyday_courseplayer', 'topbarbg'), '#050505'),
            '--heyday-accent' => self::colour((string)get_config('local_heyday_courseplayer', 'accentcolor'), '#0073a8'),
            '--heyday-page-bg' => self::colour((string)get_config('local_heyday_courseplayer', 'pagebg'), '#f4f5f7'),
            '--heyday-card-bg' => self::colour((string)get_config('local_heyday_courseplayer', 'cardbg'), '#ffffff'),
            '--heyday-sidebar-width' => self::int_setting(get_config('local_heyday_courseplayer', 'sidebarwidth'), 424, 300, 620) . 'px',
            '--heyday-content-max' => self::int_setting(get_config('local_heyday_courseplayer', 'contentmaxwidth'), 1120, 680, 1400) . 'px',
        ], $overrides);

        $css = 'body.' . self::BODY_CLASS . '.' . self::MASTER_BODY_CLASS . '{';
        foreach ($vars as $name => $value) {
            if (preg_match('/^--[a-z0-9_-]+$/i', $name)) {
                $css .= $name . ':' . s((string)$value) . ';';
            }
        }
        $css .= '}';

        return html_writer::tag('style', $css, ['id' => 'heyday-master-player-settings']);
    }

    /**
     * Render the black learner topbar used in the screenshots.
     *
     * @param string $fullname Current user's display name.
     * @param array<string,moodle_url|string> $links Link keys: search, help, tour.
     * @param string $brandname Optional brand label.
     * @param string $logourl Optional logo URL.
     * @param bool $showbrand Whether the left side should show brand/logo.
     * @return string HTML.
     */
    public static function topbar(string $fullname, array $links, string $brandname = '', string $logourl = '', bool $showbrand = false): string {
        $searchurl = self::url_from($links['search'] ?? '/local/heyday_coursesearch/search.php');
        $helpurl = self::url_from($links['help'] ?? '/local/heyday_helptour/help.php');
        $toururl = self::url_from($links['tour'] ?? '/local/heyday_helptour/tour.php');

        $brandclasses = ['heyday-ed2go-brand'];
        if (!$showbrand) {
            $brandclasses[] = 'is-empty';
        }

        $out = html_writer::start_div('heyday-ed2go-topbar');
        $out .= html_writer::start_div(implode(' ', $brandclasses));
        if ($showbrand) {
            if (trim($logourl) !== '') {
                $out .= html_writer::empty_tag('img', ['src' => $logourl, 'alt' => '', 'class' => 'heyday-topbar-logo']);
            }
            if (trim($brandname) !== '') {
                $out .= html_writer::span(s($brandname));
            }
        }
        $out .= html_writer::end_div();

        $out .= html_writer::start_div('heyday-ed2go-topbar-right', ['aria-label' => 'Player tools']);
        $out .= html_writer::link($searchurl, html_writer::span('⌕', '', ['aria-hidden' => 'true']), ['class' => 'heyday-topbar-action heyday-topbar-search', 'aria-label' => 'Search']);
        $out .= html_writer::link($helpurl, html_writer::span('?', '', ['aria-hidden' => 'true']), ['class' => 'heyday-topbar-action heyday-topbar-help', 'aria-label' => 'Help']);
        $out .= html_writer::link($toururl, html_writer::span('⚑', '', ['aria-hidden' => 'true']) . html_writer::span('Tour'), ['class' => 'heyday-topbar-action heyday-topbar-tour']);
        $out .= html_writer::span(html_writer::span('♙', '', ['aria-hidden' => 'true']) . html_writer::span(s($fullname)) . html_writer::span('⌄', '', ['aria-hidden' => 'true']), 'heyday-topbar-user', ['aria-label' => 'Signed in user']);
        $out .= html_writer::end_div();
        $out .= html_writer::end_div();

        return $out;
    }

    /**
     * Start the outer shell wrapper.
     *
     * @param string $pagekey Page key class, for example home or lesson.
     * @return string HTML.
     */
    public static function start_shell(string $pagekey): string {
        $safe = preg_replace('/[^a-z0-9_-]+/i', '-', $pagekey);
        return html_writer::start_div('heyday-courseplayer-page is-page-' . $safe) . html_writer::start_div('heyday-courseplayer-shell');
    }

    /**
     * End the outer shell wrapper.
     *
     * @return string HTML.
     */
    public static function end_shell(): string {
        return html_writer::end_div() . html_writer::end_div();
    }

    /**
     * Open the full ed2go-style player shell using reusable Mustache templates.
     *
     * @param stdClass $course Moodle course object.
     * @param string $sidebarhtml Already-rendered safe sidebar HTML.
     * @param array<string,mixed> $options Shell options.
     * @return string HTML.
     */
    public static function open(stdClass $course, string $sidebarhtml, array $options = []): string {
        global $OUTPUT, $PAGE, $USER;

        $coursecontext = \context_course::instance($course->id);

        // open() is normally called before $OUTPUT->header(). Guard these calls so
        // child plugins that accidentally call open() after output has started do not
        // crash the page. The correct pattern is still to call prepare_page()/open()
        // before printing the Moodle header.
        try {
            $PAGE->add_body_class(self::BODY_CLASS);
            $PAGE->add_body_class(self::MASTER_BODY_CLASS);
        } catch (\Throwable $e) {
            // Body classes were already locked by Moodle output. Keep rendering.
        }

        try {
            $PAGE->requires->css(new moodle_url(self::CSS_PATH));
        } catch (\Throwable $e) {
            // CSS should already be required by the caller or by prepare_page().
        }

        self::require_player_actions($PAGE);

        $defaults = [
            'pageclass' => '',
            'pagetitle' => '',
            'sectionline' => '',
            'backurl' => new moodle_url('/local/heyday_courseplayer/index.php', ['id' => $course->id]),
            'showheading' => true,
            'showbookmark' => true,
            'showprint' => true,
            'printactivitylabel' => 'Print/Save activity',
            'printsectionlabel' => 'Print/Save Getting Started',
            'showfullscreen' => true,
            'showtopbar' => true,
            'topbarbrand' => '',
            'topbaruser' => fullname($USER),
            // Topbar editing links are available to teachers/managers by default.
            // Card-header editing links are off by default so learner pages keep
            // the ed2go visual layout; callers may opt in with showeditingtools.
            'showeditingtools' => false,
            'editcourseurl' => new moodle_url('/local/heyday_courseplayer/edit.php', ['id' => $course->id]),
            'viewcourseurl' => new moodle_url('/course/view.php', ['id' => $course->id]),
            'editsettingsurl' => new moodle_url('/course/edit.php', ['id' => $course->id]),
            // Native Moodle authoring locations. These are shown only to teachers/managers.
            'contentbankurl' => new moodle_url('/contentbank/index.php', ['contextid' => $coursecontext->id]),
            'showtopbareditingtools' => null,
            'searchurl' => new moodle_url('/local/heyday_coursesearch/search.php'),
            'helpurl' => new moodle_url('/local/heyday_helptour/help.php'),
            'toururl' => new moodle_url('/local/heyday_helptour/tour.php'),
        ];
        $options = array_merge($defaults, $options);

        $caneditcourse = has_capability('moodle/course:update', $coursecontext);
        $canaddsubsection = has_capability('mod/subsection:addinstance', $coursecontext);
        $showeditingtools = !empty($options['showeditingtools']) && $caneditcourse;

        // The page-card teacher tools are hidden on Home/dashboard pages, so expose
        // native Moodle editing links in the black topbar for teachers too. Learners
        // never see these controls.
        $showtopbareditingtools = $options['showtopbareditingtools'] === null
            ? $caneditcourse
            : (!empty($options['showtopbareditingtools']) && $caneditcourse);

        $data = [
            'courseid' => $course->id,
            'coursefullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
            'courseshortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
            'sidebarhtml' => $sidebarhtml,
            'pageclass' => (string)$options['pageclass'],
            'pagetitle' => (string)$options['pagetitle'],
            'sectionline' => (string)$options['sectionline'],
            'backurl' => self::url_to_string($options['backurl']),
            'showbookmark' => !empty($options['showbookmark']),
            'showprint' => !empty($options['showprint']),
            'printactivitylabel' => (string)$options['printactivitylabel'],
            'printsectionlabel' => (string)$options['printsectionlabel'],
            'showfullscreen' => !empty($options['showfullscreen']),
            'showtopbar' => !empty($options['showtopbar']),
            'topbarbrand' => (string)$options['topbarbrand'],
            'topbaruser' => (string)$options['topbaruser'],
            'caneditcourse' => $caneditcourse,
            'showcardeditingtools' => $showeditingtools,
            'showtopbareditingtools' => $showtopbareditingtools,
            'canaddsubsection' => $showeditingtools && $canaddsubsection,
            'editcourseurl' => self::url_to_string($options['editcourseurl']),
            'viewcourseurl' => self::url_to_string($options['viewcourseurl']),
            'editsettingsurl' => self::url_to_string($options['editsettingsurl']),
            'contentbankurl' => self::url_to_string($options['contentbankurl']),
            'showheading' => !empty($options['showheading']),
            'searchurl' => self::url_to_string($options['searchurl'], '#'),
            'helpurl' => self::url_to_string($options['helpurl'], '#'),
            'toururl' => self::url_to_string($options['toururl'], '#'),
        ];

        return $OUTPUT->render_from_template('local_heyday_courseplayer/master_header', $data);
    }

    /**
     * Close the full ed2go-style player shell using reusable Mustache templates.
     *
     * @param array<string,mixed> $options Footer options.
     * @return string HTML.
     */
    public static function close(array $options = []): string {
        global $OUTPUT;

        $defaults = [
            'completion' => null,
            'next' => null,
            'supporturl' => '#',
            'cookieurl' => '#',
            'copyright' => '© 2026 Cengage Learning, Inc. All Rights Reserved',
        ];
        $options = array_merge($defaults, $options);

        $completion = null;
        if (is_array($options['completion'])) {
            $completion = array_merge([
                'done' => false,
                'label' => 'Activity not complete',
                'undourl' => '',
                'undoavailableurl' => '',
                'completeurl' => '',
                'undolabel' => 'Undo',
            ], $options['completion']);
            $completion['undourl'] = self::url_to_string($completion['undourl']);
            $completion['undoavailableurl'] = self::url_to_string($completion['undoavailableurl']);
            $completion['completeurl'] = self::url_to_string($completion['completeurl']);
        }

        $next = null;
        if (is_array($options['next'])) {
            $next = array_merge([
                'url' => '',
                'title' => '',
                'type' => 'activity',
            ], $options['next']);
            $next['url'] = self::url_to_string($next['url']);
            if ($next['url'] === '' || trim((string)$next['title']) === '') {
                $next = null;
            }
        }

        $data = [
            'completion' => $completion,
            'next' => $next,
            'supporturl' => self::url_to_string($options['supporturl'], '#'),
            'cookieurl' => self::url_to_string($options['cookieurl'], '#'),
            'copyright' => (string)$options['copyright'],
        ];

        return $OUTPUT->render_from_template('local_heyday_courseplayer/master_footer', $data);
    }

    /**
     * Convenience method for rendering a complete player page.
     *
     * @param stdClass $course Moodle course object.
     * @param string $sidebarhtml Sidebar HTML.
     * @param string $contenthtml Main content HTML.
     * @param array<string,mixed> $headeroptions Header options.
     * @param array<string,mixed> $footeroptions Footer options.
     * @return string HTML.
     */
    public static function page(
        stdClass $course,
        string $sidebarhtml,
        string $contenthtml,
        array $headeroptions = [],
        array $footeroptions = []
    ): string {
        return self::open($course, $sidebarhtml, $headeroptions)
            . $contenthtml
            . self::close($footeroptions);
    }

    /**
     * Load minimal client actions for player buttons.
     *
     * @param moodle_page $page Moodle page object.
     * @return void
     */
    private static function require_player_actions(moodle_page $page): void {
        $js = <<<'JS'
(function() {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn, {once: true});
        }
    }

    ready(function() {
        var shell = document.querySelector('.heyday-courseplayer-page');
        if (!shell) {
            return;
        }

        function closePrintMenus(except) {
            Array.prototype.forEach.call(shell.querySelectorAll('[data-heyday-print-menu]'), function(menu) {
                if (except && menu === except) {
                    return;
                }
                menu.classList.remove('is-open');
                var toggle = menu.querySelector('[data-heyday-print-toggle]');
                var list = menu.querySelector('[data-heyday-print-menu-list]');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
                if (list) {
                    list.hidden = true;
                }
            });
        }

        Array.prototype.forEach.call(shell.querySelectorAll('[data-heyday-print-menu]'), function(menu) {
            var toggle = menu.querySelector('[data-heyday-print-toggle]');
            var list = menu.querySelector('[data-heyday-print-menu-list]');
            if (!toggle || !list) {
                return;
            }

            toggle.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                var open = !menu.classList.contains('is-open');
                closePrintMenus(menu);
                menu.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                list.hidden = !open;
            });

            Array.prototype.forEach.call(menu.querySelectorAll('[data-heyday-print-scope]'), function(item) {
                item.addEventListener('click', function(event) {
                    event.preventDefault();
                    closePrintMenus();
                    document.body.setAttribute('data-heyday-print-scope', item.getAttribute('data-heyday-print-scope') || 'activity');
                    window.setTimeout(function() {
                        window.print();
                        window.setTimeout(function() {
                            document.body.removeAttribute('data-heyday-print-scope');
                        }, 400);
                    }, 30);
                });
            });
        });

        document.addEventListener('click', function() {
            closePrintMenus();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePrintMenus();
            }
        });

        Array.prototype.forEach.call(shell.querySelectorAll('[data-heyday-fullscreen]'), function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                var target = document.querySelector('.heyday-player-card') || document.documentElement;
                if (document.fullscreenElement) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                    return;
                }
                if (target.requestFullscreen) {
                    target.requestFullscreen();
                }
            });
        });

        Array.prototype.forEach.call(shell.querySelectorAll('.heyday-subsection-group'), function(details) {
            details.addEventListener('toggle', function() {
                if (!details.open) {
                    return;
                }

                var depth = 0;
                details.classList.forEach(function(classname) {
                    var match = classname.match(/^depth-(\d+)$/);
                    if (match) {
                        depth = parseInt(match[1], 10);
                    }
                });

                var siblings = shell.querySelectorAll('.heyday-subsection-group.depth-' + depth);
                Array.prototype.forEach.call(siblings, function(sibling) {
                    if (sibling !== details && sibling.open) {
                        sibling.open = false;
                    }
                });
            });
        });

        Array.prototype.forEach.call(shell.querySelectorAll('[data-heyday-bookmark]'), function(button) {
            var key = 'heyday-bookmark:' + window.location.pathname + window.location.search;
            try {
                if (window.localStorage && window.localStorage.getItem(key) === '1') {
                    button.classList.add('is-bookmarked');
                    button.setAttribute('aria-pressed', 'true');
                }
            } catch (e) {}

            button.addEventListener('click', function(event) {
                event.preventDefault();
                var active = !button.classList.contains('is-bookmarked');
                button.classList.toggle('is-bookmarked', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                try {
                    if (window.localStorage) {
                        if (active) {
                            window.localStorage.setItem(key, '1');
                        } else {
                            window.localStorage.removeItem(key);
                        }
                    }
                } catch (e) {}
            });
        });

        Array.prototype.forEach.call(shell.querySelectorAll('[data-heyday-completion-row][data-heyday-complete-url]'), function(row) {
            var completeUrl = row.getAttribute('data-heyday-complete-url') || '';
            if (!completeUrl || row.classList.contains('is-complete')) {
                return;
            }

            var content = document.querySelector('.heyday-content-body');
            var sentinel = document.querySelector('[data-heyday-content-sentinel]');
            var completed = false;
            var visibleSince = 0;
            var minViewTime = Date.now() + 900;

            function markCompleteInUi() {
                row.classList.remove('is-pending', 'is-completing');
                row.classList.add('is-complete', 'is-auto-completed');

                var pending = row.querySelector('.heyday-completion-pending');
                if (pending) {
                    var check = document.createElement('span');
                    check.className = 'heyday-completion-check';
                    check.setAttribute('aria-hidden', 'true');
                    check.textContent = '✓';
                    pending.parentNode.replaceChild(check, pending);
                }

                var label = row.querySelector('.heyday-completion-text > div');
                if (label) {
                    label.textContent = row.getAttribute('data-heyday-complete-label') || 'Activity complete';
                }

                var text = row.querySelector('.heyday-completion-text');
                var undoUrl = row.getAttribute('data-heyday-undo-url') || '';
                if (text && undoUrl && !text.querySelector('.heyday-completion-undo')) {
                    var undo = document.createElement('a');
                    undo.className = 'heyday-completion-undo';
                    undo.href = undoUrl;
                    undo.textContent = row.getAttribute('data-heyday-undo-label') || 'Undo';
                    text.appendChild(undo);
                }
            }

            function completeNow() {
                if (completed) {
                    return;
                }
                completed = true;
                row.classList.add('is-completing');
                fetch(completeUrl, {credentials: 'same-origin', redirect: 'follow'})
                    .catch(function() {})
                    .then(function() {
                        markCompleteInUi();
                    });
            }

            function contentEndVisible() {
                var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                if (!viewportHeight) {
                    return false;
                }

                if (sentinel) {
                    var sr = sentinel.getBoundingClientRect();
                    return sr.top <= viewportHeight && sr.bottom >= 0;
                }

                if (content) {
                    var cr = content.getBoundingClientRect();
                    return cr.bottom <= viewportHeight + 8;
                }

                return (window.scrollY + viewportHeight) >= (document.documentElement.scrollHeight - 8);
            }

            function checkViewed() {
                if (completed) {
                    return;
                }
                if (Date.now() < minViewTime) {
                    window.setTimeout(checkViewed, 250);
                    return;
                }
                if (contentEndVisible()) {
                    if (!visibleSince) {
                        visibleSince = Date.now();
                    }
                    if (Date.now() - visibleSince >= 500) {
                        completeNow();
                    } else {
                        window.setTimeout(checkViewed, 150);
                    }
                } else {
                    visibleSince = 0;
                }
            }

            if ('IntersectionObserver' in window && sentinel) {
                var observer = new IntersectionObserver(function(entries) {
                    var entry = entries && entries[0];
                    if (entry && entry.isIntersecting) {
                        checkViewed();
                    } else {
                        visibleSince = 0;
                    }
                }, {root: null, threshold: 0.01});
                observer.observe(sentinel);
            }

            window.addEventListener('scroll', checkViewed, {passive: true});
            window.addEventListener('resize', checkViewed);
            window.setTimeout(checkViewed, 1000);
            window.setTimeout(checkViewed, 1800);
        });
    });
})();
JS;

        try {
            $page->requires->js_init_code($js, true);
        } catch (\Throwable $e) {
            // If a caller already started output, the static links still render safely.
        }
    }

    /**
     * Convert string/moodle_url to moodle_url.
     *
     * @param moodle_url|string $value URL value.
     * @return moodle_url
     */
    private static function url_from($value): moodle_url {
        if ($value instanceof moodle_url) {
            return $value;
        }
        $value = trim((string)$value);
        if ($value === '' || $value === '#') {
            return new moodle_url('/');
        }
        if (!preg_match('/^https?:\/\//i', $value) && $value[0] !== '/') {
            $value = '/' . $value;
        }
        return new moodle_url($value);
    }

    /**
     * Convert supported URL values to a string for templates.
     *
     * @param mixed $value URL value.
     * @param string $empty Default returned for empty values.
     * @return string
     */
    private static function url_to_string($value, string $empty = ''): string {
        if ($value instanceof moodle_url) {
            return $value->out(false);
        }
        $value = trim((string)$value);
        return $value === '' ? $empty : $value;
    }

    /**
     * Sanitize hex color.
     *
     * @param string $value Setting value.
     * @param string $default Default color.
     * @return string
     */
    private static function colour(string $value, string $default): string {
        $value = trim($value);
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : $default;
    }

    /**
     * Clamp integer setting.
     *
     * @param mixed $value Raw value.
     * @param int $default Default.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @return int
     */
    private static function int_setting($value, int $default, int $min, int $max): int {
        $value = (int)$value;
        if ($value < $min || $value > $max) {
            return $default;
        }
        return $value;
    }
}
