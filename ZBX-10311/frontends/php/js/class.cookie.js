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

var cookie = {
	cookies: [],
	prefix:	null,

	init: function() {
		var allCookies = document.cookie.split('; ');

		for (var i = 0; i < allCookies.length; i++) {
			var cookiePair = allCookies[i].split('=');
			this.cookies[cookiePair[0]] = cookiePair[1];
		}
	},

	create: function(name, value, days) {
		var expires = '';

		if (typeof(days) != 'undefined') {
			var date = new Date();
			date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
			expires = '; expires=' + date.toGMTString();
		}

		document.cookie = name + '=' + value + expires + (location.protocol == 'https:' ? '; secure' : '');

		// apache header size limit
		if (document.cookie.length > 8000) {
			document.cookie = name + '=;';
			alert(locale['S_MAX_COOKIE_SIZE_REACHED']);
			return false;
		}
		else {
			this.cookies[name] = value;
		}
		return true;
	},

	createArray: function(name, value, days) {
		var list = value.join(',');
		var list_part = '';
		var part = 1;
		var part_count = parseInt(this.read(name + '_parts'), 10);

		if (is_null(part_count)) {
			part_count = 1;
		}

		var tmp_index = 0
		var result = true;
		while (list.length > 0) {
			list_part = list.substr(0, 4000);
			list = list.substr(4000);
			if (list.length > 0) {
				tmp_index = list_part.lastIndexOf(',');
				if (tmp_index > -1) {
					list = list_part.substring(tmp_index+1) + list;
					list_part = list_part.substring(0, tmp_index + 1);
				}
			}
			result = this.create(name + '_' + part, list_part, days);
			part++;

			if (!result) {
				break;
			}
		}
		this.create(name + '_parts', part - 1, days);

		while (part <= part_count) {
			this.erase(name + '_' + part);
			part++;
		}
	},

	createJSON: function(name, value, days) {
		var value_array = [];
		for (var key in value) {
			if (!empty(value[key])) {
				value_array.push(value[key]);
			}
		}
		this.createArray(name, value_array, days);
	},

	read: function(name) {
		if (typeof(this.cookies[name]) !== 'undefined') {
			return this.cookies[name];
		}
		else if (document.cookie.indexOf(name) != -1) {
			var nameEQ = name + '=';
			var ca = document.cookie.split(';');
			for (var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') {
					c = c.substring(1, c.length);
				}
				if (c.indexOf(nameEQ) == 0) {
					return this.cookies[name] = c.substring(nameEQ.length, c.length);
				}
			}
		}
		return null;
	},

	readArray: function(name) {
		var list = '';
		var list_part = '';
		var part = 1;
		var part_count = parseInt(this.read(name + '_parts'), 10);
		if (is_null(part_count)) {
			part_count = 1;
		}

		// reading all parts of selected list
		while (part <= (part_count + 1)) {
			if (!is_null(list_part)) {
				list += list_part;
			}
			list_part = this.read(name + '_' + part);
			part++;
		}
		var range = (list != '') ? list.split(',') : [];
		return range;
	},

	readJSON: function(name) {
		var value_json = {};
		var value_array = this.readArray(name);
		for (var i = 0; i < value_array.length; i++) {
			if (isset(i, value_array)) {
				value_json[value_array[i]] = value_array[i];
			}
		}
		return value_json;
	},

	erase: function(name) {
		this.create(name, '', -1);
		this.cookies[name] = undefined;
	},

	eraseArray: function(name) {
		var partCount = parseInt(this.read(name + '_parts'), 10);

		if (!is_null(partCount)) {
			for (var i = 1; i <= partCount; i++) {
				this.erase(name + '_' + i);
			}
			this.erase(name + '_parts');
		}
	}
};

/**
 * jQuery Cookie plugin
 *
 * Copyright (c) 2010 Klaus Hartl (stilbuero.de)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 */

/**
 * Create a cookie with the given key and value and other optional parameters.
 *
 * @example $.cookie('the_cookie', 'the_value');
 * @desc Set the value of a cookie.
 * @example $.cookie('the_cookie', 'the_value', { expires: 7, path: '/', domain: 'jquery.com', secure: true });
 * @desc Create a cookie with all available options.
 * @example $.cookie('the_cookie', 'the_value');
 * @desc Create a session cookie.
 * @example $.cookie('the_cookie', null);
 * @desc Delete a cookie by passing null as value. Keep in mind that you have to use the same path and domain
 *       used when the cookie was set.
 *
 * @param String key The key of the cookie.
 * @param String value The value of the cookie.
 * @param Object options An object literal containing key/value pairs to provide optional cookie attributes.
 * @option Number|Date expires Either an integer specifying the expiration date from now on in days or a Date object.
 *                             If a negative value is specified (e.g. a date in the past), the cookie will be deleted.
 *                             If set to null or omitted, the cookie will be a session cookie and will not be retained
 *                             when the browser exits.
 * @option String path The value of the path atribute of the cookie (default: path of page that created the cookie).
 * @option String domain The value of the domain attribute of the cookie (default: domain of page that created the cookie).
 * @option Boolean secure If true, the secure attribute of the cookie will be set and the cookie transmission will
 *                        require a secure protocol (like HTTPS).
 * @type undefined
 *
 * @name $.cookie
 * @cat Plugins/Cookie
 * @author Klaus Hartl/klaus.hartl@stilbuero.de
 */

/**
 * Get the value of a cookie with the given key.
 *
 * @example $.cookie('the_cookie');
 * @desc Get the value of a cookie.
 *
 * @param String key The key of the cookie.
 * @return The value of the cookie.
 * @type String
 *
 * @name $.cookie
 * @cat Plugins/Cookie
 * @author Klaus Hartl/klaus.hartl@stilbuero.de
 */
jQuery.cookie = function (key, value, options) {
	// key and value given, set cookie...
	if (arguments.length > 1 && (value === null || typeof value !== 'object')) {
		options = jQuery.extend({}, options);

		if (value === null) {
			options.expires = -1;
		}

		if (typeof options.expires === 'number') {
			var days = options.expires, t = options.expires = new Date();
			t.setDate(t.getDate() + days);
		}

		return (document.cookie = [
			encodeURIComponent(key), '=',
			options.raw ? String(value) : encodeURIComponent(String(value)),
			options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
			options.path ? '; path=' + options.path : '',
			options.domain ? '; domain=' + options.domain : '',
			(location.protocol == 'https:') ? '; secure' : ''
		].join(''));
	}

	// key and possibly options given, get cookie...
	options = value || {};
	var result, decode = options.raw ? function (s) { return s; } : decodeURIComponent;
	return (result = new RegExp('(?:^|; )' + encodeURIComponent(key) + '=([^;]*)').exec(document.cookie)) ? decode(result[1]) : null;
};
