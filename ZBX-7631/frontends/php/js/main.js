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
 * Automatic checkbox range selection
 */
var chkbxRange = {
	startbox:		null,	// start checkbox obj
	startboxName:	null,	// start checkbox name
	chkboxes:		{},		// ckbx list
	prefix:			null,	// prefix for cookie name
	pageGoName:		null,	// which checkboxes should be counted by Go button
	pageGoCount:	0,		// selected checkboxes
	selectedIds:	{},		// ids of selected checkboxes
	goButton:		null,
	cookieName:		null,

	init: function() {
		var path = new Curl();
		var filename = basename(path.getPath(), '.php');
		this.cookieName = 'cb_' + filename + (this.prefix ? '_' + this.prefix : '');
		this.selectedIds = cookie.readJSON(this.cookieName);

		var chkboxes = jQuery('.tableinfo .checkbox:not(:disabled)');
		if (chkboxes.length > 0) {
			for (var i = 0; i < chkboxes.length; i++) {
				this.implement(chkboxes[i]);
			}
		}

		this.selectMainCheckbox();

		this.goButton = $('goButton');
		if (!is_null(this.goButton)) {
			addListener(this.goButton, 'click', this.submitGo.bindAsEventListener(this), false);
		}

		this.setGo();
	},

	implement: function(obj) {
		var objName = obj.name.split('[')[0];

		if (typeof(this.chkboxes[objName]) === 'undefined') {
			this.chkboxes[objName] = [];
		}
		this.chkboxes[objName].push(obj);

		addListener(obj, 'click', this.check.bindAsEventListener(this), false);

		if (objName == this.pageGoName) {
			var objId = jQuery(obj).val();
			if (isset(objId, this.selectedIds)) {
				obj.checked = true;
			}
		}
	},

	// check if all checkboxes are selected and select main checkbox, else disable checkbox, select options and button
	selectMainCheckbox: function() {
		var mainCheckbox = jQuery('.tableinfo .header .checkbox:not(:disabled)');
		if (!mainCheckbox.length) {
			return;
		}

		var countAvailable = jQuery('.tableinfo tr:not(.header) .checkbox:not(:disabled)').length;

		if (countAvailable > 0) {
			var countChecked = jQuery('.tableinfo tr:not(.header) .checkbox:not(:disabled):checked').length;

			mainCheckbox = mainCheckbox[0];
			mainCheckbox.checked = (countChecked == countAvailable);

			if (mainCheckbox.checked) {
				jQuery('.tableinfo .header').addClass('selectedMain');
			}
			else {
				jQuery('.tableinfo .header').removeClass('selectedMain');
			}
		}
		else {
			mainCheckbox.disabled = true;
		}
	},

	check: function(e) {
		e = e || window.event;
		var obj = Event.element(e);

		PageRefresh.restart();

		if (typeof(obj) === 'undefined' || obj.type.toLowerCase() != 'checkbox' || obj.disabled === true) {
			return true;
		}

		this.setGo();

		if (obj.name.indexOf('all_') > -1 || obj.name.indexOf('_single') > -1) {
			return true;
		}
		var objName = obj.name.split('[')[0];

		// check range selection
		if (e.ctrlKey || e.shiftKey) {
			if (!is_null(this.startbox) && this.startboxName == objName && obj.name != this.startbox.name) {
				var chkboxes = this.chkboxes[objName];
				var flag = false;

				for (var i = 0; i < chkboxes.length; i++) {
					if (typeof(chkboxes[i]) !== 'undefined') {
						if (flag) {
							chkboxes[i].checked = this.startbox.checked;
						}
						if (obj.name == chkboxes[i].name) {
							break;
						}
						if (this.startbox.name == chkboxes[i].name) {
							flag = true;
						}
					}
				}

				if (flag) {
					this.setGo();
					this.selectMainCheckbox();
					return true;
				}
				else {
					for (var i = chkboxes.length - 1; i >= 0; i--) {
						if (typeof(chkboxes[i]) !== 'undefined') {
							if (flag) {
								chkboxes[i].checked = this.startbox.checked;
							}

							if (obj.name == chkboxes[i].name) {
								this.setGo();
								this.selectMainCheckbox();
								return true;
							}

							if (this.startbox.name == chkboxes[i].name) {
								flag = true;
							}
						}
					}
				}
			}

			this.setGo();
		}
		else {
			this.selectMainCheckbox();
		}

		this.startbox = obj;
		this.startboxName = objName;
	},

	checkAll: function(name, value) {
		if (typeof(this.chkboxes[name]) === 'undefined') {
			return false;
		}

		var chkboxes = this.chkboxes[name];
		for (var i = 0; i < chkboxes.length; i++) {
			if (typeof(chkboxes[i]) !== 'undefined' && chkboxes[i].disabled !== true) {
				var objName = chkboxes[i].name.split('[')[0];
				if (objName == name) {
					chkboxes[i].checked = value;
				}
			}
		}

		var mainCheckbox = jQuery('.tableinfo .header .checkbox:not(:disabled)')[0];
		if (mainCheckbox.checked) {
			jQuery('.tableinfo .header').addClass('selectedMain');
		}
		else {
			jQuery('.tableinfo .header').removeClass('selectedMain');
		}
	},

	clearSelectedOnFilterChange: function() {
		cookie.eraseArray(this.cookieName);
	},

	setGo: function() {
		if (!is_null(this.pageGoName)) {
			if (typeof(this.chkboxes[this.pageGoName]) !== 'undefined') {
				var chkboxes = this.chkboxes[this.pageGoName];
				for (var i = 0; i < chkboxes.length; i++) {
					if (typeof(chkboxes[i]) !== 'undefined') {
						var box = chkboxes[i];
						var objName = box.name.split('[')[0];
						var objId = box.name.split('[')[1];
						objId = objId.substring(0, objId.lastIndexOf(']'));
						var crow = getParent(box, 'tr');

						if (box.checked) {
							if (!is_null(crow)) {
								var origClass = crow.getAttribute('origClass');
								if (is_null(origClass)) {
									crow.setAttribute('origClass', crow.className);
								}
								crow.className = 'selected';
							}
							if (objName == this.pageGoName) {
								this.selectedIds[objId] = objId;
							}
						}
						else {
							if (!is_null(crow)) {
								var origClass = crow.getAttribute('origClass');

								if (!is_null(origClass)) {
									crow.className = origClass;
									crow.removeAttribute('origClass');
								}
							}
							if (objName == this.pageGoName) {
								delete(this.selectedIds[objId]);
							}
						}
					}
				}

			}

			var countChecked = 0;
			for (var key in this.selectedIds) {
				if (!empty(this.selectedIds[key])) {
					countChecked++;
				}
			}

			if (!is_null(this.goButton)) {
				var tmp_val = this.goButton.value.split(' ');
				this.goButton.value = tmp_val[0] + ' (' + countChecked + ')';
			}

			cookie.createJSON(this.cookieName, this.selectedIds);

			if (jQuery('#go').length) {
				jQuery('#go')[0].disabled = (countChecked == 0);
			}
			if (jQuery('#goButton').length) {
				jQuery('#goButton')[0].disabled = (countChecked == 0);
			}

			this.pageGoCount = countChecked;
		}
	},

	submitGo: function(e) {
		e = e || window.event;

		var goSelect = $('go');
		var confirmText = goSelect.options[goSelect.selectedIndex].getAttribute('confirm');

		if (!is_null(confirmText) && !confirm(confirmText)) {
			Event.stop(e);
			return false;
		}

		var form = getParent(this.goButton, 'form');
		for (var key in this.selectedIds) {
			if (!empty(this.selectedIds[key])) {
				create_var(form.name, this.pageGoName + '[' + key + ']', key, false);
			}
		}
		return true;
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
				obj.setAttribute('loop', 0);
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
				css: {
					display: 'none'
				}
			}));
		}
		else {
			var obj = jQuery('#audio');

			if (obj.length == 0 || obj.data('name') !== name) {
				obj.remove();

				jQuery('body').append(jQuery('<audio>', {
					id: 'audio',
					'data-name': name,
					src: 'audio/' + name,
					preload: 'auto',
					autoplay: true,
					loop: loop ? 9999999 : 1
				}));
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

	createBox: function(e, target, hintText, width, className, isStatic) {
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

		if (!empty(width)) {
			box.css('width', width + 'px');
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

	HintWraper: function(e, target, hintText, width, className) {
		target.isStatic = false;

		jQuery(target).on('mouseenter', function(e, d) {
			if (d) {
				e = d;
			}
			hintBox.showHint(e, target, hintText, width, className, false);

		}).on('mouseleave', function(e) {
			hintBox.hideHint(e, target);

		}).on('remove', function(e) {
			hintBox.deleteHint(target);
		});

		jQuery(target).removeAttr('onmouseover');
		jQuery(target).trigger('mouseenter', e);
	},

	showStaticHint: function(e, target, hint, width, className, resizeAfterLoad) {
		var isStatic = target.isStatic;
		hintBox.hideHint(e, target, true);

		if (!isStatic) {
			target.isStatic = true;
			hintBox.showHint(e, target, hint, width, className, true);

			if (resizeAfterLoad) {
				hint.one('load', function(e) {
					hintBox.positionHint(e, target);
				});
			}
		}
	},

	showHint: function(e, target, hintText, width, className, isStatic) {
		if (target.hintBoxItem) {
			return;
		}

		target.hintBoxItem = hintBox.createBox(e, target, hintText, width, className, isStatic);
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

		// doesn't fit in the screen horizontaly
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

		// fallback if doesnt't fit verticaly but could fit if aligned to right or left
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

function changeFlickerState(id) {
	var state = showHide(id);

	switchElementClass('flicker_icon_l', 'dbl_arrow_up', 'dbl_arrow_down');
	switchElementClass('flicker_icon_r', 'dbl_arrow_up', 'dbl_arrow_down');

	sendAjaxData({
		data: {
			filterState: state
		}
	});

	// resize multiselect
	if (typeof flickerResizeMultiselect === 'undefined' && state == 1) {
		flickerResizeMultiselect = true;

		if (jQuery('.multiselect').length > 0) {
			jQuery('#' + id).multiSelect.resize();
		}
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

function createPlaceholders() {
	if (IE) {
		jQuery(document).ready(function() {
			jQuery('[placeholder]')
				.focus(function() {
					var obj = jQuery(this);

					if (obj.val() == obj.attr('placeholder')) {
						obj.val('');
						obj.removeClass('placeholder');
					}
				})
				.blur(function() {
					var obj = jQuery(this);

					if (obj.val() == '' || obj.val() == obj.attr('placeholder')) {
						obj.val(obj.attr('placeholder'));
						obj.addClass('placeholder');
					}
				})
				.blur();

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
