<?php
// Local plugin callbacks for Heyday Getting Started.

defined('MOODLE_INTERNAL') || die();

/**
 * Inject course-index behaviour for Getting Started.
 * The parent Getting Started row opens Course Overview while child links stay visible.
 *
 * @return string
 */
function local_heyday_gettingstarted_before_footer(): string {
    global $COURSE, $PAGE;

    if (empty($COURSE) || empty($COURSE->id) || (int)$COURSE->id <= 1) {
        return '';
    }

    $courseid = (int)$COURSE->id;
    $root = (new moodle_url('/'))->out(false);

    $data = [
        'courseid' => $courseid,
        'root' => rtrim($root, '/'),
        'overviewid' => 'GS_COURSE_OVERVIEW',
        'section' => 'getting started',
        'firstchild' => 'course overview',
    ];

    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    return <<<HTML
<style id="heyday-gettingstarted-nav-css">
.heyday-gs-left-section > .courseindex-item,
.heyday-gs-left-section > [data-for="section_item"],
.heyday-gs-main-link {
    cursor: pointer !important;
}
.heyday-gs-left-section > .courseindex-item:hover,
.heyday-gs-left-section > [data-for="section_item"]:hover,
.heyday-gs-main-link:hover {
    background: #eef7fb !important;
}
.heyday-gs-left-section > .courseindex-sectioncontent,
.heyday-gs-left-section > [data-for="section_content"] {
    display: block !important;
    visibility: visible !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    background: #f1f5f8 !important;
}
.heyday-gs-left-section .courseindex-sectioncontent .courseindex-item,
.heyday-gs-left-section [data-for="section_content"] .courseindex-item,
.heyday-gs-left-section .courseindex-sectioncontent [data-for="cm"],
.heyday-gs-left-section [data-for="section_content"] [data-for="cm"] {
    display: flex !important;
    visibility: visible !important;
}
.heyday-gs-hidden-duplicate {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    min-height: 0 !important;
    max-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
}
</style>
<script id="heyday-gettingstarted-nav-js">
(function () {
    'use strict';

    var HGS = {$json};

    function cleanText(value) {
        return (value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function getLeftSections() {
        return document.querySelectorAll(
            '#courseindex .courseindex-section, ' +
            '.courseindex .courseindex-section, ' +
            '[data-region="courseindex"] .courseindex-section, ' +
            '.drawer-left .courseindex-section, ' +
            '#courseindex [data-for="section"], ' +
            '.courseindex [data-for="section"], ' +
            '[data-region="courseindex"] [data-for="section"], ' +
            '.drawer-left [data-for="section"]'
        );
    }

    function getHeader(section) {
        return section.querySelector(':scope > .courseindex-item') ||
            section.querySelector(':scope > [data-for="section_item"]') ||
            section.querySelector(':scope > .courseindex-section-title');
    }

    function getTitle(header) {
        if (!header) {
            return null;
        }
        return header.querySelector('.courseindex-link') ||
            header.querySelector('[data-for="section_title"]') ||
            header.querySelector('a') ||
            header.querySelector('button') ||
            header;
    }

    function findGsSection() {
        var sections = getLeftSections();
        for (var i = 0; i < sections.length; i++) {
            var section = sections[i];
            var header = getHeader(section);
            var title = getTitle(header);
            if (cleanText(title ? title.textContent : '') === HGS.section) {
                return {section: section, header: header, title: title};
            }
        }
        return null;
    }

    function customPageUrl(page) {
        return HGS.root + '/local/heyday_gettingstarted/view.php?courseid=' + encodeURIComponent(HGS.courseid) + '&page=' + encodeURIComponent(page);
    }

    function findOverviewUrl(section) {
        var links = section.querySelectorAll('a[href]');

        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var text = cleanText(link.textContent);
            var href = link.getAttribute('href') || '';

            if (text === HGS.firstchild && href.indexOf('/local/heyday_gettingstarted/view.php') !== -1) {
                return href;
            }
        }

        return customPageUrl('overview');
    }

    function fixGettingStartedChildLinks(section) {
        var pageMap = {
            'course overview': 'overview',
            'syllabus': 'syllabus',
            'navigating this course': 'navigating'
        };

        var seen = {};
        var links = section.querySelectorAll('a[href]');

        links.forEach(function (link) {
            var text = cleanText(link.textContent);
            var page = pageMap[text];
            var href = link.getAttribute('href') || '';

            if (!page) {
                return;
            }

            var row = link.closest('[data-for="cm"], [data-cmid], .courseindex-cm, .courseindex-item, li') || link;

            if (href.indexOf('/mod/page/view.php') !== -1 || seen[text]) {
                row.style.display = 'none';
                row.style.visibility = 'hidden';
                row.style.height = '0';
                row.style.minHeight = '0';
                row.style.maxHeight = '0';
                row.style.margin = '0';
                row.style.padding = '0';
                row.style.overflow = 'hidden';
                return;
            }

            link.setAttribute('href', customPageUrl(page));
            seen[text] = true;
        });
    }

    function forceExpanded(section) {
        section.classList.remove('collapsed');
        var header = getHeader(section);
        if (header) {
            header.setAttribute('aria-expanded', 'true');
        }
        section.querySelectorAll(':scope > .courseindex-sectioncontent, :scope > [data-for="section_content"]').forEach(function (content) {
            content.style.display = 'block';
            content.style.visibility = 'visible';
            content.style.height = 'auto';
            content.style.maxHeight = 'none';
            content.style.overflow = 'visible';
        });
    }

    function isEditingAction(target) {
        return !!(target && target.closest(
            '.editing_move, .commands, .action-menu, .dropdown, .dropdown-menu, .iconsmall, ' +
            '[data-action="edit"], [data-action="edittitle"], .quickeditlink, .edit-menu, .bulk-actions, .section-actions'
        ));
    }

    function openUrl(event, url) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
        window.location.href = url;
    }

    function init() {
        var found = findGsSection();
        if (!found || !found.section || !found.header) {
            return;
        }

        fixGettingStartedChildLinks(found.section);
        var url = findOverviewUrl(found.section);
        found.section.classList.add('heyday-gs-left-section');
        found.header.classList.add('heyday-gs-main-link');
        found.header.setAttribute('title', 'Open Course Overview');
        if (found.title) {
            found.title.classList.add('heyday-gs-main-link');
            found.title.setAttribute('title', 'Open Course Overview');
            if (found.title.tagName && found.title.tagName.toLowerCase() === 'a') {
                found.title.setAttribute('href', url);
            }
        }

        forceExpanded(found.section);

        if (found.header.dataset.heydayGsLinked === '1') {
            return;
        }
        found.header.dataset.heydayGsLinked = '1';

        found.header.addEventListener('click', function (event) {
            if (isEditingAction(event.target)) {
                return;
            }
            openUrl(event, url);
        }, true);

        if (found.title && found.title !== found.header) {
            found.title.addEventListener('click', function (event) {
                if (isEditingAction(event.target)) {
                    return;
                }
                openUrl(event, url);
            }, true);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    setTimeout(init, 300);
    setTimeout(init, 1000);
    setTimeout(init, 2000);
})();
</script>
HTML;
}
