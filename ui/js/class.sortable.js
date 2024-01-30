/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CSortable {

	/**
	 * Class applied to a sortable container.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_SORTABLE = 'sortable';

	/**
	 * Class applied to a sortable container while token is being dragged.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_SORTABLE_DRAGGING = 'sortable-dragging';

	/**
	 * Class applied to a token while it is being dragged.
	 *
	 * @type {string}
	 */
	static ZBX_STYLE_SORTABLE_DRAGGING_TOKEN = 'sortable-dragging-token';

	/**
	 * Event fired on start of dragging of a token.
	 *
	 * @type {string}
	 */
	static EVENT_DRAG_START = 'sortable-drag-start';

	/**
	 * Event fired on end of dragging of a token.
	 *
	 * @type {string}
	 */
	static EVENT_DRAG_END = 'sortable-drag-end';

	/**
	 * Event fired on end of dragging of a token, if sort order has changed.
	 *
	 * @type {string}
	 */
	static EVENT_SORT = 'sortable-sort';

	static ANIMATION_SCROLL = 'scroll';

	static LISTENERS_OFF = 'off';
	static LISTENERS_SCROLL = 'scroll';
	static LISTENERS_SCROLL_SORT = 'scroll-sort';

	#target;

	#is_vertical;

	#selector_span;
	#selector_freeze;
	#selector_handle;

	#is_enabled = false;
	#is_enabled_sorting;

	#animation_speed;
	#animation_time_limit;

	#tokens = [];
	#tokens_loc = [];

	#animations = new Map();
	#animation_frame = null;

	#scroll_pos = 0;

	#is_dragging = false;
	#drag_token = null;
	#drag_index = -1;
	#drag_index_original = -1;
	#drag_delta = 0;
	#drag_style;
	#overtake_tokens_loc = [];
	#overtake_min = -1;
	#overtake_max = -1;

	#drag_scroll_timeout = null;
	#drag_scroll_direction = 0;

	#mutation_observer = null;

	#skip_click = false;

	/**
	 * Create CSortable instance.
	 *
	 * @param {HTMLElement} target                Sortable container.
	 * @param {boolean}     is_vertical           Whether sorting is vertically oriented.
	 * @param {string}      selector_span         Selector for matching first child element of multi-element tokens.
	 * @param {string}      selector_freeze       Selector for matching frozen tokens (cannot change order).
	 * @param {string}      selector_handle       Selector for matching a drag handle.
	 * @param {boolean}     enable                Whether to enable the instance initially.
	 * @param {boolean}     enable_sorting        Whether to enable sorting initially (or just scrolling).
	 * @param {number}      animation_speed       Animation speed in pixels per second.
	 * @param {number}      animation_time_limit  Animation time limit in seconds.
	 *
	 * @returns {CSortable}
	 */
	constructor(target, {
		is_vertical = false,

		selector_span = '',
		selector_freeze = '',
		selector_handle = '',

		enable = true,
		enable_sorting = true,

		animation_speed = 500,
		animation_time_limit = .25
	} = {}) {
		this.#target = target;
		this.#target.classList.add(CSortable.ZBX_STYLE_SORTABLE);
		this.#target[is_vertical ? 'scrollTop' : 'scrollLeft'] = 0;

		this.#is_vertical = is_vertical;

		this.#selector_span = selector_span;
		this.#selector_freeze = selector_freeze;
		this.#selector_handle = selector_handle;

		this.#is_enabled_sorting = enable_sorting;

		this.#animation_speed = animation_speed;
		this.#animation_time_limit = animation_time_limit;

		this.#mutation_observer = new MutationObserver(this.#listeners.mutation);

		if (enable) {
			this.enable();
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
	 * Invoke the callback as soon as the instance has processed the updated token data.
	 */
	whenReady(callback) {
		requestAnimationFrame(() => callback());
	}

	/**
	 * Enable or disable the instance.
	 *
	 * @param {boolean} enable
	 */
	enable(enable = true) {
		if (enable === this.#is_enabled) {
			return;
		}

		if (enable) {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#updateTokens();
		}
		else {
			this.#toggleListeners(CSortable.LISTENERS_OFF);
			this.#unobserveTokens();
			this.#cancelSort();
		}

		this.#is_enabled = enable;
	}

	/**
	 * Enable or disable sorting.
	 *
	 * @param {boolean} enable
	 */
	enableSorting(enable = true) {
		if (this.#is_enabled && this.#is_enabled_sorting && !enable) {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#cancelSort();
		}

		this.#is_enabled_sorting = enable;
	}

	/**
	 * Scroll the target token into view.
	 *
	 * @param {HTMLElement} element
	 */
	scrollIntoView(element) {
		if (this.#is_dragging || this.#drag_token !== null) {
			return;
		}

		const token = this.#matchToken(element);

		if (token !== null) {
			this.#scrollIntoView(this.#tokens_loc.get(token));
		}
	}

	/**
	 * Check whether the sortable container is scrollable.
	 *
	 * @returns {boolean}
	 */
	isScrollable() {
		return this.#getScrollMax() > 0;
	}

	/**
	 * Destroy the instance.
	 */
	destroy() {
		this.enable(false);
		this.#scrollTo(0);
		this.#finishAnimations();
	}

	#updateTokens() {
		for (const token of this.#tokens) {
			this.#clearAnimation(token);
		}

		this.#tokens = [];

		for (const element of this.#target.querySelectorAll(':scope > *')) {
			if (this.#selector_span === '' || element.matches(this.#selector_span)) {
				this.#tokens.push({
					elements: [],
					freeze: this.#selector_freeze !== '' && element.matches(this.#selector_freeze),
					rel: 0
				});
			}

			this.#tokens.at(-1).elements.push(element);
		}

		this.#tokens_loc = this.#getTokensLoc(this.#tokens);
	}

	#getTokensLoc(tokens) {
		this.#sortTokens(tokens);

		const target_pos = this.#getTargetLoc().pos - this.#scroll_pos;

		const tokens_loc = new Map();

		for (const token of tokens) {
			const client_loc = this.#getLoc(token.elements);

			tokens_loc.set(token, {
				pos: client_loc.pos - target_pos - token.rel,
				dim: client_loc.dim
			});
		}

		this.#sortTokens();

		return tokens_loc;
	}

	#sortTokens(tokens = this.#tokens) {
		this.#unobserveTokens();

		const elements_old = [...this.#target.children];
		const elements_new = [];

		for (const token of tokens) {
			for (const element of token.elements) {
				elements_new.push(element);
			}
		}

		if (elements_new.length !== elements_old.length
				|| elements_new.some((element, index) => element !== elements_old[index])) {
			for (const element of elements_new) {
				this.#target.appendChild(element);
			}
		}

		this.#observeTokens();
	}

	#getTargetLoc() {
		const client_rect = this.#target.getBoundingClientRect();

		return {
			pos: this.#is_vertical ? client_rect.y : client_rect.x,
			dim: this.#is_vertical ? client_rect.height : client_rect.width
		};
	}

	#getLoc(elements) {
		let pos = 0;
		let pos_to = 0;

		for (const element of elements) {
			let loc;

			if (getComputedStyle(element).display === 'contents') {
				loc = this.#getLoc(element.children);

				if (loc.dim === 0) {
					continue;
				}
			}
			else {
				const client_rect = element.getBoundingClientRect();

				loc = {
					pos: this.#is_vertical ? client_rect.y : client_rect.x,
					dim: this.#is_vertical ? client_rect.height : client_rect.width
				};
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

	#getAnimation(key) {
		return this.#animations.get(key) ?? null;
	}

	#scheduleAnimation(key, from, to = null) {
		if (this.#animations.size === 0) {
			this.#animation_frame = requestAnimationFrame(() => this.#animate());
		}

		if (to === null) {
			to = from;
		}

		this.#animations.set(key, {
			from,
			to,
			time: performance.now(),
			duration: Math.min(this.#animation_time_limit, Math.abs(from - to) / this.#animation_speed) * 1000
		});
	}

	#clearAnimation(key) {
		this.#animations.delete(key);

		if (this.#animation_frame !== null && this.#animations.size === 0) {
			cancelAnimationFrame(this.#animation_frame);
			this.#animation_frame = null;
		}
	}

	#animate() {
		const time_now = performance.now();

		const updates = new Map();

		for (const [key, animation] of this.#animations) {
			const to = this.#getAnimationProgress(animation, time_now);

			updates.set(key, to);

			if (to === animation.to) {
				this.#animations.delete(key);
			}
		}

		this.#update(updates);
		this.#render();

		this.#animation_frame = this.#animations.size > 0
			? requestAnimationFrame(() => this.#animate())
			: null;
	}

	#finishAnimations() {
		const updates = new Map();

		for (const [key, animation] of this.#animations) {
			updates.set(key, animation.to);
		}

		this.#update(updates);
		this.#render();

		this.#animations.clear();

		if (this.#animation_frame !== null) {
			cancelAnimationFrame(this.#animation_frame);
			this.#animation_frame = null;
		}
	}

	#getAnimationProgress({from, to, time, duration}, time_now) {
		if (time_now < time + duration && duration > 0) {
			const progress = (time_now - time) / duration;
			const progress_smooth = Math.sin(Math.PI * progress / 2);

			return from + (to - from) * progress_smooth;
		}

		return to;
	}

	#update(updates) {
		if (updates.has(CSortable.ANIMATION_SCROLL)) {
			this.#scroll_pos = updates.get(CSortable.ANIMATION_SCROLL);
		}

		for (const token of this.#tokens) {
			if (updates.has(token)) {
				token.rel = updates.get(token);
			}
		}
	}

	#render() {
		if (this.#is_dragging) {
			let drag_rel = this.#getDragRelConstrained();

			const drag_pos = this.#scroll_pos + this.#tokens_loc.get(this.#drag_token).pos + drag_rel;

			let index;

			for (index = this.#overtake_min; index < this.#overtake_max; index++) {
				const token_loc = [...this.#overtake_tokens_loc[index + 1].values()][index];

				if (token_loc.pos + token_loc.dim / 2 >= drag_pos) {
					break;
				}
			}

			if (index !== this.#drag_index) {
				this.#overtake(index);

				drag_rel = this.#getDragRelConstrained();
			}

			this.#applyRel(this.#drag_token.elements, drag_rel);
		}

		for (const token of this.#tokens) {
			if (!this.#is_dragging || token !== this.#drag_token) {
				this.#applyRel(token.elements, token.rel - this.#scroll_pos);
			}
		}
	}

	#matchToken(element) {
		for (const token of this.#tokens) {
			for (const token_element of token.elements) {
				if (token_element.contains(element)) {
					return token;
				}
			}
		}

		return null;
	}

	#applyRel(elements, rel) {
		for (const element of elements) {
			if (getComputedStyle(element).display === 'contents') {
				this.#applyRel(element.children, rel);
			}
			else {
				element.style[this.#is_vertical ? 'top' : 'left'] = `${rel}px`;
			}
		}
	}

	#getDragConstraints() {
		const drag_token_loc = this.#tokens_loc.get(this.#drag_token);

		return {
			tokens: {
				min: this.#tokens_loc.get(this.#tokens[this.#overtake_min]).pos - drag_token_loc.pos - this.#scroll_pos,
				max: this.#tokens_loc.get(this.#tokens[this.#overtake_max]).pos - drag_token_loc.pos - this.#scroll_pos
			},
			client: {
				min: -drag_token_loc.pos,
				max: this.#getTargetLoc().dim - drag_token_loc.pos - drag_token_loc.dim
			}
		};
	}

	#getDragRelConstrained(constraints = this.#getDragConstraints()) {
		const min = Math.max(constraints.tokens.min, constraints.client.min);
		const max = Math.min(constraints.tokens.max, constraints.client.max);

		return Math.max(Math.min(this.#drag_token.rel, max), min);
	}

	#overtake(index) {
		const drag_token_rel_delta = this.#tokens_loc.get(this.#drag_token).pos
			- this.#overtake_tokens_loc[index].get(this.#drag_token).pos;

		this.#drag_token.rel += drag_token_rel_delta;
		this.#drag_delta += drag_token_rel_delta;

		for (const [token, token_loc] of this.#overtake_tokens_loc[index]) {
			if (token !== this.#drag_token) {
				token.rel += this.#tokens_loc.get(token).pos - token_loc.pos;
				this.#scheduleAnimation(token, token.rel, 0);
			}
		}

		this.#drag_index = index;
		this.#tokens = [...this.#overtake_tokens_loc[index].keys()];
		this.#tokens_loc = this.#overtake_tokens_loc[index];

		this.#sortTokens();
	}

	#getScrollMax() {
		return this.#target[this.#is_vertical ? 'scrollHeight' : 'scrollWidth'] - this.#getTargetLoc().dim;
	}

	#scrollTo(pos) {
		const animation = this.#getAnimation(CSortable.ANIMATION_SCROLL);

		const pos_cur = animation !== null
			? this.#getAnimationProgress(animation, performance.now())
			: this.#scroll_pos;

		const pos_to = Math.max(0, Math.min(this.#getScrollMax(), pos));

		this.#scheduleAnimation(CSortable.ANIMATION_SCROLL, pos_cur, pos_to);

		return pos_to - pos_cur;
	}

	#scrollRel(pos_rel) {
		const animation = this.#getAnimation(CSortable.ANIMATION_SCROLL);

		const pos_to = animation !== null ? animation.to : this.#scroll_pos;

		return this.#scrollTo(
			Math.sign(pos_rel) === Math.sign(pos_to - this.#scroll_pos)
				? pos_rel + pos_to
				: pos_rel + this.#scroll_pos
		);
	}

	#scrollIntoView({pos, dim}) {
		return this.#scrollTo(Math.min(pos, Math.max(this.#scroll_pos, pos + dim - this.#getTargetLoc().dim)));
	}

	#startDrag(client_pos) {
		this.#overtake_tokens_loc = [];

		this.#overtake_min = this.#drag_index;
		this.#overtake_max = this.#drag_index;

		while (this.#overtake_min >= 0 && !this.#tokens[this.#overtake_min].freeze) {
			this.#overtake_min--;
		}

		while (this.#overtake_max < this.#tokens.length && !this.#tokens[this.#overtake_max].freeze) {
			this.#overtake_max++;
		}

		this.#overtake_min++;
		this.#overtake_max--;

		for (let index = this.#overtake_min; index <= this.#overtake_max; index++) {
			const tokens = [...this.#tokens];

			tokens.splice(index, 0, ...tokens.splice(this.#drag_index, 1));

			this.#overtake_tokens_loc[index] = this.#getTokensLoc(tokens);
		}

		this.#is_dragging = true;
		this.#drag_token.rel -= this.#scroll_pos;
		this.#drag_delta = this.#drag_token.rel - client_pos;

		this.#clearAnimation(this.#drag_token);
	}

	#endDrag() {
		this.#cancelDragScroll();
		this.#scheduleAnimation(this.#drag_token, this.#scroll_pos + this.#getDragRelConstrained(), 0);
		this.#scrollIntoView(this.#tokens_loc.get(this.#drag_token));

		this.#is_dragging = false;
	}

	#startSort(client_pos) {
		if (!this.#is_dragging) {
			this.#startDrag(client_pos);

			this.#unobserveTokens();

			this.#target.classList.add(CSortable.ZBX_STYLE_SORTABLE_DRAGGING);

			for (const element of this.#drag_token.elements) {
				element.classList.add(CSortable.ZBX_STYLE_SORTABLE_DRAGGING_TOKEN);
			}

			this.#observeTokens();

			this.#drag_style = document.createElement('style');
			document.head.appendChild(this.#drag_style);
			this.#drag_style.sheet.insertRule('* { pointer-events: none; cursor: grabbing !important; }');

			this.#fire(CSortable.EVENT_DRAG_START, {index: this.#drag_index_original});
		}
	}

	#endSort() {
		if (this.#is_dragging) {
			this.#endDrag();

			this.#unobserveTokens();

			this.#target.classList.remove(CSortable.ZBX_STYLE_SORTABLE_DRAGGING);

			for (const element of this.#drag_token.elements) {
				element.classList.remove(CSortable.ZBX_STYLE_SORTABLE_DRAGGING_TOKEN);
			}

			this.#observeTokens();

			this.#drag_style.remove();

			this.#fire(CSortable.EVENT_DRAG_END, {index: this.#drag_index_original});

			if (this.#drag_index !== this.#drag_index_original) {
				this.#fire(CSortable.EVENT_SORT, {
					index: this.#drag_index_original,
					index_to: this.#drag_index
				});
			}

			this.#skip_click = true;
		}

		this.#drag_token = null;
	}

	#cancelSort() {
		if (this.#is_dragging) {
			if (this.#drag_index !== this.#drag_index_original) {
				this.#overtake(this.#drag_index_original);
			}
		}

		this.#endSort();
	}

	#requestDragScroll(direction = this.#drag_scroll_direction) {
		if (this.#drag_scroll_timeout !== null) {
			clearTimeout(this.#drag_scroll_timeout);
		}

		this.#drag_scroll_direction = direction;

		this.#drag_scroll_timeout = setTimeout(() => {
			this.#drag_scroll_timeout = null;

			const index = this.#drag_index + this.#drag_scroll_direction;

			if (index >= this.#overtake_min && index <= this.#overtake_max) {
				this.#scrollIntoView(this.#tokens_loc.get(this.#tokens[index]));
				this.#requestDragScroll();
			}
		}, this.#animation_time_limit * 1000);
	}

	#cancelDragScroll() {
		if (this.#drag_scroll_timeout !== null) {
			clearTimeout(this.#drag_scroll_timeout);
			this.#drag_scroll_timeout = null;
		}
	}

	#toggleListeners(mode) {
		this.#target.removeEventListener('mousedown', this.#listeners.mouseDown);
		this.#target.removeEventListener('click', this.#listeners.click, {capture: true});
		this.#target.removeEventListener('wheel', this.#listeners.wheel);
		this.#target.removeEventListener('keydown', this.#listeners.keydown);
		this.#target.removeEventListener('focusin', this.#listeners.focusIn);

		removeEventListener('mousemove', this.#listeners.mouseMove);
		removeEventListener('mouseup', this.#listeners.mouseUp);
		removeEventListener('wheel', this.#listeners.wheel, {capture: true});

		switch (mode) {
			case CSortable.LISTENERS_SCROLL:
				this.#target.addEventListener('mousedown', this.#listeners.mouseDown);
				this.#target.addEventListener('wheel', this.#listeners.wheel);
				this.#target.addEventListener('keydown', this.#listeners.keydown);
				this.#target.addEventListener('focusin', this.#listeners.focusIn);

				break;

			case CSortable.LISTENERS_SCROLL_SORT:
				this.#target.addEventListener('mousedown', this.#listeners.mouseDown);
				this.#target.addEventListener('click', this.#listeners.click, {capture: true});

				addEventListener('mousemove', this.#listeners.mouseMove);
				addEventListener('mouseup', this.#listeners.mouseUp);
				addEventListener('wheel', this.#listeners.wheel, {passive: false, capture: true});

				break;
		}
	}

	#observeTokens() {
		this.#mutation_observer.observe(this.#target, {
			subtree: true,
			childList: true,
			attributes: true,
			attributeFilter: ['class'],
			characterData: true
		});
	}

	#unobserveTokens() {
		this.#mutation_observer.disconnect();
	}

	#listeners = {
		mouseDown: (e) => {
			const pos = this.#scroll_pos - this.#getTargetLoc().pos + (this.#is_vertical ? e.clientY : e.clientX);

			this.#drag_token = null;

			for (const [token, token_loc] of this.#tokens_loc) {
				if (pos >= token_loc.pos + token.rel && pos < token_loc.pos + token_loc.dim + token.rel) {
					this.#scrollIntoView(token_loc);

					if (!this.#is_enabled_sorting || token.freeze) {
						break;
					}

					if (this.#selector_handle !== '') {
						const handle = e.target.closest(this.#selector_handle);

						if (handle === null || !this.#target.contains(handle)) {
							break;
						}
					}

					this.#drag_token = token;
					this.#drag_index = this.#tokens.indexOf(token);
					this.#drag_index_original = this.#drag_index;

					this.#toggleListeners(CSortable.LISTENERS_SCROLL_SORT);

					break;
				}
			}
		},

		mouseMove: (e) => {
			const client_pos = this.#is_vertical ? e.clientY : e.clientX;

			this.#startSort(client_pos);

			const rel_old = this.#drag_token.rel;

			this.#drag_token.rel = this.#drag_delta + client_pos;

			const constraints = this.#getDragConstraints();
			const rel_new = this.#getDragRelConstrained(constraints);

			if (rel_new === constraints.client.min && rel_new <= rel_old && this.#drag_token.rel < rel_new) {
				this.#requestDragScroll(-1);
			}
			else if (rel_new === constraints.client.max && rel_new >= rel_old && this.#drag_token.rel > rel_new) {
				this.#requestDragScroll(1);
			}
			else if (rel_new !== constraints.client.min && rel_new !== constraints.client.max) {
				this.#cancelDragScroll();
			}

			this.#render();
		},

		mouseUp: () => {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#endSort();
		},

		click: (e) => {
			if (this.#skip_click) {
				this.#skip_click = false;

				e.stopPropagation();
			}
		},

		wheel: (e) => {
			if (!this.#is_dragging && this.#drag_token !== null) {
				const client_pos = this.#is_vertical ? e.clientY : e.clientX;

				this.#startSort(client_pos);
				this.#drag_token.rel = this.#drag_delta + client_pos;
			}

			this.#cancelDragScroll();

			if (this.#scrollRel(e.deltaY !== 0 ? e.deltaY : e.deltaX) !== 0 || this.#is_dragging) {
				e.preventDefault();
			}

			if (this.#is_dragging) {
				e.stopPropagation();
			}
		},

		keydown: (e) => {
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

			const token = this.#matchToken(e.target);

			if (token === null || token.freeze) {
				return;
			}

			e.preventDefault();

			const index = this.#tokens.indexOf(token);
			const index_to = index + direction;

			if (index_to < 0 || index_to > this.#tokens.length - 1 || this.#tokens[index_to].freeze) {
				return;
			}

			this.#tokens.splice(index, 0, ...this.#tokens.splice(index_to, 1));
			this.#tokens_loc = this.#getTokensLoc(this.#tokens);

			e.target.focus();

			this.#fire(CSortable.EVENT_SORT, {index, index_to});
		},

		focusIn: (e) => {
			this.#target[this.#is_vertical ? 'scrollTop' : 'scrollLeft'] = 0;

			const token = this.#matchToken(e.target);

			if (token !== null) {
				this.#scrollIntoView(this.#tokens_loc.get(token));
			}
		},

		mutation: () => {
			this.#toggleListeners(CSortable.LISTENERS_SCROLL);
			this.#cancelSort();
			this.#updateTokens();
		}
	};

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

	/**
	 * Dispatch event.
	 *
	 * @param {string} type
	 * @param {Object} detail
	 * @param {Object} options
	 *
	 * @returns {boolean}
	 */
	#fire(type, detail = {}, options = {}) {
		return this.#target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
