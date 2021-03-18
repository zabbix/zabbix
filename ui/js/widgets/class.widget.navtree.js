/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


const WIDGET_NAVTREE_EVENT_SELECT = 'select';

class CWidgetNavTree extends CWidget {

	_init() {
		super._init();

		this._severity_levels = null;
		this._navtree = [];
		this._navtree_items_opened = [];
		this._navtree_item_selected = null;
		this._maps_accessible = null;
		this._show_unavailable = false;
		this._problems = null;
		this._max_depth = 10;
		this._last_id = null;

		this._is_initial_load = false;

		this._has_contents = false;
	}

	_doActivate() {
		super._doActivate();

		if (this._has_contents) {
			this._registerContentEvents();
		}
	}

	_doDeactivate() {
		super._doDeactivate();

		if (this._has_contents) {
			this._unregisterContentEvents();
		}
	}

	setEditMode() {
		this._unregisterContentEvents();
		this._removeTree();

		super.setEditMode();

		this._makeTree();
		this._makeSortable();
		this._registerContentEvents();
	}

	updateProperties({name, view_mode, fields, configuration}) {
		this._updateWidgetFields();
		super.updateProperties({name, view_mode, fields, configuration});
	}

	_processUpdateResponse(response) {
		if (this._has_contents) {
			this._unregisterContentEvents();
			this._removeTree();

			this._has_contents = false;
		}

		super._processUpdateResponse(response);

		if (response.navtree_data !== undefined) {
			this._severity_levels = response.navtree_data.severity_levels;
			this._navtree = response.navtree_data.navtree;
			this._navtree_items_opened = response.navtree_data.navtree_items_opened;
			this._navtree_item_selected = response.navtree_data.navtree_item_selected;
			this._maps_accessible = response.navtree_data.maps_accessible;
			this._show_unavailable = response.navtree_data.show_unavailable;
			this._problems = response.navtree_data.problems;
			this._max_depth = response.navtree_data.max_depth;

			this._makeTree();

			if (this._is_edit_mode) {
				this._makeSortable();
			}
			else {
				this._parseProblems();

				if (this._navtree_item_selected === null
						|| !$(`.tree-item[data-id=${this._navtree_item_selected}]`).is(':visible')
				) {
					this._navtree_item_selected = $('.tree-item:visible', this._$target)
						.not('[data-sysmapid="0"]')
						.first()
						.data('id');
				}

				if (this._markTreeItemSelected(this._navtree_item_selected)) {
					this._openBranch(this._navtree_item_selected);

					this.fire(WIDGET_NAVTREE_EVENT_SELECT, {mapid: this._navtree_item_selected});
				}
			}

			if (this._state === WIDGET_STATE_ACTIVE) {
				this._registerContentEvents();
			}

			this._has_contents = false;
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			select: (e) => {
				const link = e.target;

				const itemid = link.closest('.tree-item').getAttribute('data-id');

				if (this._markTreeItemSelected(itemid)) {
					updateUserProfile('web.dashbrd.navtree.item.selected', itemid, [this._widgetid]);

					this.fire(WIDGET_NAVTREE_EVENT_SELECT, {mapid: itemid});
				}

				e.preventDefault();
			},

			addChild: (e) => {
				const button = e.target;

				this._unregisterContentEvents();

				const depth = parseInt(button.closest('.tree-list').getAttribute('data-depth'));
				const parent = button.getAttribute('data-id');

				if (depth <= this._max_depth) {
					this._itemEditDialog(0, parent, depth + 1, button);
				}

				this._registerContentEvents();
			},

			addMaps: (e) => {
				const button = e.target;

				this._unregisterContentEvents();

				const id = button.getAttribute('data-id');

				if (typeof window.addPopupValues === 'function') {
					window.old_addPopupValues = window.addPopupValues;
				}

				window.addPopupValues = (data) => {
					const root = this._target.querySelector(`.tree-item[data-id="${id}"] > ul.tree-list`);

					for (const item of data.values) {
						root.appendChild(this._makeTreeItem({
							id: this._getNextId(),
							name: item.name,
							sysmapid: item.sysmapid,
							parent: id
						}));
					}

					const tree_item = root.closest('.tree-item');

					tree_item.classList.remove('closed');
					tree_item.classList.add('opened');

					this._setTreeHandlers();

					if (typeof old_addPopupValues === 'function') {
						window.addPopupValues = old_addPopupValues;
						delete window.old_addPopupValues;
					}

					this._registerContentEvents();
				};

				return PopUp('popup.generic', {
					srctbl: 'sysmaps',
					srcfld1: 'sysmapid',
					srcfld2: 'name',
					multiselect: '1'
				}, null, e.target);
			},

			edit: (e) => {
				const button = e.target;

				const id = button.getAttribute('data-id');
				const parent = this._target.querySelector(`input[name="navtree.parent.${id}"]`).value;
				const depth = parseInt(button.closest('[data-depth]').getAttribute('data-depth'));

				this._itemEditDialog(id, parent, depth, button);
			},

			remove: (e) => {
				const button = e.target;

				this._unregisterContentEvents();

				this._removeTreeItem(button.getAttribute('data-id'));

				this._registerContentEvents();
			},

			copy: () => {
				this._updateWidgetFields();
			}
		}

		this.on(WIDGET_EVENT_COPY, this._events.copy);
	}

	_unregisterEvents() {
		super._unregisterEvents();

		this.off(WIDGET_EVENT_COPY, this._events.copy);
	}

	_registerContentEvents() {
		if (this._is_edit_mode) {
			for (const button of this._target.querySelectorAll('.js-button-add-child')) {
				button.addEventListener('click', this._events.addChild);
			}

			for (const button of this._target.querySelectorAll('.js-button-add-maps')) {
				button.addEventListener('click', this._events.addMaps);
			}

			for (const button of this._target.querySelectorAll('.js-button-edit')) {
				button.addEventListener('click', this._events.edit);
			}

			for (const button of this._target.querySelectorAll('.js-button-remove')) {
				button.addEventListener('click', this._events.remove);
			}
		}
		else {
			for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
				link.addEventListener('click', this._events.select);
			}
		}
	}

	_unregisterContentEvents() {
		if (this._is_edit_mode) {
			for (const button of this._target.querySelectorAll('.js-button-add-child')) {
				button.removeEventListener('click', this._events.addChild);
			}

			for (const button of this._target.querySelectorAll('.js-button-add-maps')) {
				button.removeEventListener('click', this._events.addMaps);
			}

			for (const button of this._target.querySelectorAll('.js-button-edit')) {
				button.removeEventListener('click', this._events.edit);
			}

			for (const button of this._target.querySelectorAll('.js-button-remove')) {
				button.removeEventListener('click', this._events.remove);
			}
		}
		else {
			for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
				link.removeEventListener('click', this._events.select);
			}
		}
	}

	_buildTree(parent_id = 0) {
		const tree = [];

		for (const i in this._navtree) {
			const tree_item = {
				id: i,
				name: this._navtree[i].name,
				order: this._navtree[i].order,
				parent: this._navtree[i].parent,
				sysmapid: this._navtree[i].sysmapid
			};

			if (tree_item.id > this._last_id) {
				this._last_id = tree_item.id;
			}

			if (tree_item.parent == parent_id) {
				const children = this._buildTree(tree_item.id);

				if (children.length > 0) {
					tree_item.children = children;
				}

				tree_item.item_active = (tree_item.sysmapid == 0 || this._maps_accessible.includes(tree_item.sysmapid));
				tree_item.item_visible = (this._show_unavailable || tree_item.item_active);

				tree.push(tree_item);
			}
		}

		tree.sort((a, b) => {
			return a.order - b.order;
		});

		return tree;
	};

	_makeTree() {
		const tree = this._buildTree();

		let root = this._makeTreeBranch();
		root.classList.add('root');

		this._target.querySelector('.tree').appendChild(root);

		if (this._is_edit_mode) {
			const root_item = this._makeTreeItem({name: t('root'), id: 0}, 0, false);

			root.appendChild(root_item);

			if (tree.length > 0) {
				if (root_item.classList.contains('closed')) {
					root_item.classList.add('opened');
					root_item.classList.remove('closed');
				}
			}

			root = document.getElementById(`${this._unique_id}_children-of-0`);
		}

		for (const item of tree) {
			root.appendChild(this._makeTreeItem(item));
		}

		this._setTreeHandlers();
	};

	_makeTreeBranch(parent_id = null) {
		const ul = document.createElement('UL');

		if (parent_id !== null) {
			ul.setAttribute('id', `${this._unique_id}_children-of-${parent_id}`);
		}

		ul.classList.add('tree-list');

		return ul;
	};

	_makeTreeItem(item, depth = 1, editable = true) {
		const li_item = document.createElement('LI');

		li_item.classList.add('tree-item');

		if (!editable || this._navtree_items_opened.includes(item.id)) {
			li_item.classList.add('opened');
		}
		else {
			li_item.classList.add('closed');
		}

		if (!editable) {
			li_item.classList.add('root-item');
		}

		if (this._is_edit_mode && item.sysmapid == 0) {
			li_item.classList.add('no-map');
		}

		const ul = this._makeTreeBranch(item.id);
		if (item.children !== undefined && this._max_depth > depth) {
			let child_items_visible = 0;

			for (const child of item.children) {
				if (typeof child === 'object') {
					ul.appendChild(this._makeTreeItem(child, depth + 1));

					if (child.id > this._last_id) {
						this._last_id = child.id;
					}

					if (child.item_visible === true) {
						child_items_visible++;
					}
				}
			}

			if (item.children.length > 0 && child_items_visible > 0) {
				li_item.classList.add('is-parent');
			}
		}

		if (!this._is_edit_mode && item.sysmapid != 0 && !item.item_active) {
			li_item.classList.add('inaccessible');
		}

		let link;

		if (!this._is_edit_mode && item.sysmapid != 0 && item.item_active) {
			link = document.createElement('A');

			link.setAttribute('data-sysmapid', item.sysmapid);
			link.setAttribute('href', '#');
		}
		else {
			link = document.createElement('SPAN');
		}

		link.classList.add('item-name');
		link.setAttribute('title', item.name);
		link.innerText = item.name;

		li_item.setAttribute('data-id', item.id);
		li_item.setAttribute('id', `${this._unique_id}_tree-item-${item.id}`);

		if (item.sysmapid != 0) {
			li_item.setAttribute('data-sysmapid', item.sysmapid);
		}

		if (item.item_visible === false) {
			li_item.style.display = 'none';
		}

		const tree_row = document.createElement('DIV');

		tree_row.classList.add('tree-row');
		li_item.appendChild(tree_row);

		let tools;
		let problems;

		if (this._is_edit_mode) {
			tools = document.createElement('DIV');
			tools.classList.add('tools');
			tree_row.appendChild(tools);
		}
		else {
			problems = document.createElement('DIV');
			problems.classList.add('problem-icon-list');
			tree_row.appendChild(problems);
		}

		const content = document.createElement('DIV');

		content.classList.add('content');
		tree_row.appendChild(content);

		const margin_lvl = document.createElement('DIV');

		margin_lvl.classList.add('margin-lvl');
		content.appendChild(margin_lvl);

		if (this._is_edit_mode) {
			const button_add_child = document.createElement('INPUT');

			button_add_child.classList.add('add-child-btn', 'js-button-add-child');
			button_add_child.setAttribute('type', 'button');
			button_add_child.setAttribute('data-id', item.id);
			button_add_child.setAttribute('title', t('Add child element'));
			tools.appendChild(button_add_child);

			const button_add_maps = document.createElement('INPUT');

			button_add_maps.classList.add('import-items-btn', 'js-button-add-maps');
			button_add_maps.setAttribute('type', 'button');
			button_add_maps.setAttribute('data-id', item.id);
			button_add_maps.setAttribute('title', t('Add multiple maps'));
			tools.appendChild(button_add_maps);

			if (editable) {
				const button_edit = document.createElement('INPUT');

				button_edit.classList.add('edit-item-btn', 'js-button-edit');
				button_edit.setAttribute('type', 'button');
				button_edit.setAttribute('data-id', item.id);
				button_edit.setAttribute('title', t('Edit'));
				tools.appendChild(button_edit);

				const button_remove = document.createElement('BUTTON');

				button_remove.classList.add('remove-btn', 'js-button-remove');
				button_remove.setAttribute('type', 'button');
				button_remove.setAttribute('data-id', item.id);
				button_remove.setAttribute('title', t('Remove'));
				tools.appendChild(button_remove);
			}
		}

		if (this._is_edit_mode && editable) {
			const drag = document.createElement('DIV');

			drag.classList.add('drag-icon');
			content.appendChild(drag);
		}

		const arrow = document.createElement('DIV');

		arrow.classList.add('arrow');
		content.appendChild(arrow);

		if (editable) {
			const arrow_btn = document.createElement('BUTTON');
			const arrow_span = document.createElement('SPAN');

			arrow_btn.setAttribute('type', 'button');
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

				if (this._widgetid !== null) {
					updateUserProfile(`web.dashbrd.navtree-${branch.getAttribute('data-id')}.toggle`, closed_state,
						[this._widgetid]
					);

					const index = this._navtree_items_opened.indexOf(branch.getAttribute('data-id'));

					if (index > -1) {
						if (closed_state === '1') {
							this._navtree_items_opened.splice(index, 1);
						}
						else {
							this._navtree_items_opened.push(branch.getAttribute('data-id'));
						}
					}
					else if (index === -1 && closed_state === '0') {
						this._navtree_items_opened.push(branch.getAttribute('data-id'));
					}
				}
			});
		}

		content.appendChild(link);
		li_item.appendChild(ul);

		if (this._is_edit_mode && editable) {
			const name_fld = document.createElement('INPUT');
			name_fld.setAttribute('type', 'hidden');
			name_fld.setAttribute('name', 'navtree.name.' + item.id);
			name_fld.setAttribute('id', `${this._unique_id}_navtree.name.${item.id}`);
			name_fld.value = item.name;
			li_item.appendChild(name_fld);

			const parent_fld = document.createElement('INPUT');
			parent_fld.setAttribute('type', 'hidden');
			parent_fld.setAttribute('name', 'navtree.parent.' + item.id);
			parent_fld.setAttribute('id', `${this._unique_id}_navtree.parent.${item.id}`);
			parent_fld.value = +item.parent || 0;
			li_item.appendChild(parent_fld);

			const mapid_fld = document.createElement('INPUT');
			mapid_fld.setAttribute('type', 'hidden');
			mapid_fld.setAttribute('name', 'navtree.sysmapid.' + item.id);
			mapid_fld.setAttribute('id', `${this._unique_id}_navtree.sysmapid.${item.id}`);
			mapid_fld.value = item.sysmapid;
			li_item.appendChild(mapid_fld);
		}

		return li_item;
	};

	_removeTree() {
		const root = this._target.querySelector('.root');

		if (root !== null) {
			root.remove();
		}
	}

	_removeTreeItem(id) {
		this._target.querySelector(`[data-id="${id}"]`).remove();
		this._setTreeHandlers();
	};

	_setTreeHandlers() {
		// Add .is-parent class for branches with sub-items.
		$('.tree-list', this._$target).not('.ui-sortable, .root').each(function() {
			if ($('>li', $(this)).not('.inaccessible').length) {
				$(this).closest('.tree-item').addClass('is-parent');
			}
			else {
				$(this).closest('.tree-item').removeClass('is-parent');
			}
		});

		// Set [data-depth] for list and each sublist.
		$('.tree-list', this._$target).each(function() {
			$(this).attr('data-depth', $(this).parents('.tree-list').length);
		}).not('.root').promise().done(function() {
			// Show/hide 'add new items' buttons.
			$('.tree-list', this._$target).filter(function() {
				return $(this).attr('data-depth') >= this._max_depth;
			}).each(function() {
				$('.js-button-add-maps', $(this)).css('visibility', 'hidden');
				$('.js-button-add-child', $(this)).css('visibility', 'hidden');
			});

			// Show/hide buttons in deepest levels.
			$('.tree-list', this._$target).filter(function() {
				return this._max_depth > $(this).attr('data-depth');
			}).each(function() {
				$('> .tree-item > .tree-row > .tools > .js-button-add-maps', $(this)).css('visibility', 'visible');
				$('> .tree-item > .tree-row > .tools > .js-button-add-child', $(this)).css('visibility', 'visible');
			});
		});

		// Change arrow style.
		$('.is-parent', this._$target).each(function() {
			const $arrow = $('> .tree-row > .content > .arrow > .treeview > span', $(this));

			if ($(this).hasClass('opened')) {
				$arrow.removeClass('arrow-right').addClass('arrow-down');
			}
			else {
				$arrow.removeClass('arrow-down a1').addClass('arrow-right');
			}
		});
	};

	_markTreeItemSelected(itemid) {
		const selected_item = document.getElementById(`${this._unique_id}_tree-item-${itemid}`);

		if (selected_item === null) {
			return false;
		}

		let step_in_path = selected_item.closest('.tree-item');

		this._target.querySelectorAll('.selected').forEach((selected) => {
			selected.classList.remove('selected');
		});

		while (step_in_path !== null) {
			step_in_path.classList.add('selected');
			step_in_path = step_in_path.parentNode.closest('.tree-item');
		}

		return true;
	};

	_openBranch(itemid) {
		if (!$(`.tree-item[data-id=${itemid}]`).is(':visible')) {
			const selector = '> .tree-row > .content > .arrow > .treeview > span';

			let branch_to_open = $(`.tree-item[data-id=${itemid}]`).closest('.tree-list').not('.root');

			while (branch_to_open.length) {
				branch_to_open.closest('.tree-item.is-parent')
					.removeClass('closed')
					.addClass('opened');

				$(selector, branch_to_open.closest('.tree-item.is-parent'))
					.removeClass('arrow-right')
					.addClass('arrow-down');

				branch_to_open = branch_to_open.closest('.tree-item.is-parent')
					.closest('.tree-list').not('.root');
			}
		}
	};

	_getNextId() {
		this._last_id++;

		while ($(`[name="navtree.name.${this._last_id}"]`).length) {
			this._last_id++;
		}

		return this._last_id;
	}

	_makeSortable() {
		$('.root-item > .tree-list', this._$target)
			.sortable_tree({
				max_depth: this._max_depth,
				stop: () => {
					this._setTreeHandlers();
				}
			})
			.disableSelection();
	};

	_parseProblems() {
		if (this._severity_levels === null) {
			return false;
		}

		const empty_template = {};

		for (const [severity, value] in Object.entries(this._severity_levels)) {
			empty_template[severity] = 0;
		}

		for (const [itemid, problems] of Object.entries(this._problems)) {
			for (const [severity, value] of Object.entries(problems || empty_template)) {
				if (value) {
					this._target.querySelectorAll(`.tree-item[data-id="${itemid}"]`).forEach((item) => {
						item.setAttribute(`data-problems${severity}`, value);
					})
				}
			}
		}

		for (const [severity, value] of Object.entries(this._severity_levels)) {
			for (const problem of this._target.querySelectorAll(`[data-problems${severity}]`)) {
				const indicator = document.createElement('SPAN');

				indicator.classList.add('problem-icon-list-item', value.style_class);
				indicator.setAttribute('title', value.name);
				indicator.innerText = problem.getAttribute(`data-problems${severity}`);

				problem.querySelector('.tree-row > .problem-icon-list')
					.appendChild(indicator)
			}
		}
	};

	_itemEditDialog(id, parent, depth, trigger_elmnt) {
		const url = new Curl('zabbix.php');
		const item_edit = id != 0;

		url.setArgument('action', 'widget.navtree.item.edit');

		jQuery.ajax({
			url: url.getUrl(),
			method: 'POST',
			data: {
				name: item_edit ? this._target.querySelector(`[name="navtree.name.${id}"]`).value : '',
				sysmapid: item_edit ? this._target.querySelector(`[name="navtree.sysmapid.${id}"]`).value : 0,
				depth: depth
			},
			dataType: 'json',
			success: (resp) => {
				if (resp.debug !== undefined) {
					resp.body += resp.debug;
				}

				overlayDialogue({
					'title': t('Edit tree element'),
					'content': resp.body,
					'buttons': [
						{
							'title': item_edit ? t('Apply') : t('Add'),
							'class': 'dialogue-widget-save',
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
										sysmapid: form_inputs.sysmapid.value,
										add_submaps: form_inputs.add_submaps.checked ? 1 : 0,
										depth: depth
									},
									dataType: 'json',
									complete: () => {
										overlay.unsetLoading();
									},
									success: (resp) => {
										let new_item;

										form.querySelectorAll('.msg-bad').forEach((msg) => {
											msg.remove();
										})

										if (typeof resp.errors === 'object' && resp.errors.length > 0) {
											form.insertAdjacentHTML('afterbegin', resp.errors);

											return false;
										}
										else {
											if (item_edit) {
												const $row = $(`[data-id="${id}"]`, this._$target);

												$(`[name="navtree.name.${id}"]`, $row).val(resp.name);
												$(`[name="navtree.sysmapid.${id}"]`, $row)
													.val(resp['sysmapid']);
												$('> .tree-row > .content > .item-name', $row)
													.empty()
													.attr('title', resp['name'])
													.append($('<span/>').text(resp.name));
												$row.toggleClass('no-map', resp.sysmapid == 0);
											}
											else {
												const root = this._target
													.querySelector(`.tree-item[data-id="${parent}"]>ul.tree-list`);

												id = this._getNextId(),
													new_item = {
														id: id,
														name: resp['name'],
														sysmapid: resp['sysmapid'],
														parent: parent
													};

												root.append(this._makeTreeItem(new_item));

												root.closest('.tree-item').classList.remove('closed');
												root.closest('.tree-item').classList.add('opened', 'is-parent');
											}

											const add_child_level = (sysmapid, itemid, depth) => {
												if (typeof resp.hierarchy[sysmapid] !== 'undefined'
													&& depth <= this._max_depth) {
													const root = this._target
														.querySelector(`.tree-item[data-id="${itemid}"]>ul.tree-list`);

													$.each(resp.hierarchy[sysmapid], (i, submapid) => {
														if (typeof resp.submaps[submapid] !== 'undefined') {
															const submap_item = resp.submaps[submapid];
															const submap_itemid = this._getNextId();

															new_item = {
																id: submap_itemid,
																name: submap_item['name'],
																sysmapid: submap_item['sysmapid'],
																parent: itemid
															};

															root.append(this._makeTreeItem(new_item));
															add_child_level(submapid, submap_itemid, depth + 1);
														}
													});

													root.closest('.tree-item').classList.remove('closed');
													root.closest('.tree-item').classList.add('opened', 'is-parent');
												}
											};

											add_child_level(resp['sysmapid'], id, depth + 1);

											overlayDialogueDestroy(overlay.dialogueid);
											this._setTreeHandlers();
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
					'dialogueid': 'navtreeitem'
				}, trigger_elmnt);
			}
		});
	};

	_updateWidgetFields() {
		const prefix = `${widget.getUniqueId()}_`;

		if (!this._is_edit_mode) {
			return false;
		}

		for (const name in this._fields) {
			if (/^navtree\.(name|order|parent|sysmapid)\.\d+$/.test(name)) {
				delete this._fields[name]
			}
		}

		$('input[name^="navtree.name."]', this._$content_body).each((index, field) => {
			const id = field.getAttribute('name').substr(13);

			if (id) {
				const parent = document.getElementById(`${prefix}navtree.parent.${id}`).value;
				const sysmapid = document.getElementById(`${prefix}navtree.sysmapid.${id}`).value;
				const sibling = document.getElementById(`${prefix}children-of-${parent}`).childNodes;

				let order = 0;

				while (sibling[order] !== undefined && sibling[order].getAttribute('data-id') != id) {
					order++;
				}

				this._fields[`navtree.name.${id}`] = field.value;

				if (parent != 0) {
					this._fields[`navtree.parent.${id}`] = parent;
				}

				if (order != 0) {
					this._fields[`navtree.order.${id}`] = order + 1;
				}

				if (sysmapid != 0) {
					this._fields[`navtree.sysmapid.${id}`] = sysmapid;
				}
			}
		});
	};
}
