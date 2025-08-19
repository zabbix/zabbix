/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Create overlay dialogue.
 *
 * This class is for internal use only.
 * @see overlayDialogue
 *
 * @param {string}      type             Dialogue type (currently supported: "popup").
 * @param {string|null} dialogueid       Dialogue ID.
 * @param {boolean}     is_modal         Whether to prevent interaction with background objects.
 * @param {boolean}     is_draggable     Whether to allow dragging the form around.
 * @param {string}      position         Positioning strategy (Overlay.prototype.POSITION_*).
 * @param {Object|null} position_fix     Specific position, if not null (same as returned by "this.getPositionFix").
 * @param {*}           trigger_element  Focusable element to focus back when overlay is closed.
 */
function Overlay({
	type,
	dialogueid = null,
	is_modal = true,
	is_draggable = false,
	position = this.POSITION_CENTER_TOP,
	position_fix = null,
	trigger_element = undefined
} = {}) {
	this.type = type;
	this.dialogueid = dialogueid || overlays_stack.getNextId();
	this._is_modal = is_modal;
	this._is_draggable = is_draggable;
	this._position = position;
	this._position_fix = position_fix;
	this.element = trigger_element;
	this.has_custom_cancel = false;

	this.headerid = `overlay-dialogue-header-title-${this.dialogueid}`;

	if (this._is_modal) {
		this.$backdrop = jQuery('<div>', {
			'class': 'overlay-bg',
			'data-dialogueid': this.dialogueid
		});
	}

	this.$dialogue = jQuery('<div>', {
		'class': 'overlay-dialogue modal',
		'data-dialogueid': this.dialogueid,
		'role': 'dialog',
		'aria-modal': 'true',
		'aria-labelledby': this.headerid
	});

	this.$dialogue.$controls = jQuery('<div>', {class: 'overlay-dialogue-controls'});
	this.$dialogue.$head = jQuery('<div>', {class: 'overlay-dialogue-header'});
	this.$dialogue.$head.$header = jQuery('<h4>', {id: this.headerid});
	this.$dialogue.$head.$close_button = jQuery('<button>', {class: 'btn-overlay-close', title: t('S_CLOSE')});
	this.$dialogue.$body = jQuery('<div>', {class: 'overlay-dialogue-body'});
	this.$dialogue.$debug = jQuery('<pre>', {class: 'debug-output'});
	this.$dialogue.$footer = jQuery('<div>', {class: 'overlay-dialogue-footer'});
	this.$dialogue.$script = jQuery('<script>');

	this.$dialogue.$head.append(this.$dialogue.$head.$header, this.$dialogue.$head.$close_button);

	this.$dialogue.append(this.$dialogue.$head);
	this.$dialogue.append(this.$dialogue.$body);
	this.$dialogue.append(this.$dialogue.$footer);

	this.setProperties({
		content: jQuery('<div>', {'height': '68px', class: 'is-loading'})
	});

	this._initListeners();
}

/**
 * Centered positioning of overlay.
 *
 * @type {string}
 */
Overlay.prototype.POSITION_CENTER = 'center';

/**
 * Top-centered positioning of overlay.
 *
 * @type {string}
 */
Overlay.prototype.POSITION_CENTER_TOP = 'center-top';

/**
 * Required visibility of the overlay, in pixels.
 *
 * Visibility will be restored if overlay is visible less than this number of pixels from any side, except top.
 *
 * @type {number}
 */
Overlay.prototype.MIN_VISIBLE_PX = 100;

/**
 * Indication of overlay dialog being closed by user intent.
 *
 * @type {string}
 */
Overlay.prototype.CLOSE_BY_USER = 'close-by-user';

/**
 * Indication of overlay dialog being closed by script.
 *
 * @type {string}
 */
Overlay.prototype.CLOSE_BY_SCRIPT = 'close-by-script';

/**
 * Create listeners (will be connected on mount, disconnected on unmount).
 *
 * @private
 */
Overlay.prototype._initListeners = function() {
	this._listeners = {
		close_button_click: e => {
			e.preventDefault();

			if (this.has_custom_cancel) {
				this.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.cancel', {detail: {
					dialogueid: this.dialogueid
				}}));
			}
			else {
				overlayDialogueDestroy(this.dialogueid, this.CLOSE_BY_USER);
			}
		},
		form_submit: e => {
			e.preventDefault();

			if (this.$btn_submit !== null && !this.$btn_submit[0].disabled) {
				this.$btn_submit[0].click();
			}
		},
		head_double_click: e => {
			if (e.target.matches('a, button')) {
				return;
			}

			// Restore centering on double click.
			this._position_fix = null;

			this.fixPosition();
		},
		window_resize: () => {
			if (isVisible(this.$dialogue[0])) {
				this.fixPosition();
			}
		},
		debug_click: () => {
			this._fixPositionOnAnimationFrame();
		},
		drag_start: () => {
			this._is_dragging = true;
		},
		drag_stop: () => {
			const client_rect = this.$dialogue[0].getBoundingClientRect();

			this._position_fix = {
				x: client_rect.x + client_rect.width / 2,
				y: this._position === this.POSITION_CENTER_TOP ? client_rect.y : client_rect.y + client_rect.height / 2
			}

			this._force_visible_x = false;
			this._force_visible_y = false;
			this._is_dragging = false;

			this.fixPosition();
		}
	};
};

/**
 * Fix overlay position according to overlay's settings.
 */
Overlay.prototype.fixPosition = function() {
	if (this._is_dragging) {
		return;
	}

	const dialogue = this.$dialogue[0];

	const width_original = dialogue.style.width;

	dialogue.style.width = '';
	dialogue.style.height = '';
	dialogue.style.left = '0';
	dialogue.style.top = '0';

	const client_rect = dialogue.getBoundingClientRect();
	const style = getComputedStyle(dialogue);

	const width_delta = width_original !== '' ? parseFloat(style.width) - parseFloat(width_original) : 0;

	// Fix overlay size to prevent shrinking when dragged beyond screen border.
	dialogue.style.width = style.width;
	dialogue.style.height = style.height;

	if (this._position_fix !== null) {
		const visible_x_min = client_rect.width / 2 + this._dialogue_margin_left;
		const visible_x_max = window.innerWidth - client_rect.width / 2 - this._dialogue_margin_right;

		if (this._position_fix.x >= visible_x_min && this._position_fix.x <= visible_x_max) {
			// Overlay is fully visible on X axis - ensure it's fully visible on next updates.
			this._force_visible_x = true;
		}
		else if (this._force_visible_x) {
			// Overlay is not fully visible on X axis, although required, - bring it into full visibility.
			this._position_fix.x = Math.max(Math.min(this._position_fix.x, visible_x_max), visible_x_min);
		}
		else {
			// Overlay is not fully visible on X axis, and not required - ensure minimum visibility.
			const min_visible_x_min = visible_x_min - client_rect.width + this.MIN_VISIBLE_PX;
			const min_visible_x_max = visible_x_max + client_rect.width - this.MIN_VISIBLE_PX;

			this._position_fix.x = Math.max(Math.min(this._position_fix.x, min_visible_x_max), min_visible_x_min);

			// Overlay has resized on X axis - compensate position to persist the visual part of the overlay.
			if (this._position_fix.x > (visible_x_min + visible_x_max) / 2) {
				this._position_fix.x += width_delta / 2;
			}
			else {
				this._position_fix.x -= width_delta / 2;
			}

			// Overlay became fully visible on X axis - ensure it's fully visible on next updates.
			this._force_visible_x = this._position_fix.x >= visible_x_min && this._position_fix.x <= visible_x_max;
		}

		const visible_y_min = this._position === this.POSITION_CENTER_TOP
			? this._dialogue_offset_top
			: client_rect.height / 2 + this._dialogue_offset_top;

		const visible_y_max = this._position === this.POSITION_CENTER_TOP
			? window.innerHeight - client_rect.height
			: window.innerHeight - client_rect.height / 2;

		if (this._position_fix.y >= visible_y_min && this._position_fix.y <= visible_y_max) {
			// Overlay is fully visible on Y axis - ensure it's fully visible on next updates.
			this._force_visible_y = true;
		}
		else if (this._force_visible_y) {
			// Overlay is not fully visible on Y axis, although required, - bring it into full visibility.
			this._position_fix.y = Math.max(Math.min(this._position_fix.y, visible_y_max), visible_y_min);
		}
		else {
			// Overlay is not fully visible on Y axis, and not required - ensure minimum visibility.
			const min_visible_y_min = visible_y_min;
			const min_visible_y_max = visible_y_max + client_rect.height - this.MIN_VISIBLE_PX;

			this._position_fix.y = Math.max(Math.min(this._position_fix.y, min_visible_y_max), min_visible_y_min);
		}

		dialogue.style.left = `${
			Math.floor(this._position_fix.x - client_rect.width / 2 - this._dialogue_margin_left)
		}px`;
		dialogue.style.top = this._position === this.POSITION_CENTER_TOP
			? `${Math.floor(this._position_fix.y)}px`
			: `${Math.floor(this._position_fix.y - client_rect.height / 2)}px`;
	}
	else {
		dialogue.style.left = `${
			Math.max(0, Math.floor((window.innerWidth - client_rect.width) / 2 - this._dialogue_margin_left))
		}px`;
		dialogue.style.top = this._position === this.POSITION_CENTER_TOP
			? ''
			: `${Math.max(0, Math.floor((window.innerHeight - client_rect.height) / 2))}px`;
	}
};

/**
 * Get positioning strategy of the overlay.
 *
 * @returns {string}
 */
Overlay.prototype.getPosition = function() {
	return this._position;
};

/**
 * Get current specific position of the overlay. Position fix object can be reused when creating new overlays.
 *
 * @returns {Object|null}
 */
Overlay.prototype.getPositionFix = function() {
	return this._position_fix !== null ? {...this._position_fix} : null;
};

Overlay.prototype._fixPositionOnAnimationFrame = function() {
	if (this._fix_position_animation_frame === undefined) {
		this._fix_position_animation_frame = requestAnimationFrame(() => {
			delete this._fix_position_animation_frame;
			this.fixPosition();
		});
	}
};

Overlay.prototype._cancelFixPositionOnAnimationFrame = function() {
	if (this._fix_position_animation_frame !== undefined) {
		cancelAnimationFrame(this._fix_position_animation_frame);
		this._fix_position_animation_frame = undefined;
	}
};

/**
 * Determines element to place focus on and focuses it if found.
 */
Overlay.prototype.recoverFocus = function() {
	if (this.$btn_focus) {
		this.$btn_focus[0].focus({preventScroll: true});
		return;
	}

	if (jQuery('[autofocus=autofocus]', this.$dialogue).length) {
		jQuery('[autofocus=autofocus]', this.$dialogue)[0]?.focus({preventScroll: true});
	}
	else if (jQuery('.overlay-dialogue-body form :focusable', this.$dialogue).length) {
		jQuery('.overlay-dialogue-body form :focusable', this.$dialogue)[0]?.focus({preventScroll: true});
	}
	else {
		jQuery(':focusable:first', this.$dialogue)[0]?.focus({preventScroll: true});
	}
};

/**
 * Binds keyboard events to contain focus within dialogue window.
 */
Overlay.prototype.containFocus = function() {
	var focusable = jQuery(':focusable', this.$dialogue);

	focusable.off('keydown.containFocus');

	if (focusable.length > 1) {
		var first_focusable = focusable.filter(':first:not([disabled])'),
			last_focusable = focusable.filter(':last:not([disabled])');

		first_focusable
			.on('keydown.containFocus', function(e) {
				// TAB and SHIFT
				if (e.which == 9 && e.shiftKey) {
					last_focusable[0].focus();
					return false;
				}
			});

		last_focusable
			.on('keydown.containFocus', function(e) {
				// TAB and not SHIFT
				if (e.which == 9 && !e.shiftKey) {
					first_focusable[0].focus();
					return false;
				}
			});
	}
	else {
		focusable
			.on('keydown.containFocus', function(e) {
				if (e.which == 9) {
					return false;
				}
			});
	}
};

/**
 * Sets dialogue in loading state.
 */
Overlay.prototype.setLoading = function() {
	this.$dialogue.$body.addClass('is-loading is-loading-fadein');
	this.$dialogue.$controls.find('z-select, button').prop('disabled', true);

	this.$dialogue.$footer.find('button:not(.js-cancel)').each(function() {
		$(this).prop('disabled', true);
	});
};

/**
 * Sets dialogue in idle state.
 */
Overlay.prototype.unsetLoading = function() {
	this.$dialogue.$body.removeClass('is-loading is-loading-fadein');
	this.$dialogue.$footer.find('button:not(.js-cancel)').each(function() {
		if ($(this).data('disabled') !== true) {
			$(this).removeClass('is-loading').prop('disabled', false);
		}
	});
};

/**
 * @param {string} action
 * @param {array|object} options (optional)
 *
 * @return {jQuery.XHR}
 */
Overlay.prototype.load = function(action, options) {
	var url = new Curl('zabbix.php');
	url.setArgument('action', action);

	// Properties 'action' and 'options' are stored to enable popup reload. This may be done outside the class.
	this.action = action;
	this.options = options;

	if (this.xhr) {
		this.xhr.abort();
	}

	this.setLoading();
	this.xhr = jQuery.ajax({
		url: url.getUrl(),
		type: 'post',
		dataType: 'json',
		data: options
	});

	this.xhr.always(function() {
		this.unsetLoading();
	}.bind(this));

	return this.xhr;
};

/**
 * Unmount the overlay. Will remove DOM container and disconnect all event listeners.
 */
Overlay.prototype.unmount = function() {
	this.unsetProperty('prevent_navigation');

	if (this.cancel_action !== null && !this._block_cancel_action) {
		this.cancel_action();
	}

	this.$dialogue.$head.$close_button[0].removeEventListener('click', this._listeners.close_button_click);
	this.$dialogue.$body[0].removeEventListener('submit', this._listeners.form_submit);
	this.$dialogue.$head[0].removeEventListener('dblclick', this._listeners.head_double_click);
	window.removeEventListener('resize', this._listeners.window_resize);
	document.removeEventListener('debug.click', this._listeners.debug_click);

	this._body_mutation_observer.disconnect();
	this._cancelFixPositionOnAnimationFrame();

	if (this._is_draggable) {
		this.$dialogue.draggable('destroy');
	}

	const wrapper = document.querySelector('.wrapper');

	this.$dialogue[0].remove();

	if (this._is_modal) {
		this.$backdrop[0].remove();

		wrapper.style.overflow = this._wrapper_overflow;

		wrapper.scrollTo(this._wrapper_scroll_x, this._wrapper_scroll_y);
	}
};

/**
 * Mount and position the overlay according to settings.
 */
Overlay.prototype.mount = function() {
	const wrapper = document.querySelector('.wrapper');

	if (this._is_modal) {
		this._wrapper_scroll_x = wrapper.scrollLeft;
		this._wrapper_scroll_y = wrapper.scrollTop;

		this._wrapper_overflow = wrapper.style.overflow;
		wrapper.style.overflow = 'hidden';

		wrapper.appendChild(this.$backdrop[0]);
	}

	wrapper.appendChild(this.$dialogue[0]);

	if (this._is_draggable) {
		this.$dialogue.draggable({
			handle: '.overlay-dialogue-header',
			cancel: 'a, button',
			start: this._listeners.drag_start,
			stop: this._listeners.drag_stop
		});
	}

	this._body_mutation_observer = new MutationObserver(() => this._fixPositionOnAnimationFrame());

	const observable_elements = [this.$dialogue.$controls[0], this.$dialogue.$head[0], this.$dialogue.$body[0],
		this.$dialogue.$footer[0]
	];

	for (const observable_element of observable_elements) {
		this._body_mutation_observer.observe(observable_element, {
			childList: true,
			subtree: true,
			attributeFilter: ['style', 'class', 'hidden']
		});
	}

	this.$dialogue.$head.$close_button[0].addEventListener('click', this._listeners.close_button_click);
	this.$dialogue.$body[0].addEventListener('submit', this._listeners.form_submit);
	this.$dialogue.$head[0].addEventListener('dblclick', this._listeners.head_double_click);
	window.addEventListener('resize', this._listeners.window_resize);
	document.addEventListener('debug.click', this._listeners.debug_click);

	this.$dialogue[0].style.width = '';
	this.$dialogue[0].style.height = '';
	this.$dialogue[0].style.left = '';
	this.$dialogue[0].style.top = '';

	const default_style = getComputedStyle(this.$dialogue[0]);

	this._dialogue_offset_top = parseFloat(default_style.top);
	this._dialogue_margin_left = parseFloat(default_style.marginLeft);
	this._dialogue_margin_right = parseFloat(default_style.marginRight);

	this._force_visible_x = true;
	this._force_visible_y = true;
	this._is_dragging = false;

	this.fixPosition();

	this._block_cancel_action = false;
};

/**
 * @param {object} obj
 *
 * @return {jQuery}
 */
Overlay.prototype.makeButton = function(obj) {
	var $button = jQuery('<button>', {
			type: 'button',
			text: obj.title
		});

	$button.on('click', function(e) {
		if (('confirmation' in obj) && !confirm(obj.confirmation)) {
			e.preventDefault();
			return;
		}

		if (!('cancel' in obj) || !obj.cancel) {
			$(e.target)
				.blur()
				.addClass('is-loading')
				.prop('disabled', true)
				.siblings(':not(.js-cancel)')
					.prop('disabled', true);
		}

		if (obj.action && obj.action(this) !== false) {
			this._block_cancel_action = true;

			if (!obj.keepOpen) {
				if (this.has_custom_cancel) {
					this.$dialogue[0].dispatchEvent(new CustomEvent('dialogue.cancel', {detail: {
						dialogueid: this.dialogueid
					}}));
				}
				else {
					overlayDialogueDestroy(this.dialogueid, this.CLOSE_BY_USER);
				}
			}
		}

		e.preventDefault();
	}.bind(this));

	if (obj.class) {
		$button.addClass(obj.class);
	}

	if (obj.enabled === false) {
		$button
			.prop('disabled', true)
			.data('disabled', true);
	}

	return $button;
};

/**
 * @param {array} arr
 *
 * @return {array}
 */
Overlay.prototype.makeButtons = function(arr) {
	var buttons = [];

	this.cancel_action = null;
	this.$btn_submit = null;
	this.$btn_focus = null;

	arr.forEach(function(obj) {
		if (typeof obj.action === 'string') {
			obj.action = new Function('overlay', obj.action);
		}

		var $button = this.makeButton(obj);

		if (obj.cancel) {
			this.cancel_action = obj.action;
		}

		if (obj.isSubmit) {
			this.$btn_submit = $button;
		}

		if (obj.focused) {
			this.$btn_focus = $button;
		}

		buttons.push($button);
	}.bind(this));

	return buttons;
};

Overlay.prototype.preventNavigation = function(event) {
	event.preventDefault();
	event.returnValue = '';
};

/**
 * @param {string} key
 */
Overlay.prototype.unsetProperty = function(key) {
	switch (key) {
		case 'title':
			this.$dialogue.$head.$header.text('');
			break;

		case 'doc_url':
			const doc_link = this.$dialogue.$head[0].querySelector('.' + ZBX_ICON_HELP_SMALL);
			if (doc_link !== null) {
				doc_link.remove();
			}
			break;

		case 'buttons':
			this.$dialogue.$footer.find('button').remove();
			break;

		case 'content':
			this.$dialogue.$body.html('');
			if (this.$dialogue.$debug.html().length) {
				this.$dialogue.$body.append(this.$dialogue.$debug);
			}
			break;

		case 'controls':
			this.$dialogue.$controls.remove();
			break;

		case 'debug':
			this.$dialogue.$debug.remove();
			break;

		case 'script_inline':
			this.$dialogue.$script.remove();
			break;

		case 'prevent_navigation':
			const dialogues = Object.values(overlays_stack.map).filter((overlay) => overlay.$dialogue !== undefined);
			let prevent_navigation = false;

			if (this.$dialogue !== undefined) {
				if (dialogues.length === 0) {
					if (this.$dialogue[0].dataset.preventNavigation === 'true' && !isVisible(this.$dialogue[0])) {
						prevent_navigation = true;
					}
				}
				else {
					// Dialogue was closed.
					if (dialogues[dialogues.length - 1].dialogueid === this.dialogueid
							&& isVisible(this.$dialogue[0])) {
						// Ignore last dialogue in stack, because it is same as "this" (which was closed).
						dialogues.pop();

						if (dialogues.some((dialogue) => dialogue.$dialogue[0].dataset.preventNavigation === 'true')) {
							prevent_navigation = true;
						}
					}
					// Dialogue was opened.
					else {
						if (dialogues.some((dialogue) => dialogue.$dialogue[0].dataset.preventNavigation === 'true')
								|| this.$dialogue[0].dataset.preventNavigation === 'true') {
							prevent_navigation = true;
						}
					}
				}
			}

			if (!prevent_navigation) {
				removeEventListener('beforeunload', this.preventNavigation);
			}

			break;
	}
};

/**
 * Set overlay properties.
 *
 * Whenever setting multiple properties at once, the "script_inline" will execute in the end.
 *
 * @param {Object} properties
 */
Overlay.prototype.setProperties = function(properties) {
	const names_ordered = ['class', 'title', 'doc_url', 'buttons', 'footer', 'content', 'controls', 'debug',
		'prevent_navigation', 'data', 'script_inline'
	];

	for (const name of names_ordered) {
		if (!(name in properties)) {
			continue;
		}

		const value = properties[name];

		if (!value) {
			this.unsetProperty(name);
			continue;
		}

		switch (name) {
			case 'class':
				this.$dialogue.addClass(value);
				break;

			case 'title':
				this.$dialogue.$head.$header.text(value);
				break;

			case 'doc_url':
				this.unsetProperty(name);
				this.$dialogue.$head.$header[0].insertAdjacentHTML('afterend', `
					<a class="${ZBX_STYLE_BTN_ICON} ${ZBX_ICON_HELP_SMALL}" target="_blank" title="${t('Help')}" href="${value}"></a>
				`);
				break;

			case 'buttons':
				this.unsetProperty(name);
				this.$dialogue.$footer.append(this.makeButtons(value));
				break;

			case 'footer':
				this.unsetProperty(name);
				this.$dialogue.$footer.append(value);
				break;

			case 'content':
				if (typeof value === 'string') {
					// Preserve inline script execution.
					this.$dialogue.$body[0].innerHTML = '';
					this.$dialogue.$body[0].appendChild(
						document.createRange().createContextualFragment(value)
					);
				}
				else {
					this.$dialogue.$body.html(value);
				}
				if (this.$dialogue.$debug.html().length) {
					this.$dialogue.$body.append(this.$dialogue.$debug);
				}
				break;

			case 'controls':
				this.$dialogue.$controls.html(value);
				this.$dialogue.$body.before(this.$dialogue.$controls);
				break;

			case 'debug':
				this.$dialogue.$debug.html(jQuery(value).html());
				this.$dialogue.$body.append(this.$dialogue.$debug);
				break;

			case 'script_inline':
				this.unsetProperty(name);
				// See: jQuery.html() rnoInnerhtml = /<script|<style|<link/i
				// If content matches this regex it will be parsed in jQuery.buildFragment as HTML, but here we have JS.
				this.$dialogue.$script.get(0).innerHTML = value;
				this.$dialogue.$footer.prepend(this.$dialogue.$script);
				break;

			case 'prevent_navigation':
				this.$dialogue[0].dataset.preventNavigation = value;
				this.unsetProperty(name);
				window.addEventListener('beforeunload', this.preventNavigation, {passive: false});
				break;

			case 'data':
				this.data = value;
				break;
		}
	}
};
