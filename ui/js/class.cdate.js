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

var CDate = function () {
	this.clientDate = (arguments.length > 0)
		? new Date(arguments[0])
		: new Date();
	this.calcTZdiff(this.clientDate.getTime());
	this.serverDate = new Date(this.clientDate.getTime() - this.tzDiff * 1000);
};

CDate.prototype = {
	server:		0,			// getTime uses clients :0, or servers time :1
	tzDiff:		0,			// server and client TZ diff
	clientDate:	null,	// clients(JS, Browser) date object
	serverDate:	null,	// servers(PHP, Unix) date object

	calcTZdiff: function(time) {
		if (time === undefined) {
			time = new Date().getTime();
		}

		const timestamp = Object.keys(PHP_TZ_OFFSETS).reverse().find((ts) => ts * 1000 <= time);

		this.tzDiff = this.clientDate.getTimezoneOffset() * -60 - PHP_TZ_OFFSETS[timestamp];
	},

	/**
	* Formats date according given format. Uses server timezone.
	* Supported formats:	'd M Y H:i', 'j. M Y G:i', 'Y/m/d H:i', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d h:i:s A',
	*						'Y-m-d','H:i:s', 'H:i', 'M jS, Y h:i A', 'Y M d H:i', 'd.m.Y H:i' and 'd m Y H i'
	*						Format 'd m Y H i' is also accepted but used internally for date input fields.
	*
	* @param format PHP style date format limited to supported formats
	*
	* @return string|bool human readable date or false if unsupported format given
	*/
	format: function(format) {
		const shortMn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		const dt = this.getDate();
		const mnth = this.getMonth();
		const yr = this.getFullYear();
		const hrs = this.getHours();
		const mnts = this.getMinutes();
		const sec = this.getSeconds();
		const ampm = (hrs < 12) ? 'AM' : 'PM';
		const ampmhrs = appendZero((hrs + 11) % 12 + 1);

		/**
		 * Append date suffix according to English rules e.g., 3 becomes 3rd.
		 *
		 * @param int date
		 *
		 * @return string
		 */
		const appSfx = function(date) {
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
		};

		switch (format) {
			case 'd M Y H:i':
				return appendZero(dt) + ' ' + shortMn[mnth] + ' ' + yr + ' ' + appendZero(hrs) + ':' + appendZero(mnts);
			case 'j. M Y G:i':
				return dt + '. ' + shortMn[mnth] + ' ' + yr + ' ' + hrs + ':' + appendZero(mnts);
			case 'Y/m/d H:i':
				return yr + '/' + appendZero(mnth + 1) + '/' + appendZero(dt) + ' ' + appendZero(hrs) + ':' +
					appendZero(mnts);
			case 'Y-m-d H:i':
				return yr + '-' + appendZero(mnth + 1) + '-' + appendZero(dt) + ' ' + appendZero(hrs) + ':' +
					appendZero(mnts);
			case 'Y-m-d':
				return yr + '-' + appendZero(mnth + 1) + '-' + appendZero(dt);
			case 'H:i:s':
				return  appendZero(hrs) + ':' + appendZero(mnts) + ':' + appendZero(sec);
			case 'H:i':
				return  appendZero(hrs) + ':' + appendZero(mnts);
			case 'M jS, Y h:i A':
				return shortMn[mnth] + ' ' + appSfx(dt) + ', ' + yr + ' ' + ampmhrs + ':' + appendZero(mnts) + ' ' +
					ampm;
			case 'Y M d H:i':
				return  yr + ' ' + shortMn[mnth] + ' ' +appendZero(dt) + ' ' + appendZero(hrs) + ':' + appendZero(mnts);
			case 'd.m.Y H:i':
				return appendZero(dt) + '.' + appendZero(mnth + 1) + '.' + yr + ' ' + appendZero(hrs) + ':' +
					appendZero(mnts);
			case 'd. m. Y H:i':
				return appendZero(dt) + '. ' + appendZero(mnth + 1) + '. ' + yr + ' ' + appendZero(hrs) + ':' +
					appendZero(mnts);
			// date format used for date input fields
			case 'd m Y H i':
				return appendZero(dt) + ' ' + appendZero(mnth + 1) + ' ' + yr + ' ' + appendZero(hrs) + ' ' +
					appendZero(mnts);
			case 'Y-m-d h:i:s A':
				return yr + '-' + appendZero(mnth + 1) + '-' + appendZero(dt) + ' ' + ampmhrs + ':' +
					appendZero(mnts) + ':' + appendZero(sec) + ' ' + ampm;
			default:
				// defaults to Y-m-d H:i:s
				return yr + '-' + appendZero(mnth + 1) + '-' + appendZero(dt) + ' ' + appendZero(hrs) + ':' +
					appendZero(mnts) + ':' + appendZero(sec);
		}
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
		this.setTimeObject(
			strdate.toString().substr(0, 4),
			strdate.toString().substr(4, 2) - 1,
			strdate.toString().substr(6, 2),
			strdate.toString().substr(8, 2),
			strdate.toString().substr(10, 2),
			strdate.toString().substr(12, 2)
		);

		return this.getTime();
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

	setTimeObject: function(y, m, d, h, i, s) {
		this.server = 1;
		function hasAttr(arg) {
			return (typeof(arg) != 'undefined' && arg !== null);
		}

		if (hasAttr(y)) {
			this.serverDate.setFullYear(y);
		}

		if (hasAttr(m) && hasAttr(d)) {
			this.serverDate.setMonth(m, d);
		}
		else if (hasAttr(m)) {
			this.serverDate.setMonth(m);
		}
		else if (hasAttr(d)) {
			this.serverDate.setDate(d);
		}

		if (hasAttr(h)) {
			this.serverDate.setHours(h);
		}

		if (hasAttr(i)) {
			this.serverDate.setMinutes(i);
		}

		if (hasAttr(s)) {
			this.serverDate.setSeconds(s);
		}

		this.calcTZdiff();
	},

	setTime: function(arg) {
		arg = parseInt(arg, 10);
		this.server = 0;
		this.clientDate.setTime(arg);
		this.calcTZdiff(arg);
		this.serverDate.setTime(arg - this.tzDiff * 1000);
	}
};
