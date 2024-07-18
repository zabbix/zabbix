/*
** Copyright (C) 2001-2024 Zabbix SIA
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

	static DEFAULT_FONT_SIZE_DATE = 20;
	static DEFAULT_FONT_SIZE_TIME = 30;
	static DEFAULT_FONT_SIZE_TIMEZONE = 20;
	static DEFAULT_FONT_SIZE_NO_DATA = 60;
	static FONT_SIZE_MIN = 12;
	static LINE_HEIGHT = 1.14;

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

	_adjustDigitalClockSize() {
		const container_style = getComputedStyle(this._target.querySelector('.clock-digital'));

		const available_width = Math.floor(
			parseFloat(container_style.width)
			- parseFloat(container_style.paddingLeft) - parseFloat(container_style.paddingRight)
			- parseFloat(container_style.borderLeftWidth) - parseFloat(container_style.borderRightWidth)
		);

		const available_height = Math.floor(
			parseFloat(container_style.height)
			- parseFloat(container_style.paddingTop) - parseFloat(container_style.paddingBottom)
			- parseFloat(container_style.borderTopWidth) - parseFloat(container_style.borderBottomWidth)
		);

		const getAutoFontSize = (text, default_height, font_weight) => {
			let font_size = available_height * default_height / 100;

			const text_width = this._getMeasuredTextWidth(text, font_size, font_weight);

			if (text_width > available_width) {
				font_size *= available_width / text_width;
			}

			return Math.max(CWidgetClock.FONT_SIZE_MIN, font_size / CWidgetClock.LINE_HEIGHT);
		}

		if (this._is_enabled) {
			if (this._show.includes(CWidgetClock.SHOW_DATE)) {
				const date_element = this._target.querySelector('.clock-date');
				const font_weight = this._styles.date.bold ? 'bold' : '';
				const font_size = getAutoFontSize(date_element.textContent, CWidgetClock.DEFAULT_FONT_SIZE_DATE,
					font_weight);

				date_element.style.fontSize = `${font_size}px`;
			}

			if (this._show.includes(CWidgetClock.SHOW_TIME)) {
				const time_element = this._target.querySelector('.clock-time');
				const font_weight = this._styles.time.bold ? 'bold' : '';
				const font_size = getAutoFontSize(time_element.textContent, CWidgetClock.DEFAULT_FONT_SIZE_TIME,
					font_weight);

				time_element.style.fontSize = `${font_size}px`;
			}

			if (this._show.includes(CWidgetClock.SHOW_TIMEZONE)) {
				const timezone_element = this._target.querySelector('.clock-time-zone');
				const font_weight = this._styles.timezone.bold ? 'bold' : '';
				const font_size = getAutoFontSize(timezone_element.textContent, CWidgetClock.DEFAULT_FONT_SIZE_TIMEZONE,
					font_weight);

				timezone_element.style.fontSize = `${font_size}px`;
			}
		}
		else {
			const no_data_element = this._target.querySelector('.clock-disabled');
			const font_size = getAutoFontSize(no_data_element.textContent, CWidgetClock.DEFAULT_FONT_SIZE_NO_DATA,
				'bold');

			no_data_element.style.fontSize = `${font_size}px`;
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
