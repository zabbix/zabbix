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


const ZBX_STYLE_SORTABLE = 'csortable';
const ZBX_STYLE_SORTABLE_LIST = 'csortable-list';
const ZBX_STYLE_SORTABLE_ITEM = 'csortable-item';
const ZBX_STYLE_SORTABLE_DRAG_HANDLE = 'csortable-drag-handle';
const ZBX_STYLE_SORTABLE_DRAGGING = 'csortable-dragging';

const SORTABLE_EVENT_DRAG_START = 'drag_start';
const SORTABLE_EVENT_DRAG_END = 'drag_end';
const SORTABLE_EVENT_SCROLL = 'scroll';

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
		drag_scroll_delay_short = 150,
		drag_scroll_delay_long = 400,
		wheel_step = 50
	} = {}) {
		super(target);

		this._is_vertical = is_vertical;
		this._drag_scroll_delay_short = drag_scroll_delay_short;
		this._drag_scroll_delay_long = drag_scroll_delay_long;
		this._wheel_step = wheel_step;

		this._init();
		this._registerEvents();
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
	 * Add item to the list.
	 *
	 * @param {HTMLLIElement}      item
	 * @param {HTMLLIElement|null} reference_item
	 *
	 * @returns {CSortable}
	 */
	addItem(item, reference_item = null) {
		item.classList.add(ZBX_STYLE_SORTABLE_ITEM);

		this._stopDragging();
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
		if (item.parentNode != this._list) {
			throw RangeError('Item does not belong to the list.')
		}

		this._stopDragging();
		item.remove();

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
		if (item.parentNode != this._list) {
			throw RangeError('Item does not belong to the list.')
		}

		const list_loc = this._getRectLoc(this._list.getBoundingClientRect());
		const item_loc = this._getRectLoc(item.getBoundingClientRect());

		this._scrollIntoView(item_loc.pos - list_loc.pos, item_loc.dim);

		return this;
	}

	/**
	 * Is list scrolled to the beginning?
	 *
	 * @returns {boolean}
	 */
	isMinScroll() {
		return (this._list_pos <= 0 || this._isEqualPos(this._list_pos, 0));
	}

	/**
	 * Is list scrolled to the end?
	 *
	 * @returns {boolean}
	 */
	isMaxScroll() {
		const items = this._getNonDraggingItems();

		const list_loc = this._getRectLoc(this._list.getBoundingClientRect());
		const last_item_loc = this._getRectLoc(items[items.length - 1].getBoundingClientRect());

		const max_pos = last_item_loc.pos + last_item_loc.dim - list_loc.pos - list_loc.dim;

		return (this._list_pos >= max_pos || this._isEqualPos(this._list_pos, max_pos));
	}

	_init() {
		this._list = this._target.getElementsByClassName(ZBX_STYLE_SORTABLE_LIST)[0];
		this._list_pos = -parseFloat(window.getComputedStyle(this._list).getPropertyValue(
			this._is_vertical ? 'top' : 'left'
		));

		this._drag_item = null;
		this._drag_scroll_timeout = null;
	}

	_startDragging(drag_item, pos) {
		this._drag_item_index = [...this._list.children].indexOf(drag_item);

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
			if (item == drag_item) {
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
		this._drag_item = drag_item.cloneNode(true);
		this._drag_item.style.left = `${drag_item_rect.x - target_rect.x}px`;
		this._drag_item.style.top = `${drag_item_rect.y - target_rect.y}px`;
		this._target.appendChild(this._drag_item);

		// Hide the actual dragging item.
		drag_item.classList.add(ZBX_STYLE_SORTABLE_DRAGGING);

		this.fire(SORTABLE_EVENT_DRAG_START, {item: this._drag_item});
	}

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

		if (this._drag_item_loc.pos == 0) {
			this._startDragScrolling(-1);
		}
		else if (this._drag_item_loc.pos == drag_item_max_pos) {
			this._startDragScrolling(1);
		}
		else {
			this._endDragScrolling();
		}
	}

	_endDragging() {
		this._endDragScrolling();

		const drag_item_pos = (this._drag_item_index > 0)
			? this._item_loc[this._drag_item_index - 1].pos + this._item_loc[this._drag_item_index - 1].dim
			: 0;

		this._scrollIntoView(drag_item_pos, this._drag_item_loc.dim);
	}

	_endDraggingAfterTransitions() {
		const items = this._getNonDraggingItems();

		const drag_items = this._list.getElementsByClassName(ZBX_STYLE_SORTABLE_DRAGGING);
		const drag_item = (drag_items.length > 0) ? drag_items[0] : null;

		if (drag_item !== null) {
			this._list.insertBefore(drag_item,
				(this._drag_item_index < items.length) ? items[this._drag_item_index] : null
			);

			drag_item.classList.remove(ZBX_STYLE_SORTABLE_DRAGGING);
		}

		this._drag_item.remove();
		this._drag_item = null;

		this._target.classList.remove(ZBX_STYLE_SORTABLE_DRAGGING);
		this._list.style.width = '';
		this._list.style.height = '';

		for (const item of items) {
			item.style.left = '';
			item.style.top = '';
		}

		if (drag_item !== null) {
			this.fire(SORTABLE_EVENT_DRAG_END, {item: drag_item});
		}
	}

	_stopDragging() {
		this._target.dispatchEvent(new Event('_drag_stop'));
	}

	_startDragScrolling(direction) {
		if (this._drag_scroll_timeout === null) {
			this._drag_scroll_tick = 0;
			this._drag_scroll_timeout = window.setTimeout(() => {
				this._dragScroll(direction);
			}, this._getDragScrollDelay(0));
		}
	}

	_dragScroll(direction) {
		const items = this._getNonDraggingItems();

		const prev_item_pos = (this._drag_item_index > 0) ? this._item_loc[this._drag_item_index - 1].pos : 0;
		const drag_item_pos = (this._drag_item_index > 0)
			? prev_item_pos + this._item_loc[this._drag_item_index - 1].dim
			: 0;

		if (direction == -1) {
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

		this._drag_scroll_timeout = window.setTimeout(() => this._dragScroll(direction),
			this._getDragScrollDelay(++this._drag_scroll_tick)
		);
	}

	_endDragScrolling() {
		if (this._drag_scroll_timeout !== null) {
			window.clearTimeout(this._drag_scroll_timeout);
			this._drag_scroll_timeout = null;
		}
	}

	_getDragScrollDelay(tick) {
		return (tick == 0 || tick > 2) ? this._drag_scroll_delay_short : this._drag_scroll_delay_long;
	}

	_wheel(direction, pos) {
		// Prevent using wheel while scrolling by dragging.
		if (this._drag_scroll_timeout !== null) {
			return;
		}

		const items = this._getNonDraggingItems();

		if (items.length == 0) {
			return;
		}

		const list_loc = this._getRectLoc(this._list.getBoundingClientRect());
		const last_item_loc = this._getRectLoc(items[items.length - 1].getBoundingClientRect());

		this._setListPos(Math.max(0, Math.min(last_item_loc.pos + last_item_loc.dim - list_loc.pos - list_loc.dim,
			this._list_pos + this._wheel_step * direction
		)));

		if (this._drag_item !== null) {
			this._drag(pos);
		}
	}

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

	_setListPos(pos) {
		if (this._isEqualPos(pos, this._list_pos)) {
			return;
		}

		this._list_pos = pos;
		this._list.style[this._is_vertical ? 'top' : 'left'] = `-${pos}px`;

		this.fire(SORTABLE_EVENT_SCROLL, {
			is_min: this.isMinScroll(),
			is_max: this.isMaxScroll()
		});
	}

	_getNonDraggingItems() {
		return [...this._list.children].filter((item) => !item.classList.contains(ZBX_STYLE_SORTABLE_DRAGGING));
	}

	_getRectLoc(rect) {
		return (this._is_vertical
			? {pos: rect.top, dim: rect.height}
			: {pos: rect.left, dim: rect.width}
		);
	}

	_isEqualPos(pos_1, pos_2) {
		return (Math.abs(pos_1 - pos_2) < 0.001);
	}

	/**
	 * Register all DOM events.
	 */
	_registerEvents() {

		this._events = {

			targetClick: (e) => {
				if (prevent_clicks) {
					e.preventDefault();
					e.stopImmediatePropagation();
				}
			},

			targetWheel: (e) => {
				e.preventDefault();

				if (mouse_down_item !== null) {
					this._startDragging(mouse_down_item, mouse_down_pos);

					mouse_down_item = null;

					// Prevent clicks after dragging has ended.
					prevent_clicks = true;
				}

				wheel_direction = (e.deltaY != 0) ? Math.sign(e.deltaY) : Math.sign(e.deltaX);
				wheel_pos = this._is_vertical ? e.clientY : e.clientX;

				if (wheel_request === null) {
					wheel_request = window.requestAnimationFrame(() => {
						this._wheel(wheel_direction, wheel_pos);
						wheel_request = null;
					});
				}
			},

			targetScroll: (e) => {
				// Prevent focusable child element scrolling into view.
				this._target[this._is_vertical ? 'scrollTop' : 'scrollLeft'] = 0;
			},

			listMouseDown: (e) => {
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

				// Save initial mouse position.
				mouse_down_pos = this._is_vertical ? e.clientY : e.clientX;

				this._target.removeEventListener('wheel', this._events.targetWheel);
				window.addEventListener('mousemove', this._events.windowMouseMove);
				window.addEventListener('mouseup', this._events.windowMouseUp);
				window.addEventListener('wheel', this._events.targetWheel, {passive: false});
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
					mouse_move_request = window.requestAnimationFrame(() => {
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
						|| !this._isEqualPos(this._list_pos, prev_list_pos));

					if (!end_dragging_after_transitions) {
						this._endDraggingAfterTransitions();
					}
				}
				else {
					mouse_down_item = null;
				}

				prevent_clicks = false;

				if (mouse_move_request !== null) {
					window.cancelAnimationFrame(mouse_move_request);
					mouse_move_request = null;
				}

				window.removeEventListener('mousemove', this._events.windowMouseMove);
				window.removeEventListener('mouseup', this._events.windowMouseUp);
				window.removeEventListener('wheel', this._events.targetWheel);
				this._target.addEventListener('wheel', this._events.targetWheel, {passive: false});
			},

			listRunTransition: (e) => {
				transitions_set.add(e.target);
			},

			listEndTransition: (e) => {
				transitions_set.delete(e.target);

				// Delete outdated targets.
				for (const target of transitions_set) {
					if (target == this._list) {
						continue;
					}

					const item = target.closest(`.${ZBX_STYLE_SORTABLE_ITEM}`);

					if (item === null || item.parentNode != this._list) {
						transitions_set.delete(target);
					}
				}

				if (end_dragging_after_transitions && transitions_set.size == 0) {
					this._endDraggingAfterTransitions();
					end_dragging_after_transitions = false;
				}
			},

			_stopDragging: () => {
				// Actually dragging an item?
				if (prevent_clicks || mouse_down_item !== null) {
					this._events.windowMouseUp();
				}
			}
		};

		let prevent_clicks = false;
		let mouse_down_item = null;
		let mouse_down_pos;
		let mouse_move_request = null;
		let mouse_move_pos;
		let wheel_request = null;
		let wheel_direction;
		let wheel_pos;
		let transitions_set = new Set();
		let end_dragging_after_transitions = false;

		this._target.addEventListener('click', this._events.targetClick);
		this._target.addEventListener('wheel', this._events.targetWheel, {passive: false});
		this._target.addEventListener('scroll', this._events.targetScroll, {passive: false});
		this._target.addEventListener('_drag_stop', this._events._stopDragging);
		this._list.addEventListener('mousedown', this._events.listMouseDown);
		this._list.addEventListener('transitionrun', this._events.listRunTransition);
		this._list.addEventListener('transitionend', this._events.listEndTransition);
	}
}
