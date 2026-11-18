<script>
(function () {
    const form = document.getElementById('roomRatesForm');
    if (!form) return;

    const cells = Array.from(form.querySelectorAll('.rate-cell'));
    const hiddenBox = document.getElementById('roomRatesCells');
    const unsavedCount = document.getElementById('unsavedCount');
    const unsavedPlural = document.getElementById('unsavedPlural');
    const saveBtn = document.getElementById('saveChanges');
    const discardBtn = document.getElementById('discardChanges');
    const selectAll = document.getElementById('selectAllRooms');
    const roomChecks = Array.from(form.querySelectorAll('.room-select'));
    const selectedCount = document.getElementById('selectedCount');
    const bulkMessage = document.getElementById('bulkMessage');
    let submitting = false;

    const norm = (v) => {
        if (v === null || v === undefined || String(v).trim() === '') return '';
        const n = parseFloat(v);
        return isNaN(n) ? String(v) : String(Math.round(n * 100) / 100);
    };
    const isDirty = (input) => norm(input.value) !== norm(input.dataset.original);
    const peso = (v) => v === '' ? 'empty (regular rate)' : '₱' + parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });

    function refresh() {
        let dirty = 0;
        cells.forEach((input) => {
            const changed = isDirty(input);
            input.classList.toggle('bg-yellow-50', changed);
            input.classList.toggle('border-yellow-400', changed);
            input.title = changed
                ? 'Was ' + peso(input.dataset.original)
                : (input.dataset.platform ? 'Empty = uses the regular rate' : 'Regular rate');
            if (changed) dirty++;
        });
        unsavedCount.textContent = dirty;
        unsavedPlural.textContent = dirty === 1 ? '' : 's';
        saveBtn.disabled = dirty === 0;
        discardBtn.disabled = dirty === 0;
    }

    // Platform placeholders follow the room's (possibly edited) regular rate
    function refreshPlaceholders(suiteId) {
        const regular = form.querySelector(`.regular-cell[data-suite="${suiteId}"]`);
        form.querySelectorAll(`.rate-cell[data-suite="${suiteId}"]:not(.regular-cell)`).forEach((c) => {
            c.placeholder = regular ? norm(regular.value) : '';
        });
    }

    cells.forEach((input) => input.addEventListener('input', () => {
        if (input.classList.contains('regular-cell')) refreshPlaceholders(input.dataset.suite);
        refresh();
    }));

    discardBtn.addEventListener('click', () => {
        cells.forEach((input) => { input.value = input.dataset.original; });
        roomChecks.forEach((c) => refreshPlaceholders(c.value));
        refresh();
    });

    // Room selection
    function refreshSelection() {
        const n = roomChecks.filter((c) => c.checked).length;
        selectedCount.textContent = n;
        selectAll.checked = n > 0 && n === roomChecks.length;
        selectAll.indeterminate = n > 0 && n < roomChecks.length;
    }
    selectAll.addEventListener('change', () => {
        roomChecks.forEach((c) => { c.checked = selectAll.checked; });
        refreshSelection();
    });
    roomChecks.forEach((c) => c.addEventListener('change', refreshSelection));

    // Apply to selected rooms: fills cells only, nothing is saved until Save
    document.getElementById('bulkApply').addEventListener('click', () => {
        const column = document.getElementById('bulkColumn').value;
        const action = document.getElementById('bulkAction').value;
        const amount = parseFloat(document.getElementById('bulkValue').value);
        const rooms = roomChecks.filter((c) => c.checked).map((c) => c.value);
        bulkMessage.style.display = 'none';

        if (rooms.length === 0) return showBulk('Select at least one room first.');
        if (isNaN(amount) || amount < 0) return showBulk('Enter an amount.');

        rooms.forEach((suiteId) => {
            const input = form.querySelector(`.rate-cell[data-suite="${suiteId}"][data-platform="${column}"]`);
            if (!input) return;
            const regular = parseFloat(form.querySelector(`.regular-cell[data-suite="${suiteId}"]`).value) || 0;
            // A platform cell without its own rate starts from the regular rate
            const current = input.value === '' ? regular : parseFloat(input.value) || 0;
            let next;
            switch (action) {
                case 'set': next = amount; break;
                case 'add': next = current + amount; break;
                case 'sub': next = current - amount; break;
                case 'pct_up': next = Math.round(current * (1 + amount / 100)); break;
                case 'pct_down': next = Math.round(current * (1 - amount / 100)); break;
            }
            next = Math.max(0, Math.round(next * 100) / 100);
            input.value = norm(next);
            if (input.classList.contains('regular-cell')) refreshPlaceholders(suiteId);
        });
        refresh();
    });
    function showBulk(text) {
        bulkMessage.textContent = text;
        bulkMessage.style.display = 'block';
    }

    // Submit only the edited cells
    form.addEventListener('submit', (e) => {
        hiddenBox.innerHTML = '';
        const changed = cells.filter(isDirty);
        if (changed.length === 0) { e.preventDefault(); return; }
        changed.forEach((input, i) => {
            [['venue_id', input.dataset.suite], ['platform_id', input.dataset.platform], ['price', input.value]].forEach(([k, v]) => {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = `cells[${i}][${k}]`;
                h.value = v;
                hiddenBox.appendChild(h);
            });
        });
        // Visible inputs carry no name, so only the hidden cells are posted
        submitting = true;
    });

    window.addEventListener('beforeunload', (e) => {
        if (!submitting && cells.some(isDirty)) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Highlight the room opened from a Suites "Rates" link
    if (location.hash.startsWith('#suite-')) {
        const row = document.querySelector(location.hash);
        if (row) {
            row.dataset.highlighted = '1';
            // Inline style so it wins over the sticky cells' bg-white
            row.querySelectorAll('td').forEach((td) => { td.style.backgroundColor = '#faf5ff'; });
            row.scrollIntoView({ block: 'center' });
        }
    }

    refresh();
    refreshSelection();
})();
</script>
