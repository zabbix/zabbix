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

	static DEFAULT_RATIO = 1;

	static MIN_FONT_SIZE = 12;
	static MIN_FONT_SIZE_BIG = 24;

	static LINE_HEIGHT = 1.14;

	static PADDING = 10;

	onInitialize() {
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
		this._canvas_context = document.createElement('canvas').getContext('2d');
		this._styles = {};
		this._classes = {};
	}

	onActivate() {
		this._startClock();
	}

	onDeactivate() {
		this._stopClock();
	}

	onResize() {
		if (!this._has_contents) {
			return;
		}

		if (this._clock_type === CWidgetClock.TYPE_DIGITAL) {
			this._adjustDigitalClockSize();
		}
	}

	processUpdateResponse(response) {
		super.processUpdateResponse(response);

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
				this._styles = response.styles;
				this._classes = response.classes;
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
		if (!this._has_contents) {
			return;
		}

		if (this._clock_type === CWidgetClock.TYPE_DIGITAL && !this._is_enabled) {
			this._adjustDigitalClockSize();
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

				this._adjustDigitalClockSize();

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

		if (!clock_svg.classList.contains('disabled')) {
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

		const timezone_text = this._getTimeZoneText();

		if (this._tzone_format === CWidgetClock.TIMEZONE_SHORT) {
			clock_time_zone.textContent = timezone_text;
		}
		else {
			const parts = clock_time_zone.querySelectorAll('span');
			const separator = ' ';

			parts[0].textContent = timezone_text.substring(0, timezone_text.indexOf(separator));
			parts[1].textContent = timezone_text.substring(timezone_text.indexOf(separator) + 1);
		}
	}

	_getTimeZoneText() {
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

		return timezone_text;
	}

	_adjustDigitalClockSize() {
		const container_rect = this._target.querySelector('.clock-digital').getBoundingClientRect();

		const max_width = (container_rect.width - CWidgetClock.PADDING * 2) * .9;
		const max_height = container_rect.height - CWidgetClock.PADDING * 2;

		if (this._is_enabled) {
			const clock_parts = {};

			for (const type of this._show) {
				const element = this._target.querySelector(`.${this._classes[type]}`);

				if (element !== null) {
					clock_parts[type] = {
						element,
						ratio: CWidgetClock.DEFAULT_RATIO,
						min_font_size: CWidgetClock.MIN_FONT_SIZE,
						font_weight: this._styles[type].bold ? 'bold' : '',
						text: type === CWidgetClock.SHOW_TIMEZONE ? this._getTimeZoneText() : element.textContent
					}
				}
			}

			if (Object.values(clock_parts).length === 2) {
				const ratio_small = 0.17;
				const ratio_big = 0.83;

				if (CWidgetClock.SHOW_TIMEZONE in clock_parts) {
					clock_parts[CWidgetClock.SHOW_TIMEZONE].ratio = ratio_small;

					if (CWidgetClock.SHOW_DATE in clock_parts) {
						clock_parts[CWidgetClock.SHOW_DATE].ratio = ratio_big;
						clock_parts[CWidgetClock.SHOW_DATE].min_font_size = CWidgetClock.MIN_FONT_SIZE_BIG;
					}
				}

				if (CWidgetClock.SHOW_TIME in clock_parts) {
					clock_parts[CWidgetClock.SHOW_TIME].ratio = ratio_big;
					clock_parts[CWidgetClock.SHOW_TIME].min_font_size = CWidgetClock.MIN_FONT_SIZE_BIG;

					if (CWidgetClock.SHOW_DATE in clock_parts) {
						clock_parts[CWidgetClock.SHOW_DATE].ratio = ratio_small;
					}
				}
			}
			else if (Object.values(clock_parts).length === 3) {
				clock_parts[CWidgetClock.SHOW_DATE].ratio = 0.2;
				clock_parts[CWidgetClock.SHOW_TIME].ratio = 0.6;
				clock_parts[CWidgetClock.SHOW_TIMEZONE].ratio = 0.2;

				clock_parts[CWidgetClock.SHOW_TIME].min_font_size = CWidgetClock.MIN_FONT_SIZE_BIG;
			}

			for (const clock_part of Object.values(clock_parts)) {
				clock_part.font_size = Math.max(clock_part.min_font_size,
					max_height * clock_part.ratio / CWidgetClock.LINE_HEIGHT
				);
			}

			if (Object.values(clock_parts).length > 0) {
				const largest_width = Object.values(clock_parts)
					.map(e => this._getMeasuredTextWidth(e.text, e.font_size, e.font_weight))
					.reduce((w1, w2) => w1 > w2 ? w1 : w2);

				if (largest_width > max_width) {
					const width_ratio = max_width / largest_width;

					for (const clock_part of Object.values(clock_parts)) {
						clock_part.font_size = Math.max(clock_part.min_font_size, clock_part.font_size * width_ratio);
					}
				}
			}

			if (CWidgetClock.SHOW_TIMEZONE in clock_parts && this._tzone_format === CWidgetClock.TIMEZONE_FULL) {
				const timezone_element = clock_parts[CWidgetClock.SHOW_TIMEZONE];
				const timezone_line_height = timezone_element.font_size * CWidgetClock.LINE_HEIGHT;
				const elements_height = Object.values(clock_parts)
					.reduce((sum, element) => sum + element.font_size * CWidgetClock.LINE_HEIGHT, 0);
				const has_enough_free_height = max_height - elements_height >= timezone_line_height;

				timezone_element.element.classList.toggle('separated', has_enough_free_height);

				const parts = timezone_element.element.querySelectorAll('span');

				timezone_element.text = has_enough_free_height
					? Array.from(parts)
						.reduce((p1, p2) => {
							const getWidth = text => this._getMeasuredTextWidth(text, timezone_element.font_size,
								timezone_element.font_weight
							);

							return getWidth(p1.textContent) > getWidth(p2.textContent) ? p1 : p2;
						}).textContent
					: Array.from(parts, p => p.textContent).join(' ');

				const elements_largest_width = Object.values(clock_parts)
					.map(e => this._getMeasuredTextWidth(e.text, e.font_size, e.font_weight))
					.reduce((w1, w2) => w1 > w2 ? w1 : w2);
				const all_elements_height = has_enough_free_height
					? elements_height + timezone_line_height
					: elements_height;
				const ratio = Math.min(max_width / elements_largest_width, max_height / all_elements_height);

				for (const clock_part of Object.values(clock_parts)) {
					clock_part.font_size = Math.max(clock_part.min_font_size, clock_part.font_size * ratio);
				}
			}

			const parts_height = Math.floor(Number(
				Object.values(clock_parts).reduce((sum, part) => sum + part.font_size * CWidgetClock.LINE_HEIGHT, 0)
			));

			if (parts_height > max_height) {
				for (const clock_part of Object.values(clock_parts)) {
					clock_part.font_size = 0;
				}
			}

			for (const clock_part of Object.values(clock_parts)) {
				clock_part.element.style.fontSize = `${clock_part.font_size}px`;
			}
		}
		else {
			const no_data_element = this._target.querySelector('.clock-disabled');

			if (no_data_element !== null) {
				const ratio = 0.6;

				let font_size = max_height * ratio / CWidgetClock.LINE_HEIGHT;

				const width = this._getMeasuredTextWidth(no_data_element.textContent, font_size, 'bold');

				if (width > max_width) {
					font_size *= max_width / width;
				}

				font_size = Math.max(CWidgetClock.MIN_FONT_SIZE, font_size);

				if (font_size * CWidgetClock.LINE_HEIGHT > max_height) {
					font_size = 0;
				}

				no_data_element.style.fontSize = `${font_size}px`;
			}
		}
	}

	/**
	 * Get text width using canvas measuring.
	 *
	 * @param {string} text
	 * @param {number} font_size
	 * @param {number|string} font_weight
	 *
	 * @returns {number}
	 */
	_getMeasuredTextWidth(text, font_size, font_weight = '') {
		this._canvas_context.font = `${font_weight} ${font_size}px ${getComputedStyle(this._contents).fontFamily}`;

		return this._canvas_context.measureText(text).width;
	}

	hasPadding() {
		return this.getFields().clock_type === CWidgetClock.TYPE_ANALOG;
	}
}
