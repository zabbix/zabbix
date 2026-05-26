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


class CPager {

	static EVENT_SELECT = 'select';
	static EVENT_STATE_CHANGE = 'state:change';

	static RANGE = 11;

	/**
	 * @type {HTMLElement}
	 */
	#element;

	/**
	 * @type {number}
	 */
	#page;

	/**
	 * @type {AbortController}
	 */
	#state_abort_controller;

	/**
	 * @type {Object<string, function>}
	 */
	#events = {
		[CPager.EVENT_SELECT]: this.onSelect
	};

	constructor(element) {
		this.#element = element;

		this.#page = new CState().getParams().get('page') || 1;

		this.#state_abort_controller?.abort();
		this.#state_abort_controller = new AbortController();

		window.addEventListener('popstate', e => {
			const page = new CState(e.target.location.href).getParams().get('page') || 1;

			this.dispatchEvent(CPager.EVENT_SELECT, {page});
		}, { signal: this.#state_abort_controller.signal });

		this.#bindEvents();
	}

	/**
	 * @returns {number}
	 */
	getPage() {
		return this.#page;
	}

	/**
	 * @param {CustomEvent} e
	 */
	onSelect(e) {
		const {page} = e.detail;

		this.#page = page;

		this.dispatchEvent(CPager.EVENT_STATE_CHANGE, {page});
	}

	update({page, rows_per_page, num_rows, limit_exceeded}) {
		this.#element.querySelector(`.${ZBX_STYLE_PAGER_CONTAINER}`)?.remove();
		this.#element.querySelector(`.${ZBX_STYLE_TABLE_STATS}`)?.remove();

		if (this.#page != page) {
			this.#page = page;

			this.dispatchEvent(CPager.EVENT_STATE_CHANGE, {page});
		}

		const num_pages = Math.max(1, Math.round(Math.ceil(num_rows / rows_per_page)));
		page = Math.max(1, Math.min(num_pages, page));

		const total = limit_exceeded ? `${num_rows}+` : num_rows;
		const start = (page - 1) * rows_per_page;
		const end = Math.min(num_rows, start + rows_per_page);

		const nav = document.createElement('nav');
		nav.classList.add(ZBX_STYLE_PAGER_CONTAINER);
		nav.setAttribute('role', 'navigation');
		nav.setAttribute('aria-label', 'Pager');

		if (num_pages > 1) {
			const end_page = Math.min(num_pages, Math.max(CPager.RANGE, page + Math.floor(CPager.RANGE / 2)));
			const start_page = Math.max(1, end_page - CPager.RANGE + 1);

			if (start_page > 1) {
				const first = document.createElement('a');
				first.setAttribute('aria-label', t('Go to first page'));
				first.setAttribute('href', 'javascript:void(0);');
				first.textContent = t('First');
				first.addEventListener('click', () => this.dispatchEvent(CPager.EVENT_SELECT, {page: 1}));

				nav.appendChild(first);
			}

			if (page > 1) {
				const prev = document.createElement('a');
				prev.setAttribute('aria-label', sprintf(t('Go to previous page, %1$s'), page - 1));
				prev.setAttribute('href', 'javascript:void(0);');
				prev.addEventListener('click', () => this.dispatchEvent(CPager.EVENT_SELECT, {page: page - 1}));

				const arrow = document.createElement('span');
				arrow.classList.add('arrow-left');
				prev.appendChild(arrow);

				nav.appendChild(prev);
			}

			for (let i = start_page; i <= end_page; i++) {
				const current = document.createElement('a');
				current.setAttribute('href', 'javascript:void(0);');
				current.textContent = i.toString();
				current.addEventListener('click', () => this.dispatchEvent(CPager.EVENT_SELECT, {page: i}));

				if (i == page) {
					current.classList.add('paging-selected');
					current.setAttribute('aria-label', sprintf(t('Go to page %1$s, current page'), i));
					current.setAttribute('aria-current', 'true');
				}
				else {
					current.setAttribute('aria-label', sprintf(t('Go to page %1$s'), i));
				}

				nav.appendChild(current);
			}

			if (page < num_pages) {
				const next = document.createElement('a');
				next.setAttribute('aria-label', sprintf(t('Go to next page, %1$s'), page + 1));
				next.setAttribute('href', 'javascript:void(0);');
				next.addEventListener('click', () => this.dispatchEvent(CPager.EVENT_SELECT, {page: page + 1}));

				const arrow = document.createElement('span');
				arrow.classList.add('arrow-right');
				next.appendChild(arrow);

				nav.appendChild(next);
			}

			if (end_page < num_pages) {
				const last = document.createElement('a');
				last.setAttribute('aria-label', sprintf(t('Go to last page, %1$s'), num_pages));
				last.setAttribute('href', 'javascript:void(0);');
				last.textContent = t('Last');
				last.addEventListener('click', () => this.dispatchEvent(CPager.EVENT_SELECT, {page: num_pages}));

				nav.appendChild(last);
			}
		}

		const stats = document.createElement('div');
		stats.classList.add(ZBX_STYLE_TABLE_STATS);

		if (num_rows > 0) {
			if (num_pages == 1) {
				stats.textContent = sprintf(t('Displaying %1$s of %2$s found'), num_rows, total);
			}
			else {
				stats.textContent = sprintf(t('Displaying %1$s to %2$s of %3$s found'), start + 1, end, total);
			}
		}

		this.#element.classList.add(ZBX_STYLE_PAGER);

		this.#element.appendChild(nav);
		this.#element.appendChild(stats);
	}

	/**
	 * @param {string}   event
	 * @param {function} callback
	 * @returns {CPager}
	 */
	on(event, callback) {
		this.#element.addEventListener(event, callback.bind(this));

		return this;
	}

	/**
	 * @param {string}   event
	 * @param {function} callback
	 * @returns {CPager}
	 */
	off(event, callback) {
		this.#element.addEventListener(event, callback.bind(this));

		return this;
	}

	/**
	 * @param {string} type
	 * @param {Object} detail
	 * @param {Object} options
	 */
	dispatchEvent(type, detail = {}, options = {}) {
		return this.#element.dispatchEvent(new CustomEvent(type, {...options, detail}));
	}

	/**
	 * Binds all events to their corresponding handlers.
	 */
	#bindEvents() {
		Object.entries(this.#events).forEach(([name, callback]) => this.on(name, callback));
	}
}
