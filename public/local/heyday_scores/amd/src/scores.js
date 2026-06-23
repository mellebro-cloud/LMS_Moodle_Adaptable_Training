export const init = () => {
    const search = document.getElementById('hd-score-search');
    const creditOnly = document.getElementById('hd-credit-only');
    const sortButton = document.getElementById('hd-sort-name');
    const list = document.querySelector('.hd-score-list');

    const applyFilters = () => {
        const query = search ? search.value.toLowerCase().trim() : '';
        const onlyCredit = creditOnly ? creditOnly.checked : false;

        document.querySelectorAll('.hd-score-card').forEach((card) => {
            const name = card.getAttribute('data-name') || '';
            const isCredit = card.getAttribute('data-credit') === '1';

            const matchesSearch = !query || name.includes(query);
            const matchesCredit = !onlyCredit || isCredit;

            card.style.display = matchesSearch && matchesCredit ? '' : 'none';
        });
    };

    if (search) {
        search.addEventListener('input', applyFilters);
    }

    if (creditOnly) {
        creditOnly.addEventListener('change', applyFilters);
    }

    if (sortButton && list) {
        sortButton.addEventListener('click', () => {
            const cards = Array.from(list.querySelectorAll('.hd-score-card'));
            cards.sort((a, b) => {
                return (a.getAttribute('data-name') || '').localeCompare(
                    b.getAttribute('data-name') || ''
                );
            });
            cards.forEach((card) => list.appendChild(card));
        });
    }
};
