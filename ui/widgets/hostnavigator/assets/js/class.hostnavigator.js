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


class CHostNavigator {

	static ZBX_STYLE_CLASS =		'host-navigator';
	static ZBX_STYLE_LIMIT =		'host-navigator-limit';

	static GROUP_BY_HOST_GROUP = 0;
	static GROUP_BY_TAG_VALUE = 1;
	static GROUP_BY_SEVERITY = 2;

	static EVENT_HOST_SELECT = 'host.select';
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
	 * Array of hosts. Grouped in tree structure if grouping provided.
	 *
	 * @type {Array}
	 */
	#nodes = [];

	/**
	 * All maintenances between retrieved hosts.
	 *
	 * @type {Object}
	 */
	#maintenances = {};

	/**
	 * Listeners of host navigator widget.
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
		this.#container.classList.add(CHostNavigator.ZBX_STYLE_CLASS);

		this.#registerListeners();
	}

	/**
	 * Set list of hosts.
	 *
	 * @param {Array}       hosts              Array of hosts and their info.
	 * @param {Object}      maintenances       Info about all maintenances between hosts.
	 * @param {boolean}     is_limit_exceeded  Whether host limit is exceeded or not.
	 * @param {string|null} selected_hostid    ID of selected host
	 */
	setValue({hosts, maintenances, is_limit_exceeded, selected_hostid}) {
		if (this.#container !== null) {
			this.#reset();
		}

		this.#maintenances = maintenances;

		this.#prepareNodesStructure(hosts);
		this.#prepareNodesProperties(this.#nodes);

		this.#navigation_tree = new CNavigationTree(this.#nodes, {
			selected_id: selected_hostid,
			show_problems: this.#config.show_problems,
			severities: this.#config.severities
		});

		this.#container.classList.remove(ZBX_STYLE_NO_DATA);
		this.#container.appendChild(this.#navigation_tree.getContainer());

		if (is_limit_exceeded) {
			this.#createLimit(hosts.length);
		}

		this.#activateListeners();
	}

	/**
	 * Get the root container element of host navigator widget.
	 *
	 * @returns {HTMLElement}
	 */
	getContainer() {
		return this.#container;
	}

	/**
	 * Remove the root container element of host navigator widget.
	 */
	destroy() {
		this.#container.remove();
	}

	/**
	 * Prepare structure of nodes - create and sort groups.
	 * If no grouping provided, then leave flat list of hosts.
	 *
	 * @param {Array} hosts  Array of hosts and their info.
	 */
	#prepareNodesStructure(hosts) {
		if (this.#config.group_by.length > 0) {
			for (const host of hosts) {
				this.#createGroup(host);
			}

			this.#sortGroups(this.#nodes);

			if (this.#config.show_problems) {
				this.#calculateGroupsProblems(this.#nodes);
			}
		}
		else {
			this.#nodes = hosts;
		}
	}

	/**
	 * Prepare properties of nodes (groups and hosts) to fit navigation component.
	 *
	 * @param {Array} nodes  Array of nodes (groups and hosts) and their info.
	 */
	#prepareNodesProperties(nodes) {
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].children === undefined) {
				const properties = {
					id: nodes[i].hostid,
					name: nodes[i].name,
					level: this.#config.group_by?.length || 0,
					problem_count: nodes[i].problem_count
				}

				if (nodes[i].maintenanceid !== undefined) {
					properties.maintenance = this.#maintenances[nodes[i].maintenanceid];
				}

				nodes[i] = properties;
			}
			else {
				nodes[i].is_open = this.#config.open_groups.includes(JSON.stringify(nodes[i].group_identifier));

				nodes[i].severity_filter = nodes[i].group_by.attribute === CHostNavigator.GROUP_BY_SEVERITY
					? nodes[i].severity_index
					: undefined;

				this.#prepareNodesProperties(nodes[i].children);
			}
		}
	}

	/**
	 * Create group for host according to current grouping level.
	 *
	 * @param {Object}      host    Host object.
	 * @param {number}      level   Current grouping level.
	 * @param {Object|null} parent  Parent object (group).
	 */
	#createGroup(host, level = 0, parent = null) {
		const attribute = this.#config.group_by[level];

		switch (attribute.attribute) {
			case CHostNavigator.GROUP_BY_HOST_GROUP:
				for (const hostgroup of host.hostgroups) {
					const new_group = {
						...CHostNavigator.#getGroupTemplate(),
						name: hostgroup.name,
						group_by: {
							attribute: CHostNavigator.GROUP_BY_HOST_GROUP,
							name: t('Host group')
						},
						group_identifier: parent !== null
							? [...parent.group_identifier, hostgroup.groupid]
							: [hostgroup.groupid],
						level
					};

					this.#insertGroup(new_group, parent, level, host);
				}

				break;

			case CHostNavigator.GROUP_BY_TAG_VALUE:
				const matching_tags = host.tags.filter(tag => tag.tag === attribute.tag_name);

				if (matching_tags.length === 0) {
					const new_group = {
						...CHostNavigator.#getGroupTemplate(),
						name: t('Uncategorized'),
						group_by: {
							attribute: CHostNavigator.GROUP_BY_TAG_VALUE,
							name: attribute.tag_name
						},
						group_identifier: parent !== null ? [...parent.group_identifier, null] : [null],
						level,
						is_uncategorized: true
					};

					this.#insertGroup(new_group, parent, level, host);
				}
				else {
					for (const tag of matching_tags) {
						const new_group = {
							...CHostNavigator.#getGroupTemplate(),
							name: tag.value,
							group_by: {
								attribute: CHostNavigator.GROUP_BY_TAG_VALUE,
								name: attribute.tag_name
							},
							group_identifier: parent !== null ? [...parent.group_identifier, tag.value] : [tag.value],
							level
						};

						this.#insertGroup(new_group, parent, level, host);
					}
				}

				break;

			case CHostNavigator.GROUP_BY_SEVERITY:
				const has_problems = host.problem_count.some(count => count > 0);

				if (!has_problems) {
					const new_group = {
						...CHostNavigator.#getGroupTemplate(),
						name: t('Uncategorized'),
						group_by: {
							attribute: CHostNavigator.GROUP_BY_SEVERITY,
							name: t('Severity')
						},
						group_identifier: parent !== null ? [...parent.group_identifier, null] : [null],
						level,
						is_uncategorized: true,
						severity_index: -1
					};

					this.#insertGroup(new_group, parent, level, host);
				}
				else {
					for (let i = 0; i < host.problem_count.length; i++) {
						if (host.problem_count[i] > 0) {
							const new_group = {
								...CHostNavigator.#getGroupTemplate(),
								name: this.#config.severities[i].label,
								group_by: {
									attribute: CHostNavigator.GROUP_BY_SEVERITY,
									name: t('Severity')
								},
								group_identifier: parent !== null ? [...parent.group_identifier, i] : [i],
								level,
								severity_index: i
							};

							this.#insertGroup(new_group, parent, level, host);
						}
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
	 * Add host into last level.
	 *
	 * @param {Object}      new_group  New group object.
	 * @param {Object|null} parent     Parent object (group).
	 * @param {number}      level      Current grouping level.
	 * @param {Object}      host       Host object.
	 */
	#insertGroup(new_group, parent, level, host) {
		const root = parent?.children || this.#nodes;
		const same_group = root.find(group => group.name === new_group.name);

		if (same_group !== undefined) {
			new_group = same_group;
		}
		else {
			root.push(new_group);
		}

		if (level === this.#config.group_by.length - 1) {
			if (!new_group.children.some(child => child.hostid === host.hostid)) {
				new_group.children.push(host);
			}
		}
		else {
			this.#createGroup(host, ++level, new_group);
		}
	}

	/**
	 * Sort sibling groups.
	 *
	 * @param {Array} groups  Array of groups to sort.
	 */
	#sortGroups(groups) {
		if (groups[0].group_by.attribute === CHostNavigator.GROUP_BY_SEVERITY) {
			groups.sort((a, b) => b.severity_index - a.severity_index);
		}
		else {
			groups.sort((a, b) => {
				if (a.is_uncategorized) {
					return 1;
				}
				if (b.is_uncategorized) {
					return -1;
				}

				return a.name.localeCompare(b.name);
			});
		}

		for (const group of groups) {
			if (group.children?.length > 0 && group.level < this.#config.group_by.length - 1) {
				this.#sortGroups(group.children);
			}
		}
	}

	/**
	 * Calculate problems for groups from each unique child host.
	 *
	 * @param {Array}       nodes   Array of nodes.
	 * @param {Object|null} parent  Group object to set problems to.
	 *
	 * @returns {Object}  Problem count of unique hosts in parent group.
	 */
	#calculateGroupsProblems(nodes, parent = null) {
		let hosts_problems = {};

		for (const node of nodes) {
			if (node.children?.length > 0) {
				hosts_problems = {...hosts_problems, ...this.#calculateGroupsProblems(node.children, node)};
			}
			else {
				hosts_problems[node.hostid] = node.problem_count;
			}
		}

		if (parent !== null) {
			for (const problem_count of Object.values(hosts_problems)) {
				for (let i = 0; i < problem_count.length; i++) {
					parent.problem_count[i] += problem_count[i];
				}
			}
		}

		return hosts_problems;
	}

	/**
	 * Add element that informs about exceeding host limit to container.
	 *
	 * @param {number} limit
	 */
	#createLimit(limit) {
		const element = document.createElement('div');
		element.classList.add(CHostNavigator.ZBX_STYLE_LIMIT);
		element.innerText = t('%1$d of %1$d+ hosts are shown').replaceAll('%1$d', limit.toString());

		this.#container.appendChild(element);
	}

	/**
	 * Register listeners of host navigator widget.
	 */
	#registerListeners() {
		this.#listeners = {
			hostSelect: e => {
				this.#container.dispatchEvent(new CustomEvent(CHostNavigator.EVENT_HOST_SELECT, {
					detail: {
						hostid: e.detail.id
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

				this.#container.dispatchEvent(new CustomEvent(CHostNavigator.EVENT_GROUP_TOGGLE, {
					detail: {
						group_identifier: e.detail.group_identifier,
						is_open: e.detail.is_open
					}
				}));
			}
		};
	}

	/**
	 * Activate listeners of host navigator widget.
	 */
	#activateListeners() {
		this.#navigation_tree.getContainer().addEventListener(CNavigationTree.EVENT_ITEM_SELECT,
			this.#listeners.hostSelect
		);
		this.#navigation_tree.getContainer().addEventListener(CNavigationTree.EVENT_GROUP_TOGGLE,
			this.#listeners.groupToggle
		);
	}

	/**
	 * Empty the root container element of host navigator widget and other variables.
	 */
	#reset() {
		this.#container.innerHTML = '';
		this.#navigation_tree = null;
		this.#nodes = [];
		this.#maintenances = {};
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
