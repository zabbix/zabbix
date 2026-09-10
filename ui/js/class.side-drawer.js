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


class CSideDrawer {

	static EVENT_OPEN = 'sidedrawer.open';
	static EVENT_CLOSE = 'sidedrawer.close';

	/** @type {HTMLElement} */
	#split_view_element;

	/** @type {HTMLElement} */
	#content_pane_element;

	/** @type {HTMLElement} */
	#drawer_element;

	/** @type {HTMLElement} */
	#target_container_element;

	/** @type {HTMLElement} */
	#original_container_element;

	/** @type {AbortController|null} */
	#abort_controller = null;

	constructor(container, {split_view_class = 'split-view', content_pane_class = 'content',
			drawer_pane_class = 'side-drawer'} = {}) {

		this.#target_container_element = container;
		this.#original_container_element = container.cloneNode();

		this.#split_view_element = document.querySelector(`z-split-view.${split_view_class}`);
		if (this.#split_view_element === null) {
			this.#split_view_element = document.createElement('z-split-view');
			this.#split_view_element.classList.add('split-view', split_view_class);
		}

		this.#content_pane_element = document.querySelector(`z-split-view-pane.${content_pane_class}`);
		if (this.#content_pane_element === null) {
			this.#content_pane_element = document.createElement('z-split-view-pane');
			this.#content_pane_element.classList.add(content_pane_class);

			this.#split_view_element.prepend(this.#content_pane_element);
		}

		this.#drawer_element = document.querySelector(`z-split-view-pane.${drawer_pane_class}`);
		if (this.#drawer_element === null) {
			this.#drawer_element = document.createElement('z-split-view-pane');
			this.#drawer_element.classList.add(drawer_pane_class);

			this.#split_view_element.appendChild(this.#drawer_element);
		}
	}

	getElement() {
		return this.#drawer_element;
	}

	/**
	 * @returns {Promise<any>}
	 */
	open(url, options = {}) {
		this.#abort_controller?.abort();
		this.#abort_controller = new AbortController();

		this.#mount();

		this.#drawer_element.classList.add(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);

		return fetch(url, {...options, signal: this.#abort_controller.signal})
			.then(response => response.json())
			.then(response => {
				this.#bindEvents();

				this.dispatchEvent(CSideDrawer.EVENT_OPEN, {response});
			})
			.finally(() => {
				this.#drawer_element.classList.remove(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);
			});
	}

	/**
	 * @returns {Promise<any>}
	 */
	close() {
		this.#abort_controller?.abort();
		this.#abort_controller = null;

		return Promise.resolve().then(() => {
			this.#unbindEvents();
			this.#unmount();

			this.dispatchEvent(CSideDrawer.EVENT_CLOSE);
		});
	}

	on(event, callback, options = undefined) {
		this.#drawer_element.addEventListener(event, callback, options);

		return this;
	}

	off(event, callback, options = undefined) {
		this.#drawer_element.removeEventListener(event, callback, options);

		return this;
	}

	dispatchEvent(type, detail = {}, options = {}) {
		return this.#drawer_element.dispatchEvent(new CustomEvent(type, {...options, detail}));
	}

	#mount() {
		if (this.#split_view_element.isConnected) {
			return;
		}

		this.#content_pane_element.append(...this.#target_container_element.childNodes);

		this.#target_container_element.replaceWith(this.#split_view_element);
	}

	#unmount() {
		if (!this.#split_view_element.isConnected) {
			return;
		}

		this.#original_container_element.append(...this.#content_pane_element.childNodes);

		this.#split_view_element.replaceWith(this.#original_container_element);

		this.#target_container_element = this.#original_container_element;
	}

	#bindEvents() {
		document.addEventListener('keyup', this.#onKeyUp);
	}

	#unbindEvents() {
		document.removeEventListener('keyup', this.#onKeyUp);
	}

	#onKeyUp = e => {
		if (e.key !== 'Escape') {
			return;
		}

		this.close();
	}
}
