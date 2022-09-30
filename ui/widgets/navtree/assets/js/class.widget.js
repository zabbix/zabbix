/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


const WIDGET_NAVTREE_EVENT_MARK = 'widget-navtree-mark';
const WIDGET_NAVTREE_EVENT_SELECT = 'widget-navtree-select';

class CWidgetNavTree extends CWidget {

	_init() {
		super._init();

		this._severity_levels = null;
		this._navtree = [];
		this._maps = [];
		this._navtree_items_opened = [];
		this._navtree_item_selected = null;
		this._maps_accessible = null;
		this._show_unavailable = false;
		this._problems = null;
		this._max_depth = 10;
		this._last_id = null;

		this._has_contents = false;
	}

	_doActivate() {
		super._doActivate();

		if (this._has_contents) {
			if (this._target.querySelector('.root') === null) {
				this._makeTree();
				this._activateTree();
			}

			this._activateContentsEvents();
		}
	}

	_doDeactivate() {
		super._doDeactivate();

		this._deactivateContentsEvents();
	}

	announceWidgets(widgets) {
		super.announceWidgets(widgets);
		this._deactivateContentsEvents();

		this._maps = [];

		for (const widget of widgets) {
			if (widget instanceof CWidgetMap && this._fields.reference === widget._fields.filter_widget_reference) {
				this._maps.push(widget);
			}
		}

		if (this._has_contents) {
			this._activateContentsEvents();
		}
	}

	getDataCopy({is_single_copy}) {
		this._deactivateContentsEvents();
		this._setTreeHandlers();
		this._updateWidgetFields();
		this._activateContentsEvents();

		return super.getDataCopy({is_single_copy});
	}

	setEditMode() {
		if (this._has_contents) {
			this._deactivateContentsEvents();
			this._removeTree();
		}

		super.setEditMode();

		if (this._has_contents && this._state === WIDGET_STATE_ACTIVE) {
			this._makeTree();
			this._activateTree();
			this._activateContentsEvents();
		}
	}

	_processUpdateResponse(response) {
		if (this._has_contents) {
			this._deactivateContentsEvents();
			this._removeTree();

			this._has_contents = false;
		}

		super._processUpdateResponse(response);

		if (response.navtree_data !== undefined) {
			this._has_contents = true;

			this._severity_levels = response.navtree_data.severity_levels;
			this._navtree = response.navtree_data.navtree;
			this._navtree_items_opened = response.navtree_data.navtree_items_opened;
			this._navtree_item_selected = response.navtree_data.navtree_item_selected;
			this._maps_accessible = response.navtree_data.maps_accessible;
			this._show_unavailable = response.navtree_data.show_unavailable;
			this._problems = response.navtree_data.problems;
			this._max_depth = response.navtree_data.max_depth;

			this._makeTree();

			this._activateTree();
			this._activateContentsEvents();
		}
	}

	_registerEvents() {
		super._registerEvents();

		this._events = {
			...this._events,

			addChild: (e) => {
				const button = e.target;

				const depth = parseInt(button.closest('.tree-list').getAttribute('data-depth'));
				const parent = button.getAttribute('data-id');

				if (depth <= this._max_depth) {
					this._itemEditDialog(0, parent, depth + 1, button);
				}
			},

			addMaps: (e) => {
				const button = e.target;

				const id = button.getAttribute('data-id');

				if (typeof window.addPopupValues === 'function') {
					window.old_addPopupValues = window.addPopupValues;
				}

				window.addPopupValues = (data) => {
					this._deactivateContentsEvents();

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
					this._updateWidgetFields();

					if (typeof old_addPopupValues === 'function') {
						window.addPopupValues = old_addPopupValues;
						delete window.old_addPopupValues;
					}

					this._activateContentsEvents();
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
				const parent = this._target.querySelector(`input[name="navtree.parent.${id}"]`).value;
				const depth = parseInt(button.closest('[data-depth]').getAttribute('data-depth'));

				this._itemEditDialog(id, parent, depth, button);
			},

			removeItem: (e) => {
				const button = e.target;

				this._deactivateContentsEvents();

				this._target.querySelector(`[data-id="${button.getAttribute('data-id')}"]`).remove();
				this._setTreeHandlers();

				this._updateWidgetFields();

				this._activateContentsEvents();
			},

			select: (e) => {
				const link = e.target;

				const itemid = link.closest('.tree-item').getAttribute('data-id');

				if (this._markTreeItemSelected(itemid)) {
					this._openBranch(this._navtree_item_selected);

					updateUserProfile('web.dashboard.widget.navtree.item.selected', this._navtree_item_selected,
						[this._widgetid]
					);

					this.fire(WIDGET_NAVTREE_EVENT_SELECT, {
						sysmapid: this._navtree[this._navtree_item_selected].sysmapid,
						itemid: this._navtree_item_selected
					});
				}

				e.preventDefault();
			},

			selectSubmap: (e) => {
				if (e.detail.back) {
					if (e.detail.parent_itemid !== null) {
						this._markTreeItemSelected(e.detail.parent_itemid);
					}
				}
				else {
					for (let [itemid, item] of Object.entries(this._navtree)) {
						if (item.sysmapid != e.detail.sysmapid || item.parent != e.detail.parent_itemid) {
							continue;
						}

						if (this._markTreeItemSelected(itemid)) {
							this._openBranch(this._navtree_item_selected);
						}
					}
				}
			}
		}
	}

	_activateTree() {
		if (this._is_edit_mode) {
			this._makeSortable();
		}
		else {
			this._parseProblems();

			if (this._navtree_item_selected === null
				|| !jQuery(`.tree-item[data-id=${this._navtree_item_selected}]`).is(':visible')
			) {
				this._navtree_item_selected = jQuery('.tree-item:visible', jQuery(this._target))
					.not('[data-sysmapid="0"]')
					.first()
					.data('id');
			}

			let sysmapid = 0;

			if (this._markTreeItemSelected(this._navtree_item_selected)) {
				sysmapid = this._navtree[this._navtree_item_selected].sysmapid;
			}

			this.fire(WIDGET_NAVTREE_EVENT_SELECT, {sysmapid, itemid: this._navtree_item_selected});
		}
	}

	_activateContentsEvents() {
		if (this._state === WIDGET_STATE_ACTIVE && this._has_contents) {
			if (this._is_edit_mode) {
				for (const button of this._target.querySelectorAll('.js-button-add-child')) {
					button.addEventListener('click', this._events.addChild);
				}

				for (const button of this._target.querySelectorAll('.js-button-add-maps')) {
					button.addEventListener('click', this._events.addMaps);
				}

				for (const button of this._target.querySelectorAll('.js-button-edit')) {
					button.addEventListener('click', this._events.editItem);
				}

				for (const button of this._target.querySelectorAll('.js-button-remove')) {
					button.addEventListener('click', this._events.removeItem);
				}
			}
			else {
				for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
					link.addEventListener('click', this._events.select);
				}
			}
		}

		if (!this._is_edit_mode) {
			for (const widget of this._maps) {
				widget.on(WIDGET_SYSMAP_EVENT_SUBMAP_SELECT, this._events.selectSubmap);
			}
		}
	}

	_deactivateContentsEvents() {
		if (this._has_contents) {
			if (this._is_edit_mode) {
				for (const button of this._target.querySelectorAll('.js-button-add-child')) {
					button.removeEventListener('click', this._events.addChild);
				}

				for (const button of this._target.querySelectorAll('.js-button-add-maps')) {
					button.removeEventListener('click', this._events.addMaps);
				}

				for (const button of this._target.querySelectorAll('.js-button-edit')) {
					button.removeEventListener('click', this._events.editItem);
				}

				for (const button of this._target.querySelectorAll('.js-button-remove')) {
					button.removeEventListener('click', this._events.removeItem);
				}
			}
			else {
				for (const link of this._target.querySelectorAll('a[data-sysmapid]')) {
					link.removeEventListener('click', this._events.select);
				}
			}
		}

		if (!this._is_edit_mode) {
			for (const widget of this._maps) {
				widget.off(WIDGET_SYSMAP_EVENT_SUBMAP_SELECT, this._events.selectSubmap);
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
	}

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
	}

	_makeTreeBranch(parent_id = null) {
		const ul = document.createElement('ul');

		if (parent_id !== null) {
			ul.id = `${this._unique_id}_children-of-${parent_id}`;
		}

		ul.classList.add('tree-list');

		return ul;
	}

	_makeTreeItem(item, depth = 1, editable = true) {
		const li_item = document.createElement('li');

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
				if (typeof child !== 'object') {
					continue;
				}

				ul.appendChild(this._makeTreeItem(child, depth + 1));

				if (child.id > this._last_id) {
					this._last_id = child.id;
				}

				if (child.item_visible === true) {
					child_items_visible++;
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

		li_item.id = `${this._unique_id}_tree-item-${item.id}`;
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

		let tools;
		let problems;

		if (this._is_edit_mode) {
			tools = document.createElement('div');
			tools.classList.add('tools');
			tree_row.appendChild(tools);
		}
		else {
			problems = document.createElement('div');
			problems.classList.add('problem-icon-list');
			tree_row.appendChild(problems);
		}

		const content = document.createElement('div');

		content.classList.add('content');
		tree_row.appendChild(content);

		const margin_lvl = document.createElement('div');

		margin_lvl.classList.add('margin-lvl');
		content.appendChild(margin_lvl);

		if (this._is_edit_mode) {
			const button_add_child = document.createElement('input');

			button_add_child.type = 'button';
			button_add_child.title = t('Add child element');
			button_add_child.classList.add('btn-add', 'js-button-add-child');
			button_add_child.setAttribute('data-id', item.id);
			tools.appendChild(button_add_child);

			const button_add_maps = document.createElement('input');

			button_add_maps.type = 'button';
			button_add_maps.title = t('Add multiple maps');
			button_add_maps.classList.add('btn-import', 'js-button-add-maps');
			button_add_maps.setAttribute('data-id', item.id);
			tools.appendChild(button_add_maps);

			if (editable) {
				const button_edit = document.createElement('input');

				button_edit.type = 'button';
				button_edit.title = t('Edit');
				button_edit.classList.add('btn-edit', 'js-button-edit');
				button_edit.setAttribute('data-id', item.id);
				tools.appendChild(button_edit);

				const button_remove = document.createElement('button');

				button_remove.type = 'button';
				button_remove.title = t('Remove');
				button_remove.classList.add('btn-remove', 'js-button-remove');
				button_remove.setAttribute('data-id', item.id);
				tools.appendChild(button_remove);
			}
		}

		if (this._is_edit_mode && editable) {
			const drag = document.createElement('div');

			drag.classList.add('drag-icon');
			content.appendChild(drag);
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

				if (this._widgetid !== null) {
					updateUserProfile(`web.dashboard.widget.navtree.item-${branch.getAttribute('data-id')}.toggle`,
						closed_state, [this._widgetid]
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
			const name_fld = document.createElement('input');
			name_fld.id = `${this._unique_id}_navtree.name.${item.id}`;
			name_fld.type = 'hidden';
			name_fld.name = `navtree.name.${item.id}`;
			name_fld.value = item.name;
			li_item.appendChild(name_fld);

			const parent_fld = document.createElement('input');
			parent_fld.id = `${this._unique_id}_navtree.parent.${item.id}`;
			parent_fld.type = 'hidden';
			parent_fld.name = `navtree.parent.${item.id}`;
			parent_fld.value = item.parent || 0;
			li_item.appendChild(parent_fld);

			const mapid_fld = document.createElement('input');
			mapid_fld.id = `${this._unique_id}_navtree.sysmapid.${item.id}`;
			mapid_fld.type = 'hidden';
			mapid_fld.name = `navtree.sysmapid.${item.id}`;
			mapid_fld.value = item.sysmapid;
			li_item.appendChild(mapid_fld);
		}

		return li_item;
	}

	_removeTree() {
		const root = this._target.querySelector('.root');

		if (root !== null) {
			root.remove();
		}
	}

	_setTreeHandlers() {
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
		}).not('.root').promise().done(function() {
			// Show/hide 'add new items' buttons.
			jQuery('.tree-list', jQuery(this._target)).filter(function() {
				return jQuery(this).attr('data-depth') >= this._max_depth;
			}).each(function() {
				jQuery('.js-button-add-maps', jQuery(this)).css('visibility', 'hidden');
				jQuery('.js-button-add-child', jQuery(this)).css('visibility', 'hidden');
			});

			// Show/hide buttons in deepest levels.
			jQuery('.tree-list', jQuery(this._target)).filter(function() {
				return this._max_depth > jQuery(this).attr('data-depth');
			}).each(function() {
				jQuery('> .tree-item > .tree-row > .tools > .js-button-add-maps', jQuery(this)).css('visibility', 'visible');
				jQuery('> .tree-item > .tree-row > .tools > .js-button-add-child', jQuery(this)).css('visibility', 'visible');
			});
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
	}

	_markTreeItemSelected(itemid) {
		const selected_item = document.getElementById(`${this._unique_id}_tree-item-${itemid}`);

		const item = this._navtree[itemid];

		if (item === undefined || selected_item === null || item === this._navtree_item_selected) {
			return false;
		}

		this._navtree_item_selected = itemid;

		let step_in_path = selected_item.closest('.tree-item');

		this._target.querySelectorAll('.selected').forEach((selected) => {
			selected.classList.remove('selected');
		});

		while (step_in_path !== null) {
			step_in_path.classList.add('selected');
			step_in_path = step_in_path.parentNode.closest('.tree-item');
		}

		updateUserProfile('web.dashboard.widget.navtree.item.selected', this._navtree_item_selected, [this._widgetid]);

		this.fire(WIDGET_NAVTREE_EVENT_MARK, {itemid: this._navtree_item_selected});

		return true;
	}

	_openBranch(itemid) {
		if (!jQuery(`.tree-item[data-id=${itemid}]`).is(':visible')) {
			const selector = '> .tree-row > .content > .arrow > .treeview > span';

			let branch_to_open = jQuery(`.tree-item[data-id=${itemid}]`).closest('.tree-list').not('.root');

			while (branch_to_open.length) {
				branch_to_open.closest('.tree-item.is-parent')
					.removeClass('closed')
					.addClass('opened');

				jQuery(selector, branch_to_open.closest('.tree-item.is-parent'))
					.removeClass('arrow-right')
					.addClass('arrow-down');

				branch_to_open = branch_to_open.closest('.tree-item.is-parent')
					.closest('.tree-list').not('.root');
			}
		}
	}

	_getNextId() {
		this._last_id++;

		while (jQuery(`[name="navtree.name.${this._last_id}"]`).length) {
			this._last_id++;
		}

		return this._last_id;
	}

	_makeSortable() {
		jQuery('.root-item > .tree-list', jQuery(this._target))
			.sortable_tree({
				max_depth: this._max_depth,
				stop: () => {
					this._setTreeHandlers();
					this._updateWidgetFields();
				}
			})
			.disableSelection();
	}

	_parseProblems() {
		if (this._severity_levels === null) {
			return false;
		}

		const empty_template = {};

		for (const [severity, _] in Object.entries(this._severity_levels)) {
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
				const indicator = document.createElement('span');

				indicator.title = value.name;
				indicator.classList.add('problem-icon-list-item', value.style_class);
				indicator.innerText = problem.getAttribute(`data-problems${severity}`);

				problem.querySelector('.tree-row > .problem-icon-list')
					.appendChild(indicator)
			}
		}
	}

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
				if ('error' in resp) {
					clearMessages();

					const message_box = makeMessageBox('bad', resp.error.messages, resp.error.title);

					addMessage(message_box);

					return;
				}

				if (resp.debug !== undefined) {
					resp.body += resp.debug;
				}

				overlayDialogue({
					'title': t('Edit tree element'),
					'class': 'modal-popup',
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
										form.querySelectorAll('.msg-bad').forEach((msg) => {
											msg.remove();
										})

										if ('error' in resp) {
											const message_box = makeMessageBox('bad', resp.error.messages,
												resp.error.title
											)[0];

											form.insertAdjacentElement('afterbegin', message_box);

											return false;
										}
										else {
											this._deactivateContentsEvents();
											if (item_edit) {
												const $row = jQuery(`[data-id="${id}"]`, jQuery(this._target));

												jQuery(`[name="navtree.name.${id}"]`, $row).val(resp.name);
												jQuery(`[name="navtree.sysmapid.${id}"]`, $row)
													.val(resp['sysmapid']);
												jQuery('> .tree-row > .content > .item-name', $row)
													.empty()
													.attr('title', resp['name'])
													.append(jQuery('<span>').text(resp.name));
												$row.toggleClass('no-map', resp.sysmapid == 0);
											}
											else {
												const root = this._target
													.querySelector(`.tree-item[data-id="${parent}"]>ul.tree-list`);

												id = this._getNextId();

												root.append(this._makeTreeItem({
													id: id,
													name: resp['name'],
													sysmapid: resp['sysmapid'],
													parent: parent
												}));

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

															root.append(this._makeTreeItem({
																id: submap_itemid,
																name: submap_item['name'],
																sysmapid: submap_item['sysmapid'],
																parent: itemid
															}));
															add_child_level(submapid, submap_itemid, depth + 1);
														}
													});

													root.closest('.tree-item').classList.remove('closed');
													root.closest('.tree-item').classList.add('opened', 'is-parent');
												}
											};

											add_child_level(resp['sysmapid'], id, depth + 1);

											overlayDialogueDestroy(overlay.dialogueid);
											this._updateWidgetFields();
											this._setTreeHandlers();
											this._activateContentsEvents();
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
					'script_inline': resp.script_inline
				}, trigger_elmnt);
			}
		});
	}

	_updateWidgetFields() {
		const prefix = `${this.getUniqueId()}_`;

		if (!this._is_edit_mode) {
			return false;
		}

		for (const name in this._fields) {
			if (/^navtree\.(name|order|parent|sysmapid)\.\d+$/.test(name)) {
				delete this._fields[name]
			}
		}

		jQuery('input[name^="navtree.name."]', jQuery(this._content_body)).each((index, field) => {
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
	}
}
