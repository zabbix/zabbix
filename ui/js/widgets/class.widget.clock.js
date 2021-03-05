/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

		this._time = null;
		this._time_zone_offset = null;
		this._is_clock_active = false;
		this._interval_id = null;
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
			this._time = response.clock_data.time;
			this._time_zone_offset = response.clock_data.time_zone_offset;
			this._is_clock_active = true;

			this._startClock();
		}
		else {
			this._time = null;
			this._time_zone_offset = null;
			this._is_clock_active = false;
		}
	}

	_startClock() {
		if (this._is_clock_active) {
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
		const now = new Date();

		let time_offset = 0;

		if (this._time !== null) {
			time_offset = now.getTime() - this._time * 1000;
		}

		if (this._time_zone_offset !== null) {
			time_offset -= (now.getTimezoneOffset() * 60 + this._time_zone_offset) * 1000;
		}

		now.setTime(now.getTime() - time_offset);

		let h = now.getHours() % 12;
		let m = now.getMinutes();
		let s = now.getSeconds();

		this._clockHandRotate(this._target.querySelector('.clock-hand-h'), 30 * (h + m / 60 + s / 3600));
		this._clockHandRotate(this._target.querySelector('.clock-hand-m'), 6 * (m + s / 60));
		this._clockHandRotate(this._target.querySelector('.clock-hand-s'), 6 * s);
	}

	_clockHandRotate(clock_hand, degree) {
		clock_hand.setAttribute('transform', `rotate(${degree} 50 50)`);
	}
}
