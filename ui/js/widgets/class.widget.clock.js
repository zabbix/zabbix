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

	_init() {
		super._init();

		this._time_offset = 0;
		this._interval_id = null;

		this._has_contents = false;
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

			if (response.clock_data.time !== null) {
				this._time_offset = now.getTime() - response.clock_data.time * 1000;
			}

			if (response.clock_data.time_zone_offset !== null) {
				this._time_offset -= (now.getTimezoneOffset() * 60 + response.clock_data.time_zone_offset) * 1000;
			}

			this._startClock();
		}
		else {
			this._time = null;
			this._time_zone_offset = null;
			this._has_contents = false;
		}
	}

	_startClock() {
		if (this._has_contents) {
			this._interval_id = setInterval(() => this._clockHandsRotate(), 1000);
			this._clockHandsRotate();
		}
	}

	_stopClock() {
		if (this._interval_id !== null) {
			clearTimeout(this._interval_id);
			this._interval_id = null;
		}
	}

	_clockHandsRotate() {
		const clock_svg = this._target.querySelector('.clock-svg');

		if (clock_svg !== null) {
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
	}

	_clockHandRotate(clock_hand, degree) {
		clock_hand.setAttribute('transform', `rotate(${degree} 50 50)`);
	}
}
