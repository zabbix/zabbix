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


class ZSparkline extends HTMLElement {

	static ZBX_STYLE_CLASS =		'svg-sparkline';
	static ZBX_STYLE_CLASS_LINE =	'svg-sparkline-line';
	static ZBX_STYLE_CLASS_AREA =	'svg-sparkline-area';

	static PADDING_VERTICAL = 2;

	static DEFAULT_COLOR = '#42A5F5';
	static DEFAULT_FILL = 3;
	static DEFAULT_LINE_WIDTH = 1;

	#container;
	#svg;
	#line;
	#area;

	#width;
	#height;
	#color;
	#line_width;
	#fill;
	#points;
	#time_period;

	#xAccessor = value => value.x;
	#yAccessor = value => value.y;

	#xScale;
	#yScale;

	#areaGenerator;
	#lineGenerator;

	#events;
	#events_data;

	constructor() {
		super();
	}

	connectedCallback() {
		customElements.whenDefined('z-sparkline').then(() => {
			if (this.#container !== undefined) {
				return;
			}

			this.#container = d3.select(this);

			this.#width = parseFloat(this.#container.attr('width')) || this.offsetWidth;
			this.#height = parseFloat(this.#container.attr('height')) || this.offsetHeight;

			const color = this.#container.attr('color');
			this.#color = isColorHex(color) ? color : ZSparkline.DEFAULT_COLOR;

			const line_width = parseInt(this.#container.attr('line-width'));
			this.#line_width = !isNaN(line_width) ? line_width : ZSparkline.DEFAULT_LINE_WIDTH;

			const fill = parseInt(this.#container.attr('fill'));
			this.#fill = !isNaN(fill) ? fill : ZSparkline.DEFAULT_FILL;

			this.#points = ZSparkline.#parsePoints(this.#container.attr('value'));

			this.#time_period = {
				from: parseInt(this.#container.attr('time-period-from')) || d3.min(this.#points, this.#xAccessor),
				to: parseInt(this.#container.attr('time-period-to')) || d3.max(this.#points, this.#xAccessor)
			};

			this.#xScale = d3.scaleTime().domain(this.#getXScaleDomain()).range(this.#getXScaleRange());
			this.#yScale = d3.scaleLinear().domain(this.#getYScaleDomain()).range(this.#getYScaleRange());

			this.#areaGenerator = d3.area()
				.x(d => this.#xScale(this.#xAccessor(d)))
				.y1(d => this.#yScale(this.#yAccessor(d)))
				.defined(d => !isNaN(this.#yAccessor(d)));

			this.#lineGenerator = d3.line()
				.x(d => this.#xScale(this.#xAccessor(d)))
				.y(d => this.#yScale(this.#yAccessor(d)))
				.defined(d => !isNaN(this.#yAccessor(d)));

			this.#svg = this.#container
				.append('svg')
				.attr('class', ZSparkline.ZBX_STYLE_CLASS);

			this.#area = this.#svg
				.append('path')
				.attr('class', ZSparkline.ZBX_STYLE_CLASS_AREA);

			this.#line = this.#svg
				.append('path')
				.attr('class', ZSparkline.ZBX_STYLE_CLASS_LINE)
				.attr('stroke-linejoin', 'round')
				.attr('stroke-linecap', 'round')
				.attr('fill', 'none');

			this.#refresh();
			this.#registerEvents();
		});
	}

	#refresh() {
		this.#svg
			.attr('width', this.#width)
			.attr('height', this.#height);

		this.#xScale
			.domain(this.#getXScaleDomain())
			.range(this.#getXScaleRange());

		this.#yScale
			.domain(this.#getYScaleDomain())
			.range(this.#getYScaleRange());

		this.#areaGenerator.y0(this.#height);

		this.#area
			.attr('d', this.#areaGenerator(this.#points))
			.attr('fill', this.#color)
			.attr('fill-opacity', this.#fill * 0.1);

		this.#line
			.attr('d', this.#lineGenerator(this.#points))
			.attr('stroke', this.#color)
			.attr('stroke-width', this.#line_width);
	}

	#registerEvents() {
		this.#events = {
			resize: () => {
				if (this.#container !== undefined) {
					this.#width = parseFloat(this.#container.attr('width')) || this.offsetWidth;
					this.#height = parseFloat(this.#container.attr('height')) || this.offsetHeight;

					this.#refresh();
				}
			}
		};

		this.#events_data = {
			resize_observer: new ResizeObserver(this.#events.resize)
		};

		this.#events_data.resize_observer.observe(this);
	}

	#unregisterEvents() {
		this.#events_data.resize_observer.disconnect();
	}

	disconnectedCallback() {
		this.#unregisterEvents();
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (this.#container === undefined) {
			return;
		}

		if (old_value === new_value) {
			return;
		}

		switch (name) {
			case 'width':
				this.#width = parseFloat(new_value) || this.offsetWidth;
				break;

			case 'height':
				this.#height = parseFloat(new_value) || this.offsetHeight;
				break;

			case 'color':
				this.#color = isColorHex(new_value) ? new_value : ZSparkline.DEFAULT_COLOR;
				break;

			case 'line-width':
				const line_width = parseInt(new_value);
				this.#line_width = !isNaN(line_width) ? line_width : ZSparkline.DEFAULT_LINE_WIDTH;
				break;

			case 'fill':
				const fill = parseInt(new_value);
				this.#fill = !isNaN(fill) ? fill : ZSparkline.DEFAULT_FILL;
				break;

			case 'value':
				this.#points = ZSparkline.#parsePoints(new_value);
				break;

			case 'time-period-from':
				this.#time_period.from = parseInt(new_value) || d3.min(this.#points, this.#xAccessor);

				if (this.#time_period.from > this.#time_period.to) {
					this.#time_period.to = this.#time_period.from + 1;
				}
				break;

			case 'time-period-to':
				this.#time_period.to = parseInt(new_value) || d3.max(this.#points, this.#xAccessor);

				if (this.#time_period.from > this.#time_period.to) {
					this.#time_period.from = this.#time_period.to - 1;
				}
				break;

			default:
				return;
		}

		this.#refresh();
	}

	#getXScaleDomain() {
		return [this.#time_period.from, this.#time_period.to];
	}

	#getXScaleRange() {
		return [0, this.#width];
	}

	#getYScaleDomain() {
		return d3.extent(this.#points, this.#yAccessor);
	}

	#getYScaleRange() {
		return [
			this.#height - this.#line_width / 2 - ZSparkline.PADDING_VERTICAL,
			this.#line_width / 2 + ZSparkline.PADDING_VERTICAL
		];
	}

	/**
	 * Parse value from string representation to array of points.
	 *
	 * @param {string} value
	 *
	 * @returns {array}
	 */
	static #parsePoints(value) {
		try {
			return JSON.parse(value).map(point => ({x: parseInt(point[0]), y: parseFloat(point[1])}));
		}
		catch {
			return [];
		}
	}

	/**
	 * Necessary for attributeChangedCallback to check only attributes defined here.
	 *
	 * @returns {array}
	 */
	static get observedAttributes() {
		return ['width', 'height', 'color', 'line-width', 'fill', 'value', 'time-period-from', 'time-period-to'];
	}

	get width() {
		return this.getAttribute('width');
	}

	set width(width) {
		this.setAttribute('width', width);
	}

	get height() {
		return this.getAttribute('width');
	}

	set height(height) {
		this.setAttribute('height', height);
	}

	get color() {
		return this.getAttribute('color');
	}

	set color(color) {
		this.setAttribute('color', color);
	}

	get lineWidth() {
		return this.getAttribute('line-width');
	}

	set lineWidth(line_width) {
		this.setAttribute('line-width', line_width);
	}

	get fill() {
		return this.getAttribute('fill');
	}

	set fill(fill) {
		this.setAttribute('fill', fill);
	}

	get value() {
		return this.getAttribute('value');
	}

	set value(value) {
		this.setAttribute('value', value);
	}

	get timePeriodFrom() {
		return this.getAttribute('time-period-from');
	}

	set timePeriodFrom(from) {
		this.setAttribute('time-period-from', from);
	}

	get timePeriodTo() {
		return this.getAttribute('time-period-to');
	}

	set timePeriodTo(to) {
		this.setAttribute('time-period-to', to);
	}
}

customElements.define('z-sparkline', ZSparkline);
