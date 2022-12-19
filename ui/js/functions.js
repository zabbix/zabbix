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

function testUserSound(idx) {
	var element = document.getElementById(idx);
	var sound = element.options[element.selectedIndex].value;
	element = document.getElementById('messages_sounds.repeat');
	var repeat = element.options[element.selectedIndex].value;

	if (repeat == 1) {
		AudioControl.playOnce(sound);
	}
	else if (repeat > 1) {
		AudioControl.playLoop(sound, repeat);
	}
	else {
		AudioControl.playLoop(sound, document.getElementById('messages_timeout').value);
	}
}

/**
 * Converts all HTML symbols into HTML entities.
 */
jQuery.escapeHtml = function(html) {
	return jQuery('<div>').text(html).html();
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
 * Color palette object used for getting different colors from color palette.
 */
let colorPalette = (function() {
	'use strict';

	let palette = [];

	return {
		/**
		 * Gets next color from palette.
		 *
		 * @param {array} used_colors  Array of already used hexadecimal color codes.
		 *
		 * @return string  Hexadecimal color code.
		 */
		getNextColor: function(used_colors) {
			if (!used_colors.length) {
				return palette[0] || '';
			}

			const palette_usage = {};

			for (const color of palette) {
				palette_usage[color] = used_colors.filter(used_color => used_color === color).length;
			}

			const min_used_color_count = Math.min(...Object.values(palette_usage));

			return Object.keys(palette_usage).find(color => palette_usage[color] == min_used_color_count);
		},

		/**
		 * Set color palette.
		 *
		 * @param {array} colors  Array of hexadecimal color codes.
		 */
		setThemeColors: function(colors) {
			palette = colors;
		}
	}
}());

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
 * Add standard message to the top of the site.
 *
 * @param {jQuery}  jQuery object representing HTML message box with class name .msg-good, .msg-bad or .msg-warning.
 */
function addMessage($msg_box) {
	var $wrapper = $('.wrapper'),
		$main = $wrapper.find('> main'),
		$footer = $wrapper.find('> footer');

	if ($main.length) {
		$main.before($msg_box);
	}
	else if ($footer.length) {
		$footer.before($msg_box);
	}
	else {
		$wrapper.append($msg_box);
	}
}

/**
 * Clear standard messages.
 */
function clearMessages() {
	$('.wrapper').find('> .msg-good, > .msg-bad, > .msg-warning').not('.msg-global-footer').remove();
}

/**
 * Prepare Ok message for displaying after page reload.
 *
 * @param {String} message
 */
function postMessageOk(message) {
	cookie.create('system-message-ok', message);
}

/**
 * Prepare Error message for displaying after page reload.
 *
 * @param {String} message
 */
function postMessageError(message) {
	cookie.create('system-message-error', message);
}

function postMessageDetails(type, messages) {
	const encode = function (string) {
		const uint8 = new TextEncoder().encode(string);

		let result = '';
		for (let i = 0; i < uint8.byteLength; i++) {
			result += String.fromCharCode(uint8[i]);
		}

		return result;
	};

	const data = JSON.stringify({
		type: type,
		messages: messages
	});
	cookie.create('system-message-details', btoa(encode(data)));
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

	placeHolders = string.match(/%\d\$[sd]/g);
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

	var str = (years == 0) ? '' : years + t('S_YEAR_SHORT') + ' ';
	str += (months == 0) ? '' : months + t('S_MONTH_SHORT') + ' ';
	str += (isExtend && isTsDouble)
		? days + t('S_DAY_SHORT') + ' '
		: ((days == 0) ? '' : days + t('S_DAY_SHORT') + ' ');
	str += (hours == 0) ? '' : hours + t('S_HOUR_SHORT') + ' ';
	str += (minutes == 0) ? '' : minutes + t('S_MINUTE_SHORT') + ' ';

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

/**
 * Function to remove preloader and moves focus to IU element that was clicked to open it.
 *
 * @param string   id			Preloader identifier.
 */
function overlayPreloaderDestroy(id) {
	if (typeof id !== 'undefined') {

		var overlay = overlays_stack.getById(id)
		if (!overlay) {
			return;
		}
		if (typeof overlay.xhr !== 'undefined') {
			overlay.xhr.abort();
			delete overlay.xhr;
		}

		jQuery('#' + id).remove();
		removeFromOverlaysStack(id);
	}
}

/**
 * Function to close overlay dialogue and moves focus to IU element that was clicked to open it.
 *
 * @param string   dialogueid	Dialogue identifier to identify dialogue.
 */
function overlayDialogueDestroy(dialogueid) {
	if (typeof dialogueid !== 'undefined') {
		var overlay = overlays_stack.getById(dialogueid)
		if (!overlay) {
			return;
		}

		if (typeof overlay.xhr !== 'undefined') {
			overlay.xhr.abort();
			delete overlay.xhr;
		}

		if (overlay instanceof Overlay) {
			overlay.unmount();
		}

		jQuery('[data-dialogueid='+dialogueid+']').remove();

		removeFromOverlaysStack(dialogueid);

		overlay.$dialogue[0].dispatchEvent(new CustomEvent('overlay.close', {detail: {dialogueid}}));
	}
}

/**
 * Display modal window.
 *
 * @param {object} params                                   Modal window params.
 * @param {string} params.title                             Modal window title.
 * @param {string} params.class                             Modal window CSS class, often based on .modal-popup*.
 * @param {object} params.content                           Window content.
 * @param {object} params.footer                           	Window footer content.
 * @param {object} params.controls                          Window controls.
 * @param {array}  params.buttons                           Window buttons.
 * @param {string} params.debug                             Debug HTML displayed in modal window.
 * @param {string} params.buttons[]['title']                Text on the button.
 * @param {object}|{string} params.buttons[]['action']      Function object or executable string that will be executed
 *                                                          on click.
 * @param {string} params.buttons[]['class']    (optional)  Button class.
 * @param {bool}   params.buttons[]['cancel']   (optional)  It means what this button has cancel action.
 * @param {bool}   params.buttons[]['focused']  (optional)  Focus this button.
 * @param {bool}   params.buttons[]['enabled']  (optional)  Should the button be enabled? Default: true.
 * @param {bool}   params.buttons[]['keepOpen'] (optional)  Prevent dialogue closing, if button action returned false.
 * @param string   params.dialogueid            (optional)  Unique dialogue identifier to reuse existing overlay dialog
 *                                                          or create a new one if value is not set.
 * @param string   params.script_inline         (optional)  Custom javascript code to execute when initializing dialog.
 * @param {Node|null} trigger_elmnt                         UI element which triggered opening of overlay dialogue.
 *
 * @return {Overlay}
 */
function overlayDialogue(params, trigger_elmnt) {
	params.element = params.element || trigger_elmnt;
	params.type = params.type || 'popup';

	var overlay = overlays_stack.getById(params.dialogueid);

	if (!overlay) {
		overlay = new Overlay(params.type, params.dialogueid);
	}

	overlay.setProperties(params);
	overlay.mount();
	overlay.recoverFocus();
	overlay.containFocus();

	addToOverlaysStack(overlay);

	return overlay;
}

/**
 * Execute script.
 *
 * @param string scriptid			Script ID.
 * @param string confirmation		Confirmation text.
 * @param {Node} trigger_element	UI element that was clicked to open overlay dialogue.
 * @param string hostid				Host ID.
 * @param string eventid			Event ID.
 */
function executeScript(scriptid, confirmation, trigger_element, hostid = null, eventid = null) {
	var execute = function() {
		var popup_options = {scriptid: scriptid};

		if (hostid !== null) {
			popup_options.hostid = hostid;
		}

		if (eventid !== null) {
			popup_options.eventid = eventid;
		}

		if (Object.keys(popup_options).length === 2) {
			PopUp('popup.scriptexec', popup_options, {dialogue_class: 'modal-popup-medium', trigger_element});
		}
	};

	if (confirmation.length > 0) {
		overlayDialogue({
			'title': t('Execution confirmation'),
			'content': jQuery('<span>')
				.addClass('confirmation-msg')
				.text(confirmation),
			'class': 'modal-popup modal-popup-small position-middle',
			'buttons': [
				{
					'title': t('Cancel'),
					'class': 'btn-alt',
					'focused': (hostid === null && eventid === null),
					'action': function() {}
				},
				{
					'title': t('Execute'),
					'enabled': (hostid !== null || eventid !== null),
					'focused': (hostid !== null || eventid !== null),
					'action': function() {
						execute();
					}
				}
			]
		}, trigger_element);

		return false;
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

						if (l + 1 == r) {
							if (typeof curr_json[key] === 'undefined') {
								curr_json[key] = [];
							}

							curr_json[key].push(n['value']);
							break;
						}
						else if (l == -1 || r == -1 || r <= l) {
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

/**
 * Parse URL string to object. Hash starting part of URL will be removed.
 * Return object where 'url' key contains parsed URL, 'pairs' key is array of objects with parsed arguments.
 * For malformed URL strings will return false.
 *
 * @param {string} url_string  URL string to parse.
 *
 * @return {object|bool}
 */
function parseUrlString(url_string) {
	try {
		decodeURI(url_string);
	}
	catch {
		return false;
	}

	let url = url_string.replace(/#.+/, '');
	const pos = url.indexOf('?');
	const pairs = [];

	if (pos != -1) {
		const query = url.substring(pos + 1);
		url = url.substring(0, pos);

		for (const param of new URLSearchParams(query)) {
			if (encodeURIComponent(param[0]).match(/%[01]/) || encodeURIComponent(param[1]).match(/%[01]/)) {
				// Non-printable characters in URL.
				return false;
			}

			pairs.push({
				'name': param[0],
				'value': param[1]
			});
		}
	}

	return {
		'url': url,
		'pairs': pairs
	};
}

/**
 * Message formatting function.
 *
 * @param {string}       type            Message type. ('good'|'bad'|'warning')
 * @param {array}        messages        Error messages.
 * @param {string|null}  title           Error title.
 * @param {boolean}      show_close_box  Show close button.
 * @param {boolean|null} show_details    Show details on opening.
 *
 * @return {jQuery}
 */
function makeMessageBox(type, messages, title = null, show_close_box = true, show_details = null) {
	var classes = {good: 'msg-good', bad: 'msg-bad', warning: 'msg-warning'},
		msg_class = classes[type];

	if (show_details === null) {
		show_details = type === 'bad' || type === 'warning';
	}

	var	$list = jQuery('<ul>')
			.addClass('list-dashed'),
		$msg_details = jQuery('<div>')
			.addClass('msg-details')
			.append($list),
		aria_labels = {good: t('Success message'), bad: t('Error message'), warning: t('Warning message')},
		$msg_box = jQuery('<output>')
			.addClass(msg_class).attr('role', 'contentinfo')
			.attr('aria-label', aria_labels[type]),
		$details_arrow = jQuery('<span>')
			.attr('id', 'details-arrow')
			.addClass(show_details ? 'arrow-up' : 'arrow-down'),
		$link_details = jQuery('<a>')
			.text(t('Details') + ' ')
			.addClass('link-action')
			.attr('href', 'javascript:void(0)')
			.attr('role', 'button')
			.append($details_arrow)
			.attr('aria-expanded', show_details ? 'true' : 'false');

		$link_details.click(function() {
			showHide(jQuery(this)
				.siblings('.msg-details')
				.find('.msg-details-border')
			);
			jQuery('#details-arrow', jQuery(this)).toggleClass('arrow-up arrow-down');
			jQuery(this).attr('aria-expanded', jQuery(this)
				.find('.arrow-down')
				.length == 0
			);
		});

	if (title !== null) {
		if (Array.isArray(messages) && messages.length > 0) {
			$msg_box.prepend($link_details);
		}
		jQuery('<span>')
			.text(title)
			.appendTo($msg_box);

		$list.addClass('msg-details-border');

		if (!show_details) {
			$list.hide();
		}
	}

	if (Array.isArray(messages) && messages.length > 0) {
		jQuery.map(messages, function (message) {
			jQuery('<li>')
				.text(message)
				.appendTo($list);
			return null;
		});

		$msg_box.append($msg_details);
	}

	if (show_close_box) {
		var $button = jQuery('<button>')
				.addClass('overlay-close-btn')
				.attr('type', 'button')
				.attr('title', t('Close'))
				.click(function() {
					jQuery(this)
						.closest('.' + msg_class)
						.remove();
				});
		$msg_box.append($button);
	}

	return $msg_box;
}

/**
 * Download svg graph as .png image.
 *
 * @param {SVGElement} svg
 * @param {string}     file_name
 */
function downloadSvgImage(svg, file_name) {
	var $dom_node = jQuery(svg),
		canvas = document.createElement('canvas'),
		labels = $dom_node.next('.svg-graph-legend'),
		$clone = $dom_node.clone(),
		$container = $dom_node.closest('.dashboard-grid-widget-content'),
		image = new Image,
		a = document.createElement('a'),
		style = document.createElementNS('http://www.w3.org/1999/xhtml', 'style'),
		$labels_clone,
		labels_height = labels.length ? labels.height() : 0,
		context2d;

	// Clone only svg graph styles.
	style.innerText = jQuery.map(document.styleSheets[0].cssRules, function (rule) {
		return rule.selectorText && rule.selectorText.substr(0, 5) == '.svg-' ? rule.cssText : '';
	}).join('');

	jQuery.map(['background-color', 'font-family', 'font-size', 'color'], function (key) {
		$clone.css(key, $container.css(key));
	});

	canvas.width = $dom_node.width()
	canvas.height = $dom_node.height() + labels_height;
	context2d = canvas.getContext('2d');
	image.onload = function() {
		context2d.drawImage(image, 0, 0);
		a.href = canvas.toDataURL('image/png');
		a.rel = 'noopener' + (ZBX_NOREFERER ? ' noreferrer' : '');
		a.download = file_name;
		a.target = '_blank';
		a.click();
	}
	$labels_clone = jQuery(document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject'))
		.attr({
			x: 0,
			y: canvas.height - labels_height,
			width: canvas.width,
			height: labels_height
		})
		.append(jQuery(document.createElementNS('http://www.w3.org/1999/xhtml', 'div'))
			.append(style)
			.append(labels.clone())
		);

	$clone.attr('height', canvas.height + 'px').append($labels_clone);
	image.src = 'data:image/svg+xml;base64,' + btoa(new XMLSerializer().serializeToString($clone[0]));
}

/**
 * Download classic image as given file name.
 *
 * @param {HTMLImageElement} img
 * @param {string}           file_name
 */
function downloadPngImage(img, file_name) {
	var a = document.createElement('a');

	a.href = img.src;
	a.rel = 'noopener' + (ZBX_NOREFERER ? ' noreferrer' : '');
	a.download = file_name;
	a.target = '_blank';
	a.click();
}

/**
 * Writes text into primary clipboard. Provides fallback for insecure context.
 *
 * @param {string} text  Text to write.
 */
function writeTextClipboard(text) {
	if (window.isSecureContext) {
		return window.navigator.clipboard.writeText(text);
	}

	const textarea = document.createElement('textarea');

	textarea.value = text;
	textarea.style.position = 'fixed';
	document.body.appendChild(textarea);
	textarea.select();
	document.execCommand('copy');
	textarea.remove();
}

function urlEncodeData(parameters, prefix = '') {
	const result = [];

	for (let [name, value] of Object.entries(parameters)) {
		if (value === undefined) {
			continue;
		}

		if (value === null) {
			value = '';
		}

		const prefixed_name = prefix !== '' ? `${prefix}[${name}]` : name;

		if (Array.isArray(value) || (typeof value === 'object')) {
			const result_part = urlEncodeData(value, prefixed_name);

			if (result_part !== '') {
				result.push(result_part);
			}
		}
		else {
			result.push([encodeURIComponent(prefixed_name), encodeURIComponent(value)].join('='));
		}
	}

	return result.join('&');
}

/**
 * Get form field values as deep object.
 *
 * Example:
 *     <form>
 *         <input name="a" value="1">
 *         <input name="b[c]" value="2">
 *         <input name="b[d]" value="3">
 *         <input name="e[f][]" value="4">
 *         <input name="e[f][]" value="5">
 *     </form>
 *
 *    ... will result in:
 *
 *    {
 *        a: "1",
 *        b: {
 *            c: "2",
 *            d: "3"
 *        },
 *        e: {
 *            f: ["4", "5"]
 *        }
 *    }
 *
 * @param {HTMLFormElement} form
 *
 * @return {object}
 */
function getFormFields(form) {
	const fields = {};

	for (let [key, value] of new FormData(form)) {
		value = value.replace(/\r?\n/g, '\r\n');

		const key_parts = [...key.matchAll(/[^\[\]]+|\[\]/g)];

		let key_fields = fields;

		for (let i = 0; i < key_parts.length; i++) {
			const key_part = key_parts[i][0];

			if (i == key_parts.length - 1) {
				if (key_part === '[]') {
					key_fields.push(value);
				}
				else {
					key_fields[key_part] = value;
				}

				break;
			}

			if (key_part === '[]') {
				const key_field = key_parts[i + 1][0] === '[]' ? [] : {};

				key_fields.push(key_field);
				key_fields = key_field;
			}
			else {
				if (!(key_part in key_fields)) {
					key_fields[key_part] = key_parts[i + 1][0] === '[]' ? [] : {};
				}

				key_fields = key_fields[key_part];
			}
		}
	}

	return fields;
}
