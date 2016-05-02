/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


// jQuery no conflict
if (typeof(jQuery) != 'undefined') {
	jQuery.noConflict();
}

function isset(key, obj) {
	return (is_null(key) || is_null(obj)) ? false : (typeof(obj[key]) != 'undefined');
}

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

function SDI(msg) {
	if (GK || WK) {
		console.log(msg);
		return true;
	}

	var div_help = document.getElementById('div_help');

	if (typeof(div_help) == 'undefined' || empty(div_help)) {
		var div_help = document.createElement('div');
		var doc_body = document.getElementsByTagName('body')[0];

		if (empty(doc_body)) {
			return false;
		}

		doc_body.appendChild(div_help);
		div_help.setAttribute('id', 'div_help');
		div_help.setAttribute('style', 'position: absolute; left: 10px; top: 100px; border: 1px red solid; width: 400px; height: 400px; background-color: white; font-size: 12px; overflow: auto; z-index: 20;');
	}

	var pre = document.createElement('pre');
	pre.appendChild(document.createTextNode(msg));
	div_help.appendChild(document.createTextNode('DEBUG INFO: '));
	div_help.appendChild(document.createElement('br'));
	div_help.appendChild(pre);
	div_help.appendChild(document.createElement('br'));
	div_help.appendChild(document.createElement('br'));
	div_help.scrollTop = div_help.scrollHeight;

	return true;
}

function SDJ(obj, name) {
	if (GK || WK) {
		console.dir(obj);
		return true;
	}

	var debug = '';
	name = name || 'none';

	for (var key in obj) {
		if (typeof(obj[key]) == name) {
			continue;
		}
		debug += key + ': ' + obj[key] + ' (' + typeof(obj[key]) + ')' + '\n';
	}

	SDI(debug);
}

function addListener(element, eventname, expression, bubbling) {
	bubbling = bubbling || false;
	element = $(element);

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

	if (!empty(e)) {
		if (IE) {
			e.cancelBubble = true;
			e.returnValue = false;

			if (IE9 && e.preventDefault) {
				e.preventDefault();
			}
		}
		else {
			e.stopPropagation();
			e.preventDefault();
		}
	}

	return false;
}

function add_variable(o_el, s_name, x_value, s_formname, o_document) {
	var form;

	if (!o_document) {
		o_document = document;
	}

	if (s_formname) {
		if (!(form = o_document.forms[s_formname])) {
			throw "Missing form with name '" + s_formname + "'.";
		}
	}
	else if (o_el) {
		if (!(form = o_el.form)) {
			throw "Missing form in 'o_el' object";
		}
	}
	else {
		if (!(form = this.form)) {
			throw "Missing form in 'this' object";
		}
	}

	var o_variable = o_document.createElement('input');
	if (!o_variable) {
		throw "Can't create element";
	}
	o_variable.type = 'hidden';
	o_variable.name = s_name;
	o_variable.id = s_name;
	o_variable.value = x_value;
	form.appendChild(o_variable);

	return true;
}

function checkAll(form_name, chkMain, shkName) {
	var frmForm = document.forms[form_name];
	var value = frmForm.elements[chkMain].checked;

	chkbxRange.checkAll(shkName, value);

	return true;
}

function checkLocalAll(form_name, chkMain, chkName) {
	var frmForm = document.forms[form_name];
	var checkboxes = $$('input[name=' + chkName + ']');

	for (var i = 0; i < checkboxes.length; i++) {
		if (isset('type', checkboxes[i]) && checkboxes[i].type == 'checkbox') {
			checkboxes[i].checked = frmForm.elements[chkMain].checked;
		}
	}

	return true;
}

function clearAllForm(form) {
	form = $(form);

	var inputs = form.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		var type = inputs[i].getAttribute('type');
		switch (type) {
			case 'button':
			case 'hidden':
			case 'submit':
				break;
			case 'checkbox':
				inputs[i].checked = false;
				break;
			case 'text':
			case 'password':
			default:
				inputs[i].value = '';
		}
	}

	var selects = form.getElementsByTagName('select');
	for (var i = 0; i < selects.length; i++) {
		selects[i].selectedIndex = 0;
	}

	var areas = form.getElementsByTagName('textarea');
	for (var i = 0; i < areas.length; i++) {
		areas[i].innerHTML = '';
	}

	return true;
}

function close_window() {
	window.setTimeout('window.close();', 500); // solve bug for Internet Explorer
	return false;
}

function Confirm(msg) {
	return confirm(msg, 'title');
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

function deselectAll() {
	if (IE) {
		document.selection.empty();
	}
	else {
		var sel = window.getSelection();
		sel.removeAllRanges();
	}
}

function getDimensions(obj, trueSide) {
	obj = $(obj);

	if (typeof(trueSide) == 'undefined') {
		trueSide = false;
	}

	var dim = {
		left:	0,
		top:	0,
		right:	0,
		bottom:	0,
		width:	0,
		height:	0
	};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		var dim = {
			left:	parseInt(obj.style.left, 10),
			top:	parseInt(obj.style.top, 10),
			right:	parseInt(obj.style.right, 10),
			bottom:	parseInt(obj.style.bottom, 10),
			width:	parseInt(obj.style.width, 10),
			height:	parseInt(obj.style.height, 10)
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

		if (!trueSide) {
			dim.right = dim.left + dim.width;
			dim.bottom = dim.top + dim.height;
		}
	}

	return dim;
}

function getParent(obj, name) {
	if (obj.parentNode.nodeName.toLowerCase() == name.toLowerCase()) {
		return obj.parentNode;
	}
	else if (obj.parentNode.nodeName.toLowerCase() == 'body') {
		return null;
	}
	else {
		return getParent(obj.parentNode, name);
	}
}

function getPosition(obj) {
	obj = $(obj);
	var pos = {top: 0, left: 0};

	if (!is_null(obj) && typeof(obj.offsetParent) != 'undefined') {
		pos.left = obj.offsetLeft;
		pos.top = obj.offsetTop;

		try {
			while (!is_null(obj.offsetParent)) {
				obj = obj.offsetParent;
				pos.left += obj.offsetLeft;
				pos.top += obj.offsetTop;

				if (IE && obj.offsetParent.toString() == 'unknown') {
					break;
				}
			}
		} catch(e) {
		}
	}

	return pos;
}

function get_bodywidth() {
	var w = parseInt(document.body.scrollWidth);
	var w2 = parseInt(document.body.offsetWidth);

	return (w2 < w) ? w2 : w;
}

function get_cursor_position(e) {
	e = e || window.event;
	var cursor = {x: 0, y: 0};

	if (e.pageX || e.pageY) {
		cursor.x = e.pageX;
		cursor.y = e.pageY;
	}
	else {
		var de = document.documentElement;
		var b = document.body;
		cursor.x = e.clientX + (de.scrollLeft || b.scrollLeft) - (de.clientLeft || 0);
		cursor.y = e.clientY + (de.scrollTop || b.scrollTop) - (de.clientTop || 0);
	}

	return cursor;
}

function get_scroll_pos() {
	var scrOfX = 0, scrOfY = 0;

	// netscape compliant
	if (typeof(window.pageYOffset) == 'number') {
		scrOfY = window.pageYOffset;
		scrOfX = window.pageXOffset;
	}
	// DOM compliant
	else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
		scrOfY = document.body.scrollTop;
		scrOfX = document.body.scrollLeft;
	}
	// IE6 standards compliant mode
	else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
		scrOfY = document.documentElement.scrollTop;
		scrOfX = document.documentElement.scrollLeft;
	}

	return [scrOfX, scrOfY];
}

function insertInElement(element_name, text, tagName) {
	var elems = (IE)
		? $$(tagName + '[name=' + element_name + ']')
		: document.getElementsByName(element_name);

	for (var key = 0; key < elems.length; key++) {
		if (typeof(elems[key]) != 'undefined' && !is_null(elems[key])) {
			$(elems[key]).update(text);
		}
	}
}

function openWinCentered(loc, winname, iwidth, iheight, params) {
	var tp = Math.ceil((screen.height - iheight) / 2);
	var lf = Math.ceil((screen.width - iwidth) / 2);

	if (params.length > 0) {
		params = ', ' + params;
	}

	loc = new Curl(loc).getUrl();

	var WinObjReferer = window.open(loc, winname, 'width=' + iwidth + ', height=' + iheight + ', top=' + tp + ', left=' + lf + params);
	WinObjReferer.focus();
}

function PopUp(url, width, height, form_name) {
	if (!width) {
		width = 720;
	}
	if (!height) {
		height = 480;
	}
	if (!form_name) {
		form_name = 'zbx_popup';
	}

	var left = (screen.width - (width + 150)) / 2;
	var top = (screen.height - (height + 150)) / 2;

	var popup = window.open(url, form_name, 'width=' + width + ', height=' + height + ', top=' + top + ', left=' + left + ', resizable=yes, scrollbars=yes, location=no, menubar=no');
	popup.focus();

	return false;
}

function redirect(uri, method, needle) {
	method = method || 'get';
	var url = new Curl(uri);

	if (method.toLowerCase() == 'get') {
		window.location = url.getUrl();
	}
	else {
		// useless param just for easier loop
		var action = '';
		var domBody = document.getElementsByTagName('body')[0];
		var postForm = document.createElement('form');
		domBody.appendChild(postForm);
		postForm.setAttribute('method', 'post');

		var args = url.getArguments();
		for (var key in args) {
			if (empty(args[key])) {
				continue;
			}
			if (typeof(needle) != 'undefined' && key.indexOf(needle) > -1) {
				action += '&' + key + '=' + args[key];
				continue;
			}

			var hInput = document.createElement('input');
			hInput.setAttribute('type', 'hidden');
			postForm.appendChild(hInput);
			hInput.setAttribute('name', key);
			hInput.setAttribute('value', args[key]);
		}

		postForm.setAttribute('action', url.getPath() + '?' + action.substr(1));
		postForm.submit();
	}

	return false;
}

function showHide(obj, style) {
	if (typeof(style) == 'undefined') {
		style = 'inline';
	}
	if (is_string(obj)) {
		obj = document.getElementById(obj);
	}
	if (!obj) {
		throw 'showHide(): Object not found.';
	}

	if (obj.style.display != 'none') {
		obj.style.display = 'none';
		return 0;
	}
	else {
		obj.style.display = style;
		return 1;
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

function showHideByName(name, style) {
	if (typeof(style) == 'undefined') {
		style = 'none';
	}

	var objs = $$('[name=' + name + ']');

	if (empty(objs)) {
		throw 'showHideByName(): Object not found.';
	}

	for (var i = 0; i < objs.length; i++) {
		var obj = objs[i];
		obj.style.display = style;
	}
}

function switchElementsClass(obj, class1, class2) {
	obj = $(obj);
	if (!obj) {
		return false;
	}

	var result = false;

	if (obj.hasClassName(class1)) {
		obj.removeClassName(class1);
		obj.className = class2 + ' ' + obj.className;
		result = class2;
	}
	else if (obj.hasClassName(class2)) {
		obj.removeClassName(class2);
		obj.className =  class1 + ' ' + obj.className;
		result = class1;
	}
	else {
		obj.className = class1 + ' ' + obj.className;
		result = class1;
	}

	return result;
}

function zbx_throw(msg) {
	throw(msg);
}
