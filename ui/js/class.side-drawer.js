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
	static EVENT_BEFORE_CLOSE = 'sidedrawer.before-close';
	static EVENT_CLOSE = 'sidedrawer.close';
	static EVENT_POSITION = 'sidedrawer.position';

	/** @type {HTMLElement} */
	#split_view_element;

	/** @type {HTMLElement} */
	#content_pane_element;

	/** @type {HTMLElement} */
	#drawer_element;

	/** @type {AbortController|null} */
	#abort_controller = null;

	constructor({
		position = null,
		position_min = null,
		position_max = null
	} = {}) {
		this.#split_view_element = document.querySelector(`z-split-view`);

		for (const [attribute, value] of
				Object.entries({'fixed-size': position, min: position_min, max: position_max})) {
			if (value !== null) {
				this.#split_view_element.setAttribute(attribute, value);
			}
		}

		this.#split_view_element.addEventListener('split-end', () => {
			this.dispatchEvent(CSideDrawer.EVENT_POSITION, {
				position: this.#split_view_element.getAttribute('fixed-size')
			});
		});

		this.#content_pane_element = document.querySelector(`z-split-view-pane.wrapper`);
		this.#drawer_element = document.querySelector(`z-split-view-pane.side-drawer`);
		this.#drawer_element.classList.add('closed');
	}

	getElement() {
		return this.#drawer_element;
	}

	/**
	 * @returns {Promise<any>}
	 */
	open(promise_open, abort_controller = null) {
		this.#abort_controller?.abort();
		this.#abort_controller = abort_controller;

		this.#drawer_element.classList.remove('closed');

		this.#drawer_element.classList.add(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);

		return promise_open
			.then(response => {
				if ('error' in response) {
					this.#handleError(response.error);

					return;
				}

				this.dispatchEvent(CSideDrawer.EVENT_OPEN, {response});
			})
			.catch(error => {
				if ((abort_controller?.signal.aborted ?? false) || error.name === 'TypeError') {
					return;
				}

				this.#handleError({title: error.name, messages: [error.message]});
			})
			.finally(() => {
				this.#bindEvents();

				this.#drawer_element.classList.remove(ZBX_STYLE_LOADING, ZBX_STYLE_LOADING_FADEIN);

				if (this.#abort_controller === abort_controller) {
					this.#abort_controller = null;
				}
		});
	}

	/**
	 * @returns {Promise<any>}
	 */
	close() {
		return Promise.resolve().then(() => {
			this.dispatchEvent(CSideDrawer.EVENT_BEFORE_CLOSE);

			this.#unbindEvents();

			this.#drawer_element.innerHTML = '';
			this.#drawer_element.classList.add('closed');

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

	#bindEvents() {
		const close_button = this.#drawer_element.querySelector('.js-close-button');
		close_button?.addEventListener('click', this.#onClose);

		document.addEventListener('keyup', this.#onKeyUp);
	}

	#unbindEvents() {
		const close_button = this.#drawer_element.querySelector('.js-close-button');
		close_button?.removeEventListener('click', this.#onClose);

		document.removeEventListener('keyup', this.#onKeyUp);
	}

	#handleError(error) {
		const $msg_box = makeMessageBox('bad', error.messages ?? [], error.title ?? t('Unexpected server error.'));

		this.#drawer_element.innerHTML = '';
		this.#drawer_element.appendChild($msg_box[0]);
	}

	#onClose = () => {
		this.close();
	}

	#onKeyUp = e => {
		if (e.key !== 'Escape') {
			return;
		}

		this.close();
	}
}
