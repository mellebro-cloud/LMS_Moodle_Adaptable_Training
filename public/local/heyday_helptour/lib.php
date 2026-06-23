<?php
// This file is part of Moodle - http://moodle.org/
//
// Local plugin callbacks for Heyday Help and Tour.

defined('MOODLE_INTERNAL') || die();

/**
 * Output the Help + Tour controls and tour modal just before the page footer.
 *
 * This plugin intentionally DOES NOT replace Moodle's default search or user menu.
 * It only adds Help Center and Tour controls beside Moodle's existing header items.
 *
 * @return string HTML/CSS/JS output.
 */
function local_heyday_helptour_before_footer(): string {
    global $PAGE, $COURSE, $CFG, $USER;

    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        return '';
    }

    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        return '';
    }

    // Do not inject on admin settings/editing pages where it may interfere.
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $blocked = [
        '/admin/',
        '/course/modedit.php',
        '/course/edit.php',
        '/course/editsection.php',
        '/backup/',
        '/grade/grading/',
    ];

    foreach ($blocked as $blockpath) {
        if (strpos($path, $blockpath) !== false) {
            return '';
        }
    }

    $courseid = 0;
    if (!empty($COURSE) && !empty($COURSE->id) && (int)$COURSE->id !== SITEID) {
        $courseid = (int)$COURSE->id;
    } else {
        $courseid = optional_param('courseid', optional_param('id', 0, PARAM_INT), PARAM_INT);
    }

    $helpurl = new moodle_url('/local/heyday_helptour/help.php', ['courseid' => $courseid]);
    $toururl = new moodle_url('/local/heyday_helptour/tour.php', ['courseid' => $courseid]);

    $helpurljs = json_encode($helpurl->out(false));
    $toururljs = json_encode($toururl->out(false));
    $wwwrootjs = json_encode($CFG->wwwroot);
    $username = isloggedin() && !isguestuser() ? fullname($USER) : '';
    $usernamejs = json_encode($username);

    return <<<HTML
<style id="heyday-helptour-css">
/* =========================================================
   Heyday Help + Tour plugin
   Adds only Help Center + Tour.
   Moodle default search + user account menu remain unchanged.
   ========================================================= */
#heyday-helptour-nav {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0 !important;
    height: 42px !important;
    min-height: 42px !important;
    max-height: 42px !important;
    margin: 0 6px 0 0 !important;
    padding: 0 !important;
    vertical-align: middle !important;
    z-index: 9999 !important;
}

#heyday-helptour-nav .heyday-ht-item {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 42px !important;
    min-height: 42px !important;
    max-height: 42px !important;
    padding: 0 11px !important;
    margin: 0 !important;
    border: 0 !important;
    border-left: 1px solid rgba(255,255,255,.25) !important;
    border-radius: 0 !important;
    background: transparent !important;
    color: #ffffff !important;
    box-shadow: none !important;
    text-decoration: none !important;
    font-size: 14px !important;
    line-height: 42px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    white-space: nowrap !important;
}

#heyday-helptour-nav .heyday-ht-item:last-child {
    border-right: 1px solid rgba(255,255,255,.25) !important;
}

#heyday-helptour-nav .heyday-ht-item:hover,
#heyday-helptour-nav .heyday-ht-item:focus {
    background: #111111 !important;
    color: #ffffff !important;
    text-decoration: none !important;
    outline: none !important;
}

#heyday-helptour-nav .heyday-ht-icon {
    color: #ffffff !important;
    font-size: 16px !important;
    line-height: 1 !important;
    margin: 0 6px 0 0 !important;
}

#heyday-helptour-nav .heyday-ht-help .heyday-ht-icon {
    margin-right: 0 !important;
}

#heyday-helptour-nav .heyday-ht-help span.heyday-ht-label {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    clip: rect(1px, 1px, 1px, 1px) !important;
}

/* Floating fallback if the theme does not expose a normal navbar target. */
#heyday-helptour-nav.heyday-ht-floating {
    position: fixed !important;
    top: 0 !important;
    right: 115px !important;
    height: 42px !important;
    z-index: 100010 !important;
    background: transparent !important;
}

/* Tour modal overlay */
#heyday-tour-overlay {
    position: fixed !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, .58) !important;
    z-index: 100000 !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 24px !important;
}

#heyday-tour-overlay.heyday-tour-open {
    display: flex !important;
}

#heyday-tour-dialog {
    width: 360px !important;
    max-width: calc(100vw - 48px) !important;
    min-height: 205px !important;
    background: #ffffff !important;
    color: #333333 !important;
    border-radius: 4px !important;
    border: 1px solid #d6d6d6 !important;
    box-shadow: 0 5px 20px rgba(0,0,0,.25) !important;
    padding: 28px 24px 20px 24px !important;
    position: relative !important;
    font-family: Arial, Helvetica, sans-serif !important;
}

#heyday-tour-close {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    width: 28px !important;
    height: 28px !important;
    border: 0 !important;
    background: transparent !important;
    color: #333333 !important;
    font-size: 24px !important;
    line-height: 28px !important;
    cursor: pointer !important;
}

#heyday-tour-title {
    margin: 0 34px 18px 0 !important;
    padding: 0 !important;
    color: #333333 !important;
    font-size: 20px !important;
    line-height: 1.25 !important;
    font-weight: 700 !important;
}

#heyday-tour-body {
    color: #444444 !important;
    font-size: 15px !important;
    line-height: 1.45 !important;
    margin: 0 0 26px 0 !important;
}

#heyday-tour-footer {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
}

#heyday-tour-prev,
#heyday-tour-next {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 34px !important;
    padding: 7px 13px !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    line-height: 1.2 !important;
    cursor: pointer !important;
}

#heyday-tour-prev {
    background: #ffffff !important;
    color: #333333 !important;
    border: 1px solid #999999 !important;
}

#heyday-tour-next {
    background: #0072a8 !important;
    color: #ffffff !important;
    border: 1px solid #0072a8 !important;
}

#heyday-tour-prev[disabled] {
    display: none !important;
}

#heyday-tour-step-count {
    margin-right: auto !important;
    font-size: 13px !important;
    color: #666666 !important;
}

@media (max-width: 700px) {
    #heyday-helptour-nav .heyday-ht-tour span.heyday-ht-label {
        display: none !important;
    }

    #heyday-helptour-nav .heyday-ht-tour .heyday-ht-icon {
        margin-right: 0 !important;
    }
}
</style>

<script id="heyday-helptour-js">
(function () {
    'use strict';

    var HELP_URL = {$helpurljs};
    var TOUR_URL = {$toururljs};
    var WWWROOT = {$wwwrootjs};
    var USER_NAME = {$usernamejs};

    var tourSteps = [
        {
            title: 'Welcome to your course!',
            body: 'This is the main course dashboard. You can see your course progress, next activity, and quick navigation from here.'
        },
        {
            title: 'Course menu',
            body: 'Use the left course menu to open Home, Scores, Discussions, Getting Started, Pretest, lessons, resources, and the final exam.'
        },
        {
            title: 'Course progress',
            body: 'The completion circle shows how much of the course is complete. The score circle shows your current grade when a score is available.'
        },
        {
            title: 'Continue learning',
            body: 'Use the Continue button to open the next recommended incomplete activity.'
        },
        {
            title: 'Help Center',
            body: 'Click the question mark icon in the top bar to open the Help Center FAQ page.'
        },
        {
            title: 'Search and account',
            body: 'Use Moodle\'s default search and user account menu in the top bar. This plugin does not replace those Moodle features.'
        }
    ];

    var currentStep = 0;

    function cleanText(value) {
        return (value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function createElement(tag, className, html) {
        var el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (html !== undefined) {
            el.innerHTML = html;
        }
        return el;
    }

    function findHeaderTarget() {
        var selectors = [
            '.usermenu',
            '[data-region="user-menu"]',
            '#user-menu-toggle',
            '.navbar .navbar-nav:last-child',
            'nav.navbar .nav:last-child',
            'header .navbar-nav:last-child',
            'header nav',
            'nav.navbar',
            '.navbar'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var item = document.querySelector(selectors[i]);
            if (item) {
                return item;
            }
        }

        return null;
    }

    function alreadyInsideNewNav(node) {
        return node && node.closest && node.closest('#heyday-helptour-nav');
    }

    function buildHelpTourNav() {
        if (document.getElementById('heyday-helptour-nav')) {
            return;
        }

        var nav = createElement('div', '', '');
        nav.id = 'heyday-helptour-nav';
        nav.setAttribute('aria-label', 'Heyday help and tour');

        var help = createElement(
            'a',
            'heyday-ht-item heyday-ht-help',
            '<i class="fa fa-question-circle heyday-ht-icon" aria-hidden="true"></i><span class="heyday-ht-label">Help Center</span>'
        );
        help.href = HELP_URL;
        help.title = 'Help Center';
        help.setAttribute('aria-label', 'Help Center');

        var tour = createElement(
            'button',
            'heyday-ht-item heyday-ht-tour',
            '<i class="fa fa-map-signs heyday-ht-icon" aria-hidden="true"></i><span class="heyday-ht-label">Tour</span>'
        );
        tour.type = 'button';
        tour.title = 'Tour';
        tour.setAttribute('aria-label', 'Tour');
        tour.addEventListener('click', function (event) {
            event.preventDefault();
            openTour(0);
        });

        nav.appendChild(help);
        nav.appendChild(tour);

        var target = findHeaderTarget();

        if (target && !alreadyInsideNewNav(target)) {
            if (target.classList && (target.classList.contains('usermenu') || target.getAttribute('data-region') === 'user-menu' || target.id === 'user-menu-toggle')) {
                target.parentNode.insertBefore(nav, target);
            } else {
                target.appendChild(nav);
            }
        } else {
            nav.classList.add('heyday-ht-floating');
            document.body.appendChild(nav);
        }
    }

    function ensureTourModal() {
        if (document.getElementById('heyday-tour-overlay')) {
            return;
        }

        var overlay = createElement('div');
        overlay.id = 'heyday-tour-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'heyday-tour-title');

        var dialog = createElement('div');
        dialog.id = 'heyday-tour-dialog';

        var close = createElement('button', '', '&times;');
        close.id = 'heyday-tour-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Close tour');
        close.addEventListener('click', closeTour);

        var title = createElement('h2');
        title.id = 'heyday-tour-title';

        var body = createElement('div');
        body.id = 'heyday-tour-body';

        var footer = createElement('div');
        footer.id = 'heyday-tour-footer';

        var count = createElement('span');
        count.id = 'heyday-tour-step-count';

        var prev = createElement('button', '', 'Previous');
        prev.id = 'heyday-tour-prev';
        prev.type = 'button';
        prev.addEventListener('click', function () {
            if (currentStep > 0) {
                openTour(currentStep - 1);
            }
        });

        var next = createElement('button', '', 'Next');
        next.id = 'heyday-tour-next';
        next.type = 'button';
        next.addEventListener('click', function () {
            if (currentStep < tourSteps.length - 1) {
                openTour(currentStep + 1);
            } else {
                closeTour();
            }
        });

        footer.appendChild(count);
        footer.appendChild(prev);
        footer.appendChild(next);

        dialog.appendChild(close);
        dialog.appendChild(title);
        dialog.appendChild(body);
        dialog.appendChild(footer);
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeTour();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeTour();
            }
        });
    }

    function openTour(step) {
        ensureTourModal();

        currentStep = Math.max(0, Math.min(step, tourSteps.length - 1));

        var overlay = document.getElementById('heyday-tour-overlay');
        var title = document.getElementById('heyday-tour-title');
        var body = document.getElementById('heyday-tour-body');
        var count = document.getElementById('heyday-tour-step-count');
        var prev = document.getElementById('heyday-tour-prev');
        var next = document.getElementById('heyday-tour-next');

        title.textContent = tourSteps[currentStep].title;
        body.textContent = tourSteps[currentStep].body;
        count.textContent = (currentStep + 1) + ' / ' + tourSteps.length;
        prev.disabled = currentStep === 0;
        next.textContent = currentStep === tourSteps.length - 1 ? 'Finish' : 'Next (' + (currentStep + 1) + '/' + tourSteps.length + ')';

        overlay.classList.add('heyday-tour-open');
        next.focus();
    }

    function closeTour() {
        var overlay = document.getElementById('heyday-tour-overlay');
        if (overlay) {
            overlay.classList.remove('heyday-tour-open');
        }
    }

    function init() {
        buildHelpTourNav();
        ensureTourModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    setTimeout(init, 500);
    setTimeout(init, 1500);
})();
</script>
HTML;
}
