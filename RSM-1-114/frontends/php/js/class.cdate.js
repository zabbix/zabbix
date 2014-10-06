/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

var CDate = Class.create();

CDate.prototype = {
	server:		0,			// getTime uses clients :0, or servers time :1
	tzDiff:		0,			// server and client TZ diff
	clientDate:	null,	// clients(JS, Browser) date object
	serverDate:	null,	// servers(PHP, Unix) date object
	tmpDate:	null,		// inner usage

	initialize: function() {
		this.tmpDate = new Date();

		this.clientDate = (arguments.length > 0)
			? new Date(arguments[0])
			: new Date();
		this.calcTZdiff(this.clientDate.getTime());
		this.serverDate = new Date(this.clientDate.getTime() - this.tzDiff * 1000);
	},

	calcTZdiff: function(time) {
		var ddTZOffset;

		if (typeof(time) != 'undefined') {
			this.tmpDate.setTime(time);
			ddTZOffset = this.tmpDate.getTimezoneOffset() * -60;
		}
		else {
			ddTZOffset = this.serverDate.getTimezoneOffset() * -60;
		}

		this.tzDiff = ddTZOffset - PHP_TZ_OFFSET;
	},

	/**
	* Formats date according given format. Uses server timezone.
	* Supported formats: 'd M Y H:i', 'j. M Y G:i', 'Y/m/d H:i', 'M jS, Y h:i A', 'Y M d H:i', 'd.m.Y H:i'
	*
	* @param format PHP style date format limited to supported formats
	*
	* @return string|bool human readable date or false if unsupported format given
	*/
	format: function(format) {
		var shortMn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		var dt = this.getDate(),
			mnth = this.getMonth(),
			yr = this.getFullYear(),
			hrs = this.getHours(),
			mnts = this.getMinutes();

		/**
		 * Transform datetime parts to two digits e.g., 2 becomes 02
		 * @param int val
		 * @return string
		 */
		var appZr = function(val) {
			return val < 10 ? '0' + val : val;
		}

		/**
		 * Append date suffix according to english rules e.g., 3 becomes 3rd
		 * @param int date
		 * @return string
		 */
		var appSfx = function(date) {
			if (date % 10 == 1 && date != 11) {
				return date + 'st';
			}
			if (date % 10 == 2 && date != 12) {
				return date + 'nd';
			}
			if (date % 10 == 3 && date != 13) {
				return date + 'rd';
			}
			return date + 'th';
		}

		switch(format) {
			case 'd M Y H:i':
				return appZr(dt) + ' ' + shortMn[mnth] + ' ' + yr + ' ' + appZr(hrs) + ':' + appZr(mnts);
			case 'j. M Y G:i':
				return dt + '. ' + shortMn[mnth] + ' ' + yr + ' ' + hrs + ':' + appZr(mnts);
			case 'Y/m/d H:i':
				return yr + '/' + appZr(mnth + 1) + '/' + appZr(dt) + ' ' + appZr(hrs) + ':' + appZr(mnts);
			case 'M jS, Y h:i A':
				var ampm = (hrs < 12) ? 'AM' : 'PM';
				hrs = appZr((hrs + 11) % 12 + 1);
				return shortMn[mnth] + ' ' + appSfx(dt) + ', ' + yr + ' ' + hrs + ':' + appZr(mnts) + ' ' + ampm;
			case 'Y M d H:i':
				return  yr + ' ' + shortMn[mnth] + ' ' +appZr(dt) + ' ' + appZr(hrs) + ':' + appZr(mnts);
			case 'd.m.Y H:i':
				return appZr(dt) + '.' + appZr(mnth + 1) + '.' + yr + ' ' + appZr(hrs) + ':' + appZr(mnts);
		}

		return false;
	},

	getZBXDate: function() {
		var thedate = [];
		thedate[0] = this.serverDate.getDate();
		thedate[1] = this.serverDate.getMonth() + 1;
		thedate[2] = this.serverDate.getFullYear();
		thedate[3] = this.serverDate.getHours();
		thedate[4] = this.serverDate.getMinutes();
		thedate[5] = this.serverDate.getSeconds();

		for (var i = 0; i < thedate.length; i++) {
			if ((thedate[i] + '').length < 2) {
				thedate[i] = '0' + thedate[i];
			}
		}

		return '' + thedate[2] + thedate[1] + thedate[0] + thedate[3] + thedate[4] + thedate[5];
	},

	setZBXDate: function(strdate) {
		this.server = 1;

		var theDate = {
			Y: strdate.toString().substr(0, 4),
			m: strdate.toString().substr(4, 2),
			d: strdate.toString().substr(6, 2),
			H: strdate.toString().substr(8, 2),
			i: strdate.toString().substr(10, 2),
			s: strdate.toString().substr(12, 2)
		};

		this.serverDate.setFullYear(theDate.Y);
		this.serverDate.setMonth(theDate.m - 1);
		this.serverDate.setDate(theDate.d);
		this.serverDate.setHours(theDate.H);
		this.serverDate.setMinutes(theDate.i);
		this.serverDate.setSeconds(theDate.s);
		this.serverDate.setMilliseconds(0);
		this.calcTZdiff();

		return this.getTime();
	},

	getFormattedDate: function() {
		var fDate = this.getFullYear() + '-' + (this.getMonth() + 1) + '-' + this.getDate();
		fDate += ' ' + this.getHours() + ':' + this.getMinutes() + ':' + this.getSeconds();
		fDate += ' ' + tzOffsetHours + ':' + this.tzOffset / 3600;

		return fDate;
	},

	toString: function() {
		return this.serverDate.toString();
	},

	parse: function(arg) {
		this.server = 1;
		this.serverDate.setTime(Date.parse(arg));
		this.calcTZdiff();

		return this.getTime();
	},

	getTimezoneOffset: function() {
		return parseInt(-PHP_TZ_OFFSET / 60);
	},

	getMilliseconds: function() {
		return this.serverDate.getMilliseconds();
	},

	getSeconds: function() {
		return this.serverDate.getSeconds();
	},

	getMinutes: function() {
		return this.serverDate.getMinutes();
	},

	getHours: function() {
		return this.serverDate.getHours();
	},

	getDay: function() {
		return this.serverDate.getDay();
	},

	getMonth: function() {
		return this.serverDate.getMonth();
	},

	getYear: function() {
		return this.serverDate.getYear();
	},

	getFullYear: function() {
		return this.serverDate.getFullYear();
	},

	getDate: function() {
		return this.serverDate.getDate();
	},

	getTime: function() {
		if (this.server == 1) {
			return this.serverDate.getTime() + this.tzDiff * 1000;
		}
		else {
			return this.clientDate.getTime();
		}
	},

	setMilliseconds: function(arg) {
		this.server = 1;
		this.serverDate.setMilliseconds(arg);
		this.calcTZdiff();
	},

	setSeconds: function(arg) {
		this.server = 1;
		this.serverDate.setSeconds(arg);
		this.calcTZdiff();
	},

	setMinutes: function(arg) {
		this.server = 1;
		this.serverDate.setMinutes(arg);
		this.calcTZdiff();
	},

	setHours: function(arg) {
		this.server = 1;
		this.serverDate.setHours(arg);
		this.calcTZdiff();
	},

	setDate: function(arg) {
		this.server = 1;
		this.serverDate.setDate(arg);
		this.calcTZdiff();
	},

	setMonth: function(arg) {
		this.server = 1;
		this.serverDate.setMonth(arg);
		this.calcTZdiff();
	},

	setFullYear: function(arg) {
		this.server = 1;
		this.serverDate.setFullYear(arg);
		this.calcTZdiff();
	},

	setTime: function(arg) {
		arg = parseInt(arg, 10);

		this.server = 0;
		this.calcTZdiff(arg);
		this.serverDate.setTime(arg - this.tzDiff * 1000);
		this.clientDate.setTime(arg);
	},

	debug: function(fnc_name, id) {
		if (this.debug_status) {
			var str = 'CDate.' + fnc_name;
			if (typeof(id) != 'undefined') {
				str += ' :' + id;
			}
			if (this.debug_prev == str) {
				return true;
			}

			this.debug_info += str;
			if (this.debug_status == 2) {
				SDI(str);
			}
			this.debug_prev = str;
		}
	}
}
