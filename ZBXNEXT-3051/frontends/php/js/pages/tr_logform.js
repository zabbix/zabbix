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


// trigger log expression
var logexpr_count = 0;
var key_count = 0;

function nextObject(n) {
	var t = n.parentNode.tagName;
	do {
		n = n.nextSibling;
	} while (n && n.nodeType != 1 && n.parentNode.tagName == t);

	return n;
}

function previousObject(p) {
	var t = p.parentNode.tagName;
	do {
		p = p.previousSibling;
	} while (p && p.nodeType != 1 && p.parentNode.tagName == t);

	return p;
}

function add_logexpr() {
	var EXPRESSION_TYPE_NO_MATCH = 1;
	try {
		var expr = document.getElementById('logexpr');
		var expr_t = document.getElementById('expr_type');
		var bt_and = document.getElementById('add_key_and');
		var bt_or = document.getElementById('add_key_or');
		var iregexp = document.getElementById('iregexp');
	}
	catch(e) {
		throw('Error: ' + (IE ? e.description : e));
	}

	var ex = bt_and.disabled ? ' or ' : ' and ';
	if (expr_t.value == EXPRESSION_TYPE_NO_MATCH) {
		ex = bt_and.disabled ? ' and ' : ' or ';
	}

	var expression = '';
	var lp;
	for (lp = 0; lp < key_count; lp++) {
		var key = document.getElementsByName('keys[' + lp + '][value]')[0];
		var typ = document.getElementsByName('keys[' + lp + '][type]')[0];
		if (typeof(key) != 'undefined' && typeof(typ) != 'undefined') {
			if (expression != '') {
				expression += ex;
			}
			expression += typ.value + '(' + key.value + ')';
			remove_keyword('keytr' + lp);
		}
	}

	if (typeof(expr.value) != 'undefined' && expr.value != '') {
		if (expression != '') {
			expression += ex;
		}
		expression += iregexp.checked ? 'iregexp' : 'regexp';
		expression += '(' + expr.value + ')';
	}

	if (expression == '') {
		return false;
	}

	var classattr = IE ? 'className' : 'class';

	var tr = document.createElement('tr');
	document.getElementById('exp_list').getElementsByTagName('tbody')[0].appendChild(tr);

	tr.setAttribute('id', 'logtr' + logexpr_count);
	var td = document.createElement('td');
	tr.appendChild(td);
	td.appendChild(document.createTextNode(expression));

	var form = jQuery('form[name=sform]');

	form.prepend(jQuery('<input>', {
		name: 'expressions[' + logexpr_count + '][value]',
		type: 'hidden',
		value: expression
	}));

	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(expr_t.options[expr_t.selectedIndex].text));

	form.prepend(jQuery('<input>', {
		name: 'expressions[' + logexpr_count + '][type]',
		type: 'hidden',
		value: expr_t.value
	}));

	// optional
	var td = document.createElement('td');
	tr.appendChild(td);

	td.setAttribute(IE ? 'cssText' : 'style', 'white-space: nowrap;');

	var img = document.createElement('img');
	img.setAttribute('src', 'images/general/arrow_up.png');
	img.setAttribute('onclick', 'element_up("logtr' + logexpr_count + '");');
	img.setAttribute('border', '0');
	img.setAttribute('alt', 'up');
	img.setAttribute('class', 'updown');

	td.appendChild(img);
	td.appendChild(document.createTextNode(' '));

	var img = document.createElement('img');
	img.setAttribute('src', 'images/general/arrow_down.png');
	img.setAttribute('onclick', 'element_down("logtr' + logexpr_count + '");');
	img.setAttribute('border', '0');
	img.setAttribute('alt', 'down');
	img.setAttribute('class', 'updown');

	td.appendChild(img);

	var td = document.createElement('td');
	td.setAttribute(classattr, 'nowrap');
	tr.appendChild(td);

	var url = document.createElement('button');
	url.setAttribute('onclick', 'javascript: remove_expression("logtr' + logexpr_count + '");');
	url.setAttribute(classattr, 'btn-link');
	url.appendChild(document.createTextNode(locale['S_REMOVE']));

	td.appendChild(url);

	logexpr_count++;
	expr.value = '';
	expr_t.selectedIndex=0;
	bt_and.disabled = false;
	bt_or.disabled = false;

	processExpressionList();
}

function remove_expression(expr_id) {
	var expr_tr = document.getElementById(expr_id);
	var id = getIdFromNodeId(expr_id);
	if (is_number(id)) {
		var elm_v = document.getElementsByName('expressions[' + id + '][value]')[0];
		var elm_t = document.getElementsByName('expressions[' + id + '][type]')[0];
		var elm_s = document.getElementsByName('expressions[' + id + '][view]')[0];

		if (typeof(elm_v) != 'undefined') {
			elm_v.parentNode.removeChild(elm_v);
		}
		if (typeof(elm_t) != 'undefined') {
			elm_t.parentNode.removeChild(elm_t);
		}
		if (typeof(elm_s) != 'undefined') {
			elm_s.parentNode.removeChild(elm_s);
		}
	}
	if (typeof(expr_tr) != 'undefined') {
		expr_tr.parentNode.removeChild(expr_tr);
	}

	processExpressionList();
}

function processExpressionList() {
	actions = jQuery('#exp_list td:nth-child(3) .updown');
	actions.show();
	actions.first().hide();
	actions.last().hide();
}

function element_up(elementid) {
	var c_obj = document.getElementById(elementid);
	var p_obj = c_obj.parentNode;

	if (typeof(p_obj) == 'undefined') {
		return null;
	}

	var c2_obj = previousObject(c_obj);
	if (c2_obj && c2_obj.id.length > 0) {
		swapNodes(c2_obj, c_obj);
		swapNodesNames(c2_obj, c_obj);
	}

	processExpressionList();
}

function element_down(elementid) {
	var c_obj = document.getElementById(elementid);
	var p_obj = c_obj.parentNode;

	if (typeof(p_obj) == 'undefined') {
		return null;
	}

	var c2_obj = nextObject(c_obj);
	if (c2_obj && c2_obj.id.length > 0) {
		swapNodes(c_obj, c2_obj);
		swapNodesNames(c_obj, c2_obj);
	}

	processExpressionList();
}

function swapNodes(n1, n2) {
	var p1, p2, b;

	if ((p1 = n1.parentNode) && (p2 = n2.parentNode)) {
		b = nextObject(n2);
		if (n1 == b) {
			return;
		}

		p1.replaceChild(n2, n1); // new, old
		if (b) {
			// n1 - the node which we insert
			// b - the node before which we insert
			p2.insertBefore(n1, b);
		}
		else {
			p2.appendChild(n1);
		}
	}
}

function swapNodesNames(n1, n2) {
	var id1 = n1.id;
	var id2 = n2.id;
	if (is_string(id1) && is_string(id2)) {
		var reg = /logtr([0-9])/i;
		id1 = parseInt(id1.replace(reg, '$1'));
		id2 = parseInt(id2.replace(reg, '$1'));
	}

	if (is_number(id1) && is_number(id2)) {
		var elm = [];
		elm[0] = document.getElementsByName('expressions[' + id1 + '][value]')[0];
		elm[1] = document.getElementsByName('expressions[' + id1 + '][type]')[0];
		elm[2] = document.getElementsByName('expressions[' + id2 + '][value]')[0];
		elm[3] = document.getElementsByName('expressions[' + id2 + '][type]')[0];

		swapNodes(elm[0], elm[2]);
		swapNodes(elm[1], elm[3]);

		return true;
	}
	return false;
}

function closeForm(page) {
	window.opener.location.reload(true);
	window.self.close();
}

function add_keyword(bt_type) {
	try {
		var expr = document.getElementById('logexpr');
		var iregexp = document.getElementById('iregexp');
		var cb = document.getElementById(bt_type == 'and' ? 'add_key_or' : 'add_key_and');
	}
	catch(e) {
		throw('Error: ' + (IE ? e.description : e));
	}

	if (typeof(expr.value) == 'undefined' || expr.value == '') {
		return false;
	}

	cb.disabled = true;

	var classattr = IE ? 'className' : 'class';

	var tr = document.createElement('tr');
	document.getElementById('key_list').getElementsByTagName('tbody')[0].appendChild(tr);

	tr.setAttribute('id', 'keytr' + key_count);

	// keyword
	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(expr.value));

	var input = IE ? document.createElement('<input name="keys[' + key_count + '][value]" />') : document.createElement('input');
	input.setAttribute('type', 'hidden');
	input.setAttribute('value', expr.value);
	!IE ? input.setAttribute('name', 'keys[' + key_count + '][value]') : '';

	td.appendChild(input);

	// type
	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(iregexp.checked ? 'iregexp' : 'regexp'));

	var input = IE ? document.createElement('<input name="keys[' + key_count + '][type]" />') : document.createElement('input');
	input.setAttribute('type', 'hidden');
	input.setAttribute('value', iregexp.checked ? 'iregexp' : 'regexp');
	!IE ? input.setAttribute('name', 'keys[' + key_count + '][type]') : '';

	td.appendChild(input);

	// delete
	var td = document.createElement('td');
	td.setAttribute(classattr, 'nowrap');
	tr.appendChild(td);

	var url = document.createElement('button');
	url.setAttribute('onclick', 'javascript: remove_keyword("keytr' + key_count + '");');
	url.setAttribute(classattr, 'btn-link');
	url.appendChild(document.createTextNode(locale['S_REMOVE']));

	td.appendChild(url);

	key_count++;
	expr.value = '';
}

function add_keyword_and() {
	add_keyword('and');
}

function add_keyword_or() {
	add_keyword('or');
}

function getIdFromNodeKeyId(id) {
	if (typeof(id) == 'string') {
		var reg = /keytr([0-9])/i;
		id = parseInt(id.replace(reg, '$1'));
	}
	if (typeof(id) == 'number') {
		return id;
	}
	return null;
}

function remove_keyword(key_id) {
	var key_tr = document.getElementById(key_id);
	var id = getIdFromNodeKeyId(key_id);
	if (is_number(id)) {
		var elm_v = document.getElementsByName('keys[' + id + '][value]')[0];
		var elm_t = document.getElementsByName('keys[' + id + '][type]')[0];

		if (typeof(elm_v) == 'undefined') {
			elm_v.parentNode.removeChild(elm_v);
		}
		if (typeof(elm_t) == 'undefined') {
			elm_t.parentNode.removeChild(elm_t);
		}
	}
	if (typeof(key_tr) != 'undefined') {
		key_tr.parentNode.removeChild(key_tr);
	}

	var lp;
	var bData = false;
	for (lp = 0; lp < key_count; lp++) {
		var elm_v = document.getElementsByName('keys[' + lp + '][value]')[0];
		if (typeof(elm_v) != 'undefined') {
			bData = true;
		}
	}
	if (!bData) {
		var bt_and = document.getElementById('add_key_and');
		var bt_or = document.getElementById('add_key_or');
		if (typeof(bt_and) != 'undefined') {
			bt_and.disabled = false;
		}
		if (typeof(bt_or) != 'undefined') {
			bt_or.disabled = false;
		}
	}
}
