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


const BAR_GAUGE_BAR_DEFAULT_COLOR = '#000000';
const BAR_GAUGE_BAR_DEFAULT_MIN = 0;
const BAR_GAUGE_BAR_DEFAULT_MAX = 100;
const BAR_GAUGE_BAR_ITEM_WIDTH = 7;

class ZBarGauge extends HTMLElement {

	constructor() {
		super();

		this._refresh_frame = null;

		this._fill = BAR_GAUGE_BAR_DEFAULT_COLOR;
		this._solid = false;
		this._min = 0;
		this._max = 100;
		this._value = 0;

		const shadow = this.attachShadow({mode: 'open'});

		this._canvas = document.createElement('canvas');
		this._canvas.setAttribute('part', 'bar');
		shadow.appendChild(this._canvas);

		this.registerEvents();
	}

	connectedCallback() {
		setTimeout(() => {
			this._events.update();
		});
	}

	disconnectedCallback() {
		this.unregisterEvents();
	}

	static get observedAttributes() {
		return ['fill', 'max', 'min', 'solid', 'value', 'width'];
	}

	attributeChangedCallback(name, old_value, new_value) {
		if (old_value === new_value) {
			return;
		}

		switch (name) {
			case 'fill':
				this._fill = (new_value !== null && /^#([0-9A-F]{6})$/i.test(new_value))
					? new_value
					: BAR_GAUGE_BAR_DEFAULT_COLOR;

				return this._events.update();

			case 'max':
				this._max = new_value !== null && !isNaN(new_value) ? Number(new_value) : BAR_GAUGE_BAR_DEFAULT_MAX;
				break

			case 'min':
				this._min = new_value !== null && !isNaN(new_value) ? Number(new_value) : BAR_GAUGE_BAR_DEFAULT_MIN;
				break;

			case 'solid':
				this._solid = new_value !== null;
				break;

			case 'value':
				if (new_value !== null) {
					this._value = Number(new_value);

					this.dispatchEvent(new Event('change', {bubbles: true}));
				}
				break;

			case 'width':
				if (new_value === null) {
					this.style.width = '';
				}
				else if (!isNaN(new_value)) {
					this.style.width = `${new_value}px`;
				}
				else {
					this.style.width = new_value;
				}
				break;

			default:
				return;
		}

		this._refresh();
	}

	get max() {
		return this.getAttribute('max');
	}

	set max(max) {
		this.setAttribute('max', max);
	}

	get min() {
		return this.getAttribute('min');
	}

	set min(min) {
		this.setAttribute('min', min);
	}

	get value() {
		return this.getAttribute('value');
	}

	set value(value) {
		this.setAttribute('value', value);
	}

	get width() {
		return this.getAttribute('width');
	}

	set width(width) {
		this.setAttribute('width', width);
	}

	addThreshold(value, fill) {
		const threshold = document.createElement('threshold');
		threshold.setAttribute('value', value);
		threshold.setAttribute('fill', fill);

		this.appendChild(threshold);
	}

	_refresh() {
		if (this._refresh_frame !== null || this._thresholds === undefined) {
			return;
		}

		this._refresh_frame = window.requestAnimationFrame(() => {
			this._refresh_frame = null;

			const ctx = this._canvas.getContext("2d");

			const width = this.offsetWidth - 2;
			this._canvas.height = this.offsetHeight - 2;

			const value = Math.max(this._min, Math.min(this._max, this._value));

			if (this._solid) {
				const bar_size = value > this._min
					? Math.max(Math.floor(width / (this._max - this._min) * (value - this._min)), 2)
					: 0;

				this._canvas.width = width + 2;

				this._drawCell(ctx, 1, bar_size, this._getThresholdColorByValue(value), 1);
			}
			else {
				const cell_count = Math.floor(width / BAR_GAUGE_BAR_ITEM_WIDTH);
				const cell_interval = (this._max - this._min) / cell_count;

				this._canvas.width = cell_count * BAR_GAUGE_BAR_ITEM_WIDTH + 1;

				for (let i = 0; i < cell_count; i++) {
					const alpha = (value - this._min) / cell_interval > i ? 1 : .25;

					this._drawCell(ctx, i * BAR_GAUGE_BAR_ITEM_WIDTH + 1, BAR_GAUGE_BAR_ITEM_WIDTH - 1,
						this._getThresholdColorByValue(i * cell_interval + this._min), alpha
					);
				}
			}
		});
	}

	_drawCell(ctx, x, width, color, alpha) {
		const rgb = this._hexToRgb(color);
		const rgb_lighten = this._colorLightenDarken(rgb, .5);
		const rgb_darken = this._colorLightenDarken(rgb, -.3);
		const fill = ctx.createLinearGradient(x, 1, x, this._canvas.height - 2);

		fill.addColorStop(0, `rgba(${rgb_lighten.r}, ${rgb_lighten.g}, ${rgb_lighten.b}, ${alpha}`);
		fill.addColorStop(.3, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha}`);
		fill.addColorStop(.8, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha}`);
		fill.addColorStop(1, `rgba(${rgb_darken.r}, ${rgb_darken.g}, ${rgb_darken.b}, ${alpha}`);

		ctx.fillStyle = fill;

		this._roundRect(ctx, x, 1, width, this._canvas.height - 2, 3);
		ctx.fill();
	}

	_roundRect(ctx, x, y, width, height, radius) {
		radius = Math.min(width / 2, radius);

		ctx.beginPath();
		ctx.moveTo(x + radius, y);
		ctx.arcTo(x + width, y, x + width, y + height, radius);
		ctx.arcTo(x + width, y + height, x, y + height, radius);
		ctx.arcTo(x, y + height, x, y, radius);
		ctx.arcTo(x, y, x + width, y, radius);
		ctx.closePath();
	}

	_getThresholdColorByValue(value) {
		let color = BAR_GAUGE_BAR_DEFAULT_COLOR;

		for (const threshold of Object.keys(this._thresholds).sort((a, b) => (a - b))) {
			if (threshold <= value) {
				color = this._thresholds[threshold];
			}
		}

		return color;
	}

	_hexToRgb(hex) {
		const rgb = /^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);

		return {
			r: rgb ? parseInt(rgb[1], 16) : 0,
			g: rgb ? parseInt(rgb[2], 16) : 0,
			b: rgb ? parseInt(rgb[3], 16) : 0
		};
	}

	_colorLightenDarken(rgb, amount) {
		return {
			r: Math.max(0, Math.min(255, rgb.r + amount * (amount > 0 ? 255 - rgb.r : rgb.r))),
			g: Math.max(0, Math.min(255, rgb.g + amount * (amount > 0 ? 255 - rgb.g : rgb.g))),
			b: Math.max(0, Math.min(255, rgb.b + amount * (amount > 0 ? 255 - rgb.b : rgb.b)))
		};
	}

	registerEvents() {
		this._events = {
			resize: () => {
				this._refresh();
			},

			update: () => {
				this._thresholds = {0: this._fill};

				for (const threshold of this.querySelectorAll('threshold')) {
					if (threshold.hasAttribute('fill') && threshold.hasAttribute('value')) {
						this._thresholds[threshold.getAttribute('value')] = threshold.getAttribute('fill');
					}
				}

				this._refresh();
			}
		}

		this._events_data = {
			resize_observer: new ResizeObserver(this._events.resize),
			mutation_observer: new MutationObserver(this._events.update)
		}

		this._events_data.resize_observer.observe(this);
		this._events_data.mutation_observer.observe(this, {childList: true});
	}

	unregisterEvents() {
		this._events_data.resize_observer.disconnect();
		this._events_data.mutation_observer.disconnect();

		cancelAnimationFrame(this._refresh_frame);
	}
}

customElements.define('z-bar-gauge', ZBarGauge);
