/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

		this.delayLeft -= 1000;
		if (this.delayLeft < 0) {
			location.replace(location.href);
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
 * Main menu
 */
var MMenu = {
	menus:			{'empty': 0, 'view': 0, 'cm': 0, 'reports': 0, 'config': 0, 'admin': 0},
	def_label:		null,
	sub_active: 	false,
	timeout_reset:	null,
	timeout_change:	null,

	mouseOver: function(show_label) {
		clearTimeout(this.timeout_reset);
		this.timeout_change = setTimeout('MMenu.showSubMenu("' + show_label + '")', 200);
		PageRefresh.restart();
	},

	submenu_mouseOver: function() {
		clearTimeout(this.timeout_reset);
		clearTimeout(this.timeout_change);
		PageRefresh.restart();
	},

	mouseOut: function() {
		clearTimeout(this.timeout_change);
		this.timeout_reset = setTimeout('MMenu.showSubMenu("' + this.def_label + '")', 2500);
	},

	showSubMenu: function(show_label) {
		var menu_div = $('sub_' + show_label);
		if (!is_null(menu_div)) {
			$(show_label).className = 'active';
			menu_div.show();
			for (var key in this.menus) {
				if (key == show_label) {
					continue;
				}

				var menu_cell = $(key);
				if (!is_null(menu_cell)) {
					if (menu_cell.tagName.toLowerCase() != 'select') {
						menu_cell.className = '';
					}
				}
				var sub_menu_cell = $('sub_' + key);
				if (!is_null(sub_menu_cell)) {
					sub_menu_cell.hide();
				}
			}
		}
	}
};

/*
 * Audio control system
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

		if (IE) {
			this.create(name, false);
		}
		else {
			var obj = jQuery('#audio');

			if (obj.length > 0 && obj.data('name') === name) {
				obj.trigger('play');
			}
			else {
				this.create(name, false);
			}
		}
	},

	playLoop: function(name, delay) {
		this.stop();

		if (IE) {
			this.create(name, true);
		}
		else {
			var obj = jQuery('#audio');

			if (obj.length > 0 && obj.data('name') === name) {
				obj.trigger('play');
			}
			else {
				this.create(name, true);
			}
		}

		AudioControl.loop(new Date().getTime() + delay * 1000);
	},

	stop: function() {
		var obj = document.getElementById('audio');

		if (obj !== null) {
			clearTimeout(AudioControl.timeoutHandler);

			if (IE) {
				obj.setAttribute('loop', false);
				obj.setAttribute('playcount', 0);

				try {
					obj.stop();
				}
				catch (e) {
					setTimeout(
						function() {
							try {
								document.getElementById('audio').stop();
							}
							catch (e) {
							}
						},
						100
					);
				}
			}
			else {
				jQuery(obj).trigger('pause');
			}
		}
	},

	create: function(name, loop) {
		if (IE) {
			jQuery('#audio').remove();

			jQuery('body').append(jQuery('<embed>', {
				id: 'audio',
				'data-name': name,
				src: 'audio/' + name,
				enablejavascript: true,
				autostart: true,
				loop: true,
				playcount: loop ? 9999999 : 1,
				height: 0
			}));
		}
		else {
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
 * @author Konstantin Buravcov
 */
var jqBlink = {
	shown: false, // are objects currently shown or hidden?
	blinkInterval: 1000, // how fast will they blink (ms)
	secondsSinceInit: 0,

	/**
	 * Shows/hides the elements and repeats it self after 'this.blinkInterval' ms
	 */
	blink: function() {
		var objects = jQuery('.blink');

		// maybe some of the objects should not blink any more?
		objects = this.filterOutNonBlinking(objects);

		// changing visibility state
		fun = this.shown ? 'removeClass' : 'addClass';
		jQuery.each(objects, function() {
			if (typeof jQuery(this).data('toggleClass') !== 'undefined') {
				jQuery(this)[fun](jQuery(this).data('toggleClass'));
			}
			else {
				jQuery(this).css('visibility', jqBlink.shown ? 'hidden' : 'visible');
			}
		})

		// reversing the value of indicator attribute
		this.shown = !this.shown;

		// I close my eyes only for a moment, and a moment's gone
		this.secondsSinceInit += this.blinkInterval / 1000;

		// repeating this function with delay
		setTimeout(jQuery.proxy(this.blink, this), this.blinkInterval);
	},

	/**
	 * Check all currently found objects and exclude ones that should stop blinking by now
	 */
	filterOutNonBlinking: function(objects) {
		var that = this;

		return objects.filter(function() {
			var obj = jQuery(this);
			if (typeof obj.data('timeToBlink') !== 'undefined') {
				var shouldBlink = parseInt(obj.data('timeToBlink'), 10) > that.secondsSinceInit;

				// if object stops blinking, it should be left visible
				if (!shouldBlink && !that.shown) {
					obj.css('visibility', 'visible');
				}
				return shouldBlink;
			}
			else {
				// no time-to-blink attribute, should blink forever
				return true;
			}
		});
	}
};

/*
 * HintBox class.
 */
var hintBox = {

	createBox: function(e, target, hintText, className, isStatic) {
		var box = jQuery('<div></div>').addClass('hintbox');

		if (typeof hintText === 'string') {
			hintText = hintText.replace(/\n/g, '<br />');
		}

		if (!empty(className)) {
			box.append(jQuery('<span></span>').addClass(className).html(hintText));
		}
		else {
			box.html(hintText);
		}

		if (isStatic) {
			var close_link = jQuery('<div>' + locale['S_CLOSE'] + '</div>')
				.addClass('link')
				.css({
					'text-align': 'right',
					'border-bottom': '1px #333 solid'
				}).click(function() {
					hintBox.hideHint(e, target, true);
				});
			box.prepend(close_link);
		}

		jQuery('body').append(box);

		return box;
	},

	HintWraper: function(e, target, hintText, className) {
		target.isStatic = false;

		jQuery(target).on('mouseenter', function(e, d) {
			if (d) {
				e = d;
			}
			hintBox.showHint(e, target, hintText, className, false);

		}).on('mouseleave', function(e) {
			hintBox.hideHint(e, target);

		}).on('remove', function(e) {
			hintBox.deleteHint(target);
		});

		jQuery(target).removeAttr('onmouseover');
		jQuery(target).trigger('mouseenter', e);
	},

	showStaticHint: function(e, target, hint, className, resizeAfterLoad) {
		var isStatic = target.isStatic;
		hintBox.hideHint(e, target, true);

		if (!isStatic) {
			target.isStatic = true;
			hintBox.showHint(e, target, hint, className, true);

			if (resizeAfterLoad) {
				hint.one('load', function(e) {
					hintBox.positionHint(e, target);
				});
			}
		}
	},

	showHint: function(e, target, hintText, className, isStatic) {
		if (target.hintBoxItem) {
			return;
		}

		target.hintBoxItem = hintBox.createBox(e, target, hintText, className, isStatic);
		hintBox.positionHint(e, target);
		target.hintBoxItem.show();
	},

	positionHint: function(e, target) {
		var wWidth = jQuery(window).width(),
			wHeight = jQuery(window).height(),
			scrollTop = jQuery(window).scrollTop(),
			scrollLeft = jQuery(window).scrollLeft(),
			top, left;

		// uses stored clientX on afterload positioning when there is no event
		if (e.clientX) {
			target.clientX = e.clientX;
			target.clientY = e.clientY;
		}

		// doesn't fit in the screen horizontally
		if (target.hintBoxItem.width() + 10 > wWidth) {
			left = scrollLeft + 2;
		}
		// 10px to right if fit
		else if (wWidth - target.clientX - 10 > target.hintBoxItem.width()) {
			left = scrollLeft + target.clientX + 10;
		}
		// 10px from screen right side
		else {
			left = scrollLeft + wWidth - 10 - target.hintBoxItem.width();
		}

		// 10px below if fit
		if (wHeight - target.clientY - target.hintBoxItem.height() - 10 > 0) {
			top = scrollTop + target.clientY + 10;
		}
		// 10px above if fit
		else if (target.clientY - target.hintBoxItem.height() - 10 > 0) {
			top = scrollTop + target.clientY - target.hintBoxItem.height() - 10;
		}
		// 10px below as fallback
		else {
			top = scrollTop + target.clientY + 10;
		}

		// fallback if doesn't fit verticaly but could fit if aligned to right or left
		if ((top - scrollTop + target.hintBoxItem.height() > wHeight)
				&& (target.clientX - 10 > target.hintBoxItem.width() || wWidth - target.clientX - 10 > target.hintBoxItem.width())) {

			// align to left if fit
			if (wWidth - target.clientX - 10 > target.hintBoxItem.width()) {
				left = scrollLeft + target.clientX + 10;
			}
			// align to right
			else {
				left = scrollLeft + target.clientX - target.hintBoxItem.width() - 10;
			}

			// 10px from bottom if fit
			if (wHeight - 10 > target.hintBoxItem.height()) {
				top = scrollTop + wHeight - target.hintBoxItem.height() - 10;
			}
			// 10px from top
			else {
				top = scrollTop + 10;
			}
		}

		target.hintBoxItem.css({
			top: top + 'px',
			left: left + 'px',
			zIndex: 100
		});
	},

	hideHint: function(e, target, hideStatic) {
		if (target.isStatic && !hideStatic) {
			return;
		}

		hintBox.deleteHint(target);
	},

	deleteHint: function(target) {
		if (target.hintBoxItem) {
			target.hintBoxItem.remove();
			delete target.hintBoxItem;

			if (target.isStatic) {
				delete target.isStatic;
			}
		}
	}
};

/*
 * Color picker
 */
function hide_color_picker() {
	if (!color_picker) {
		return;
	}

	color_picker.style.zIndex = 1000;
	color_picker.style.visibility = 'hidden';
	color_picker.style.left = '-' + ((color_picker.style.width) ? color_picker.style.width : 100) + 'px';
	curr_lbl = null;
	curr_txt = null;
}

function show_color_picker(id) {
	if (!color_picker) {
		return;
	}

	curr_lbl = document.getElementById('lbl_' + id);
	curr_txt = document.getElementById(id);
	var pos = getPosition(curr_lbl);
	color_picker.x = pos.left;
	color_picker.y = pos.top;
	color_picker.style.left = (color_picker.x + 20) + 'px';
	color_picker.style.top = color_picker.y + 'px';
	color_picker.style.visibility = 'visible';
}

function create_color_picker() {
	if (color_picker) {
		return;
	}

	color_picker = document.createElement('div');
	color_picker.setAttribute('id', 'color_picker');
	color_picker.innerHTML = color_table;
	document.body.appendChild(color_picker);
	hide_color_picker();
}

function set_color(color) {
	if (curr_lbl) {
		curr_lbl.style.background = curr_lbl.style.color = '#' + color;
		curr_lbl.title = '#' + color;
	}
	if (curr_txt) {
		curr_txt.value = color.toString().toUpperCase();
	}
	hide_color_picker();
}

function set_color_by_name(id, color) {
	curr_lbl = document.getElementById('lbl_' + id);
	curr_txt = document.getElementById(id);
	set_color(color);
}

function add2favorites(favobj, favid) {
	sendAjaxData({
		data: {
			favobj: favobj,
			favid: favid,
			favaction: 'add'
		}
	});
}

function rm4favorites(favobj, favid) {
	sendAjaxData({
		data: {
			favobj: favobj,
			favid: favid,
			favaction: 'remove'
		}
	});
}


/**
 * Toggles filter state and updates title and icons accordingly.
 *
 * @param {int} 	id					Id of filter in DOM
 * @param {string} 	titleWhenVisible	Title to set when filter is visible
 * @param {string} 	titleWhenHidden		Title to set when filter is collapsed
 */
function changeFlickerState(id, titleWhenVisible, titleWhenHidden) {
	var state = showHide(id);

	switchElementClass('flicker_icon_l', 'dbl_arrow_up', 'dbl_arrow_down');
	switchElementClass('flicker_icon_r', 'dbl_arrow_up', 'dbl_arrow_down');

	var title = state ? titleWhenVisible : titleWhenHidden;

	jQuery('#flicker_title').html(title);

	sendAjaxData({
		data: {
			filterState: state
		}
	});

	// resize multiselects in the flicker
	if (jQuery('.multiselect').length > 0 && state == 1) {
		jQuery('.multiselect', jQuery('#' + id)).multiSelect('resize');
	}
}

function changeWidgetState(obj, widgetId) {
	var widgetObj = jQuery('#' + widgetId + '_widget'),
		css = switchElementClass(obj, 'arrowup', 'arrowdown'),
		state = 0;

	if (css === 'arrowdown') {
		jQuery('.body', widgetObj).slideUp(50);
		jQuery('.footer', widgetObj).slideUp(50);
	}
	else {
		jQuery('.body', widgetObj).slideDown(50);
		jQuery('.footer', widgetObj).slideDown(50);

		state = 1;
	}

	sendAjaxData({
		data: {
			widgetName: widgetId,
			widgetState: state
		}
	});
}

/**
 * Send ajax data.
 *
 * @param object options
 */
function sendAjaxData(options) {
	var url = new Curl(location.href);
	url.setQuery('?output=ajax');

	var defaults = {
		type: 'post',
		url: url.getUrl()
	};

	jQuery.ajax(jQuery.extend({}, defaults, options));
}

/**
 * Finds all elements with a 'placeholder' attribute and emulates the placeholder in IE.
 */
function createPlaceholders() {
	if (IE) {
		jQuery('[placeholder]').each(function() {
			var placeholder = jQuery(this);

			if (!placeholder.data('has-placeholder-handlers')) {
				placeholder
					.data('has-placeholder-handlers', true)
					.focus(function() {
						var obj = jQuery(this);

						if (!obj.attr('placeholder')) {
							return;
						}

						if (obj.val() == obj.attr('placeholder')) {
							obj.val('');
							obj.removeClass('placeholder');
						}
					})
					.blur(function() {
						var obj = jQuery(this);

						if (!obj.attr('placeholder')) {
							return;
						}

						if (obj.val() == '' ||  obj.val() == obj.attr('placeholder')) {
							obj.val(obj.attr('placeholder'));
							obj.addClass('placeholder');
						}
					})
					.blur();
			}

			jQuery('form').submit(function() {
				jQuery('.placeholder').each(function() {
					var obj = jQuery(this);

					if (obj.val() == obj.attr('placeholder')) {
						obj.val('');
					}
				});
			});
		});
	}
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

		// search for other conditions of the same type
		for (var n = i + 1; n < conditions.length; n++) {
			if (typeof conditions[n] !== 'undefined' && conditions[i].type == conditions[n].type) {
				groupedConditions.push(conditions[n].id);
				delete conditions[n];
			}
		}

		// join conditions of the same type
		if (groupedConditions.length > 1) {
			groupedFormulas.push('(' + groupedConditions.join(' ' + conditionOperator + ' ') + ')');
		}
		else {
			groupedFormulas.push(groupedConditions[0]);
		}
	}

	var formula = groupedFormulas.join(' ' + groupOperator + ' ');

	// strip parentheses if there's only one condition group
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
	 * - template		- row template selector
	 * - row			- element row selector
	 * - add			- add row button selector
	 * - remove			- remove row button selector
	 * - counter 		- number to start row enumeration from
	 * - dataCallback	- function to generate the data passed to the template
	 *
	 * Triggered events:
	 * - rowremove.dynamicRows 	- after removing a row (triggered before tableupdate.dynamicRows)
	 * - tableupdate.dynamicRows 	- after adding or removing a row
	 *
	 * @param options
	 */
	$.fn.dynamicRows = function(options) {
		options = $.extend({}, {
			template: '',
			row: '.form_row',
			add: '.element-table-add',
			remove: '.element-table-remove',
			counter: null,
			dataCallback: function(data) {
				return {};
			}
		}, options);

		return this.each(function() {
			var table = $(this);

			table.data('dynamicRows', {
				counter: (options.counter !== null) ? options.counter : $(options.row, table).length
			});

			// add buttons
			table.on('click', options.add, function() {
				// add the new row before the row with the "Add" button
				addRow(table, $(this).closest('tr'), options);
			});

			// remove buttons
			table.on('click', options.remove, function() {
				// remove the parent row
				removeRow(table, $(this).closest(options.row), options);
			});
		});
	};

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

		createPlaceholders();

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
		row.remove();

		table.trigger('rowremove.dynamicRows', options);
		table.trigger('tableupdate.dynamicRows', options);
	}
}(jQuery));

jQuery(function ($) {
	var verticalHeaderTables = {};

	var tablesWidthChangeChecker = function() {
		for (var tableId in verticalHeaderTables) {
			if (verticalHeaderTables.hasOwnProperty(tableId)) {
				var table = verticalHeaderTables[tableId];

				if (table && table.width() != table.data('last-width')) {
					centerVerticalCellContents(table);
				}
			}
		}
		setTimeout(tablesWidthChangeChecker, 100);
	};

	var centerVerticalCellContents = function(table) {
		var verticalCells = $('.vertical_rotation', table);

		verticalCells.each(function() {
			var cell = $(this),
				cellWidth = cell.width();

			if (cellWidth > 30) {
				cell.children().css({
					position: 'relative',
					left: (cellWidth / 2 - 12) + 'px'
				});
			}
		});

		table.data('last-width', table.width());
	};

	tablesWidthChangeChecker();

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
				var cell = $(this);

				var text = $('<span>', {
					text: cell.html()
				});

				if (IE) {
					text.css({'font-family': 'monospace'});
				}

				cell.text('').append(text);
			});

			// rotate cells
			cellsToRotate.each(function() {
				var cell = $(this),
					span = cell.children(),
					height = cell.height(),
					width = span.width(),
					transform = (width / 2) + 'px ' + (width / 2) + 'px';

				var css = {
					"transform-origin": transform,
					"-webkit-transform-origin": transform,
					"-moz-transform-origin": transform,
					"-o-transform-origin": transform
				};

				if (IE) {
					css['font-family'] = 'monospace';
					css['-ms-transform-origin'] = '50% 50%';
				}

				if (IE9) {
					css['-ms-transform-origin'] = transform;
				}

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

			centerVerticalCellContents(table);

			table.on('remove', function() {
				delete verticalHeaderTables[table.attr('id')];
			});

			verticalHeaderTables[table.attr('id')] = table;
		});
	};
});
