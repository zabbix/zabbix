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

	/** @type {HTMLElement} */
	#tooltip;

	/** @type {ResizeObserver | null} */
	#resize_observer = null;

	/** @type {number | null} */
	#animation_frame_id = null;

	/** @type {Map<string, object>} */
	#spans = new Map();

	/** @type {Map<HTMLElement, object[]>} */
	#marker_events = new Map();

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

		this.#tooltip = document.createElement('div');
		this.#tooltip.className = 'trace-tooltip';
		document.body.appendChild(this.#tooltip);

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

		this.#tooltip.remove();
	}

	#addEventListeners() {
		this.#timeline.addEventListener('input', this.#onTimelineInput);
		this.#timeline.addEventListener('change', this.#onTimelineChange);
		this.#tree.addEventListener('toggle', this.#onTreeToggle);
		this.#tree.addEventListener('select', this.#onTreeSelect);
		this.#waterfall.addEventListener('pointerover', this.#onWaterfallPointerOver);
		this.#waterfall.addEventListener('pointerout', this.#onWaterfallPointerOut);
		this.#container.querySelector('[data-trace-scroll]').addEventListener('scroll', this.#onScroll);
	}

	#removeEventListeners() {
		this.#timeline.removeEventListener('input', this.#onTimelineInput);
		this.#timeline.removeEventListener('change', this.#onTimelineChange);
		this.#tree.removeEventListener('toggle', this.#onTreeToggle);
		this.#tree.removeEventListener('select', this.#onTreeSelect);
		this.#waterfall.removeEventListener('pointerover', this.#onWaterfallPointerOver);
		this.#waterfall.removeEventListener('pointerout', this.#onWaterfallPointerOut);
		this.#container.querySelector('[data-trace-scroll]').removeEventListener('scroll', this.#onScroll);
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

	#onWaterfallPointerOver = event => {
		const marker = event.target instanceof Element
			? event.target.closest('.trace-event-marker')
			: null;

		if (marker === null || !this.#waterfall.contains(marker)) {
			return;
		}

		const events = this.#marker_events.get(marker) || [];

		if (events.length > 0) {
			this.#showTooltip(marker, events);
		}
	};

	#onWaterfallPointerOut = event => {
		const marker = event.target instanceof Element
			? event.target.closest('.trace-event-marker')
			: null;

		if (marker === null) {
			return;
		}

		if (event.relatedTarget instanceof Node && marker.contains(event.relatedTarget)) {
			return;
		}

		this.#hideTooltip();
	};

	#onScroll = () => {
		this.#hideTooltip();
	};

	setTrace(trace) {
		this.#trace = trace;
		this.#spans.clear();

		const spans = Array.isArray(trace?.spans) ? trace.spans : [];

		for (const span of spans) {
			this.#spans.set(String(span.id), {
				...span,
				id: String(span.id),
				parentId: span.parentId == null ? null : String(span.parentId),
				events: Array.isArray(span.events) ? span.events : []
			});
		}

		this.#start = Number.isFinite(Number(trace?.start)) ? Number(trace.start) : this.#getTraceStart();
		this.#end = Number.isFinite(Number(trace?.end)) ? Number(trace.end) : this.#getTraceEnd();

		if (this.#end <= this.#start) {
			this.#end = this.#start + 1;
		}

		this.#selected_start = Number.isFinite(Number(trace?.selectedStart)) ? Number(trace.selectedStart) : this.#start;
		this.#selected_end = Number.isFinite(Number(trace?.selectedEnd)) ? Number(trace.selectedEnd) : this.#end;

		this.#normalizeSelectedRange();
		this.#render();
		this.#tree.expandToDepth(this.#spans.size);
		this.#renderWaterfall();
	}

	#applyTimelineValue(value) {
		if (value === null || typeof value !== 'object') {
			return;
		}

		this.#selected_start = this.#parseNumber(value.selectedStart, this.#selected_start);
		this.#selected_end = this.#parseNumber(value.selectedEnd, this.#selected_end);
		this.#normalizeSelectedRange();

		this.#renderTree();
		this.#renderTimeHeader();
		this.#renderWaterfall();

		this.#container.dispatchEvent(new CustomEvent('range-change', {
			bubbles: true,
			detail: {
				start: this.#start,
				end: this.#end,
				selectedStart: this.#selected_start,
				selectedEnd: this.#selected_end
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
		const width = 1000;
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
				parentId: span.parentId,
				label: span.name || span.id,
				subtitle: span.operation || '',
				meta: span.count ?? '',
				span
			}));
	}

	#renderTimeHeader() {
		const ticks = this.#getTicks();
		const elements = [];

		for (const tick of ticks) {
			const tick_element = document.createElement('div');
			const label = document.createElement('span');

			tick_element.className = 'trace-time-tick';
			tick_element.style.setProperty('--trace-tick-position', `${tick.ratio * 100}%`);

			label.textContent = this.#formatTime(tick.value, tick.step);
			tick_element.appendChild(label);
			elements.push(tick_element);
		}

		this.#time_header.replaceChildren(...elements);
	}

	#renderWaterfall() {
		const ticks = this.#getTicks();
		const rows = this.#tree.visibleItems;
		const elements = [];

		this.#marker_events.clear();

		for (const row of rows) {
			elements.push(this.#createWaterfallRow(row, ticks));
		}

		this.#waterfall.replaceChildren(...elements);
	}

	#createWaterfallRow(row, ticks) {
		const span = row.item.span;
		const row_element = document.createElement('div');

		row_element.className = 'trace-waterfall-row';
		row_element.dataset.spanId = span.id;

		for (const tick of ticks) {
			const line = document.createElement('div');
			line.className = 'trace-grid-line';
			line.style.setProperty('--trace-grid-line-position', `${tick.ratio * 100}%`);
			row_element.appendChild(line);
		}

		if (this.#spanIntersectsSelectedRange(span)) {
			const bar = document.createElement('div');
			const label = document.createElement('span');

			bar.className = 'trace-span-bar';
			bar.style.setProperty('--trace-span-start', `${this.#getClampedStartRatio(span) * 100}%`);
			bar.style.setProperty('--trace-span-width', `${this.#getClampedWidthRatio(span) * 100}%`);
			bar.style.setProperty('--trace-span-color', span.color || this.#getColor(row.index || 0));

			label.className = 'trace-span-label';
			label.textContent = this.#formatDuration(Number(span.end) - Number(span.start));

			bar.appendChild(label);
			row_element.appendChild(bar);
		}

		for (const group of this.#getMarkerGroups(span.events)) {
			const marker = document.createElement('span');

			marker.className = 'trace-event-marker';
			marker.style.setProperty('--trace-event-position', `${group.ratio * 100}%`);

			if (group.events.length > 1) {
				marker.dataset.count = String(group.events.length);
			}

			this.#marker_events.set(marker, group.events);
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
				current = current.parentId === null ? undefined : this.#spans.get(current.parentId);
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

	#getTicks() {
		const width = this.#time_header.getBoundingClientRect().width;
		const range = this.#selected_end - this.#selected_start;

		if (width <= 0 || range <= 0) {
			return [];
		}

		const label_gap = this.#getPixelCustomProperty('--trace-label-gap', 120);
		const step = this.#getTickStep(range, width, label_gap);
		const first_tick = Math.ceil(this.#selected_start / step) * step;
		const ticks = [];

		for (let value = first_tick; value <= this.#selected_end; value += step) {
			ticks.push({
				value,
				step,
				ratio: this.#valueToRatio(value, this.#selected_start, this.#selected_end)
			});
		}

		if (ticks.length === 0 || ticks[0].value !== this.#selected_start) {
			ticks.unshift({
				value: this.#selected_start,
				step,
				ratio: 0
			});
		}

		if (ticks[ticks.length - 1].value !== this.#selected_end) {
			ticks.push({
				value: this.#selected_end,
				step,
				ratio: 1
			});
		}

		return ticks;
	}

	#getTickStep(range, width, label_gap) {
		const target_count = Math.max(2, Math.floor(width / label_gap));
		const target_step = range / target_count;
		const steps = [
			1, 2, 5, 10, 20, 50, 100, 200, 500,
			1000, 2000, 5000, 10000, 15000, 30000,
			60000, 120000, 300000, 600000, 900000, 1800000,
			3600000, 7200000, 10800000, 21600000, 43200000,
			86400000, 172800000, 604800000, 1209600000,
			2592000000, 7776000000, 15552000000, 31536000000
		];

		return steps.find(step => step >= target_step) || steps[steps.length - 1];
	}

	#showTooltip(anchor, events) {
		this.#tooltip.replaceChildren(...events.map(event => this.#createTooltipItem(event)));
		this.#tooltip.toggleAttribute('data-visible', true);

		const anchor_rect = anchor.getBoundingClientRect();
		const tooltip_rect = this.#tooltip.getBoundingClientRect();

		let left = anchor_rect.left + anchor_rect.width / 2 - tooltip_rect.width / 2;
		let top = anchor_rect.bottom + 8;

		left = Math.max(8, Math.min(left, window.innerWidth - tooltip_rect.width - 8));

		if (top + tooltip_rect.height > window.innerHeight - 8) {
			top = anchor_rect.top - tooltip_rect.height - 8;
		}

		this.#tooltip.style.left = `${left}px`;
		this.#tooltip.style.top = `${Math.max(8, top)}px`;
	}

	#hideTooltip() {
		this.#tooltip.toggleAttribute('data-visible', false);
	}

	#createTooltipItem(event) {
		const item = document.createElement('div');
		item.className = 'trace-tooltip-item';

		const title = document.createElement('div');
		title.className = 'trace-tooltip-title';
		title.textContent = event.title || event.name || event.type || 'Event';

		const time = document.createElement('div');
		time.className = 'trace-tooltip-time';
		time.textContent = this.#formatDuration(Number(event.time));

		item.append(title, time);

		if (event.description) {
			const description = document.createElement('div');
			description.className = 'trace-tooltip-description';
			description.textContent = event.description;
			item.appendChild(description);
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

	#formatTime(value, step) {
		const duration = this.#end - this.#start;

		if (duration <= 10) {
			return this.#formatDuration(value);
		}

		const date = new Date(value);
		const ms = String(date.getMilliseconds()).padStart(3, '0');
		const seconds = String(date.getSeconds()).padStart(2, '0');
		const minutes = String(date.getMinutes()).padStart(2, '0');
		const hours = String(date.getHours()).padStart(2, '0');

		if (step < 1000) {
			return `${seconds}.${ms}s`;
		}

		if (step < 60000) {
			return `${hours}:${minutes}:${seconds}`;
		}

		return `${hours}:${minutes}`;
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
