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


class TraceViewPage {

	static SCALE_LABEL_GAP = 120;
	static SCALE_OFFSET = 11;

	/** @type {HTMLElement} */
	#container;

	/** @type {HTMLElement} */
	#timeline;

	/** @type {SVGSVGElement} */
	#overview;

	/** @type {ZNavigationTree} */
	#tree;

	/** @type {HTMLElement} */
	#time_header;

	/** @type {HTMLElement} */
	#waterfall;

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {number | null} */
	#animation_frame_id = null;

	/** @type {Map<string, object>} */
	#spans = new Map();

	/** @type {object | null} */
	#trace = null;

	/** @type {number} */
	#start = 0;

	/** @type {number} */
	#end = 1000;

	/** @type {number} */
	#selected_start = 0;

	/** @type {number} */
	#selected_end = 1000;

	constructor(container, trace) {
		this.#container = container;
		this.#timeline = container.querySelector('[data-trace-timeline]');
		this.#overview = container.querySelector('[data-trace-overview]');
		this.#tree = container.querySelector('[data-trace-tree]');
		this.#time_header = container.querySelector('[data-trace-time-header]');
		this.#waterfall = container.querySelector('[data-trace-waterfall]');

		this.#addEventListeners();

		this.#resize_observer = new ResizeObserver(() => this.#scheduleRender());
		this.#resize_observer.observe(this.#container);
		this.#resize_observer.observe(this.#time_header);

		this.setTrace(trace);
	}

	destroy() {
		this.#removeEventListeners();

		this.#resize_observer?.disconnect();
		this.#resize_observer = null;

		if (this.#animation_frame_id !== null) {
			cancelAnimationFrame(this.#animation_frame_id);
			this.#animation_frame_id = null;
		}
	}

	#addEventListeners() {
		this.#timeline.addEventListener('input', this.#onTimelineInput);
		this.#timeline.addEventListener('change', this.#onTimelineChange);
		this.#tree.addEventListener('toggle', this.#onTreeToggle);
		this.#tree.addEventListener('select', this.#onTreeSelect);
	}

	#removeEventListeners() {
		this.#timeline.removeEventListener('input', this.#onTimelineInput);
		this.#timeline.removeEventListener('change', this.#onTimelineChange);
		this.#tree.removeEventListener('toggle', this.#onTreeToggle);
		this.#tree.removeEventListener('select', this.#onTreeSelect);
	}

	#onTimelineInput = event => {
		this.#applyTimelineValue(event.target.value);
	};

	#onTimelineChange = event => {
		this.#applyTimelineValue(event.target.value);
	};

	#onTreeToggle = () => {
		this.#renderWaterfall();
	};

	#onTreeSelect = event => {
		this.#container.dispatchEvent(new CustomEvent('span-select', {
			bubbles: true,
			detail: event.detail
		}));
	};

	setTrace(trace) {
		this.#trace = trace;
		this.#spans.clear();

		const spans = Array.isArray(trace?.spans) ? trace.spans : [];

		for (const span of spans) {
			this.#spans.set(String(span.id), {
				...span,
				id: String(span.id),
				parent_id: span.parent_id == null ? null : String(span.parent_id),
				events: Array.isArray(span.events) ? span.events : []
			});
		}

		this.#start = Number.isFinite(Number(trace?.start)) ? Number(trace.start) : this.#getTraceStart();
		this.#end = Number.isFinite(Number(trace?.end)) ? Number(trace.end) : this.#getTraceEnd();

		if (this.#end <= this.#start) {
			this.#end = this.#start + 1;
		}

		this.#selected_start = Number.isFinite(Number(trace?.selected_start)) ? Number(trace.selected_start) : this.#start;
		this.#selected_end = Number.isFinite(Number(trace?.selected_end)) ? Number(trace.selected_end) : this.#end;

		this.#normalizeSelectedRange();
		this.#render();
	}

	#applyTimelineValue(value) {
		if (value === null || typeof value !== 'object') {
			return;
		}

		this.#selected_start = this.#parseNumber(value.selected_start, this.#selected_start);
		this.#selected_end = this.#parseNumber(value.selected_end, this.#selected_end);
		this.#normalizeSelectedRange();

		this.#renderTree();
		this.#renderTimeHeader();
		this.#renderWaterfall();

		this.#container.dispatchEvent(new CustomEvent('range-change', {
			bubbles: true,
			detail: {
				start: this.#start,
				end: this.#end,
				selected_start: this.#selected_start,
				selected_end: this.#selected_end
			}
		}));
	}

	#render() {
		this.#renderHeader();
		this.#renderTimeline();
		this.#renderOverview();
		this.#renderTree();
		this.#renderTimeHeader();
		this.#renderWaterfall();
	}

	#renderHeader() {
		const trace_id = this.#container.querySelector('[data-trace-id]');

		if (trace_id !== null) {
			trace_id.textContent = this.#trace?.id ? `Trace ID: ${this.#trace.id}` : 'Trace';
		}
	}

	#renderTimeline() {
		this.#timeline.setAttribute('start', String(this.#start));
		this.#timeline.setAttribute('end', String(this.#end));
		this.#timeline.setAttribute('selected-start', String(this.#selected_start));
		this.#timeline.setAttribute('selected-end', String(this.#selected_end));
	}

	#renderOverview() {
		const width = this.#overview.parentElement.offsetWidth;
		const height = 56;
		const rows = Array.from(this.#spans.values());
		const elements = [];

		this.#overview.setAttribute('viewBox', `0 0 ${width} ${height}`);

		rows.forEach((span, index) => {
			const x1 = this.#valueToRatio(span.start, this.#start, this.#end) * width;
			const x2 = this.#valueToRatio(span.end, this.#start, this.#end) * width;
			const y = 5 + index % 8 * 6;

			const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
			line.setAttribute('x1', String(this.#clamp(x1, 0, width)));
			line.setAttribute('x2', String(this.#clamp(x2, 0, width)));
			line.setAttribute('y1', String(y));
			line.setAttribute('y2', String(y));
			line.setAttribute('stroke', span.color || this.#getColor(index));
			line.setAttribute('stroke-width', '2');
			line.setAttribute('stroke-linecap', 'round');

			elements.push(line);
		});

		this.#overview.replaceChildren(...elements);
	}

	#renderTree() {
		const visible_ids = this.#getSelectedTreeIds();

		this.#tree.items = Array.from(this.#spans.values())
			.filter(span => visible_ids.has(span.id))
			.map(span => ({
				id: span.id,
				parent_id: span.parent_id,
				label: span.name || span.id,
				subtitle: span.operation || '',
				meta: span.count ?? '',
				span
			}));

		this.#tree.expandToDepth(this.#spans.size);
	}

	#renderTimeHeader() {
		const ticks = this.#getTicks();
		const elements = [];
		const range = this.#selected_end - this.#selected_start;

		for (const tick of ticks) {
			const label = document.createElement('span');
			label.textContent = this.#formatTime(tick.value, tick.unit, range);

			const tick_element = document.createElement('div');
			tick_element.classList.add('trace-time-tick');
			tick_element.style.setProperty('--trace-tick-position', tick.position);
			tick_element.appendChild(label);

			elements.push(tick_element);
		}

		this.#time_header.replaceChildren(...elements);
	}

	#renderWaterfall() {
		const ticks = this.#getTicks();
		const rows = this.#tree.visibleItems;
		const elements = [];

		for (const row of rows) {
			elements.push(this.#createWaterfallRow(row));
		}

		this.#waterfall.replaceChildren(...elements);

		for (const tick of ticks) {
			const line = document.createElement('div');
			line.classList.add('trace-grid-line');
			line.style.setProperty('--trace-grid-line-position', tick.position);

			this.#waterfall.appendChild(line);
		}
	}

	#createWaterfallRow(row) {
		const span = row.item.span;
		const row_element = document.createElement('div');

		row_element.classList.add('trace-waterfall-row');
		row_element.dataset.spanId = span.id;

		if (this.#spanIntersectsSelectedRange(span)) {
			const value = Number(span.end) - Number(span.start);

			const label = document.createElement('span');
			label.classList.add('trace-span-label');
			label.textContent = this.#formatTime(value, this.#getTimeUnit(value));

			const bar = document.createElement('div');
			bar.classList.add('trace-span-bar');
			bar.style.setProperty('--trace-span-start', `${this.#getClampedStartRatio(span) * 100}%`);
			bar.style.setProperty('--trace-span-width', `${this.#getClampedWidthRatio(span) * 100}%`);
			bar.style.setProperty('--trace-span-color', span.color || this.#getColor(span.index || 0));
			bar.appendChild(label);

			row_element.appendChild(bar);
		}

		for (const group of this.#getMarkerGroups(span.events)) {
			const marker = document.createElement('span');
			marker.classList.add('trace-event-marker');
			marker.style.setProperty('--trace-event-position', `${group.ratio * 100}%`);

			if (group.events.length > 1) {
				marker.dataset.count = String(group.events.length);
			}

			marker.dataset.hintbox = '1';
			marker.dataset.hintboxStatic = '1';
			marker.dataset.hintboxHtml = group.events.map(event => this.#createTooltipItem(event).outerHTML).join('');
			marker.dataset.hintboxClass = 'trace-tooltip';
			marker.dataset.hintboxPositionFixed = '1';
			marker.dataset.hintboxDelay = '0';

			row_element.appendChild(marker);
		}

		return row_element;
	}

	#getSelectedTreeIds() {
		const ids = new Set();

		for (const span of this.#spans.values()) {
			if (!this.#spanIntersectsSelectedRange(span)) {
				continue;
			}

			let current = span;

			while (current !== undefined) {
				ids.add(current.id);
				current = current.parent_id === null ? undefined : this.#spans.get(current.parent_id);
			}
		}

		return ids;
	}

	#getMarkerGroups(events) {
		const width = Math.max(1, this.#time_header.getBoundingClientRect().width);
		const bucket_size = 10;
		const groups = new Map();

		for (const event of events) {
			if (!this.#valueIntersectsSelectedRange(event.time)) {
				continue;
			}

			const ratio = this.#valueToRatio(event.time, this.#selected_start, this.#selected_end);
			const x = ratio * width;
			const bucket = Math.round(x / bucket_size);
			const key = String(bucket);

			if (!groups.has(key)) {
				groups.set(key, {
					x,
					events: []
				});
			}

			groups.get(key).events.push(event);
		}

		return Array.from(groups.values()).map(group => ({
			ratio: this.#clamp(group.x / width, 0, 1),
			events: group.events
		}));
	}

	#getTimeUnit(value) {
		if (value < 1e-6) {
			return 'ns';
		}

		if (value < 1e-3) {
			return 'µs';
		}

		if (value < 1) {
			return 'ms';
		}

		if (value < 60) {
			return 's';
		}

		if (value < 3600) {
			return 'm';
		}

		if (value < 86400) {
			return 'h';
		}

		return 'd';
	}

	#getTicks() {
		const width = this.#time_header.getBoundingClientRect().width;
		const range = this.#selected_end - this.#selected_start;

		if (width <= TraceViewPage.SCALE_LABEL_GAP || range <= 0) {
			return [];
		}

		const label_gap = this.#getPixelCustomProperty('--trace-label-gap', TraceViewPage.SCALE_LABEL_GAP);
		const num_segments = Math.max(1, Math.floor(width / label_gap));
		const step = range / num_segments;
		const padding_ratio = TraceViewPage.SCALE_OFFSET / width;
		const span_ratio = 1 - 2 * padding_ratio;

		const ticks = [];
		for (let i = 0; i <= num_segments; i++) {
			const ratio = i / num_segments;
			const value = this.#selected_start + range * ratio;

			let position;
			if (i === 0) {
				position = `${TraceViewPage.SCALE_OFFSET}px`;
			}
			else if (i === num_segments) {
				position = `calc(100% - ${TraceViewPage.SCALE_OFFSET}px)`;
			}
			else {
				position = `${(padding_ratio + ratio * span_ratio) * 100}%`;
			}

			const unit = this.#getTimeUnit(value);

			if (i === 1) {
				ticks[0].unit = unit;
			}

			ticks.push({value, step, ratio, position, unit});
		}

		return ticks;
	}

	#createTooltipItem(event) {
		const title = document.createElement('div');
		title.classList.add('trace-tooltip-title');
		title.textContent = event.title || event.name || event.type || 'Event';

		const time = document.createElement('div');
		time.classList.add('trace-tooltip-time');
		time.textContent = this.#formatDuration(Number(event.time));

		const item = document.createElement('div');
		item.classList.add('trace-tooltip-item');
		item.append(title, time);

		if (event.attributes) {
			const attributes = document.createElement('div');
			attributes.classList.add('trace-tooltip-attributes');

			for (const [name, value] of Object.entries(event.attributes)) {
				const attribute_name = document.createElement('span');
				attribute_name.textContent = name;

				const attribute_value = document.createElement('span');
				attribute_value.textContent = String(value);

				const attribute = document.createElement('div');
				attribute.classList.add('trace-tooltip-attribute');
				attribute.append(attribute_name, attribute_value);

				attributes.appendChild(attribute);
			}

			item.appendChild(attributes);
		}

		return item;
	}

	#scheduleRender() {
		if (this.#animation_frame_id !== null) {
			return;
		}

		this.#animation_frame_id = requestAnimationFrame(() => {
			this.#animation_frame_id = null;
			this.#renderTimeHeader();
			this.#renderOverview();
			this.#renderWaterfall();
		});
	}

	#normalizeSelectedRange() {
		this.#selected_start = this.#clamp(this.#selected_start, this.#start, this.#end);
		this.#selected_end = this.#clamp(this.#selected_end, this.#start, this.#end);

		if (this.#selected_end < this.#selected_start) {
			const selected_start = this.#selected_end;

			this.#selected_end = this.#selected_start;
			this.#selected_start = selected_start;
		}
	}

	#spanIntersectsSelectedRange(span) {
		return Number(span.end) >= this.#selected_start && Number(span.start) <= this.#selected_end;
	}

	#valueIntersectsSelectedRange(value) {
		return Number(value) >= this.#selected_start && Number(value) <= this.#selected_end;
	}

	#getClampedStartRatio(span) {
		return this.#valueToRatio(
			this.#clamp(Number(span.start), this.#selected_start, this.#selected_end),
			this.#selected_start,
			this.#selected_end
		);
	}

	#getClampedWidthRatio(span) {
		const start = this.#clamp(Number(span.start), this.#selected_start, this.#selected_end);
		const end = this.#clamp(Number(span.end), this.#selected_start, this.#selected_end);

		return Math.max(0,
			this.#valueToRatio(end, this.#selected_start, this.#selected_end)
			- this.#valueToRatio(start, this.#selected_start, this.#selected_end)
		);
	}

	#getTraceStart() {
		let start = Infinity;

		for (const span of this.#spans.values()) {
			start = Math.min(start, Number(span.start));
		}

		return Number.isFinite(start) ? start : 0;
	}

	#getTraceEnd() {
		let end = -Infinity;

		for (const span of this.#spans.values()) {
			end = Math.max(end, Number(span.end));
		}

		return Number.isFinite(end) ? end : 1000;
	}

	#formatTime(value, unit, range = 0) {
		if (value <= 0) {
			return `0${unit}`;
		}

		switch (unit) {
			case 'ns': {
				const precision = this.#getPrecision(range * 1e9);

				return `${+(value * 1e9).toFixed(precision)}ns`;
			}

			case 'µs': {
				const precision = this.#getPrecision(range * 1e6);

				return `${+(value * 1e6).toFixed(precision)}µs`;
			}

			case 'ms': {
				const precision = this.#getPrecision(range * 1e3);

				return `${+(value * 1e3).toFixed(precision)}ms`;
			}

			case 's': {
				const precision = this.#getPrecision(range);

				return `${+value.toFixed(precision)}s`;
			}

			case 'm': {
				const mins = Math.floor(value / 60);
				const secs = Math.round(value % 60);

				if (secs === 0 || secs === 60) {
					const final_mins = secs === 60 ? mins + 1 : mins;

					return `${final_mins}m`;
				}

				return `${mins}m ${secs}s`;
			}

			case 'h': {
				const hours = Math.floor(value / 3600);
				const mins = Math.round((value % 3600) / 60);

				if (mins === 0) {
					return `${hours}h`;
				}

				return `${hours}h ${mins}m`;
			}

			case 'd': {
				const days = Math.floor(value / 86400);
				const hours = Math.round((value % 86400) / 3600);

				if (hours === 0 || hours === 24) {
					const final_days = hours === 24 ? days + 1 : days;

					return `${final_days}d`;
				}

				return `${days}d ${hours}h`;
			}
		}
	}

	#getPrecision(scaled_range) {
		if (scaled_range <= 0) {
			return 1;
		}

		const log = Math.log10(scaled_range);

		if (log < 0) {
			return Math.min(3, Math.max(1, Math.ceil(-log) + 1));
		}

		return 1;
	}
	#formatDuration(value) {
		if (value < 1e-6) {
			return `${Math.round(value * 1e9)}ns`;
		}

		if (value < 1e-3) {
			return `${Math.round(value * 1e6)}µs`;
		}

		return `${Math.round(value * 1e6) / 1000}ms`;
	}

	#valueToRatio(value, start, end) {
		const range = end - start;

		return range > 0
			? this.#clamp((Number(value) - start) / range, 0, 1)
			: 0;
	}

	#getPixelCustomProperty(name, fallback) {
		const value = Number.parseFloat(getComputedStyle(this.#container).getPropertyValue(name));

		return Number.isFinite(value) ? value : fallback;
	}

	#parseNumber(value, fallback) {
		const number = Number(value);

		return Number.isFinite(number) ? number : fallback;
	}

	#getColor(index) {
		const colors = ['#4c5de5', '#22935f', '#f17c0e', '#9b4cad', '#e93b3b', '#2588d8'];

		return colors[index % colors.length];
	}

	#clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}
}
