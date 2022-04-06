// JavaScript Document
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


var CLNDR = null,
	calendar = function (timeobject, trigger_elmnt, date_time_format) {
		if (!this.checkOuterObj(timeobject)) {
			throw 'Calendar: constructor expects second parameter to be input form field DOM node.';
		}

		this.id = jQuery(trigger_elmnt).attr('id');
		this.trigger_elmnt = trigger_elmnt;
		this.date_time_format = date_time_format;
		this.sdt = new CDate();
	};

function toggleCalendar(trigger_elmnt, time_input, date_time_format) {
	if (CLNDR && jQuery(trigger_elmnt).is(CLNDR.trigger_elmnt) && CLNDR.is_visible) {
		CLNDR.clndrhide();
	}
	else {
		CLNDR && CLNDR.clndrhide();
		CLNDR = new calendar(time_input, trigger_elmnt, date_time_format);
		CLNDR.clndrshow();
	}
}

calendar.prototype = {
	id: null,					// Calendar ID. Should be unique on page.
	sdt: null,					// Date object of a selected date.
	month: 0,					// Represents currently opened month number.
	year: 2008,					// Represents currently opened year.
	day: 1,						// Represents currently opened day.
	clndr_calendar: null,		// html obj of calendar
	clndr_month_div: null,		// html obj
	clndr_year_div: null,		// html obj
	clndr_days: null,			// html obj
	clndr_month: null,			// html obj
	clndr_year: null,			// html obj
	clndr_year_wrap: null,		// html obj
	clndr_month_wrap: null,		// html obj
	clndr_monthup: null,		// html bttn obj
	clndr_monthdown: null,		// html bttn obj
	clndr_yearup: null,			// html bttn obj
	clndr_yeardown: null,		// html bttn obj
	timeobject: null,			// Input field with selected time. Source and destination of selected date.
	is_visible: false,			// State of calendar visibility.
	has_user_time: false,		// Confirms, if time was selected from input field.
	hl_month: null,				// highlighted month number
	hl_year: null,				// highlighted year number
	hl_day: null,				// highlighted days number
	active_section: null,		// Active calendar section. See 'sections' array. Default value set in method clndrshow.
	monthname: new Array(t('S_JANUARY'), t('S_FEBRUARY'), t('S_MARCH'), t('S_APRIL'), t('S_MAY'), t('S_JUNE'),
		t('S_JULY'), t('S_AUGUST'), t('S_SEPTEMBER'), t('S_OCTOBER'), t('S_NOVEMBER'), t('S_DECEMBER')
	),
	dayname: new Array(t('S_SUNDAY'), t('S_MONDAY'), t('S_TUESDAY'), t('S_WEDNESDAY'), t('S_THURSDAY'), t('S_FRIDAY'),
		t('S_SATURDAY')
	),
	sections: new Array('.calendar-year', '.calendar-month', '.calendar-date'),
	date_time_format: PHP_ZBX_FULL_DATE_TIME,
	trigger_elmnt: null,		// Calendar visibility trigger element.

	ondateselected: function() {
		this.setDateToOuterObj();
		this.clndrhide();
	},

	clndrhide: function() {
		if (this.is_visible) {
			this.calendardelete();
			this.is_visible = false;

			jQuery(window).off('resize', this.calendarPositionHandler);

			jQuery(document)
				.off('click', this.calendarDocumentClickHandler)
				.off('keydown', this.calendarKeyDownHandler)
				.off('keyup', this.calendarKeyUpHandler);

			removeFromOverlaysStack(this.id);
		}
	},

	clndrshow: function() {
		this.calendarcreate();
		this.setSDateFromOuterObj();
		this.syncBSDateBySDT();
		this.syncHlDate();
		this.setCDate();

		this.calendarPositionHandler();
		this.clndr_calendar.style.display = (this.clndr_calendar.tagName === 'span') ? 'inline' : 'block';
		this.is_visible = true;

		jQuery(window).on('resize', jQuery.proxy(this.calendarPositionHandler, this));
		jQuery(this.trigger_elmnt).on('remove', jQuery.proxy(this.clndrhide, this));

		jQuery(document)
			.on('keydown', jQuery.proxy(this.calendarKeyDownHandler, this))
			.on('keyup', jQuery.proxy(this.calendarKeyUpHandler, this))
			.on('click', jQuery.proxy(this.calendarDocumentClickHandler, this));

		addToOverlaysStack(this.id, this.trigger_elmnt, 'clndr');

		this.active_section = this.sections.indexOf('.calendar-date');
		this.focusSection();
	},

	setPosition: function(top, left) {
		jQuery(this.clndr_calendar).css({
			top: Math.min(top, jQuery(window).height() - jQuery(this.clndr_calendar).outerHeight()) + 'px',
			left: Math.min(left, jQuery(window).width() - jQuery(this.clndr_calendar).outerWidth()) + 'px'
		});
	},

	calendarDocumentClickHandler: function(e) {
		var $target = jQuery(e.target);

		if (!$target.is(this.trigger_elmnt) && !$target.closest('.overlay-dialogue.calendar').length) {
			this.clndrhide();
		}
	},

	calendarPositionHandler: function () {
		var $anchor = jQuery(this.trigger_elmnt),
			offset = $anchor.offset();

		this.setPosition(offset.top + $anchor.height(), offset.left + $anchor.width());
	},

	/**
	 * This function is workaround for Firefox bug.
	 *
	 * When triggering keydown event on [space] button, event is called for both, the actual element as well as calendar
	 * icon elemnet, so the calendar is first closed (by handler of actually focused element) and immediately opened
	 * again (by calendar icon element's handler).
	 *
	 * Workaround works as follow - it separates [enter] and [space] button in 2 handlers with similar functionality
	 * (since pressing [space] and [enter] does the same thing in calendar). Keyup handles the [space] click event,
	 * while other keyboard events are handled by keydown event.
	 */
	calendarKeyUpHandler: function(event) {
		if (event.which == 32) { // Space
			// Enter has special meaning for each Calendar section.
			var active_section = this.sections[this.active_section];
			if (active_section === '.calendar-year' ||  active_section === '.calendar-month') {
				this.active_section++;
				this.focusSection();
			}
			else if (active_section === '.calendar-date') {
				this.setday(event, this.hl_day, this.hl_month, this.hl_year);
			}

			return false; // Prevent page scrolling when pressing Space.
		}
	},

	calendarKeyDownHandler: function(event) {
		var hl_date;

		if (this.active_section < 0 || this.active_section > this.sections.length) {
			this.active_section = 0;
		}

		switch (event.which) {
			case 37: // arrow left
			case 38: // arrow up
			case 39: // arrow right
			case 40: // arrow down
				switch (this.sections[this.active_section]) {
					case '.calendar-date':
						hl_date = new Date(this.hl_year, this.hl_month, this.hl_day, 0, 0, 0, 0);

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

						this.hl_year = hl_date.getFullYear();
						this.hl_month = hl_date.getMonth();
						this.hl_day = hl_date.getDate();

						jQuery('td.highlighted', this.clndr_calendar)
							.removeClass('highlighted')
							.attr('tabindex', '-1');

						if (this.hl_year != this.year || this.hl_month != this.month) {
							this.year = this.hl_year;
							this.month = this.hl_month;
							this.day = this.hl_day;
							this.setCDate();
						}

						jQuery('td[data-date='+this.hl_day+']', this.clndr_calendar)
							.addClass('highlighted')
							.attr('tabindex', '0')
							.focus();
						break;

					case '.calendar-year':
						// Arrow left or arrow down.
						if (event.which == 37 || event.which == 40) {
							this.yeardown();
						}
						// Arrow right or arrow up.
						else if (event.which == 38 || event.which == 39) {
							this.yearup();
						}
						break;

					case '.calendar-month':
						// Arrow left or arrow down.
						if (event.which == 37 || event.which == 40) {
							this.monthdown();
						}
						// Arrow right or arrow up.
						else if (event.which == 38 || event.which == 39) {
							this.monthup();
						}
						break;
				}

				// Prevent page scrolling.
				event.preventDefault();

				break;

			case 9: // Tab
				event.preventDefault();

				if (event.shiftKey) {
					this.active_section--;
					if (this.active_section < 0) {
						this.active_section = this.sections.length - 1;
					}
				}
				else {
					this.active_section++;
					if (this.active_section >= this.sections.length) {
						this.active_section = 0;
					}
				}

				this.focusSection();
				break;

			case 13: // Enter
				// Enter has special meaning for each Calendar section.
				var active_section = this.sections[this.active_section];
				if (active_section === '.calendar-year' ||  active_section === '.calendar-month') {
					this.active_section++;
					this.focusSection();
				}
				else if (active_section === '.calendar-date') {
					this.setday(event, this.hl_day, this.hl_month, this.hl_year);
				}

				return false;

			case 32: // Prevent page scrolling when pressing Space.
				return false;
		}
	},

	focusSection: function() {
		var section_to_focus = this.sections[this.active_section];

		jQuery('.highlighted', this.clndr_calendar).removeClass('highlighted').blur();
		if (section_to_focus === '.calendar-year' ||  section_to_focus === '.calendar-month') {
			jQuery(section_to_focus, this.clndr_calendar).addClass('highlighted').focus();
		}
		else if (section_to_focus === '.calendar-date') {
			/**
			 * Switching between months and years, date picker will highlight previously selected date. If
			 * selected date is in different year or month, the first date of displayed year is highleghted.
			 * Same happens also if the number of dates in selected month is smaller than selected date in different
			 * month.
			 */
			if (this.hl_year != this.year || this.hl_month != this.month
					|| new Date(this.year, this.month + 1, 0).getDate() < this.hl_day) {
				this.hl_day = 1;
			}

			jQuery('td[data-date='+this.hl_day+']', this.clndr_calendar)
				.addClass('highlighted')
				.attr('tabindex', '0')
				.focus();
		}
	},

	checkOuterObj: function(timeobject) {
		if (typeof(timeobject) === 'undefined' || empty(timeobject)) {
			return false;
		}

		this.timeobject = document.getElementById(timeobject);

		if (this.timeobject === null || this.timeobject.tagName.toLowerCase() !== 'input') {
			return false;
		}

		return true;
	},

	setSDateFromOuterObj: function() {
		var val = this.timeobject.value,
			/**
			 * Date and time format separators must be synced with ZBX_FULL_DATE_TIME, ZBX_DATE_TIME and ZBX_DATE in
			 * defines.inc.php.
			 */
			datetime = val.split(' '),
			date = datetime[0].split('-'),
			time = (datetime.length > 1) ? datetime[1].split(':') : new Array();

		// Open calendar with current time by default.
		this.sdt = new CDate();
		this.has_user_time = false;

		if (date.length === 3 && this.setSDateDMY(date[2], date[1], date[0])) {
			if (!time.length) {
				return;
			}

			this.sdt.setTimeObject(null, null, null, 0, 0, 0);

			// Set time to calendar, so time doesn't change when selecting different date.
			if ((time.length === 2 || time.length === 3)
					&& time[0] > -1 && time[0] < 24
					&& time[1] > -1 && time[1] < 60) {
				this.has_user_time = true;
				this.sdt.setHours(time[0]);
				this.sdt.setMinutes(time[1]);

				if (time.length === 3 && time[2] > -1 && time[2] < 60) {
					this.sdt.setSeconds(time[2]);
				}
			}
		}
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
		var $input = jQuery(this.timeobject),
			new_val = this.sdt.format(this.date_time_format);

		if ($input.val() != new_val) {
			$input.val(new_val).trigger('change');
		}
	},

	setday: function(e, day, month, year) {
		this.day = day;
		this.month = month;
		this.year = year;
		this.syncSDT();
		this.syncBSDateBySDT();
		this.ondateselected();
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
			this.setCDate();
		}

		this.hl_month = this.month;
		this.hl_year = this.year;
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
			this.setCDate();
		}

		this.hl_month = this.month;
		this.hl_year = this.year;
	},

	yearup: function() {
		if (this.year >= 2038) {
			return ;
		}
		this.year++;
		this.setCDate();
		this.hl_year = this.year;
	},

	yeardown: function() {
		if (this.year <= 1970) {
			return ;
		}
		this.year--;
		this.setCDate();
		this.hl_year = this.year;
	},

	syncBSDateBySDT: function() {
		this.day = this.sdt.getDate();
		this.month = this.sdt.getMonth();
		this.year = this.sdt.getFullYear();
	},

	syncHlDate: function() {
		this.hl_day = this.day;
		this.hl_month = this.month;
		this.hl_year = this.year;
	},

	syncSDT: function() {
		var hours = 0,
			minutes = 0,
			seconds = 0;

		if (this.has_user_time) {
			// If time was present in input field, use it.
			hours = this.sdt.getHours();
			minutes = this.sdt.getMinutes();
			seconds = this.sdt.getSeconds();
		}
		else {
			var cdt = new CDate();

			// If today was selected, use current time. Otherwise use 00:00:00.
			if (cdt.getFullYear() === this.year && cdt.getMonth() === this.month && cdt.getDate() === this.day) {
				hours = cdt.getHours();
				minutes = cdt.getMinutes();
				seconds = cdt.getSeconds();
			}
		}

		this.sdt.setTimeObject(this.year, this.month, this.day, hours, minutes, seconds);
	},

	setCDate: function() {
		this.clndr_month.textContent = this.monthname[this.month];
		this.clndr_year.textContent = this.year;
		this.createDaysTab();
	},

	createDaysTab: function() {
		var tbody = this.clndr_days;
		tbody.innerHTML = '';

		var cdt = new CDate();

		// Start drawing days from first week of the month.
		cdt.setTimeObject(this.year, this.month, 1);

		// make 0 - Monday, not Sunday (as default)
		var prev_days = cdt.getDay() - 1;
		if (prev_days < 0) {
			prev_days = 6;
		}

		// Set to first day of the week.
		if (prev_days > 0) {
			cdt.setTime(cdt.getTime() - prev_days * 86400000);
		}

		for (var y = 0; y < 6; y++) {
			var tr = document.createElement('tr');
			tr.setAttribute('role', 'presentation');

			tbody.appendChild(tr);
			for (var x = 0; x < 7; x++) {
				var td = document.createElement('td');
				tr.appendChild(td);

				if (this.month != cdt.getMonth()) {
					$(td).addClass('grey');
				}
				else {
					td.setAttribute('data-date', cdt.getDate());
				}

				if (this.sdt.getFullYear() == cdt.getFullYear()
						&& this.sdt.getMonth() == cdt.getMonth()
						&& this.sdt.getDate() == cdt.getDate()) {
					$(td).addClass('selected');
				}

				td.setAttribute('aria-label', this.calendarGetReadableDate(cdt));
				td.setAttribute('tabindex', '-1');
				td.setAttribute('role', 'button');

				var span = document.createElement('span');
				span.setAttribute('aria-hidden', 'true');
				span.appendChild(document.createTextNode(cdt.getDate()));
				td.appendChild(span);

				addListener(td, 'click', this.setday.bindAsEventListener(
					this, cdt.getDate(), cdt.getMonth(), cdt.getFullYear()
				));

				cdt.setTime(cdt.getTime() + 86400000); // + 1day
			}
		}
	},

	calendarGetReadableDate: function(cdt) {
		return cdt.getDate() + ', ' + this.dayname[cdt.getDay()] + ' ' + this.monthname[cdt.getMonth()] + ' ' +
				cdt.getFullYear();
	},
	/**
	 * Create and append calendar DOM element to body.
	 */
	calendarcreate: function() {
		this.clndr_calendar = document.createElement('div');
		this.clndr_calendar.className = 'overlay-dialogue calendar';
		this.clndr_calendar.setAttribute('aria-label', t('S_CALENDAR'));
		this.clndr_calendar.setAttribute('role', 'application');
		this.clndr_calendar.setAttribute('tabindex', '0');
		this.clndr_calendar.style.display = 'none';

		document.body.appendChild(this.clndr_calendar);

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
		this.clndr_year_wrap.setAttribute('aria-atomic', 'true');
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
		this.clndr_month_wrap.setAttribute('aria-atomic', 'true');
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

		[
			t('S_MONDAY_SHORT_BIG'),
			t('S_TUESDAY_SHORT_BIG'),
			t('S_WEDNESDAY_SHORT_BIG'),
			t('S_THURSDAY_SHORT_BIG'),
			t('S_FRIDAY_SHORT_BIG'),
			t('S_SATURDAY_SHORT_BIG'),
			t('S_SUNDAY_SHORT_BIG')
		].forEach(function(str) {
			var td = document.createElement('th');
			td.appendChild(document.createTextNode(str));
			tr.appendChild(td);
		});

		/*
		 * Days calendar
		 */
		this.clndr_days = document.createElement('tbody');
		this.clndr_days.setAttribute('class', 'calendar-date');
		table.appendChild(this.clndr_days);

		addListener(this.clndr_monthdown, 'click', this.monthdown.bindAsEventListener(this));
		addListener(this.clndr_monthup, 'click', this.monthup.bindAsEventListener(this));
		addListener(this.clndr_yeardown, 'click', this.yeardown.bindAsEventListener(this));
		addListener(this.clndr_yearup, 'click', this.yearup.bindAsEventListener(this));

		// Active section setter.
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
	/**
	 * Remove calendar DOM element. Detach event listeners.
	 */
	calendardelete: function() {
		removeListener(this.clndr_monthdown, 'click', this.monthdown);
		removeListener(this.clndr_monthup, 'click', this.monthup);
		removeListener(this.clndr_yeardown, 'click', this.yeardown);
		removeListener(this.clndr_yearup, 'click', this.yearup);
		jQuery(this.clndr_calendar).remove();
	}
};
