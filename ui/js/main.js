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


// Global constants.

// Sync with SASS variable: $ui-transition-duration.
const UI_TRANSITION_DURATION = 300;

const PROFILE_TYPE_INT = 2;
const PROFILE_TYPE_STR = 3;

// Array indexOf method for javascript<1.6 compatibility
if (!Array.prototype.indexOf) {
	Array.prototype.indexOf = function (searchElement) {
		if (this === void 0 || this === null) {
			throw new TypeError();
		}
		var t = Object(this);
		var len = t.length >>> 0;
		if (len === 0) {
			return -1;
		}
		var n = 0;
		if (arguments.length > 0) {
			n = Number(arguments[1]);
			if (n !== n) { // shortcut for verifying if it's NaN
				n = 0;
			}
			else if (n !== 0 && n !== (1 / 0) && n !== -(1 / 0)) {
				n = (n > 0 || -1) * Math.floor(Math.abs(n));
			}
		}
		if (n >= len) {
			return -1;
		}
		var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
		for (; k < len; k++) {
			if (k in t && t[k] === searchElement) {
				return k;
			}
		}
		return -1;
	}
}

/*
 * Page refresh
 */
var PageRefresh = {
	delay:		null, // refresh timeout
	delayLeft:	null, // left till refresh
	timeout:	null, // link to timeout

	init: function(time) {
		this.delay = time;
		this.delayLeft = this.delay;
		this.start();
	},

	check: function() {
		if (is_null(this.delay)) {
			return false;
		}

		this.delayLeft = Math.max(-1, this.delayLeft - 1000);

		if (this.delayLeft < 0 && !overlays_stack.length) {
			if (ED) {
				sessionStorage.scrollTop = $('.wrapper').scrollTop();
			}

			location.reload();
		}
		else {
			this.timeout = setTimeout('PageRefresh.check()', 1000);
		}
	},

	start: function() {
		if (is_null(this.delay)) {
			return false;
		}
		this.timeout = setTimeout('PageRefresh.check()', 1000);
	},

	stop: function() {
		clearTimeout(this.timeout);
	},

	restart: function() {
		this.stop();
		this.delayLeft = this.delay;
		this.start();
	}
};

/*
 * Audio control system.
 */
var AudioControl = {

	timeoutHandler: null,

	loop: function(timeout) {
		AudioControl.timeoutHandler = setTimeout(
			function() {
				if (new Date().getTime() >= timeout) {
					AudioControl.stop();
				}
				else {
					AudioControl.loop(timeout);
				}
			},
			1000
		);
	},

	playOnce: function(name) {
		this.stop();

		var obj = jQuery('#audio');

		if (obj.length > 0 && obj.data('name') === name) {
			obj.trigger('play');
		}
		else {
			this.create(name, false);
		}
	},

	playLoop: function(name, delay) {
		this.stop();

		var obj = jQuery('#audio');

		if (obj.length > 0 && obj.data('name') === name) {
			obj.trigger('play');
		}
		else {
			this.create(name, true);
		}

		AudioControl.loop(new Date().getTime() + delay * 1000);
	},

	stop: function() {
		var obj = document.getElementById('audio');

		if (obj !== null) {
			clearTimeout(AudioControl.timeoutHandler);

			jQuery(obj).trigger('pause');
		}
	},

	create: function(name, loop) {
		var obj = jQuery('#audio');

		if (obj.length == 0 || obj.data('name') !== name) {
			obj.remove();

			var audioOptions = {
				id: 'audio',
				'data-name': name,
				src: 'audio/' + name,
				preload: 'auto',
				autoplay: true
			};

			if (loop) {
				audioOptions.loop = true;
			}

			jQuery('body').append(jQuery('<audio>', audioOptions));
		}
	}
};

/*
 * Replace standard blink functionality
 */
/**
 * Sets HTML elements to blink.
 * Example of usage:
 *      <span class="js-blink" data-time-to-blink="60">test 1</span>
 *      <span class="js-blink" data-time-to-blink="30">test 2</span>
 *      <span class="js-blink" data-toggle-class="normal">test 3</span>
 *      <span class="js-blink">test 3</span>
 *      <script type="text/javascript">
 *          jQuery(document).ready(function(
 *              jqBlink.blink();
 *          ));
 *      </script>
 * Elements with class 'js-blink' will blink for 'data-seconds-to-blink' seconds
 * If 'data-seconds-to-blink' is omitted, element will blink forever.
 * For elements with class 'js-blink' and attribute 'data-toggle-class' class will be toggled.
 */
var jqBlink = {
	shown: true, // are objects currently shown or hidden?
	interval: 1000, // how fast will they blink (ms)

	/**
	 * Shows/hides the elements and repeats it self after 'this.blinkInterval' ms
	 */
	blink: function() {
		var that = this;

		setInterval(function() {
			var $collection = jQuery('.js-blink');

			$collection.each(function() {
				var $el = jQuery(this),
					blink = true;

				if (typeof $el.data('timeToBlink') !== 'undefined') {
					blink = (($el.data()['timeToBlink']--) > 0);
				}

				if (blink) {
					if (typeof $el.data('toggleClass') !== 'undefined') {
						$el.toggleClass($el.data('toggleClass'));
					}
					else {
						$el.css('opacity', that.shown ? '1' : '0');
					}
				}
				else if (that.shown) {
					$el.removeClass('js-blink').removeClass($el.data('toggleClass')).css('opacity', 1);
				}
			});

			that.shown = !that.shown;
		}, this.interval);
	}
};

/*
 * HintBox class.
 */
var hintBox = {

	show_hint_timer: null,

	/**
	 * Initialize hint box event handlers.
	 *
	 * Triggered events:
	 * - onShowHint.hintBox 	- when show a hintbox.
	 * - onHideHint.hintBox 	- when hide a hintbox.
	 * - onDeleteHint.hintBox 	- when removing a hintbox.
	 */
	bindEvents: function () {
		jQuery(document).on('keydown click mouseenter mousemove mouseleave', '[data-hintbox=1]', function (e) {
			const $target = jQuery(this).hasClass('hint-item')
				? jQuery(this).siblings('.main-hint')
				: jQuery(this);

			if (e.type === 'keydown') {
				if (e.which !== 13) {
					return;
				}

				const offset = $target.offset(),
					w = jQuery(window);

				// Emulate a click on the left middle point of the target.
				e.clientX = offset.left - w.scrollLeft();
				e.clientY = offset.top - w.scrollTop() + ($target.height() / 2);
				e.preventDefault();
			}

			hintBox.displayHint(e, $target, $target.data('hintbox-delay') !== undefined
				? $target.data('hintbox-delay')
				: 400
			);

			return false;
		});
	},

	displayHint: function(e, $target, delay = 0) {
		clearTimeout(hintBox.show_hint_timer);

		switch (e.handleObj.origType) {
			case 'mouseenter':
				hintBox.showHintStart(e, $target, delay);
				break;

			case 'mousemove':
				if (!$target[0].hintBoxItem) {
					hintBox.showHintStart(e, $target, delay);
				}
				else if ($target.data('hintbox-track-mouse') === 1 && !$target[0].isStatic) {
					hintBox.positionElement(e, $target, $target[0].hintBoxItem);
				}
				break;

			case 'mouseleave':
				hintBox.hideHint($target[0], false);
				$target.blur();
				break;

			case 'keydown':
			case 'click':
				if ($target.data('hintbox-static') === 1) {
					hintBox.showStaticHint(e, $target[0], $target.data('hintbox-class'), false,
						$target.data('hintbox-style')
					);
				}
				break;
		}
	},

	showHintStart: function (e, $target, delay) {
		const showHintHandler = function() {
			hintBox.showHint(e, $target[0], $target[0].dataset.hintboxContents, $target.data('hintbox-class'),
				false, $target.data('hintbox-style')
			);
		}

		if (delay > 0) {
			hintBox.show_hint_timer = setTimeout(showHintHandler, delay);
		}
		else {
			showHintHandler();
		}
	},

	getHintboxAction: function(hint_type) {
		switch (hint_type) {
			case 'eventlist':
				return 'hintbox.eventlist';

			case 'eventactions':
				return 'hintbox.actionlist';
		}
	},

	preloadHint: function(e, target, box) {
		const url = new Curl('zabbix.php');
		const data = jQuery(target).data('hintbox-preload');

		url.setArgument('action', data.action || hintBox.getHintboxAction(data.type));

		const xhr = jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: data.data,
			dataType: 'json'
		});

		const $preloader = jQuery('<div>', {
			'id': 'hintbox-preloader',
			'class': 'is-loading hintbox-preloader'
		}).appendTo(box);

		xhr.done(function(resp) {
			let hintbox_contents = '';

			if ('error' in resp) {
				const message_box = makeMessageBox('bad', resp.error.messages, resp.error.title, false, true)[0];

				hintbox_contents += message_box.innerHTML;
			}
			else {
				if (resp.messages) {
					hintbox_contents += resp.messages;
				}

				if (resp.data) {
					hintbox_contents += resp.data;
				}

				if (resp.value) {
					hintbox_contents += resp.value;
				}
			}

			target.dataset.hintboxContents = hintbox_contents;

			$preloader.remove();

			if (target.hintBoxItem !== undefined) {
				box.append(hintbox_contents);

				// Reset hintbox position.
				box.css({
					width: '',
					height: '',
					top: '',
					left: ''
				});

				hintBox.positionElement(e, target, target.hintBoxItem);
			}
		});
	},

	createBox: function(e, target, hintText, className, isStatic, styles, appendTo, reposition_on_resize = true) {
		var hintboxid = hintBox.getUniqueId(),
			box = jQuery('<div>', {'data-hintboxid': hintboxid}).addClass('overlay-dialogue wordbreak'),
			appendTo = appendTo || '.wrapper';

		if (styles) {
			// property1: value1; property2: value2; property(n): value(n)

			var style_list = styles.split(';');

			for (var i = 0; i < style_list.length; i++) {
				var style_props = style_list[i].split(':');

				if (style_props[1]) {
					box.css(style_props[0].trim(), style_props[1].trim());
				}
			}
		}

		if (typeof hintText === 'string') {
			hintText = hintText.replace(/\n/g, '<br />');
		}

		if (!empty(className)) {
			box.append(jQuery('<div>').addClass(className).html(hintText));
		}
		else {
			box.html(hintText);
		}

		if (isStatic) {
			target.hintboxid = hintboxid;
			jQuery(target).attr('data-expanded', 'true');
			addToOverlaysStack(hintboxid, target, 'hintbox');

			var close_link = jQuery('<button>', {
					'class': 'btn-overlay-close',
					'title': t('S_CLOSE')
				}
			)
				.click(function() {
					hintBox.hideHint(target, true);
				});
			box.prepend(close_link);
		}

		if (target.dataset?.hintboxPreload !== '' && target.dataset?.hintboxContents === '') {
			hintBox.preloadHint(e, target, box);
		}

		jQuery(appendTo).append(box);

		target.observer = new MutationObserver(() => {
			const element = target instanceof jQuery ? target[0] : target;

			if (!isVisible(element)) {
				hintBox.deleteHint(target);
			}

			setTimeout(() => {
				if (target.scroll_observer !== undefined) {
					const element_rect = element.getBoundingClientRect();

					if ((element_rect.x !== target.scroll_observer.x || element_rect.y !== target.scroll_observer.y)
							&& element.dataset.hintboxIgnorePositionChange !== '1') {
						const do_focus_target = document.activeElement === document.body
							|| element.hintBoxItem[0].contains(document.activeElement);

						hintBox.deleteHint(target, do_focus_target);
					}
				}
			});
		});

		target.observer.observe(document.body, {
			attributes: true,
			attributeFilter: ['style', 'class'],
			subtree: true,
			childList: true
		});

		if (reposition_on_resize) {
			target.resize_observer = new ResizeObserver(() => hintBox.onResize(e, target));
			target.resize_observer.observe(box[0]);
		}

		return box;
	},

	showStaticHint: function(e, target, className, resizeAfterLoad, styles, hintText) {
		const isStatic = target.isStatic;
		hintBox.hideHint(target, true);

		if (!isStatic) {
			if (typeof hintText === 'undefined') {
				hintText = target.dataset.hintboxContents;
			}

			target.isStatic = true;
			hintBox.showHint(e, target, hintText, className, true, styles);
			jQuery(target).data('return-control', jQuery(e.target));

			if (resizeAfterLoad) {
				const resetSize = () => {
					for (const css_property of ['width', 'height']) {
						target.hintBoxItem[0].style[css_property] = null;
					}
				};

				resetSize();

				const preloader = document.createElement('div');
				preloader.id = 'hintbox-preloader';
				preloader.className = 'is-loading hintbox-preloader';

				target.hintBoxItem[0].append(preloader);

				hintText[0].style.display = 'none';

				hintText.one('load', function(e) {
					resetSize();

					preloader.remove();
					hintText[0].style.display = null;

					hintBox.positionElement(e, target, target.hintBoxItem);
				});
			}

			const element = target instanceof jQuery ? target[0] : target;
			const element_rect_initial = element.getBoundingClientRect();

			target.scroll_observer = {
				x: element_rect_initial.x,
				y: element_rect_initial.y,
				callback: (e) => hintBox.onScroll(target, e)
			};

			addEventListener('scroll', target.scroll_observer.callback, {capture: true});
		}

		addEventListener('resize', target.resizeHandler = e => hintBox.onResize(e, target));
	},

	onScroll: function(target, e) {
		const element = target instanceof jQuery ? target[0] : target;
		const element_rect = element.getBoundingClientRect();

		if (e.target.classList.contains('wrapper')) {
			target.scroll_observer.x = element_rect.x;
			target.scroll_observer.y = element_rect.y;

			return;
		}

		if (element_rect.x !== target.scroll_observer.x || element_rect.y !== target.scroll_observer.y) {
			const do_focus_target = document.activeElement === document.body
				|| element.hintBoxItem[0].contains(document.activeElement);

			hintBox.deleteHint(target, do_focus_target);
		}
	},

	showHint: function(e, target, hintText, className, isStatic, styles) {
		if (target.hintBoxItem) {
			return;
		}

		target.hintBoxItem = hintBox.createBox(e, target, hintText, className, isStatic, styles);
		hintBox.positionElement(e, target, target.hintBoxItem);
		target.hintBoxItem.show();

		if (target.isStatic) {
			Overlay.prototype.recoverFocus.call({'$dialogue': target.hintBoxItem});
			Overlay.prototype.containFocus.call({'$dialogue': target.hintBoxItem});
		}

		target.dispatchEvent(new CustomEvent('onShowHint.hintBox'));
	},

	positionElement: function(e, target, $elem) {
		const hint = $elem[0];
		const host = hint.offsetParent;
		const host_rect = host.getBoundingClientRect();
		const event_offset = 10;
		const css = {
			width: null,
			height: null,
			top: null,
			left: null
		};

		const resetPosition = () => {
			Object.keys(css).forEach(key => {
				css[key] = null;
				hint.style[key] = null;
			});
		};

		resetPosition();

		let host_client_width = host.clientWidth;
		let host_client_height = host.clientHeight;

		do {
			// Check if client size has decreased (because of scrollbars).
			if (host_client_width > host.clientWidth) {
				host.style.overflowY = 'scroll';
			}
			if (host_client_height > host.clientHeight) {
				host.style.overflowX = 'scroll';
			}

			host_client_width = host.clientWidth;
			host_client_height = host.clientHeight;

			resetPosition();

			const scrollbar_vertical_width = host.offsetWidth - host.clientWidth;
			const scrollbar_horizontal_height = host.offsetHeight - host.clientHeight;

			// Usable area relative to host.
			const host_x_min = host.scrollLeft;
			const host_x_max = Math.min(host.scrollWidth, host_rect.width - scrollbar_vertical_width + host_x_min);
			const host_y_min = host.scrollTop;
			const host_y_max = Math.min(host.scrollHeight, host_rect.height - scrollbar_horizontal_height + host_y_min);

			// Hint size.
			const hint_rect = hint.getBoundingClientRect();
			const hint_computed_style = getComputedStyle(hint);

			/*
				Fix popup width and height since browsers will tend to reduce the size of the popup,
				if positioned further than the width of window when horizontal scrolling is active.
			*/
			css.width = Math.ceil(parseFloat(hint_computed_style.width));
			css.height = Math.ceil(parseFloat(hint_computed_style.height));

			// Event coordinates relative to host.
			if ((target.event_x === null || target.event_x === undefined) && e.originalEvent.clientX !== undefined) {
				target.event_x = e.originalEvent.clientX - host_rect.left + host_x_min;
				target.event_y = e.originalEvent.clientY - host_rect.top + host_y_min;
			}

			css.left = target.event_x + event_offset + hint_rect.width <= host_x_max
				? target.event_x + event_offset
				: host_x_max - hint_rect.width;

			// Hint fits under event.
			if (target.event_y + event_offset + hint_rect.height <= host_y_max) {
				css.top = target.event_y + event_offset;
			}
			// Hint fits above event.
			else if (target.event_y - event_offset - hint_rect.height >= host_y_min) {
				css.top = target.event_y - event_offset - hint_rect.height;
			}
			// Hint fits neither under nor above event - then show it under event.
			else {
				css.top = target.event_y + event_offset;
			}

			// Assign css rules to hint.
			Object.entries(css).forEach(([key, value]) => hint.style[key] = value !== null ? `${value}px` : null);
		}
		while (host_client_width > host.clientWidth || host_client_height > host.clientHeight);

		host.style.overflowX = null;
		host.style.overflowY = null;
	},

	hideHint: function(target, hideStatic) {
		if (target.isStatic && !hideStatic) {
			return;
		}

		hintBox.deleteHint(target);

		target = target instanceof jQuery ? target[0] : target;

		target.dispatchEvent(new CustomEvent('onHideHint.hintBox'));
	},

	deleteHint: function(target, do_focus_target = true) {
		target.event_x = null;
		target.event_y = null;

		if (typeof target.hintboxid !== 'undefined') {
			jQuery(target).removeAttr('data-expanded');
			removeFromOverlaysStack(target.hintboxid, do_focus_target);
		}

		if (target.hintBoxItem) {
			target.hintBoxItem.trigger('onDeleteHint.hintBox');
			target.hintBoxItem.remove();
			delete target.hintBoxItem;

			if (target.isStatic) {
				if (jQuery(target).data('return-control') !== undefined && do_focus_target) {
					jQuery(target).data('return-control').focus();
				}
				delete target.isStatic;
			}
		}

		if (target.observer !== undefined) {
			target.observer.disconnect();

			delete target.observer;
		}

		if (target.scroll_observer !== undefined) {
			removeEventListener('scroll', target.scroll_observer.callback, {capture: true});

			delete target.scroll_observer;
		}

		if (target.resize_observer !== undefined) {
			target.resize_observer.disconnect();

			delete target.resize_observer;
		}

		removeEventListener('resize', target.resizeHandler);
	},

	deleteAll: () => {
		for (let i = overlays_stack.length - 1; i >= 0; i--) {
			const overlay = overlays_stack.getById(overlays_stack.stack[i]);

			if (overlay.type === 'hintbox') {
				hintBox.deleteHint(overlay.element);
			}
		}
	},

	getUniqueId: function() {
		var hintboxid = Math.random().toString(36).substring(7);
		while (jQuery('[data-hintboxid="' + hintboxid + '"]').length) {
			hintboxid = Math.random().toString(36).substring(7);
		}

		return hintboxid;
	},

	onResize: function(e, target) {
		if (target && target.hintBoxItem) {
			hintBox.positionElement(e, target, target.hintBoxItem);
		}
	}
};

/**
 * Perform JSON-RPC Zabbix API call.
 *
 * @param {string} method
 * @param {object} params
 * @param {int}    id
 *
 * @returns {Promise<any>}
 */
function ApiCall(method, params, id = 1) {
	return fetch(new Curl('api_jsonrpc.php').getUrl(), {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		credentials: 'same-origin',
		body: JSON.stringify({jsonrpc: '2.0', method, params, id}),
	})
		.then(response => response.json())
		.then(response => {
			if ('error' in response) {
				throw new Error(response.error.message, {
					cause: { code: response.error.code, data: response.error.data }
				});
			}

			return response;
		});
}

/**
 * Section collapse toggle.
 *
 * @param {string}      id
 * @param {string|null} profile_idx  If not null, stores state in profile.
 */
function toggleSection(id, profile_idx) {
	const section = document.getElementById(id);
	const toggle = section.querySelector('.section-toggle');

	let is_collapsed = section.classList.contains(ZBX_STYLE_COLLAPSED);

	section.classList.toggle(ZBX_STYLE_COLLAPSED, !is_collapsed);

	toggle.classList.toggle(ZBX_ICON_CHEVRON_DOWN, !is_collapsed);
	toggle.classList.toggle(ZBX_ICON_CHEVRON_UP, is_collapsed);
	toggle.setAttribute('title', is_collapsed ? t('S_COLLAPSE') : t('S_EXPAND'));

	if (profile_idx !== '') {
		updateUserProfile(profile_idx, is_collapsed ? '1' : '0', []);
	}
}

/**
 * Send ajax data.
 *
 * @param string url
 * @param object options
 */
function sendAjaxData(url, options) {
	var url = new Curl(url);
	url.setArgument('output', 'ajax');

	options.type = 'post';
	options.url = url.getUrl();

	return jQuery.ajax(options);
}

/**
 * Converts number to letter representation.
 * From A to Z, then from AA to ZZ etc.
 * Example: 0 => A, 25 => Z, 26 => AA, 27 => AB, 52 => BA, ...
 *
 * Keep in sync with PHP num2letter().
 *
 * @param {int} number
 *
 * @return {string}
 */
function num2letter(number) {
	var start = 'A'.charCodeAt(0);
	var base = 26;
	var str = '';
	var level = 0;

	do {
		if (level++ > 0) {
			number--;
		}
		var remainder = number % base;
		number = (number - remainder) / base;
		str = String.fromCharCode(start + remainder) + str;
	} while (number);

	return str;
}

/**
 * Generate a formula from the given conditions with respect to the given evaluation type.
 * Each condition must have a condition type, that will be used for grouping.
 *
 * Each condition object must have the following properties:
 * - id		- ID used in the formula
 * - type	- condition type used for grouping
 *
 * Supported evalType values:
 * - 1 - or
 * - 2 - and
 * - 3 - and/or
 *
 * Example:
 * getConditionFormula([{'id': 'A', 'type': '1'}, {'id': 'B', 'type': '1'}, {'id': 'C', 'type': '2'}], '1');
 *
 * // (A and B) and C
 *
 * Keep in sync with PHP CConditionHelper::getFormula().
 *
 * @param {array} 	conditions	array of condition objects
 * @param {string} 	evalType
 *
 * @returns {string}
 */
function getConditionFormula(conditions, evalType) {
	var conditionOperator, groupOperator;

	switch (evalType) {
		// and
		case 1:
			conditionOperator = 'and';
			groupOperator = conditionOperator;
			break;

		// or
		case 2:
			conditionOperator = 'or';
			groupOperator = conditionOperator;
			break;

		// and/or
		default:
			conditionOperator = 'or';
			groupOperator = 'and';
	}

	var groupedFormulas = [];

	for (var i = 0; i < conditions.length; i++) {
		if (typeof conditions[i] === 'undefined') {
			continue;
		}

		var groupedConditions = [];

		groupedConditions.push(conditions[i].id);

		// Search for other conditions of the same type.
		for (var n = i + 1; n < conditions.length; n++) {
			if (typeof conditions[n] !== 'undefined' && conditions[i].type == conditions[n].type) {
				groupedConditions.push(conditions[n].id);
				delete conditions[n];
			}
		}

		// Join conditions of the same type.
		if (groupedConditions.length > 1) {
			groupedFormulas.push('(' + groupedConditions.join(' ' + conditionOperator + ' ') + ')');
		}
		else {
			groupedFormulas.push(groupedConditions[0]);
		}
	}

	var formula = groupedFormulas.join(' ' + groupOperator + ' ');

	// Strip parentheses if there's only one condition group.
	if (groupedFormulas.length == 1) {
		formula = formula.substr(1, formula.length - 2);
	}

	return formula;
}

(function($) {
	/**
	 * Creates a table with dynamic add/remove row buttons.
	 *
	 * Supported options:
	 * - template				- row template selector
	 * - row					- element row selector
	 * - add					- add row button selector
	 * - remove					- remove row button selector
	 * - rows					- array of rows objects data
	 * - counter 				- number to start row enumeration from
	 * - dataCallback			- function to generate the data passed to the template
	 * - remove_next_sibling	- remove also next element
	 * - sortable				- enable CSortable class initialization
	 * - sortable_options		- additional options to pass to CSortable class constructor
	 *
	 * Triggered events:
	 * - tableupdate.dynamicRows 	- after adding or removing a row.
	 * - beforeadd.dynamicRows 	    - only before adding a new row.
	 * - afteradd.dynamicRows 	    - only after adding a new row.
	 * - afterremove.dynamicRows 	- only after removing a row.
	 *
	 * @param options
	 */
	$.fn.dynamicRows = function(options) {
		options = $.extend({}, {
			template: '',
			row: '.form_row',
			add: '.element-table-add',
			remove: '.element-table-remove',
			remove_next_sibling: false,
			disable: '.element-table-disable',
			counter: null,
			allow_empty: false,
			beforeRow: null,
			rows: [],
			dataCallback: function(data) {
				return {};
			},
			sortable: false,
			sortable_options: {}
		}, options);

		const sortable_options = options.sortable
			? {
				selector_span: options.sortable_options.selector_span,
				selector_handle: options.sortable_options.selector_handle,
				freeze_start: options.sortable_options.freeze_start,
				freeze_end: options.sortable_options.freeze_end,
				enable_sorting: options.sortable_options.enable_sorting
			}
			: {};

		return this.each(function() {
			var table = $(this);

			if (options.sortable) {
				const sortable_target = 'target' in options.sortable_options
					? table[0].querySelector(options.sortable_options.target)
					: table[0];

				table.sortable = new CSortable(sortable_target, sortable_options);
				table.sortable.on(CSortable.EVENT_SORT, () => table.trigger('tableupdate.dynamicRows'));
			}

			// If options.remove_next_sibling is true, counter counts each row making the next index twice as large (bug).
			table.data('dynamicRows', {
				counter: (options.counter !== null) ? options.counter : $(options.row, table).length,
				addRows: (rows) => {
					const local_options = $.extend({}, options, {
						dataCallback: (data) => options.dataCallback($.extend(data, rows.shift()))
					});
					const beforeRow = (options['beforeRow'] !== null)
						? $(options['beforeRow'], table)
						: $(options.add, table).closest('tr');

					table.trigger('beforeadd.dynamicRows', options);

					while (rows.length > 0) {
						addRow(table, beforeRow, local_options);
					}

					table.trigger('afteradd.dynamicRows', options);
					table.trigger('change');

					return table;
				},
				removeRows: (filter) => {
					for (const row of $(options.row, table)) {
						if (filter === undefined || filter(row)) {
							removeRow(table, row, options);
						}
					}

					table.trigger('afterremove.dynamicRows', options);
					table.trigger('change');

					return table;
				},
				enableSorting: (enable_sorting = true) => {
					if (options.sortable) {
						table.sortable.enableSorting(enable_sorting);
					}
				}
			});

			// add buttons
			table.on('click', options.add, function() {
				table.trigger('beforeadd.dynamicRows', options);

				// add the new row before the row with the "Add" button
				var beforeRow = (options['beforeRow'] !== null)
					? $(options['beforeRow'], table)
					: $(this).closest('tr');
				addRow(table, beforeRow, options);

				if (!options.allow_empty) {
					$(options.remove, table).attr('disabled', false);
				}

				table.trigger('afteradd.dynamicRows', options);
			});

			// remove buttons
			table.on('click', options.remove, function() {
				// remove the parent row
				removeRow(table, $(this).closest(options.row), options);

				if (!options.allow_empty && $(options.row, table).length === 0) {
					table.trigger('beforeadd.dynamicRows', options);

					addRow(table, $(options.add, table).closest('tr'), options);
					$(options.remove, table).attr('disabled', true);

					table.trigger('afteradd.dynamicRows', options);
				}
			});

			// disable buttons
			table.on('click', options.disable, function() {
				// disable the parent row
				disableRow($(this).closest(options.row));
			});

			table.on('change', options, function() {
				if (!options.allow_empty) {
					$(options.remove, table).attr('disabled', false);
				}
			});

			if (options.rows.length > 0) {
				var before_row = (options['beforeRow'] !== null)
					? $(options['beforeRow'], table)
					: $(options.add, table).closest('tr');
				initRows(table, before_row, options);
			}
		});
	};

	/**
	 * Renders options.rows array as HTML rows during initialization.
	 *
	 * @param {jQuery} table       Table jquery node.
	 * @param {jQuery} before_row  Rendered rows will be inserted before this node.
	 * @param {object} options     Object with options.
	 */
	function initRows(table, before_row, options) {
		var template = new Template($(options.template).html()),
			counter = table.data('dynamicRows').counter,
			$row;

		options.rows.forEach((data) => {
			data.rowNum = counter;
			$row = $(template.evaluate($.extend(data, options.dataCallback(data))));

			for (const name in data) {
				// Set 'z-select' value.
				$row
					.find(`z-select[name$="[${counter}][${name}]"]`)
					.val(data[name]);

				// Set 'radio' value.
				$row
					.find(`[type="radio"][name$="[${counter}][${name}]"][value="${$.escapeSelector(data[name])}"]`)
					.attr('checked', 'checked');
			}

			before_row.before($row);
			++counter;
		});

		table.data('dynamicRows').counter = counter;
	}

	/**
	 * Adds a row before the given row.
	 *
	 * @param {jQuery} table
	 * @param {jQuery} beforeRow
	 * @param {object} options
	 */
	function addRow(table, beforeRow, options) {
		var data = {
			rowNum: table.data('dynamicRows').counter
		};
		data = $.extend(data, options.dataCallback(data));

		var template = new Template($(options.template).html());
		beforeRow.before(template.evaluate(data));
		table.data('dynamicRows').counter++;

		table.trigger('tableupdate.dynamicRows', options);
	}

	/**
	 * Removes the given row.
	 *
	 * @param {jQuery} table
	 * @param {jQuery} row
	 * @param {object} options
	 */
	function removeRow(table, row, options) {
		if (options.remove_next_sibling) {
			row.next().remove();
		}

		row.remove();

		table.trigger('tableupdate.dynamicRows', options);
		table.trigger('afterremove.dynamicRows', options);
	}

	/**
	 * Disables the given row.
	 *
	 * @param {jQuery} row
	 */
	function disableRow(row) {
		row.find('textarea').prop('readonly', true);
		row.find('input').prop('readonly', true);
		row.find('button').prop('disabled', true);
	}
}(jQuery));

jQuery(function ($) {
	if (ED && typeof sessionStorage.scrollTop !== 'undefined') {
		$('.wrapper').scrollTop(sessionStorage.scrollTop);
		sessionStorage.removeItem('scrollTop');
	}
});

document.addEventListener('DOMContentLoaded', () => {

	// Event hub initialization.

	ZABBIX.EventHub = new CEventHub();

	// Software version check initialization.

	if (typeof CSoftwareVersionCheck !== 'undefined') {
		ZABBIX.SoftwareVersionCheck = new CSoftwareVersionCheck();
	}
});

window.addEventListener('load', () => {

	// SideBar initialization.

	const sidebar = document.querySelector('.sidebar');

	if (sidebar !== null) {
		ZABBIX.MenuMain = new CMenu(document.querySelector('.menu-main'));
		ZABBIX.UserMain = new CMenu(document.querySelector('.menu-user'));

		ZABBIX.Sidebar = new CSidebar(sidebar)
			.on('viewmodechange', (e) => {
				updateUserProfile('web.sidebar.mode', e.detail.view_mode, []);
				window.dispatchEvent(new Event('resize'));
			});
	}
});
