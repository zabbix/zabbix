/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
 *      <span class="blink" data-time-to-blink="60">test 1</span>
 *      <span class="blink" data-time-to-blink="30">test 2</span>
 *      <span class="blink" data-toggle-class="normal">test 3</span>
 *      <span class="blink">test 3</span>
 *      <script type="text/javascript">
 *          jQuery(document).ready(function(
 *              jqBlink.blink();
 *          ));
 *      </script>
 * Elements with class 'blink' will blink for 'data-seconds-to-blink' seconds
 * If 'data-seconds-to-blink' is omitted, element will blink forever.
 * For elements with class 'blink' and attribute 'data-toggle-class' class will be toggled.
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
			var $collection = jQuery('.blink');

			$collection.each(function() {
				var $el = jQuery(this),
					blink = true;

				if (typeof $el.data('timeToBlink') !== 'undefined') {
					blink = (($el.data()['timeToBlink']--) > 0);
				}

				if (blink) {
					if (typeof $el.data('toggleClass') !== 'undefined') {
						$el[that.shown ? 'removeClass' : 'addClass']($el.data('toggleClass'));
					}
					else {
						$el.css('visibility', that.shown ? 'visible' : 'hidden');
					}
				}
				else if (that.shown) {
					$el.removeClass('blink').removeClass($el.data('toggleClass')).css('visibility', '');
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

	preload_hint_timer: null,
	show_hint_timer: null,

	/**
	 * Initialize hint box event handlers.
	 *
	 * Triggered events:
	 * - onDeleteHint.hintBox 	- when removing a hintbox.
	 */
	bindEvents: function () {
		jQuery(document).on('keydown click mouseenter mouseleave', '[data-hintbox=1]', function (e) {
			var $target = jQuery(this).hasClass('hint-item')
				? jQuery(this).siblings('.main-hint')
				: jQuery(this);

			if (e.type === 'keydown') {
				if (e.which != 13) {
					return;
				}

				var offset = $target.offset(),
					w = jQuery(window);

				// Emulate a click on the left middle point of the target.
				e.clientX = offset.left - w.scrollLeft();
				e.clientY = offset.top - w.scrollTop() + ($target.height() / 2);
				e.preventDefault();
			}

			if ($target.data('hintbox-preload') && $target.next('.hint-box').children().length == 0) {
				clearTimeout(hintBox.preload_hint_timer);

				// Manually trigger preloaderCloseHandler for the previous preloader.
				if (jQuery('#hintbox-preloader').length) {

					// Prevent loading restart on repetitive click and keydown events.
					if (e.type === 'click' || e.type === 'keydown') {
						return false;
					}

					jQuery(document).trigger('click');
				}

				if (e.type === 'mouseleave') {
					$target.blur();

					return false;
				}

				var preloadHintHandler = function() {
					hintBox.preloadHint(e, $target);
				}

				if (e.type === 'mouseenter') {
					hintBox.preload_hint_timer = setTimeout(preloadHintHandler, 400);
				}
				else {
					preloadHintHandler();
				}

				return false;
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
				var showHintHandler = function() {
					hintBox.showHint(e, $target[0], $target.next('.hint-box').html(), $target.data('hintbox-class'),
						false, $target.data('hintbox-style')
					);
				}

				if (delay > 0) {
					hintBox.show_hint_timer = setTimeout(showHintHandler, delay);
				}
				else {
					showHintHandler();
				}
				break;

			case 'mouseleave':
				hintBox.hideHint($target[0], false);
				$target.blur();
				break;

			case 'keydown':
			case 'click':
				if ($target.data('hintbox-static') == 1) {
					hintBox.showStaticHint(e, $target[0], $target.data('hintbox-class'), false,
						$target.data('hintbox-style')
					);
				}
				break;
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

	preloadHint: function(e, $target) {
		var url = new Curl('zabbix.php'),
			data = $target.data('hintbox-preload');

		url.setArgument('action', hintBox.getHintboxAction(data.type));

		var xhr = jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: data.data,
			dataType: 'json'
		});

		var $preloader = hintBox.createPreloader();

		var preloader_timer = setTimeout(function() {
			$preloader.fadeIn(200);
			hintBox.positionElement(e, $target[0], $preloader);
		}, 500);

		addToOverlaysStack($preloader.prop('id'), $target[0], 'preloader', xhr);

		xhr.done(function(resp) {
			clearTimeout(preloader_timer);
			overlayPreloaderDestroy($preloader.prop('id'));

			var $hint_box = $target.next('.hint-box').empty();

			if ('error' in resp) {
				const message_box = makeMessageBox('bad', resp.error.messages, resp.error.title, false, true);

				$hint_box.append(message_box);
			}
			else {
				if (resp.messages) {
					$hint_box.append(resp.messages);
				}

				if (resp.data) {
					$hint_box.append(resp.data);
				}
			}

			hintBox.displayHint(e, $target);
		});

		jQuery(document)
			.off('click', hintBox.preloaderCloseHandler)
			.on('click', {id: $preloader.prop('id')}, hintBox.preloaderCloseHandler);
	},

	/**
	 * Create preloader elements for the hint box.
	 */
	createPreloader: function() {
		return jQuery('<div>', {
			'id': 'hintbox-preloader',
			'class': 'is-loading hintbox-preloader'
		})
			.appendTo($('.wrapper'))
			.on('click', function(e) {
				e.stopPropagation();
			})
			.hide();
	},

	/**
	 * Event handler for the preloader elements destroy.
	 */
	preloaderCloseHandler: function(event) {
		overlayPreloaderDestroy(event.data.id);
	},

	createBox: function(e, target, hintText, className, isStatic, styles, appendTo) {
		var hintboxid = hintBox.getUniqueId(),
			box = jQuery('<div>', {'data-hintboxid': hintboxid}).addClass('overlay-dialogue'),
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
					'class': 'overlay-close-btn',
					'title': t('S_CLOSE')
				}
			)
				.click(function() {
					hintBox.hideHint(target, true);
				});
			box.prepend(close_link);
		}

		jQuery(appendTo).append(box);

		target.observer = new MutationObserver(() => {
			const node = target instanceof Node ? target : target[0];

			if (document.body.contains(node)) {
				return;
			}

			hintBox.deleteHint(target);
		})

		target.observer.observe(document, {
			childList: true,
			subtree: true
		})

		return box;
	},

	showStaticHint: function(e, target, className, resizeAfterLoad, styles, hintText) {
		var isStatic = target.isStatic;
		hintBox.hideHint(target, true);

		if (!isStatic) {
			if (typeof hintText === 'undefined') {
				hintText = jQuery(target).next('.hint-box').html();
			}

			target.isStatic = true;
			hintBox.showHint(e, target, hintText, className, true, styles);
			jQuery(target).data('return-control', jQuery(e.target));

			if (resizeAfterLoad) {
				hintText.one('load', function(e) {
					hintBox.positionElement(e, target, target.hintBoxItem);
				});
			}
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
	},

	positionElement: function(e, target, $elem) {
		if (e.clientX) {
			target.clientX = e.clientX;
			target.clientY = e.clientY;
		}

		var $host = $elem.offsetParent(),
			host_offset = $host.offset(),
			// Usable area relative to host.
			host_x_min = $host.scrollLeft(),
			host_x_max = Math.min($host[0].scrollWidth,
				$(window).width() + $(window).scrollLeft() - host_offset.left + $host.scrollLeft()
			) - 1,
			host_y_min = $host.scrollTop(),
			host_y_max = Math.min($host[0].scrollHeight,
				$(window).height() + $(window).scrollTop() - host_offset.top + $host.scrollTop()
			) - 1,
			// Event coordinates relative to host.
			event_x = target.clientX - host_offset.left + $host.scrollLeft(),
			event_y = target.clientY - host_offset.top + $host.scrollTop(),
			event_offset = 10,
			// Hint box width and height.
			hint_width = $elem.outerWidth(),
			hint_height = $elem.outerHeight(),
			/*
				Fix popup width and height since browsers will tend to reduce the size of the popup, if positioned further
				than the width of window when horizontal scrolling is active.
			*/
			css = {
				width: $elem.width(),
				height: $elem.height()
			};

		if (event_x + event_offset + hint_width <= host_x_max) {
			css.left = event_x + event_offset;
		}
		else {
			css.right = -$host.scrollLeft();
		}

		if (event_y + event_offset + hint_height <= host_y_max) {
			css.top = event_y + event_offset;
		}
		else if (event_y - event_offset - hint_height >= host_y_min) {
			css.top = event_y - event_offset - hint_height;
		}
		else {
			css.top = Math.max(host_y_min, Math.min(host_y_max - hint_height, event_y + event_offset));

			if (css.right !== undefined) {
				delete css.right;

				css.left = ((event_x - event_offset - hint_width >= host_x_min)
					? event_x - event_offset - hint_width
					: event_x + event_offset
				);
			}
		}

		$elem.css(css);
	},

	hideHint: function(target, hideStatic) {
		if (target.isStatic && !hideStatic) {
			return;
		}

		hintBox.deleteHint(target);
	},

	deleteHint: function(target) {
		if (typeof target.hintboxid !== 'undefined') {
			jQuery(target).removeAttr('data-expanded');
			removeFromOverlaysStack(target.hintboxid);
		}

		if (target.hintBoxItem) {
			target.hintBoxItem.trigger('onDeleteHint.hintBox');
			target.hintBoxItem.remove();
			delete target.hintBoxItem;

			if (target.isStatic) {
				if (jQuery(target).data('return-control') !== undefined) {
					jQuery(target).data('return-control').focus();
				}
				delete target.isStatic;
			}
		}

		if (target.observer !== undefined) {
			target.observer.disconnect();

			delete target.observer;
		}
	},

	getUniqueId: function() {
		var hintboxid = Math.random().toString(36).substring(7);
		while (jQuery('[data-hintboxid="' + hintboxid + '"]').length) {
			hintboxid = Math.random().toString(36).substring(7);
		}

		return hintboxid;
	}
};

/**
 * Add object to the list of favourites.
 */
function add2favorites(object, objectid) {
	sendAjaxData('zabbix.php?action=favourite.create', {
		data: {
			object: object,
			objectid: objectid
		}
	});
}

/**
 * Remove object from the list of favourites. Remove all favourites if objectid==0.
 */
function rm4favorites(object, objectid) {
	sendAjaxData('zabbix.php?action=favourite.delete', {
		data: {
			object: object,
			objectid: objectid
		}
	});
}

/**
 * Toggles filter state and updates title and icons accordingly.
 *
 * @param {string} 	idx					User profile index
 * @param {string} 	value				Value
 * @param {object} 	idx2				An array of IDs
 * @param {integer} profile_type		Profile type
 */
function updateUserProfile(idx, value, idx2, profile_type = PROFILE_TYPE_INT) {
	const value_fields = {
		[PROFILE_TYPE_INT]: 'value_int',
		[PROFILE_TYPE_STR]: 'value_str'
	};

	return sendAjaxData('zabbix.php?action=profile.update', {
		data: {
			idx: idx,
			[value_fields[profile_type]]: value,
			idx2: idx2
		}
	});
}

function changeWidgetState(obj, widgetId, idx) {
	var widgetObj = jQuery('#' + widgetId + '_widget'),
		css = switchElementClass(obj, 'btn-widget-collapse', 'btn-widget-expand'),
		state = 0;

	if (css === 'btn-widget-expand') {
		jQuery('.body', widgetObj).slideUp(50);
		jQuery('.dashboard-widget-foot', widgetObj).slideUp(50);
	}
	else {
		jQuery('.body', widgetObj).slideDown(50);
		jQuery('.dashboard-widget-foot', widgetObj).slideDown(50);

		state = 1;
	}

	obj.title = (state == 1) ? t('S_COLLAPSE') : t('S_EXPAND');
	if (idx !== '' && typeof idx !== 'undefined') {
		updateUserProfile(idx, state, []);
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
			beforeRow: null,
			rows: [],
			dataCallback: function(data) {
				return {};
			}
		}, options);

		return this.each(function() {
			var table = $(this);

			// If options.remove_next_sibling is true, counter counts each row making the next index twice as large (bug).
			table.data('dynamicRows', {
				counter: (options.counter !== null) ? options.counter : $(options.row, table).length
			});

			// add buttons
			table.on('click', options.add, function() {
				table.trigger('beforeadd.dynamicRows', options);

				// add the new row before the row with the "Add" button
				var beforeRow = (options['beforeRow'] !== null)
					? $(options['beforeRow'], table)
					:  $(this).closest('tr');
				addRow(table, beforeRow, options);

				table.trigger('afteradd.dynamicRows', options);
			});

			// remove buttons
			table.on('click', options.remove, function() {
				// remove the parent row
				removeRow(table, $(this).closest(options.row), options);
			});

			// disable buttons
			table.on('click', options.disable, function() {
				// disable the parent row
				disableRow($(this).closest(options.row));
			});

			if (typeof options.rows === 'object') {
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
	var verticalHeaderTables = {};

	$.fn.makeVerticalRotation = function() {
		this.each(function(i) {
			var table = $(this);

			if (table.data('rotated') == 1) {
				return;
			}
			table.data('rotated', 1);

			var cellsToRotate = $('.vertical_rotation', table),
				betterCells = [];

			// insert spans
			cellsToRotate.each(function() {
				var cell = $(this),
					text = $('<span>', {
						text: cell.html()
					}).css({'white-space': 'nowrap'});

				cell.text('').append(text);
			});

			// rotate cells
			cellsToRotate.each(function() {
				var cell = $(this),
					span = cell.children(),
					height = cell.height(),
					width = span.width(),
					transform = (width / 2) + 'px ' + (width / 2) + 'px';

				var css = {};

				css['transform-origin'] = transform;
				css['-webkit-transform-origin'] = transform;
				css['-moz-transform-origin'] = transform;
				css['-o-transform-origin'] = transform;

				var divInner = $('<div>', {
					'class': 'vertical_rotation_inner'
				})
					.css(css)
					.append(span.text());

				var div = $('<div>', {
					height: width,
					width: height
				})
					.append(divInner);

				betterCells.push(div);
			});

			cellsToRotate.each(function(i) {
				$(this).html(betterCells[i]);
			});

			table.on('remove', function() {
				delete verticalHeaderTables[table.attr('id')];
			});

			verticalHeaderTables[table.attr('id')] = table;
		});
	};

	if (ED && typeof sessionStorage.scrollTop !== 'undefined') {
		$('.wrapper').scrollTop(sessionStorage.scrollTop);
		sessionStorage.removeItem('scrollTop');
	}
});

window.addEventListener('load', e => {

	/**
	 * SideBar initialization.
	 */
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
