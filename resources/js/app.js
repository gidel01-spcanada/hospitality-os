import './bootstrap';

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

	let currentIndex = Number(gallery.dataset.galleryStart || 0);
	const image = gallery.querySelector('[data-gallery-image]') || gallery.querySelector('.property-image');
	const counter = gallery.querySelector('[data-gallery-counter]');

	const render = () => {
		const source = images[currentIndex];
		const imageUrl = typeof source === 'string' ? source : source.url;
		const imageTag = typeof source === 'string' ? '' : (source.tag || '');

		if (image.matches('img')) {
			image.src = imageUrl;
		} else {
			image.style.backgroundImage = `linear-gradient(rgba(0,0,0,0.15), rgba(0,0,0,0.15)), url("${imageUrl}")`;
		}

		const tag = gallery.querySelector('[data-gallery-tag]');
		if (tag) {
			tag.textContent = imageTag;
			tag.hidden = !imageTag;
		}

		if (counter) {
			counter.textContent = `${currentIndex + 1} / ${images.length}`;
		}
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

	render();
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
		window.history.replaceState(null, '', `#${tabName}`);
	});
});

document.querySelectorAll('[data-property-tabs]').forEach((editor) => {
	const requestedTab = window.location.hash.slice(1);
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

document.querySelectorAll('[data-photo-sortable]').forEach((list) => {
	let draggedRow = null;

	const updatePhotoOrder = () => {
		list.querySelectorAll('[data-photo-id]').forEach((row, index) => {
			const order = row.querySelector('[data-photo-order]');
			if (order) {
				order.value = index + 1;
			}
		});
	};

	list.querySelectorAll('[data-photo-id]').forEach((row) => {
		row.addEventListener('dragstart', () => {
			draggedRow = row;
			row.classList.add('is-dragging');
		});
		row.addEventListener('dragend', () => {
			row.classList.remove('is-dragging');
			draggedRow = null;
			updatePhotoOrder();
		});
		row.addEventListener('dragover', (event) => {
			event.preventDefault();
			if (draggedRow && draggedRow !== row) {
				const bounds = row.getBoundingClientRect();
				const insertAfter = event.clientY > bounds.top + bounds.height / 2;
				row.parentNode.insertBefore(draggedRow, insertAfter ? row.nextSibling : row);
			}
		});
	});

	updatePhotoOrder();
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

	const render = () => {
		const year = displayedMonth.getFullYear();
		const month = displayedMonth.getMonth();
		const firstDay = new Date(year, month, 1);
		const daysInMonth = new Date(year, month + 1, 0).getDate();
		const formatDate = (day) => `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
		calendar.innerHTML = `<div class="availability-calendar-title"><button type="button" data-calendar-prev aria-label="Previous month">&larr;</button><strong>${displayedMonth.toLocaleString(undefined, { month: 'long', year: 'numeric' })}</strong><button type="button" data-calendar-next aria-label="Next month">&rarr;</button></div><div class="availability-calendar-grid">${headings.map((heading) => `<strong>${heading}</strong>`).join('')}</div>`;
		const grid = calendar.querySelector('.availability-calendar-grid');
		for (let blank = 0; blank < firstDay.getDay(); blank += 1) {
			grid.insertAdjacentHTML('beforeend', '<span class="availability-day availability-empty"></span>');
		}
		for (let day = 1; day <= daysInMonth; day += 1) {
			const date = formatDate(day);
			const past = date < today.toISOString().slice(0, 10);
			const status = past ? 'past' : (inRange(date, external) ? 'external' : (inRange(date, blocked) || inRange(date, reserved) ? 'blocked' : 'available'));
			grid.insertAdjacentHTML('beforeend', `<span class="availability-day availability-${status}">${day}</span>`);
		}
		calendar.querySelector('[data-calendar-prev]').addEventListener('click', () => { displayedMonth.setMonth(displayedMonth.getMonth() - 1); render(); });
		calendar.querySelector('[data-calendar-next]').addEventListener('click', () => { displayedMonth.setMonth(displayedMonth.getMonth() + 1); render(); });
	};

	render();
});
