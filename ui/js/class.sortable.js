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


const ZBX_STYLE_SORTABLE = 'sortable';
const ZBX_STYLE_SORTABLE_LIST = 'sortable-list';
const ZBX_STYLE_SORTABLE_ITEM = 'sortable-item';
const ZBX_STYLE_SORTABLE_DRAG_HANDLE = 'sortable-drag-handle';
const ZBX_STYLE_SORTABLE_DRAGGING = 'sortable-dragging';

const SORTABLE_EVENT_DRAG_START = 'drag_start';
const SORTABLE_EVENT_DRAG_END = 'drag_end';

class CSortable extends CBaseComponent {

	/**
	 * Create CSortable instance.
	 *
	 * @param {HTMLElement}  target
	 *
	 * @returns {CSortable}
	 */
	constructor(target, {
		is_vertical,
		is_sorting_enabled = true,
		drag_scroll_delay_short = 150,
		drag_scroll_delay_long = 400,
		wheel_step = 100,
		do_activate = true
	}) {
		super(target);

		this._is_vertical = is_vertical;
		this._is_sorting_enabled = is_sorting_enabled;
		this._drag_scroll_delay_short = drag_scroll_delay_short;
		this._drag_scroll_delay_long = drag_scroll_delay_long;
		this._wheel_step = wheel_step;

		this._init();
		this._registerEvents();

		if (do_activate) {
			this.activate();
		}
	}

	/**
	 * Activate the interactive functionality.
	 *
	 * @returns {CSortable}
	 */
	activate() {
		if (this._is_activated) {
			throw Error('Instance already activated.');
		}

		this._fixListPos();

		this._activateEvents();

		this._is_activated = true;

		return this;
	}

	/**
	 * Deactivate the interactive functionality.
	 *
	 * @returns {CSortable}
	 */
	deactivate() {
		if (!this._is_activated) {
			throw Error('Instance already deactivated.');
		}

		this._cancelDragging();

		this._deactivateEvents();

		this._is_activated = false;

		return this;
	}

	/**
	 * Enable or disable the sorting functionality.
	 *
	 * @param {boolean} enable

	 * @returns {CSortable}
	 */
	enableSorting(enable = true) {
		if (this._is_sorting_enabled && !enable) {
			this._cancelDragging();
		}

		this._is_sorting_enabled = enable;

		return this;
	}

	/**
	 * Get list of items.
	 *
	 * @returns {HTMLCollection}
	 */
	getList() {
		return this._list;
	}

	/**
	 * Is the list scrollable (not all items visible)?
	 *
	 * @returns {boolean}
	 */
	isScrollable() {
		return !this._isPosEqual(this._getListPosMax(), 0);
	}

	/**
	 * Insert item to the list before the reference item or at the end.
	 *
	 * @param {HTMLLIElement}      item
	 * @param {HTMLLIElement|null} reference_item
	 *
	 * @returns {CSortable}
	 */
	insertItemBefore(item, reference_item = null) {
		item.classList.add(ZBX_STYLE_SORTABLE_ITEM);
		item.tabIndex = 0;

		this._cancelDragging();
		this._list.insertBefore(item, reference_item);

		return this;
	}

	/**
	 * Remove item from the list.
	 *
	 * @param {HTMLLIElement} item
	 *
	 * @returns {CSortable}
	 */
	removeItem(item) {
		if (item.parentNode !== this._list) {
			throw RangeError('Item does not belong to the list.');
		}

		this._cancelDragging();
		this._list.removeChild(item);

		return this;
	}

	/**
	 * Scroll item into view.
	 *
	 * @param {HTMLLIElement} item
	 *
	 * @returns {CSortable}
	 */
	scrollItemIntoView(item) {
		if (item.parentNode !== this._list) {
			throw RangeError('Item does not belong to the list.');
		}

		const list_loc = this._getRectLoc(this._list.getBoundingClientRect());
		const item_loc = this._getRectLoc(item.getBoundingClientRect());

		this._scrollIntoView(item_loc.pos - list_loc.pos, item_loc.dim);

		return this;
	}

	/**
	 * Initialize the instance.
	 */
	_init() {
		this._target.classList.add(ZBX_STYLE_SORTABLE);

		this._list = this._target.querySelector(`.${ZBX_STYLE_SORTABLE_LIST}`);

		if (this._list === null) {
			this._list = document.createElement('ul');
			this._target.appendChild(this._list);
		}

		this._list.classList.add(ZBX_STYLE_SORTABLE_LIST);

		this._list_pos = -parseFloat(getComputedStyle(this._list).getPropertyValue(
			this._is_vertical ? 'top' : 'left'
		));

		this._drag_item = null;
		this._drag_scroll_timeout = null;

		this._is_activated = false;
	}

	/**
	 * Start dragging the item.
	 *
	 * @param {HTMLLIElement} drag_item  Dragged item.
	 * @param {number}        pos        Starting axis position.
	 */
	_startDragging(drag_item, pos) {
		this._drag_item_index_original = [...this._list.children].indexOf(drag_item);

		this._drag_item_index = this._drag_item_index_original;

		const target_rect = this._target.getBoundingClientRect();
		const target_loc = this._getRectLoc(target_rect);

		const list_rect = this._list.getBoundingClientRect();
		const list_loc = this._getRectLoc(list_rect);

		const drag_item_rect = drag_item.getBoundingClientRect();
		const drag_item_loc = this._getRectLoc(drag_item_rect);

		this._drag_item_loc = {
			pos: drag_item_loc.pos - target_loc.pos,
			dim: drag_item_loc.dim
		};

		this._drag_item_event_delta_pos = this._drag_item_loc.pos - pos;

		this._item_loc = [];

		for (const item of this._list.children) {
			if (item === drag_item) {
				continue;
			}

			const item_rect = item.getBoundingClientRect();
			const item_loc = this._getRectLoc(item_rect);

			this._item_loc.push({
				pos: item_loc.pos - list_loc.pos,
				dim: item_loc.dim
			});

			item.style.left = `${item_rect.x - list_rect.x}px`;
			item.style.top = `${item_rect.y - list_rect.y}px`;
		}

		this._target.classList.add(ZBX_STYLE_SORTABLE_DRAGGING);
		this._list.style.width = `${list_rect.width}px`;
		this._list.style.height = `${list_rect.height}px`;

		// Clone the dragging item not to disturb the original order while dragging.
		this._drag_item = drag_item;
		this._drag_item.style.left = `${drag_item_rect.x - target_rect.x}px`;
		this._drag_item.style.top = `${drag_item_rect.y - target_rect.y}px`;
		this._target.appendChild(this._drag_item);

		// Hide the actual dragging item.
		drag_item.classList.add(ZBX_STYLE_SORTABLE_DRAGGING);

		this.fire(SORTABLE_EVENT_DRAG_START, {item: drag_item});
	}

	/**
	 * Drag the currently dragged item to a new position.
	 *
	 * @param {number} pos  New axis position.
	 */
	_drag(pos) {
		const items = this._getNonDraggingItems();

		const target_rect = this._target.getBoundingClientRect();
		const target_loc = this._getRectLoc(target_rect);

		const drag_item_rect = this._drag_item.getBoundingClientRect();
		const drag_item_loc = this._getRectLoc(drag_item_rect);

		const drag_item_max_pos = target_loc.dim - drag_item_loc.dim;
		this._drag_item_loc.pos = Math.max(0, Math.min(drag_item_max_pos, pos + this._drag_item_event_delta_pos));
		this._drag_item.style[this._is_vertical ? 'top' : 'left'] = `${this._drag_item_loc.pos}px`;

		const center_pos = this._list_pos + this._drag_item_loc.pos + this._drag_item_loc.dim / 2;

		for (let index = this._drag_item_index - 1; index >= 0; index--) {
			if (center_pos >= this._item_loc[index].pos + (this._item_loc[index].dim + this._drag_item_loc.dim) / 2) {
				break;
			}

			this._drag_item_index--;
			this._item_loc[index].pos += this._drag_item_loc.dim;
			items[index].style[this._is_vertical ? 'top' : 'left'] = `${this._item_loc[index].pos}px`;
		}

		for (let index = this._drag_item_index; index < items.length; index++) {
			if (center_pos <= this._item_loc[index].pos + (this._item_loc[index].dim - this._drag_item_loc.dim) / 2) {
				break;
			}

			this._drag_item_index++;
			this._item_loc[index].pos -= this._drag_item_loc.dim;
			items[index].style[this._is_vertical ? 'top' : 'left'] = `${this._item_loc[index].pos}px`;
		}

		if (this._drag_item_loc.pos === 0) {
			this._startDragScrolling(-1);
		}
		else if (this._drag_item_loc.pos === drag_item_max_pos) {
			this._startDragScrolling(1);
		}
		else {
			this._endDragScrolling();
		}
	}

	/**
	 * End dragging the item.
	 */
	_endDragging() {
		this._endDragScrolling();

		const drag_item_pos = (this._drag_item_index > 0)
			? this._item_loc[this._drag_item_index - 1].pos + this._item_loc[this._drag_item_index - 1].dim
			: 0;

		this._scrollIntoView(drag_item_pos, this._drag_item_loc.dim);
	}

	/**
	 * End dragging the item after the positional transitions have ended.
	 */
	_endDraggingAfterTransitions() {
		const items = this._getNonDraggingItems();

		const drag_item = this._drag_item;

		this._list.insertBefore(drag_item,
			(this._drag_item_index < items.length) ? items[this._drag_item_index] : null
		);

		drag_item.classList.remove(ZBX_STYLE_SORTABLE_DRAGGING);
		drag_item.style.left = '';
		drag_item.style.top = '';

		this._target.classList.remove(ZBX_STYLE_SORTABLE_DRAGGING);
		this._list.style.width = '';
		this._list.style.height = '';

		for (const item of items) {
			item.style.left = '';
			item.style.top = '';
		}

		// Re-focus the dragged item.
		drag_item.focus();

		this._drag_item = null;

		this.fire(SORTABLE_EVENT_DRAG_END, {item: drag_item});
	}

	/**
	 * Start list scrolling iteratively when the item is dragged to the beginning or to the end of the list.
	 *
	 * @param {number} direction  Either 1 or -1 for scrolling forward or backward respectively.
	 */
	_startDragScrolling(direction) {
		if (this._drag_scroll_timeout === null) {
			this._drag_scroll_tick = 0;
			this._drag_scroll_timeout = setTimeout(() => {
				this._dragScroll(direction);
			}, this._getDragScrollDelay(0));
		}
	}

	/**
	 * Scroll the list by one item when the item is dragged to the beginning or to the end of the list.
	 *
	 * @param {number} direction  Either 1 or -1 for scrolling forward or backward respectively.
	 */
	_dragScroll(direction) {
		const items = this._getNonDraggingItems();

		const prev_item_pos = (this._drag_item_index > 0) ? this._item_loc[this._drag_item_index - 1].pos : 0;
		const drag_item_pos = (this._drag_item_index > 0)
			? prev_item_pos + this._item_loc[this._drag_item_index - 1].dim
			: 0;

		if (direction === -1) {
			if (this._drag_item_index > 0) {
				this._drag_item_index--;

				this._setListPos(prev_item_pos);

				this._item_loc[this._drag_item_index].pos += this._drag_item_loc.dim;
				items[this._drag_item_index].style[this._is_vertical ? 'top' : 'left'] =
					`${this._item_loc[this._drag_item_index].pos}px`;
			}
			else {
				this._scrollIntoView(drag_item_pos, this._drag_item_loc.dim);
			}
		}
		else {
			const next_item_pos = (this._drag_item_index < items.length)
				? this._item_loc[this._drag_item_index].pos
				: drag_item_pos + this._drag_item_loc.dim;

			const next_next_item_pos = (this._drag_item_index < items.length - 1)
				? this._item_loc[this._drag_item_index + 1].pos
				: next_item_pos + (
					(this._drag_item_index < items.length) ? this._item_loc[this._drag_item_index].dim : 0
				);

			if (this._drag_item_index < items.length) {
				const list_loc = this._getRectLoc(this._list.getBoundingClientRect());

				this._setListPos(next_next_item_pos - list_loc.dim);

				this._item_loc[this._drag_item_index].pos -= this._drag_item_loc.dim;
				items[this._drag_item_index].style[this._is_vertical ? 'top' : 'left'] =
					`${this._item_loc[this._drag_item_index].pos}px`;

				this._drag_item_index++;
			}
			else {
				this._scrollIntoView(drag_item_pos, this._drag_item_loc.dim);
			}
		}

		this._drag_scroll_timeout = setTimeout(() => this._dragScroll(direction),
			this._getDragScrollDelay(++this._drag_scroll_tick)
		);
	}

	/**
	 * End list scrolling iteratively when the item is dragged to the beginning or to the end of the list.
	 */
	_endDragScrolling() {
		if (this._drag_scroll_timeout !== null) {
			clearTimeout(this._drag_scroll_timeout);
			this._drag_scroll_timeout = null;
		}
	}

	/**
	 * Get the delay for a sequent list scrolling when the item is dragged to the beginning or to the end of the list.
	 *
	 * @param {number} iteration  Zero-based list scrolling iteration.
	 *
	 * @returns {number}
	 */
	_getDragScrollDelay(iteration) {
		return (iteration === 0 || iteration > 2) ? this._drag_scroll_delay_short : this._drag_scroll_delay_long;
	}

	/**
	 * Cancel item dragging and return the item to its original position.
	 */
	_cancelDragging() {
		if (this._drag_item !== null) {
			// Simulate dropping the item at it's original position.

			this._drag_item_index = this._drag_item_index_original;

			this.fire('_dragcancel');
		}
	}

	/**
	 * Scroll the list by mouse wheel in the given direction.
	 *
	 * @param {number} direction  Either 1 or -1 for scrolling forward or backward respectively.
	 * @param {number} pos        Mouse axis position.
	 */
	_wheel(direction, pos) {
		// Prevent using wheel while scrolling by dragging.
		if (this._drag_scroll_timeout !== null) {
			return;
		}

		this._setListPos(Math.max(0, Math.min(this._getListPosMax(), this._list_pos + this._wheel_step * direction)));

		if (this._drag_item !== null) {
			this._drag(pos);
		}
	}

	/**
	 * Scroll the list as little as possible to fully contain the object with the given position and dimension.
	 *
	 * @param {number} pos  Object position in decimal pixels.
	 * @param {number} dim  Object dimension in decimal pixels.
	 */
	_scrollIntoView(pos, dim) {
		if (pos < this._list_pos) {
			this._setListPos(pos);
		}
		else {
			const list_loc = this._getRectLoc(this._list.getBoundingClientRect());

			if (pos + dim > this._list_pos + list_loc.dim) {
				this._setListPos(pos + dim - list_loc.dim);
			}
		}
	}

	/**
	 * Scroll the list to the given position.
	 *
	 * @param {number} pos  Position in decimal pixels.
	 */
	_setListPos(pos) {
		if (this._isPosEqual(pos, this._list_pos)) {
			return;
		}

		this._list_pos = pos;
		this._list.style[this._is_vertical ? 'top' : 'left'] = `-${pos}px`;
	}

	/**
	 * Fix the list scroll position (on list resize).
	 */
	_fixListPos() {
		const list_pos_max = this._getListPosMax();

		if (this._list_pos > list_pos_max) {
			this._setListPos(list_pos_max);
		}
	}

	/**
	 * Get the maximum scroll position of the list.
	 *
	 * @returns {number}  Position in decimal pixels.
	 */
	_getListPosMax() {
		const items = this._getNonDraggingItems();

		const list_loc = this._getRectLoc(this._list.getBoundingClientRect());

		if (this._drag_item === null) {
			if (items.length === 0) {
				return 0;
			}

			const last_item_loc = this._getRectLoc(items[items.length - 1].getBoundingClientRect());

			return Math.max(0, last_item_loc.pos + last_item_loc.dim - list_loc.pos - list_loc.dim);
		}
		else {
			if (items.length === 0) {
				return Math.max(0, this._drag_item_loc.dim - list_loc.dim);
			}

			const scroll_dim = (this._drag_item_index < items.length)
				? this._item_loc[items.length - 1].pos + this._item_loc[items.length - 1].dim
				: this._item_loc[items.length - 1].pos + this._item_loc[items.length - 1].dim + this._drag_item_loc.dim;

			return Math.max(0, scroll_dim - list_loc.dim);
		}
	}

	/**
	 * Get all list items except the one being dragged.
	 *
	 * @returns {HTMLElement[]}
	 */
	_getNonDraggingItems() {
		return [...this._list.children].filter((item) => !item.classList.contains(ZBX_STYLE_SORTABLE_DRAGGING));
	}

	/**
	 * Get the position and dimension of the DOMRect, based on the current instance orientation.
	 *
	 * @param {DOMRect} rect
	 *
	 * @returns {Object}
	 */
	_getRectLoc(rect) {
		return (this._is_vertical
			? {pos: rect.top, dim: rect.height}
			: {pos: rect.left, dim: rect.width}
		);
	}

	/**
	 * Check if decimal positions are equal by dismissing floating-point calculation errors.
	 *
	 * @param {number} pos_1  Decimal position.
	 * @param {number} pos_2  Decimal position.
	 *
	 * @returns {boolean}
	 */
	_isPosEqual(pos_1, pos_2) {
		return (Math.abs(pos_1 - pos_2) < 0.001);
	}

	/**
	 * Register all DOM events.
	 */
	_registerEvents() {
		let prevent_clicks;
		let mouse_down_item;
		let mouse_down_pos;
		let mouse_move_request;
		let mouse_move_pos;
		let wheel_request;
		let wheel_direction;
		let wheel_pos;
		let end_dragging_after_transitions;
		let transitions_set;
		let list_resize_observer;

		this._events = {
			targetClick: (e) => {
				if (prevent_clicks) {
					e.preventDefault();
					e.stopImmediatePropagation();
				}
			},

			targetScroll: () => {
				// Prevent browsers from automatically scrolling focusable elements into view.
				this._target[this._is_vertical ? 'scrollTop' : 'scrollLeft'] = 0;
			},

			wheel: (e) => {
				if (mouse_down_item !== null) {
					this._startDragging(mouse_down_item, mouse_down_pos);

					mouse_down_item = null;

					// Prevent clicks after dragging has ended.
					prevent_clicks = true;
				}

				if (this._drag_item !== null) {
					e.preventDefault();
				}

				wheel_direction = (e.deltaY !== 0) ? Math.sign(e.deltaY) : Math.sign(e.deltaX);
				wheel_pos = this._is_vertical ? e.clientY : e.clientX;

				if (wheel_request === null) {
					wheel_request = requestAnimationFrame(() => {
						this._wheel(wheel_direction, wheel_pos);
						wheel_request = null;
					});
				}
			},

			listMouseDown: (e) => {
				if (e.button !== 0) {
					return;
				}

				if (!this._is_sorting_enabled) {
					return;
				}

				// Prevent clicks while transitions are running.
				if (transitions_set.size > 0) {
					return;
				}

				mouse_down_item = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				// Interested in items and not the list itself.
				if (mouse_down_item === null) {
					return;
				}


				// Scroll item into view if it is partially visible.
				this.scrollItemIntoView(mouse_down_item);

				// Drag handle specified, but clicked elsewhere?
				if (mouse_down_item.getElementsByClassName(ZBX_STYLE_SORTABLE_DRAG_HANDLE).length > 0
						&& e.target.closest(`.${ZBX_STYLE_SORTABLE_DRAG_HANDLE}`) === null) {
					mouse_down_item = null;

					return;
				}

				e.preventDefault();

				// Save initial mouse position.
				mouse_down_pos = this._is_vertical ? e.clientY : e.clientX;

				this.off('wheel', this._events.wheel);
				window.addEventListener('mousemove', this._events.windowMouseMove);
				window.addEventListener('mouseup', this._events.windowMouseUp);
				window.addEventListener('wheel', this._events.wheel, {passive: false});
			},

			windowMouseMove: (e) => {
				if (mouse_down_item !== null) {
					this._startDragging(mouse_down_item, mouse_down_pos);

					mouse_down_item = null;

					// Prevent clicks after dragging has ended.
					prevent_clicks = true;
				}

				mouse_move_pos = this._is_vertical ? e.clientY : e.clientX;

				if (mouse_move_request === null) {
					mouse_move_request = requestAnimationFrame(() => {
						this._drag(mouse_move_pos);
						mouse_move_request = null;
					});
				}
			},

			windowMouseUp: () => {
				// Was dragging in progress?
				if (mouse_down_item === null) {
					const prev_list_pos = this._list_pos;

					// Will occasionally update this._list_pos and start the transition later.
					this._endDragging();

					end_dragging_after_transitions = (transitions_set.size > 0
						|| !this._isPosEqual(this._list_pos, prev_list_pos));

					if (!end_dragging_after_transitions) {
						this._endDraggingAfterTransitions();
					}
				}
				else {
					mouse_down_item = null;
				}

				prevent_clicks = false;

				if (mouse_move_request !== null) {
					cancelAnimationFrame(mouse_move_request);
					mouse_move_request = null;
				}

				window.removeEventListener('mousemove', this._events.windowMouseMove);
				window.removeEventListener('mouseup', this._events.windowMouseUp);
				window.removeEventListener('wheel', this._events.wheel);
				this.on('wheel', this._events.wheel, {passive: false});
			},

			listKeyDown: (e) => {
				if (!this._is_sorting_enabled) {
					return;
				}

				if (e.target.parentNode !== this._list) {
					return;
				}

				if ((e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') || !e.ctrlKey) {
					return;
				}

				const reference_item = e.key === 'ArrowLeft'
					? e.target.previousElementSibling
					: (e.target.nextElementSibling ? e.target.nextElementSibling.nextElementSibling : null);

				// Leftmost item already focused?
				if (e.key === 'ArrowLeft' && reference_item === null) {
					return;
				}

				this.insertItemBefore(e.target, reference_item);

				e.preventDefault();

				// Re-focus the moved item.
				e.target.focus();
			},

			listFocusIn: (e) => {
				const item = e.target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

				if (item) {
					this.scrollItemIntoView(item);
				}
			},

			listRunTransition: (e) => {
				if (e.propertyName === (this._is_vertical ? 'top' : 'left')) {
					transitions_set.add(e.target);
				}
			},

			listEndTransition: (e) => {
				transitions_set.delete(e.target);

				// Delete outdated targets.
				for (const target of transitions_set) {
					if (target === this._list) {
						continue;
					}

					const item = target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (item === null || item.parentNode !== this._list) {
						transitions_set.delete(target);
					}
				}

				if (end_dragging_after_transitions && transitions_set.size === 0) {
					this._endDraggingAfterTransitions();
					end_dragging_after_transitions = false;
				}
			},

			listResize: () => {
				this._fixListPos();
			},

			_cancelDragging: () => {
				// Actually dragging an item?
				if (prevent_clicks || mouse_down_item !== null) {
					this._events.windowMouseUp();
				}
			},
		};

		this._activateEvents = () => {
			prevent_clicks = false;
			mouse_down_item = null;
			mouse_move_request = null;
			wheel_request = null;
			end_dragging_after_transitions = false;
			transitions_set = new Set();

			this.on('click', this._events.targetClick);
			this.on('scroll', this._events.targetScroll);
			this.on('wheel', this._events.wheel, {passive: false});
			this.on('_dragcancel', this._events._cancelDragging);
			this._list.addEventListener('mousedown', this._events.listMouseDown);
			this._list.addEventListener('keydown', this._events.listKeyDown);
			this._list.addEventListener('focusin', this._events.listFocusIn);
			this._list.addEventListener('transitionrun', this._events.listRunTransition);
			this._list.addEventListener('transitionend', this._events.listEndTransition);

			list_resize_observer = new ResizeObserver(this._events.listResize);
			list_resize_observer.observe(this._list);
		};

		this._deactivateEvents = () => {
			if (wheel_request !== null) {
				cancelAnimationFrame(wheel_request);
			}

			if (end_dragging_after_transitions) {
				this._endDraggingAfterTransitions();
			}

			this.off('click', this._events.targetClick);
			this.off('scroll', this._events.targetScroll);
			this.off('wheel', this._events.wheel);
			this.off('_dragcancel', this._events._cancelDragging);
			this._list.removeEventListener('mousedown', this._events.listMouseDown);
			this._list.removeEventListener('keydown', this._events.listKeyDown);
			this._list.removeEventListener('focusin', this._events.listFocusIn);
			this._list.removeEventListener('transitionrun', this._events.listRunTransition);
			this._list.removeEventListener('transitionend', this._events.listEndTransition);

			// Added by mousedown event handler.
			window.removeEventListener('mousemove', this._events.windowMouseMove);
			window.removeEventListener('mouseup', this._events.windowMouseUp);
			window.removeEventListener('wheel', this._events.wheel);

			list_resize_observer.disconnect();
		};
	}
}
