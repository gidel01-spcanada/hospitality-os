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
	const closeMobileMenu = () => {
		mobileNavActions.classList.remove('is-open');
		mobileMenuToggle.setAttribute('aria-expanded', 'false');
	};
	mobileMenuToggle.addEventListener('click', () => {
		const isOpen = mobileNavActions.classList.toggle('is-open');
		mobileMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});
	mobileNavActions.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobileMenu));
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closeMobileMenu();
	});
}

const portalNavToggle = document.querySelector('[data-portal-nav-toggle]');
const portalSidebar = document.querySelector('[data-portal-sidebar]');

if (portalNavToggle && portalSidebar) {
	const closePortalNav = () => {
		portalSidebar.classList.remove('is-open');
		portalNavToggle.setAttribute('aria-expanded', 'false');
	};
	portalNavToggle.addEventListener('click', () => {
		const isOpen = portalSidebar.classList.toggle('is-open');
		portalNavToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	});
	portalSidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closePortalNav));
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closePortalNav();
	});
}

const portalForms = Array.from(document.querySelectorAll('[data-portal-form]'));
const portalFormSnapshots = new Map(portalForms.map((form) => [form, new URLSearchParams(new FormData(form)).toString()]));
const portalFormsHaveChanges = () => portalForms.some((form) => new URLSearchParams(new FormData(form)).toString() !== portalFormSnapshots.get(form));

portalForms.forEach((form) => {
	form.addEventListener('submit', (event) => {
		if (form.dataset.portalSaving === 'true') {
			event.preventDefault();
			return;
		}
		form.dataset.portalSaving = 'true';
		form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => { button.disabled = true; });
	});
});

if (portalForms.length) {
	window.addEventListener('beforeunload', (event) => {
		if (!portalFormsHaveChanges()) return;
		event.preventDefault();
		event.returnValue = '';
	});
}

const topbar = document.querySelector('.topbar');
if (topbar) {
	const updateTopbarScrollState = () => topbar.classList.toggle('is-scrolled', window.scrollY > 8);
	updateTopbarScrollState();
	window.addEventListener('scroll', updateTopbarScrollState, { passive: true });
}

const helpChat = document.querySelector('[data-help-chat]');
if (helpChat) {
	const form = helpChat.querySelector('[data-help-chat-form]');
	const textarea = form?.querySelector('textarea[name="message"]');
	const messages = helpChat.querySelector('[data-help-chat-messages]');
	const status = helpChat.querySelector('[data-help-chat-status]');
	const history = [];

	const appendMessage = (className, content) => {
		const message = document.createElement('p');
		message.className = className;
		message.textContent = content;
		messages.appendChild(message);
		messages.scrollTop = messages.scrollHeight;
	};

	helpChat.querySelector('[data-help-chat-minimize]')?.addEventListener('click', () => {
		helpChat.open = false;
		helpChat.classList.add('is-minimized');
	});
	helpChat.querySelector('summary')?.addEventListener('click', () => {
		helpChat.classList.remove('is-minimized');
	});
	helpChat.querySelector('[data-help-chat-close]')?.addEventListener('click', () => { helpChat.open = false; });
	form?.addEventListener('submit', async (event) => {
		event.preventDefault();
		const message = textarea.value.trim();
		if (!message) return;

		appendMessage('help-chat-question', message);
		textarea.value = '';
		const submit = form.querySelector('button[type="submit"]');
		submit.disabled = true;
		status.textContent = '';

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
				},
				body: JSON.stringify({ message, history }),
			});
			const result = await response.json();
			if (!response.ok) throw new Error(result.message || '');

			appendMessage('help-chat-answer', result.answer);
			history.push({ role: 'user', content: message }, { role: 'assistant', content: result.answer });
			if (history.length > 10) history.splice(0, history.length - 10);
		} catch (error) {
			status.textContent = error.message || form.dataset.errorMessage;
		} finally {
			submit.disabled = false;
			textarea.focus();
		}
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
	const caption = gallery.querySelector('[data-gallery-caption]');
	const stage = gallery.querySelector('[data-gallery-stage]') || gallery;
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
		if (caption) {
			caption.textContent = altOf(source);
			gallery.closest('.gallery-panel')?.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
				thumb.setAttribute('aria-pressed', String(Number(thumb.dataset.galleryThumb) === currentIndex));
			});
		}
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
			stage.appendChild(layer);
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
	if (form.hasAttribute('data-comparison-controls')) return;
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
	label.textContent = start && end ? `${displayDate(start)} → ${displayDate(end)}` : start ? `${displayDate(start)} → ${picker.dataset.endLabel}` : picker.dataset.placeholder;
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
				endInput.dispatchEvent(new Event('change', { bubbles: true }));
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
	const form = button.closest('form');
	const result = form.querySelector('[data-availability-result]');
	const bookingCard = form.closest('.booking-card');
	const stayTotal = bookingCard?.querySelector('[data-validated-stay-total]');
	const stayTotalValue = stayTotal?.querySelector('[data-validated-stay-total-value]');
	const breakdown = bookingCard?.querySelector('[data-pricing-breakdown]');
	const breakdownContent = breakdown?.querySelector('[data-pricing-breakdown-content]');
	let requestSequence = 0;
	const money = (amount, currency) => `${new Intl.NumberFormat(document.documentElement.lang, { maximumFractionDigits: 0 }).format(amount)} ${currency}`;
	const appendPricingLine = (label, value, className = '') => {
		const row = document.createElement('div');
		if (className) row.className = className;
		const term = document.createElement('dt');
		term.textContent = label;
		const amount = document.createElement('dd');
		amount.textContent = value;
		row.append(term, amount);
		breakdownContent.appendChild(row);
	};
	const checkAvailability = async () => {
		if (!form.elements.check_in.value || !form.elements.check_out.value) return;
		const currentRequest = ++requestSequence;

		form.querySelectorAll('.form-error-box, .reservation-success').forEach((el) => el.remove());
		const payload = new FormData();
		payload.append('check_in', form.elements.check_in.value);
		payload.append('check_out', form.elements.check_out.value);
		payload.append('adults', form.elements.adults?.value || '1');
		payload.append('children', form.elements.children?.value || '0');
		form.querySelectorAll('input[name="selected_features[]"]:checked').forEach((feature) => payload.append('selected_features[]', feature.value));
		button.disabled = true;
		if (stayTotal) stayTotal.hidden = true;
		if (breakdown) breakdown.hidden = true;
		result.dataset.availabilityState = 'checking';
		result.textContent = form.dataset.availabilityChecking;

		try {
			const response = await fetch(form.dataset.availabilityUrl, {
				method: 'POST',
				cache: 'no-store',
				headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
				body: payload,
			});
			if (!response.ok) {
				throw new Error('Availability request failed');
			}
			const data = await response.json();
			if (currentRequest !== requestSequence) return;
			result.dataset.availabilityState = data.reason === 'minimum_stay' ? 'minimum-stay' : (data.available ? 'available' : 'unavailable');
			result.textContent = data.reason === 'minimum_stay' ? form.dataset.availabilityMinimumStay : (data.message || form.dataset.availabilityAvailable);
			if (data.available && data.breakdown) {
				const pricing = data.breakdown;
				stayTotalValue.textContent = money(pricing.total_amount, pricing.currency);
				stayTotal.hidden = false;
				breakdownContent.replaceChildren();
				breakdown.querySelector('[data-pricing-currency]').textContent = pricing.currency;
				appendPricingLine(form.dataset.pricingNightsLabel, pricing.nights);
				appendPricingLine(form.dataset.pricingNightlyRateLabel, money(pricing.nightly_rate, pricing.currency));
				appendPricingLine(form.dataset.pricingRoomSubtotalLabel, money(pricing.room_subtotal, pricing.currency));
				pricing.extra_lines.forEach((line) => appendPricingLine(line.label, money(line.amount, line.currency)));
				if (pricing.fees > 0) appendPricingLine(form.dataset.pricingFeesLabel, money(pricing.fees, pricing.currency));
				pricing.tax_lines.forEach((line) => appendPricingLine(line.label, money(line.amount, line.currency)));
				pricing.discount_lines.forEach((line) => appendPricingLine(line.label, `−${money(line.amount, line.currency)}`));
				appendPricingLine(form.dataset.pricingFinalTotalLabel, money(pricing.total_amount, pricing.currency), 'stay-pricing-final-total');
				breakdown.hidden = false;
			}
		} catch {
			if (currentRequest !== requestSequence) return;
			result.dataset.availabilityState = 'error';
			result.textContent = form.dataset.availabilityFailed;
		} finally {
			if (currentRequest === requestSequence) button.disabled = false;
		}
	};
	button.addEventListener('click', checkAvailability);
	const recheckAvailability = () => {
		if (form.elements.check_in.value && form.elements.check_out.value) checkAvailability();
	};
	form.elements.check_out?.addEventListener('change', recheckAvailability);
	form.elements.adults?.addEventListener('change', recheckAvailability);
	form.elements.children?.addEventListener('change', recheckAvailability);
	form.querySelectorAll('input[name="selected_features[]"]').forEach((feature) => feature.addEventListener('change', recheckAvailability));
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

document.querySelectorAll('[data-share-panel]').forEach((panel) => {
	const sensitive = new Set(['_token', '_method', 'token', 'signature', 'expires', 'checkout_return', 'page', 'password', 'password_confirmation', 'email', 'full_name', 'phone', 'customer_note', 'g-recaptcha-response']);
	const stateForm = panel.dataset.shareState ? document.querySelector(panel.dataset.shareState) : null;
	const allowed = panel.dataset.shareParams
		? Object.fromEntries(panel.dataset.shareParams.split(',').map((entry) => {
			const [from, to] = entry.trim().split(':');
			return [from, to || from];
		}))
		: null;

	const shareUrl = () => {
		const url = new URL(panel.dataset.shareUrl, window.location.href);
		if (!stateForm) return url.toString();

		const values = new Map();
		for (const field of stateForm.elements) {
			if (!field.name || field.disabled || sensitive.has(field.name) || ['file', 'password', 'submit', 'button'].includes(field.type)) continue;
				if (field.name === 'sort' && field.value === 'recommended') continue;
			if (allowed && !(field.name in allowed)) continue;
			const target = allowed ? allowed[field.name] : field.name;
			if (!values.has(target)) values.set(target, []);
			if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) continue;
			if (field.value !== '') values.get(target).push(field.value);
		}

		values.forEach((list, name) => {
			[...url.searchParams.keys()].filter((key) => key === name || key.startsWith(`${name.replace(/\[\]$/, '')}[`)).forEach((key) => url.searchParams.delete(key));
			list.forEach((value) => url.searchParams.append(name, value));
		});

		return url.toString();
	};

	const refresh = () => {
		const url = shareUrl();
		const title = panel.dataset.shareTitle ?? document.title;
		const encodedUrl = encodeURIComponent(url);
		const encodedTitle = encodeURIComponent(title);
		const links = {
			whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
			facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
			x: `https://twitter.com/intent/tweet?text=${encodedTitle}&url=${encodedUrl}`,
			linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
			email: `mailto:?subject=${encodedTitle}&body=${encodedUrl}`,
		};
		panel.querySelectorAll('[data-share-network]').forEach((link) => {
			link.href = links[link.dataset.shareNetwork] ?? link.href;
		});
		panel.querySelectorAll('[data-copy-share-link]').forEach((button) => {
			button.dataset.copyShareLink = url;
		});

		return { url, title };
	};

	panel.addEventListener('toggle', () => { if (panel.open) refresh(); });
	panel.querySelectorAll('[data-share-network], [data-copy-share-link]').forEach((control) => {
		control.addEventListener('pointerdown', refresh);
		control.addEventListener('focus', refresh);
	});

	const nativeShare = panel.querySelector('[data-native-share]');
	if (nativeShare && typeof navigator.share === 'function') {
		nativeShare.hidden = false;
		nativeShare.addEventListener('click', async () => {
			const { url, title } = refresh();
			try {
				await navigator.share({ title, url });
			} catch {
				// The user closed the share sheet.
			}
		});
	}
});

document.querySelectorAll('[data-number-stepper]').forEach((stepper) => {
	const input = stepper.querySelector('[data-step-input]');
	if (!input) return;
	stepper.querySelectorAll('[data-step]').forEach((button) => {
		button.addEventListener('click', () => {
			const minimum = Number(input.min || 0);
			const maximum = Number(input.max || Number.MAX_SAFE_INTEGER);
			const current = input.value === '' ? minimum - Number(button.dataset.step) : Number(input.value);
			const next = Math.min(maximum, Math.max(minimum, current + Number(button.dataset.step)));
			input.value = next === minimum && Number(button.dataset.step) < 0 ? '' : String(next);
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});
	});
});

document.querySelectorAll('[data-price-range]').forEach((group) => {
	const minRange = group.querySelector('[data-price-range-min]');
	const maxRange = group.querySelector('[data-price-range-max]');
	const minInput = group.closest('form')?.querySelector('[data-price-number-min]');
	const maxInput = group.closest('form')?.querySelector('[data-price-number-max]');
	const summary = group.closest('form')?.querySelector('[data-price-summary]');
	if (!minRange || !maxRange || !minInput || !maxInput) return;
	const updateSummary = () => {
		if (!summary) return;
		const formatter = new Intl.NumberFormat(document.documentElement.lang || 'fr', { maximumFractionDigits: 0 });
		const minimum = minInput.value === '' ? 0 : Number(minInput.value);
		const maximum = maxInput.value === '' ? Number(group.dataset.priceCeiling) : Number(maxInput.value);
		summary.textContent = summary.dataset.template
			.replace('__MIN__', formatter.format(minimum))
			.replace('__MAX__', formatter.format(maximum));
	};

	const syncRangeToNumbers = () => {
		minInput.value = minRange.value === '0' ? '' : minRange.value;
		maxInput.value = maxRange.value === group.dataset.priceCeiling ? '' : maxRange.value;
		minInput.dispatchEvent(new Event('input', { bubbles: true }));
	};
	const syncNumberToRange = (input, range, isMinimum) => {
		if (input.value === '') {
			range.value = isMinimum ? '0' : group.dataset.priceCeiling;
		} else {
			range.value = String(Math.min(Number(group.dataset.priceCeiling), Number(input.value)));
		}
	};
	minRange.addEventListener('input', () => {
		if (Number(minRange.value) > Number(maxRange.value)) maxRange.value = minRange.value;
		syncRangeToNumbers();
	});
	maxRange.addEventListener('input', () => {
		if (Number(maxRange.value) < Number(minRange.value)) minRange.value = maxRange.value;
		syncRangeToNumbers();
	});
	minInput.addEventListener('input', () => syncNumberToRange(minInput, minRange, true));
	maxInput.addEventListener('input', () => syncNumberToRange(maxInput, maxRange, false));
	group.addEventListener('input', updateSummary);
	minInput.addEventListener('input', updateSummary);
	maxInput.addEventListener('input', updateSummary);
});

document.querySelectorAll('[data-amenities-section]').forEach((section) => {
	const countBadge = section.querySelector('[data-amenity-count]');
	const updateAmenityCount = () => {
		const count = section.querySelectorAll('input[name="amenities[]"]:checked').length;
		if (!countBadge) return;
		countBadge.hidden = count === 0;
		countBadge.textContent = countBadge.dataset.countTemplate.replace('__COUNT__', String(count));
	};
	section.addEventListener('change', updateAmenityCount);
	section.querySelectorAll('[data-amenity-more]').forEach((button) => {
		button.dataset.moreLabel = button.textContent.trim();
		button.addEventListener('click', () => {
			const category = button.closest('[data-amenity-category]');
			const extras = [...(category?.querySelectorAll('[data-amenity-extra]') ?? [])];
			const show = extras.some((item) => item.hidden);
			if (category) category.dataset.expanded = String(show);
			extras.forEach((item) => { item.hidden = !show; });
			button.textContent = show ? button.dataset.lessLabel : button.dataset.moreLabel;
		});
		button.dataset.lessLabel = button.getAttribute('data-less-label') ?? button.dataset.moreLabel;
	});

	const search = section.querySelector('[data-amenity-search]');
	search?.addEventListener('input', () => {
		const query = search.value.trim().toLocaleLowerCase();
		section.querySelectorAll('[data-amenity-option]').forEach((option) => {
			const matches = !query || (option.dataset.amenitySearchText ?? '').includes(query);
			const extraCollapsed = option.hasAttribute('data-amenity-extra') && option.closest('[data-amenity-category]')?.dataset.expanded !== 'true';
			option.hidden = query ? !matches : extraCollapsed;
			if (query && matches) {
				const category = option.closest('[data-amenity-category]');
				if (category) category.open = true;
			}
		});
		section.querySelectorAll('[data-amenity-more]').forEach((button) => { button.hidden = Boolean(query); });
		section.querySelectorAll('[data-amenity-category]').forEach((category) => {
			category.hidden = Boolean(query) && !category.querySelector('[data-amenity-option]:not([hidden])');
		});
	});
});

document.querySelectorAll('[data-filter-drawer]').forEach((drawer) => {
	const form = drawer.querySelector('form');
	const countBadge = drawer.querySelector('.filter-count-badge');
	const apply = drawer.querySelector('[data-filter-apply]');
	const resultCount = drawer.querySelector('[data-filter-result-count]');
	const syncBodyLock = () => {
		if (window.matchMedia('(max-width: 760px)').matches && drawer.open) document.body.classList.add('filter-drawer-open');
		else document.body.classList.remove('filter-drawer-open');
	};
	drawer.addEventListener('toggle', syncBodyLock);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && drawer.open && window.matchMedia('(max-width: 760px)').matches) drawer.open = false;
	});
	window.addEventListener('resize', syncBodyLock);
	if (!form) return;

	const sync = () => {
		const min = form.querySelector('[data-price-number-min]');
		const max = form.querySelector('[data-price-number-max]');
		if (min && max) {
			const invalidPriceRange = min.value !== '' && max.value !== '' && Number(min.value) > Number(max.value);
			min.setCustomValidity(invalidPriceRange ? min.dataset.rangeError ?? 'Invalid range' : '');
			max.setCustomValidity(invalidPriceRange ? max.dataset.rangeError ?? 'Invalid range' : '');
		}
		const start = form.querySelector('[data-date-range-start]')?.value ?? '';
		const end = form.querySelector('[data-date-range-end]')?.value ?? '';
		const invalidDates = Boolean(start) !== Boolean(end);
		const names = new Set();
		[...form.elements].forEach((field) => {
			if (!field.name || field.disabled || ['submit', 'button', 'range'].includes(field.type)) return;
			if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
			if (field.name === 'sort' && field.value === 'recommended') return;
			if (field.name === 'check_in' || field.name === 'check_out') {
				if (start && end) names.add('dates');
				return;
			}
			if (field.name === 'min_price' || field.name === 'max_price') {
				if (min?.value || max?.value) names.add('price');
				return;
			}
			if (field.value !== '') names.add(field.name.startsWith('amenities') ? `${field.name}-${field.value}` : field.name);
		});
		const count = names.size;
		drawer.dataset.activeCount = String(count);
		if (countBadge) countBadge.textContent = drawer.dataset.filterCountTemplate.replace('__COUNT__', String(count));
		if (apply) apply.disabled = invalidDates || Boolean(min?.validationMessage) || Boolean(max?.validationMessage);
	};
	const markResultsStale = () => {
		if (resultCount?.dataset.staleLabel) resultCount.textContent = resultCount.dataset.staleLabel;
	};
	form.addEventListener('input', () => { sync(); markResultsStale(); });
	form.addEventListener('change', () => { sync(); markResultsStale(); });
	form.addEventListener('submit', (event) => {
		if (event.defaultPrevented) return;
		sync();
		if (apply?.disabled || !form.reportValidity()) {
			event.preventDefault();
			return;
		}
		event.preventDefault();
		const url = new URL(form.action, window.location.href);
		for (const [name, value] of new FormData(form).entries()) {
			if (value === '' || name === '_token' || name === '_method' || name === 'page' || (name === 'sort' && value === 'recommended')) continue;
			url.searchParams.append(name, value);
		}
		window.location.assign(url.toString());
	});
	sync();
});

document.querySelectorAll('[data-establishment-review-filters]').forEach((form) => {
	const origin = form.querySelector('[data-review-origin]');
	const home = form.querySelector('[data-review-home]');
	if (!origin || !home) return;
	const updateReviewHomeFilter = () => { home.disabled = origin.value === 'establishment'; };
	origin.addEventListener('change', updateReviewHomeFilter);
	updateReviewHomeFilter();
});

document.querySelectorAll('[data-property-filter-form]').forEach((form) => {
	form.addEventListener('submit', () => {
		try { window.localStorage.removeItem('afrikappart-compare'); } catch {}
	});
});

import './property-comparison';

const compareForm = document.querySelector('#compare-form');
if (compareForm) {
	const compareOptions = [...document.querySelectorAll('[data-compare-option][form="compare-form"]')];
	const compareCount = compareForm.querySelector('[data-compare-count]');
	const compareSubmit = compareForm.querySelector('button[type="submit"]');
	const selectionLimit = () => window.matchMedia('(max-width: 760px)').matches ? 3 : 4;
	try {
		const remembered = JSON.parse(localStorage.getItem('afrikappart-compare') || '[]');
		compareOptions.forEach((option) => { option.checked = remembered.includes(option.value); });
	} catch {}
	const updateCompareState = () => {
		const selected = compareOptions.filter((option) => option.checked);
		compareOptions.forEach((option) => {
			option.disabled = !option.checked && selected.length >= selectionLimit();
		});
		if (compareCount) {
			compareCount.textContent = compareCount.dataset.template?.replace(':count', selected.length) || `${selected.length} selected`;
		}
		if (compareSubmit) compareSubmit.disabled = selected.length < 2 || selected.length > selectionLimit();
		try { localStorage.setItem('afrikappart-compare', JSON.stringify(selected.map((option) => option.value))); } catch {}
	};
	compareCount?.setAttribute('data-template', compareCount.textContent.replace(/\d+/, ':count'));
	compareOptions.forEach((option) => option.addEventListener('change', updateCompareState));
	compareForm.addEventListener('submit', (event) => {
		const count = compareOptions.filter((option) => option.checked).length;
		if (count < 2 || count > selectionLimit()) event.preventDefault();
		else {
			try { sessionStorage.setItem('afrikappart-compare-return', window.location.href); } catch {}
		}
	});
	window.addEventListener('resize', updateCompareState);
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
