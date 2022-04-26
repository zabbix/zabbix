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


const KEY_ARROW_DOWN = 40;
const KEY_ARROW_LEFT = 37;
const KEY_ARROW_RIGHT = 39;
const KEY_ARROW_UP = 38;
const KEY_BACKSPACE = 8;
const KEY_DELETE = 46;
const KEY_ENTER = 13;
const KEY_ESCAPE = 27;
const KEY_TAB = 9;
const KEY_PAGE_UP = 33;
const KEY_PAGE_DOWN = 34;
const KEY_END = 35;
const KEY_HOME = 36;
const KEY_SPACE = 32;

/**
 * jQuery based publish/subscribe handler.
 *
 * - $.subscribe(event_name, callback)
 * - $.unsubscribe(event_name, callback)
 * - $.publish(event_name, data_object)
 *
 */
(function($) {
	var pubsub = $({});

	$.subscribe = function() {
		pubsub.on.apply(pubsub, arguments);
	};

	$.unsubscribe = function() {
		pubsub.off.apply(pubsub, arguments);
	};

	$.publish = function() {
		pubsub.trigger.apply(pubsub, arguments);
	};
}(jQuery));

var overlays_stack = new OverlayCollection();

function isset(key, obj) {
	return (is_null(key) || is_null(obj)) ? false : (typeof(obj[key]) != 'undefined');
}

/**
 * @deprecated  use strict comparison instead
 *
 * @param obj
 * @returns {*}
 */
function empty(obj) {
	if (is_null(obj)) {
		return true;
	}
	if (obj === false) {
		return true;
	}
	if (is_string(obj) && obj === '') {
		return true;
	}
	if (typeof(obj) == 'undefined') {
		return true;
	}

	return is_array(obj) && obj.length == 0;
}

/**
 * @deprecated use === null instead
 *
 * @param obj
 * @returns {boolean}
 */
function is_null(obj) {
	return (obj == null);
}

function is_number(obj) {
	return isNaN(obj) ? false : (typeof(obj) === 'number');
}

function is_object(obj, instance) {
	if (typeof(instance) === 'object' || typeof(instance) === 'function') {
		if (typeof(obj) === 'object' && obj instanceof instance) {
			return true;
		}
	}
	else {
		if (typeof(obj) === 'object') {
			return true;
		}
	}

	return false;
}

function is_string(obj) {
	return (typeof(obj) === 'string');
}

function is_array(obj) {
	return (obj != null) && (typeof obj == 'object') && ('splice' in obj) && ('join' in obj);
}

/**
 * Get elements existing exclusively in one of both arrays.
 * @deprecated
 *
 * @param {Array} arr
 *
 * @returns {Array}
 */
Array.prototype.xor = function(arr) {
	var merged_arr = this.concat(arr);

	return merged_arr.filter(function(e) {
		return (merged_arr.indexOf(e) === merged_arr.lastIndexOf(e));
	});
};

function addListener(element, eventname, expression, bubbling) {
	bubbling = bubbling || false;
	element = $(element)[0];

	if (element.addEventListener) {
		element.addEventListener(eventname, expression, bubbling);
		return true;
	}
	else if (element.attachEvent) {
		element.attachEvent('on' + eventname, expression);
		return true;
	}
	else {
		return false;
	}
}

function removeListener(element, eventname, expression, bubbling) {
	bubbling = bubbling || false;
	element = $(element);

	if (element.removeEventListener) {
		element.removeEventListener(eventname, expression, bubbling);
		return true;
	}
	else if (element.detachEvent) {
		element.detachEvent('on' + eventname, expression);
		return true;
	}
	else {
		return false;
	}
}

function cancelEvent(e) {
	if (!e) {
		e = window.event;
	}

	e.stopPropagation();
	e.preventDefault();

	return false;
}

function checkAll(form_name, chkMain, shkName) {
	var frmForm = document.forms[form_name],
		value = frmForm.elements[chkMain].checked;

	chkbxRange.checkObjectAll(shkName, value);
	chkbxRange.update(shkName);

	return true;
}

function Confirm(msg) {
	return confirm(msg);
}

/**
 * Function removes input elements in specified form that matches given selector.
 *
 * @param {object}|{string}  form_name  Form element in which input elements will be selected. If given value is 'null',
 *                                      the DOM document object will be used.
 * @param {string} selector             String containing one or more commas separated CSS selectors.
 *
 * @returns {bool}
 */
function removeVarsBySelector(form_name, selector) {
	if (form_name !== null) {
		var source = is_string(form_name) ? document.forms[form_name] : form_name;
	}
	else {
		var source = document;
	}

	if (!source) {
		return false;
	}

	var inputs = source.querySelectorAll(selector);

	if (inputs.length) {
		for (var i in inputs) {
			if (typeof inputs[i] === 'object') {
				inputs[i].parentNode.removeChild(inputs[i]);
			}
		}
	}
}

function create_var(form_name, var_name, var_value, doSubmit) {
	var objForm = is_string(form_name) ? document.forms[form_name] : form_name;
	if (!objForm) {
		return false;
	}

	var objVar = (typeof(objForm[var_name]) != 'undefined') ? objForm[var_name] : null;
	if (is_null(objVar)) {
		objVar = document.createElement('input');
		objVar.setAttribute('type', 'hidden');
		if (!objVar) {
			return false;
		}
		objVar.setAttribute('name', var_name);
		objVar.setAttribute('id', var_name.replace(']', '').replace('[', '_'));
		objForm.appendChild(objVar);
	}

	if (is_null(var_value)) {
		objVar.parentNode.removeChild(objVar);
	}
	else {
		objVar.value = var_value;
	}

	if (doSubmit) {
		objForm.submit();
	}

	return false;
}

function getDimensions(obj) {
	var dim = {
		left:		0,
		top:		0,
		right:		0,
		bottom:		0,
		width:		0,
		height:		0,
		offsetleft:	0
	};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		var dim = {
			left:	parseInt(obj.style.left, 10),
			top:	parseInt(obj.style.top, 10),
			width:	parseInt(obj.style.width, 10),
			height:	parseInt(obj.style.height, 10),
			offsetleft: parseInt(jQuery(obj).offset().left, 10)
		};

		if (!is_number(dim.top)) {
			dim.top = parseInt(obj.offsetTop, 10);
		}
		if (!is_number(dim.left)) {
			dim.left = parseInt(obj.offsetLeft, 10);
		}
		if (!is_number(dim.width)) {
			dim.width = parseInt(obj.offsetWidth, 10);
		}
		if (!is_number(dim.height)) {
			dim.height = parseInt(obj.offsetHeight, 10);
		}

		dim.right = dim.left + dim.width;
		dim.bottom = dim.top + dim.height;
	}

	return dim;
}

function getPosition(obj) {
	var pos = {top: 0, left: 0};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		pos.left = obj.offsetLeft;
		pos.top = obj.offsetTop;

		try {
			while (!is_null(obj.offsetParent)) {
				obj = obj.offsetParent;
				pos.left += obj.offsetLeft;
				pos.top += obj.offsetTop;
			}
		} catch(e) {
		}
	}

	return pos;
}

/**
 * Opens popup content in overlay dialogue.
 *
 * @param {string}           action           Popup controller related action.
 * @param {array|object}     parameters       Array with key/value pairs that will be used as query for popup request.
 *
 * @param {string}           dialogue_class   CSS class, usually based on .modal-popup and .modal-popup-{size}.
 * @param {string|null}      dialogueid       ID of overlay dialogue.
 * @param {HTMLElement|null} trigger_element  UI element which was clicked to open overlay dialogue.
 *
 * @returns {Overlay}
 */
function PopUp(action, parameters, {
	dialogueid = null,
	dialogue_class = '',
	trigger_element = document.activeElement
} = {}) {
	var overlay = overlays_stack.getById(dialogueid);

	if (!overlay) {
		overlay = overlayDialogue({
			dialogueid,
			title: '',
			content: jQuery('<div>', {'height': '68px', class: 'is-loading'}),
			class: 'modal-popup ' + dialogue_class,
			buttons: [],
			element: trigger_element,
			type: 'popup'
		});
	}

	overlay
		.load(action, parameters)
		.then(function(resp) {
			if (typeof resp.errors !== 'undefined') {
				overlay.setProperties({
					content: resp.errors
				});
			}
			else {
				var buttons = resp.buttons !== null ? resp.buttons : [];

				switch (action) {
					case 'popup.scheduledreport.list':
					case 'popup.scheduledreport.test':
					case 'popup.scriptexec':
						buttons.push({
							'title': t('Ok'),
							'cancel': true,
							'action': (typeof resp.cancel_action !== 'undefined') ? resp.cancel_action : function() {}
						});
						break;

					default:
						buttons.push({
							'title': t('Cancel'),
							'class': 'btn-alt js-cancel',
							'cancel': true,
							'action': (typeof resp.cancel_action !== 'undefined') ? resp.cancel_action : function() {}
						});
				}

				overlay.setProperties({
					title: resp.header,
					content: resp.body,
					controls: resp.controls,
					buttons: buttons,
					debug: resp.debug,
					script_inline: resp.script_inline,
					data: resp.data || null
				});
			}

			overlay.recoverFocus();
			overlay.containFocus();
		});

	addToOverlaysStack(overlay);

	return overlay;
}

/**
 * Open "Update problem" dialog and manage URL change.
 *
 * @param {Object} parameters
 * @param {array}  parameters['eventids']  Eventids to update.
 * @param {object} trigger_element        (optional) UI element which was clicked to open overlay dialogue.
 *
 * @returns {Overlay}
 */
function acknowledgePopUp(parameters, trigger_element) {
	var overlay = PopUp('popup.acknowledge.edit', parameters, {trigger_element}),
		backurl = location.href;

	overlay.trigger_parents = $(trigger_element).parents();

	overlay.xhr.then(function() {
		var url = new Curl('zabbix.php', false);
		url.setArgument('action', 'popup');
		url.setArgument('popup_action', 'acknowledge.edit');
		url.setArgument('eventids', parameters.eventids);

		history.replaceState({}, '', url.getUrl());
	});

	overlay.$dialogue[0].addEventListener('overlay.close', () => {
		history.replaceState({}, '', backurl);
	}, {once: true});

	return overlay;
}

/**
 * Function to add details about overlay UI elements in global overlays_stack variable.
 *
 * @param {string|Overlay} id       Unique overlay element identifier or Overlay object.
 * @param {object} element          UI element which must be focused when overlay UI element will be closed.
 * @param {object} type             Type of overlay UI element.
 * @param {object} xhr              (optional) XHR request used to load content. Used to abort loading. Currently used
 *                                  with type 'popup' only.
 */
function addToOverlaysStack(id, element, type, xhr) {
	if (id instanceof Overlay) {
		overlays_stack.pushUnique(id);
	}
	else {
		overlays_stack.pushUnique({
			dialogueid: id.toString(),
			element: element,
			type: type,
			xhr: xhr
		});
	}

	jQuery(document)
		.off('keydown', closeDialogHandler)
		.on('keydown', closeDialogHandler);
}

// Keydown handler. Closes last opened overlay UI element.
function closeDialogHandler(event) {
	if (event.which == 27) { // ESC
		var dialog = overlays_stack.end();
		if (typeof dialog !== 'undefined') {
			switch (dialog.type) {
				// Close overlay popup.
				case 'popup':
					overlayDialogueDestroy(dialog.dialogueid);
					break;

				// Close overlay hintbox.
				case 'hintbox':
					hintBox.hideHint(dialog.element, true);
					break;

				// Close popup menu overlays.
				case 'menu-popup':
					jQuery('.menu-popup.menu-popup-top:visible').menuPopup('close', dialog.element);
					break;

				// Close context menu preloader.
				case 'preloader':
					overlayPreloaderDestroy(dialog.dialogueid);
					break;

				// Close overlay time picker.
				case 'clndr':
					CLNDR.clndrhide();
					break;

				// Close overlay message.
				case 'message':
					jQuery(ZBX_MESSAGES).each(function(i, msg) {
						msg.closeAllMessages();
					});
					break;

				// Close overlay color picker.
				case 'color_picker':
					jQuery.colorpicker('hide');
					break;
			}
		}
	}
}

/**
 * Removed overlay from overlays stack and sets focus to source element.
 *
 * @param {string} dialogueid		Id of dialogue, that is being closed.
 * @param {boolean} return_focus	If not FALSE, the element stored in overlay.element will be focused.
 *
 * @return {object|undefined|null}  Overlay object, if found.
 */
function removeFromOverlaysStack(dialogueid, return_focus) {
	var overlay = null,
		index;

	if (return_focus !== false) {
		return_focus = true;
	}

	overlay = overlays_stack.removeById(dialogueid);
	if (overlay && return_focus) {
		jQuery(overlay.element).focus();
	}

	// Remove event listener.
	if (overlays_stack.length == 0) {
		jQuery(document).off('keydown', closeDialogHandler);
	}

	return overlay;
}

/**
 * Reload content of Modal Overlay dialogue without closing it.
 *
 * @param {object} form		Filter form in which element has been changed. Assumed that form is inside Overlay Dialogue.
 * @param {string} action	(optional) action value that is used in CRouter. Default value is 'popup.generic'.
 */
function reloadPopup(form, action) {
	var dialogueid = form.closest('[data-dialogueid]').dataset.dialogueid,
		dialogue_class = jQuery(form).closest('[data-dialogueid]').prop('class'),
		action = action || 'popup.generic',
		parameters = {};

	for (const input of form.elements) {
		parameters[input.name] = input.value;
	};

	PopUp(action, parameters, {dialogueid, dialogue_class});
}

/**
 * Pass value to add.popup trigger.
 *
 * @param {string} object			refers to destination object
 * @param {string} single_value		value passed to destination object
 * @param {string} parentid			parent id
 */
function addValue(object, single_value, parentid) {
	var overlay = overlays_stack.end(),
		value = {};

	if (isset(single_value, overlay.data)) {
		value = overlay.data[single_value];
	}
	else {
		value[object] = single_value;
	}

	if (typeof parentid === 'undefined') {
		var parentid = null;
	}
	var data = {
		object: object,
		values: [value],
		parentId: parentid
	};

	jQuery(document).trigger('add.popup', data);
}

/**
 * Adds multiple values to destination form.
 *
 * @param {string} frame			refers to destination form
 * @param {object} values			values added to destination form
 * @param {boolean} submit_parent	indicates that after adding values, form must be submitted
 */
function addValues(frame, values, submit_parent) {
	var forms = document.getElementsByTagName('FORM')[frame],
		submit_parent = submit_parent || false,
		frm_storage = null;

	for (var key in values) {
		if (values[key] === null) {
			continue;
		}

		if (typeof forms !== 'undefined') {
			frm_storage = jQuery(forms).find('#' + key).get(0);
		}
		if (typeof frm_storage === 'undefined' || frm_storage === null) {
			frm_storage = document.getElementById(key);
		}

		if (jQuery(frm_storage).is('span')) {
			jQuery(frm_storage).html(values[key]);
		}
		else {
			jQuery(frm_storage).val(values[key]).change();
		}
	}

	if (frm_storage !== null && submit_parent) {
		frm_storage.form.submit();
	}
}

/**
 * Collects checked values and passes them to add.popup trigger.
 *
 * @param {string} object		refers to object that is selected from popup
 * @param {string} parentid		parent id
 */
function addSelectedValues(object, parentid) {
	if (typeof parentid === 'undefined') {
		var parentid = null;
	}

	var data = {object: object, values: [], parentId: parentid},
		overlay = overlays_stack.end();

	overlay.$dialogue.find('input[type="checkbox"]').filter(':checked').each((i, c) => {
		if (c.name.indexOf('all_') == -1) {
			data['values'].push(overlay.data[c.value] || c.value);
		}
	});

	jQuery(document).trigger('add.popup', data);
}

/**
 * Add media.
 *
 * @param {string} formname			name of destination form
 * @param {integer} media			media id. If -1, then assumes that this is new media
 * @param {integer} mediatypeid		media type id
 * @param {string} sendto			media sendto value
 * @param {string} period			media period value
 * @param {string} active			media active value
 * @param {string} severity			media severity value
 *
 * @returns true
 */
function add_media(formname, media, mediatypeid, sendto, period, active, severity) {
	var form = window.document.forms[formname];
	var media_name = (media > -1) ? 'medias[' + media + ']' : 'new_media';

	window.create_var(form, media_name + '[mediatypeid]', mediatypeid);
	if (typeof sendto === "object") {
		window.removeVarsBySelector(form, 'input[name^="'+media_name+'[sendto]"]');
		jQuery(sendto).each(function(i, st) {
			window.create_var(form, media_name + '[sendto]['+i+']', st);
		});
	}
	else {
		window.create_var(form, media_name + '[sendto]', sendto);
	}
	window.create_var(form, media_name + '[period]', period);
	window.create_var(form, media_name + '[active]', active);
	window.create_var(form, media_name + '[severity]', severity);

	form.submit();
	return true;
}

/**
 * Send trigger expression form data to server for validation before adding it to trigger expression field.
 *
 * @param {Overlay} overlay
 */
function validate_trigger_expression(overlay) {
	var $form = overlay.$dialogue.find('form'),
		url = new Curl($form.attr('action'));

	url.setArgument('add', 1);

	overlay.setLoading();
	overlay.xhr = jQuery.ajax({
		url: url.getUrl(),
		data: $form.serialize(),
		complete: function() {
			overlay.unsetLoading();
		},
		success: function(ret) {
			overlay.$dialogue.find('.msg-bad, .msg-good').remove();

			if (typeof ret.errors !== 'undefined') {
				jQuery(ret.errors).insertBefore($form);
			}
			else {
				var form = window.document.forms[ret.dstfrm];
				var obj = (typeof form !== 'undefined')
					? jQuery(form).find('#' + ret.dstfld1).get(0)
					: document.getElementById(ret.dstfld1);

				if (ret.dstfld1 === 'expression' || ret.dstfld1 === 'recovery_expression') {
					jQuery(obj).val(jQuery(obj).val() + ret.expression);
				}
				else {
					jQuery(obj).val(ret.expression);
				}

				overlayDialogueDestroy(overlay.dialogueid);
			}
		},
		dataType: 'json',
		type: 'POST'
	});
}

function redirect(uri, method, needle, invert_needle, add_sid, allow_empty) {
	method = (method || 'get').toLowerCase();
	add_sid = (method !== 'get' && (typeof add_sid === 'undefined' || add_sid));

	var url = new Curl(uri, add_sid);

	if (method == 'get') {
		window.location = url.getUrl();
	}
	else {
		// useless param just for easier loop
		var action = '';
		var domBody = document.getElementsByTagName('body')[0];
		var postForm = document.createElement('form');
		domBody.appendChild(postForm);
		postForm.setAttribute('method', 'post');

		invert_needle = (typeof(invert_needle) != 'undefined' && invert_needle);

		var args = url.getArguments();
		for (var key in args) {
			if (!allow_empty && empty(args[key])) {
				continue;
			}

			var is_needle = (typeof(needle) != 'undefined' && key.indexOf(needle) > -1);

			if ((is_needle && !invert_needle) || (!is_needle && invert_needle)) {
				if (Array.isArray(args[key])) {
					for (var i = 0, l = args[key].length; i < l; i++) {
						action += '&' + key + '[]=' + args[key][i];
					}
				}
				else {
					action += '&' + key + '=' + args[key];
				}

				continue;
			}

			var hInput = document.createElement('input');
			hInput.setAttribute('type', 'hidden');
			postForm.appendChild(hInput);

			if (Array.isArray(args[key])) {
				hInput.setAttribute('name', key + '[]');
				for (var i = 0, l = args[key].length; i < l; i++) {
					hInput.setAttribute('value', args[key][i]);
				}
			}
			else {
				hInput.setAttribute('name', key);
				hInput.setAttribute('value', args[key]);
			}
		}

		postForm.setAttribute('action', url.getPath() + '?' + action.substr(1));
		postForm.submit();
	}

	return false;
}

/**
 * Send parameters to given url using natural HTML form submission.
 *
 * @param {string} url
 * @param {Object} params
 * @param {boolean} allow_empty
 *
 * @return {boolean}
 */
function post(url, params) {
	function addVars(post_form, name, value) {
		if (Array.isArray(value)) {
			for (let i = 0; i < value.length; i++) {
				addVars(post_form, `${name}[]`, value[i]);
			}
		}
		else if (typeof value === 'object' && value !== null) {
			for (const [key, _value] of Object.entries(value)) {
				addVars(post_form, `${name}[${key}]`, _value);
			}
		}
		else {
			addVar(post_form, name, value);
		}
	}

	function addVar(post_form, name, value) {
		const is_multiline = /\r|\n/.exec(value);
		let input;

		if (is_multiline) {
			input = document.createElement('textarea');
		}
		else {
			input = document.createElement('input');
			input.type = 'hidden';
		}

		input.name = name;
		input.value = value;
		post_form.appendChild(input);
	}

	const dom_body = document.getElementsByTagName('body')[0];
	const post_form = document.createElement('form');
	post_form.setAttribute('action', url);
	post_form.setAttribute('method', 'post');
	post_form.style.display = 'none';

	for (const [key, value] of Object.entries(params)) {
		addVars(post_form, key, value);
	}

	dom_body.appendChild(post_form);
	post_form.submit();
}

function showHide(obj) {
	if (jQuery(obj).is(':hidden')) {
		jQuery(obj).css('display', 'block');
	}
	else {
		jQuery(obj).css('display', 'none');
	}
}

function showHideVisible(obj) {
	if (is_string(obj)) {
		obj = document.getElementById(obj);
	}
	if (!obj) {
		throw 'showHideVisible(): Object not found.';
	}

	if (obj.style.visibility != 'hidden') {
		obj.style.visibility = 'hidden';
	}
	else {
		obj.style.visibility = 'visible';
	}
}

/**
 * Switch element classes and return final class.
 *
 * @param object|string obj			object or object id
 * @param string        class1
 * @param string        class2
 *
 * @return string
 */
function switchElementClass(obj, class1, class2) {
	obj = (typeof obj === 'string') ? jQuery('#' + obj) : jQuery(obj);

	if (obj.length > 0) {
		if (obj.hasClass(class1)) {
			obj.removeClass(class1);
			obj.addClass(class2);

			return class2;
		}
		else if (obj.hasClass(class2)) {
			obj.removeClass(class2);
			obj.addClass(class1);

			return class1;
		}
	}

	return null;
}

/**
 * Returns the file name of the given path.
 *
 * @param string path
 * @param string suffix
 *
 * @return string
 */
function basename(path, suffix) {
	var name = path.replace(/^.*[\/\\]/g, '');

	if (typeof suffix === 'string' && name.substr(name.length - suffix.length) == suffix) {
		name = name.substr(0, name.length - suffix.length);
	}

	return name;
}

/**
 * Transform datetime parts to two digits e.g., 2 becomes 02.
 *
 * @param int val
 *
 * @return string
 */
function appendZero(val) {
	return val < 10 ? '0' + val : val;
}

/**
 * Function converts unix timestamp to human readable time in format 'Y-m-d H:i:s'.
 *
 * @param {type} time   Unix timestamp to convert.
 *
 * @returns {string}
 */
function time2str(time) {
	var dt = new Date(time * 1000),
		Y = dt.getFullYear(),
		m = appendZero(dt.getMonth()+1),
		d = appendZero(dt.getDate()),
		H = appendZero(dt.getHours()),
		i = appendZero(dt.getMinutes()),
		s = appendZero(dt.getSeconds());

	return Y + '-' + m + '-' + d + ' ' + H + ':' + i + ':' + s;
}

/**
 * Trims selected element values.
 *
 * @param array selectors
 */
jQuery.fn.trimValues = function(selectors) {
	var form = this,
		obj;

	jQuery.each(selectors, function(i, value) {
		obj = jQuery(value, form);

		jQuery(obj).each(function() {
			jQuery(this).val(jQuery.trim(jQuery(this).val()));
		});
	});
};

/**
 * Inserts hidden input into a form
 *
 * @param string form_name
 * @param string input_name
 * @param string input_value
 */
function submitFormWithParam(form_name, input_name, input_value) {
	var input = document.createElement('input');
	input.setAttribute('type', 'hidden');
	input.setAttribute('name', input_name);
	input.setAttribute('value', input_value);
	document.forms[form_name].appendChild(input);
	jQuery(document.forms[form_name]).trigger('submit');
}

if (typeof Element.prototype.remove === 'undefined') {
	Element.prototype.remove = function() {
		this.parentNode.removeChild(this);
		return this;
	};
}

/**
 * @deprecated use native bind method
 */
Function.prototype.bindAsEventListener = function (context) {
	var method = this, args = Array.prototype.slice.call(arguments, 1);

	return function(event) {
		return method.apply(context, [event || window.event].concat(args));
	};
};

function openMassupdatePopup(action, parameters = {}, {
	dialogue_class = '',
	trigger_element = document.activeElement
}) {
	const form = trigger_element.closest('form');

	switch (action) {
		case 'popup.massupdate.host':
			parameters.hostids = chkbxRange.getSelectedIds();
			break;

		default:
			parameters.ids = chkbxRange.getSelectedIds();
	}

	switch (action) {
		case 'popup.massupdate.item':
			parameters.context = form.querySelector('#form_context').value;
			parameters.prototype = 0;
			break;

		case 'popup.massupdate.trigger':
			parameters.context = form.querySelector('#form_context').value;
			break;

		case 'popup.massupdate.itemprototype':
		case 'popup.massupdate.triggerprototype':
			parameters.parent_discoveryid = form.querySelector('#form_parent_discoveryid').value;
			parameters.context = form.querySelector('#form_context').value;
			parameters.prototype = 1;
			break;
	}

	return PopUp(action, parameters, {dialogue_class, trigger_element});
}

/**
 * @param {boolean} value
 * @param {string} objectid
 * @param {string} replace_to
 */
function visibilityStatusChanges(value, objectid, replace_to) {
	const obj = document.getElementById(objectid);

	if (obj === null) {
		throw `Cannot find objects with name [${objectid}]`;
	}

	if (replace_to && replace_to != '') {
		if (obj.originalObject) {
			const old_obj = obj.originalObject;
			old_obj.originalObject = obj;

			obj.parentNode.replaceChild(old_obj, obj);
		}
		else if (!value) {
			const new_obj = document.createElement('span');
			new_obj.setAttribute('name', obj.name);
			new_obj.setAttribute('id', obj.id);
			new_obj.innerHTML = replace_to;
			new_obj.originalObject = obj;

			obj.parentNode.replaceChild(new_obj, obj);
		}
		else {
			throw 'Missing originalObject for restoring';
		}
	}
	else {
		obj.style.visibility = value ? 'visible' : 'hidden';
	}
}

/**
 * Clears session storage from markers of checked table rows.
 * Or keeps only accessible IDs in the list of checked rows.
 *
 * @param {string}       page
 * @param {array|Object} keepids
 */
function uncheckTableRows(page, keepids = []) {
	// This key only works for new MVC pages.
	const key = (page === '') ? 'cb_zabbix' : 'cb_zabbix_'+page;

	if (keepids.length) {
		// If keepids will not have same key as value, it will create mess, when new checkbox will be checked.
		let keepids_formatted = {};
		for (const id of Object.values(keepids)) {
			keepids_formatted[id.toString()] = id.toString();
		}

		sessionStorage.setItem(key, JSON.stringify(keepids_formatted));
	}
	else {
		sessionStorage.removeItem(key);
	}
}
