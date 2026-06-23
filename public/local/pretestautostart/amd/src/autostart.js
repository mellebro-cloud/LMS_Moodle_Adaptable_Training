// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Automatically submit the Moodle quiz start/continue/preview form.
 *
 * @module     local_pretestautostart/autostart
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const findCandidateForms = () => {
    const selectors = [
        'form[action*="/mod/quiz/startattempt.php"]',
        'form[action*="/mod/quiz/continueattempt.php"]',
        'form[action*="/mod/quiz/attempt.php"]'
    ];

    return selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)));
};

const isPreviewForm = (form) => {
    const preview = form.querySelector('input[name="preview"]');
    return preview && preview.value === '1';
};

const showStartButtonsAgain = () => {
    document.querySelectorAll('.quizstartbuttondiv').forEach((element) => {
        element.style.display = '';
    });
};

const submitForm = (form) => {
    const submitButton = form.querySelector('input[type="submit"], button[type="submit"]');
    if (submitButton) {
        submitButton.click();
        return;
    }

    form.submit();
};

export const init = (config = {}) => {
    window.setTimeout(() => {
        const forms = findCandidateForms();
        const form = forms.find((candidate) => !isPreviewForm(candidate) || config.autopreview === true);

        if (!form) {
            showStartButtonsAgain();
            return;
        }

        submitForm(form);
    }, 250);
};
