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


class CWidgetNavTree extends CWidget {

	/**
	 * @type {boolean}
	 */
	#has_content = false;

	/**
	 * @type {Array}
	 */
	#navtree = [];

	/**
	 * @type {number|null}
	 */
	#navtree_item_selected = null;

	/**
	 * @type {Array}
	 */
	#navtree_items_opened = [];

	/**
	 * @type {Object|null}
	 */
	#problems = null;

	/**
	 * @type {Object|null}
	 */
	#severity_levels = null;

	/**
	 * @type {Array}
	 */
	#maps_accessible = [];

	/**
	 * @type {boolean}
	 */
	#show_unavailable = false;

	/**
	 * @type {number}
	 */
	#max_depth = 10;

	/**
	 * @type {Object}
	 */
	#event_handlers;

	/**
	 * @type {number}
	 */
	#last_id = 0;

	onStart() {
		this.#registerEvents();
	}

	onActivate() {
		this.#activateContentEvents();
	}

	onDeactivate() {
		this.#deactivateContentEvents();
	}

	getDataCopy({is_single_copy}) {
		this.#deactivateContentEvents();

		this.#setTreeHandlers();
		this.#updateWidgetFields();

		this.#activateContentEvents();

		return super.getDataCopy({is_single_copy});
	}

	onEdit() {
		if (this.#has_content) {
			this.#deactivateContentEvents();
			this.#removeTree();
		}

		if (this.getState() === WIDGET_STATE_ACTIVE) {
			this.#makeTree();
			this.#activateContentEvents();
		}
	}

	onFeedback({type, value}) {
		if (type !== CWidgetsData.DATA_TYPE_MAP_ID) {
			return;
		}

		const sysmapid = value[0];

		const item_selected = this.#navtree[this.#navtree_item_selected];

		let new_item_id = 0;

		for (const [id, item] of Object.entries(this.#navtree)) {
			if (item.sysmapid == sysmapid && item.parent == this.#navtree_item_selected) {
				new_item_id = id;
				break;
			}
		}

		if (new_item_id == 0) {
			for (const [id, item] of Object.entries(this.#navtree)) {
				if (item.sysmapid == sysmapid && item_selected.parent == id) {
					new_item_id = id;
					break;
				}
			}
		}

		if (new_item_id != 0 && this.#markTreeItemSelected(new_item_id)) {
			this.#openBranch(this.#navtree_item_selected);
			this.#updateUserProfile();

			return true;
		}

		return false;
	}

	processUpdateResponse(response) {
		this.clearContents();

		super.processUpdateResponse(response);

		if (response.navtree_data !== undefined) {
			this.#has_content = true;

			this.#navtree = response.navtree_data.navtree;
			this.#navtree_item_selected = response.navtree_data.navtree_item_selected;
			this.#navtree_items_opened = response.navtree_data.navtree_items_opened;

			this.#problems = response.navtree_data.problems;
			this.#severity_levels = response.navtree_data.severity_levels;

			this.#maps_accessible = response.navtree_data.maps_accessible;
			this.#show_unavailable = response.navtree_data.show_unavailable;

			this.#max_depth = response.navtree_data.max_depth;

			this.#makeTree();
			this.#activateContentEvents();
		}
	}

	onClearContents() {
		if (this.#has_content) {
			this.#deactivateContentEvents();
			this.#removeTree();

			this.#has_content = false;
		}
	}

	#makeTree() {
		if (!this.#has_content) {
			return;
		}

		const tree = this.#buildTree();

		let root = this.#makeTreeBranch();
		root.classList.add('root');

		this._target.querySelector('.tree').appendChild(root);

		if (this.isEditMode()) {
			const root_item = this.#makeTreeItem({name: t('root'), id: 0}, 0, false);

			root.appendChild(root_item);

			if (tree.length > 0) {
				if (root_item.classList.contains('closed')) {
					root_item.classList.add('opened');
					root_item.classList.remove('closed');
				}
			}

			root = document.getElementById(`${this.getUniqueId()}_children-of-0`);
		}

		for (const item of tree) {
			root.appendChild(this.#makeTreeItem(item));
		}

		this.#setTreeHandlers();
		this.#activateTree();
	}

	#buildTree(parent_id = 0) {
		const tree = [];

		for (const i in this.#navtree) {
			const tree_item = {
				id: i,
				name: this.#navtree[i].name,
				order: this.#navtree[i].order,
				parent: this.#navtree[i].parent,
				sysmapid: this.#navtree[i].sysmapid
			};

			if (tree_item.id > this.#last_id) {
				this.#last_id = tree_item.id;
			}

			if (tree_item.parent == parent_id) {
				const children = this.#buildTree(tree_item.id);

				if (children.length > 0) {
					tree_item.children = children;
				}

				tree_item.item_active = (tree_item.sysmapid == 0 || this.#maps_accessible.includes(tree_item.sysmapid));
				tree_item.item_visible = (this.#show_unavailable || tree_item.item_active);

				tree.push(tree_item);
			}
		}

		tree.sort((a, b) => {
			return a.order - b.order;
		});

		return tree;
	}

	#makeTreeItem(item, depth = 1, editable = true) {
		const li_item = document.createElement('li');

		li_item.classList.add('tree-item');

		if (!editable || this.#navtree_items_opened.includes(item.id)) {
			li_item.classList.add('opened');
		}
		else {
			li_item.classList.add('closed');
		}

		if (!editable) {
			li_item.classList.add('root-item');
		}

		if (this.isEditMode() && item.sysmapid == 0) {
			li_item.classList.add('no-map');
		}

		const ul = this.#makeTreeBranch(item.id);

		if (item.children !== undefined && this.#max_depth > depth) {
			let child_items_visible = 0;

			for (const child of item.children) {
				if (typeof child !== 'object') {
					continue;
				}

				ul.appendChild(this.#makeTreeItem(child, depth + 1));

				if (child.id > this.#last_id) {
					this.#last_id = child.id;
				}

				if (child.item_visible === true) {
					child_items_visible++;
				}
			}

			if (item.children.length > 0 && child_items_visible > 0) {
				li_item.classList.add('is-parent');
			}
		}

		if (!this.isEditMode() && item.sysmapid != 0 && !item.item_active) {
			li_item.classList.add('inaccessible');
		}

		let link;

		if (!this.isEditMode() && item.sysmapid != 0 && item.item_active) {
			link = document.createElement('a');

			link.href = '#';
			link.setAttribute('data-sysmapid', item.sysmapid);
		}
		else {
			link = document.createElement('span');
		}

		link.title = item.name;
		link.innerText = item.name;
		link.classList.add('item-name');

		li_item.id = `${this.getUniqueId()}_tree-item-${item.id}`;
		li_item.setAttribute('data-id', item.id);

		if (item.sysmapid != 0) {
			li_item.setAttribute('data-sysmapid', item.sysmapid);
		}

		if (item.item_visible === false) {
			li_item.style.display = 'none';
		}

		const tree_row = document.createElement('div');
		tree_row.classList.add('tree-row');
		li_item.appendChild(tree_row);

		const content = document.createElement('div');
		content.classList.add('content');
		tree_row.appendChild(content);

		const margin_lvl = document.createElement('div');
		margin_lvl.classList.add('margin-lvl');
		content.appendChild(margin_lvl);

		if (this.isEditMode()) {
			const tools = document.createElement('div');
			tools.classList.add('tools');
			tree_row.appendChild(tools);

			const button_add_child = document.createElement('button');
			button_add_child.type = 'button';
			button_add_child.title = t('Add child element');
			button_add_child.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_PLUS, 'js-add-child');
			button_add_child.setAttribute('data-id', item.id);
			tools.appendChild(button_add_child);

			const button_add_maps = document.createElement('button');
			button_add_maps.type = 'button';
			button_add_maps.title = t('Add multiple maps');
			button_add_maps.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_COPY, 'js-add-maps');
			button_add_maps.setAttribute('data-id', item.id);
			tools.appendChild(button_add_maps);

			if (editable) {
				const button_edit = document.createElement('button');
				button_edit.type = 'button';
				button_edit.title = t('Edit');
				button_edit.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_PENCIL, 'js-edit');
				button_edit.setAttribute('data-id', item.id);
				tools.appendChild(button_edit);

				const button_remove = document.createElement('button');
				button_remove.type = 'button';
				button_remove.title = t('Remove');
				button_remove.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_REMOVE_SMALL, 'js-remove');
				button_remove.setAttribute('data-id', item.id);
				tools.appendChild(button_remove);

				const drag = document.createElement('div');
				drag.classList.add('drag-icon');
				content.appendChild(drag);
			}
		}
		else {
			const problems = document.createElement('div');
			problems.classList.add('problem-icon-list');
			tree_row.appendChild(problems);
		}

		const arrow = document.createElement('div');
		arrow.classList.add('arrow');
		content.appendChild(arrow);

		if (editable) {
			const arrow_btn = document.createElement('button');
			const arrow_span = document.createElement('span');

			arrow_btn.type = 'button';
			arrow_btn.classList.add('treeview');
			arrow_span.classList.add(li_item.classList.contains('opened') ? 'arrow-down' : 'arrow-right');
			arrow_btn.appendChild(arrow_span);
			arrow.appendChild(arrow_btn);
			arrow_btn.addEventListener('click', () => {
				const branch = arrow_btn.closest('[data-id]');

				let closed_state = '1';

				if (branch.classList.contains('opened')) {
					arrow_btn.querySelector('span').classList.add('arrow-right');
					arrow_btn.querySelector('span').classList.remove('arrow-down');

					branch.classList.add('closed');
					branch.classList.remove('opened');
				}
				else {
					arrow_btn.querySelector('span').classList.add('arrow-down');
					arrow_btn.querySelector('span').classList.remove('arrow-right');

					branch.classList.add('opened');
					branch.classList.remove('closed');

					closed_state = '0';
				}

				if (this.getWidgetId() !== null) {
					updateUserProfile(`web.dashboard.widget.navtree.item-${branch.getAttribute('data-id')}.toggle`,
						closed_state, [this.getWidgetId()]
					);

					const index = this.#navtree_items_opened.indexOf(branch.getAttribute('data-id'));

					if (index > -1) {
						if (closed_state === '1') {
							this.#navtree_items_opened.splice(index, 1);
						}
						else {
							this.#navtree_items_opened.push(branch.getAttribute('data-id'));
						}
					}
					else if (index === -1 && closed_state === '0') {
						this.#navtree_items_opened.push(branch.getAttribute('data-id'));
					}
				}
			});
		}

		content.appendChild(link);
		li_item.appendChild(ul);

		if (this.isEditMode() && editable) {
			const name_fld = document.createElement('input');
			name_fld.id = `${this.getUniqueId()}_navtree.${item.id}.name`;
			name_fld.type = 'hidden';
			name_fld.name = `navtree[${item.id}][name]`;
			name_fld.value = item.name;
			li_item.appendChild(name_fld);

			const parent_fld = document.createElement('input');
			parent_fld.id = `${this.getUniqueId()}_navtree.${item.id}.parent`;
			parent_fld.type = 'hidden';
			parent_fld.name = `navtree[${item.id}][parent]`;
			parent_fld.value = item.parent || 0;
			li_item.appendChild(parent_fld);

			const mapid_fld = document.createElement('input');
			mapid_fld.id = `${this.getUniqueId()}_navtree.${item.id}.sysmapid`;
			mapid_fld.type = 'hidden';
			mapid_fld.name = `navtree[${item.id}][sysmapid]`;
			mapid_fld.value = item.sysmapid;
			li_item.appendChild(mapid_fld);
		}

		return li_item;
	}

	#makeTreeBranch(parent_id = null) {
		const ul = document.createElement('ul');

		if (parent_id !== null) {
			ul.id = `${this.getUniqueId()}_children-of-${parent_id}`;
		}

		ul.classList.add('tree-list');

		return ul;
	}

	#activateTree() {
		if (this.isEditMode()) {
			this.#makeSortable();
		}
		else {
			this.#parseProblems();

			if (!this.hasEverUpdated() && this.isReferred()) {
				const item_selected = this.#getDefaultSelectable();

				if (this.#markTreeItemSelected(item_selected)) {
					this.#updateUserProfile();
					this.#broadcast();
				}
			}
			else if (this.#navtree_item_selected !== null) {
				this.#markTreeItemSelected(this.#navtree_item_selected);
			}
		}
	}

	#getDefaultSelectable() {
		return jQuery('.tree-item:visible', jQuery(this._target))
			.not('[data-sysmapid="0"]')
			.first()
			.data('id');
	}

	#broadcast() {
		this.broadcast({
			[CWidgetsData.DATA_TYPE_MAP_ID]: [this.#navtree[this.#navtree_item_selected].sysmapid]
		});
	}

	#updateUserProfile() {
		updateUserProfile('web.dashboard.widget.navtree.item.selected',
			this.#navtree_item_selected, [this.getWidgetId()]
		);
	}

	#removeTree() {
		const root = this._target.querySelector('.root');

		if (root !== null) {
			root.remove();
		}
	}

	#setTreeHandlers() {
		// Add .is-parent class for branches with sub-items.
		jQuery('.tree-list', jQuery(this._target)).not('.ui-sortable, .root').each(function() {
			if (jQuery('>li', jQuery(this)).not('.inaccessible').length) {
				jQuery(this).closest('.tree-item').addClass('is-parent');
			}
			else {
				jQuery(this).closest('.tree-item').removeClass('is-parent');
			}
		});

		// Set [data-depth] for list and each sublist.
		jQuery('.tree-list', jQuery(this._target)).each(function() {
			jQuery(this).attr('data-depth', jQuery(this).parents('.tree-list').length);
		});

		// Change arrow style.
		jQuery('.is-parent', jQuery(this._target)).each(function() {
			const $arrow = jQuery('> .tree-row > .content > .arrow > .treeview > span', jQuery(this));

			if (jQuery(this).hasClass('opened')) {
				$arrow.removeClass('arrow-right').addClass('arrow-down');
			}
			else {
				$arrow.removeClass('arrow-down a1').addClass('arrow-right');
			}
		});

		for (const tree_element of document.querySelectorAll('.tree-list')) {
			for (const button of tree_element.querySelectorAll('.js-add-child, .js-add-maps')) {
				button.disabled = tree_element.dataset.depth >= this.#max_depth;
			}
		}
	}

	#markTreeItemSelected(itemid) {
		const selected_item = document.getElementById(`${this.getUniqueId()}_tree-item-${itemid}`);
		const item = this.#navtree[itemid];

		if (item === undefined || selected_item === null) {
			return false;
		}

		this.#navtree_item_selected = itemid;

		let step_in_path = selected_item.closest('.tree-item');

		this._target.querySelectorAll('.selected').forEach((selected) => {
			selected.classList.remove('selected');
		});

		while (step_in_path !== null) {
			step_in_path.classList.add('selected');
			step_in_path = step_in_path.parentNode.closest('.tree-item');
		}

		return true;
	}

	#openBranch(itemid) {
		if (!jQuery(`.tree-item[data-id=${itemid}]`).is(':visible')) {
			const selector = '> .tree-row > .content > .arrow > .treeview > span';

			let $branch_to_open = jQuery(`.tree-item[data-id=${itemid}]`).closest('.tree-list').not('.root');

			while ($branch_to_open.length) {
				$branch_to_open.closest('.tree-item.is-parent')
					.removeClass('closed')
					.addClass('opened');

				jQuery(selector, $branch_to_open.closest('.tree-item.is-parent'))
					.removeClass('arrow-right')
					.addClass('arrow-down');

				$branch_to_open = $branch_to_open.closest('.tree-item.is-parent')
					.closest('.tree-list').not('.root');
			}
		}
	}

	#getNextId() {
		this.#last_id++;

		while (jQuery(`[name="navtree[${this.#last_id}][name]"]`).length) {
			this.#last_id++;
		}

		return this.#last_id;
	}

	#makeSortable() {
		jQuery('.root-item > .tree-list', jQuery(this._target))
			.sortable_tree({
				max_depth: this.#max_depth,
				stop: () => {
					this.#setTreeHandlers();
					this.#updateWidgetFields();
				}
			})
			.disableSelection();
	}

	#parseProblems() {
		if (this.#severity_levels === null) {
			return false;
		}

		const empty_template = {};

		for (const [severity, _] in Object.entries(this.#severity_levels)) {
			empty_template[severity] = 0;
		}

		for (const [itemid, problems] of Object.entries(this.#problems)) {
			for (const [severity, value] of Object.entries(problems || empty_template)) {
				if (value) {
					this._target.querySelectorAll(`.tree-item[data-id="${itemid}"]`).forEach((item) => {
						item.setAttribute(`data-problems${severity}`, value);
					})
				}
			}
		}

		for (const [severity, value] of Object.entries(this.#severity_levels)) {
			for (const problem of this._target.querySelectorAll(`[data-problems${severity}]`)) {
				const indicator = document.createElement('span');

				indicator.title = value.name;
				indicator.classList.add('problem-icon-list-item', value.style_class);
				indicator.innerText = problem.getAttribute(`data-problems${severity}`);

				problem.querySelector('.tree-row > .problem-icon-list').appendChild(indicator)
			}
		}
	}

	#updateWidgetFields() {
		if (!this.isEditMode()) {
			return;
		}

		const prefix = `${this.getUniqueId()}_`;

		this._fields.navtree = {};

		jQuery('input[name$="[name]"]', jQuery(this._body)).each((index, field) => {
			const matches = field.getAttribute('name').match(/^navtree\[(\d+)\]/);
			const id = matches[1];

			const parent = document.getElementById(`${prefix}navtree.${id}.parent`).value;
			const sysmapid = document.getElementById(`${prefix}navtree.${id}.sysmapid`).value;
			const element = document.getElementById(`${prefix}children-of-${parent}`);

			let order = 0;

			if (element !== null) {
				const sibling = element.childNodes;

				while (sibling[order] !== undefined && sibling[order].getAttribute('data-id') != id) {
					order++;
				}
			}

			this._fields.navtree[id] = {name: field.value};

			if (parent != 0) {
				this._fields.navtree[id].parent = parent;
			}

			if (order != 0) {
				this._fields.navtree[id].order = order + 1;
			}

			if (sysmapid != 0) {
				this._fields.navtree[id].sysmapid = sysmapid;
			}
		});
	}

	#itemEditDialog(id, parent, depth, trigger_element) {
		const url = new Curl('zabbix.php');
		const item_edit = id != 0;

		url.setArgument('action', 'widget.navtree.item.edit');

		jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: {
				name: item_edit ? this._target.querySelector(`[name="navtree[${id}][name]"]`).value : '',
				sysmapid: item_edit ? this._target.querySelector(`[name="navtree[${id}][sysmapid]"]`).value : 0,
				depth: depth
			},
			dataType: 'json',
			success: (response) => {
				let content = response.body;

				if (response.error !== undefined) {
					content = makeMessageBox('bad', response.error.messages, response.error.title, false)[0];
				}

				if (response.debug !== undefined) {
					content += response.debug;
				}

				overlayDialogue({
					'title': t('Edit tree element'),
					'class': 'modal-popup',
					'content': content,
					'buttons': [
						{
							'title': item_edit ? t('Apply') : t('Add'),
							'class': 'dialogue-widget-save',
							'enabled': response.error === undefined,
							'action': (overlay) => {
								const form = document.getElementById('widget-dialogue-form');
								const form_inputs = form.elements;
								const url = new Curl('zabbix.php');

								url.setArgument('action', 'widget.navtree.item.update');

								overlay.setLoading();

								overlay.xhr = $.ajax({
									url: url.getUrl(),
									method: 'POST',
									data: {
										name: form_inputs.name.value.trim(),
										sysmapid: form_inputs.sysmapid !== undefined
											? form_inputs.sysmapid.value
											: '0',
										add_submaps: form_inputs.add_submaps !== undefined
										&& form_inputs.add_submaps.checked ? 1 : 0,
										depth: depth
									},
									dataType: 'json',
									complete: () => {
										overlay.unsetLoading();
									},
									success: (response) => {
										form.querySelectorAll('.msg-bad').forEach((msg) => {
											msg.remove();
										})

										if ('error' in response) {
											const message_box = makeMessageBox('bad', response.error.messages,
												response.error.title
											)[0];

											form.insertAdjacentElement('afterbegin', message_box);

											return false;
										}
										else {
											this.#deactivateContentEvents();

											if (item_edit) {
												const $row = jQuery(`[data-id="${id}"]`, jQuery(this._target));

												jQuery(`[name="navtree[${id}][name]"]`, $row).val(response.name);
												jQuery(`[name="navtree[${id}][sysmapid]"]`, $row)
													.val(response['sysmapid']);
												jQuery('> .tree-row > .content > .item-name', $row)
													.empty()
													.attr('title', response['name'])
													.append(jQuery('<span>').text(response.name));
												$row.toggleClass('no-map', response.sysmapid == 0);
											}
											else {
												const root = this._target
													.querySelector(`.tree-item[data-id="${parent}"]>ul.tree-list`);

												id = this.#getNextId();

												if (root !== null) {
													root.append(this.#makeTreeItem({
														id: id,
														name: response['name'],
														sysmapid: response['sysmapid'],
														parent: parent
													}, depth + 1));

													root.closest('.tree-item').classList.remove('closed');
													root.closest('.tree-item').classList.add('opened', 'is-parent');
												}
											}

											const add_child_level = (sysmapid, itemid, depth) => {
												const root = this._target
													.querySelector(`.tree-item[data-id="${itemid}"]>ul.tree-list`);

												if (root === null) {
													return;
												}

												const tree_item = root.closest('.tree-item');

												if (tree_item.classList.contains('is-parent')) {
													tree_item.classList.remove('closed');
													tree_item.classList.add('opened');
												}

												if (response.hierarchy[sysmapid] !== undefined && itemid !== undefined
														&& depth <= this.#max_depth) {
													$.each(response.hierarchy[sysmapid], (i, submapid) => {
														if (response.submaps[submapid] === undefined) {
															return;
														}

														const same_consecutive_submap = response.hierarchy[sysmapid]
															.filter((id, index) => id === submapid && index < i).length;

														const added_submap = root.querySelectorAll(
															`:scope>.tree-item[data-sysmapid="${submapid}"]`
														)[same_consecutive_submap];

														let submap_itemid;

														if (added_submap !== undefined) {
															submap_itemid = added_submap.dataset.id;
														}
														else {
															submap_itemid = this.#getNextId();

															const submap_item = response.submaps[submapid];

															root.append(this.#makeTreeItem({
																id: submap_itemid,
																name: submap_item['name'],
																sysmapid: submap_item['sysmapid'],
																parent: itemid
															}));
														}

														add_child_level(submapid, submap_itemid, depth + 1);
													});

													tree_item.classList.add('is-parent');
												}
											};

											add_child_level(response['sysmapid'], id, depth + 1);

											overlayDialogueDestroy(overlay.dialogueid);
											this.#updateWidgetFields();
											this.#setTreeHandlers();
											this.#activateContentEvents();
										}
									}
								});

								return false;
							},
							'isSubmit': true
						},
						{
							'title': t('Cancel'),
							'class': 'btn-alt',
							'action': () => {}
						}
					],
					'dialogueid': 'navtreeitem',
					'script_inline': response.script_inline
				}, trigger_element);
			}
		});
	}

	#activateContentEvents() {
		if (this.#has_content && this.getState() === WIDGET_STATE_ACTIVE) {
			if (this.isEditMode()) {
				for (const button of this._target.querySelectorAll('.js-add-child')) {
					button.addEventListener('click', this.#event_handlers.addChild);
				}

				for (const button of this._target.querySelectorAll('.js-add-maps')) {
					button.addEventListener('click', this.#event_handlers.addMaps);
				}

				for (const button of this._target.querySelectorAll('.js-edit')) {
					button.addEventListener('click', this.#event_handlers.editItem);
				}

				for (const button of this._target.querySelectorAll('.js-remove')) {
					button.addEventListener('click', this.#event_handlers.removeItem);
				}
			}
			else {
				for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
					link.addEventListener('click', this.#event_handlers.select);
				}
			}
		}
	}

	#deactivateContentEvents() {
		if (this.#has_content) {
			if (this.isEditMode()) {
				for (const button of this._target.querySelectorAll('.js-add-child')) {
					button.removeEventListener('click', this.#event_handlers.addChild);
				}

				for (const button of this._target.querySelectorAll('.js-add-maps')) {
					button.removeEventListener('click', this.#event_handlers.addMaps);
				}

				for (const button of this._target.querySelectorAll('.js-edit')) {
					button.removeEventListener('click', this.#event_handlers.editItem);
				}

				for (const button of this._target.querySelectorAll('.js-remove')) {
					button.removeEventListener('click', this.#event_handlers.removeItem);
				}
			}
			else {
				for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
					link.removeEventListener('click', this.#event_handlers.select);
				}
			}
		}
	}

	#registerEvents() {
		this.#event_handlers = {
			addChild: (e) => {
				const button = e.target;
				const depth = parseInt(button.closest('.tree-list').getAttribute('data-depth'));
				const parent = button.getAttribute('data-id');

				this.#itemEditDialog(0, parent, depth + 1, button);
			},

			addMaps: (e) => {
				const button = e.target;
				const depth = parseInt(button.closest('.tree-list').getAttribute('data-depth'));
				const id = button.getAttribute('data-id');

				if (typeof window.addPopupValues === 'function') {
					window.old_addPopupValues = window.addPopupValues;
				}

				window.addPopupValues = (data) => {
					this.#deactivateContentEvents();

					const root = this._target.querySelector(`.tree-item[data-id="${id}"] > ul.tree-list`);

					if (root !== null) {
						for (const item of data.values) {
							root.appendChild(this.#makeTreeItem({
								id: this.#getNextId(),
								name: item.name,
								sysmapid: item.id,
								parent: id
							}, depth + 1));
						}

						const tree_item = root.closest('.tree-item');

						tree_item.classList.remove('closed');
						tree_item.classList.add('opened');
					}

					this.#setTreeHandlers();
					this.#updateWidgetFields();

					if (typeof old_addPopupValues === 'function') {
						window.addPopupValues = old_addPopupValues;
						delete window.old_addPopupValues;
					}

					this.#activateContentEvents();
				};

				return PopUp('popup.generic', {
					srctbl: 'sysmaps',
					srcfld1: 'sysmapid',
					srcfld2: 'name',
					multiselect: '1'
				}, {dialogue_class: 'modal-popup-generic', trigger_element: e.target});
			},

			editItem: (e) => {
				const button = e.target;

				const id = button.getAttribute('data-id');
				const parent = this._target.querySelector(`input[name="navtree[${id}][parent]"]`).value;
				const depth = parseInt(button.closest('[data-depth]').getAttribute('data-depth'));

				this.#itemEditDialog(id, parent, depth, button);
			},

			removeItem: (e) => {
				const button = e.target;

				this.#deactivateContentEvents();

				this._target.querySelector(`[data-id="${button.getAttribute('data-id')}"]`).remove();
				this.#setTreeHandlers();

				this.#updateWidgetFields();

				this.#activateContentEvents();
			},

			select: (e) => {
				const link = e.target;

				const itemid = link.closest('.tree-item').getAttribute('data-id');

				if (this.#markTreeItemSelected(itemid)) {
					this.#openBranch(this.#navtree_item_selected);

					this.#updateUserProfile();
					this.#broadcast();
				}

				e.preventDefault();
			}
		};
	}
}
