<?php
// Heyday course search.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    $courseid = optional_param('id', 0, PARAM_INT);
}
$query = optional_param('q', '', PARAM_RAW_TRIMMED);

if (!$courseid) {
    throw new moodle_exception('missingparam', 'error', '', 'courseid');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);
require_login($course);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/heyday_coursesearch/search.php', ['courseid' => $course->id]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursesearch', 'local_heyday_coursesearch'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('heyday-coursesearch-page');
$PAGE->requires->css(new moodle_url('/local/heyday_coursesearch/styles.css'));

function local_heyday_coursesearch_norm(string $text): string {
    $text = html_to_text($text, 0, false);
    $text = core_text::strtolower($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text ?? '');
}

function local_heyday_coursesearch_excerpt(string $text, string $query): string {
    $plain = trim(preg_replace('/\s+/', ' ', html_to_text($text, 0, false)) ?? '');
    if ($plain === '') {
        return '';
    }

    $queryplain = trim($query);
    $pos = $queryplain !== '' ? core_text::strpos(core_text::strtolower($plain), core_text::strtolower($queryplain)) : false;
    if ($pos === false) {
        return shorten_text($plain, 180);
    }

    $start = max(0, $pos - 70);
    $excerpt = core_text::substr($plain, $start, 220);
    if ($start > 0) {
        $excerpt = '...' . $excerpt;
    }
    if ($start + 220 < core_text::strlen($plain)) {
        $excerpt .= '...';
    }
    return $excerpt;
}

function local_heyday_coursesearch_match_score(string $haystack, string $query): int {
    $haystack = local_heyday_coursesearch_norm($haystack);
    $query = local_heyday_coursesearch_norm($query);
    if ($query === '') {
        return 0;
    }

    $score = 0;
    if (core_text::strpos($haystack, $query) !== false) {
        $score += 100;
    }

    $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($terms as $term) {
        if (core_text::strpos($haystack, $term) !== false) {
            $score += 10;
        }
    }

    return $score;
}

function local_heyday_coursesearch_get_module_extra_text(stdClass $cm, stdClass $course): string {
    global $DB;

    $text = '';
    if (!empty($cm->content)) {
        $text .= ' ' . $cm->content;
    }

    if (!empty($cm->description)) {
        $text .= ' ' . $cm->description;
    }

    if (empty($cm->instance) || empty($cm->modname)) {
        return $text;
    }

    $table = $cm->modname;
    if (!$DB->get_manager()->table_exists($table)) {
        return $text;
    }

    $record = $DB->get_record($table, ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$record) {
        return $text;
    }

    foreach (['name', 'intro', 'content', 'summary', 'description', 'externalurl'] as $field) {
        if (isset($record->{$field}) && is_string($record->{$field})) {
            $text .= ' ' . $record->{$field};
        }
    }

    return $text;
}

$results = [];

if ($query !== '') {
    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();

    // Search course title/summary as a course result.
    $coursehaystack = $course->fullname . ' ' . $course->shortname . ' ' . ($course->summary ?? '');
    $coursescore = local_heyday_coursesearch_match_score($coursehaystack, $query);
    if ($coursescore > 0) {
        $results[] = [
            'score' => $coursescore + 20,
            'title' => format_string($course->fullname),
            'url' => new moodle_url('/course/view.php', ['id' => $course->id]),
            'meta' => get_string('opencourse', 'local_heyday_coursesearch'),
            'summary' => local_heyday_coursesearch_excerpt($course->summary ?? '', $query),
        ];
    }

    foreach ($modinfo->cms as $cm) {
        if (!$cm->uservisible) {
            continue;
        }

        $sectionname = '';
        if (isset($sections[$cm->sectionnum])) {
            $sectioninfo = $sections[$cm->sectionnum];
            $sectionname = get_section_name($course, $sectioninfo);
        }

        $extra = local_heyday_coursesearch_get_module_extra_text($cm, $course);
        $haystack = $cm->name . ' ' . $cm->modplural . ' ' . $sectionname . ' ' . $extra;
        $score = local_heyday_coursesearch_match_score($haystack, $query);

        if ($score <= 0) {
            continue;
        }

        $results[] = [
            'score' => $score,
            'title' => format_string($cm->name),
            'url' => $cm->url ?: new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $cm->sectionnum]),
            'meta' => trim($sectionname . ($sectionname ? ' / ' : '') . $cm->modplural),
            'summary' => local_heyday_coursesearch_excerpt($extra, $query),
        ];
    }

    usort($results, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
}

echo $OUTPUT->header();


$hcsheaderjs = <<<'JS'
(function () {
    'use strict';

    var COURSE_ID = %COURSEID%;

    function getMoodleRoot() {
        if (window.M && M.cfg && M.cfg.wwwroot) {
            return M.cfg.wwwroot;
        }
        var path = window.location.pathname;
        var markers = ['/local/', '/course/', '/mod/', '/admin/'];
        for (var i = 0; i < markers.length; i++) {
            var index = path.indexOf(markers[i]);
            if (index !== -1) {
                return window.location.origin + path.substring(0, index);
            }
        }
        return window.location.origin + '/moodle';
    }

    function courseId() {
        var params = new URLSearchParams(window.location.search);
        return params.get('courseid') || params.get('id') || COURSE_ID;
    }

    function cleanText(value) {
        return (value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function isCourseSearchPage() {
        return window.location.pathname.indexOf('/local/heyday_coursesearch/search.php') !== -1 ||
            document.body.classList.contains('path-local-heyday_coursesearch') ||
            document.body.classList.contains('heyday-coursesearch-page') ||
            document.body.id === 'page-local-heyday_coursesearch-search';
    }

    function createIcon(id, iconClass, label, href) {
        var a = document.createElement('a');
        a.id = id;
        a.className = 'hcs-topbar-item hcs-topbar-icon';
        a.href = href;
        a.title = label;
        a.setAttribute('aria-label', label);
        a.innerHTML = '<i class="fa ' + iconClass + '" aria-hidden="true"></i><span class="sr-only">' + label + '</span>';
        return a;
    }

    function separator() {
        var span = document.createElement('span');
        span.className = 'hcs-topbar-separator';
        span.setAttribute('aria-hidden', 'true');
        return span;
    }

    function findUserMenu() {
        var inBar = document.querySelector('#hcs-ed2go-topbar .usermenu, #hcs-ed2go-topbar [data-region="user-menu"]');
        if (inBar) {
            return inBar;
        }

        var selectors = [
            '.usermenu',
            '[data-region="user-menu"]',
            '#user-menu-toggle'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var found = document.querySelector(selectors[i]);
            if (!found || found.closest('#hcs-ed2go-topbar')) {
                continue;
            }

            if (found.matches && found.matches('.usermenu, [data-region="user-menu"]')) {
                return found;
            }

            var userRegion = found.closest('.usermenu, [data-region="user-menu"]');
            if (userRegion) {
                return userRegion;
            }

            var navItem = found.closest('li.nav-item, .nav-item');
            return navItem || found;
        }

        return null;
    }

    function hideElement(element) {
        if (!element || element.closest('#hcs-ed2go-topbar') || element.closest('.hcs-wrap')) {
            return;
        }
        element.classList.add('hcs-original-topbar-hidden');
        element.setAttribute('aria-hidden', 'true');
        element.style.setProperty('display', 'none', 'important');
        element.style.setProperty('visibility', 'hidden', 'important');
        element.style.setProperty('width', '0', 'important');
        element.style.setProperty('height', '0', 'important');
        element.style.setProperty('min-width', '0', 'important');
        element.style.setProperty('min-height', '0', 'important');
        element.style.setProperty('max-width', '0', 'important');
        element.style.setProperty('max-height', '0', 'important');
        element.style.setProperty('margin', '0', 'important');
        element.style.setProperty('padding', '0', 'important');
        element.style.setProperty('border', '0', 'important');
        element.style.setProperty('overflow', 'hidden', 'important');
    }

    function hideOldHeaderPieces() {
        if (!isCourseSearchPage()) {
            return;
        }

        /* Hide earlier Heyday/Additional-HTML topbars and their Help/Tour buttons. */
        document.querySelectorAll(
            '#heyday-ed2go-topbar, .heyday-topbar-item, .heyday-topbar-right, ' +
            '#heyday-topbar-help, #heyday-topbar-tour, #heyday-topbar-search, ' +
            '.hsb-topbar, .hsb-help-button, .hsb-help-link, .hsb-tour-button, .hsb-tour-text, ' +
            '.tool_usertours-resettour, .tool_usertours-resettourcontainer'
        ).forEach(function (element) {
            hideElement(element);
        });

        /* Hide Adaptable/Moodle original top utility rows after moving the user menu. */
        document.querySelectorAll(
            'header, nav.navbar, .navbar.fixed-top, .navbar-dark, ' +
            '#header1, #header2, #header3, #above-header, #top-header, #adaptable-top-header, ' +
            '.top-header, .above-header, .navbar-inverse'
        ).forEach(function (element) {
            if (!element || element.closest('#hcs-ed2go-topbar') || element.querySelector('#hcs-ed2go-topbar')) {
                return;
            }

            var rect = element.getBoundingClientRect();
            var text = cleanText(element.textContent);
            var looksLikeTopUtility = rect.top < 180 && (
                element.querySelector('.usermenu') ||
                element.querySelector('[data-region="user-menu"]') ||
                element.querySelector('.simplesearchform') ||
                element.querySelector('[data-region="search-input"]') ||
                element.querySelector('.popover-region') ||
                element.querySelector('.fa-search') ||
                element.querySelector('.fa-question-circle') ||
                element.querySelector('.fa-question') ||
                element.querySelector('.fa-bell') ||
                element.querySelector('.fa-comment') ||
                text.indexOf('tour') !== -1 ||
                text.indexOf('help') !== -1 ||
                text.indexOf('admin user') !== -1
            );

            if (looksLikeTopUtility) {
                hideElement(element);
            }
        });

        /* Hide old header search, messages, notifications, Help and Tour controls.
           Do not touch the main in-page .hcs-form search field. */
        document.querySelectorAll(
            '.simplesearchform, .searchform, .search-input-form, [data-region="search-input"], ' +
            '[data-region="search-input-wrapper"], .search-input-wrapper, ' +
            '.popover-region-notifications, .popover-region-messages, ' +
            '[data-region="popover-region-notifications"], [data-region="popover-region-messages"], ' +
            '.popover-region, a[href*="/message/"], a[href*="/notifications/"]'
        ).forEach(function (element) {
            var rect = element.getBoundingClientRect();
            if (rect.top < 180 && !element.closest('#hcs-ed2go-topbar') && !element.closest('.hcs-form')) {
                hideElement(element);
            }
        });

        document.querySelectorAll('a, button, li, div, span').forEach(function (element) {
            if (!element || element.closest('#hcs-ed2go-topbar') || element.closest('.hcs-wrap')) {
                return;
            }

            var rect = element.getBoundingClientRect();
            if (rect.top >= 180) {
                return;
            }

            var text = cleanText(element.textContent);
            var href = element.getAttribute('href') || '';
            var title = cleanText(element.getAttribute('title') || '');
            var aria = cleanText(element.getAttribute('aria-label') || '');
            var combined = text + ' ' + href.toLowerCase() + ' ' + title + ' ' + aria;
            var hasHelpIcon = !!(element.querySelector && (element.querySelector('.fa-question-circle') || element.querySelector('.fa-question')));

            var isNoise =
                combined.indexOf('tour') !== -1 ||
                combined.indexOf('usertours') !== -1 ||
                combined.indexOf('help center') !== -1 ||
                combined.indexOf('heyday_helptour') !== -1 ||
                (hasHelpIcon && combined.indexOf('help') !== -1) ||
                element.classList.contains('hsb-help-button') ||
                element.classList.contains('hsb-help-link') ||
                element.classList.contains('hsb-tour-button') ||
                element.classList.contains('heyday-topbar-item');

            if (isNoise) {
                hideElement(element);
            }
        });
    }

    function buildTopbar() {
        if (!isCourseSearchPage()) {
            return;
        }

        document.body.classList.add('hcs-ed2go-topbar-active');

        var bar = document.getElementById('hcs-ed2go-topbar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'hcs-ed2go-topbar';
            document.body.prepend(bar);
        }

        var right = bar.querySelector('.hcs-topbar-right');
        if (!right) {
            right = document.createElement('div');
            right.className = 'hcs-topbar-right';
            bar.appendChild(right);
        }

        var hasFallback = !!right.querySelector('.hcs-user-fallback');
        if (right.dataset.hcsBuilt === '1' && !hasFallback) {
            hideOldHeaderPieces();
            return;
        }

        var userMenu = findUserMenu();
        while (right.firstChild) {
            right.removeChild(right.firstChild);
        }

        var root = getMoodleRoot();
        var cid = encodeURIComponent(courseId());

        right.appendChild(createIcon(
            'hcs-topbar-search',
            'fa-search',
            'Search',
            root + '/local/heyday_coursesearch/search.php?courseid=' + cid
        ));
        right.appendChild(separator());

        right.appendChild(createIcon(
            'hcs-topbar-help',
            'fa-question-circle',
            'Help',
            root + '/local/heyday_helptour/help.php?courseid=' + cid
        ));
        right.appendChild(separator());

        if (userMenu) {
            userMenu.classList.remove('hcs-original-topbar-hidden');
            userMenu.removeAttribute('aria-hidden');
            userMenu.style.display = '';
            userMenu.style.visibility = '';
            userMenu.style.opacity = '';
            right.appendChild(userMenu);
        } else {
            var fallback = document.createElement('span');
            fallback.className = 'hcs-topbar-item hcs-user-fallback';
            fallback.innerHTML = '<i class="fa fa-user" aria-hidden="true"></i><span>User</span>';
            right.appendChild(fallback);
        }

        right.dataset.hcsBuilt = '1';
        hideOldHeaderPieces();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildTopbar);
    } else {
        buildTopbar();
    }

    setTimeout(buildTopbar, 250);
    setTimeout(buildTopbar, 800);
    setTimeout(buildTopbar, 1600);
})();
JS;
$hcsheaderjs = str_replace('%COURSEID%', (string)(int)$course->id, $hcsheaderjs);
echo html_writer::tag('script', $hcsheaderjs, ['id' => 'hcs-ed2go-search-header-js']);

echo html_writer::start_div('hcs-wrap');

echo html_writer::start_tag('form', [
    'class' => 'hcs-form',
    'method' => 'get',
    'action' => new moodle_url('/local/heyday_coursesearch/search.php'),
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => $course->id,
]);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'q',
    'value' => s($query),
    'placeholder' => get_string('searchplaceholder', 'local_heyday_coursesearch'),
    'aria-label' => get_string('searchplaceholder', 'local_heyday_coursesearch'),
    'autofocus' => 'autofocus',
]);
echo html_writer::tag('button', '<i class="fa fa-search" aria-hidden="true"></i><span class="sr-only">' . get_string('searchbutton', 'local_heyday_coursesearch') . '</span>', [
    'type' => 'submit',
    'title' => get_string('searchbutton', 'local_heyday_coursesearch'),
]);
echo html_writer::end_tag('form');

if ($query === '') {
    echo html_writer::start_div('hcs-instructions');
    echo html_writer::tag('h2', get_string('searchinstructions', 'local_heyday_coursesearch'));
    echo html_writer::tag('p', '<strong>apple orange</strong><br>Search for documents containing any term.');
    echo html_writer::tag('p', '<strong>"orange apple"</strong><br>Use quotes to search for exact phrases.');
    echo html_writer::tag('p', '<strong>"extra terrestrial" + life</strong><br>Use plus to search for documents containing all terms.');
    echo html_writer::tag('p', '<strong>flash + -photography</strong><br>Use plus and minus to search for documents containing some terms but not others.');
    echo html_writer::tag('p', '<strong>flash + -(photography dance)</strong><br>Use parentheses to group terms.');
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('hcs-results');
    echo html_writer::tag('h2', get_string('resultsfor', 'local_heyday_coursesearch', s($query)));

    if (empty($results)) {
        echo html_writer::div(get_string('noresults', 'local_heyday_coursesearch'), 'hcs-empty');
    } else {
        foreach ($results as $result) {
            echo html_writer::start_div('hcs-result-card');
            echo html_writer::tag('div', html_writer::link($result['url'], $result['title']), ['class' => 'hcs-result-title']);
            if (!empty($result['meta'])) {
                echo html_writer::div(s($result['meta']), 'hcs-result-meta');
            }
            if (!empty($result['summary'])) {
                echo html_writer::div(s($result['summary']), 'hcs-result-summary');
            }
            echo html_writer::end_div();
        }
    }

    echo html_writer::end_div();
}

echo html_writer::end_div();

echo $OUTPUT->footer();
