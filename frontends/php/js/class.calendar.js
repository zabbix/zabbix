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
	clndr_month_div: null,		// html obj
	clndr_year_div: null,		// html obj
	clndr_minute: null,			// html from obj
	clndr_hour: null,			// html from obj
	clndr_days: null,			// html obj
	clndr_month: null,			// html obj
	clndr_year: null,			// html obj
	clndr_selectedday: null,	// html obj, selected day
	clndr_monthup: null,		// html bttn obj
	clndr_monthdown: null,		// html bttn obj
	clndr_yearup: null,			// html bttn obj
	clndr_year_wrap: null,		// html obj
	clndr_month_wrap: null,		// html obj
	clndr_yeardown: null,		// html bttn obj
	clndr_now: null,			// html bttn obj
	clndr_done: null,			// html bttn obj
	clndr_utime_field: null,	// html obj where unix date representation is saved
	timeobjects: new Array(),	// object list where will be saved date
	status: false,				// status of timeobjects
	visible: 0,					// GMenu style state
	hl_month: null,				// highlighted month number
	hl_year: null,				// highlighted year number
	hl_day: null,				// highlighted days number
	active_section: null,		// Active calendar section. See 'sections' array. Default value set in method clndrshow.
	monthname: new Array(locale['S_JANUARY'], locale['S_FEBRUARY'], locale['S_MARCH'], locale['S_APRIL'], locale['S_MAY'], locale['S_JUNE'], locale['S_JULY'], locale['S_AUGUST'], locale['S_SEPTEMBER'], locale['S_OCTOBER'], locale['S_NOVEMBER'], locale['S_DECEMBER']),
	dayname: new Array(locale['S_SUNDAY'], locale['S_MONDAY'], locale['S_TUESDAY'], locale['S_WEDNESDAY'], locale['S_THURSDAY'], locale['S_FRIDAY'], locale['S_SATURDAY']),
	sections: new Array('.calendar-year', '.calendar-month', '.calendar-date', '.calendar-time > [name="hour"]', '.calendar-time > [name="minute"]', '.calendar-footer button:first', '.calendar-footer button:last'),

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

		var cal_obj = this;
		jQuery(this.sections).each(function(index, item) {
			jQuery(item, cal_obj.clndr_calendar)
				.attr({'tabindex': '0'})
				.on('click', function() {
					cal_obj.active_section = index;
					cal_obj.focusSection();
				});
		});
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
		jQuery(document).off('keydown', this.calendarKeyDownHandler);
	},

	clndrshow: function(top, left) {
		if (this.visible == 1) {
			this.clndrhide();
		}
		else {
			// Close all opened calendars.
			jQuery(CLNDR).each(function(i, cal) {
				if (cal.clndr.visible == 1 && cal.clndr.id != this.id) {
					cal.clndr.clndrhide();
				}
			});

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

			jQuery(document).on('keydown', {calandar: this}, this.calendarKeyDownHandler);
			this.active_section = 2;
			this.focusSection();
		}
	},

	calendarKeyDownHandler: function(event) {
		var cal = event.data.calandar,
			hl_date;

		if (cal.active_section < 0 || cal.active_section > cal.sections.length) {
			cal.active_section = 0;
		}

		switch (event.which) {
			case 37: // arrow left
			case 38: // arrow up
			case 39: // arrow right
			case 40: // arrow down
				event.preventDefault(); // Prevent page scrolling.

				switch (cal.sections[cal.active_section]) {
					case '.calendar-date':
						var change = true;
						if (cal.hl_month === null || cal.hl_day === null || cal.hl_year === null) {
							cal.hl_year = cal.year;
							cal.hl_month = cal.month;
							cal.hl_day = cal.day;
						}
						else if (cal.hl_year != cal.year || cal.hl_month != cal.month) {
							change = false;
							cal.hl_day = 1;
							cal.hl_year = cal.year;
							cal.hl_month = cal.month;
						}

						hl_date = new Date(cal.hl_year, cal.hl_month, cal.hl_day, 0, 0, 0, 0);

						if (change) {
							switch (event.which) {
								case 37: // arrow left
									hl_date.setDate(hl_date.getDate() - 1);
									break;

								case 38: // arrow up
									hl_date.setDate(hl_date.getDate() - 7);
									break;

								case 39: // arrow right
									hl_date.setDate(hl_date.getDate() + 1);
									break;

								case 40: // arrow down
									hl_date.setDate(hl_date.getDate() + 7);
									break;
							}
						}

						cal.hl_year = hl_date.getFullYear();
						cal.hl_month = hl_date.getMonth();
						cal.hl_day = hl_date.getDate();

						jQuery('td.highlighted', cal.clndr_calendar)
							.removeClass('highlighted')
							.attr('tabindex', '-1');

						if (cal.hl_year == cal.year && cal.hl_month == cal.month) {
							jQuery('td[data-date='+cal.hl_day+']', cal.clndr_calendar)
								.addClass('highlighted')
								.attr('tabindex', '0')
								.focus();
						}
						else {
							cal.year = cal.hl_year;
							cal.month = cal.hl_month;
							cal.day = cal.hl_day;
							cal.syncCDT();
							cal.setCDate();

							jQuery('td[data-date='+cal.hl_day+']', cal.clndr_calendar)
								.addClass('highlighted')
								.attr('tabindex', '0')
								.focus();
						}
						break;

					case '.calendar-year':
						if (event.which == 37 || event.which == 40) {
							cal.yeardown();
						}
						else if (event.which == 38 || event.which == 39) {
							cal.yearup();
						}
						break;

					case '.calendar-month':
						if (event.which == 37 || event.which == 40) {
							cal.monthdown();
						}
						else if (event.which == 38 || event.which == 39) {
							cal.monthup();
						}
						break;
				}
				break;

			case 9: // Tab
				event.preventDefault();

				if (event.shiftKey) {
					cal.active_section--;
					if (cal.active_section < 0) {
						cal.active_section = cal.sections.length - 1;
					}
				}
				else {
					cal.active_section++;
					if (cal.active_section >= cal.sections.length) {
						cal.active_section = 0;
					}
				}

				cal.focusSection();
				break;

			case 13: // Enter
				// Enter has special meaning for each Calendar section.
				var active_section = cal.sections[cal.active_section];
				if (active_section === '.calendar-year' ||  active_section === '.calendar-month') {
					cal.active_section++;
					cal.focusSection();
				}
				else if (jQuery.inArray(active_section, new Array('.calendar-date', '.calendar-time > [name="hour"]',
						'.calendar-time > [name="minute"]', '.calendar-footer button:last')) > -1) {
					cal.setday(event, cal.hl_day, cal.hl_month, cal.hl_year);
					cal.setDone();
				}
				break;

			case 32: // Space
				if (cal.sections[cal.active_section] === '.calendar-date') {
					cal.setday(event, cal.hl_day, cal.hl_month, cal.hl_year);
					cal.focusSection();
				}
				break;

			case 27: // ESC
				cal.clndrhide();
				break;
		}
	},

	focusSection: function() {
		var section_to_focus = this.sections[this.active_section];

		jQuery('.highlighted', this.clndr_calendar).removeClass('highlighted').blur();
		if (section_to_focus === '.calendar-year' ||  section_to_focus === '.calendar-month') {
			jQuery(section_to_focus, this.clndr_calendar).addClass('highlighted').focus();
		}
		else if (section_to_focus === '.calendar-date') {
			this.hl_year = this.hl_year || this.year;
			this.hl_month = this.hl_month || this.month;
			this.hl_day = this.hl_day || this.day;

			if (this.hl_year != this.year || this.hl_month != this.month) {
				this.hl_day = 1;
			}

			jQuery('td[data-date='+this.hl_day+']', this.clndr_calendar)
				.addClass('highlighted')
				.attr('tabindex', '0')
				.focus();
		}
		else {
			jQuery(section_to_focus, this.clndr_calendar).focus();
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
			this.clndr_selectedday.setAttribute('tabindex', '-1');
		}
		var selectedday = Event.element(e);
		if (selectedday.tagName === 'SPAN') {
			selectedday = selectedday.parentNode;
		}
		Element.extend(selectedday);

		this.clndr_selectedday = selectedday;
		this.clndr_selectedday.addClassName('selected');
		this.clndr_selectedday.setAttribute('tabindex', '0');
		this.day = day;
		this.month = month;
		this.year = year;
		this.hl_day = day;
		this.hl_month = month;
		this.hl_year = year;
		this.syncSDT();
		this.syncBSDateBySDT();
		this.syncCDT();
		this.setCDate();
		//this.clndr_selectedday.focus();
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
			tr.setAttribute('role', 'presentation');

			tbody.appendChild(tr);
			for (var x = 0; x < 7; x++) {
				var td = document.createElement('td');
				tr.appendChild(td);
				Element.extend(td);

				if (cur_month != this.cdt.getMonth()) {
					td.addClassName('grey');
				}
				else {
					td.setAttribute('data-date', this.cdt.getDate());
				}

				if (this.sdt.getFullYear() == this.cdt.getFullYear()
						&& this.sdt.getMonth() == this.cdt.getMonth()
						&& this.sdt.getDate() == this.cdt.getDate()) {
					td.addClassName('selected');
					this.clndr_selectedday = td;
				}

				td.setAttribute('aria-label', this.calendarGetReadableDate(this.cdt));
				td.setAttribute('tabindex', '-1');
				td.setAttribute('role', 'button');

				var span = document.createElement('span');
				span.setAttribute('aria-hidden', 'true');
				span.appendChild(document.createTextNode(this.cdt.getDate()));
				td.appendChild(span);

				addListener(td, 'click', this.setday.bindAsEventListener(this, this.cdt.getDate(), this.cdt.getMonth(), this.cdt.getFullYear()));
				this.cdt.setTime(this.cdt.getTime() + 86400000); // + 1day
			}
		}
	},

	calendarGetReadableDate: function(cdt) {
		return cdt.getDate() + ', ' + this.dayname[cdt.getDay()] + ' ' + this.monthname[cdt.getMonth()] + ' ' +
				cdt.getFullYear();
	},

	calendarcreate: function(parentNodeid) {
		this.clndr_calendar = document.createElement('div');
		Element.extend(this.clndr_calendar);
		this.clndr_calendar.className = 'overlay-dialogue calendar';
		this.clndr_calendar.setAttribute('aria-label', locale['S_Calendar']);
		this.clndr_calendar.setAttribute('aria-describedby', 'calendar-description');
		this.clndr_calendar.setAttribute('role', 'application');
		this.clndr_calendar.setAttribute('tabindex', '0');
		this.clndr_calendar.hide();

		if (typeof(parentNodeid) === 'undefined' || !parentNodeid) {
			document.body.appendChild(this.clndr_calendar);
		}
		else {
			$(parentNodeid).appendChild(this.clndr_calendar);
		}

		var infobox = document.createElement('div');
		infobox.setAttribute('id', 'calendar-description');
		infobox.innerHTML = locale['S_calendar_helptext'];
		infobox.style.display = 'none';
		this.clndr_calendar.appendChild(infobox);

		/*
		 * Calendar header
		 */
		var header = document.createElement('div');
		this.clndr_calendar.appendChild(header);
		header.className = 'calendar-header';

		//  year
		this.clndr_year_div = document.createElement('div');
		this.clndr_year_div.setAttribute('role', 'presentation');
		this.clndr_year_div.className = 'calendar-year';
		header.appendChild(this.clndr_year_div);

		var arrow_left = document.createElement('span');
		arrow_left.className = 'arrow-left';
		var arrow_right = document.createElement('span');
		arrow_right.className = 'arrow-right';

		this.clndr_yeardown = document.createElement('button');
		this.clndr_yeardown.setAttribute('type', 'button');
		this.clndr_yeardown.setAttribute('tabindex', '-1');
		this.clndr_yeardown.className = 'btn-grey';
		this.clndr_yeardown.appendChild(arrow_left);
		this.clndr_year_div.appendChild(this.clndr_yeardown);

		this.clndr_year = document.createTextNode('');

		this.clndr_year_wrap = document.createElement('span');
		this.clndr_year_wrap.appendChild(this.clndr_year);
		this.clndr_year_wrap.setAttribute('aria-live', 'assertive');
		this.clndr_year_wrap.setAttribute('id', 'current-year'+this.id);
		this.clndr_year_div.appendChild(this.clndr_year_wrap);
		this.clndr_year_div.setAttribute('aria-labelledby', this.clndr_year_wrap.id);

		this.clndr_yearup = document.createElement('button');
		this.clndr_yearup.setAttribute('type', 'button');
		this.clndr_yearup.setAttribute('tabindex', '-1');
		this.clndr_yearup.className = 'btn-grey';
		this.clndr_yearup.appendChild(arrow_right);
		this.clndr_year_div.appendChild(this.clndr_yearup);

		// month
		this.clndr_month_div = document.createElement('div');
		this.clndr_month_div.className = 'calendar-month';
		this.clndr_month_div.setAttribute('role', 'presentation');
		header.appendChild(this.clndr_month_div);

		var arrow_left = document.createElement('span');
		arrow_left.className = 'arrow-left';
		var arrow_right = document.createElement('span');
		arrow_right.className = 'arrow-right';

		this.clndr_monthdown = document.createElement('button');
		this.clndr_monthdown.setAttribute('type', 'button');
		this.clndr_monthdown.setAttribute('tabindex', '-1');
		this.clndr_monthdown.className = 'btn-grey';
		this.clndr_monthdown.appendChild(arrow_left);
		this.clndr_month_div.appendChild(this.clndr_monthdown);

		this.clndr_month = document.createTextNode('');
		this.clndr_month_wrap = document.createElement('span');
		this.clndr_month_wrap.setAttribute('aria-live', 'assertive');
		this.clndr_month_wrap.setAttribute('id', 'current-month'+this.id);
		this.clndr_month_wrap.appendChild(this.clndr_month);
		this.clndr_month_div.appendChild(this.clndr_month_wrap);
		this.clndr_month_div.setAttribute('aria-labelledby', this.clndr_month_wrap.id);

		this.clndr_monthup = document.createElement('button');
		this.clndr_monthup.setAttribute('type', 'button');
		this.clndr_monthup.setAttribute('tabindex', '-1');
		this.clndr_monthup.className = 'btn-grey';
		this.clndr_monthup.appendChild(arrow_right);
		this.clndr_month_div.appendChild(this.clndr_monthup);

		// days heading
		var table = document.createElement('table');
		this.clndr_calendar.appendChild(table);

		var thead = document.createElement('thead');
		thead.setAttribute('role', 'presentation');
		table.appendChild(thead);

		var tr = document.createElement('tr');
		thead.appendChild(tr);

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_MONDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_TUESDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_WEDNESDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_THURSDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_FRIDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_SATURDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		var td = document.createElement('th');
		tr.appendChild(td);
		td.appendChild(document.createTextNode(locale['S_SUNDAY_SHORT_BIG']));
		td.setAttribute('tabindex', '-1');

		/*
		 * Days calendar
		 */
		this.clndr_days = document.createElement('tbody');
		Element.extend(this.clndr_days);
		this.clndr_days.setAttribute('class', 'calendar-date');
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
		this.clndr_hour.setAttribute('aria-label', locale['S_hour']);
		this.clndr_hour.onchange = function() { validateDatePartBox(this, 0, 23, 2); };
		this.clndr_hour.className = 'calendar_textbox';

		// minutes
		this.clndr_minute = document.createElement('input');
		this.clndr_minute.setAttribute('type', 'text');
		this.clndr_minute.setAttribute('name', 'minute');
		this.clndr_minute.setAttribute('value', 'mm');
		this.clndr_minute.setAttribute('maxlength', '2');
		this.clndr_minute.setAttribute('aria-label', locale['S_minute']);
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
