define([], function() {
    return {
        init: function(courseid) {
            var target = M.cfg.wwwroot + '/local/heyday_discussions/index.php?id=' + encodeURIComponent(courseid);

            /**
             *
             * @param text
             */
            function cleanText(text) {
                return (text || '').replace(/\s+/g, ' ').trim();
            }

            /**
             *
             * @param link
             */
            function isDiscussionsCourseSection(link) {
                var label = cleanText(link.textContent);
                var href = link.getAttribute('href') || '';

                if (label !== 'Discussions') {
                    return false;
                }

                return href.indexOf('/course/view.php') !== -1 ||
                       href.indexOf('/course/section.php') !== -1 ||
                       href.indexOf('#section') !== -1;
            }

            /**
             *
             */
            function applyRedirect() {
                document.querySelectorAll('a').forEach(function(link) {
                    if (!isDiscussionsCourseSection(link)) {
                        return;
                    }

                    link.setAttribute('href', target);

                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.location.href = target;
                    });
                });
            }

            applyRedirect();

            // Moodle course index can update dynamically, so apply again shortly after load.
            window.setTimeout(applyRedirect, 600);
            window.setTimeout(applyRedirect, 1500);
        }
    };
});