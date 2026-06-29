/* Heyday Pretest Shell DOM enhancer. */
(function() {
    'use strict';

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function makeButton(text, className, attrs) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className || '';
        button.textContent = text;
        if (attrs) {
            Object.keys(attrs).forEach(function(key) {
                button.setAttribute(key, attrs[key]);
            });
        }
        return button;
    }

    function setupLikeButtons() {
        var key = 'heyday-pretest-like-' + (window.HeydayPretestConfig && window.HeydayPretestConfig.cmid ? window.HeydayPretestConfig.cmid : location.pathname + location.search);
        qsa('[data-heyday-like]').forEach(function(button) {
            if (localStorage.getItem(key) === '1') {
                button.classList.add('is-liked');
                button.textContent = '♥';
            }
            button.addEventListener('click', function() {
                var liked = button.classList.toggle('is-liked');
                button.textContent = liked ? '♥' : '♡';
                localStorage.setItem(key, liked ? '1' : '0');
            });
        });
    }

    function setupPrintMenus() {
        document.addEventListener('click', function(event) {
            var toggle = event.target.closest('[data-heyday-print-toggle]');
            var menu;
            if (toggle) {
                event.preventDefault();
                menu = toggle.parentNode.querySelector('[data-heyday-print-menu]');
                if (menu) {
                    menu.classList.toggle('is-open');
                }
                return;
            }

            if (event.target.closest('[data-heyday-print-activity]') || event.target.closest('[data-heyday-print-lesson]')) {
                event.preventDefault();
                window.print();
                return;
            }

            qsa('[data-heyday-print-menu].is-open').forEach(function(openMenu) {
                if (!openMenu.contains(event.target)) {
                    openMenu.classList.remove('is-open');
                }
            });
        });
    }

    function setupFullscreen() {
        document.addEventListener('click', function(event) {
            var button = event.target.closest('[data-heyday-fullscreen]');
            if (!button) {
                return;
            }
            event.preventDefault();
            var target = qs('[data-heyday-pretest-shell]') || qs('#region-main') || document.documentElement;
            if (!document.fullscreenElement && target.requestFullscreen) {
                target.requestFullscreen();
            } else if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        });
    }

    function setupBackButtons() {
        qsa('.hd-back-btn').forEach(function(link) {
            link.addEventListener('click', function(event) {
                if (history.length > 1) {
                    event.preventDefault();
                    history.back();
                }
            });
        });
    }

    function createTopbar() {
        var cfg = window.HeydayPretestConfig || {};
        if (qs('.hd-core-shell-top')) {
            return;
        }

        var main = qs('#region-main');
        if (!main) {
            return;
        }

        var top = document.createElement('div');
        top.className = 'hd-core-shell-top';
        top.setAttribute('data-heyday-pretest-shell', '1');

        var left = document.createElement('div');
        left.className = 'hd-pretest-actions-left';
        var back = document.createElement('a');
        back.className = 'hd-icon-btn hd-back-btn';
        back.href = cfg.shellurl || '#';
        back.setAttribute('aria-label', 'Back');
        back.textContent = '←';
        var like = makeButton('♡', 'hd-icon-btn hd-like-btn', {
            'aria-label': 'Bookmark this activity',
            'data-heyday-like': '1'
        });
        left.appendChild(back);
        left.appendChild(like);

        var right = document.createElement('div');
        right.className = 'hd-pretest-actions-right';
        var printWrap = document.createElement('div');
        printWrap.className = 'hd-print-menu-wrap';
        var print = makeButton('⎙', 'hd-icon-btn hd-print-btn', {
            'aria-label': 'Print',
            'data-heyday-print-toggle': '1'
        });
        var menu = document.createElement('div');
        menu.className = 'hd-print-dropdown';
        menu.setAttribute('data-heyday-print-menu', '1');
        var printActivity = makeButton('Print/Save activity', '', {'data-heyday-print-activity': '1'});
        var printLesson = makeButton('Print/Save entire lesson', '', {'data-heyday-print-lesson': '1'});
        menu.appendChild(printActivity);
        menu.appendChild(printLesson);
        printWrap.appendChild(print);
        printWrap.appendChild(menu);
        var fullscreen = makeButton('⛶', 'hd-icon-btn hd-fullscreen-btn', {
            'aria-label': 'Full screen',
            'data-heyday-fullscreen': '1'
        });
        right.appendChild(printWrap);
        right.appendChild(fullscreen);

        top.appendChild(left);
        top.appendChild(right);
        main.insertBefore(top, main.firstChild);

        var heading = document.createElement('div');
        heading.className = 'hd-core-heading';
        var course = document.createElement('div');
        course.className = 'hd-course-title';
        course.textContent = cfg.coursename || '';
        var h1 = document.createElement('h1');
        h1.textContent = cfg.quizname || 'Pretest';
        heading.appendChild(course);
        heading.appendChild(h1);
        main.insertBefore(heading, top.nextSibling);
    }

    function setupQuestionSelectionStyling() {
        qsa('.que .answer input[type="radio"], .que .answer input[type="checkbox"]').forEach(function(input) {
            input.addEventListener('change', function() {
                var answer = input.closest('.answer');
                if (!answer) {
                    return;
                }
                qsa('div.r0, div.r1, .answer-item', answer).forEach(function(row) {
                    row.classList.remove('hd-answer-selected');
                    var checked = qs('input[type="radio"]:checked, input[type="checkbox"]:checked', row);
                    if (checked) {
                        row.classList.add('hd-answer-selected');
                    }
                });
            });
            input.dispatchEvent(new Event('change', {bubbles: true}));
        });
    }

    function addInstructionsLink() {
        var form = qs('form#responseform');
        if (!form || qs('.hd-show-instructions')) {
            return;
        }
        var link = document.createElement('a');
        link.href = '#';
        link.className = 'hd-show-instructions';
        link.textContent = 'ⓘ Show Instructions';
        link.addEventListener('click', function(event) {
            event.preventDefault();
            alert('Answer all questions, then use Save and Close or Submit Answers at the bottom of the page.');
        });
        form.insertBefore(link, form.firstChild);
    }

    function renameSubmitButtons() {
        qsa('input[type="submit"], button[type="submit"]').forEach(function(button) {
            var value = (button.value || button.textContent || '').toLowerCase();
            if (value.indexOf('finish attempt') !== -1 || value.indexOf('submit all') !== -1 || value.indexOf('submit') !== -1) {
                if (button.tagName.toLowerCase() === 'input') {
                    button.value = 'Submit Answers';
                } else {
                    button.textContent = 'Submit Answers';
                }
            }
        });
    }

    function addSaveCloseButton() {
        var submitArea = qs('.submitbtns');
        var form = qs('form#responseform');
        if (!submitArea || !form || qs('.hd-save-close')) {
            return;
        }
        var button = makeButton('Save and Close', 'btn btn-secondary hd-save-close');
        button.addEventListener('click', function() {
            // Moodle autosaves quiz responses. This sends the learner back to the shell.
            var cfg = window.HeydayPretestConfig || {};
            location.href = cfg.shellurl || document.referrer || '/';
        });
        submitArea.insertBefore(button, submitArea.firstChild);
    }

    function addLearnerCounter() {
        if (qs('.hd-learner-counter')) {
            return;
        }
        var el = document.createElement('div');
        el.className = 'hd-learner-counter';
        el.innerHTML = '<span>&rsaquo;</span><span class="count-green">0</span><span>Mine</span><span class="count-green">0</span><span class="count-red">0</span><span>New</span><span class="count-blue">0</span><span>Bookmarked</span>';
        document.body.appendChild(el);
    }

    function enhanceCoreQuizPage() {
        if (!document.documentElement.classList.contains('heyday-pretest-core-quiz')) {
            return;
        }
        createTopbar();
        addInstructionsLink();
        setupQuestionSelectionStyling();
        renameSubmitButtons();
        addSaveCloseButton();
        addLearnerCounter();
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupLikeButtons();
        setupPrintMenus();
        setupFullscreen();
        setupBackButtons();
        enhanceCoreQuizPage();
    });
})();
