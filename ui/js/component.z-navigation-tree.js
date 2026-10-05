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


class ZNavigationTree extends HTMLElement {

	/** @type {Map<string, object>} */
	#items = new Map();

	/** @type {string[]} */
	#root_ids = [];

	/** @type {Set<string>} */
	#expanded_ids = new Set();

	/** @type {string | null} */
	#selected_id = null;

	/** @type {HTMLElement} */
	#content;

	constructor() {
		super();

		this.#content = document.createElement('div');
		this.#content.className = 'z-navigation-tree-content';
	}

	connectedCallback() {
		if (this.#content.parentNode !== this) {
			this.appendChild(this.#content);
		}

		this.#addEventListeners();
		this.#render();
	}

	disconnectedCallback() {
		this.#removeEventListeners();
	}

	#addEventListeners() {
		this.#content.addEventListener('deselect', this.#onDeselect);
		this.#content.addEventListener('click', this.#onClick);
	}

	#removeEventListeners() {
		this.#content.removeEventListener('click', this.#onClick);
		this.#content.removeEventListener('deselect', this.#onDeselect);
	}

	#onClick = e => {
		const row = e.target instanceof Element
			? e.target.closest('.z-navigation-tree-row')
			: null;

		if (row === null || !this.#content.contains(row)) {
			return;
		}

		const id = row.dataset.id;

		if (id === undefined) {
			return;
		}

		if (event.target.closest('.z-navigation-tree-toggle') !== null) {
			this.#toggle(id);
			return;
		}

		this.#select(id);
	}

	#onDeselect = () => {
		this.#selected_id = null;
		this.#render();
	}

	#toggle(id) {
		const item = this.#items.get(id);

		if (item === undefined || item.children.length === 0) {
			return;
		}

		if (this.#expanded_ids.has(id)) {
			this.#expanded_ids.delete(id);
		}
		else {
			this.#expanded_ids.add(id);
		}

		this.#render();

		this.dispatchEvent(new CustomEvent('toggle', {
			bubbles: true,
			detail: {
				id,
				expanded: this.#expanded_ids.has(id),
				visibleItems: this.visibleItems
			}
		}));
	}

	#select(id) {
		if (!this.#items.has(id)) {
			return;
		}

		this.#selected_id = id;
		this.#render();

		this.dispatchEvent(new CustomEvent('select', {
			bubbles: true,
			detail: {
				id,
				item: this.#items.get(id)
			}
		}));
	}

	#setItems(items) {
		this.#items.clear();
		this.#root_ids = [];

		for (const item of items) {
			const id = String(item.id);

			this.#items.set(id, {
				...item,
				id,
				parent_id: item.parent_id == null ? null : String(item.parent_id),
				children: []
			});
		}

		for (const item of this.#items.values()) {
			if (item.parent_id !== null && this.#items.has(item.parent_id)) {
				this.#items.get(item.parent_id).children.push(item.id);
			}
			else {
				this.#root_ids.push(item.id);
			}
		}

		this.#render();
	}

	#render() {
		if (!this.isConnected) {
			return;
		}

		this.#content.replaceChildren(...this.visibleItems.map(row => this.#createRow(row)));
	}

	#createRow(row) {
		const item = row.item;
		const has_children = item.children.length > 0;
		const is_expanded = this.#expanded_ids.has(item.id);

		const element = document.createElement('div');
		element.className = 'z-navigation-tree-row';
		element.dataset.id = item.id;
		element.style.setProperty('--navigation-tree-depth', String(row.depth));
		element.toggleAttribute('selected', this.#selected_id === item.id);

		const content = document.createElement('div');
		content.className = 'z-navigation-tree-item';

		const title = document.createElement('div');
		title.className = 'z-navigation-tree-title';
		title.textContent = item.label ?? item.name ?? item.id;

		content.appendChild(title);

		if (item.subtitle !== undefined && item.subtitle !== '') {
			const subtitle = document.createElement('div');
			subtitle.className = 'z-navigation-tree-subtitle';
			subtitle.textContent = item.subtitle;
			content.appendChild(subtitle);
		}

		const meta = document.createElement('div');
		meta.className = 'z-navigation-tree-meta';
		meta.textContent = item.meta == null ? '' : String(item.meta);

		const toggle = document.createElement('button');

		if (has_children) {
			toggle.type = 'button';
			toggle.classList.add('btn-icon', is_expanded ? ZBX_ICON_CHEVRON_DOWN : ZBX_ICON_CHEVRON_UP);
		}
		else {
			toggle.disabled = true;
		}

		toggle.classList.add('z-navigation-tree-toggle');

		element.append(toggle, content, meta);

		return element;
	}

	#buildVisibleItems() {
		const rows = [];

		const walk = (id, depth) => {
			const item = this.#items.get(id);

			if (item === undefined) {
				return;
			}

			rows.push({
				id,
				depth,
				item
			});

			if (!this.#expanded_ids.has(id)) {
				return;
			}

			for (const child_id of item.children) {
				walk(child_id, depth + 1);
			}
		};

		for (const root_id of this.#root_ids) {
			walk(root_id, 0);
		}

		return rows;
	}

	expand(id) {
		if (this.#items.has(String(id))) {
			this.#expanded_ids.add(String(id));
			this.#render();
		}
	}

	collapse(id) {
		this.#expanded_ids.delete(String(id));
		this.#render();
	}

	expandToDepth(depth) {
		this.#expanded_ids.clear();

		const walk = (id, current_depth) => {
			const item = this.#items.get(id);

			if (item === undefined || item.children.length === 0 || current_depth >= depth) {
				return;
			}

			this.#expanded_ids.add(id);

			for (const child_id of item.children) {
				walk(child_id, current_depth + 1);
			}
		};

		for (const root_id of this.#root_ids) {
			walk(root_id, 0);
		}

		this.#render();
	}

	get items() {
		return Array.from(this.#items.values());
	}

	set items(value) {
		this.#setItems(Array.isArray(value) ? value : []);
	}

	get visibleItems() {
		return this.#buildVisibleItems();
	}

	get expandedIds() {
		return Array.from(this.#expanded_ids);
	}

	set expandedIds(value) {
		this.#expanded_ids = new Set(Array.isArray(value) ? value.map(String) : []);
		this.#render();
	}

	get selectedId() {
		return this.#selected_id;
	}

	set selectedId(value) {
		this.#selected_id = value == null ? null : String(value);
		this.#render();
	}
}

customElements.define('z-navigation-tree', ZNavigationTree);
