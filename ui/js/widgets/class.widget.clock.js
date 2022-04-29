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
**/


class CWidgetClock extends CWidget {

	static TYPE_ANALOG = 0;
	static TYPE_DIGITAL = 1;

	static HOUR_TWENTY_FOUR = 0;
	static HOUR_TWELVE = 1;

	static TIMEZONE_SHORT = 0;
	static TIMEZONE_FULL = 1;
	static TIMEZONE_LOCAL = 'local';

	static SHOW_DATE = 1;
	static SHOW_TIME = 2;
	static SHOW_TIMEZONE = 3;

	_init() {
		super._init();

		this._time_offset = 0;
		this._interval_id = null;
		this._clock_type = CWidgetClock.TYPE_ANALOG;
		this._time_zone = null;
		this._show_seconds = true;
		this._time_format = 0;
		this._tzone_format = 0;
		this._show = [];
		this._has_contents = false;
		this._is_enabled = true;
	}

	_initLocal() {
		if (this._show.includes(CWidgetClock.SHOW_DATE)) {
			this._fillDate();
		}

		if (this._show.includes(CWidgetClock.SHOW_TIMEZONE)) {
			this._fillTimeZone();
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events.resize = () => {
			const padding = 25;
			const header_height = this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 0 : 33;

			this._target.style.setProperty(
				'--content-height',
				`${this._cell_height * this._pos.height - padding * 2 - header_height}px`
			);
		}
	}

	_activateEvents() {
		super._activateEvents();

		this._resize_observer = new ResizeObserver(this._events.resize);
		this._resize_observer.observe(this._target);
	}

	_deactivateEvents() {
		super._deactivateEvents();

		this._resize_observer.disconnect();
	}

	_doActivate() {
		super._doActivate();

		this._startClock();
	}

	_doDeactivate() {
		super._doDeactivate();

		this._stopClock();
	}

	_processUpdateResponse(response) {
		super._processUpdateResponse(response);

		this._stopClock();

		if (response.clock_data !== undefined) {
			this._has_contents = true;
			this._time_offset = 0;

			const now = new Date();

			this._clock_type = response.clock_data.type;

			if (response.clock_data.time !== null) {
				this._time_offset = now.getTime() - response.clock_data.time * 1000;
			}

			if (response.clock_data.time_zone_offset !== null) {
				this._time_offset -= (now.getTimezoneOffset() * 60 + response.clock_data.time_zone_offset) * 1000;
			}

			if (this._clock_type === CWidgetClock.TYPE_DIGITAL) {
				this._time_zone = response.clock_data.time_zone;
				this._show_seconds = response.clock_data.seconds;
				this._time_format = response.clock_data.time_format;
				this._tzone_format = response.clock_data.tzone_format;
				this._show = response.clock_data.show;
				this._is_enabled = response.clock_data.is_enabled;

				if (this._time_zone === CWidgetClock.TIMEZONE_LOCAL) {
					this._initLocal();
				}
			}

			if (this._is_enabled) {
				this._startClock();
			}
		}
		else {
			this._time = null;
			this._time_zone_offset = null;
			this._has_contents = false;
		}
	}

	_startClock() {
		if (!this._has_contents) {
			return;
		}

		if (this._clock_type === CWidgetClock.TYPE_ANALOG) {
			this._interval_id = setInterval(() => this._clockHandsRotate(), 1000);
			this._clockHandsRotate();
		}
		else if (this._show.includes(CWidgetClock.SHOW_TIME)) {
			this._interval_id = setInterval(() => this._clockDisplayUpdate(), 1000);
			this._clockDisplayUpdate();
		}
	}

	_stopClock() {
		if (this._interval_id !== null) {
			clearTimeout(this._interval_id);
			this._interval_id = null;
		}
	}

	_clockDisplayUpdate() {
		const clock_display = this._target.querySelector('.clock-time');

		if (clock_display === null) {
			return;
		}

		const now = new Date();
		now.setTime(now.getTime() - this._time_offset);

		let options = {hour: '2-digit', minute: '2-digit', hour12: false};
		let locale = 'en-US';

		if (this._time_format === CWidgetClock.HOUR_TWELVE) {
			options['hour12'] = true;
			locale = navigator.language ?? 'en-US';
		}

		if (this._show_seconds) {
			options.second = '2-digit';
		}

		let time = now.toLocaleTimeString(locale, options);

		if (this._is_enabled) {
			clock_display.innerHTML = time;
		}
	}

	_clockHandsRotate() {
		const clock_svg = this._target.querySelector('.clock-svg');

		if (clock_svg === null) {
			return;
		}

		const now = new Date();
		now.setTime(now.getTime() - this._time_offset);

		let h = now.getHours() % 12;
		let m = now.getMinutes();
		let s = now.getSeconds();

		if (clock_svg !== null && !clock_svg.classList.contains('disabled')) {
			this._clockHandRotate(clock_svg.querySelector('.clock-hand-h'), 30 * (h + m / 60 + s / 3600));
			this._clockHandRotate(clock_svg.querySelector('.clock-hand-m'), 6 * (m + s / 60));
			this._clockHandRotate(clock_svg.querySelector('.clock-hand-s'), 6 * s);
		}
	}

	_clockHandRotate(clock_hand, degree) {
		clock_hand.setAttribute('transform', `rotate(${degree} 50 50)`);
	}

	_fillDate() {
		const date_display = this._target.querySelector('.clock-date');

		if (!date_display) {
			return;
		}

		const now = new Date();
		let year = now
			.getFullYear()
			.toString();
		let month = (now.getMonth() + 1)
			.toString()
			.padStart(2, 0);
		let day = now
			.getDate()
			.toString()
			.padStart(2, 0);

		if (this._is_enabled) {
			date_display.innerText = `${year}/${month}/${day}`;
		}
	}

	_fillTimeZone() {
		const tzone_display = this._target.querySelector('.clock-time-zone');

		if (!tzone_display) {
			return;
		}

		const now = new Date();
		let time_zone = Intl
			.DateTimeFormat()
			.resolvedOptions()
			.timeZone;

		if (this._tzone_format === CWidgetClock.TIMEZONE_SHORT) {
			let pos = time_zone.lastIndexOf('/');

			if (pos !== -1) {
				time_zone = time_zone.substring(pos + 1);
			}
		}
		else {
			let offset = now.getTimezoneOffset();
			let offset_hours = (offset > 0 ? '(UTC-' : '(UTC+');

			offset = Math.abs(offset);
			offset_hours += Math.floor(offset / 60).toString().padStart(2, 0)
				+ ':' + (offset % 60).toString().padStart(2, 0) + ')';

			time_zone = `${offset_hours} ${time_zone}`;
		}

		time_zone = time_zone.replace(/_/g, ' ');

		if (this._is_enabled) {
			tzone_display.innerText = time_zone;
		}
	}
}
