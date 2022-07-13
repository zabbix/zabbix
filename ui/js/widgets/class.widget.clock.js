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

	static DEFAULT_LOCALE = 'en-US';

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

	_registerEvents() {
		super._registerEvents();

		this._events.resize = () => {
			const padding = 25;
			const header_height = this._view_mode == ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
				? 0
				: this._content_header.offsetHeight;

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
			this._is_enabled = response.clock_data.is_enabled;
			this._time_offset = 0;

			this._clock_type = response.clock_data.type;

			const now = new Date();

			if (response.clock_data.time !== null) {
				this._time_offset = now.getTime() - response.clock_data.time * 1000;
			}

			if (response.clock_data.time_zone_offset !== null) {
				this._time_offset -= (now.getTimezoneOffset() * 60 + response.clock_data.time_zone_offset) * 1000;
			}

			if (this._clock_type === CWidgetClock.TYPE_DIGITAL) {
				this._date = response.clock_data.date;
				this._time_zone = response.clock_data.time_zone;
				this._show_seconds = response.clock_data.seconds;
				this._time_format = response.clock_data.time_format;
				this._tzone_format = response.clock_data.tzone_format;
				this._show = response.clock_data.show;
			}

			this._startClock();
		}
		else {
			this._has_contents = false;
			this._is_enabled = false;
			this._time_offset = null;
		}
	}

	_startClock() {
		if (!this._is_enabled || !this._has_contents) {
			return;
		}

		switch (this._clock_type) {
			case CWidgetClock.TYPE_ANALOG:
				this._interval_id = setInterval(() => this._clockAnalogUpdate(), 1000);
				this._clockAnalogUpdate();
				break;

			case CWidgetClock.TYPE_DIGITAL:
				if (this._show.includes(CWidgetClock.SHOW_DATE)) {
					this._fillDate();
				}

				if (this._show.includes(CWidgetClock.SHOW_TIMEZONE)) {
					this._fillTimeZone();
				}

				this._interval_id = setInterval(() => this._clockDigitalUpdate(), 1000);
				this._clockDigitalUpdate();
				break;
		}
	}

	_stopClock() {
		if (this._interval_id !== null) {
			clearTimeout(this._interval_id);
			this._interval_id = null;
		}
	}

	_clockAnalogUpdate() {
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

	_clockDigitalUpdate() {
		const clock_display = this._target.querySelector('.clock-time');

		if (clock_display === null) {
			return;
		}

		const now = new Date();
		now.setTime(now.getTime() - this._time_offset);

		let options = {
			hour: '2-digit',
			minute: '2-digit',
			hourCycle: this._time_format === CWidgetClock.HOUR_TWELVE ? 'h12' : 'h23'
		};
		let locale = navigator.language ?? CWidgetClock.DEFAULT_LOCALE;

		if (this._show_seconds) {
			options.second = '2-digit';
		}

		clock_display.textContent = now.toLocaleTimeString(locale, options);
	}

	_fillDate() {
		const clock_date = this._target.querySelector('.clock-date');

		if (clock_date === null) {
			return;
		}

		clock_date.textContent = this._date;
	}

	_fillTimeZone() {
		const clock_time_zone = this._target.querySelector('.clock-time-zone');

		if (clock_time_zone === null) {
			return;
		}

		let timezone_text = this._time_zone;

		if (this._time_zone === CWidgetClock.TIMEZONE_LOCAL) {
			const now = new Date();
			let time_zone = Intl.DateTimeFormat().resolvedOptions().timeZone;

			if (this._tzone_format === CWidgetClock.TIMEZONE_SHORT) {
				const pos = time_zone.lastIndexOf('/');

				if (pos !== -1) {
					time_zone = time_zone.substring(pos + 1);
				}
			}
			else {
				const offset = now.getTimezoneOffset();

				const hours = Math.floor(Math.abs(offset) / 60).toString().padStart(2, '0');
				const minutes = (Math.abs(offset) % 60).toString().padStart(2, '0');

				time_zone = `(UTC${offset > 0 ? '-' : '+'}${hours}:${minutes}) ${time_zone}`;
			}

			timezone_text = time_zone.replace(/_/g, ' ');
		}

		clock_time_zone.textContent = timezone_text;
	}
}
