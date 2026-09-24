/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class ZTimelineRangeSlider extends HTMLElement {

	static SCALE_LABEL_GAP = 90;
	static SCALE_OFFSET = 11;

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {number | null} */
	#animation_frame_id = null;

	/** @type {HTMLElement} */
	#track;

	/** @type {HTMLElement} */
	#selection_layer;

	/** @type {HTMLElement} */
	#selection;

	/** @type {HTMLElement} */
	#handle_start;

	/** @type {HTMLElement} */
	#handle_end;

	/** @type {HTMLElement} */
	#scale;

	/** @type {number} */
	#start = 0;

	/** @type {number} */
	#end = 1000;

	/** @type {number} */
	#selected_start = 0;

	/** @type {number} */
	#selected_end = 1000;

	/** @type {number} */
	#step = 0;

	/** @type {boolean} */
	#has_content = false;

	/** @type {boolean} */
	#is_reflecting = false;

	/** @type {number | null} */
	#pointer_id = null;

	/** @type {{
		mode: 'select' | 'move' | 'resize-start' | 'resize-end',
		origin_value: number,
		origin_selected_start: number,
		origin_selected_end: number
	} | null} */
	#drag_state = null;

	constructor() {
		super();

		this.#track = document.createElement('div');
		this.#track.classList.add('z-timeline-range-slider-track');

		this.#selection_layer = document.createElement('div');
		this.#selection_layer.classList.add('z-timeline-range-slider-selection-layer');

		this.#selection = document.createElement('div');
		this.#selection.classList.add('z-timeline-range-slider-selection');
		this.#selection.dataset.action = 'move';

		this.#handle_start = document.createElement('div');
		this.#handle_start.classList.add('z-timeline-range-slider-handle', 'z-timeline-range-slider-handle-start');
		this.#handle_start.dataset.action = 'resize-start';

		this.#handle_end = document.createElement('div');
		this.#handle_end.classList.add('z-timeline-range-slider-handle', 'z-timeline-range-slider-handle-end');
		this.#handle_end.dataset.action = 'resize-end';

		this.#scale = document.createElement('div');
		this.#scale.classList.add('z-timeline-range-slider-scale');

		this.#selection_layer.append(this.#selection, this.#handle_start, this.#handle_end);
	}

	static get observedAttributes() {
		return ['start', 'end', 'selected-start', 'selected-end', 'step', 'scale'];
	}

	connectedCallback() {
		this.#mountDom();
		this.#readAttributes();
		this.#addEventListeners();

		this.#resize_observer = new ResizeObserver(() => this.#scheduleRender());
		this.#resize_observer.observe(this);

		this.#render();
	}

	disconnectedCallback() {
		this.#resize_observer?.disconnect();
		this.#resize_observer = null;

		this.#removeEventListeners();

		if (this.#animation_frame_id !== null) {
			cancelAnimationFrame(this.#animation_frame_id);
			this.#animation_frame_id = null;
		}
	}

	attributeChangedCallback() {
		if (this.#is_reflecting) {
			return;
		}

		this.#readAttributes();
		this.#scheduleRender();
	}

	#mountDom() {
		if (this.#track.parentNode === this) {
			return;
		}

		const nodes = Array.from(this.childNodes);

		this.#has_content = nodes.some(node =>
			node.nodeType === Node.ELEMENT_NODE
			|| (node.textContent ?? '').trim() !== ''
		);

		this.toggleAttribute('has-content', this.#has_content);

		for (const node of nodes) {
			this.#track.appendChild(node);
		}

		this.append(this.#scale, this.#track, this.#selection_layer);
	}

	#addEventListeners() {
		this.#selection_layer.addEventListener('pointerdown', this.#onPointerDown);
	}

	#removeEventListeners() {
		this.#selection_layer.removeEventListener('pointerdown', this.#onPointerDown);
		this.#selection_layer.removeEventListener('pointermove', this.#onPointerMove);
		this.#selection_layer.removeEventListener('pointerup', this.#onPointerUp);
		this.#selection_layer.removeEventListener('pointercancel', this.#onPointerUp);
	}

	#readAttributes() {
		this.#start = this.#parseTimeAttribute('start', 0);
		this.#end = this.#parseTimeAttribute('end', 1000);

		if (this.#end <= this.#start) {
			this.#end = this.#start + 1;
		}

		this.#step = Math.max(0, Number(this.getAttribute('step')) || 0);

		this.#selected_start = this.hasAttribute('selected-start')
			? this.#parseTimeAttribute('selected-start', this.#start)
			: this.#start;

		this.#selected_end = this.hasAttribute('selected-end')
			? this.#parseTimeAttribute('selected-end', this.#end)
			: this.#end;

		this.#normalizeSelectedRange();
	}

	#parseTimeAttribute(name, fallback) {
		const value = this.getAttribute(name);

		if (value === null || value.trim() === '') {
			return fallback;
		}

		const number_value = Number(value);

		if (Number.isFinite(number_value)) {
			return number_value;
		}

		const date_value = Date.parse(value);

		return Number.isFinite(date_value) ? date_value : fallback;
	}

	#normalizeSelectedRange() {
		let selected_start = Math.min(this.#selected_start, this.#selected_end);
		let selected_end = Math.max(this.#selected_start, this.#selected_end);

		selected_start = this.#clamp(this.#snap(selected_start), this.#start, this.#end);
		selected_end = this.#clamp(this.#snap(selected_end), this.#start, this.#end);

		if (selected_end < selected_start) {
			selected_end = selected_start;
		}

		this.#selected_start = selected_start;
		this.#selected_end = selected_end;
	}

	#onPointerDown = e => {
		if (this.#pointer_id !== null || (e.pointerType === 'mouse' && e.button !== 0)) {
			return;
		}

		const value = this.#valueFromEvent(e);
		const action = e.target instanceof HTMLElement && e.target.dataset.action !== undefined
			? e.target.dataset.action
			: 'select';

		this.#pointer_id = e.pointerId;

		this.#drag_state = {
			mode: action,
			origin_value: value,
			origin_selected_start: this.#selected_start,
			origin_selected_end: this.#selected_end
		};

		this.#selection_layer.setPointerCapture(this.#pointer_id);
		this.#selection_layer.addEventListener('pointermove', this.#onPointerMove);
		this.#selection_layer.addEventListener('pointerup', this.#onPointerUp);
		this.#selection_layer.addEventListener('pointercancel', this.#onPointerUp);

		this.toggleAttribute('resizing', true);

		if (action === 'select') {
			this.#setSelectedRange(value, value, true);
		}

		e.preventDefault();
	};

	#onPointerMove = e => {
		if (e.pointerId !== this.#pointer_id || this.#drag_state === null) {
			return;
		}

		const value = this.#valueFromEvent(e);
		const drag_state = this.#drag_state;

		switch (drag_state.mode) {
			case 'resize-start':
				this.#setSelectedRange(value, drag_state.origin_selected_end, true);
				break;

			case 'resize-end':
				this.#setSelectedRange(drag_state.origin_selected_start, value, true);
				break;

			case 'move':
				this.#moveSelectedRange(value - drag_state.origin_value);
				break;

			case 'select':
				this.#setSelectedRange(drag_state.origin_value, value, true);
				break;
		}
	};

	#onPointerUp = e => {
		if (e.pointerId !== this.#pointer_id) {
			return;
		}

		this.#selection_layer.removeEventListener('pointermove', this.#onPointerMove);
		this.#selection_layer.removeEventListener('pointerup', this.#onPointerUp);
		this.#selection_layer.removeEventListener('pointercancel', this.#onPointerUp);

		if (this.#selection_layer.hasPointerCapture(this.#pointer_id)) {
			this.#selection_layer.releasePointerCapture(this.#pointer_id);
		}

		this.#pointer_id = null;
		this.#drag_state = null;

		this.toggleAttribute('resizing', false);
		this.#dispatchChangeEvent();
	};

	#moveSelectedRange(delta) {
		const length = this.#selected_end - this.#selected_start;

		let selected_start = this.#snap(this.#drag_state.origin_selected_start + delta);
		let selected_end = selected_start + length;

		if (selected_start < this.#start) {
			selected_start = this.#start;
			selected_end = selected_start + length;
		}

		if (selected_end > this.#end) {
			selected_end = this.#end;
			selected_start = selected_end - length;
		}

		this.#setSelectedRange(selected_start, selected_end, true);
	}

	#setSelectedRange(selected_start, selected_end, dispatch_input) {
		this.#selected_start = selected_start;
		this.#selected_end = selected_end;
		this.#normalizeSelectedRange();
		this.#reflectSelectedRange();
		this.#render();

		if (dispatch_input) {
			this.#dispatchInputEvent();
		}
	}

	#reflectSelectedRange() {
		this.#is_reflecting = true;

		this.setAttribute('selected-start', String(Math.round(this.#selected_start)));
		this.setAttribute('selected-end', String(Math.round(this.#selected_end)));

		this.#is_reflecting = false;
	}

	#valueFromEvent(e) {
		const rect = this.getBoundingClientRect();
		const position = this.#clamp(e.clientX - rect.left, 0, rect.width);
		const ratio = rect.width > 0 ? position / rect.width : 0;

		return this.#snap(this.#start + (this.#end - this.#start) * ratio);
	}

	#snap(value) {
		if (this.#step <= 0) {
			return value;
		}

		return this.#start + Math.round((value - this.#start) / this.#step) * this.#step;
	}

	#render() {
		const range = this.#end - this.#start;
		const selected_start_ratio = (this.#selected_start - this.#start) / range;
		const selected_end_ratio = (this.#selected_end - this.#start) / range;

		this.style.setProperty(
			'--timeline-range-slider-selected-start-position',
			`${this.#clamp(selected_start_ratio, 0, 1) * 100}%`
		);

		this.style.setProperty(
			'--timeline-range-slider-selected-end-position',
			`${this.#clamp(selected_end_ratio, 0, 1) * 100}%`
		);

		this.#renderScale();
	}

	#getTicks() {
		const width = this.getBoundingClientRect().width;
		const range = this.#end - this.#start;

		if (width <= ZTimelineRangeSlider.SCALE_LABEL_GAP || range <= 0) {
			return [];
		}

		const label_gap = this.#getPixelCustomProperty('--timeline-range-slider-label-gap',
			ZTimelineRangeSlider.SCALE_LABEL_GAP);
		const num_segments = Math.max(1, Math.floor(width / label_gap));
		const step = range / num_segments;

		const padding_ratio = ZTimelineRangeSlider.SCALE_OFFSET / width;
		const span_ratio = 1 - 2 * padding_ratio;

		const ticks = [];

		for (let i = 0; i <= num_segments; i++) {
			const ratio = i / num_segments;
			const value = this.#start + range * ratio;

			let position;
			if (i === 0) {
				position = `${ZTimelineRangeSlider.SCALE_OFFSET}px`;
			}
			else if (i === num_segments) {
				position = `calc(100% - ${ZTimelineRangeSlider.SCALE_OFFSET}px)`;
			}
			else {
				position = `${(padding_ratio + ratio * span_ratio) * 100}%`;
			}

			ticks.push({value, step, ratio, position});
		}

		return ticks;
	}

	#renderScale() {
		if (!this.#shouldRenderScale()) {
			this.#scale.replaceChildren();
			return;
		}

		const ticks = this.#getTicks();

		if (ticks.length === 0) {
			this.#scale.replaceChildren();

			return;
		}

		const range = this.#end - this.#start;
		const elements = ticks.map(tick => {
			const label = document.createElement('span');
			label.textContent = this.#formatTime(tick.value, tick.step, range);

			const element = document.createElement('div');
			element.classList.add('z-timeline-range-slider-tick');
			element.style.setProperty('--tick-position', tick.position);
			element.appendChild(label);

			return element;
		});

		this.#scale.replaceChildren(...elements);
	}

	#shouldRenderScale() {
		return !this.#has_content || this.hasAttribute('scale');
	}

	#formatTime(value) {
		if (value <= 0) {
			return '0s';
		}

		if (value < 1e-6) {
			return `${Math.round(value * 1e9)}ns`;
		}

		if (value < 1e-3) {
			return `${+(value * 1e6).toFixed(1)}µs`;
		}

		if (value < 1) {
			return `${+(value * 1e3).toFixed(1)}ms`;
		}

		if (value < 60) {
			return `${+value.toFixed(1)}s`;
		}

		if (value < 3600) {
			const mins = Math.floor(value / 60);
			const secs = Math.round(value % 60);

			if (secs === 0 || secs === 60) {
				const final_mins = secs === 60 ? mins + 1 : mins;

				return `${final_mins}m`;
			}

			return `${mins}m ${secs}s`;
		}

		const hours = Math.floor(value / 3600);
		const mins = Math.round((value % 3600) / 60);

		if (mins === 0) {
			return `${hours}h`;
		}

		return `${hours}h ${mins}m`;
	}

	#getPixelCustomProperty(name, fallback) {
		const value = Number.parseFloat(getComputedStyle(this).getPropertyValue(name));

		return Number.isFinite(value) ? value : fallback;
	}

	#scheduleRender() {
		if (this.#animation_frame_id !== null) {
			return;
		}

		this.#animation_frame_id = requestAnimationFrame(() => {
			this.#animation_frame_id = null;
			this.#render();
		});
	}

	#dispatchInputEvent() {
		this.dispatchEvent(new CustomEvent('input', {
			bubbles: true,
			detail: this.value
		}));
	}

	#dispatchChangeEvent() {
		this.dispatchEvent(new CustomEvent('change', {
			bubbles: true,
			detail: this.value
		}));
	}

	#clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	get value() {
		return {
			start: this.#start,
			end: this.#end,
			selected_start: this.#selected_start,
			selected_end: this.#selected_end
		};
	}

	get start() {
		return this.#start;
	}

	set start(value) {
		this.setAttribute('start', String(value));
	}

	get end() {
		return this.#end;
	}

	set end(value) {
		this.setAttribute('end', String(value));
	}

	get selectedStart() {
		return this.#selected_start;
	}

	set selectedStart(value) {
		this.setAttribute('selected-start', String(value));
	}

	get selectedEnd() {
		return this.#selected_end;
	}

	set selectedEnd(value) {
		this.setAttribute('selected-end', String(value));
	}

	get step() {
		return this.#step;
	}

	set step(value) {
		this.setAttribute('step', String(value));
	}
}

customElements.define('z-timeline-range-slider', ZTimelineRangeSlider);
