import './bootstrap';

const analyticsConfig = window.__ANALYTICS_CONFIG__ || null;
let analyticsReady = false;

const loadAnalytics = () => {
	if (analyticsReady || !analyticsConfig) return;
	analyticsReady = true;

	if (analyticsConfig.ga4MeasurementId && !analyticsConfig.gtmContainerId) {
		window.dataLayer = window.dataLayer || [];
		window.gtag = (...args) => window.dataLayer.push(args);
		window.gtag('js', new Date());
		window.gtag('config', analyticsConfig.ga4MeasurementId, { anonymize_ip: true });
		const script = document.createElement('script');
		script.async = true;
		script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(analyticsConfig.ga4MeasurementId)}`;
		document.head.appendChild(script);
	}

	if (analyticsConfig.gtmContainerId) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
		const script = document.createElement('script');
		script.async = true;
		script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(analyticsConfig.gtmContainerId)}`;
		document.head.appendChild(script);
	}

	window.trackEvent = (name, parameters = {}) => {
		const safeParameters = Object.fromEntries(Object.entries(parameters).filter(([key, value]) =>
			!/(email|phone|name|address|token|reservation)/i.test(key) && ['string', 'number', 'boolean'].includes(typeof value)));
		if (typeof window.gtag === 'function') window.gtag('event', name, safeParameters);
		window.dataLayer?.push({ event: name, ...safeParameters });
	};
	window.trackEvent('page_view', { page_path: window.location.pathname, page_language: document.documentElement.lang });
};

const analyticsConsent = document.querySelector('[data-analytics-consent]');
if (analyticsConsent) {
	const storedConsent = window.localStorage.getItem('afrik_analytics_consent');
	if (storedConsent === 'accepted') loadAnalytics();
	if (!storedConsent) analyticsConsent.hidden = false;
	analyticsConsent.querySelector('[data-analytics-consent-accept]')?.addEventListener('click', () => {
		window.localStorage.setItem('afrik_analytics_consent', 'accepted');
		analyticsConsent.hidden = true;
		loadAnalytics();
	});
	analyticsConsent.querySelector('[data-analytics-consent-decline]')?.addEventListener('click', () => {
		window.localStorage.setItem('afrik_analytics_consent', 'declined');
		analyticsConsent.hidden = true;
	});
}

document.addEventListener('click', (event) => {
	const propertyLink = event.target.closest('a.property-link, a.property-gallery-link');
	if (propertyLink) window.trackEvent?.('select_property', { page_path: new URL(propertyLink.href).pathname });
});

document.addEventListener('submit', (event) => {
	if (event.target.matches('#compare-form')) window.trackEvent?.('compare_properties', { selected_count: event.target.querySelectorAll('[data-compare-option]:checked').length });
	if (event.target.matches('form[action*="/reserve"], form[action*="/checkout"]')) window.trackEvent?.('start_booking', { page_path: window.location.pathname });
});

// Fallback for browsers that don't yet support the native `name` attribute grouping on <details>.
document.addEventListener('toggle', (event) => {
	const details = event.target;
	if (details.tagName !== 'DETAILS' || !details.open || !details.name) return;

	document.querySelectorAll(`details[name="${details.name}"]`).forEach((other) => {
		if (other !== details) other.open = false;
	});
}, true);

const confirmDialog = document.querySelector('[data-confirm-dialog]');
let pendingConfirmationForm = null;

if (confirmDialog) {
	const message = confirmDialog.querySelector('[data-confirm-dialog-message]');
	const cancel = confirmDialog.querySelector('[data-confirm-dialog-cancel]');
	const accept = confirmDialog.querySelector('[data-confirm-dialog-accept]');

	document.addEventListener('submit', (event) => {
		const isDelete = event.target.querySelector('input[name="_method"][value="DELETE"]');
		const confirmation = event.submitter?.dataset.confirmMessage
			|| event.target.dataset.confirmMessage
			|| (isDelete ? confirmDialog.dataset.defaultMessage : '');
		if (!confirmation || event.target.dataset.confirmed === 'true') {
			return;
		}

		event.preventDefault();
		pendingConfirmationForm = event.target;
		message.textContent = confirmation;
		confirmDialog.showModal();
		cancel.focus();
	});

	cancel.addEventListener('click', () => confirmDialog.close());
	accept.addEventListener('click', () => {
		if (!pendingConfirmationForm) {
			return;
		}

		pendingConfirmationForm.dataset.confirmed = 'true';
		confirmDialog.close();
		pendingConfirmationForm.requestSubmit();
		pendingConfirmationForm = null;
	});
	confirmDialog.addEventListener('close', () => {
		pendingConfirmationForm = null;
	});
}

const mobileMenuToggle = document.querySelector('[data-mobile-menu-toggle]');
const mobileNavActions = document.querySelector('.nav-actions');

if (mobileMenuToggle && mobileNavActions) {
	mobileNavActions.id = 'mobile-nav-actions';
	mobileMenuToggle.addEventListener('click', () => {
		const isOpen = mobileNavActions.classList.toggle('is-open');
		mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});
}

document.querySelector('[data-account-tabs] .is-active')?.scrollIntoView({
	block: 'nearest',
	inline: 'center',
});

document.querySelectorAll('.admin-flash.alert-success').forEach((flash) => {
	setTimeout(() => {
		flash.classList.add('is-dismissing');
		flash.addEventListener('transitionend', () => flash.remove(), { once: true });
	}, 4000);
});

document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());

if (document.body.classList.contains('admin-layout')) {
	const scrollKey = `admin-scroll:${window.location.pathname}${window.location.search}`;
	const resetScrollKey = `admin-scroll-reset:${window.location.pathname}`;
	const sidebar = document.querySelector('#adminSidebar');
	const sidebarScrollKey = 'admin-sidebar-scroll';
	const savedScroll = window.sessionStorage.getItem(scrollKey);
	const invalidControl = document.querySelector('[aria-invalid="true"], .is-error, .form-error-box, .form-alert-error');
	if (window.sessionStorage.getItem(resetScrollKey) === 'true') {
		window.sessionStorage.removeItem(resetScrollKey);
		window.sessionStorage.removeItem(scrollKey);
		window.requestAnimationFrame(() => window.scrollTo({ top: 0, behavior: 'auto' }));
	} else if (invalidControl) {
		window.sessionStorage.removeItem(scrollKey);
		window.requestAnimationFrame(() => invalidControl.scrollIntoView({ block: 'center', behavior: 'auto' }));
	} else if (savedScroll !== null) {
		window.sessionStorage.removeItem(scrollKey);
		window.requestAnimationFrame(() => window.scrollTo({ top: Number(savedScroll), behavior: 'auto' }));
	}

	document.addEventListener('submit', (event) => {
		if (event.defaultPrevented || !event.target.matches('form')) return;
		window.sessionStorage.setItem(scrollKey, String(window.scrollY));
	});

	document.querySelectorAll('a[href*="/create"], a[href*="/edit"]').forEach((link) => {
		link.addEventListener('click', () => {
			const destination = new URL(link.href, window.location.origin);
			window.sessionStorage.setItem(`admin-scroll-reset:${destination.pathname}`, 'true');
		});
	});

	if (sidebar) {
		const savedSidebarScroll = window.sessionStorage.getItem(sidebarScrollKey);
		if (savedSidebarScroll !== null) {
			window.requestAnimationFrame(() => { sidebar.scrollTop = Number(savedSidebarScroll); });
		}
		const saveSidebarScroll = () => window.sessionStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop));
		sidebar.addEventListener('scroll', saveSidebarScroll, { passive: true });
		sidebar.querySelectorAll('.admin-nav-link').forEach((link) => link.addEventListener('click', saveSidebarScroll));
	}
}

document.querySelectorAll('[data-admin-modal-open]').forEach((button) => {
	const modal = document.querySelector(`[data-admin-modal="${button.dataset.adminModalOpen}"]`);
	if (!modal) return;
	button.addEventListener('click', () => {
		modal.showModal();
		modal.querySelector('input:not([type="hidden"]), select, textarea, button:not([data-admin-modal-close])')?.focus();
	});
});

document.querySelectorAll('[data-admin-modal]').forEach((modal) => {
	modal.querySelector('[data-admin-modal-close]')?.addEventListener('click', () => modal.close());
	modal.addEventListener('click', (event) => {
		if (event.target === modal) modal.close();
	});
});

document.querySelectorAll('[data-host-mode]').forEach((mode) => {
	const form = mode.closest('form');
	const existingField = form?.querySelector('[data-host-existing-field]');
	const createFields = form?.querySelector('[data-host-create-fields]');
	const update = () => {
		const existing = mode.value === 'existing';
		if (existingField) existingField.hidden = !existing;
		if (createFields) createFields.hidden = existing;
		createFields?.querySelectorAll('input').forEach((input) => { input.disabled = existing; });
		existingField?.querySelectorAll('select').forEach((select) => { select.disabled = !existing; });
	};
	mode.addEventListener('change', update);
	update();
});

// Cleaning visit create/edit now use dedicated pages instead of dynamically-generated modals.

// Property rules, availability, and calendars now use dedicated create/edit pages instead of dynamically-generated modals.

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

document.querySelectorAll('[data-video-embed]').forEach((button) => {
	button.addEventListener('click', () => {
		const iframe = document.createElement('iframe');
		iframe.src = button.dataset.videoEmbed;
		iframe.title = button.dataset.videoTitle || 'Property video';
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		iframe.allowFullscreen = true;
		iframe.loading = 'lazy';
		button.replaceWith(iframe);
	});
});

document.querySelectorAll('form').forEach((form) => {
	const pairs = [['effective_from', 'effective_to'], ['date_from', 'date_to'], ['check_in', 'check_out']];
	for (const [startName, endName] of pairs) {
		const startInput = form.querySelector(`input[type="date"][name="${startName}"]`);
		const endInput = form.querySelector(`input[type="date"][name="${endName}"]`);
		if (!startInput || !endInput || form.querySelector('[data-date-range-picker]')) continue;
		const picker = document.createElement('div');
		picker.className = 'date-range-picker admin-date-range-picker';
		picker.dataset.dateRangePicker = '';
		picker.dataset.incompleteMessage = 'Select both dates';
		picker.dataset.pastMessage = 'Invalid date';
		picker.dataset.startLabel = 'End';
		picker.dataset.endLabel = 'End';
		picker.dataset.placeholder = 'Start / End';
		picker.innerHTML = `<label><span>Start / End</span><button type="button" class="date-range-trigger" data-date-range-trigger aria-expanded="false"><span data-date-range-label>Start / End</span></button></label><input type="hidden" name="${startName}" value="${startInput.value}" data-date-range-start><input type="hidden" name="${endName}" value="${endInput.value}" data-date-range-end><div class="date-range-popover" data-date-range-popover hidden></div>`;
		startInput.closest('label, div')?.setAttribute('hidden', 'hidden');
		endInput.closest('label, div')?.setAttribute('hidden', 'hidden');
		startInput.disabled = true;
		endInput.disabled = true;
		const anchor = startInput.closest('label, div') || startInput;
		anchor.parentNode?.insertBefore(picker, anchor);
		break;
	}
});

document.querySelectorAll('[data-date-range-picker]').forEach((picker) => {
	const trigger = picker.querySelector('[data-date-range-trigger]');
	const popover = picker.querySelector('[data-date-range-popover]');
	const label = picker.querySelector('[data-date-range-label]');
	const startInput = picker.querySelector('[data-date-range-start]');
	const endInput = picker.querySelector('[data-date-range-end]');
	let start = startInput.value || '';
	let end = endInput.value || '';
	const todayValue = new Date().toISOString().slice(0, 10);
	const allowPastDates = picker.classList.contains('admin-date-range-picker') && !picker.hasAttribute('data-disable-past-dates');
	let visibleMonth = start ? new Date(`${start}T12:00:00`) : new Date();
	visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);

	const formatDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
	const displayDate = (value) => value ? new Date(`${value}T12:00:00`).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '';
	const render = (focusSelector = null) => {
		const year = visibleMonth.getFullYear();
		const month = visibleMonth.getMonth();
		const firstDay = new Date(year, month, 1);
		const daysInMonth = new Date(year, month + 1, 0).getDate();
		const cells = [];
		for (let blank = 0; blank < firstDay.getDay(); blank += 1) cells.push('<span class="date-range-empty"></span>');
		for (let day = 1; day <= daysInMonth; day += 1) {
			const value = formatDate(new Date(year, month, day));
			const selected = value === start || value === end;
			const inRange = start && end && value > start && value < end;
			const past = !allowPastDates && value < todayValue;
			cells.push(`<button type="button" class="date-range-day${selected ? ' is-selected' : ''}${inRange ? ' is-in-range' : ''}${past ? ' is-past' : ''}" data-date-value="${value}"${past ? ' disabled aria-disabled="true"' : ''}>${day}</button>`);
		}
		popover.innerHTML = `<div class="date-range-header"><button type="button" data-date-prev aria-label="Previous month">&larr;</button><strong>${visibleMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</strong><button type="button" data-date-next aria-label="Next month">&rarr;</button></div><div class="date-range-weekdays">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => `<span>${day}</span>`).join('')}</div><div class="date-range-days">${cells.join('')}</div>`;
		popover.querySelector('[data-date-prev]').addEventListener('click', () => { visibleMonth.setMonth(visibleMonth.getMonth() - 1); render('[data-date-prev]'); });
		popover.querySelector('[data-date-next]').addEventListener('click', () => { visibleMonth.setMonth(visibleMonth.getMonth() + 1); render('[data-date-next]'); });
		popover.querySelectorAll('[data-date-value]').forEach((dayButton) => {
			dayButton.addEventListener('click', () => {
				const value = dayButton.dataset.dateValue;
				if (!start || (start && end) || value < start) {
					start = value;
					end = '';
				} else {
					end = value;
				}
				startInput.value = start;
				endInput.value = end;
				label.textContent = start && end ? `${displayDate(start)} → ${displayDate(end)}` : start ? `${displayDate(start)} → ${picker.dataset.endLabel}` : picker.dataset.placeholder;
				if (start && end) {
					trigger.removeAttribute('aria-invalid');
					picker.querySelector('[data-date-range-error]')?.remove();
				}
				if (start && end) {
					popover.hidden = true;
					trigger.setAttribute('aria-expanded', 'false');
				}
				render(start && end ? null : `[data-date-value="${value}"]`);
			});
		});
		if (focusSelector) {
			popover.querySelector(focusSelector)?.focus();
		}
	};

	trigger.addEventListener('click', () => {
		if (popover.hidden) {
			popover.hidden = false;
			trigger.setAttribute('aria-expanded', 'true');
			render();
		}
	});
	const form = picker.closest('form');
	form?.addEventListener('submit', (event) => {
		if ((start && start < todayValue) || (end && end < todayValue) || (start && !end) || (!start && end)) {
			event.preventDefault();
			trigger.setAttribute('aria-invalid', 'true');
			const errorMessage = start < todayValue || end < todayValue ? picker.dataset.pastMessage : picker.dataset.incompleteMessage;
			label.textContent = errorMessage;
			let error = picker.querySelector('[data-date-range-error]');
			if (!error) {
				error = document.createElement('p');
				error.className = 'form-error-box';
				error.dataset.dateRangeError = '';
				picker.appendChild(error);
			}
			error.textContent = errorMessage;
			popover.hidden = false;
			trigger.setAttribute('aria-expanded', 'true');
			render();
		}
	});
	picker.addEventListener('focusout', () => {
		window.requestAnimationFrame(() => {
			if (!picker.contains(document.activeElement)) {
			popover.hidden = true;
			trigger.setAttribute('aria-expanded', 'false');
			}
		});
	});
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

document.querySelectorAll('[data-ai-copy-assistant]').forEach((assistant) => {
	const form = assistant.closest('[data-property-tabs]')?.querySelector('[data-property-editor-form]') || assistant.closest('form');
	const generate = assistant.querySelector('[data-ai-copy-generate]');
	const preview = assistant.querySelector('[data-ai-copy-preview]');
	const status = assistant.querySelector('[data-ai-copy-status]');
	const fields = ['summary_fr', 'description_fr', 'summary_en', 'description_en'];
	const sourceFor = (field) => {
		const [type, locale] = field.split('_');
		return form.elements[`translations[${locale}][${type}]`];
	};

	generate?.addEventListener('click', async () => {
		generate.disabled = true;
		generate.setAttribute('aria-busy', 'true');
		status.textContent = assistant.dataset.loadingLabel;
		preview.hidden = true;

		try {
			const response = await fetch(assistant.dataset.endpoint, {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
				},
				body: JSON.stringify(Object.fromEntries(fields.map((field) => [field, sourceFor(field)?.value || '']))),
			});
			const payload = await response.json();
			if (!response.ok) {
				const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : null;
				throw new Error(validationMessage || payload.message || assistant.dataset.errorLabel);
			}

			fields.forEach((field) => {
				assistant.querySelector(`[data-ai-copy-result="${field}"]`).value = payload.suggestion[field];
			});
			preview.hidden = false;
			status.textContent = assistant.dataset.readyLabel;
			preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		} catch (error) {
			status.textContent = error.message || assistant.dataset.errorLabel;
		} finally {
			generate.disabled = false;
			generate.removeAttribute('aria-busy');
		}
	});

	assistant.querySelector('[data-ai-copy-apply]')?.addEventListener('click', () => {
		fields.forEach((field) => {
			const source = sourceFor(field);
			if (source) source.value = assistant.querySelector(`[data-ai-copy-result="${field}"]`).value;
		});
		preview.hidden = true;
		status.textContent = assistant.dataset.appliedLabel;
	});

	assistant.querySelector('[data-ai-copy-discard]')?.addEventListener('click', () => {
		preview.hidden = true;
		status.textContent = assistant.dataset.discardedLabel;
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
		payload.append('adults', form.elements.adults?.value || '1');
		payload.append('children', form.elements.children?.value || '0');
		form.querySelectorAll('input[name="selected_features[]"]:checked').forEach((feature) => payload.append('selected_features[]', feature.value));
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
			result.textContent = data.reason === 'minimum_stay' ? form.dataset.availabilityMinimumStay : (data.available ? (data.message || form.dataset.availabilityAvailable) : form.dataset.availabilityUnavailable);
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
		tab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
	});

	tab.addEventListener('keydown', (event) => {
		if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
			return;
		}

		event.preventDefault();
		const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[data-property-tab]')];
		const currentIndex = tabs.indexOf(tab);
		const nextIndex = event.key === 'Home'
			? 0
			: event.key === 'End'
				? tabs.length - 1
				: (currentIndex + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;

		tabs[nextIndex].focus();
		tabs[nextIndex].click();
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
		const handle = row.querySelector('.photo-drag-handle');
		if (handle) {
			handle.tabIndex = 0;
			handle.setAttribute('role', 'button');
			handle.setAttribute('aria-describedby', 'photo-reorder-help');
			handle.addEventListener('keydown', (event) => {
				if (!['ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
					return;
				}

				event.preventDefault();
				const orderedRows = rows();
				const currentIndex = orderedRows.indexOf(row);
				const targetIndex = event.key === 'Home'
					? 0
					: event.key === 'End'
						? orderedRows.length - 1
						: currentIndex + (event.key === 'ArrowDown' ? 1 : -1);

				if (targetIndex < 0 || targetIndex >= orderedRows.length || targetIndex === currentIndex) {
					return;
				}

				if (targetIndex > currentIndex) {
					list.insertBefore(row, orderedRows[targetIndex].nextSibling);
				} else {
					list.insertBefore(row, orderedRows[targetIndex]);
				}

				updatePhotoOrder();
				handle.focus();
			});
		}

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

document.querySelectorAll('[data-copy-text]').forEach((button) => {
	button.addEventListener('click', async () => {
		const status = button.parentElement?.querySelector('[data-copy-text-status]');
		try {
			await navigator.clipboard.writeText(button.dataset.copyText ?? '');
			if (status) {
				status.textContent = button.dataset.copySuccess ?? 'Copied.';
				status.hidden = false;
			} else {
				button.textContent = button.dataset.copySuccessShort ?? 'OK';
			}
		} catch {
			if (status) {
				status.textContent = button.dataset.copyError ?? 'Unable to copy.';
				status.hidden = false;
			} else {
				button.textContent = button.dataset.copyErrorShort ?? '—';
			}
		}
		window.setTimeout(() => {
			if (status) status.hidden = true;
		}, 2400);
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

const compareForm = document.querySelector('#compare-form');
if (compareForm) {
	const compareOptions = [...document.querySelectorAll('[data-compare-option][form="compare-form"]')];
	const compareCount = compareForm.querySelector('[data-compare-count]');
	const compareSubmit = compareForm.querySelector('button[type="submit"]');
	const updateCompareState = () => {
		const selected = compareOptions.filter((option) => option.checked);
		compareOptions.forEach((option) => {
			option.disabled = !option.checked && selected.length >= 3;
		});
		if (compareCount) {
			compareCount.textContent = compareCount.dataset.template?.replace(':count', selected.length) || `${selected.length} selected`;
		}
		if (compareSubmit) compareSubmit.disabled = selected.length < 2;
	};
	compareCount?.setAttribute('data-template', compareCount.textContent.replace(/\d+/, ':count'));
	compareOptions.forEach((option) => option.addEventListener('change', updateCompareState));
	compareForm.addEventListener('submit', (event) => {
		if (compareOptions.filter((option) => option.checked).length < 2) event.preventDefault();
	});
	updateCompareState();
}

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
	const inRange = (date, ranges) => ranges.some(([start, end]) => date >= start && date < end);
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

document.querySelectorAll('[data-toggle-availability], [data-toggle-content]').forEach((button) => {
	button.addEventListener('click', () => {
		const content = document.getElementById(button.getAttribute('aria-controls'));
		const expanded = button.getAttribute('aria-expanded') === 'true';
		button.setAttribute('aria-expanded', String(!expanded));
		button.textContent = expanded ? button.dataset.showLabel : button.dataset.hideLabel;
		if (content) content.hidden = expanded;
	});
});
