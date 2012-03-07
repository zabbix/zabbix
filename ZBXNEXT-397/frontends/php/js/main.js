/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

// Array indexOf method for javascript<1.6 compatibility
if (!Array.prototype.indexOf) {
	Array.prototype.indexOf = function (searchElement) {
		'use strict';
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
			location.reload();
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
	startbox_name:	null,	// start checkbox name
	chkboxes:		{},		// ckbx list
	pageGoName:		null,	// which checkboxes should be counted by Go button
	pageGoCount:	0,		// selected checkboxes
	selected_ids:	{},		// ids of selected checkboxes
	goButton:		null,
	page:			null,	// loaded page name

	init: function() {
		var path = new Curl();
		this.page = path.getPath();
		this.selected_ids = cookie.readJSON('cb_' + this.page);
		var chk_bx = document.getElementsByTagName('input');
		for (var i = 0; i < chk_bx.length; i++) {
			if (typeof(chk_bx[i]) != 'undefined' && chk_bx[i].type.toLowerCase() == 'checkbox') {
				this.implement(chk_bx[i]);
			}
		}

		this.goButton = $('goButton');
		if (!is_null(this.goButton)) {
			addListener(this.goButton, 'click', this.submitGo.bindAsEventListener(this), false);
		}
		this.setGo();
	},

	implement: function(obj) {
		var obj_name = obj.name.split('[')[0];

		if (typeof(this.chkboxes[obj_name]) == 'undefined') {
			this.chkboxes[obj_name] = [];
		}
		this.chkboxes[obj_name].push(obj);

		addListener(obj, 'click', this.check.bindAsEventListener(this), false);

		if (obj_name == this.pageGoName) {
			var obj_id  = obj.name.split('[')[1];
			obj_id = obj_id.substring(0, obj_id.lastIndexOf(']'));

			if (isset(obj_id, this.selected_ids)) {
				obj.checked = true;
			}
		}
	},

	check: function(e) {
		e = e || window.event;
		var obj = Event.element(e);

		PageRefresh.restart();

		if (typeof(obj) == 'undefined' || obj.type.toLowerCase() != 'checkbox') {
			return true;
		}

		this.setGo();

		if (obj.name.indexOf('all_') > -1 || obj.name.indexOf('_single') > -1) {
			return true;
		}
		var obj_name = obj.name.split('[')[0];

		// check range selection
		if (e.ctrlKey || e.shiftKey) {
			if (!is_null(this.startbox) && this.startbox_name == obj_name && obj.name != this.startbox.name) {
				var chkbx_list = this.chkboxes[obj_name];
				var flag = false;

				for (var i = 0; i < chkbx_list.length; i++) {
					if (typeof(chkbx_list[i]) != 'undefined') {
						if (flag) {
							chkbx_list[i].checked = this.startbox.checked;
						}
						if (obj.name == chkbx_list[i].name) {
							break;
						}
						if (this.startbox.name == chkbx_list[i].name) {
							flag = true;
						}
					}
				}

				if (flag) {
					this.setGo();
					return true;
				}
				else {
					for (var i = chkbx_list.length - 1; i >= 0; i--) {
						if (typeof(chkbx_list[i]) != 'undefined') {
							if (flag) {
								chkbx_list[i].checked = this.startbox.checked;
							}

							if (obj.name == chkbx_list[i].name) {
								this.setGo();
								return true;
							}

							if (this.startbox.name == chkbx_list[i].name) {
								flag = true;
							}
						}
					}
				}
			}
			this.setGo();
		}
		this.startbox = obj;
		this.startbox_name = obj_name;
	},

	checkAll: function(name, value) {
		if (typeof(this.chkboxes[name]) == 'undefined') {
			return false;
		}

		var chk_bx = this.chkboxes[name];
		for (var i = 0; i < chk_bx.length; i++) {
			if (typeof(chk_bx[i]) != 'undefined' && chk_bx[i].disabled != true) {
				var obj_name = chk_bx[i].name.split('[')[0];
				if (obj_name == name) {
					chk_bx[i].checked = value;
				}
			}
		}
	},

	setGo: function() {
		if (!is_null(this.pageGoName)) {
			if (typeof(this.chkboxes[this.pageGoName]) == 'undefined') {
				return false;
			}

			var chk_bx = this.chkboxes[this.pageGoName];
			for (var i = 0; i < chk_bx.length; i++) {
				if (typeof(chk_bx[i]) != 'undefined') {
					var box = chk_bx[i];
					var obj_name = box.name.split('[')[0];
					var obj_id  = box.name.split('[')[1];
					obj_id = obj_id.substring(0, obj_id.lastIndexOf(']'));
					var crow = getParent(box, 'tr');

					if (box.checked) {
						if (!is_null(crow)) {
							var origClass = crow.getAttribute('origClass');
							if (is_null(origClass)) {
								crow.setAttribute('origClass', crow.className);
							}
							crow.className = 'selected';
						}
						if (obj_name == this.pageGoName) {
							this.selected_ids[obj_id] = obj_id;
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
						if (obj_name == this.pageGoName) {
							delete(this.selected_ids[obj_id]);
						}
					}
				}
			}

			var countChecked = 0;
			for (var key in this.selected_ids) {
				if (!empty(this.selected_ids[key])) {
					countChecked++;
				}
			}

			if (!is_null(this.goButton)) {
				var tmp_val = this.goButton.value.split(' ');
				this.goButton.value = tmp_val[0] + ' (' + countChecked + ')';
			}

			cookie.createJSON('cb_' + this.page, this.selected_ids);

			this.pageGoCount = countChecked;
		}
	},

	submitGo: function(e){
		e = e || window.event;

		if (this.pageGoCount > 0) {
			var goSelect = $('go');
			var confirmText = goSelect.options[goSelect.selectedIndex].getAttribute('confirm');

			if (!is_null(confirmText) && !confirm(confirmText)) {
				Event.stop(e);
				return false;
			}

			var form = getParent(this.goButton, 'form');
			for (var key in this.selected_ids) {
				if (!empty(this.selected_ids[key])) {
					create_var(form.name, this.pageGoName + '[' + key + ']', key, false);
				}
			}
			return true;
		}
		else {
			alert(locale['S_NO_ELEMENTS_SELECTED']);
			Event.stop(e);
			return false;
		}
	}
};

/*
 * Audio Control System
 */
var AudioList = {
	list:		{}, // audio files options
	dom:		{}, // dom objects links
	standart:	{
		'embed': {
			'enablejavascript': 'true',
			'autostart': 'false',
			'loop': 0
		},
		'audio': {
			'autobuffer': 'autobuffer',
			'autoplay': null,
			'controls': null
		}
	},

	play: function(audiofile) {
		if (!this.create(audiofile)) {
			return false;
		}
		if (IE) {
			try {
				this.dom[audiofile].Play();
			}
			catch(e) {
				setTimeout(this.play.bind(this, audiofile), 500);
			}
		}
		else {
			this.dom[audiofile].play();
		}
	},

	pause: function(audiofile) {
		if (!this.create(audiofile)) {
			return false;
		}
		if (IE) {
			try {
				this.dom[audiofile].Stop();
			}
			catch(e) {
				setTimeout(this.pause.bind(this, audiofile), 1000);
			}
		}
		else {
			this.dom[audiofile].pause();
		}
	},

	stop: function(audiofile) {
		if (!this.create(audiofile)) {
			return false;
		}

		if (IE) {
			this.dom[audiofile].setAttribute('loop', '0');
		}
		else {
			this.dom[audiofile].removeAttribute('loop');
		}

		if (!IE) {
			try {
				if (!this.dom[audiofile].paused) {
					this.dom[audiofile].currentTime = 0;
				}
				else if (this.dom[audiofile].currentTime > 0) {
					this.dom[audiofile].play();
					this.dom[audiofile].currentTime = 0;
					this.dom[audiofile].pause();
				}
			}
			catch(e) {
			}
		}

		if (!is_null(this.list[audiofile].timeout)) {
			clearTimeout(this.list[audiofile].timeout);
			this.list[audiofile].timeout = null;
		}

		this.pause(audiofile);
		this.endLoop(audiofile);
	},

	stopAll: function(e){
		for (var name in this.list) {
			if (empty(this.dom[name])) {
				continue;
			}
			this.stop(name);
		}
	},

	volume: function(audiofile, vol) {
		if (!this.create(audiofile)) {
			return false;
		}
	},

	loop: function(audiofile, params) {
		if (!this.create(audiofile)) {
			return false;
		}

		if (isset('repeat', params)) {
			if (IE) {
				this.play(audiofile);
			}
			else {
				if (this.list[audiofile].loop == 0) {
					if (params.repeat != 0) {
						this.startLoop(audiofile, params.repeat);
					}
					else {
						this.endLoop(audiofile);
					}
				}
				if (this.list[audiofile].loop != 0) {
					this.list[audiofile].loop--;
					this.play(audiofile);
				}
			}
		}
		else if (isset('seconds', params)) {
			if (IE) {
				this.dom[audiofile].setAttribute('loop', '1');
			}
			else {
				this.startLoop(audiofile, 9999999);
				this.list[audiofile].loop--;
			}
			this.play(audiofile);
			this.list[audiofile].timeout = setTimeout(AudioList.stop.bind(AudioList, audiofile), 1000 * parseInt(params.seconds, 10));
		}
	},

	startLoop: function(audiofile, loop) {
		if (!isset(audiofile, this.list)) {
			return false;
		}
		if (isset('onEnded', this.list[audiofile])) {
			this.endLoop(audiofile);
		}
		this.list[audiofile].loop = parseInt(loop, 10);
		this.list[audiofile].onEnded = this.loop.bind(this, audiofile, {'repeat' : 0});
		addListener(this.dom[audiofile], 'ended', this.list[audiofile].onEnded);
	},

	endLoop: function(audiofile) {
		if (!isset(audiofile, this.list)) {
			return true;
		}
		this.list[audiofile].loop = 0;

		if (isset('onEnded', this.list[audiofile])) {
			removeListener(this.dom[audiofile], 'ended', this.list[audiofile].onEnded);
			this.list[audiofile].onEnded = null;
			delete(this.list[audiofile].onEnded);
		}
	},

	create: function(audiofile, params) {
		if (typeof(audiofile) == 'undefined') {
			return false;
		}
		if (isset(audiofile, this.list)) {
			return true;
		}
		if (typeof(params) == 'undefined') {
			params = {};
		}
		if (!isset('audioList', this.dom)) {
			this.dom.audioList = document.createElement('div');
			document.getElementsByTagName('body')[0].appendChild(this.dom.audioList);
			this.dom.audioList.setAttribute('id', 'audiolist');
		}

		if (IE) {
			this.dom[audiofile] = document.createElement('embed');
			this.dom.audioList.appendChild(this.dom[audiofile]);
			this.dom[audiofile].setAttribute('name', audiofile);
			this.dom[audiofile].setAttribute('src', 'audio/' + audiofile);
			this.dom[audiofile].style.display = 'none';

			for (var key in this.standart.embed) {
				if (isset(key, params)) {
					this.dom[audiofile].setAttribute(key, params[key]);
				}
				else if (!is_null(this.standart.embed[key])) {
					this.dom[audiofile].setAttribute(key, this.standart.embed[key]);
				}
			}
		}
		else {
			this.dom[audiofile] = document.createElement('audio');
			this.dom.audioList.appendChild(this.dom[audiofile]);
			this.dom[audiofile].setAttribute('id', audiofile);
			this.dom[audiofile].setAttribute('src', 'audio/' + audiofile);

			for (var key in this.standart.audio) {
				if (isset(key, params)) {
					this.dom[audiofile].setAttribute(key, params[key]);
				}
				else if (!is_null(this.standart.audio[key])) {
					this.dom[audiofile].setAttribute(key, this.standart.audio[key]);
				}
			}
			this.dom[audiofile].load();
		}
		this.list[audiofile] = params;
		this.list[audiofile].loop = 0;
		this.list[audiofile].timeout = null;
		return true;
	},

	remove: function(audiofile) {
		if (!isset(audiofile, this.dom)) {
			return true;
		}
		$(this.dom[audiofile]).remove();

		delete(this.dom[audiofile]);
		delete(this.list[audiofile]);
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
 *      <span class="blink">test 3</span>
 *      <script type="text/javascript">
 *          jQuery(document).ready(function(
 *              jqBlink.blink();
 *          ));
 *      </script>
 * Elements with class 'blink' will blink for 'data-seconds-to-blink' seconds
 * If 'data-seconds-to-blink' is omitted, element will blink forever.
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
		objects.css('visibility', this.shown ? 'hidden' : 'visible');

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
 * ZABBIX HintBoxes
 */
var hintBox = {
	boxes: {}, // array of dom Hint Boxes
	boxesCount: 0, // unique box id

	createBox: function(obj, hint_text, width, className, byClick) {
		var boxid = 'hintbox_' + this.boxesCount;
		var box = document.createElement('div');
		var obj_tag = obj.nodeName.toLowerCase();

		if (obj_tag == 'td' || obj_tag == 'body') {
			obj.appendChild(box);
		}
		else {
			obj.parentNode.appendChild(box);
		}

		box.setAttribute('id', boxid);
		box.style.display = 'none';
		box.className = 'hintbox';

		if (!empty(className)) {
			hint_text = "<span class=\"" + className + "\">" + hint_text + "</span>";
		}

		if (!empty(width)) {
			box.style.width = width + 'px';
		}

		var close_link = '';
		if (byClick) {
			close_link = '<div class="link" '+
							'style="text-align: right; border-bottom: 1px #333 solid;" '+
							'onclick="javascript: hintBox.hide(\'' + boxid + '\');">' + locale['S_CLOSE'] + '</div>';
		}

		box.innerHTML = close_link + hint_text;
		this.boxes[boxid] = box;
		this.boxesCount++;
		return box;
	},

	showOver: function(obj, hint_text, width, className) {
		var hintid = obj.getAttribute('hintid');
		var hintbox = $(hintid);

		if (!empty(hintbox)) {
			var byClick = hintbox.getAttribute('byclick');
		}
		else {
			var byClick = null;
		}

		if (!empty(byClick)) {
			return;
		}

		hintbox = this.createBox(obj, hint_text, width, className, false);
		obj.setAttribute('hintid', hintbox.id);
		this.show(obj, hintbox);
	},

	hideOut: function(obj) {
		var hintid = obj.getAttribute('hintid');
		var hintbox = $(hintid);

		if (!empty(hintbox)) {
			var byClick = hintbox.getAttribute('byclick');
		}
		else {
			var byClick = null;
		}

		if (!empty(byClick)) {
			return;
		}

		if (!empty(hintid)) {
			obj.removeAttribute('hintid');
			obj.removeAttribute('byclick');
			this.hide(hintid);
		}
	},

	onClick: function(obj, hint_text, width, className) {
		var hintid = obj.getAttribute('hintid');
		var hintbox = $(hintid);

		if (!empty(hintbox)) {
			var byClick = hintbox.getAttribute('byclick');
		}
		else {
			var byClick = null;
		}

		if (!empty(hintid) && empty(byClick)) {
			obj.removeAttribute('hintid');
			this.hide(hintid);
			hintbox = this.createBox(obj, hint_text, width, className, true);
			hintbox.setAttribute('byclick', 'true');
			obj.setAttribute('hintid', hintbox.id);
			this.show(obj, hintbox);
		}
		else if (!empty(hintid)) {
			obj.removeAttribute('hintid');
			hintbox.removeAttribute('byclick');
			this.hide(hintid);
		}
		else {
			hintbox = this.createBox(obj, hint_text, width, className, true);
			hintbox.setAttribute('byclick', 'true');
			obj.setAttribute('hintid', hintbox.id);
			this.show(obj, hintbox);
		}
	},

	show: function(obj, hintbox) {
		var body_width = document.viewport.getDimensions().width;
		hintbox.style.visibility = 'hidden';
		hintbox.style.display = 'block';
		var posit = $(obj).positionedOffset();
		var cumoff = $(obj).cumulativeOffset();

		if (parseInt(cumoff.left + 10 + hintbox.offsetWidth) > body_width) {
			posit.left = posit.left - parseInt((cumoff.left + 10 + hintbox.offsetWidth) - body_width) + document.viewport.getScrollOffsets().left;
			posit.left -= 10;
			posit.left = (posit.left < 0) ? 0 : posit.left;
		}
		else {
			posit.left += 10;
		}
		hintbox.x = posit.left;
		hintbox.y = posit.top;
		hintbox.style.left = hintbox.x + 'px';
		hintbox.style.top = hintbox.y + 10 + parseInt(obj.offsetHeight / 2) + 'px';
		hintbox.style.visibility = 'visible';
		hintbox.style.zIndex = '999';
	},

	hide: function(boxid) {
		var hint = $(boxid);
		if (!is_null(hint)) {
			delete(this.boxes[boxid]);

			// Opera browser refresh bug!
			hint.style.display = 'none';
			if (OP) {
				setTimeout(function(){ hint.remove(); }, 200);
			}
			else {
				hint.remove();
			}
		}
	},

	hideAll: function() {
		for (var id in this.boxes) {
			if (typeof(this.boxes[id]) != 'undefined' && !empty(this.boxes[id])) {
				this.hide(id);
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

function show_color_picker(name) {
	if (!color_picker) {
		return;
	}
	curr_lbl = document.getElementById('lbl_' + name);
	curr_txt = document.getElementById(name);
	var pos = getPosition(curr_lbl);
	color_picker.x = pos.left;
	color_picker.y = pos.top;
	color_picker.style.left = color_picker.x + 'px';
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

function set_color_by_name(name, color) {
	curr_lbl = document.getElementById('lbl_' + name);
	curr_txt = document.getElementById(name);
	set_color(color);
}

/*
 * Zabbix ajax requests
 */
function add2favorites(favobj, favid) {
	if ('undefined' == typeof(Ajax)) {
		throw('Prototype.js lib is required!');
	}

	if (typeof(favid) == 'undefined' || empty(favid)) {
		return;
	}

	var params = {
		'favobj': favobj,
		'favid': favid,
		'favaction': 'add'
	};

	send_params(params);
}

function rm4favorites(favobj, favid, menu_rowid) {
	if ('undefined' == typeof(Ajax)) {
		throw('Prototype.js lib is required!');
	}

	if (typeof(favobj) == 'undefined' || typeof(favid) == 'undefined') {
		throw 'No agruments sent to function [rm4favorites()].';
	}

	var params = {
		'favobj': favobj,
		'favid': favid,
		'favcnt': menu_rowid,
		'favaction': 'remove'
	};

	send_params(params);
}

function change_flicker_state(divid) {
	deselectAll();

	var switchArrows = function() {
		switchElementsClass($('flicker_icon_l'), 'dbl_arrow_up', 'dbl_arrow_down');
		switchElementsClass($('flicker_icon_r'), 'dbl_arrow_up', 'dbl_arrow_down');
	};

	var filter_state = ShowHide(divid);
	switchArrows();

	if (false === filter_state) {
		return false;
	}

	var params = {
		'favaction': 'flop',
		'favobj': 'filter',
		'favref': divid,
		'favstate': filter_state
	};
	send_params(params);

	// selection box position
	if (typeof(moveSBoxes) != 'undefined') {
		moveSBoxes();
	}
}

function changeHatStateUI(icon, divid) {
	deselectAll();

	var switchIcon = function() {
		switchElementsClass(icon, 'arrowup', 'arrowdown');
	};

	jQuery($(divid).parentNode).
		find('.body').toggle().end().
		find('.footer').toggle().end();

	switchIcon();

	var hat_state = jQuery(icon).hasClass('arrowup') ? 1 : 0;
	if (false === hat_state) {
		return false;
	}

	var params = {
		'favaction': 'flop',
		'favobj': 'hat',
		'favref': divid,
		'favstate': hat_state
	};
	send_params(params);
}

function change_hat_state(icon, divid) {
	deselectAll();

	var switchIcon = function() {
		switchElementsClass(icon, 'arrowup', 'arrowdown');
	};

	var hat_state = ShowHide(divid);
	switchIcon();

	if (false === hat_state) {
		return false;
	}

	var params = {
		'favaction': 'flop',
		'favobj': 'hat',
		'favref': divid,
		'favstate': hat_state
	};
	send_params(params);
}

function send_params(params) {
	if (typeof(params) === 'undefined') {
		params = [];
	}

	var url = new Curl(location.href);
	url.setQuery('?output=ajax');

	new Ajax.Request(url.getUrl(), {
			'method': 'post',
			'parameters': params,
			'onSuccess': function() { },
			'onFailure': function() {
				document.location = url.getPath() + '?' + Object.toQueryString(params);
			}
		}
	);
}

function setRefreshRate(pmasterid, dollid, interval, params) {
	if (typeof(Ajax) == 'undefined') {
		throw('Prototype.js lib is required!');
	}

	if (typeof(params) == 'undefined' || is_null(params)) {
		params = {};
	}
	params['pmasterid'] = pmasterid;
	params['favobj'] = 'set_rf_rate';
	params['favref'] = dollid;
	params['favcnt'] = interval;
	send_params(params);
}

function switch_mute(icon) {
	deselectAll();
	var sound_state = switchElementsClass(icon, 'iconmute', 'iconsound');

	if (false === sound_state) {
		return false;
	}
	sound_state = (sound_state == 'iconmute') ? 1 : 0;

	var params = {
		'favobj': 'sound',
		'favref': 'sound',
		'favstate': sound_state
	};
	send_params(params);
}

function createPlaceholders() {
	if (IE) {
		jQuery(document).ready(function() {
			'use strict';

			jQuery('[placeholder]').focus(function() {
				if (jQuery(this).val() == jQuery(this).attr('placeholder')) {
					jQuery(this).val('');
					jQuery(this).removeClass('placeholder');
				}
			}).blur(function() {
				if (jQuery(this).val() == '' || jQuery(this).val() == jQuery(this).attr('placeholder')) {
					jQuery(this).addClass('placeholder');
					jQuery(this).val(jQuery(this).attr('placeholder'));
				}
			}).blur();
		});
	}
}
