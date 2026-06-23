define([], function() {
    return {
        init: function() {
            var printButton = document.querySelector('.hd-print');
            var fullscreenButton = document.querySelector('.hd-fullscreen');
            var searchInput = document.querySelector('.hd-search-posts');
            var sortButton = document.querySelector('.hd-sort-discussions');
            var list = document.querySelector('.hd-thread-list');
            var loadMoreButton = document.querySelector('.hd-load-more');

            if (printButton) {
                printButton.addEventListener('click', function() {
                    window.print();
                });
            }

            if (fullscreenButton) {
                fullscreenButton.addEventListener('click', function() {
                    var target = document.querySelector('.hd-forum-shell') || document.documentElement;
                    if (!document.fullscreenElement && target.requestFullscreen) {
                        target.requestFullscreen();
                    } else if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var query = searchInput.value.toLowerCase().trim();
                    document.querySelectorAll('.hd-thread').forEach(function(thread) {
                        var text = thread.getAttribute('data-search') || '';
                        var matches = !query || text.indexOf(query) !== -1;
                        thread.style.display = matches ? '' : 'none';
                    });
                });
            }

            if (sortButton && list) {
                sortButton.addEventListener('click', function() {
                    var threads = Array.prototype.slice.call(list.querySelectorAll('.hd-thread'));
                    threads.sort(function(a, b) {
                        var an = (a.querySelector('h3') ? a.querySelector('h3').textContent : '').toLowerCase();
                        var bn = (b.querySelector('h3') ? b.querySelector('h3').textContent : '').toLowerCase();
                        return an.localeCompare(bn);
                    });
                    threads.forEach(function(thread) {
                        list.appendChild(thread);
                        thread.classList.remove('hd-thread-hidden');
                        thread.style.display = '';
                    });
                    if (loadMoreButton) {
                        loadMoreButton.style.display = 'none';
                    }
                });
            }

            if (loadMoreButton) {
                loadMoreButton.addEventListener('click', function() {
                    document.querySelectorAll('.hd-thread-hidden').forEach(function(thread) {
                        thread.classList.remove('hd-thread-hidden');
                        thread.style.display = '';
                    });
                    loadMoreButton.style.display = 'none';
                });
            }

            document.querySelectorAll('.hd-report-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.alert('Report workflow can be connected to Moodle moderation policy in the next release.');
                });
            });
        }
    };
});
