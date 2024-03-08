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


class CNavigationTree {

	static ZBX_STYLE_CLASS =									'navigation-tree';
	static ZBX_STYLE_NODES =									'navigation-tree-nodes';
	static ZBX_STYLE_NODE =										'navigation-tree-node';
	static ZBX_STYLE_NODE_IS_GROUP =							'navigation-tree-node-is-group';
	static ZBX_STYLE_NODE_IS_OPENED =							'navigation-tree-node-is-opened';
	static ZBX_STYLE_NODE_IS_SELECTED =							'navigation-tree-node-is-selected';
	static ZBX_STYLE_NODE_INFO =								'navigation-tree-node-info';
	static ZBX_STYLE_NODE_INFO_HELPERS =						'navigation-tree-node-info-helpers';
	static ZBX_STYLE_NODE_INFO_PRIMARY =						'navigation-tree-node-info-primary';
	static ZBX_STYLE_NODE_INFO_SECONDARY =						'navigation-tree-node-info-secondary';
	static ZBX_STYLE_NODE_INFO_LEVEL =							'navigation-tree-node-info-level';
	static ZBX_STYLE_NODE_INFO_ARROW =							'navigation-tree-node-info-arrow';
	static ZBX_STYLE_NODE_INFO_NAME =							'navigation-tree-node-info-name';
	static ZBX_STYLE_NODE_INFO_MAINTENANCE =					'navigation-tree-node-info-maintenance';
	static ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT =				'navigation-tree-node-info-maintenance-hint';
	static ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT_PRIMARY =		'navigation-tree-node-info-maintenance-hint-primary';
	static ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT_SECONDARY =		'navigation-tree-node-info-maintenance-hint-secondary';
	static ZBX_STYLE_NODE_INFO_PROBLEMS =						'navigation-tree-node-info-problems';
	static ZBX_STYLE_NODE_INFO_PROBLEMS_HINT =					'navigation-tree-node-info-problems-hint';
	static ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY =			'navigation-tree-node-info-problems-hint-severity';
	static ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_COLOR =	'navigation-tree-node-info-problems-hint-severity-color';
	static ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_NAME =	'navigation-tree-node-info-problems-hint-severity-name';
	static ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_COUNT =	'navigation-tree-node-info-problems-hint-severity-count';
	static ZBX_STYLE_NODE_INFO_GROUP_HINT =						'navigation-tree-node-info-group-hint';
	static ZBX_STYLE_NODE_INFO_GROUP_HINT_ATTRIBUTE =			'navigation-tree-node-info-group-hint-attribute';
	static ZBX_STYLE_NODE_INFO_GROUP_HINT_VALUE =				'navigation-tree-node-info-group-hint-value';
	static ZBX_STYLE_NODE_CHILDREN =							'navigation-tree-node-children';
	static ZBX_STYLE_GROUP_UNCATEGORIZED =						'navigation-tree-group-uncategorized';

	/**
	 * Root container element.
	 *
	 * @type {HTMLElement}
	 */
	#container;

	/**
	 * Container element of nodes.
	 *
	 * @type {HTMLElement}
	 */
	#container_nodes;

	/**
	 * Events of navigation tree.
	 *
	 * @type {Object}
	 */
	#events = {};

	/**
	 * Navigation tree elements (nodes, arrows, names, etc.).
	 *
	 * @type {Object}
	 */
	#tree_elements = {};

	/**
	 * ID of selected item.
	 *
	 * @type {string}
	 */
	#selected_id = '';

	/**
	 * Whether to show problems or not.
	 *
	 * @type {boolean}
	 */
	#show_problems = true;

	/**
	 * Create CNavigationTree instance.
	 *
	 * @param {Array}   nodes          Array of nodes data.
	 * @param {string}  selected_id    ID of selected item. Empty string if none selected.
	 * @param {boolean} show_problems  Whether to show problems or not.
	 *
	 * @returns {CNavigationTree}
	 */
	constructor(nodes, {
		selected_id = '',
		show_problems = true
	} = {}) {
		this.#selected_id = selected_id;
		this.#show_problems = show_problems;

		this.#tree_elements = {
			nodes: [],
			item_names: [],
			arrows: []
		};

		this.#container = document.createElement('div');
		this.#container.classList.add(CNavigationTree.ZBX_STYLE_CLASS);

		this.#container_nodes = document.createElement('div');
		this.#container_nodes.classList.add(CNavigationTree.ZBX_STYLE_NODES);

		this.#container.appendChild(this.#container_nodes);

		for (const node of nodes) {
			this.#container_nodes.appendChild(this.#createNode(node));
		}

		this.#registerEvents();
		this.#activateEvents();

		if (this.#selected_id !== '') {
			this.#container.querySelector(`.${CNavigationTree.ZBX_STYLE_NODE}[data-id="${this.#selected_id}"]
				.${CNavigationTree.ZBX_STYLE_NODE_INFO_NAME}`)
					?.click();
		}
	}

	/**
	 * Get the root container element of navigation tree.
	 *
	 * @returns {HTMLElement}
	 */
	getContainer() {
		return this.#container;
	}

	/**
	 * Create node element of navigation tree.
	 *
	 * @param {Object} node  All data (including children) of node.
	 *
	 * @returns {HTMLElement}
	 */
	#createNode(node) {
		const container = document.createElement('div');
		container.classList.add(CNavigationTree.ZBX_STYLE_NODE);
		container.dataset.level = node.level;

		if (node.children?.length > 0) {
			container.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_GROUP);
		}

		if (node.is_opened) {
			container.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_OPENED);
		}

		if (node.is_uncategorized) {
			container.classList.add(CNavigationTree.ZBX_STYLE_GROUP_UNCATEGORIZED);
		}

		const info = document.createElement('div');
		info.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO);

		container.appendChild(info);

		const helpers = document.createElement('div');
		helpers.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_HELPERS);

		info.appendChild(helpers);

		const primary = document.createElement('div');
		primary.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PRIMARY);

		info.appendChild(primary);

		const secondary = document.createElement('div');
		secondary.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_SECONDARY);

		info.appendChild(secondary);

		helpers.appendChild(this.#createLevel());
		helpers.appendChild(this.#createArrow(node));

		primary.appendChild(this.#createName(node));

		if (node.maintenance !== undefined) {
			primary.appendChild(this.#createMaintenance(node));
		}

		if (this.#show_problems && node.problems?.length > 0) {
			secondary.appendChild(this.#createProblems(node));
		}

		this.#tree_elements.nodes.push(container);

		if (node.children?.length > 0) {
			const children = document.createElement('div');
			children.classList.add(CNavigationTree.ZBX_STYLE_NODE_CHILDREN);

			container.appendChild(children);

			for (const child of node.children) {
				children.appendChild(this.#createNode(child));
			}
		}
		else {
			container.dataset.id = node.id;
		}

		return container;
	}

	/**
	 * Create level element of node.
	 *
	 * @returns {HTMLElement}
	 */
	#createLevel() {
		const level = document.createElement('div');
		level.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_LEVEL);

		return level;
	}

	/**
	 * Create arrow element of node.
	 *
	 * @param {Object} node  Node data.
	 *
	 * @returns {HTMLElement}
	 */
	#createArrow(node) {
		const arrow = document.createElement('div');
		arrow.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_ARROW);

		if (node.children?.length > 0) {
			const button = document.createElement('button');
			button.type = 'button';

			const span = document.createElement('span');
			span.classList.add(node.is_opened ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_RIGHT);

			button.appendChild(span);
			arrow.appendChild(button);

			this.#tree_elements.arrows.push(button);
		}

		return arrow;
	}

	/**
	 * Create name element of node.
	 *
	 * @param {Object} node  Node data.
	 *
	 * @returns {HTMLElement}
	 */
	#createName(node) {
		let name;

		if (node.children?.length > 0) {
			name = document.createElement('span');

			this.#setGroupHint(name, node);
		}
		else {
			name = document.createElement('a');
			name.href = '#';
			name.title = node.name;

			this.#tree_elements.item_names.push(name);
		}

		name.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_NAME);
		name.innerText = node.name;

		return name;
	}

	/**
	 * Set hint for group.
	 *
	 * @param {HTMLElement} element  Element to add hint to.
	 * @param {Object}      node     Node data.
	 */
	#setGroupHint(element, node) {
		element.dataset.hintbox = '1';
		element.dataset.hintboxStatic = '1';

		const hint = document.createElement('div');
		hint.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_GROUP_HINT);

		const attribute = document.createElement('span');
		attribute.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_GROUP_HINT_ATTRIBUTE);
		attribute.innerText = `${node.group_by.name}: `;

		hint.appendChild(attribute);

		const value = document.createElement('span');
		value.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_GROUP_HINT_VALUE);
		value.innerText = node.name;

		if (node.is_uncategorized) {
			value.classList.add(CNavigationTree.ZBX_STYLE_GROUP_UNCATEGORIZED);
		}

		hint.appendChild(value);

		element.dataset.hintboxContents = hint.outerHTML;
	}

	/**
	 * Create maintenance element of node.
	 *
	 * @param {Object} node  Node data.
	 *
	 * @returns {HTMLElement}
	 */
	#createMaintenance(node) {
		const maintenance = document.createElement('div');
		maintenance.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_MAINTENANCE);

		const button = document.createElement('button');
		button.type = 'button';
		button.classList.add(ZBX_STYLE_BTN_ICON, ZBX_STYLE_BTN_SMALL, ZBX_ICON_WRENCH_ALT_SMALL,
			ZBX_STYLE_COLOR_WARNING);

		this.#setMaintenanceHint(button, node.maintenance);

		maintenance.appendChild(button);

		return maintenance;
	}

	/**
	 * Set hint for maintenance.
	 *
	 * @param {HTMLElement} element      Element to add hint to.
	 * @param {Object}      maintenance  Maintenance data.
	 */
	#setMaintenanceHint(element, maintenance) {
		element.dataset.hintbox = '1';
		element.dataset.hintboxStatic = '1';

		const hint = document.createElement('div');
		hint.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT);

		const primary = document.createElement('div');
		primary.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT_PRIMARY);
		primary.innerText = `${maintenance.name} [${maintenance.type === '1'
			? t('Maintenance without data collection')
			: t('Maintenance with data collection')}]`;

		hint.appendChild(primary);

		const secondary = document.createElement('div');
		secondary.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_MAINTENANCE_HINT_SECONDARY);
		secondary.innerText = maintenance.description;

		hint.appendChild(secondary);

		element.dataset.hintboxContents = hint.outerHTML;
	}

	/**
	 * Create problems element of node.
	 *
	 * @param {Object} node  Node data.
	 *
	 * @returns {HTMLElement}
	 */
	#createProblems(node) {
		const problems = document.createElement('div');
		problems.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS);

		const contents = document.createElement('a');
		contents.classList.add(ZBX_STYLE_PROBLEM_ICON_LINK);
		contents.href = '#';

		this.#setProblemsHint(contents, node.problems);

		problems.appendChild(contents);

		for (const severity of node.problems) {
			const box = document.createElement('span');
			box.classList.add(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM, severity.class_status);
			box.innerText = severity.count;

			contents.appendChild(box);
		}

		return problems;
	}

	/**
	 * Set hint for problems.
	 *
	 * @param {HTMLElement} element   Element to add hint to.
	 * @param {Object}      problems  Problems data.
	 */
	#setProblemsHint(element, problems) {
		element.dataset.hintbox = '1';
		element.dataset.hintboxStatic = '1';

		const hint = document.createElement('div');
		hint.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS_HINT);

		for (const severity of problems) {
			const row = document.createElement('div');
			row.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY);

			hint.appendChild(row);

			const color = document.createElement('span');
			color.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_COLOR, severity.class);

			row.appendChild(color);

			const name = document.createElement('span');
			name.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_NAME);
			name.innerText = `${severity.severity}: `;

			row.appendChild(name);

			const count = document.createElement('span');
			count.classList.add(CNavigationTree.ZBX_STYLE_NODE_INFO_PROBLEMS_HINT_SEVERITY_COUNT);
			count.innerText = severity.count;

			row.appendChild(count);
		}

		element.dataset.hintboxContents = hint.outerHTML;
	}

	/**
	 * Register events of navigation tree.
	 */
	#registerEvents() {
		this.#events = {
			itemSelect: (e) => {
				const selected_node = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE}`);
				const selected_id = selected_node.dataset.id;

				this.#selected_id = selected_id;

				for (const node of this.#tree_elements.nodes) {
					node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_SELECTED);

					if (node.dataset.id === selected_id) {
						node.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_SELECTED);
					}
				}

				this.#container.dispatchEvent(new CustomEvent('item.select', {detail: {id: selected_id}}));
			},

			groupToggle: (e) => {
				const node = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE}`);
				const arrow = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE_INFO_ARROW} button`);
				let is_closed = '1';

				if (node.classList.contains(CNavigationTree.ZBX_STYLE_NODE_IS_OPENED)) {
					node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_OPENED);
					arrow.querySelector('span').classList.add(ZBX_STYLE_ARROW_RIGHT);
					arrow.querySelector('span').classList.remove(ZBX_STYLE_ARROW_DOWN);

					const inner_opened_nodes = node.querySelectorAll(`.${CNavigationTree.ZBX_STYLE_NODE_IS_OPENED}`);

					for (const inner_opened_node of inner_opened_nodes) {
						inner_opened_node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_OPENED);

						const inner_arrow = inner_opened_node
							.querySelector(`.${CNavigationTree.ZBX_STYLE_NODE_INFO_ARROW} button`);

						inner_arrow.querySelector('span').classList.add(ZBX_STYLE_ARROW_RIGHT);
						inner_arrow.querySelector('span').classList.remove(ZBX_STYLE_ARROW_DOWN);

						this.#container.dispatchEvent(new CustomEvent('group.toggle', {detail: {group_id: '123', is_closed}}));
					}
				}
				else {
					node.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_OPENED);
					arrow.querySelector('span').classList.add(ZBX_STYLE_ARROW_DOWN);
					arrow.querySelector('span').classList.remove(ZBX_STYLE_ARROW_RIGHT);
					is_closed = '0';
				}

				this.#container.dispatchEvent(new CustomEvent('group.toggle', {detail: {group_id: '123', is_closed}}));
			},
		};
	}

	/**
	 * Activate events of navigation tree.
	 */
	#activateEvents() {
		for (const item_name of this.#tree_elements.item_names) {
			item_name.addEventListener('click', this.#events.itemSelect);
		}

		for (const arrow of this.#tree_elements.arrows) {
			arrow.addEventListener('click', this.#events.groupToggle);
		}
	}
}
