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


class CDetailsPanel {

	/** @type {HTMLElement} */
	#element;

	/** @type {HTMLElement} */
	#groups;

	/** @type {object} */
	#data;

	constructor(container, data) {
		this.#data = this.#normalizeData(data);

		this.#element = document.createElement('div');
		this.#element.className = 'details-panel';

		container.append(this.#element);

		this.#groups = document.createElement('div');
		this.#groups.className = 'details-panel-groups';

		this.#render();
		this.#addEventListeners();
	}

	#addEventListeners() {
		this.#element.addEventListener('click', this.#onClick);
	}

	#removeEventListeners() {
		this.#element.removeEventListener('click', this.#onClick);
	}

	#onClick = event => {
		const button = event.target instanceof Element ? event.target : null;

		if (button === null) {
			return;
		}

		if (button.classList.contains('details-panel-close')) {
			this.#dispatchEvent('close');
		}
		else if (button.classList.contains('details-group-toggle')) {
			const group = button.closest('.details-group');
			const is_collapsed = group.toggleAttribute('collapsed');

			button.setAttribute('aria-expanded', String(!is_collapsed));
			button.classList.toggle(ZBX_ICON_CHEVRON_UP_SMALL, !is_collapsed);
			button.classList.toggle(ZBX_ICON_CHEVRON_DOWN_SMALL, is_collapsed);

			this.#dispatchEvent('toggle', {
				index: Number(group.dataset.index),
				collapsed: is_collapsed
			});
		}
	};

	#render() {
		const header = document.createElement('div');
		header.className = 'details-panel-header';

		const title = document.createElement('div');
		title.className = 'details-panel-title';
		title.textContent = this.#data.title;

		const close_button = document.createElement('button');
		close_button.type = 'button';
		close_button.classList.add('details-panel-close', ZBX_STYLE_BTN_ICON, ZBX_STYLE_BTN_MEDIUM, ZBX_ICON_REMOVE_SMALL);
		close_button.setAttribute('aria-label', t('Close'));

		header.append(title, close_button);

		this.#groups.replaceChildren(...this.#createGroups(this.#data.groups));

		this.#element.replaceChildren(header, this.#groups);
	}

	#createGroups(groups) {
		return groups.map((group, index) => this.#createGroup(group, index));
	}

	#createItems(items) {
		return items.map((item, index) => this.#createItem(item, index));
	}

	#createGroup(group, index) {
		const element = document.createElement('div');
		element.className = 'details-group';
		element.dataset.index = String(index);

		const header = document.createElement('div');
		header.className = 'details-group-header';

		const title = document.createElement('span');
		title.className = 'details-group-title';
		title.textContent = group.title;

		const toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.classList.add('details-group-toggle', ZBX_STYLE_BTN_ICON, ZBX_STYLE_BTN_MEDIUM,
			ZBX_ICON_CHEVRON_UP_SMALL);
		toggle.setAttribute('aria-expanded', 'true');

		header.append(title, toggle);

		const content = document.createElement('div');
		content.className = 'details-group-content';

		content.replaceChildren(...this.#createItems(group.items));

		element.append(header, content);

		return element;
	}

	#createItem(item, index) {
		if (item?.items) {
			return this.#createGroup(item, index);
		}

		const row = document.createElement('div');
		row.className = 'details-item';

		const name = document.createElement('div');
		name.className = 'details-item-name';
		name.textContent = item.name;

		const value = document.createElement('div');
		value.className = 'details-item-value';

		if (item.value instanceof Node) {
			value.appendChild(item.value);
		}
		else {
			value.textContent = item.value;
		}

		row.append(name, value);

		return row;
	}

	#normalizeItems(items) {
		if (!Array.isArray(items)) {
			return [];
		}

		return items.map(item => {
			if (Array.isArray(item?.items)) {
				return {
					title: String(item?.title ?? item?.name ?? ''),
					items: this.#normalizeItems(item.items)
				};
			}

			return {
				name: String(item?.name ?? ''),
				value: item?.value instanceof Node ? item.value : String(item?.value ?? '')
			};
		});
	}

	#normalizeGroups(groups) {
		if (!Array.isArray(groups)) {
			return [];
		}

		return groups.map(group => ({
			title: String(group?.title ?? ''),
			items: this.#normalizeItems(group?.items)
		}));
	}

	#normalizeData(data) {
		return {
			title: String(data?.title ?? ''),
			groups: this.#normalizeGroups(data?.groups)
		};
	}

	#dispatchEvent(type, detail = {}) {
		this.#element.dispatchEvent(new CustomEvent(type, {
			bubbles: true,
			detail
		}));
	}

	get() {
		return this.#element;
	}

	destroy() {
		this.#removeEventListeners();
		this.#element.remove();
	}
}
