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


class ZVertical extends HTMLElement {

	/**
	 * @type {HTMLElement|null}
	 */
	#inner_container = null;

	/**
	 * @type {Object}
	 */
	#events = {};

	/**
	 * @type {Object}
	 */
	#events_data = {};

	constructor() {
		super();

		this.attachShadow({mode: 'open'});

		this.#inner_container = document.createElement('div');

		Object.assign(this.#inner_container.style, {
			display: 'inline-block',
			position: 'absolute',
			bottom: 0,
			left: 0,
			transform: 'rotate(270deg)'
		});

		const slot = document.createElement('slot');
		this.#inner_container.append(slot);

		this.shadowRoot.append(this.#inner_container);
	}

	connectedCallback() {
		const props_to_inherit = {
			maxHeight: 'maxWidth',
			maxWidth: 'maxHeight',
			width: 'height',
			height: 'width',
			minWidth: 'minHeight',
			minHeight: 'minWidth',
			textOverflow: 'textOverflow',
			overflow: 'overflow'
		}

		const container_styles = getComputedStyle(this);

		for (const prop in props_to_inherit) {
			// Skip property that is not set or set to default value, as it is already inherited.
			if (!['auto', 'none', '0px'].includes(container_styles[prop])) {
				this.#inner_container.style[props_to_inherit[prop]] = container_styles[prop];
			}
		}

		this.#registerEvents();
		this.#refresh();
	}

	disconnectedCallback() {
		this.#unregisterEvents();
	}

	#refresh() {
		if (this.#inner_container === null) {
			return;
		}

		this.style.width = `${this.#inner_container.scrollHeight}px`;
		this.style.height = `${this.#inner_container.scrollWidth}px`;

		const anchor_position = Math.min(this.#inner_container.scrollHeight, this.#inner_container.scrollWidth) / 2;

		this.#inner_container.style.transformOrigin = `${anchor_position}px ${anchor_position}px`;
	}

	#registerEvents() {
		this.#events = {
			resize: () => {
				this.#refresh();
			},

			update: () => {
				this.#refresh();
			}
		}

		this.#events_data = {
			resize_observer: new ResizeObserver(this.#events.resize),
			mutation_observer: new MutationObserver(this.#events.update)
		}

		this.#events_data.resize_observer.observe(this);
		this.#events_data.mutation_observer.observe(this, {childList: true});
	}

	#unregisterEvents() {
		this.#events_data.resize_observer.disconnect();
		this.#events_data.mutation_observer.disconnect();
	}
}

customElements.define('z-vertical', ZVertical);
