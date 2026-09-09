import './bootstrap';

const mobileMenuToggle = document.querySelector('[data-mobile-menu-toggle]');
const mobileNavActions = document.querySelector('.nav-actions');

if (mobileMenuToggle && mobileNavActions) {
	mobileNavActions.id = 'mobile-nav-actions';
	mobileMenuToggle.addEventListener('click', () => {
		const isOpen = mobileNavActions.classList.toggle('is-open');
		mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});
}

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
	let images;

	try {
		images = JSON.parse(gallery.dataset.gallery || '[]');
	} catch {
		images = [];
	}

	if (images.length < 2) {
		return;
	}

	const urlOf = (source) => (typeof source === 'string' ? source : source.url);
	const tagOf = (source) => (typeof source === 'string' ? '' : source.tag || '');
	const altOf = (source) => (typeof source === 'string' ? '' : source.alt || '');

	let currentIndex = Number(gallery.dataset.galleryStart || 0);
	const image = gallery.querySelector('[data-gallery-image]') || gallery.querySelector('.property-image');
	const counter = gallery.querySelector('[data-gallery-counter]');
	const isImgElement = image.matches('img');
	const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	const decoded = new Map();
	const preload = (url) => {
		if (!decoded.has(url)) {
			decoded.set(url, new Promise((resolve) => {
				const loader = new Image();
				loader.onload = loader.onerror = () => resolve();
				loader.src = url;
			}));
		}

		return decoded.get(url);
	};

	const backgroundFor = (url) => `linear-gradient(rgba(0,0,0,0.15), rgba(0,0,0,0.15)), url("${url}")`;

	const applyImage = (source) => {
		const url = urlOf(source);
		if (isImgElement) {
			image.src = url;
			image.alt = altOf(source);
		} else {
			image.style.backgroundImage = backgroundFor(url);
		}
	};

	const applyMeta = (source) => {
		const tag = gallery.querySelector('[data-gallery-tag]');
		if (tag) {
			const value = tagOf(source);
			tag.textContent = value;
			tag.hidden = !value;
		}

		if (counter) {
			counter.textContent = `${currentIndex + 1} / ${images.length}`;
		}
	};

	let fadeTimer = null;
	let activeLayer = null;

	// Finish any in-flight crossfade immediately so rapid clicks never stack layers.
	const commitLayer = () => {
		if (fadeTimer) {
			window.clearTimeout(fadeTimer);
			fadeTimer = null;
		}

		if (activeLayer) {
			applyImage(images[Number(activeLayer.dataset.index)]);
			activeLayer.remove();
			activeLayer = null;
		}
	};

	let renderToken = 0;

	const render = async (animate = true) => {
		const token = ++renderToken;
		const source = images[currentIndex];
		const url = urlOf(source);

		await preload(url);

		if (token !== renderToken) {
			return;
		}

		commitLayer();
		applyMeta(source);

		if (!animate || prefersReducedMotion) {
			applyImage(source);
		} else {
			const layer = document.createElement('div');
			layer.className = 'gallery-fade-layer';
			layer.dataset.index = currentIndex;
			layer.style.backgroundImage = backgroundFor(url);
			gallery.appendChild(layer);
			activeLayer = layer;

			requestAnimationFrame(() => {
				layer.style.opacity = '1';
			});

			fadeTimer = window.setTimeout(commitLayer, 260);
		}

		preload(urlOf(images[(currentIndex + 1) % images.length]));
		preload(urlOf(images[(currentIndex - 1 + images.length) % images.length]));
	};

	gallery.querySelector('[data-gallery-prev]')?.addEventListener('click', () => {
		currentIndex = (currentIndex - 1 + images.length) % images.length;
		render();
	});

	gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => {
		currentIndex = (currentIndex + 1) % images.length;
		render();
	});

	gallery.closest('.gallery-panel')?.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
		thumb.addEventListener('click', () => {
			currentIndex = Number(thumb.dataset.galleryThumb);
			render();
		});
	});

	render(false);
});

document.querySelectorAll('[data-language-tab]').forEach((tab) => {
	tab.addEventListener('click', () => {
		const editor = tab.closest('.language-editor');
		const locale = tab.dataset.languageTab;

		editor.querySelectorAll('[data-language-tab]').forEach((item) => {
			const active = item === tab;
			item.classList.toggle('is-active', active);
			item.setAttribute('aria-selected', active ? 'true' : 'false');
		});

		editor.querySelectorAll('[data-language-panel]').forEach((panel) => {
			const active = panel.dataset.languagePanel === locale;
			panel.classList.toggle('is-active', active);
			panel.hidden = !active;
		});
	});
});

document.querySelectorAll('[data-check-availability]').forEach((button) => {
	button.addEventListener('click', async () => {
		const form = button.closest('form');
		const result = form.querySelector('[data-availability-result]');
		form.querySelectorAll('.form-error-box, .reservation-success').forEach((el) => el.remove());
		const payload = new FormData();
		payload.append('check_in', form.elements.check_in.value);
		payload.append('check_out', form.elements.check_out.value);
		button.disabled = true;
		result.textContent = '';

		try {
			const response = await fetch(form.dataset.availabilityUrl, {
				method: 'POST',
				headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
				body: payload,
			});
			if (!response.ok) {
				throw new Error('Availability request failed');
			}
			const data = await response.json();
			result.textContent = data.available ? form.dataset.availabilityAvailable : form.dataset.availabilityUnavailable;
		} catch {
			result.textContent = form.dataset.availabilityUnavailable;
		} finally {
			button.disabled = false;
		}
	});
});

document.querySelectorAll('[data-property-tab]').forEach((tab) => {
	tab.addEventListener('click', () => {
		const editor = tab.closest('[data-property-tabs]');
		const tabName = tab.dataset.propertyTab;

		editor.querySelectorAll('[data-property-tab]').forEach((item) => {
			const active = item === tab;
			item.classList.toggle('is-active', active);
			item.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		editor.querySelectorAll('[data-property-panel]').forEach((panel) => {
			const active = panel.dataset.propertyPanel === tabName;
			panel.classList.toggle('is-active', active);
			panel.hidden = !active;
		});
		window.sessionStorage.setItem(`admin-editor-tab:${window.location.pathname}`, tabName);
		window.history.replaceState(null, '', `#${tabName}`);
	});
});

document.querySelectorAll('[data-property-tabs]').forEach((editor) => {
	const requestedTab = window.location.hash.slice(1) || window.sessionStorage.getItem(`admin-editor-tab:${window.location.pathname}`);
	const tab = editor.querySelector(`[data-property-tab="${requestedTab}"]`);
	tab?.click();

	editor.querySelectorAll('form').forEach((form) => {
		form.addEventListener('submit', () => {
			const activeTab = editor.querySelector('[data-property-tab].is-active')?.dataset.propertyTab || 'general';
			let field = form.querySelector('input[name="active_tab"]');
			if (!field) {
				field = document.createElement('input');
				field.type = 'hidden';
				field.name = 'active_tab';
				form.appendChild(field);
			}
			field.value = activeTab;
		});
	});
});

document.querySelectorAll('[data-currency-pair]').forEach((input) => {
	input.addEventListener('input', () => {
		const pair = input.dataset.currencyPair;
		const rate = Number(input.dataset.xofPerEur);
		const value = Number(input.value);
		const target = document.querySelector(`[data-currency-pair="${pair}"][data-currency-input="${input.dataset.currencyInput === 'xof' ? 'eur' : 'xof'}"]`);

		if (!target || !Number.isFinite(rate) || rate <= 0 || !Number.isFinite(value)) {
			return;
		}

		target.value = (input.dataset.currencyInput === 'xof' ? value / rate : value * rate).toFixed(2);
	});
});

document.querySelectorAll('[data-photo-sortable]').forEach((list) => {
	const form = list.closest('form');
	const status = form?.querySelector('[data-photo-order-status]');
	let draggedRow = null;

	const rows = () => Array.from(list.querySelectorAll('[data-photo-id]'));
	const currentOrder = () => rows().map((row) => row.dataset.photoId).join(',');
	const initialOrder = currentOrder();
	const initialPositions = new Map(rows().map((row, index) => [row.dataset.photoId, index + 1]));

	const clearDropTargets = () => {
		rows().forEach((row) => row.classList.remove('is-drop-before', 'is-drop-after'));
	};

	const dropsAfter = (row, event) => {
		const bounds = row.getBoundingClientRect();
		return event.clientY > bounds.top + bounds.height / 2;
	};

	const updatePhotoOrder = () => {
		rows().forEach((row, index) => {
			const currentPosition = index + 1;
			const order = row.querySelector('[data-photo-order]');
			if (order) {
				order.value = currentPosition;
			}

			const position = row.querySelector('[data-photo-position]');
			if (position) {
				position.textContent = currentPosition;
			}

			row.classList.toggle('is-order-changed', initialPositions.get(row.dataset.photoId) !== currentPosition);
		});

		if (status) {
			status.hidden = currentOrder() === initialOrder;
		}
	};

	rows().forEach((row) => {
		row.addEventListener('dragstart', (event) => {
			draggedRow = row;
			row.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			// Firefox refuses to start a drag unless data is attached.
			event.dataTransfer.setData('text/plain', row.dataset.photoId ?? '');
		});

		row.addEventListener('dragend', () => {
			row.classList.remove('is-dragging');
			clearDropTargets();
			draggedRow = null;
			updatePhotoOrder();
		});

		row.addEventListener('dragover', (event) => {
			if (!draggedRow || draggedRow === row) {
				return;
			}

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			clearDropTargets();
			row.classList.add(dropsAfter(row, event) ? 'is-drop-after' : 'is-drop-before');
		});

		row.addEventListener('dragleave', () => {
			row.classList.remove('is-drop-before', 'is-drop-after');
		});

		row.addEventListener('drop', (event) => {
			if (!draggedRow || draggedRow === row) {
				return;
			}

			event.preventDefault();
			list.insertBefore(draggedRow, dropsAfter(row, event) ? row.nextSibling : row);
			clearDropTargets();
			updatePhotoOrder();
		});
	});

	updatePhotoOrder();
});

document.querySelectorAll('[data-copy-payment-link]').forEach((button) => {
	button.addEventListener('click', async () => {
		const status = button.parentElement?.querySelector('[data-copy-payment-link-status]');

		try {
			await navigator.clipboard.writeText(button.dataset.copyPaymentLink ?? '');
			if (status) {
				status.textContent = button.dataset.copyPaymentLinkSuccess ?? 'Payment link copied.';
				status.hidden = false;
			}
		} catch {
			if (status) {
				status.textContent = button.dataset.copyPaymentLinkError ?? 'Unable to copy the payment link.';
				status.hidden = false;
			}
		}
	});
});

document.querySelectorAll('[data-copy-share-link]').forEach((button) => {
	button.addEventListener('click', async () => {
		const status = button.closest('.share-panel')?.querySelector('[data-copy-share-link-status]');

		try {
			await navigator.clipboard.writeText(button.dataset.copyShareLink ?? '');
			if (status) {
				status.textContent = button.dataset.copyShareLinkSuccess ?? 'Link copied.';
				status.hidden = false;
			}
		} catch {
			if (status) {
				status.textContent = button.dataset.copyShareLinkError ?? 'Unable to copy the link.';
				status.hidden = false;
			}
		}
	});
});

document.querySelectorAll('[data-availability-calendar]').forEach((calendar) => {
	const parseRanges = (value) => {
		try {
			return JSON.parse(value || '[]');
		} catch {
			return [];
		}
	};
	const blocked = parseRanges(calendar.dataset.blocked);
	const reserved = parseRanges(calendar.dataset.reserved);
	const external = parseRanges(calendar.dataset.external);
	const today = new Date();
	let displayedMonth = new Date(today.getFullYear(), today.getMonth(), 1);
	const inRange = (date, ranges) => ranges.some(([start, end]) => date >= start && date <= end);
	const headings = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

	const renderMonth = (monthDate) => {
		const year = monthDate.getFullYear();
		const month = monthDate.getMonth();
		const firstDay = new Date(year, month, 1);
		const daysInMonth = new Date(year, month + 1, 0).getDate();
		const formatDate = (day) => `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
		const days = [];
		for (let blank = 0; blank < firstDay.getDay(); blank += 1) days.push('<span class="availability-day availability-empty"></span>');
		for (let day = 1; day <= daysInMonth; day += 1) {
			const date = formatDate(day);
			const past = date < today.toISOString().slice(0, 10);
			const status = past ? 'past' : (inRange(date, external) ? 'external' : (inRange(date, blocked) || inRange(date, reserved) ? 'blocked' : 'available'));
			days.push(`<span class="availability-day availability-${status}">${day}</span>`);
		}
		return `<div class="availability-month"><strong class="availability-month-title">${monthDate.toLocaleString(undefined, { month: 'long', year: 'numeric' })}</strong><div class="availability-calendar-grid">${headings.map((heading) => `<strong>${heading}</strong>`).join('')}${days.join('')}</div></div>`;
	};

	const render = () => {
		const secondMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1);
		calendar.innerHTML = `<div class="availability-calendar-title"><button type="button" data-calendar-prev aria-label="Previous two months">&larr;</button><span>${displayedMonth.toLocaleString(undefined, { month: 'short', year: 'numeric' })} - ${secondMonth.toLocaleString(undefined, { month: 'short', year: 'numeric' })}</span><button type="button" data-calendar-next aria-label="Next two months">&rarr;</button></div><div class="availability-calendar-months">${renderMonth(displayedMonth)}${renderMonth(secondMonth)}</div>`;
		calendar.querySelector('[data-calendar-prev]').addEventListener('click', () => { displayedMonth.setMonth(displayedMonth.getMonth() - 1); render(); });
		calendar.querySelector('[data-calendar-next]').addEventListener('click', () => { displayedMonth.setMonth(displayedMonth.getMonth() + 1); render(); });
	};

	render();
});

document.querySelectorAll('[data-toggle-availability]').forEach((button) => {
	button.addEventListener('click', () => {
		const content = document.getElementById(button.getAttribute('aria-controls'));
		const expanded = button.getAttribute('aria-expanded') === 'true';
		button.setAttribute('aria-expanded', String(!expanded));
		button.textContent = expanded ? button.dataset.showLabel : button.dataset.hideLabel;
		if (content) content.hidden = expanded;
	});
});
