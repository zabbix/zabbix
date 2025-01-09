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


class CSortable {

	/**
	 * Class applied to a sortable container.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_CLASS = 'sortable';

	/**
	 * Class applied to a sortable container if sorting is disabled or there are less than two items to sort.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_DISABLED = 'sortable-disabled';

	/**
	 * Class applied to a sortable container while transitions are temporarily disabled.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_TRANSITIONS_DISABLED = 'sortable-transitions-disabled';

	/**
	 * Class applied to a sortable container while item is being dragged.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_DRAGGING = 'sortable-dragging';

	/**
	 * Class applied to item elements.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_ITEM = 'sortable-item';

	/**
	 * Class applied to frozen item elements.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_ITEM_FROZEN = 'sortable-item-frozen';

	/**
	 * Class applied to elements of item while it is being dragged.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_ITEM_DRAGGING = 'sortable-item-dragging';

	/**
	 * Event fired on start of dragging of an item.
	 *
	 * @type {string}
	 */
	static EVENT_DRAG_START = 'sortable-drag-start';

	/**
	 * Event fired on overtaking an item.
	 *
	 * @type {string}
	 */
	static EVENT_DRAG_OVERTAKE = 'sortable-drag-overtake';

	/**
	 * Event fired on end of dragging of an item.
	 *
	 * @type {string}
	 */
	static EVENT_DRAG_END = 'sortable-drag-end';

	/**
	 * Event fired on end of dragging of an item, if sort order has changed.
	 *
	 * @type {string}
	 */
	static EVENT_SORT = 'sortable-sort';

	static TRANSITIONS_ENDED_END_DRAGGING = 'end-dragging';

	static DRAG_SCROLL_TIMEOUT_MS = 200;

	static LISTENERS_OFF = 'off';
	static LISTENERS_SCROLL = 'scroll';
	static LISTENERS_SCROLL_DRAG = 'scroll-drag';
	static LISTENERS_DRAG_END = 'drag-end';

	#target;

	#is_horizontal;

	#selector_span;
	#selector_handle;

	#freeze_start;
	#freeze_end;

	#is_enabled = false;
	#is_enabled_sorting;

	#is_visible;

	#items = [];

	#scroll_pos = 0;

	#mouse_down_pos = 0;

	#is_dragging = false;
	#drag_item = null;
	#drag_item_rendering_animation_frame = null;
	#drag_index = -1;
	#drag_overtake = -1;
	#drag_delta = 0;
	#drag_style;

	#drag_scroll_timeout = null;
	#drag_scroll_direction = 0;

	#resize_observer;
	#resize_observer_connected = false;

	#mutation_observer;
	#mutation_observer_connected = false;

	#intersection_observer;
	#intersection_observer_connected = false;

	#transitions = new Map();
	#transitions_end_callbacks = new Map();

	/**
	 * Create CSortable instance.
	 *
	 * @param {HTMLElement} target             Sortable container.
	 * @param {boolean}     is_horizontal      Whether sorting is horizontally oriented.
	 * @param {string}      selector_span      Selector for matching first child element of multi-element items.
	 * @param {string}      selector_handle    Selector for matching a drag handle.
	 * @param {number}      freeze_start       Number of items to freeze at the start.
	 * @param {number}      freeze_end         Number of items to freeze at the end.
	 * @param {boolean}     enable             Whether to enable the instance initially.
	 * @param {boolean}     enable_sorting     Whether to enable sorting initially (or just scrolling).
	 *
	 * @returns {CSortable}
	 */
	constructor(target, {
		is_horizontal = false,

		selector_span = '',
		selector_handle = '',

		freeze_start = 0,
		freeze_end = 0,

		enable = true,
		enable_sorting = true
	} = {}) {
		this.#target = target;
		this.#target.classList.add(CSortable.ZBX_STYLE_CLASS);
		this.#target[is_horizontal ? 'scrollLeft' : 'scrollTop'] = 0;

		this.#is_horizontal = is_horizontal;

		this.#selector_span = selector_span;
		this.#selector_handle = selector_handle;

		this.#freeze_start = freeze_start;
		this.#freeze_end = freeze_end;

		this.#is_enabled_sorting = enable_sorting;

		this.#resize_observer = new ResizeObserver(this.#listeners.resize);
		this.#mutation_observer = new MutationObserver(this.#listeners.mutation);
		this.#intersection_observer = new IntersectionObserver(this.#listeners.intersection);

		this.#is_visible = this.#target.children.length === 0 || this.#getTargetLoc().dim > 0;

		if (enable) {
			this.enable();
		}
		else {
			this.#target.classList.add(CSortable.ZBX_STYLE_DISABLED);
		}
	}

	/**
	 * Get sortable container.
	 *
	 * @returns {HTMLElement}
	 */
	getTarget() {
		return this.#target;
	}

	/**
	 * Enable or disable the instance.
	 *
	 * @param {boolean} enable
	 *
	 * @returns {boolean}  Previous state.
	 */
	enable(enable = true) {
		if (enable === this.#is_enabled) {
			return enable;
		}

		if (enable) {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#observeResize();
			this.#observeMutations();
			this.#observeIntersection();
			this.#updateItems();
			this.#render();
		}
		else {
			this.#toggleListeners(CSortable.LISTENERS_OFF);
			this.#observeResize(false);
			this.#observeMutations(false);
			this.#observeIntersection(false);
			this.#cancelDragging();
		}

		this.#is_enabled = enable;

		this.#updateTargetDisabled();

		return !enable;
	}

	/**
	 * Enable or disable sorting.
	 *
	 * @param {boolean} enable_sorting
	 *
	 * @returns {boolean}  Previous state.
	 */
	enableSorting(enable_sorting = true) {
		if (this.#is_enabled && this.#is_enabled_sorting && !enable_sorting) {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#cancelDragging();
		}

		this.#is_enabled_sorting = enable_sorting;

		this.#updateTargetDisabled();

		return !enable_sorting;
	}

	/**
	 * Scroll the target item into view.
	 *
	 * @param {HTMLElement} element
	 * @param {boolean}     immediate
	 */
	scrollIntoView(element, {immediate = false} = {}) {
		if (this.#is_dragging || this.#drag_item !== null) {
			return;
		}

		this.#update();

		const item = this.#matchItem(element);

		if (item !== null) {
			if (this.#revealItem(item) !== 0) {
				if (immediate) {
					this.#enableTransitions(false);
				}
			}
		}
	}

	/**
	 * Check whether the sortable container is scrollable.
	 *
	 * @returns {boolean}
	 */
	isScrollable() {
		this.#update();

		return this.#getScrollMax() > 0;
	}

	/**
	 * Apply changes to items without modifying their order, size or positioning.
	 *
	 * @param {function} callback
	 */
	mutate(callback) {
		const observe_mutations = this.#observeMutations(false);

		callback();

		this.#observeMutations(observe_mutations);
	}

	/**
	 * Attach event listener.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CSortable}
	 */
	on(type, listener, options = false) {
		this.#target.addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CSortable}
	 */
	off(type, listener, options = false) {
		this.#target.removeEventListener(type, listener, options);

		return this;
	}

	// Private methods.

	#fire(type, detail = {}, options = {}) {
		return this.#target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}

	#update() {
		this.#toggleListeners(CSortable.LISTENERS_SCROLL);
		this.#cancelDragging();
		this.#updateItems();
		this.#render();
		this.#updateTargetDisabled();
	}

	#updateItems() {
		this.#items = [];

		for (const element of this.#target.children) {
			if (this.#selector_span === '' || element.matches(this.#selector_span)) {
				this.#items.push({
					elements: [],
					elements_live: [],
					pos: 0,
					dim: 0,
					rel: 0
				});
			}

			this.#items.at(-1).elements.push(element);
		}

		for (const item of this.#items) {
			item.elements_live = this.#getLiveElements(item.elements);
		}

		this.mutate(() => {
			for (const [index, item] of this.#items.entries()) {
				for (const element of item.elements_live) {
					element.classList.add(CSortable.ZBX_STYLE_ITEM);
					element.classList.toggle(CSortable.ZBX_STYLE_ITEM_FROZEN,
						index < this.#freeze_start || index > this.#items.length - 1 - this.#freeze_end
					);
				}
			}
		});

		this.#updateItemsLoc();
	}

	#updateTargetDisabled() {
		this.#target.classList.toggle(CSortable.ZBX_STYLE_DISABLED,
			!this.#is_enabled || !this.#is_enabled_sorting
				|| this.#items.length - this.#freeze_start - this.#freeze_end < 2
		);
	}

	#updateItemsOrder() {
		this.#target.innerHTML = '';

		for (const item of this.#items) {
			this.#target.append(...item.elements);
		}
	}

	#updateItemsLoc() {
		const target_pos = this.#getTargetLoc().pos;

		for (const item of this.#items) {
			const loc = this.#getItemLoc(item);

			item.pos = loc.pos - target_pos;
			item.dim = loc.dim;
		}
	}

	#getLiveElements(elements) {
		const walk_through = ['contents', 'table-row-group', 'table-row', 'table-column-group', 'table-column'];

		let live_elements = [];

		for (const element of elements) {
			if (walk_through.includes(getComputedStyle(element).display)) {
				live_elements = [...live_elements, ...this.#getLiveElements(element.children)];
			}
			else {
				live_elements.push(element);
			}
		}

		return live_elements;
	}

	#getTargetLoc() {
		const rect = this.#target.getBoundingClientRect();

		return {
			pos: this.#is_horizontal ? rect.x : rect.y,
			dim: this.#is_horizontal ? rect.width : rect.height
		};
	}

	#getItemLoc(item) {
		const transitional_offset = this.#getItemTransitionalOffset(item);

		let pos = 0;
		let pos_to = 0;

		for (const element of item.elements_live) {
			const rect = element.getBoundingClientRect();

			const loc = {
				pos: this.#scroll_pos + (this.#is_horizontal ? rect.x : rect.y) - transitional_offset,
				dim: this.#is_horizontal ? rect.width : rect.height
			};

			if (loc.dim === 0) {
				continue;
			}

			if (pos === 0 && pos_to === 0) {
				pos = loc.pos;
				pos_to = loc.pos + loc.dim;
			}
			else {
				pos = Math.min(pos, loc.pos);
				pos_to = Math.max(pos_to, loc.pos + loc.dim);
			}
		}

		return {
			pos,
			dim: pos_to - pos
		};
	}

	#getItemTransitionalOffset(item) {
		if (item.elements_live.length === 0) {
			return 0;
		}

		const computed_style = getComputedStyle(item.elements_live[0]);
		const computed_style_pos = this.#is_horizontal ? computed_style.left : computed_style.top;

		return this.#scroll_pos + (computed_style_pos === 'auto' ? 0 : parseFloat(computed_style_pos || '0'));
	}

	#enableTransitions(enable = true) {
		this.#target.classList.toggle(CSortable.ZBX_STYLE_TRANSITIONS_DISABLED, !enable);
	}

	#render() {
		this.#enableTransitions();

		for (const item of this.#items) {
			if (!this.#is_dragging || item !== this.#drag_item) {
				this.#applyRel(item, item.rel - this.#scroll_pos);
			}
		}
	}

	#doDragItemRendering() {
		const constraints_by_target = this.#getDragRelConstraintsByTarget();
		const constraints_by_freeze = this.#getDragRelConstraintsByFreeze();

		const drag_rel = this.#constrainDragRel(this.#drag_item.rel, [
			constraints_by_target,
			this.#getDragRelConstraintsByFreeze()
		]);

		const drag_rel_real_time = this.#constrainDragRel(this.#drag_item.rel, [
			constraints_by_target,
			this.#getDragRelConstraintsByFreezeRealTime(constraints_by_freeze)
		]);

		this.#applyRel(this.#drag_item, drag_rel_real_time);

		const pos_delta = this.#items[this.#drag_index].dim + this.#items[this.#freeze_start + 1].pos
			- this.#items[this.#freeze_start].pos - this.#items[this.#freeze_start].dim;

		const items = [...this.#items];

		items.splice(this.#drag_index, 1);

		const items_loc = [];

		for (const [index, item] of items.entries()) {
			items_loc.push({
				pos: item.pos + (index >= this.#drag_index ? -pos_delta : 0),
				dim: item.dim
			});
		}

		const drag_pos = this.#scroll_pos + this.#drag_item.pos + drag_rel;

		let overtake;

		for (overtake = 0; overtake < items_loc.length; overtake++) {
			if (overtake < items_loc.length && items_loc[overtake].pos + items_loc[overtake].dim / 2 >= drag_pos) {
				break;
			}
		}

		if (overtake !== this.#drag_overtake) {
			for (const [index, item] of items.entries()) {
				item.rel = (index >= overtake ? items_loc[index].pos + pos_delta : items_loc[index].pos) - item.pos;
			}

			this.#drag_overtake = overtake;

			this.#fire(CSortable.EVENT_DRAG_OVERTAKE, {
				index: this.#drag_index,
				index_to: this.#drag_overtake
			});

			this.#render();
		}

		if (this.#drag_item_rendering_animation_frame === null) {
			this.#drag_item_rendering_animation_frame = requestAnimationFrame(() => {
				this.#drag_item_rendering_animation_frame = null;
				this.#doDragItemRendering();
			});
		}
	}

	#cancelDragItemRendering() {
		if (this.#drag_item_rendering_animation_frame !== null) {
			cancelAnimationFrame(this.#drag_item_rendering_animation_frame);
			this.#drag_item_rendering_animation_frame = null;
		}
	}

	#getDragRelConstraintsByTarget() {
		return {
			min: -this.#drag_item.pos,
			max: this.#getTargetLoc().dim - this.#drag_item.pos - this.#drag_item.dim
		};
	}

	#getDragRelConstraintsByFreeze() {
		const item_min_pos = this.#items[this.#freeze_start].pos;
		const item_max_pos = this.#items.at(-1 - this.#freeze_end).pos;
		const item_max_dim = this.#items.at(-1 - this.#freeze_end).dim;

		return {
			min: item_min_pos - this.#drag_item.pos - this.#scroll_pos,
			max: item_max_pos + item_max_dim - this.#drag_item.pos - this.#drag_item.dim - this.#scroll_pos
		};
	}

	#getDragRelConstraintsByFreezeRealTime(constraints = this.#getDragRelConstraintsByFreeze()) {
		if (this.#freeze_start === 0 && this.#freeze_end === 0) {
			return constraints;
		}

		const transitional_offset = this.#getItemTransitionalOffset(
			this.#items[this.#freeze_start > 0 ? 0 : this.#items.length - 1]
		);

		return {
			min: constraints.min + transitional_offset,
			max: constraints.max + transitional_offset
		};
	}

	#constrainDragRel(rel, constraints) {
		for (const {min, max} of constraints) {
			rel = Math.max(Math.min(rel, max), min);
		}

		return rel;
	}

	#matchItem(element) {
		for (const item of this.#items) {
			for (const item_element of item.elements) {
				if (item_element.contains(element)) {
					return item;
				}
			}
		}

		return null;
	}

	#applyRel(item, rel) {
		for (const element of item.elements_live) {
			element.style[this.#is_horizontal ? 'left' : 'top'] = `${rel}px`;
		}
	}

	#getScrollMax() {
		if (this.#items.length === 0) {
			return 0;
		}

		const scroll_max = this.#items.at(-1).pos + this.#items.at(-1).dim - this.#getTargetLoc().dim;

		return scroll_max >= 0.1 ? scroll_max : 0;
	}

	#scrollTo(pos) {
		const pos_old = this.#scroll_pos;
		const pos_new = Math.max(0, Math.min(this.#getScrollMax(), pos));

		this.#scroll_pos = pos_new;
		this.#render();

		return pos_new - pos_old;
	}

	#scrollRel(pos_rel) {
		return this.#scrollTo(this.#scroll_pos + pos_rel);
	}

	#revealItem(item) {
		const index = this.#items.indexOf(item);

		if (index === 0) {
			return this.#scrollTo(0);
		}
		else if (index === this.#items.length - 1) {
			return this.#scrollTo(this.#getScrollMax());
		}

		const pos = item.pos + item.rel;

		return this.#scrollTo(Math.min(pos, Math.max(this.#scroll_pos, pos + item.dim - this.#getTargetLoc().dim)));
	}

	#startDragging(client_pos) {
		this.#is_dragging = true;

		this.#target.classList.add(CSortable.ZBX_STYLE_DRAGGING);

		this.mutate(() => {
			for (const element of this.#drag_item.elements_live) {
				element.classList.add(CSortable.ZBX_STYLE_ITEM_DRAGGING);
			}
		});

		this.#drag_item.rel -= this.#scroll_pos;
		this.#drag_delta = this.#drag_item.rel - client_pos;

		this.#drag_style = document.createElement('style');
		document.head.appendChild(this.#drag_style);
		this.#drag_style.sheet.insertRule(
			'* { user-select: none; pointer-events: none; cursor: grabbing !important; }'
		);

		this.#fire(CSortable.EVENT_DRAG_START, {index: this.#drag_index});
	}

	#endDragging(success = true) {
		this.#is_dragging = false;

		this.#drag_style.remove();

		if (success) {
			for (const item of this.#items) {
				item.rel = 0;
			}

			this.#render();

			this.#items.splice(this.#drag_overtake, 0, ...this.#items.splice(this.#drag_index, 1));
		}

		this.#target.classList.remove(CSortable.ZBX_STYLE_DRAGGING);

		this.mutate(() => {
			for (const item of this.#items) {
				for (const element of item.elements_live) {
					element.classList.remove(CSortable.ZBX_STYLE_ITEM_DRAGGING);
				}
			}
		});

		if (success) {
			this.#updateItemsOrder();
			this.#updateItemsLoc();

			this.#revealItem(this.#drag_item);

			for (const element of this.#drag_item.elements) {
				if (element.matches('[tabindex]:not([tabindex="-1"])')) {
					element.focus();
					break;
				}
			}

			this.#fire(CSortable.EVENT_DRAG_END, {
				index: this.#drag_index,
				index_to: this.#drag_overtake
			});

			if (this.#drag_overtake !== this.#drag_index) {
				this.#fire(CSortable.EVENT_SORT, {
					index: this.#drag_index,
					index_to: this.#drag_overtake
				});
			}
		}
	}

	#cancelDragging() {
		if (this.#is_dragging) {
			this.#rejectTransitionEndedPromise(CSortable.TRANSITIONS_ENDED_END_DRAGGING);

			this.#cancelDragScrolling();
			this.#cancelDragItemRendering();
			this.#endDragging(false);

			this.#drag_item = null;
		}
	}

	#doDragScrolling(direction = this.#drag_scroll_direction) {
		if (this.#drag_scroll_timeout !== null) {
			clearTimeout(this.#drag_scroll_timeout);
		}

		this.#drag_scroll_direction = direction;

		this.#drag_scroll_timeout = setTimeout(() => {
			this.#drag_scroll_timeout = null;

			let index = this.#drag_overtake;

			if (this.#drag_scroll_direction === -1) {
				if (index <= this.#drag_index) {
					index--;
				}
			}
			else {
				if (index >= this.#drag_index) {
					index++;
				}
			}

			if (index >= this.#freeze_start && index <= this.#items.length - 1 - this.#freeze_end) {
				this.#revealItem(this.#items[index]);
				this.#doDragScrolling();
			}
		}, CSortable.DRAG_SCROLL_TIMEOUT_MS);
	}

	#cancelDragScrolling() {
		if (this.#drag_scroll_timeout !== null) {
			clearTimeout(this.#drag_scroll_timeout);
			this.#drag_scroll_timeout = null;
		}
	}

	#toggleListeners(mode) {
		this.#target.removeEventListener('mousedown', this.#listeners.mouseDown);
		this.#target.removeEventListener('wheel', this.#listeners.wheel);
		this.#target.removeEventListener('keydown', this.#listeners.keyDown);
		this.#target.removeEventListener('focusin', this.#listeners.focusIn);
		this.#target.removeEventListener('transitionrun', this.#listeners.transitionRun);
		this.#target.removeEventListener('transitionend', this.#listeners.transitionEnd);
		this.#target.removeEventListener('transitioncancel', this.#listeners.transitionCancel);

		removeEventListener('mousemove', this.#listeners.mouseMove);
		removeEventListener('mouseup', this.#listeners.mouseUp);
		removeEventListener('wheel', this.#listeners.wheel, {capture: true});

		switch (mode) {
			case CSortable.LISTENERS_SCROLL:
				this.#target.addEventListener('mousedown', this.#listeners.mouseDown);
				this.#target.addEventListener('wheel', this.#listeners.wheel);
				this.#target.addEventListener('keydown', this.#listeners.keyDown);
				this.#target.addEventListener('focusin', this.#listeners.focusIn);
				this.#target.addEventListener('transitionrun', this.#listeners.transitionRun);
				this.#target.addEventListener('transitionend', this.#listeners.transitionEnd);
				this.#target.addEventListener('transitioncancel', this.#listeners.transitionCancel);

				break;

			case CSortable.LISTENERS_SCROLL_DRAG:
				this.#target.addEventListener('mousedown', this.#listeners.mouseDown);
				this.#target.addEventListener('transitionrun', this.#listeners.transitionRun);
				this.#target.addEventListener('transitionend', this.#listeners.transitionEnd);
				this.#target.addEventListener('transitioncancel', this.#listeners.transitionCancel);

				addEventListener('mousemove', this.#listeners.mouseMove);
				addEventListener('mouseup', this.#listeners.mouseUp);
				addEventListener('wheel', this.#listeners.wheel, {passive: false, capture: true});

				break;

			case CSortable.LISTENERS_DRAG_END:
				this.#target.addEventListener('transitionrun', this.#listeners.transitionRun);
				this.#target.addEventListener('transitionend', this.#listeners.transitionEnd);
				this.#target.addEventListener('transitioncancel', this.#listeners.transitionCancel);

				break;
		}
	}

	#observeResize(observe_resize = true) {
		if (observe_resize === this.#resize_observer_connected) {
			return observe_resize;
		}

		if (observe_resize) {
			this.#resize_observer.observe(this.#target);
		}
		else {
			this.#resize_observer.disconnect();
		}

		this.#resize_observer_connected = observe_resize;

		return !observe_resize;
	}

	#observeMutations(observe_mutations = true) {
		if (observe_mutations === this.#mutation_observer_connected) {
			return observe_mutations;
		}

		if (observe_mutations) {
			this.#mutation_observer.observe(this.#target, {
				childList: true
			});

			for (const item of this.#items) {
				for (const element of item.elements) {
					this.#mutation_observer.observe(element, {
						subtree: true,
						childList: true,
						attributes: true,
						characterData: true
					});
				}
			}
		}
		else {
			this.#mutation_observer.disconnect();
		}

		this.#mutation_observer_connected = observe_mutations;

		return !observe_mutations;
	}

	#observeIntersection(observe_intersection = true) {
		if (observe_intersection === this.#intersection_observer_connected) {
			return observe_intersection;
		}

		if (observe_intersection) {
			this.#intersection_observer.observe(this.#target);
		}
		else {
			this.#intersection_observer.disconnect();
		}

		this.#intersection_observer_connected = observe_intersection;

		return !observe_intersection;
	}

	#hasTransitions() {
		return this.#transitions.size > 0;
	}

	#promiseTransitionsEnded(key) {
		return new Promise((resolve, reject) => {
			this.#transitions_end_callbacks.set(key, success => success ? resolve() : reject());
		});
	}

	#rejectTransitionEndedPromise(key) {
		if (this.#transitions_end_callbacks.has(key)) {
			this.#transitions_end_callbacks.get(key)(false);
			this.#transitions_end_callbacks.delete(key);
		}
	}

	#listeners = {
		mouseDown: (e) => {
			if (this.#hasTransitions()) {
				return;
			}

			this.#mouse_down_pos = this.#is_horizontal ? e.clientX : e.clientY;

			const pos = this.#scroll_pos + this.#mouse_down_pos - this.#getTargetLoc().pos;

			for (let index = 0; index < this.#items.length; index++) {
				const item = this.#items[index];

				if (pos >= item.pos + item.rel && pos < item.pos + item.dim + item.rel) {
					this.#revealItem(item);

					if (!this.#is_enabled_sorting
							|| index < this.#freeze_start
							|| index >= this.#items.length - this.#freeze_end
							|| this.#items.length - this.#freeze_start - this.#freeze_end < 2) {
						break;
					}

					if (this.#selector_handle !== '') {
						const handle = e.target.closest(this.#selector_handle);

						if (handle === null || !this.#target.contains(handle)) {
							break;
						}
					}

					this.#drag_item = item;
					this.#drag_index = index;
					this.#drag_overtake = index;

					this.#toggleListeners(CSortable.LISTENERS_SCROLL_DRAG);

					e.preventDefault();

					break;
				}
			}
		},

		mouseMove: (e) => {
			if (!this.#is_dragging) {
				this.#startDragging(this.#mouse_down_pos);
				this.#doDragItemRendering();
			}

			const rel_old = this.#drag_item.rel;

			this.#drag_item.rel = this.#drag_delta + (this.#is_horizontal ? e.clientX : e.clientY);

			const constraints_by_target = this.#getDragRelConstraintsByTarget();
			const constraints_by_freeze = this.#getDragRelConstraintsByFreeze();

			const rel_new = this.#constrainDragRel(this.#drag_item.rel, [constraints_by_target, constraints_by_freeze]);

			if (rel_new === constraints_by_target.min && rel_new <= rel_old && this.#drag_item.rel < rel_new) {
				this.#doDragScrolling(-1);
			}
			else if (rel_new === constraints_by_target.max && rel_new >= rel_old && this.#drag_item.rel > rel_new) {
				this.#doDragScrolling(1);
			}
			else if (rel_new !== constraints_by_target.min && rel_new !== constraints_by_target.max) {
				this.#cancelDragScrolling();
			}
		},

		mouseUp: () => {
			if (this.#is_dragging) {
				this.#toggleListeners(CSortable.LISTENERS_DRAG_END);

				this.#cancelDragScrolling();

				Promise.resolve()
					.then(() => {
						if (this.#hasTransitions()) {
							return this.#promiseTransitionsEnded(CSortable.TRANSITIONS_ENDED_END_DRAGGING);
						}
					})
					.then(() => {
						this.#cancelDragItemRendering();
						this.#endDragging();
					})
					.finally(() => {
						this.#toggleListeners(CSortable.LISTENERS_SCROLL);
						this.#drag_item = null;
					});
			}
			else {
				this.#toggleListeners(CSortable.LISTENERS_SCROLL);
				this.#drag_item = null;
			}
		},

		wheel: (e) => {
			if (!this.#is_dragging && this.#drag_item !== null) {
				this.#startDragging(this.#mouse_down_pos);
				this.#doDragItemRendering();
			}

			this.#cancelDragScrolling();

			if (this.#scrollRel(e.deltaY !== 0 ? e.deltaY : e.deltaX) !== 0
					|| this.#is_dragging
					|| this.#hasTransitions()
					|| (this.#is_horizontal && this.#getScrollMax() > 0)) {
				e.preventDefault();
			}

			if (this.#is_dragging) {
				e.stopImmediatePropagation();
			}
		},

		keyDown: (e) => {
			if (!this.#is_enabled_sorting) {
				return;
			}

			let direction;

			if (e.ctrlKey && (e.key === 'ArrowLeft' || e.key === 'ArrowUp')) {
				direction = -1;
			}
			else if (e.ctrlKey && (e.key === 'ArrowRight' || e.key === 'ArrowDown')) {
				direction = 1;
			}
			else {
				return;
			}

			const item = this.#matchItem(e.target);

			if (item === null) {
				return;
			}

			e.preventDefault();
			e.stopImmediatePropagation();

			const index = this.#items.indexOf(item);
			const index_to = index + direction;

			if (Math.min(index, index_to) < this.#freeze_start
					|| Math.max(index, index_to) > this.#items.length - 1 - this.#freeze_end) {
				return;
			}

			this.#items.splice(index, 0, ...this.#items.splice(index_to, 1));

			this.#updateItemsOrder();
			this.#updateItemsLoc();

			this.#revealItem(item);

			e.target.focus();

			this.#fire(CSortable.EVENT_SORT, {index, index_to});
		},

		focusIn: (e) => {
			let scroll_parent = this.#target;

			while (scroll_parent !== null) {
				if (getComputedStyle(scroll_parent)[this.#is_horizontal ? 'overflowX' : 'overflowY'] === 'hidden') {
					scroll_parent[this.#is_horizontal ? 'scrollLeft' : 'scrollTop'] = 0;

					break;
				}

				scroll_parent = scroll_parent.parentElement;
			}

			const item = this.#matchItem(e.target);

			if (item !== null) {
				this.#revealItem(item);
			}
		},

		transitionRun: (e) => {
			if (e.target.closest(`.${CSortable.ZBX_STYLE_CLASS}`) === this.#target
					&& e.target.classList.contains(CSortable.ZBX_STYLE_ITEM)
					&& e.propertyName === (this.#is_horizontal ? 'left' : 'top')) {
				this.#transitions.set(e.target,
					this.#transitions.has(e.target) ? this.#transitions.get(e.target) + 1 : 1
				);
			}
		},

		transitionEnd: (e) => {
			if (e.target.closest(`.${CSortable.ZBX_STYLE_CLASS}`) === this.#target
					&& e.target.classList.contains(CSortable.ZBX_STYLE_ITEM)
					&& e.propertyName === (this.#is_horizontal ? 'left' : 'top')) {
				const count = this.#transitions.has(e.target) ? this.#transitions.get(e.target) - 1 : 0;

				if (count > 0) {
					this.#transitions.set(e.target, count);
				}
				else {
					this.#transitions.delete(e.target);
				}

				if (this.#transitions.size === 0) {
					for (const callback of this.#transitions_end_callbacks.values()) {
						callback(true);
					}

					this.#transitions_end_callbacks.clear();
				}
			}
		},

		transitionCancel: (e) => {
			this.#listeners.transitionEnd(e);
		},

		resize: () => {
			this.#scrollTo(Math.min(this.#scroll_pos, this.#getScrollMax()));
		},

		mutation: (records) => {
			if (records.some(record => record.type !== 'attributes' || record.attributeName !== 'style')) {
				this.#update();

				const item = this.#matchItem(document.activeElement);

				if (item !== null) {
					this.#revealItem(item);
				}
			}
		},

		intersection: (entries) => {
			if (entries[0].isIntersecting !== this.#is_visible) {
				this.#is_visible = entries[0].isIntersecting;

				this.#update();
			}
		}
	};
}
