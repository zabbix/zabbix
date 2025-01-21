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
		return;
	}

	var objVar = (typeof(objForm[var_name]) != 'undefined') ? objForm[var_name] : null;
	if (is_null(objVar)) {
		objVar = document.createElement('input');
		objVar.setAttribute('type', 'hidden');
		if (!objVar) {
			return;
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
 * @param {string}           action              Popup controller related action.
 * @param {Array|Object}     parameters          Array with key/value pairs that will be used as query for popup
 *                                               request.
 *
 * @param {string}           dialogue_class      CSS class, usually based on .modal-popup and .modal-popup-{size}.
 * @param {string|null}      dialogueid          ID of overlay dialogue.
 * @param {HTMLElement|null} trigger_element     UI element which was clicked to open overlay dialogue.
 * @param {boolean}          prevent_navigation  Show warning when navigating away from an active dialogue.
 *
 * @returns {Overlay}
 */
function PopUp(action, parameters, {
	dialogueid = null,
	dialogue_class = '',
	trigger_element = document.activeElement,
	prevent_navigation = false
} = {}) {
	hintBox.deleteAll();

	let overlay = overlays_stack.getById(dialogueid);

	if (!overlay) {
		overlay = overlayDialogue({
			dialogueid,
			title: '',
			doc_url: '',
			content: jQuery('<div>', {'height': '68px', 'width': '105px', class: 'is-loading'}),
			class: 'modal-popup ' + dialogue_class,
			buttons: [],
			element: trigger_element,
			type: 'popup',
			prevent_navigation
		});
	}

	overlay
		.load(action, parameters)
		.then(function(resp) {
			if ('error' in resp) {
				overlay.setProperties({
					title: resp.header !== undefined ? resp.header : '',
					content: makeMessageBox('bad', resp.error.messages, resp.error.title, false),
					buttons: [
						{
							'title': t('Cancel'),
							'class': 'btn-alt js-cancel',
							'cancel': true,
							'action': function() {}
						}
					]
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
							'action': (resp.cancel_action !== undefined) ? resp.cancel_action : () => {}
						});
						break;

					default:
						if (!buttons.some(button => button.cancel)) {
							buttons.push({
								'title': t('Cancel'),
								'class': 'btn-alt js-cancel',
								'cancel': true,
								'action': (resp.cancel_action !== undefined) ? resp.cancel_action : () => {}
							});
						}
				}

				overlay.setProperties({
					title: resp.header,
					doc_url: resp.doc_url,
					content: resp.body,
					controls: resp.controls,
					class: 'modal-popup ' + resp.dialogue_class ?? dialogue_class,
					buttons,
					debug: resp.debug,
					script_inline: resp.script_inline,
					data: resp.data || null
				});

				const resizeHandler = (grid) => {
					for (const label of grid.querySelectorAll(':scope > label')) {
						const rect = label.getBoundingClientRect()

						if (rect.width > 0) {
							// Use of setTimeout() to prevent ResizeObserver observation error in Safari.
							setTimeout(() => {
								grid.style.setProperty('--label-width', `${Math.round(rect.width)}px`);
							});
							break;
						}
					}
				}

				for (const grid of overlay.$dialogue.$body[0].querySelectorAll(`form .${ZBX_STYLE_FORM_GRID}`)) {
					new ResizeObserver(() => resizeHandler(grid)).observe(grid);

					const labels = grid.querySelectorAll(`:scope > label, :scope > .${ZBX_STYLE_COLLAPSIBLE} > label`);
					for (const label of labels) {
						new MutationObserver(() => resizeHandler(grid)).observe(label, {childList: true});
					}
				}
			}

			overlay.recoverFocus();
			overlay.containFocus();
		})
		.fail((resp) => {
			if (resp.statusText !== 'abort') {
				const error = resp.responseJSON !== undefined && resp.responseJSON.error !== undefined
					? resp.responseJSON.error
					: {title: t('Unexpected server error.')};

				overlay.setProperties({
					content: makeMessageBox('bad', error.messages, error.title, false),
					buttons: [
						{
							'title': t('Cancel'),
							'class': 'btn-alt js-cancel',
							'cancel': true,
							'action': function() {}
						}
					]
				});

				overlay.recoverFocus();
				overlay.containFocus();
			}
		});

	addToOverlaysStack(overlay);

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
	if (event.which === KEY_ESCAPE) {
		const overlay = overlays_stack.end();

		if (typeof overlay !== 'undefined') {
			switch (overlay.type) {
				// Close overlay popup.
				case 'popup':
					overlayDialogueDestroy(overlay.dialogueid, Overlay.prototype.CLOSE_BY_USER);
					break;

				// Close overlay hintbox.
				case 'hintbox':
					hintBox.hideHint(overlay.element, true);
					break;

				// Close popup menu overlays.
				case 'menu-popup':
					jQuery('.menu-popup.menu-popup-top:visible').menuPopup('close', overlay.element);
					break;

				// Close context menu preloader.
				case 'preloader':
					overlayPreloaderDestroy(overlay.dialogueid);
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

				// Close map/shape overlay.
				case 'map-window':
					jQuery('#map-window .btn-overlay-close').trigger('click');
					break;
			}
		}
	}
}

/**
 * Remove overlay from stack and set focus to source element.
 *
 * @param {string}  dialogueid
 * @param {boolean} return_focus  If true, overlay.element will be focused.
 *
 * @return {Object|undefined}  Overlay object, if found.
 */
function removeFromOverlaysStack(dialogueid, return_focus = true) {
	const overlay = overlays_stack.removeById(dialogueid);

	if (overlay && return_focus) {
		if (overlay.element !== undefined) {
			const element = overlay.element instanceof jQuery ? overlay.element[0] : overlay.element;

			element.focus({preventScroll: true});
		}
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
	const dialogueid = form.closest('[data-dialogueid]').dataset.dialogueid;
	const dialogue_class = jQuery(form).closest('[data-dialogueid]').prop('class');
	const parameters = getFormFields(form);

	action = action || 'popup.generic';

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
 */
function addValues(frame, values) {
	var forms = document.getElementsByTagName('FORM')[frame],
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

		if (jQuery(frm_storage).is(':input')) {
			jQuery(frm_storage).val(values[key]).change();
		}
		else {
			jQuery(frm_storage).text(values[key]);
		}
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

			if ('error' in ret) {
				const message_box = makeMessageBox('bad', ret.error.messages, ret.error.title);

				message_box.insertBefore($form);

				return;
			}

			var form = window.document.forms[ret.dstfrm];
			var obj = (typeof form !== 'undefined')
				? jQuery(form).find('#' + ret.dstfld1).get(0)
				: document.getElementById(ret.dstfld1);

			if ((ret.dstfld1 === 'expr_temp' || ret.dstfld1 === 'recovery_expr_temp')) {
				jQuery(obj).val(ret.expression);
			}
			else {
				jQuery(obj).val(jQuery(obj).val() + ret.expression);
			}

			overlayDialogueDestroy(overlay.dialogueid);

			obj.dispatchEvent(new Event('change'));
		},
		dataType: 'json',
		type: 'POST'
	});
}

function redirect(uri, method, needle, invert_needle, allow_empty) {
	method = (method || 'get').toLowerCase();

	var url = new Curl(uri);

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
						action += '&' + key + '[]=' + encodeURIComponent(args[key][i]);
					}
				}
				else {
					action += '&' + key + '=' + encodeURIComponent(args[key]);
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
 * Check if element is visible.
 *
 * @param {object} element
 *
 * @return {boolean}
 */
function isVisible(element) {
	return element.getClientRects().length > 0 && window.getComputedStyle(element).visibility !== 'hidden';
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
		return method.apply(context, [event].concat(args));
	};
};

function openMassupdatePopup(action, parameters = {}, {
	dialogue_class = '',
	trigger_element = document.activeElement
}) {
	const form = trigger_element.closest('form');

	switch (action) {
		case 'popup.massupdate.host':
			parameters.hostids = Object.keys(chkbxRange.getSelectedIds());
			break;

		default:
			parameters.ids = Object.keys(chkbxRange.getSelectedIds());
	}

	switch (action) {
		case 'item.massupdate':
			parameters.context = form.querySelector('#form_context').value;
			parameters.prototype = 0;
			break;

		case 'trigger.massupdate':
			parameters.context = form.querySelector('#form_context').value;
			break;

		case 'item.prototype.massupdate':
		case 'trigger.prototype.massupdate':
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
			new_obj.classList.add('visibility-box-caption');
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
 * @param {boolean}      mvc
 */
function uncheckTableRows(page, keepids = [], mvc = true) {
	const key = mvc ? 'cb_zabbix_'+page : 'cb_'+page;

	if (keepids.length) {
		let keepids_formatted = {};
		const current = chkbxRange.getSelectedIds();

		for (const id of Object.values(keepids)) {
			keepids_formatted[id.toString()] = (id in current) ? current[id] : '';
		}

		sessionStorage.setItem(key, JSON.stringify(keepids_formatted));
	}
	else {
		sessionStorage.removeItem(key);
	}
}

// Fix jQuery ui.sortable vertical positioning bug.
$.widget("ui.sortable", $.extend({}, $.ui.sortable.prototype, {
	_getParentOffset: function () {
		this.offsetParent = this.helper.offsetParent();

		const pos = this.offsetParent.offset();

		if (this.scrollParent[0] !== this.document[0]
				&& $.contains(this.scrollParent[0], this.offsetParent[0])) {
			pos.left += this.scrollParent.scrollLeft();
			pos.top += this.scrollParent.scrollTop();
		}

		if ((this.offsetParent[0].tagName && this.offsetParent[0].tagName.toLowerCase() === 'html' && $.ui.ie)
				|| this.offsetParent[0] === this.document[0].body) {
			pos = {top: 0, left: 0};
		}

		return {
			top: pos.top + (parseInt(this.offsetParent.css('borderTopWidth'), 10) || 0),
			left: pos.left + (parseInt(this.offsetParent.css('borderLeftWidth'), 10) || 0)
		};
	}
}));
