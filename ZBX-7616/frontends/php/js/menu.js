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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * Title: tigra menu
 *
 * Description: See the demo at url
 * URL: http://www.softcomplex.com/products/tigra_menu/
 * Version: 2.0 (commented source)
 * Date: 04-05-2003 (mm-dd-yyyy)
 * Tech. Support: http://www.softcomplex.com/forum/forumdisplay.php?fid=40
 * Notes: This script is free. Visit official site for further details.
 */
function get_style(el, styleProp) {
	var y = null;

	if (el.currentStyle) {
		y = el.currentStyle[styleProp];
	}
	else if (window.getComputedStyle) {
		y = document.defaultView.getComputedStyle(el, null).getPropertyValue(styleProp);
	}

	return y;
}

function get_real_text_width(text, id) {
	var item_type = 'pum_o_submenu';
	if (id == 0) {
		item_type = 'pum_iheader';
	}

	var test_element = document.createElement('div');
	test_element.setAttribute('class', item_type);
	test_element.setAttribute('style', 'visibility: hidden');
	document.body.appendChild(test_element);

	var font_size_text = get_style(test_element, 'font-size');
	if (!font_size_text) {
		font_size_text = get_style(test_element, 'fontSize');
	}

	var font_size = parseInt(font_size_text);
	font_size += 2;

	var margin_left_text = get_style(test_element, 'margin-left');
	if (!margin_left_text) {
		margin_left_text = get_style(test_element, 'marginLeft');
	}

	var margin_left = parseInt(margin_left_text);

	document.body.removeChild(test_element);

	test_element = document.createElement('a');
	test_element.setAttribute('class', item_type);
	test_element.setAttribute('style', 'font-size: ' + font_size + 'px; visibility: hidden');
	test_element.innerHTML = text;

	document.body.appendChild(test_element);

	var tmp_len = test_element.offsetWidth + margin_left + 5;

	document.body.removeChild(test_element);
	test_element = null;

	return tmp_len;
}

function truncateText(text, len) {
	return (text.length > len) ? text.substring(0, len) + '...' : text;
}

function show_popup_menu(e, content, width) {
	if (A_MENUS[0] !== undefined) {
		A_MENUS[0].collapse();
	}

	var cursor = get_cursor_position(e);
	var tmp_width = 0;
	var max_width = 0;
	var menuTextLength = 35;

	for (var i = 0; i < content.length; i++) {
		if (content[i].length) {
			content[i][0] = truncateText(content[i][0], menuTextLength);
			tmp_width = get_real_text_width(content[i][0], i);

			if (max_width < tmp_width) {
				max_width = tmp_width;
			}
		}

		// truncate sub menu text
		if (content[i].length > 4) {
			for (var j = 4; j < content[i].length; j++) {
				content[i][j][0] = truncateText(content[i][j][0], menuTextLength);
				tmp_width = get_real_text_width(content[i][j][0], i);

				if (max_width < tmp_width) {
					max_width = tmp_width;
				}
			}
		}
	}

	if (width == null || width < max_width) {
		width = max_width;
	}

	if (width == 0) {
		width = 220;
	}

	var pos = [
		{block_top: -12, block_left: -5, width: width},
		{block_top: 5, block_left: width - 5, width: width}
	];

	new popup_menu (content, pos, cursor.x, cursor.y);

	return false;
}

// global collection containing all menus on current page
var A_MENUS = [];

function popup_menu(a_items, a_tpl, x, y) {
	// browser check
	if (!document.body || !document.body.style) {
		return null;
	}

	this.n_scroll_left = get_scroll_pos()[0];
	this.n_scr_width = (document.body.clientWidth)
		? document.body.clientWidth
		: document.width;

	// store items structure
	this.a_config = a_items;

	// store template structure
	this.a_tpl = a_tpl;

	// get menu id
	this.n_id = A_MENUS.length;

	// declare collections
	this.a_index = [];
	this.a_children = [];

	// assign methods and event handlers
	this.expand = menu_expand;
	this.collapse = menu_collapse;
	this.onclick = menu_onclick;
	this.onmouseout = menu_onmouseout;
	this.onmouseover = menu_onmouseover;
	this.onmousedown = menu_onmousedown;
	this.getstyle = mitem_getstyle;
	this.set_x_direction = mitem_set_x_direction;
	this.get_x_direction = mitem_get_x_direction;
	this.set_y_direction = mitem_set_y_direction;
	this.get_y_direction = mitem_get_y_direction;

	// default level scope description structure
	this.a_tpl_def = {
		block_top: 0,
		block_left: 0,
		top: 23,
		left: 0,
		width: 170,
		height: 24,
		hide_delay: 200,
		expd_delay: 200,
		"z-index": 900
	};

	// default css
	this.a_css_def = {
		outer: ['pum_o_item'],
		inner: ['pum_i_item']
	};

	// assign methods and properties required to emulate parent item
	this.getprop = function(s_key) {
		return this.a_tpl_def[s_key];
	};

	this.o_root = this;
	this.n_depth = -1;
	this.n_x = x;
	this.n_y = y;

	// 	init items recursively
	for (n_order = 0; n_order < a_items.length; n_order++) {
		new menu_item(this, n_order);
	}

	// register self in global collection
	A_MENUS[this.n_id] = this;

	// make root level visible
	for (var n_order = 0; n_order < this.a_children.length; n_order++) {
		this.a_children[n_order].e_oelement.style.visibility = 'visible';
	}
}

function mitem_set_x_direction(n_val) {
	this.n_x_direction = n_val;
}

function mitem_get_x_direction() {
	return this.n_x_direction ? this.n_x_direction : null;
}

function mitem_set_y_direction(n_val) {
	this.n_y_direction = n_val;
}

function mitem_get_y_direction() {
	return this.n_y_direction ? this.n_y_direction : null;
}

function menu_collapse(n_id) {
	// cancel item open delay
	clearTimeout(this.o_showtimer);

	// by default collapse all levels
	var n_tolevel = (n_id ? this.a_index[n_id].n_depth : -1);
	if (-1 == n_tolevel) {
		for (n_id = 0; n_id < this.a_index.length; n_id++) {
			var o_curritem = this.a_index[n_id];
			if (o_curritem) {
				var e_oelement = document.getElementById(o_curritem.e_oelement.id);
				if (e_oelement != null) {
					document.body.removeChild(e_oelement);
				}
			}
		}
		A_MENUS.splice(this.o_root.n_id);
	}
	else {
		// hide all items over the level specified
		for (n_id = 0; n_id < this.a_index.length; n_id++) {
			var o_curritem = this.a_index[n_id];
			if (o_curritem && o_curritem.n_depth > n_tolevel && o_curritem.b_visible) {
				o_curritem.e_oelement.style.visibility = 'hidden';
				o_curritem.b_visible = false;
			}
		}
	}

	// reset current item if mouse has gone out of items
	if (!n_id) {
		this.o_current = null;
	}
}

function menu_expand(n_id) {
	// expand only when mouse is over some menu item
	if (this.o_hidetimer) {
		return null;
	}

	// lookup current item
	var o_item = this.a_index[n_id];

	// close previously opened items
	if (this.o_current && this.o_current.n_depth >= o_item.n_depth) {
		this.collapse(o_item.n_id);
	}
	this.o_current = o_item;

	// exit if there are no children to open
	if (!o_item.a_children) {
		return null;
	}

	// show direct child items
	for (var n_order = 0; n_order < o_item.a_children.length; n_order++) {
		var o_curritem = o_item.a_children[n_order];
		o_curritem.e_oelement.style.visibility = 'visible';
		o_curritem.b_visible = true;
	}
}

function menu_onclick(n_id) {
	// don't go anywhere if item has no link defined
	// lookup new item's object
	if (Boolean(this.a_index[n_id].a_config[1])) {
		// lookup new item's object
		var o_item = this.a_index[n_id];

		// apply rollout
		o_item.e_oelement.className = o_item.getstyle(0, 0);
		o_item.e_ielement.className = o_item.getstyle(1, 0);

		this.o_hidetimer = setTimeout('if (typeof(A_MENUS[' + this.n_id + ']) != "undefined") { A_MENUS[' + this.n_id + '].collapse(); }', 100);
		return true;
	}
	return false;
}

function menu_onmouseout(n_id) {
	// lookup new item's object
	var o_item = this.a_index[n_id];

	// apply rollout
	o_item.e_oelement.className = o_item.getstyle(0, 0);
	o_item.e_ielement.className = o_item.getstyle(1, 0);

	// run mouseover timer
	this.o_hidetimer = setTimeout('if (typeof(A_MENUS[' + this.n_id + ']) != "undefined") { A_MENUS[' + this.n_id + '].collapse(); }', o_item.getprop('hide_delay'));
}

function menu_onmouseover(n_id) {
	// cancel mouseoute menu close and item open delay
	clearTimeout(this.o_hidetimer);
	this.o_hidetimer = null;
	clearTimeout(this.o_showtimer);

	// lookup new item's object
	var o_item = this.a_index[n_id];

	// apply rollover
	o_item.e_oelement.className = o_item.getstyle(0, 1);
	o_item.e_ielement.className = o_item.getstyle(1, 1);

	// if onclick open is set then no more actions required
	if (o_item.getprop('expd_delay') < 0) {
		return null;
	}

	// run expand timer
	this.o_showtimer = setTimeout('A_MENUS[' + this.n_id + '].expand(' + n_id + ');', o_item.getprop('expd_delay'));

}

// called when mouse button is pressed on menu item
function menu_onmousedown(n_id) {
	// lookup new item's object
	var o_item = this.a_index[n_id];

	// apply mouse down style
	o_item.e_oelement.className = o_item.getstyle(0, 2);
	o_item.e_ielement.className = o_item.getstyle(1, 2);

	this.expand(n_id);
}

// menu item Class
function menu_item(o_parent, n_order) {
	// store parameters passed to the constructor
	this.n_depth  = o_parent.n_depth + 1;

	var item_offset = this.n_depth ? 4 : 0;
	this.a_config = o_parent.a_config[n_order + item_offset];

	// return if required parameters are missing
	if (!this.a_config || !this.a_config[0]) {
		return;
	}

	// store info from parent item
	this.o_root = o_parent.o_root;
	this.o_parent = o_parent;
	this.n_order = n_order;

	// register in global and parent's collections
	this.n_id = this.o_root.a_index.length + 1;
	this.o_root.a_index[this.n_id] = this;
	o_parent.a_children[n_order] = this;

	// calculate item's coordinates
	var o_root = this.o_root,
		a_tpl  = this.o_root.a_tpl;

	this.a_css = this.a_config[3] ? this.a_config[3] : null;

	// assign methods
	this.getprop  = mitem_getprop;
	this.getstyle = mitem_getstyle;

	this.set_x_direction = mitem_set_x_direction;
	this.get_x_direction = mitem_get_x_direction;
	this.set_y_direction = mitem_set_y_direction;
	this.get_y_direction = mitem_get_y_direction;

	if (!o_parent.n_x_direction && !n_order) {
		// calculate menu direction in first element
		o_parent.set_x_direction(
			this.getprop('width') + o_parent.n_x + this.getprop('block_left') > o_root.n_scr_width + o_root.n_scroll_left ? -1 : 1
		);
	}

	this.n_x = n_order
		? o_parent.a_children[n_order - 1].n_x + this.getprop('left') * o_parent.get_x_direction()
		: o_parent.n_x + this.getprop('block_left') * o_parent.get_x_direction();

	if (-1 ==  o_parent.get_x_direction() && o_parent == o_root && !n_order) {
		this.n_x -= this.getprop('width');
	}

	if (!o_parent.n_y_direction && !n_order) {
		o_parent.set_y_direction(jQuery(window).height() + jQuery(window).scrollTop() - o_parent.n_y - this.getprop('height') * o_parent.a_config.length < 0 ? -1 : 1);
	}

	// top
	this.n_y = n_order
		? o_parent.a_children[n_order - 1].n_y + this.getprop('top')
		: o_parent.n_y + this.getprop('block_top') * (o_parent == o_root ? o_parent.get_y_direction() : 1);

	if (-1 == o_parent.get_y_direction() && !n_order) {
		this.n_y = jQuery(window).height() + jQuery(window).scrollTop() - this.getprop('height') * (o_parent.a_config.length - item_offset);
	}

	// generate item's HMTL
	var el = document.createElement('a');
	var menuItemId = 'e' + o_root.n_id + '_' + this.n_id + 'o';
	el.setAttribute('id', menuItemId);

	// js callback action
	if (jQuery.isFunction(this.a_config[1])) {
		jQuery(el).click(this.a_config[1]);
	}
	// url action
	else {
		if (!is_null(this.a_config[1]) && (this.a_config[1].indexOf('javascript') == -1)
				&& !(!is_null(this.a_config[2]) || this.a_config[2] == 'nosid')) {
			var url = new Curl(this.a_config[1]);
			this.a_config[1] = url.getUrl();
		}

		el.setAttribute('href', this.a_config[1]);
		if (this.a_config[2] && this.a_config[2]['tw']) {
			el.setAttribute('target', this.a_config[2]['tw']);
		}
	}

	el.className = this.getstyle(0, 0);
	el.style.position = 'absolute';
	el.style.top = this.n_y + 'px';
	el.style.left = this.n_x + 'px';
	el.style.width = this.getprop('width') + 'px';
	el.style.height = this.getprop('height') + 'px';
	el.style.visibility = 'hidden';
	el.style.zIndex = parseInt(this.n_depth, 10) + 100;
	el.o_root_n_id = o_root.n_id;
	el.this_n_id = this.n_id;
	el.onclick = A_MENUS_onclick;
	el.onmouseout = A_MENUS_onmouseout;
	el.onmouseover = A_MENUS_onmouseover;
	el.onmousedown = A_MENUS_onmousedown;

	var eldiv = document.createElement('div');
	eldiv.setAttribute('id', 'e' + o_root.n_id + '_' + this.n_id +'i');
	eldiv.className = this.getstyle(1, 0);

	// truncating long strings - they don't fit in the popup menu'
	if (typeof(this.a_config[0]) == 'string' && this.a_config[0].length > 35) {
		eldiv.setAttribute('title', this.a_config[0]);
	}

	eldiv.appendChild(document.createTextNode(this.a_config[0]));

	el.appendChild(eldiv);

	document.body.appendChild(el);

	this.e_ielement = document.getElementById('e' + o_root.n_id + '_' + this.n_id + 'i');
	this.e_oelement = document.getElementById('e' + o_root.n_id + '_' + this.n_id + 'o');

	this.b_visible = !this.n_depth;

	var newResult = 0;
	var nText = '';

	newResult = this.e_ielement.scrollWidth - this.getprop('width');
	if (newResult > 0) {
		// anti down
		var x = 500;
		while (x) {
			newResult = this.e_ielement.scrollWidth - this.getprop('width');
			nText = jQuery.unescapeHtml(this.e_ielement.innerHTML);
			this.e_ielement.innerHTML = jQuery.escapeHtml(nText.substring(0, nText.length - 10));
			x--;
			if (newResult < 1) {
				this.e_ielement.innerHTML += '...';
				x = 0;
				break;
			}
		}
	}

	// no more initialization if leaf
	if (this.a_config.length < item_offset) {
		return null;
	}

	// node specific methods and properties
	this.a_children = [];

	// init downline recursively
	for (var n_order = 0; n_order < this.a_config.length - item_offset; n_order++) {
		new menu_item(this, n_order);
	}
}

function A_MENUS_onclick() {
	if (!is_null(A_MENUS[this.o_root_n_id])) {
		return A_MENUS[this.o_root_n_id].onclick(this.this_n_id);
	}
}

function A_MENUS_onmouseout() {
	return A_MENUS[this.o_root_n_id].onmouseout(this.this_n_id);
}

function A_MENUS_onmouseover() {
	return A_MENUS[this.o_root_n_id].onmouseover(this.this_n_id);
}

function A_MENUS_onmousedown() {
	return A_MENUS[this.o_root_n_id].onmousedown(this.this_n_id);
}

// reads property from template file, inherits from parent level if not found
function mitem_getprop(s_key) {
	// check if value is defined for current level
	var s_value = null,
		a_level = this.o_root.a_tpl[this.n_depth];

	// return value if explicitly defined
	if (a_level) {
		s_value = a_level[s_key];
	}

	// request recursively from parent levels if not defined
	return s_value == null ? this.o_parent.getprop(s_key) : s_value;
}

// reads property from template file, inherits from parent level if not found
function mitem_getstyle(n_pos, n_state) {
	var a_css = this.a_css;

	// request recursively from parent levels if not defined
	if (!a_css) {
		a_css = this.o_root.a_css_def;
	}

	var a_oclass = a_css[n_pos ? 'inner' : 'outer'];

	// same class for all states
	if (typeof(a_oclass) == 'string') {
		return a_oclass;
	}

	// inherit class from previous state if not explicitly defined
	for (var n_currst = n_state; n_currst >= 0; n_currst--) {
		if (a_oclass[n_currst]) {
			return a_oclass[n_currst];
		}
	}
}

/**
 * Creates a header object for the menu.
 *
 * @param label
 *
 * @return {Array}
 */
function createMenuHeader(label) {
	return [label, null, null, {outer: 'pum_oheader', inner: 'pum_iheader'}];
}

/**
 * Creates a menu link object for the menu.
 *
 * Supported config values:
 * - nosid - exclude the SID parameter from the URL.
 *
 * @param label
 * @param action    the target URL
 * @param config    additional configuration parameter
 *
 * @return {Array}
 */
function createMenuItem(label, action, config) {
	return [label, action, config, {outer: 'pum_o_item', inner: 'pum_i_item'}];
}
