/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


function getIdFromNodeId(id) {
	if (typeof(id) == 'string') {
		var reg = /logtr([0-9])/i;
		id = parseInt(id.replace(reg, '$1'));
	}
	if (typeof(id) == 'number') {
		return id;
	}
	return null;
}

function check_target(e) {
	var targets = document.getElementsByName('expr_target_single');
	for (var i = 0; i < targets.length; ++i) {
		targets[i].checked = targets[i] == e;
	}
}

function delete_expression(expr_id) {
	document.getElementsByName('remove_expression')[0].value = expr_id;
}

function copy_expression(id) {
	var expr_temp = document.getElementsByName('expr_temp')[0];
	if (expr_temp.value.length > 0 && !confirm(t('Do you wish to replace the conditional expression?'))) {
		return null;
	}

	var src = document.getElementById(id);
	if (typeof src.textContent != 'undefined') {
		expr_temp.value = src.textContent;
	}
	else {
		expr_temp.value = src.innerText;
	}
}

/*
 * Graph related stuff
 */
var graphs = {
	graphtype : 0,

	submit : function(obj) {
		if (obj.name == 'graphtype') {
			if ((obj.selectedIndex > 1 && this.graphtype < 2) || (obj.selectedIndex < 2 && this.graphtype > 1)) {
				var refr = document.getElementsByName('form_refresh');
				refr[0].value = 0;
			}
		}
		document.getElementsByName('frm_graph')[0].submit();
	}
};

function cloneRow(elementid, count) {
	if (typeof(cloneRow.count) == 'undefined') {
		cloneRow.count = count;
	}
	cloneRow.count++;

	var tpl = new Template($(elementid).cloneNode(true).wrap('div').innerHTML);

	var emptyEntry = tpl.evaluate({'id' : cloneRow.count});

	var newEntry = $(elementid).insert({'before' : emptyEntry}).previousSibling;

	$(newEntry).descendants().each(function(e) {
		e.removeAttribute('disabled');
	});
	newEntry.setAttribute('id', 'entry_' + cloneRow.count);
	newEntry.style.display = '';
}

function testUserSound(idx) {
	var sound = $(idx).options[$(idx).selectedIndex].value;
	var repeat = $('messages_sounds.repeat').options[$('messages_sounds.repeat').selectedIndex].value;

	if (repeat == 1) {
		AudioControl.playOnce(sound);
	}
	else if (repeat > 1) {
		AudioControl.playLoop(sound, repeat);
	}
	else {
		AudioControl.playLoop(sound, $('messages_timeout').value);
	}
}

function removeObjectById(id) {
	var obj = document.getElementById(id);
	if (obj != null && typeof(obj) == 'object') {
		obj.parentNode.removeChild(obj);
	}
}

/**
 * Converts all HTML entities into the corresponding symbols.
 */
jQuery.unescapeHtml = function(html) {
	return jQuery('<div />').html(html).text();
}

/**
 * Converts all HTML symbols into HTML entities.
 */
jQuery.escapeHtml = function(html) {
	return jQuery('<div />').text(html).html();
}

function validateNumericBox(obj, allowempty, allownegative) {
	if (obj != null) {
		if (allowempty) {
			if (obj.value.length == 0 || obj.value == null) {
				obj.value = '';
			}
			else {
				if (isNaN(parseInt(obj.value, 10))) {
					obj.value = 0;
				}
				else {
					obj.value = parseInt(obj.value, 10);
				}
			}
		}
		else {
			if (isNaN(parseInt(obj.value, 10))) {
				obj.value = 0;
			}
			else {
				obj.value = parseInt(obj.value, 10);
			}
		}
	}
	if (!allownegative) {
		if (obj.value < 0) {
			obj.value = obj.value * -1;
		}
	}
}

/**
 * Validates and formats input element containing a part of date.
 *
 * @param object {obj}			input element value of which is being validated
 * @param int {min}				minimal allowed value (inclusive)
 * @param int {max}				maximum allowed value (inclusive)
 * @param int {paddingSize}		number of zeroes used for padding
 */
function validateDatePartBox(obj, min, max, paddingSize) {
	if (obj != null) {
		min = min ? min : 0;
		max = max ? max : 59;
		paddingSize = paddingSize ? paddingSize : 2;

		var paddingZeroes = [];
		for (var i = 0; i != paddingSize; i++) {
			paddingZeroes.push('0');
		}
		paddingZeroes = paddingZeroes.join('');

		var currentValue = obj.value.toString();

		if (/^[0-9]+$/.match(currentValue)) {
			var intValue = parseInt(currentValue, 10);

			if (intValue < min || intValue > max) {
				obj.value = paddingZeroes;
			}
			else if (currentValue.length < paddingSize) {
				var paddedValue = paddingZeroes + obj.value;
				obj.value = paddedValue.substring(paddedValue.length - paddingSize);
			}
		}
		else {
			obj.value = paddingZeroes;
		}
	}
}

/**
 * Translates the given string.
 *
 * @param {String} str
 */
function t(str) {
	return (!!locale[str]) ? locale[str] : str;
}

/**
 * Generates unique id with prefix 'new'.
 * id starts from 0 in each JS session.
 *
 * @return string
 */
function getUniqueId() {
	if (typeof getUniqueId.id === 'undefined') {
		getUniqueId.id = 0;
	}

	return 'new' + (getUniqueId.id++).toString();
}

/**
 * Color palette, (implementation from PHP)
 */
var prevColor = {'color': 0, 'gradient': 0};

function incrementNextColor() {
	prevColor['color']++;
	if (prevColor['color'] == 7) {
		prevColor['color'] = 0;

		prevColor['gradient']++;
		if (prevColor['gradient'] == 3) {
			prevColor['gradient'] = 0;
		}
	}
}

function getNextColor(paletteType) {
	var palette, gradient, hexColor, r, g, b;

	switch (paletteType) {
		case 1:
			palette = [200, 150, 255, 100, 50, 0];
			break;
		case 2:
			palette = [100, 50, 200, 150, 250, 0];
			break;
		case 0:
		default:
			palette = [255, 200, 150, 100, 50, 0];
			break;
	}

	gradient = palette[prevColor['gradient']];
	r = (100 < gradient) ? 0 : 255;
	g = r;
	b = r;

	switch (prevColor['color']) {
		case 0:
			g = gradient;
			break;
		case 1:
			r = gradient;
			break;
		case 2:
			b = gradient;
			break;
		case 3:
			b = gradient;
			r = b;
			break;
		case 4:
			b = gradient;
			g = b;
			break;
		case 5:
			g = gradient;
			r = g;
			break;
		case 6:
			b = gradient;
			g = b;
			r = b;
			break;
	}

	incrementNextColor();

	hexColor = ('0' + parseInt(r, 10).toString(16)).slice(-2)
				+ ('0' + parseInt(g, 10).toString(16)).slice(-2)
				+ ('0' + parseInt(b, 10).toString(16)).slice(-2);

	return hexColor.toUpperCase();
}

/**
 * Used for php ctweenbox object.
 * Moves item from 'from' select to 'to' select and adds or removes hidden fields to 'formname' for posting data.
 * Moving perserves alphabetical order.
 *
 * @formname string	form name where hidden fields will be added
 * @objname string	unique name for hidden field naming
 * @from string		from select id
 * @to string		to select id
 * @action string	action to perform with hidden field
 *
 * @return true
 */
function moveListBoxSelectedItem(objname, from, to, action) {
	to = jQuery('#' + to);

	jQuery('#' + from).find('option:selected').each(function(i, fromel) {
		var notApp = true;
		to.find('option').each(function(j, toel) {
			if (toel.innerHTML.toLowerCase() > fromel.innerHTML.toLowerCase()) {
				jQuery(toel).before(fromel);
				notApp = false;
				return false;
			}
		});
		if (notApp) {
			to.append(fromel);
		}
		fromel = jQuery(fromel);
		if (action.toLowerCase() == 'add') {
			jQuery(this)
				.closest('form')
				.append("<input name='" + objname + '[' + fromel.val() + ']' + "' id='" + objname + '_' + fromel.val()
					+ "' value='" + fromel.val() + "' type='hidden'>"
				);
		}
		else if (action.toLowerCase() == 'rmv') {
			jQuery('#' + objname + '_' + fromel.val()).remove();
		}
	});

	return true;
}

/**
 * Returns the number of properties of an object.
 *
 * @param obj
 *
 * @return int
 */
function objectSize(obj) {
	var size = 0, key;

	for (key in obj) {
		if (obj.hasOwnProperty(key)) {
			size++;
		}
	}

	return size;
}

/**
 * Replace placeholders like %<number>$s with arguments.
 * Can be used like usual sprintf but only for %<number>$s placeholders.
 *
 * @param string
 *
 * @return string
 */
function sprintf(string) {
	var placeHolders,
		position,
		replace;

	if (typeof string !== 'string') {
		throw Error('Invalid input type. String required, got ' + typeof string);
	}

	placeHolders = string.match(/%\d\$s/g);
	for (var l = placeHolders.length - 1; l >= 0; l--) {
		position = placeHolders[l][1];
		replace = arguments[position];

		if (typeof replace === 'undefined') {
			throw Error('Placeholder for non-existing parameter');
		}

		string = string.replace(placeHolders[l], replace)
	}

	return string;
}

/**
 * Optimization:
 *
 * 86400 = 24 * 60 * 60
 * 31536000 = 365 * 86400
 * 2592000 = 30 * 86400
 * 604800 = 7 * 86400
 *
 * @param int  timestamp
 * @param bool isTsDouble
 * @param bool isExtend
 *
 * @return string
 */
function formatTimestamp(timestamp, isTsDouble, isExtend) {
	timestamp = timestamp || 0;

	var years = 0,
		months = 0;

	if (isExtend) {
		years = Math.floor(timestamp / 31536000);
		months = Math.floor((timestamp - years * 31536000) / 2592000);
	}

	var days = Math.floor((timestamp - years * 31536000 - months * 2592000) / 86400),
		hours = Math.floor((timestamp - years * 31536000 - months * 2592000 - days * 86400) / 3600),
		minutes = Math.floor((timestamp - years * 31536000 - months * 2592000 - days * 86400 - hours * 3600) / 60);

	// due to imprecise calculations it is possible that the remainder contains 12 whole months but no whole years
	if (months == 12) {
		years++;
		months = 0;
	}

	if (isTsDouble) {
		if (months.toString().length == 1) {
			months = '0' + months;
		}
		if (days.toString().length == 1) {
			days = '0' + days;
		}
		if (hours.toString().length == 1) {
			hours = '0' + hours;
		}
		if (minutes.toString().length == 1) {
			minutes = '0' + minutes;
		}
	}

	var str = (years == 0) ? '' : years + locale['S_YEAR_SHORT'] + ' ';
	str += (months == 0) ? '' : months + locale['S_MONTH_SHORT'] + ' ';
	str += (isExtend && isTsDouble)
		? days + locale['S_DAY_SHORT'] + ' '
		: ((days == 0) ? '' : days + locale['S_DAY_SHORT'] + ' ');
	str += (hours == 0) ? '' : hours + locale['S_HOUR_SHORT'] + ' ';
	str += (minutes == 0) ? '' : minutes + locale['S_MINUTE_SHORT'] + ' ';

	return str;
}

/**
 * Splitting string using slashes with escape backslash support.
 *
 * @param string $path
 *
 * @return array
 */
function splitPath(path) {
	var items = [],
		s = '',
		escapes = '';

	for (var i = 0, size = path.length; i < size; i++) {
		if (path[i] === '/') {
			if (escapes === '') {
				items[items.length] = s;
				s = '';
			}
			else {
				if (escapes.length % 2 == 0) {
					s += stripslashes(escapes);
					items[items.length] = s;
					s = escapes = '';
				}
				else {
					s += stripslashes(escapes) + path[i];
					escapes = '';
				}
			}
		}
		else if (path[i] === '\\') {
			escapes += path[i];
		}
		else {
			s += stripslashes(escapes) + path[i];
			escapes = '';
		}
	}

	if (escapes !== '') {
		s += stripslashes(escapes);
	}

	items[items.length] = s;

	return items;
}

/**
 * Removing unescaped backslashes from string.
 * Analog of PHP stripslashes().
 *
 * @param string str
 *
 * @return string
 */
function stripslashes(str) {
	return str.replace(/\\(.?)/g, function(s, chars) {
		if (chars == '\\') {
			return '\\';
		}
		else if (chars == '') {
			return '';
		}
		else {
			return chars;
		}
	});
}

function overlayDialogueDestroy() {
	jQuery('#overlay_bg, #overlay_dialogue').remove();
	jQuery('body').css({'overflow': ''});
	jQuery('body[style=""]').removeAttr('style');
}

/**
 * Display modal window
 *
 * @param string title					modal window title
 * @param object content				window content
 * @param array  buttons				window buttons
 * @param string buttons[]['title']
 * @param string buttons[]['class']
 * @param bool	 buttons[]['focused']
 * @param bool   buttons[]['enabled']
 * @param object buttons[]['click']
 */
function overlayDialogue(params) {
	var overlay_bg = jQuery('<div>', {
		id: 'overlay_bg',
		class: 'overlay-bg',
		css: {
			'display': 'none'
		}
	});

	var overlay_dialogue_footer = jQuery('<div>', {
		class: 'overlay-dialogue-footer'
	});

	var button_focused = null;

	jQuery.each(params.buttons, function(index, obj) {
		var button = jQuery('<button>', {
			type: 'button',
			text: obj.title
		}).click(function() {
			obj.action();
			overlayDialogueDestroy();
			return false;
		});

		if ('class' in obj) {
			button.addClass(obj.class);
		}

		if ('enabled' in obj && obj.enabled === false) {
			button.attr('disabled', 'disabled');
		}

		if ('focused' in obj && obj.focused === true) {
			button_focused = button;
		}

		overlay_dialogue_footer.append(button);
	});

	overlay_dialogue = jQuery('<div>', {
		id: 'overlay_dialogue',
		class: 'overlay-dialogue',
		css: {
			'position': 'fixed',
			'top': '40%',
			'left': '50%',
			'display': 'none'
		}
	})
		.append(
			jQuery('<span>', {
				class: 'overlay-close-btn'
			})
				.click(function() {
					overlayDialogueDestroy();
					return false;
				})
		)
		.append(
			jQuery('<div>', {
				class: 'dashbrd-widget-head'
			}).append(jQuery('<h4>').text(params.title))
		)
		.append(
			jQuery('<div>', {
				class: 'overlay-dialogue-body'
			}).append(params.content)
		)
		.append(overlay_dialogue_footer)
		.on('keypress keydown', function(e) {
			if (e.which == 27) { // ESC
				overlayDialogueDestroy();
				return false;
			}
		});

	overlay_bg
		.appendTo('body')
		.show();
	overlay_dialogue
		.appendTo('body')
		.css({
			'margin-top': '-' + (overlay_dialogue.outerHeight() / 2) + 'px',
			'margin-left': '-' + (overlay_dialogue.outerWidth() / 2) + 'px'
		})
		.show();

	var focusable = jQuery(':focusable', overlay_dialogue);

	if (focusable.length > 0) {
		var first_focusable = focusable.filter(':first'),
			last_focusable = focusable.filter(':last');

		first_focusable.on('keydown', function(e) {
			if (e.keyCode == 9 && e.shiftKey) {
				last_focusable.focus();
				return false;
			}
		});

		last_focusable.on('keydown', function(e) {
			if (e.keyCode == 9 && !e.shiftKey) {
				first_focusable.focus();
				return false;
			}
		});
	}

	jQuery('body').css({'overflow': 'hidden'});

	if (button_focused !== null) {
		button_focused.focus();
	}
}

/**
 * Execute script.
 *
 * @param string hostid			host id
 * @param string scriptid		script id
 * @param string confirmation	confirmation text
 */
function executeScript(hostid, scriptid, confirmation) {
	var execute = function() {
		if (hostid !== null) {
			openWinCentered('scripts_exec.php?hostid=' + hostid + '&scriptid=' + scriptid, 'Tools', 950, 470,
				'titlebar=no, resizable=yes, scrollbars=yes, dialog=no'
			);
		}
	};

	if (confirmation.length > 0) {
		overlayDialogue({
			'title': t('Execution confirmation'),
			'content': jQuery('<span>').text(confirmation),
			'buttons': [
				{
					'title': t('Cancel'),
					'class': 'btn-alt',
					'focused': (hostid === null),
					'action': function() {}
				},
				{
					'title': t('Execute'),
					'enabled': (hostid !== null),
					'focused': (hostid !== null),
					'action': function() {
						execute();
					}
				}
			]
		});
	}
	else {
		execute();
	}
}
