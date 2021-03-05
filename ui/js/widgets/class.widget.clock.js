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

	constructor({
		...config,
		time,
		time_zone_string,
		time_zone_offset
	}) {
		super(config);

		this._time = time;
		this._time_zone_string = time_zone_string;
		this._time_zone_offset = time_zone_offset;
	}

	start() {
		super.start();

	// 	'$("#'.$this->getId().'").zbx_clock('.
	// 	json_encode([
	// 		'time' => $this->time,
	// 		'time_zone_string' => $this->time_zone_string,
	// 		'time_zone_offset' => $this->time_zone_offset,
	// 		'clock_id' => $this->getId()
	// ]).
	// 	');'.
	}

	activate() {
		super.activate();

		this._clock_hands_rotate();
	}

	_clock_hands_rotate() {
		const now = new Date();

		let time_offset = 0;

		if (this._time !== null) {
			time_offset = now.getTime() - this._time * 1000;
		}

		if (this._time_zone_offset !== null) {
			time_offset += (- now.getTimezoneOffset() * 60 - this._time_zone_offset) * 1000;
		}

		if (time_offset != 0) {
			now.setTime(now.getTime() - time_offset);
		}

		let h = now.getHours() % 12;
		let m = now.getMinutes();
		let s = now.getSeconds();

		this._clock_hand_rotate(this._target.querySelector('.clock-hand-h'), 30 * (h + m / 60 + s / 3600));
		this._clock_hand_rotate(this._target.querySelector('.clock-hand-m'), 6 * (m + s / 60));
		this._clock_hand_rotate(this._target.querySelector('.clock-hand-s'), 6 * s);
	}

	_clock_hand_rotate(clock_hand, degree) {
		clock_hand.setAttribute('transform', `rotate(${degree} 50 50)`);
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			tick: () => {
				this._clock_hands_rotate();
			}
		}

		this._interval_id = setInterval(this._events.tick, 1000);
	}

	_unregisterEvents() {
		super._unregisterEvents();

		clearInterval(this._interval_id);
	}
}
