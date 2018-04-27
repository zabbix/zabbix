// JavaScript Document
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
**
*/
var CLNDR = new Array();
var calendar = Class.create();

function create_calendar(time, timeobjects, id, utime_field_id, parentNodeid) {
	id = id || CLNDR.length;
	if ('undefined' == typeof(utime_field_id)) {
		utime_field_id = null;
	}
	CLNDR[id] = new Object;
	CLNDR[id].clndr = new calendar(id, time, timeobjects, utime_field_id, parentNodeid);
	return CLNDR[id];
}

calendar.prototype = {
	id: null,					// personal ID
	cdt: new CDate(),			// Date object of current(viewed) date
	sdt: new CDate(),			// Date object of a selected date
	month: 0,					// represents month number
	year: 2008,					// represents year
	day: 1,						// represents days
	hour: 12,					// hours
	minute: 00,					// minutes
	clndr_calendar: null,		// html obj of calendar
	clndr_minute: null,			// html from obj
	clndr_hour: null,			// html from obj
	clndr_days: null,			// html obj
	clndr_month: null,			// html obj
	clndr_year: null,			// html obj
	clndr_selectedday: null,	// html obj, selected day
	clndr_monthup: null,		// html bttn obj
	clndr_monthdown: null,		// html bttn obj
	clndr_yearup: null,			// html bttn obj
	clndr_yeardown: null,		// html bttn obj
	clndr_now: null,			// html bttn obj
	clndr_done: null,			// html bttn obj
	clndr_utime_field: null,	// html obj where unix date representation is saved
	timeobjects: new Array(),	// object list where will be saved date
	status: false,				// status of timeobjects
	visible: 0,					// GMenu style state
	monthname: new Array(locale['S_JANUARY'], locale['S_FEBRUARY'], locale['S_MARCH'], locale['S_APRIL'], locale['S_MAY'], locale['S_JUNE'], locale['S_JULY'], locale['S_AUGUST'], locale['S_SEPTEMBER'], locale['S_OCTOBER'], locale['S_NOVEMBER'], locale['S_DECEMBER']),

	initialize: function(id, stime, timeobjects, utime_field_id, parentNodeid) {
		this.id = id;
		this.timeobjects = new Array();
		if (!(this.status = this.checkOuterObj(timeobjects))) {
			throw 'Calendar: constructor expects second parameter to be list of DOM nodes [d,M,Y,H,i].';
		}
		this.calendarcreate(parentNodeid);

		addListener(this.clndr_monthdown, 'click', this.monthdown.bindAsEventListener(this));
		addListener(this.clndr_monthup, 'click', this.monthup.bindAsEventListener(this));
		addListener(this.clndr_yeardown, 'click', this.yeardown.bindAsEventListener(this));
		addListener(this.clndr_yearup, 'click', this.yearup.bindAsEventListener(this));
		addListener(this.clndr_hour, 'blur', this.sethour.bindAsEventListener(this));
		addListener(this.clndr_minute, 'blur', this.setminute.bindAsEventListener(this));
		addListener(this.clndr_now, 'click', this.setNow.bindAsEventListener(this));
		addListener(this.clndr_done, 'click', this.setDone.bindAsEventListener(this));

		for (var i = 0; i < this.timeobjects.length; i++) {
			if (typeof(this.timeobjects[i]) != 'undefined' && !empty(this.timeobjects[i])) {
				addListener(this.timeobjects[i], 'change', this.setSDateFromOuterObj.bindAsEventListener(this));
			}
		}

		if ('undefined' != typeof(stime) && !empty(stime)) {
			this.sdt.setTime(stime * 1000);
		}
		else {
			this.setSDateFromOuterObj();
		}

		this.cdt.setTime(this.sdt.getTime());
		this.cdt.setDate(1);
		this.syncBSDateBySDT();
		this.setCDate();

		utime_field_id = $(utime_field_id);
		if (!is_null(utime_field_id)) {
			this.clndr_utime_field = utime_field_id;
		}
	},

	ondateselected: function() {
		this.setDateToOuterObj();
		this.clndrhide();
		this.onselect(this.sdt.getTime());
	},

	onselect: function(time) {
		// place any function;
	},

	clndrhide: function(e) {
		if (typeof(e) != 'undefined') {
			cancelEvent(e);
		}
		this.clndr_calendar.hide();
		this.visible = 0;

		removeFromOverlaysStack(this.id.toString());
	},

	clndrshow: function(top, left, trigger_elmnt) {
		if (this.visible == 1) {
			this.clndrhide();
		}
		else {
			if (this.status) {
				this.setSDateFromOuterObj();
				this.cdt.setTime(this.sdt.getTime());
				this.cdt.setDate(1);
				this.syncBSDateBySDT();
				this.setCDate();
			}
			if ('undefined' != typeof(top) && 'undefined' != typeof(left)) {
				this.clndr_calendar.style.top = top + 'px';
				this.clndr_calendar.style.left = left + 'px';
			}
			this.clndr_calendar.show();
			this.visible = 1;

			overlayDialogueOnLoad(true, this.clndr_calendar);
			addToOverlaysStack(this.id, trigger_elmnt, 'clndr');
		}
	},

	checkOuterObj: function(timeobjects) {
		if ('undefined' != typeof(timeobjects) && !empty(timeobjects)) {
			if (is_array(timeobjects)) {
				this.timeobjects = timeobjects;
			}
			else {
				this.timeobjects.push(timeobjects);
			}
		}
		else {
			return false;
		}

		for (var i = 0; i < this.timeobjects.length; i++) {
			if ('undefined' != this.timeobjects[i] && !empty(this.timeobjects[i])) {
				this.timeobjects[i] = $(this.timeobjects[i]);
				if (empty(this.timeobjects[i])) {
					return false;
				}
			}
		}
		return true;
	},

	setSDateFromOuterObj: function() {
		switch (this.timeobjects.length) {
			case 1:
				var val = null;
				var result = false;

				if (this.timeobjects[0].tagName.toLowerCase() === 'input') {
					val = this.timeobjects[0].value;
				}
				else {
					val = (IE) ? this.timeobjects[0].innerText : this.timeobjects[0].textContent;
				}

				// allow unix timestamp 0 (year 1970)
				if (jQuery(this.timeobjects[0]).attr('data-timestamp') >= 0) {
					this.setNow(jQuery(this.timeobjects[0]).attr('data-timestamp'));
				}
				else {
					if (is_string(val)) {
						var datetime = val.split(' ');
						var date = datetime[0].split('.');
						var time = new Array();

						if (datetime.length > 1) {
							var time = datetime[1].split(':');
						}
						if (date.length == 3) {
							result = this.setSDateDMY(date[0], date[1], date[2]);
							if (time.length == 2) {
								if (time[0] > -1 && time[0] < 24) {
									this.sdt.setHours(time[0]);
								}
								if (time[1] > -1 && time[1] < 60) {
									this.sdt.setMinutes(time[1]);
								}
							}
						}
					}
				}

				if (!result) {
					return false;
				}
				break;
			case 3:
			case 5:
				var val = new Array();
				var result = true;

				for (var i = 0; i < this.timeobjects.length; i++) {
					if ('undefined' != this.timeobjects[i] && !empty(this.timeobjects[i])) {
						if (this.timeobjects[i].tagName.toLowerCase() == 'input') {
							val[i] = this.timeobjects[i].value;
						}
						else {
							val[i] = (IE) ? this.timeobjects[i].innerText : this.timeobjects[i].textContent;
						}
					}
					else {
						result = false;
					}
				}

				if (result) {
					result = this.setSDateDMY(val[0], val[1], val[2]);
					if (val.length > 4) {
						val[3] = parseInt(val[3], 10);
						val[4] = parseInt(val[4], 10);
						if (val[3] > -1 && val[3] < 24) {
							this.sdt.setHours(val[3]);
							result = true;
						}
						if (val[4] > -1 && val[4] < 60) {
							this.sdt.setMinutes(val[4]);
							result = true;
						}
						this.sdt.setSeconds(0);
					}
				}
				if (!result) {
					return false;
				}
				break;
			default:
				return false;
		}

		if (!is_null(this.clndr_utime_field)) {
			this.clndr_utime_field.value = this.sdt.getZBXDate();
		}
		return true;
	},

	setSDateDMY: function(d, m, y) {
		var dateHolder = new Date(y, m - 1, d, 0, 0, 0);

		if (y >= 1970 && dateHolder.getFullYear() == y && dateHolder.getMonth() == m - 1 && dateHolder.getDate() == d) {
			this.sdt.setTimeObject(y, m - 1, d);
			return true;
		}

		return false;
	},

	setDateToOuterObj: function() {
		switch (this.timeobjects.length) {
			case 1:
				// uses default format
				var date = this.sdt.format();

				if (this.timeobjects[0].tagName.toLowerCase() === 'input') {
					this.timeobjects[0].value = date;
				}
				else {
					if (IE) {
						this.timeobjects[0].innerText =  date;
					}
					else {
						this.timeobjects[0].textContent = date;
					}
				}
				break;

			case 3:
			case 5:
				// custom date format for input fields
				var date = this.sdt.format('d m Y H i').split(' ');

				for (var i = 0; i < this.timeobjects.length; i++) {
					if (this.timeobjects[i].tagName.toLowerCase() === 'input') {
						this.timeobjects[i].value = date[i];
					}
					else {
						if (IE) {
							this.timeobjects[i].innerText = date[i];
						}
						else {
							this.timeobjects[i].textContent = date[i];
						}
					}
				}
				break;
		}

		if (!is_null(this.clndr_utime_field)) {
			this.clndr_utime_field.value = this.sdt.getZBXDate();
		}
	},

	setNow: function(timestamp) {
		var now = (isNaN(timestamp)) ? new CDate() : new CDate(timestamp * 1000);
		this.day = now.getDate();
		this.month = now.getMonth();
		this.year = now.getFullYear();
		this.hour = now.getHours();
		this.minute = now.getMinutes();
		this.syncSDT();
		this.syncBSDateBySDT();
		this.syncCDT();
		this.setCDate();
	},

	setDone: function() {
		this.syncBSDateBySDT();
		this.ondateselected();
	},

	setminute: function() {
		var minute = parseInt(this.clndr_minute.value, 10);
		if (minute > -1 && minute < 60) {
			this.minute = minute;
			this.syncSDT();
		}
		else {
			this.clndr_minute.value = this.minute;
		}
	},

	sethour: function() {
		var hour = parseInt(this.clndr_hour.value, 10);
		if (hour > -1 && hour < 24) {
			this.hour = hour;
			this.syncSDT();
		}
		else {
			this.clndr_hour.value = this.hour;
		}
	},

	setday: function(e, day, month, year) {
		if (!is_null(this.clndr_selectedday)) {
			this.clndr_selectedday.removeClassName('selected');
		}
		var selectedday = Event.element(e);
		Element.extend(selectedday);

		this.clndr_selectedday = selectedday;
		this.clndr_selectedday.addClassName('selected');
		this.day = day;
		this.month = month;
		this.year = year;
		this.syncSDT();
		this.syncBSDateBySDT();
		this.syncCDT();
		this.setCDate();
	},

	monthup: function() {
		this.month++;

		if (this.month > 11) {
			// prevent months from running in loop in year 2038
			if (this.year < 2038) {
				this.month = 0;
				this.yearup();
			}
			else {
				this.month = 11;
			}
		}
		else {
			this.syncCDT();
			this.setCDate();
		}
	},

	monthdown: function() {
		this.month--;

		if (this.month < 0) {
			// prevent months from running in loop in year 1970
			if (this.year > 1970) {
				this.month = 11;
				this.yeardown();
			}
			else {
				this.month = 0;
			}
		}
		else {
			this.syncCDT();
			this.setCDate();
		}
	},

	yearup: function() {
		if (this.year >= 2038) {
			return ;
		}
		this.year++;
		this.syncCDT();
		this.setCDate();
	},

	yeardown: function() {
		if (this.year <= 1970) {
			return ;
		}
		this.year--;
		this.syncCDT();
		this.setCDate();
	},

	syncBSDateBySDT: function() {
		this.minute = this.sdt.getMinutes();
		this.hour = this.sdt.getHours();
		this.day = this.sdt.getDate();
		this.month = this.sdt.getMonth();
		this.year = this.sdt.getFullYear();
	},

	syncSDT: function() {
		this.sdt.setTimeObject(this.year, this.month, this.day, this.hour, this.minute);
	},

	syncCDT: function() {
		this.cdt.setTimeObject(this.year, this.month, 1, this.hour, this.minute);
	},

	setCDate: function() {
		this.clndr_minute.value = this.minute;
		this.clndr_minute.onchange();
		this.clndr_hour.value = this.hour;
		this.clndr_hour.onchange();
		this.clndr_month.textContent = this.monthname[this.month];
		this.clndr_year.textContent = this.year;
		this.createDaysTab();
	},

	createDaysTab: function() {
		var tbody = this.clndr_days;
		tbody.update('');

		var cur_month = this.cdt.getMonth();

		// make 0 - Monday, not Sunday (as default)
		var prev_days = this.cdt.getDay() - 1;
		if (prev_days < 0) {
			prev_days = 6;
		}
		if (prev_days > 0) {
			this.cdt.setTime(this.cdt.getTime() - prev_days * 86400000);
		}

		for (var y = 0; y < 6; y++) {
			var tr = document.createElement('tr');
			tbody.appendChild(tr);
			for (var x = 0; x < 7; x++) {
				var td = document.createElement('td');
				tr.appendChild(td);
				Element.extend(td);

				if (cur_month != this.cdt.getMonth()) {
					td.addClassName('grey');
				}

				if (this.sdt.getFullYear() == this.cdt.getFullYear()
						&& this.sdt.getMonth() == this.cdt.getMonth()
						&& this.sdt.getDate() == this.cdt.getDate()) {
					td.addClassName('selected');
					this.clndr_selectedday = td;
				}

				addListener(td, 'click', this.setday.bindAsEventListener(this, this.cdt.getDate(), this.cdt.getMonth(), this.cdt.getFullYear()));
				td.appendChild(document.createTextNode(this.cdt.getDate()));
				this.cdt.setTime(this.cdt.getTime() + 86400000); // + 1day
			}
		}
	},

	calendarcreate: function(parentNodeid) {
		this.clndr_calendar = document.createElement('div');
		Element.extend(this.clndr_calendar);
		this.clndr_calendar.className = 'overlay-dialogue calendar';
		this.clndr_calendar.hide();

		if (typeof(parentNodeid) === 'undefined' || !parentNodeid) {
			document.body.appendChild(this.clndr_calendar);
		}
		else {
			$(parentNodeid).appendChild(this.clndr_calendar);
		}

		/*
		 * Calendar header
		 */
		var header = document.createElement('div');
		this.clndr_calendar.appendChild(header);
		header.className = 'calendar-header';

		//  year
		var year_div = document.createElement('div');
		year_div.className = 'calendar-year';
		header.appendChild(year_div);

		var arrow_left = document.createElement('span');
		arrow_left.className = 'arrow-left';
		var arrow_right = document.createElement('span');
		arrow_right.className = 'arrow-right';

		this.clndr_yeardown = document.createElement('button');
		this.clndr_yeardown.setAttribute('type', 'button');
		this.clndr_yeardown.className = 'btn-grey';
		this.clndr_yeardown.appendChild(arrow_left);
		year_div.appendChild(this.clndr_yeardown);

		this.clndr_year = document.createTextNode('');
		year_div.appendChild(this.clndr_year);

		this.clndr_yearup = document.createElement('button');
		this.clndr_yearup.setAttribute('type', 'button');
		this.clndr_yearup.className = 'btn-grey';
		this.clndr_yearup.appendChild(arrow_right);
		year_div.appendChild(this.clndr_yearup);

		// month
		var month_div = document.createElement('div');
		month_div.className = 'calendar-month';
		header.appendChild(month_div);

		var arrow_left = document.createElement('span');
		arrow_left.className = 'arrow-left';
		var arrow_right = document.createElement('span');
		arrow_right.className = 'arrow-right';

		this.clndr_monthdown = document.createElement('button');
		this.clndr_monthdown.setAttribute('type', 'button');
		this.clndr_monthdown.className = 'btn-grey';
		this.clndr_monthdown.appendChild(arrow_left);
		month_div.appendChild(this.clndr_monthdown);

		this.clndr_month = document.createTextNode('');
		month_div.appendChild(this.clndr_month);

		this.clndr_monthup = document.createElement('button');
		this.clndr_monthup.setAttribute('type', 'button');
		this.clndr_monthup.className = 'btn-grey';
		this.clndr_monthup.appendChild(arrow_right);
		month_div.appendChild(this.clndr_monthup);

		// days heading
		var table = document.createElement('table');
		this.clndr_calendar.appendChild(table);

		var thead = document.createElement('thead');
		table.appendChild(thead);

		var tr = document.createElement('tr');
		thead.appendChild(tr);

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_MONDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_TUESDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_WEDNESDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_THURSDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_FRIDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_SATURDAY_SHORT_BIG']));

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_SUNDAY_SHORT_BIG']));

		/*
		 * Days calendar
		 */
		this.clndr_days = document.createElement('tbody');
		Element.extend(this.clndr_days);
		table.appendChild(this.clndr_days);

		/*
		 * Hours & minutes
		 */
		var line_div = document.createElement('div');
		line_div.className = 'calendar-time';

		// hour
		this.clndr_hour = document.createElement('input');
		this.clndr_hour.setAttribute('type', 'text');
		this.clndr_hour.setAttribute('name', 'hour');
		this.clndr_hour.setAttribute('value', 'hh');
		this.clndr_hour.setAttribute('maxlength', '2');
		this.clndr_hour.onchange = function() { validateDatePartBox(this, 0, 23, 2); };
		this.clndr_hour.className = 'calendar_textbox';

		// minutes
		this.clndr_minute = document.createElement('input');
		this.clndr_minute.setAttribute('type', 'text');
		this.clndr_minute.setAttribute('name', 'minute');
		this.clndr_minute.setAttribute('value', 'mm');
		this.clndr_minute.setAttribute('maxlength', '2');
		this.clndr_minute.onchange = function() { validateDatePartBox(this, 0, 59, 2); };
		this.clndr_minute.className = 'calendar_textbox';

		line_div.appendChild(document.createTextNode(locale['S_TIME'] + " "));
		line_div.appendChild(this.clndr_hour);
		line_div.appendChild(document.createTextNode(' : '));
		line_div.appendChild(this.clndr_minute);
		this.clndr_calendar.appendChild(line_div);

		/*
		 * Footer
		 */
		var line_div = document.createElement('div');
		line_div.className = 'calendar-footer';

		// now
		this.clndr_now = document.createElement('button');
		this.clndr_now.className = 'btn-grey';
		this.clndr_now.setAttribute('type', 'button');
		this.clndr_now.setAttribute('value', locale['S_NOW']);
		this.clndr_now.appendChild(document.createTextNode(locale['S_NOW']));
		line_div.appendChild(this.clndr_now);

		// done
		this.clndr_done = document.createElement('button');
		this.clndr_done.setAttribute('type', 'button');
		this.clndr_done.appendChild(document.createTextNode(locale['S_DONE']));
		line_div.appendChild(this.clndr_done);
		this.clndr_calendar.appendChild(line_div);
	}
}
