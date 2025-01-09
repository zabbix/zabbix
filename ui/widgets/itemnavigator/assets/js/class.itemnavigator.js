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


class CItemNavigator {

	static ZBX_STYLE_CLASS =		'item-navigator';
	static ZBX_STYLE_LIMIT =		'item-navigator-limit';

	static GROUP_BY_HOST_GROUP = 0;
	static GROUP_BY_HOST_NAME = 1;
	static GROUP_BY_HOST_TAG_VALUE = 2;
	static GROUP_BY_ITEM_TAG_VALUE = 3;

	static EVENT_ITEM_SELECT = 'item.select';
	static EVENT_GROUP_TOGGLE = 'group.toggle';

	/**
	 * Widget configuration.
	 *
	 * @type {Object}
	 */
	#config;

	/**
	 * Root container element.
	 *
	 * @type {HTMLElement}
	 */
	#container;

	/**
	 * Navigation tree instance.
	 *
	 * @type {CNavigationTree|null}
	 */
	#navigation_tree = null;

	/**
	 * Array of items. Grouped in tree structure if grouping provided.
	 *
	 * @type {Array}
	 */
	#nodes = [];

	/**
	 * All hosts to which retrieved items belong.
	 *
	 * @type {Array}
	 */
	#hosts = [];

	/**
	 * Listeners of item navigator widget.
	 *
	 * @type {Object}
	 */
	#listeners = {};

	/**
	 * @param {Object} config  Widget configuration.
	 */
	constructor(config) {
		this.#config = config;

		this.#container = document.createElement('div');
		this.#container.classList.add(CItemNavigator.ZBX_STYLE_CLASS);

		this.#registerListeners();
	}

	/**
	 * Set list of items.
	 *
	 * @param {Array}       items              Array of items and their info.
	 * @param {Array}       hosts              All hosts to which retrieved items belong.
	 * @param {boolean}     is_limit_exceeded  Whether item limit is exceeded or not.
	 * @param {string|null} selected_itemid    ID of selected item.
	 */
	setValue({items, hosts, is_limit_exceeded, selected_itemid}) {
		if (this.#container !== null) {
			this.#reset();
		}

		this.#hosts = hosts;

		this.#prepareNodesStructure(items);
		this.#prepareNodesProperties(this.#nodes);

		this.#navigation_tree = new CNavigationTree(this.#nodes, {
			selected_id: selected_itemid,
			show_problems: this.#config.show_problems,
			severities: this.#config.severities
		});

		this.#container.classList.remove(ZBX_STYLE_NO_DATA);
		this.#container.appendChild(this.#navigation_tree.getContainer());

		if (is_limit_exceeded) {
			this.#createLimit(items.length);
		}

		this.#activateListeners();
	}

	/**
	 * Get the root container element of item navigator widget.
	 *
	 * @returns {HTMLElement}
	 */
	getContainer() {
		return this.#container;
	}

	/**
	 * Remove the root container element of item navigator widget.
	 */
	destroy() {
		this.#container.remove();
	}

	/**
	 * Prepare structure of nodes - create and sort groups.
	 * If no grouping provided, then leave flat list of items.
	 *
	 * @param {Array} items  Array of items and their info.
	 */
	#prepareNodesStructure(items) {
		if (this.#config.group_by.length > 0) {
			for (const item of items) {
				this.#createGroup(item);
			}

			this.#sortGroups(this.#nodes);

			if (this.#config.show_problems) {
				this.#calculateGroupsProblems(this.#nodes);
			}
		}
		else {
			this.#nodes = items;
		}
	}

	/**
	 * Prepare properties of nodes (groups and items) to fit navigation component.
	 *
	 * @param {Array} nodes  Array of nodes (groups and items) and their info.
	 */
	#prepareNodesProperties(nodes) {
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].children === undefined) {
				nodes[i] = {
					id: nodes[i].itemid,
					name: nodes[i].name,
					level: this.#config.group_by?.length || 0,
					problem_count: nodes[i].problem_count
				};
			}
			else {
				nodes[i].is_open = this.#config.open_groups.includes(JSON.stringify(nodes[i].group_identifier));

				this.#prepareNodesProperties(nodes[i].children);
			}
		}
	}

	/**
	 * Create group for item according to current grouping level.
	 *
	 * @param {Object}      item    Item object.
	 * @param {number}      level   Current grouping level.
	 * @param {Object|null} parent  Parent object (group).
	 */
	#createGroup(item, level = 0, parent = null) {
		const attribute = this.#config.group_by[level];

		switch (attribute.attribute) {
			case CItemNavigator.GROUP_BY_HOST_GROUP:
				for (const hostgroup of this.#hosts[item.hostid].hostgroups) {
					const new_group = {
						...CItemNavigator.#getGroupTemplate(),
						name: hostgroup.name,
						group_by: {
							name: t('Host group')
						},
						group_identifier: parent !== null
							? [...parent.group_identifier, hostgroup.groupid]
							: [hostgroup.groupid],
						level
					};

					this.#insertGroup(new_group, parent, level, item);
				}

				break;

			case CItemNavigator.GROUP_BY_HOST_NAME:
				const new_group = {
					...CItemNavigator.#getGroupTemplate(),
					name: this.#hosts[item.hostid].name,
					group_by: {
						name: t('Host name')
					},
					group_identifier: parent !== null
						? [...parent.group_identifier, item.hostid]
						: [item.hostid],
					level
				};

				this.#insertGroup(new_group, parent, level, item);

				break;

			case CItemNavigator.GROUP_BY_HOST_TAG_VALUE:
			case CItemNavigator.GROUP_BY_ITEM_TAG_VALUE:
				let tags = this.#hosts[item.hostid].tags || [];
				let attribute_name = t('Host tag');

				if (attribute.attribute === CItemNavigator.GROUP_BY_ITEM_TAG_VALUE) {
					tags = item.tags;
					attribute_name = t('Item tag');
				}

				const matching_tags = tags.filter(tag => tag.tag === attribute.tag_name);

				if (matching_tags.length === 0) {
					const new_group = {
						...CItemNavigator.#getGroupTemplate(),
						name: t('Uncategorized'),
						group_by: {
							name: `${attribute_name}: ${attribute.tag_name}`
						},
						group_identifier: parent !== null ? [...parent.group_identifier, null] : [null],
						level,
						is_uncategorized: true
					};

					this.#insertGroup(new_group, parent, level, item);
				}
				else {
					for (const tag of matching_tags) {
						const new_group = {
							...CItemNavigator.#getGroupTemplate(),
							name: tag.value,
							group_by: {
								name: `${attribute_name}: ${attribute.tag_name}`
							},
							group_identifier: parent !== null ? [...parent.group_identifier, tag.value] : [tag.value],
							level
						};

						this.#insertGroup(new_group, parent, level, item);
					}
				}

				break;
		}
	}

	/**
	 * Common properties of groups.
	 *
	 * @returns {Object}  Group object with default values.
	 */
	static #getGroupTemplate() {
		return {
			name: '',
			group_by: {},
			group_identifier: [],
			level: 0,
			is_uncategorized: false,
			problem_count: [0, 0, 0, 0, 0, 0],
			children: [],
			is_open: false
		};
	}

	/**
	 * Insert new group into parent object according to current grouping level.
	 * Add item into last level.
	 *
	 * @param {Object}      new_group  New group object.
	 * @param {Object|null} parent     Parent object (group).
	 * @param {number}      level      Current grouping level.
	 * @param {Object}      item       Item object.
	 */
	#insertGroup(new_group, parent, level, item) {
		const root = parent?.children || this.#nodes;
		const same_group = root.find(group => group.name === new_group.name);

		if (same_group !== undefined) {
			new_group = same_group;
		}
		else {
			root.push(new_group);
		}

		if (level === this.#config.group_by.length - 1) {
			if (!new_group.children.some(child => child.itemid === item.itemid)) {
				new_group.children.push(item);
			}
		}
		else {
			this.#createGroup(item, ++level, new_group);
		}
	}

	/**
	 * Sort sibling groups.
	 *
	 * @param {Array} groups  Array of groups to sort.
	 */
	#sortGroups(groups) {
		groups.sort((a, b) => {
			if (a.is_uncategorized) {
				return 1;
			}
			if (b.is_uncategorized) {
				return -1;
			}

			return a.name.localeCompare(b.name);
		});

		for (const group of groups) {
			if (group.children?.length > 0 && group.level < this.#config.group_by.length - 1) {
				this.#sortGroups(group.children);
			}
		}
	}

	/**
	 * Calculate problems for groups from each unique child item.
	 *
	 * @param {Array}       nodes   Array of nodes.
	 * @param {Object|null} parent  Group object to set problems to.
	 *
	 * @returns {Object}  Problem count of unique items in parent group.
	 */
	#calculateGroupsProblems(nodes, parent = null) {
		let items_problems = {};

		for (const node of nodes) {
			if (node.children?.length > 0) {
				items_problems = {...items_problems, ...this.#calculateGroupsProblems(node.children, node)};
			}
			else {
				items_problems[node.itemid] = node.problem_count;
			}
		}

		if (parent !== null) {
			for (const problem_count of Object.values(items_problems)) {
				for (let i = 0; i < problem_count.length; i++) {
					parent.problem_count[i] += problem_count[i];
				}
			}
		}

		return items_problems;
	}

	/**
	 * Add element that informs about exceeding item limit to container.
	 *
	 * @param {number} limit
	 */
	#createLimit(limit) {
		const element = document.createElement('div');
		element.classList.add(CItemNavigator.ZBX_STYLE_LIMIT);
		element.innerText = t('%1$d of %1$d+ items are shown').replaceAll('%1$d', limit.toString());

		this.#container.appendChild(element);
	}

	/**
	 * Register listeners of item navigator widget.
	 */
	#registerListeners() {
		this.#listeners = {
			itemSelect: e => {
				this.#container.dispatchEvent(new CustomEvent(CItemNavigator.EVENT_ITEM_SELECT, {
					detail: {
						itemid: e.detail.id
					}
				}));
			},

			groupToggle: e => {
				const selected_group_identifier = e.detail.group_identifier;

				if (e.detail.is_open) {
					this.#config.open_groups.push(JSON.stringify(selected_group_identifier));
				}
				else {
					for (let i = 0; i < this.#config.open_groups.length; i++) {
						const open_group_identifier = JSON.parse(this.#config.open_groups[i]);

						if (open_group_identifier.length >= selected_group_identifier.length) {
							let is_subgroup = true;

							for (let j = 0; j < selected_group_identifier.length; j++) {
								if (open_group_identifier[j] !== selected_group_identifier[j]) {
									is_subgroup = false;
									break;
								}
							}

							if (is_subgroup) {
								this.#config.open_groups.splice(i, 1);
								i--;
							}
						}
					}
				}

				this.#container.dispatchEvent(new CustomEvent(CItemNavigator.EVENT_GROUP_TOGGLE, {
					detail: {
						group_identifier: e.detail.group_identifier,
						is_open: e.detail.is_open
					}
				}));
			}
		};
	}

	/**
	 * Activate listeners of item navigator widget.
	 */
	#activateListeners() {
		this.#navigation_tree.getContainer().addEventListener(CNavigationTree.EVENT_ITEM_SELECT,
			this.#listeners.itemSelect
		);
		this.#navigation_tree.getContainer().addEventListener(CNavigationTree.EVENT_GROUP_TOGGLE,
			this.#listeners.groupToggle
		);
	}

	/**
	 * Empty the root container element of item navigator widget and other variables.
	 */
	#reset() {
		this.#container.innerHTML = '';
		this.#navigation_tree = null;
		this.#nodes = [];
	}

	/**
	 * Select item of navigation tree.
	 *
	 * @param {string} item_id  ID of item to select.
	 */
	selectItem(item_id) {
		this.#navigation_tree.selectItem(item_id);
	}
}
