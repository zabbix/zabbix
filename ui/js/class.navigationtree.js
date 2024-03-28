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
	static ZBX_STYLE_NODE_IS_OPEN =								'navigation-tree-node-is-open';
	static ZBX_STYLE_NODE_IS_SELECTED =							'navigation-tree-node-is-selected';
	static ZBX_STYLE_NODE_INFO =								'navigation-tree-node-info';
	static ZBX_STYLE_NODE_INFO_HELPERS =						'navigation-tree-node-info-helpers';
	static ZBX_STYLE_NODE_INFO_PRIMARY =						'navigation-tree-node-info-primary';
	static ZBX_STYLE_NODE_INFO_SECONDARY =						'navigation-tree-node-info-secondary';
	static ZBX_STYLE_NODE_INFO_LEVEL =							'navigation-tree-node-info-level';
	static ZBX_STYLE_NODE_INFO_ARROW =							'navigation-tree-node-info-arrow';
	static ZBX_STYLE_NODE_INFO_NAME =							'navigation-tree-node-info-name';
	static ZBX_STYLE_NODE_INFO_MAINTENANCE =					'navigation-tree-node-info-maintenance';
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

	static MAINTENANCE_TYPE_NORMAL = '0';
	static MAINTENANCE_TYPE_NODATA = '1';

	static EVENT_ITEM_SELECT = 'item.select';
	static EVENT_GROUP_TOGGLE = 'group.toggle';

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
			items: [],
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
	 * Get severity object at provided index.
	 *
	 * @param {number} index  Which severity to return.
	 *
	 * @returns {Object}  Severity object with necessary info.
	 */
	static getSeverity(index) {
		const severities = {
			[TRIGGER_SEVERITY_NOT_CLASSIFIED]: {
				name: t('Not classified'),
				class: ZBX_STYLE_NA_BG,
				class_status: ZBX_STYLE_STATUS_NA_BG
			},
			[TRIGGER_SEVERITY_INFORMATION]: {
				name: t('Information'),
				class: ZBX_STYLE_INFO_BG,
				class_status: ZBX_STYLE_STATUS_INFO_BG
			},
			[TRIGGER_SEVERITY_WARNING]: {
				name: t('Warning'),
				class: ZBX_STYLE_WARNING_BG,
				class_status: ZBX_STYLE_STATUS_WARNING_BG
			},
			[TRIGGER_SEVERITY_AVERAGE]: {
				name: t('Average'),
				class: ZBX_STYLE_AVERAGE_BG,
				class_status: ZBX_STYLE_STATUS_AVERAGE_BG
			},
			[TRIGGER_SEVERITY_HIGH]: {
				name: t('High'),
				class: ZBX_STYLE_HIGH_BG,
				class_status: ZBX_STYLE_STATUS_HIGH_BG
			},
			[TRIGGER_SEVERITY_DISASTER]: {
				name: t('Disaster'),
				class: ZBX_STYLE_DISASTER_BG,
				class_status: ZBX_STYLE_STATUS_DISASTER_BG
			}
		};

		return severities[index];
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
			container.dataset.group_identifier = JSON.stringify(node.group_identifier);
		}

		if (node.is_open) {
			container.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_OPEN);
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

		if (this.#show_problems) {
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

			this.#tree_elements.items.push(info);
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
			span.classList.add(node.is_open ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_RIGHT);

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
		const name = document.createElement('span');

		if (node.children?.length > 0) {
			this.#setGroupHint(name, node);
		}
		else {
			name.title = node.name;
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

		const type = maintenance.maintenance_type === CNavigationTree.MAINTENANCE_TYPE_NORMAL
			? t('Maintenance with data collection')
			: t('Maintenance without data collection');

		const description = maintenance.description !== '' ? `\n${maintenance.description}` : '';

		element.dataset.hintboxContents = `${maintenance.name} [${type}]${description}`;
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

		const problems_data = this.#prepareNodeProblems(node);

		this.#setProblemsHint(contents, problems_data);

		problems.appendChild(contents);

		for (const severity of problems_data) {
			const box = document.createElement('span');
			box.classList.add(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM, severity.class_status);
			box.innerText = severity.count;

			contents.appendChild(box);
		}

		return problems;
	}

	/**
	 * Prepare problems data of node.
	 *
	 * @param {Object} node  Node data.
	 **
	 * @returns {Array}  Array of problem objects.
	 */
	#prepareNodeProblems(node) {
		const problems = [];

		for (let i = TRIGGER_SEVERITY_DISASTER; i >= TRIGGER_SEVERITY_NOT_CLASSIFIED; i--) {
			const is_severity_allowed = node.severity_filter !== undefined ? i === node.severity_filter : true;

			if (node.problem_count[i] > 0 && is_severity_allowed) {
				const severity = CNavigationTree.getSeverity(i);

				problems.push({
					severity: severity.name,
					class: severity.class,
					class_status: severity.class_status,
					count: node.problem_count[i],
					order: i,
				});
			}
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
				if (e.target.closest('[data-hintbox="1"]') !== null) {
					return;
				}

				const selected_node = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE}`);
				const selected_id = selected_node.dataset.id;

				this.#selected_id = selected_id;

				for (const node of this.#tree_elements.nodes) {
					node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_SELECTED);

					if (node.dataset.id === selected_id) {
						node.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_SELECTED);
					}
				}

				this.#container.dispatchEvent(new CustomEvent(CNavigationTree.EVENT_ITEM_SELECT, {
					detail: {
						id: selected_id
					}
				}));
			},

			groupToggle: (e) => {
				const node = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE}`);
				const arrow = e.target.closest(`.${CNavigationTree.ZBX_STYLE_NODE_INFO_ARROW} button`);
				let is_open = false;

				if (node.classList.contains(CNavigationTree.ZBX_STYLE_NODE_IS_OPEN)) {
					node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_OPEN);
					arrow.querySelector('span').classList.replace(ZBX_STYLE_ARROW_DOWN, ZBX_STYLE_ARROW_RIGHT);

					const inner_open_nodes = node.querySelectorAll(`.${CNavigationTree.ZBX_STYLE_NODE_IS_OPEN}`);

					for (const inner_open_node of inner_open_nodes) {
						inner_open_node.classList.remove(CNavigationTree.ZBX_STYLE_NODE_IS_OPEN);

						const inner_arrow = inner_open_node
							.querySelector(`.${CNavigationTree.ZBX_STYLE_NODE_INFO_ARROW} button`);

						inner_arrow.querySelector('span').classList.replace(ZBX_STYLE_ARROW_DOWN, ZBX_STYLE_ARROW_RIGHT);
					}
				}
				else {
					node.classList.add(CNavigationTree.ZBX_STYLE_NODE_IS_OPEN);
					arrow.querySelector('span').classList.replace(ZBX_STYLE_ARROW_RIGHT, ZBX_STYLE_ARROW_DOWN);

					is_open = true;
				}

				this.#container.dispatchEvent(new CustomEvent(CNavigationTree.EVENT_GROUP_TOGGLE, {
					detail: {
						group_identifier: JSON.parse(node.dataset.group_identifier),
						is_open
					}
				}));
			}
		};
	}

	/**
	 * Activate events of navigation tree.
	 */
	#activateEvents() {
		for (const item of this.#tree_elements.items) {
			item.addEventListener('click', this.#events.itemSelect);
		}

		for (const arrow of this.#tree_elements.arrows) {
			arrow.addEventListener('click', this.#events.groupToggle);
		}
	}
}
