/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

function check_target(e, type) {
	// If type is expression.
	if (type == 0) {
		var targets = document.getElementsByName('expr_target_single');
	}
	// Type is recovery expression.
	else {
		var targets = document.getElementsByName('recovery_expr_target_single');
	}

	for (var i = 0; i < targets.length; ++i) {
		targets[i].checked = targets[i] == e;
	}
}

/**
 * Remove part of expression.
 *
 * @param string id		Expression temporary ID.
 * @param number type	Expression (type = 0) or recovery expression (type = 1).
 */
function delete_expression(id, type) {
	// If type is expression.
	if (type == 0) {
		jQuery('#remove_expression').val(id);
	}
	// Type is recovery expression.
	else {
		jQuery('#remove_recovery_expression').val(id);
	}
}

/**
 * Insert expression part into input field.
 *
 * @param string id		Expression temporary ID.
 * @param number type	Expression (type = 0) or recovery expression (type = 1).
 */
function copy_expression(id, type) {
	// If type is expression.
	if (type == 0) {
		var element = document.getElementsByName('expr_temp')[0];
	}
	// Type is recovery expression.
	else {
		var element = document.getElementsByName('recovery_expr_temp')[0];
	}

	if (element.value.length > 0 && !confirm(t('Do you wish to replace the conditional expression?'))) {
		return null;
	}

	var src = document.getElementById(id);
	if (typeof src.textContent != 'undefined') {
		element.value = src.textContent;
	}
	else {
		element.value = src.innerText;
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
 * Color palette object used for geting different colors from color palette.
 */
var colorPalette = (function() {
	'use strict';

	var current_color = 0,
		palette = [];

	return {
		incrementNextColor: function() {
			if (++current_color == palette.length) {
				current_color = 0;
			}
		},

		/**
		 * Gets next color from palette.
		 *
		 * @return string	hexadecimal color code
		 */
		getNextColor: function() {
			var color = palette[current_color];

			this.incrementNextColor();

			return color;
		},

		/**
		 * Set theme specific color palette.
		 *
		 * @param array colors  Array of hexadecimal color codes.
		 */
		setThemeColors: function(colors) {
			palette = colors;
			current_color = 0;
		}
	}
}());

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

function overlayDialogueDestroy(dialogueid) {
	dialogueid = dialogueid || null;

	jQuery('[data-dialogueid='+dialogueid+']').remove();
	jQuery('body').css({'overflow': ''});
	jQuery('body[style=""]').removeAttr('style');
}

/**
 * Get unused overlay dialog id.
 *
 * @return {string}
 */
function getOverlayDialogueId() {
	var dialogueid = Math.random().toString(36).substring(7);
	while (jQuery('[data-dialogueid="' + dialogueid + '"]').length) {
		dialogueid = Math.random().toString(36).substring(7);
	}

	return dialogueid;
}

/**
 * Display modal window.
 *
 * @param {object} params                                   Modal window params.
 * @param {string} params.title                             Modal window title.
 * @param {object} params.content                           Window content.
 * @param {object} params.controls                          Window controls.
 * @param {array}  params.buttons                           Window buttons.
 * @param {string} params.buttons[]['title']                Text on the button.
 * @param {object}|{string} params.buttons[]['action']      Function object or executable string that will be executed
 *                                                          on click.
 * @param {string} params.buttons[]['class']	(optional)  Button class.
 * @param {bool}   params.buttons[]['cancel']	(optional)  It means what this button has cancel action.
 * @param {bool}   params.buttons[]['focused']	(optional)  Focus this button.
 * @param {bool}   params.buttons[]['enabled']	(optional)  Should the button be enabled? Default: true.
 * @param {bool}   params.buttons[]['keepOpen']	(optional)  Prevent dialogue closing, if button action returned false.
 * @param string   params.dialogueid            (optional)  Unique dialogue identifier to reuse existing overlay dialog
 *                                                          or create a new one if value is not set.
 * @param string   params.script_inline         (optional)  Custom javascript code to execute when initializing dialog.
 *
 * @return {bool}
 */
function overlayDialogue(params) {
	var button_focused = null,
		cancel_action = null,
		overlay_dialogue = null,
		headerid = '',
		overlay_dialogue_footer = jQuery('<div>', {
			class: 'overlay-dialogue-footer'
		});

	if (typeof params.dialogueid === 'undefined') {
		params.dialogueid = getOverlayDialogueId();
	}

	if (typeof params.script_inline !== 'undefined') {
		jQuery(overlay_dialogue_footer).append(jQuery('<script>').text(params.script_inline));
	}

	headerid = 'dashbrd-widget-head-title-'+params.dialogueid;

	if (jQuery('.overlay-dialogue[data-dialogueid="' + params.dialogueid + '"]').length) {
		overlay_dialogue = jQuery('.overlay-dialogue[data-dialogueid="' + params.dialogueid + '"]');

		jQuery(overlay_dialogue)
			.attr('class', 'overlay-dialogue modal')
			.unbind('keydown')
			.empty();
	}
	else {
		overlay_dialogue = jQuery('<div>', {
			'id': 'overlay_dialogue',
			'class': 'overlay-dialogue modal',
			'data-dialogueid': params.dialogueid,
			'role': 'dialog',
			'aria-modal': 'true',
			'aria-labeledby': headerid
		});

		jQuery('<div>', {
			'id': 'overlay_bg',
			'class': 'overlay-bg',
			'data-dialogueid': params.dialogueid
		})
			.appendTo('body');

		jQuery(overlay_dialogue).appendTo('body');
	}

	var center_overlay_dialog = function() {
			overlay_dialogue.css({
				'left': Math.round((jQuery(window).width() - jQuery(overlay_dialogue).outerWidth()) / 2) + 'px',
				'top': Math.round((jQuery(window).height() - jQuery(overlay_dialogue).outerHeight()) / 2) + 'px'
			});
		},
		body_mutation_observer = window.MutationObserver || window.WebKitMutationObserver,
		body_mutation_observer = new body_mutation_observer(function(mutation) {
			center_overlay_dialog();
		});

	jQuery.each(params.buttons, function(index, obj) {
		var button = jQuery('<button>', {
			type: 'button',
			text: obj.title
		}).click(function() {
			if (typeof obj.action === 'string') {
				obj.action = new Function(obj.action);
			}
			var res = obj.action();

			if (res !== false && (!('keepOpen' in obj) || obj.keepOpen === false)) {
				body_mutation_observer.disconnect();
				overlayDialogueDestroy(params.dialogueid);
			}

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

		if ('cancel' in obj && obj.cancel === true) {
			cancel_action = obj.action;
		}

		overlay_dialogue_footer.append(button);
	});

	jQuery(overlay_dialogue)
		.append(
			jQuery('<button>', {
				class: 'overlay-close-btn'
			})
				.click(function() {
					body_mutation_observer.disconnect();

					if (cancel_action !== null) {
						cancel_action();
					}

					overlayDialogueDestroy(params.dialogueid);

					return false;
				})
		)
		.append(
			jQuery('<div>', {
				class: 'dashbrd-widget-head'
			}).append(jQuery('<h4 id="'+headerid+'">').text(params.title))
		)
		.append(params.controls ? jQuery('<div>').addClass('overlay-dialogue-controls').html(params.controls) : null)
		.append(
			jQuery('<div>', {
				class: 'overlay-dialogue-body',
			})
				.append(params.content)
				.each(function() {
					body_mutation_observer.observe(this, {childList: true, subtree: true});
				})
				.find('form')
					.attr('aria-labeledby', headerid)
				.end()
		)
		.append(overlay_dialogue_footer)
		.on('keydown', function(e) {
			// ESC
			if (e.which == 27) {
				body_mutation_observer.disconnect();
				if (cancel_action !== null) {
					cancel_action();
				}
				overlayDialogueDestroy(params.dialogueid);

				return false;
			}
		});

	if (typeof params.class !== 'undefined') {
		overlay_dialogue.addClass(params.class);
	}

	center_overlay_dialog();

	jQuery(window).resize(function() {
		if (jQuery('#overlay_dialogue').length) {
			center_overlay_dialog();
		}
	});

	var focusable = jQuery(':focusable', overlay_dialogue);

	if (focusable.length > 0) {
		var first_focusable = focusable.filter(':first'),
			last_focusable = focusable.filter(':last');

		first_focusable.on('keydown', function(e) {
			// TAB and SHIFT
			if (e.which == 9 && e.shiftKey) {
				last_focusable.focus();

				return false;
			}
		});

		last_focusable.on('keydown', function(e) {
			// TAB and not SHIFT
			if (e.which == 9 && !e.shiftKey) {
				first_focusable.focus();

				return false;
			}
		});
	}

	jQuery('body').css({'overflow': 'hidden'});

	if (button_focused !== null) {
		button_focused.focus();
	}

	// Don't focus element in overlay, if the button is already focused.
	overlayDialogueOnLoad(!button_focused);
}

/**
 * Actions to perform, when dialogue is created, as well as, when data in dialogue changed,
 * and this is forced from outside.
 *
 * @param {bool} focus  Focus first focusable element in overlay.
 */
function overlayDialogueOnLoad(focus) {
	if (focus) {
		jQuery('[autofocus=autofocus]:focusable', jQuery('#overlay_dialogue')).first().focus();
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
			PopUp('popup.scriptexec', {
				hostid: hostid,
				scriptid: scriptid
			});
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

(function($) {
	$.fn.serializeJSON = function() {
		var json = {};

		jQuery.map($(this).serializeArray(), function(n) {
			var	l = n['name'].indexOf('['),
				r = n['name'].indexOf(']'),
				curr_json = json;

			if (l != -1 && r != -1 && r > l) {
				var	key = n['name'].substr(0, l);

				if (l + 1 == r) {
					if (typeof curr_json[key] === 'undefined') {
						curr_json[key] = [];
					}

					curr_json[key].push(n['value']);
				}
				else {
					if (typeof curr_json[key] === 'undefined') {
						curr_json[key] = {};
					}
					curr_json = curr_json[key];

					do {
						key = n['name'].substr(l + 1, r - l - 1);
						l = n['name'].indexOf('[', r + 1);
						r = n['name'].indexOf(']', r + 1);

						if (l == -1 || r == -1 || r <= l) {
							curr_json[key] = n['value']
							break;
						}

						if (typeof curr_json[key] === 'undefined') {
							curr_json[key] = {};
						}
						curr_json = curr_json[key];
					} while (l != -1 && r != -1 && r > l);
				}
			}
			else {
				json[n['name']] = n['value'];
			}
		});

		return json;
	};
})(jQuery);

function makeErrorMessageBox(errors, elementId) {
	var div = jQuery('<div>').addClass('msg-bad').attr('id', elementId);
	var details = jQuery('<div>').addClass('msg-details'),
		ul = jQuery('<ul>');

	errors.each(function (error) {
		// split long messages
		var msg = '';
		error.match(/[\s\S]{1,120}/g).each(function (error_part) {
			msg = msg + jQuery.escapeHtml(error_part) + "\n";
		});
		ul.append(jQuery('<li>').append(msg));
	});
	details.append(ul);
	div.append(details);

	return div;
}
