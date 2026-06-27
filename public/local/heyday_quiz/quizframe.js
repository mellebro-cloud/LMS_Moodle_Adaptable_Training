(function () {
    'use strict';

    var injectedCss = [
        'html, body { background: #fff !important; }',
        'body { margin: 0 !important; padding: 0 !important; font-family: Arial, Helvetica, sans-serif !important; color: #111827 !important; overflow-x: hidden !important; }',
        '.navbar, nav.navbar, #page-header, #page-navbar, .secondary-navigation, .activity-navigation, .drawer-toggles, .drawer-left, .drawer-right, #theme_boost-drawers-courseindex, footer#page-footer, [data-region="blocks-column"], .breadcrumb, .tertiary-navigation, .activity-header, .urlselect, .quizattemptcounts, .activity-information { display: none !important; }',
        '#page, #page.drawers, #page-content, #region-main, .main-inner, #topofscroll, #region-main-box { margin: 0 !important; padding: 0 !important; max-width: none !important; width: 100% !important; background: #fff !important; border: 0 !important; box-shadow: none !important; }',
        '.container, .container-fluid { max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }',
        'h1, .page-header-headings, .quizinfo, .box.py-3.quizinfo { display: none !important; }',
        '.que { border: 0 !important; border-bottom: 1px dashed #cfd7df !important; padding: 22px 0 18px 60px !important; margin: 0 !important; position: relative !important; background: #fff !important; box-shadow: none !important; }',
        '.que .info { position: absolute !important; left: 0 !important; top: 25px !important; width: 42px !important; height: 42px !important; padding: 0 !important; margin: 0 !important; border: 0 !important; border-radius: 0 22px 22px 0 !important; background: #687482 !important; color: #fff !important; display: flex !important; align-items: center !important; justify-content: center !important; }',
        '.que .info .no { color: #fff !important; font-weight: 700 !important; font-size: 16px !important; }',
        '.que .info .state, .que .info .grade, .que .info .editquestion, .que .info .questionflag { display: none !important; }',
        '.que .content { margin: 0 !important; padding: 0 !important; }',
        '.que .formulation { border: 0 !important; background: #fff !important; padding: 0 !important; margin: 0 !important; color: #111827 !important; }',
        '.qtext { margin: 0 0 18px !important; font-size: 16px !important; line-height: 1.45 !important; color: #111827 !important; }',
        '.ablock { margin: 0 !important; }',
        '.answer > div, .answer .r0, .answer .r1 { display: grid !important; grid-template-columns: 54px minmax(0, 1fr) !important; align-items: center !important; min-height: 42px !important; margin: 0 0 10px !important; padding: 0 !important; background: #f3f3f3 !important; border: 0 !important; color: #0076a8 !important; }',
        '.answer > div:hover, .answer .r0:hover, .answer .r1:hover { background: #eef7fb !important; }',
        '.answer .answernumber { width: 54px !important; min-height: 42px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; background: #d5d9dd !important; color: #334155 !important; margin: 0 12px 0 0 !important; font-weight: 600 !important; }',
        '.answer input[type="radio"], .answer input[type="checkbox"] { margin-left: 8px !important; margin-right: 10px !important; transform: scale(1.2) !important; accent-color: #0076a8 !important; }',
        '.answer label, .answer .flex-fill, .answer .d-flex, .answer p { color: #0076a8 !important; font-size: 15px !important; line-height: 1.35 !important; margin: 0 !important; }',
        '.submitbtns, .quizattempt .submitbtns, .path-mod-quiz .submitbtns { display: flex !important; justify-content: flex-end !important; align-items: center !important; gap: 12px !important; padding: 22px 0 !important; margin: 0 !important; }',
        'input[type="submit"], button[type="submit"], .btn-primary, .mod_quiz-next-nav { border-radius: 3px !important; padding: 9px 15px !important; font-weight: 700 !important; box-shadow: none !important; }',
        '.btn-primary, input.btn-primary, button.btn-primary, .mod_quiz-next-nav { background: #4f922d !important; border-color: #4f922d !important; color: #fff !important; }',
        '.btn-secondary, input.btn-secondary, button.btn-secondary { background: #fff !important; border-color: #b7c0c9 !important; color: #334155 !important; }',
        '.qn_buttons, #mod_quiz_navblock, .block, .block-region { display: none !important; }',
        '.quizattemptsummary, .generaltable, table.quizreviewsummary { width: 100% !important; }',
        '.boxaligncenter, .box.generalbox, .quizstartbuttondiv, .continuebutton { text-align: center !important; margin: 22px auto !important; }',
        '.quizstartbuttondiv .btn, .continuebutton .btn { background: #0076a8 !important; border-color: #0076a8 !important; color: #fff !important; font-weight: 700 !important; padding: 11px 18px !important; border-radius: 3px !important; }'
    ].join('\n');

    function injectCss(doc) {
        if (!doc || doc.getElementById('heyday-quiz-frame-style') || doc.getElementById('heyday-quiz-css')) {
            return;
        }
        var style = doc.createElement('style');
        style.id = 'heyday-quiz-frame-style';
        style.type = 'text/css';
        style.appendChild(doc.createTextNode(injectedCss));
        doc.head.appendChild(style);
    }

    function resizeFrame(frame) {
        try {
            var doc = frame.contentDocument || frame.contentWindow.document;
            var body = doc.body;
            var html = doc.documentElement;
            if (!body || !html) {
                return;
            }
            var height = Math.max(
                body.scrollHeight,
                body.offsetHeight,
                html.clientHeight,
                html.scrollHeight,
                html.offsetHeight,
                900
            );
            frame.style.height = (height + 80) + 'px';
        } catch (e) {
            frame.style.height = '1200px';
        }
    }

    function prepareFrame(frame) {
        try {
            var doc = frame.contentDocument || frame.contentWindow.document;
            injectCss(doc);
            resizeFrame(frame);

            var tries = 0;
            var interval = window.setInterval(function () {
                tries += 1;
                try {
                    injectCss(doc);
                    resizeFrame(frame);
                } catch (e) {
                    window.clearInterval(interval);
                }
                if (tries > 12) {
                    window.clearInterval(interval);
                }
            }, 350);
        } catch (e) {
            frame.style.height = '1200px';
        }
    }

    function bindInstructionsToggle() {
        var button = document.querySelector('[data-heyday-toggle-instructions]');
        var panel = document.querySelector('[data-heyday-instructions]');
        if (!button || !panel) {
            return;
        }
        button.addEventListener('click', function () {
            var hidden = panel.hasAttribute('hidden');
            if (hidden) {
                panel.removeAttribute('hidden');
                button.textContent = 'ⓘ Hide Instructions';
            } else {
                panel.setAttribute('hidden', 'hidden');
                button.textContent = 'ⓘ Show Instructions';
            }
        });
    }

    function init() {
        bindInstructionsToggle();
        var frame = document.getElementById('heyday-quiz-frame');
        if (!frame) {
            return;
        }
        frame.addEventListener('load', function () {
            prepareFrame(frame);
        });
        prepareFrame(frame);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
