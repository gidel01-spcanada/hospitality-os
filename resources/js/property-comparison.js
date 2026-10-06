const comparison = document.querySelector('[data-comparison-page]');

if (comparison) {
    const form = comparison.querySelector('[data-comparison-controls]');
    const content = comparison.querySelector('[data-comparison-content]');
    const feedback = comparison.querySelector('[data-comparison-feedback]');
    const selected = comparison.querySelector('[data-comparison-selected]');
    const addSelect = comparison.querySelector('[data-comparison-add]');
    const addButton = comparison.querySelector('[data-comparison-add-button]');
    try {
        const previousUrl = sessionStorage.getItem('afrikappart-compare-return');
        if (previousUrl) {
            const previous = new URL(previousUrl, window.location.href);
            if (previous.origin === window.location.origin && (previous.pathname === '/properties' || previous.pathname.startsWith('/establishments/'))) {
                comparison.querySelector('[data-comparison-back]').href = previous.toString();
            }
        }
    } catch {}
    let controller;
    let timer;
    let lastUrl = window.location.href;
    let requestNumber = 0;
    let inFlightUrl = null;
    const ids = () => [...selected.querySelectorAll('input')].map((input) => input.value);
    const maximum = () => window.matchMedia('(max-width: 760px)').matches ? 3 : 4;
    const rememberSelection = () => {
        try { localStorage.setItem('afrikappart-compare', JSON.stringify(ids())); } catch {}
    };
    const updateControls = () => {
        const currentIds = ids();
        addButton.disabled = currentIds.length >= maximum() || !addSelect.value;
        [...addSelect.options].forEach((option) => { option.disabled = currentIds.includes(option.value); });
        const mode = comparison.querySelector('[name="comparison_mode"]:checked').value;
        comparison.querySelector('.comparison-mode').hidden = currentIds.length < 2;
        content.querySelectorAll('[data-comparison-row]').forEach((row) => {
            row.hidden = mode === 'differences' && row.dataset.different !== 'true';
        });
        content.querySelectorAll('[data-comparison-rows]').forEach((section) => {
            const message = section.querySelector('[data-comparison-no-difference]');
            if (message) message.hidden = Boolean(section.querySelector('[data-comparison-row]:not([hidden])'));
        });
    };
    const valid = () => {
        const arrival = form.elements.check_in;
        const departure = form.elements.check_out;
        const invalid = Boolean(arrival.value) !== Boolean(departure.value)
            || (arrival.value && departure.value && departure.value <= arrival.value);
        departure.setCustomValidity(invalid ? comparison.dataset.invalidDates : '');

        return form.reportValidity();
    };
    const refresh = async (force = false) => {
        clearTimeout(timer);
        if (!valid()) return;
        const url = new URL(comparison.dataset.endpoint, window.location.href);
        for (const [name, value] of new FormData(form)) {
            if (value !== '') url.searchParams.append(name, value);
        }
        if (!force && url.toString() === inFlightUrl) return;
        if (!force && url.toString() === lastUrl) {
            controller?.abort();
            requestNumber++;
            inFlightUrl = null;
            content.removeAttribute('aria-busy');
            content.classList.remove('comparison-stale');
            feedback.textContent = comparison.dataset.ready;
            return;
        }
        controller?.abort();
        controller = new AbortController();
        inFlightUrl = url.toString();
        const currentRequest = ++requestNumber;
        content.setAttribute('aria-busy', 'true');
        content.classList.add('comparison-stale');
        feedback.textContent = comparison.dataset.loading;
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Comparison unavailable');
            const result = await response.json();
            if (currentRequest !== requestNumber) return;
            const collapsed = new Map([...content.querySelectorAll('[data-comparison-collapse]')].map((button) => [button.getAttribute('aria-controls'), button.getAttribute('aria-expanded')]));
            content.innerHTML = result.html;
            content.querySelectorAll('[data-comparison-collapse]').forEach((button) => {
                const expanded = collapsed.get(button.getAttribute('aria-controls'));
                if (expanded !== undefined) {
                    button.setAttribute('aria-expanded', expanded);
                    content.querySelector(`#${button.getAttribute('aria-controls')}`).hidden = expanded === 'false';
                    button.lastElementChild.textContent = expanded === 'false' ? '+' : '−';
                }
            });
            const currentIds = result.properties.map(String);
            selected.querySelectorAll('input').forEach((input) => { if (!currentIds.includes(input.value)) input.remove(); });
            if (result.currencies) {
                const currencySelect = form.elements.currency;
                currencySelect.replaceChildren(...result.currencies.map((currency) => new Option(currency, currency, false, currency === result.currency)));
            }
            url.searchParams.delete('properties[]');
            currentIds.forEach((id) => url.searchParams.append('properties[]', id));
            if (result.currencies.length) url.searchParams.set('currency', result.currency);
            else url.searchParams.delete('currency');
            lastUrl = url.toString();
            history.replaceState(null, '', url);
            content.classList.remove('comparison-stale');
            feedback.textContent = comparison.dataset.ready;
            updateControls();
            rememberSelection();
        } catch (error) {
            if (error.name !== 'AbortError') feedback.textContent = comparison.dataset.error;
        } finally {
            if (currentRequest === requestNumber) {
                inFlightUrl = null;
                content.removeAttribute('aria-busy');
            }
        }
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); refresh(true); });
    form.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(() => refresh(), 250);
    });
    comparison.querySelectorAll('[name="comparison_mode"]').forEach((input) => input.addEventListener('change', updateControls));
    addSelect.addEventListener('change', updateControls);
    addButton.addEventListener('click', () => {
        if (!addSelect.value || ids().length >= maximum() || ids().includes(addSelect.value)) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'properties[]';
        input.value = addSelect.value;
        selected.append(input);
        addSelect.value = '';
        updateControls();
        refresh();
    });
    content.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-comparison-remove]');
        if (remove) {
            selected.querySelectorAll('input').forEach((input) => { if (input.value === remove.dataset.comparisonRemove) input.remove(); });
            updateControls();
            refresh();
            return;
        }
        const collapse = event.target.closest('[data-comparison-collapse]');
        if (collapse) {
            const expanded = collapse.getAttribute('aria-expanded') !== 'true';
            collapse.setAttribute('aria-expanded', String(expanded));
            content.querySelector(`#${collapse.getAttribute('aria-controls')}`).hidden = !expanded;
            collapse.lastElementChild.textContent = expanded ? '−' : '+';
        }
    });
    comparison.querySelector('[data-comparison-copy]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(window.location.href);
            feedback.textContent = comparison.dataset.copied;
        } catch { feedback.textContent = comparison.dataset.copyError; }
    });
    window.addEventListener('resize', updateControls);
    updateControls();
    rememberSelection();
}