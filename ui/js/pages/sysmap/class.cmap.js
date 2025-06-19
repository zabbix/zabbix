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


class CMap {
	constructor(containerid, map_data) {
		this.selements = {};
		this.shapes = {};
		this.links = {};

		// Number of selected items.
		this.selection = {
			count: {
				selements: 0,
				shapes: 0
			},
			selements: {},
			shapes: {}
		};

		// Linkid of currently edited link.
		this.current_linkid = '0';

		this.items = map_data.sysmap.items;
		this.sysmapid = map_data.sysmap.sysmapid;
		this.data = map_data.sysmap;
		this.background = null;
		this.iconList = map_data.iconList;
		this.defaultAutoIconId = map_data.defaultAutoIconId;
		this.defaultIconId = map_data.defaultIconId;
		this.defaultIconName = map_data.defaultIconName;
		this.csrf_token = map_data.csrf_token;
		this.containerid = containerid;
		this.container = $(`#${containerid}`);

		if (this.container.length == 0) {
			this.container = $(document.body);
		}

		this.images = {};
		Object.values(this.iconList).forEach((item) => this.images[item.imageid] = item);

		this.map = new SVGMap({
			theme: map_data.theme,
			canvas: {
				width: this.data.width,
				height: this.data.height,
				mask: true
			},
			container: this.container[0]
		});

		this.container.css({
			width: `${this.data.width}px`,
			height: `${this.data.height}px`,
			overflow: 'hidden'
		});

		this.container.css('position', 'relative');
		this.base64image = true;
		$('#sysmap_img').remove();

		const processItems = (key, target, Class, field_id, sort = false) => {
			const items = Object.values(this.data[key]);

			if (sort) {
				items.sort((a, b) => a.zindex - b.zindex);
			}

			items.forEach((item) => target[item[field_id]] = new Class(this, item));
		};

		processItems('selements', this.selements, Selement, 'selementid', true);
		processItems('shapes', this.shapes, Shape, 'sysmap_shapeid', true);
		processItems('links', this.links, Link, 'linkid');

		// Create container for forms.
		this.form_container = $('<div>', {
				id: 'map-window',
				class: 'overlay-dialogue',
				style: 'display: none; top: 0; left: 0; padding-top: 13px;'
			})
			.appendTo('.wrapper')
			.draggable({
				containment: [0, 0, 3200, 3200]
			});

		this.updateImage();
		this.form = new SelementForm(this.form_container, this);
		this.massForm = new MassForm(this.form_container, this);
		this.linkForm = new LinkForm(this.form_container, this);
		this.shapeForm = new ShapeForm(this.form_container, this);
		this.massShapeForm = new MassShapeForm(this.form_container, this);

		this.#addMapActionsEventListeners();
		this.#addMapElementEventListeners();
		this.#addFormEventListeners();

		// Initialize selectable.
		this.container.selectable({
			start: (e) => {
				if (!e.ctrlKey && !e.metaKey) {
					this.#clearSelection();
				}
			},
			stop: (e) => {
				const selected = this.container[0].querySelectorAll('.ui-selected'),
					ids = [];

				selected.forEach((element) => {
					ids.push({
						id: element.dataset.id,
						type: element.dataset.type
					});

					// Remove ui-selected class, to not confuse next selection.
					element.classList.remove('ui-selected');
				});

				this.#selectElements(ids, e.ctrlKey || e.metaKey);
			}
		});

		this.copypaste_buffer = [];
		this.expand_sources = [];
		this.buffered_expand = false;

		/**
		 * Buffer for draggable elements and elements group bounds.
		 */
		this.draggable_buffer = null;
	}

	#save() {
		const url = new Curl();

		this.#correctSelementZIndexes();

		$.ajax({
			url: url.getPath() + '?output=ajax',
			type: 'post',
			data: {
				favobj: 'sysmap',
				action: 'update',
				[CSRF_TOKEN_NAME]: this.csrf_token,
				sysmapid: this.sysmapid,
				sysmap: JSON.stringify(this.data)
			},
			error: function() {
				throw new Error('Cannot update map.');
			}
		});
	}

	/**
	 * Automatically correct element z-index values. Useful if map was imported from older version where all
	 * z-index values are 0. Or map was added in API where "zindex" could be set to any numeric value like -3,
	 * for example. Firstly sort elements by "zindex" and secondly sort by "selementid". Since "selementid"
	 * can also be a new element "new0", "new1" etc., compare those as strings. The resulting
	 * this.data.selements will yield corrected "zindex" values for elements.
	 */
	#correctSelementZIndexes() {
		if (!this.data.selements) {
			return;
		}

		const selements = this.data.selements,
			keys = Object.keys(selements);

		// Sort by numeric "zindex", then by "selementid" (as string).
		keys.sort((a, b) => {
			const zA = Number(selements[a].zindex),
				zB = Number(selements[b].zindex);

			if (zA == zB) {
				// Compare "selementid" as strings in case two zindex values were identical.
				return selements[a].selementid.localeCompare(selements[b].selementid);
			}

			return zA - zB;
		});

		// Reassign sequential "zindex" values based on sorted order.
		keys.forEach((key, index) => selements[key].zindex = index.toString());
	}

	#setExpandedLabels(elements, labels) {
		elements.forEach((element, i) => element.expanded = labels ? labels[i] : null);

		this.updateImage();

		if (labels === null) {
			alert(t('S_MACRO_EXPAND_ERROR'));
		}
	}

	expandMacros(source) {
		const url = new Curl();

		if (source !== null) {
			if (/\{.+\}/.test(source.getLabel(false))) {
				this.expand_sources.push(source);
			}
			else {
				source.expanded = null;
			}
		}

		if (this.buffered_expand === false && this.expand_sources.length > 0) {
			const sources = this.expand_sources;

			this.expand_sources = [];

			const post = sources.map((source) => source.data);

			$.ajax({
				url: url.getPath() + '?output=ajax',
				type: 'post',
				dataType: 'html',
				data: {
					favobj: 'sysmap',
					action: 'expand',
					[CSRF_TOKEN_NAME]: this.csrf_token,
					sysmapid: this.sysmapid,
					name: this.data.name,
					source: JSON.stringify(post)
				},
				success: (data) => {
					try {
						data = JSON.parse(data);
					}
					catch (e) {
						data = null;
					}

					this.#setExpandedLabels(sources, data);
				},
				error: () => this.#setExpandedLabels(sources, null)
			});
		}
		else if (this.buffered_expand === false) {
			this.updateImage();
		}
	}

	updateImage() {
		const shapes = [],
			links = [],
			elements = [],
			grid_size = this.data.grid_show == SYSMAP_GRID_SHOW_ON ? parseInt(this.data.grid_size, 10) : 0;

		if (grid_size !== this.data.last_grid_size) {
			this.map.setGrid(grid_size);
			this.data.last_grid_size = grid_size;
		}

		Object.keys(this.selements).forEach((key) => {
			const element = {},
				data = this.selements[key].data;

			['selementid', 'x', 'y', 'label_location', 'zindex'].forEach((name) => element[name] = data[name]);

			element['label'] = this.selements[key].getLabel();

			if (data.elementtype == SVGMapElement.TYPE_HOST_GROUP
					&& data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS) {
				element.width = data.areatype == SVGMapElement.AREA_TYPE_FIT ? this.data.width : data.width;
				element.height = data.areatype == SVGMapElement.AREA_TYPE_FIT ? this.data.height : data.height;
			}

			if ((data.use_iconmap == SYSMAP_ELEMENT_USE_ICONMAP_ON && this.data.iconmapid != 0)
					&& (data.elementtype == SVGMapElement.TYPE_HOST
						|| (data.elementtype == SVGMapElement.TYPE_HOST_GROUP
								&& data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS))) {
				element.icon = this.defaultAutoIconId;
			}
			else {
				element.icon = data.iconid_off;
			}

			elements.push(element);
		});

		Object.keys(this.links).forEach((key) => {
			const link = {};

			['linkid', 'selementid1', 'selementid2', 'drawtype', 'color']
				.forEach((name) => link[name] = this.links[key].data[name]);

			link['label'] = this.links[key].getLabel();
			links.push(link);
		});

		Object.keys(this.shapes).forEach((key) => {
			const shape = {};

			Object.keys(this.shapes[key].data).forEach((name) => shape[name] = this.shapes[key].data[name]);
			shape['text'] = this.shapes[key].getLabel();
			shapes.push(shape);
		});

		this.map.update({
			background: this.data.backgroundid,
			background_scale: this.data.background_scale,
			elements,
			links,
			shapes,
			duplicated_links: [],
			label_location: this.data.label_location
		});
	}

	#deleteSelectedElements() {
		if (this.selection.count.selements && confirm(t('S_DELETE_SELECTED_ELEMENTS_Q'))) {
			Object.keys(this.selection.selements).forEach((id) => {
				this.selements[id].remove();
				this.#removeLinksBySelementId(id);
			});

			this.#toggleForm();
			this.updateImage();
		}
	}

	#deleteSelectedShapes() {
		if (this.selection.count.shapes && confirm(t('S_DELETE_SELECTED_SHAPES_Q'))) {
			Object.keys(this.selection.shapes).forEach((id) => this.shapes[id].remove());

			this.#toggleForm();
			this.updateImage();
		}
	}

	#removeLinksBySelementId(selementid) {
		const linkids = this.getLinksBySelementIds({[selementid]: selementid});

		linkids.forEach((id) => this.links[id].remove());
	}

	/**
	 * Returns the links between the given elements.
	 *
	 * @param {array} selementids  Map element IDs (key and value pairs).
	 *
	 * @return {array}
	 */
	getLinksBySelementIds(selementids) {
		const selements_count = Object.keys(selementids).length;

		return Object.entries(this.data.links)
			.filter(([linkid, link]) => {
				const has_both = selementids[link.selementid1] && selementids[link.selementid2],
					has_one = selements_count == 1
						&& (selementids[link.selementid1] || selementids[link.selementid2]);

				return has_both || has_one;
			})
			.map(([linkid]) => linkid);
	}

	/**
	 * Add map panel event listeners.
	 */
	#addMapActionsEventListeners() {
		// Add a map element.
		document.getElementById('selementAdd').addEventListener('click', () => {
			if (this.iconList[0] === undefined) {
				alert(t('S_NO_IMAGES'));

				return;
			}

			const selement = new Selement(this);

			this.selements[selement.id] = selement;
			this.updateImage();
		});

		// Remove single map element.
		document.getElementById('selementRemove')
			.addEventListener('click', this.#deleteSelectedElements.bind(this));

		// Add shape.
		document.getElementById('shapeAdd').addEventListener('click', () => {
			const shape = new Shape(this);

			this.shapes[shape.id] = shape;
			this.updateImage();
		});

		// Remove shapes.
		document.querySelectorAll('#shapeRemove, #shapesRemove, #shapeMassRemove').forEach((element) => {
			element.addEventListener('click', this.#deleteSelectedShapes.bind(this));
		});

		// Add link.
		document.getElementById('linkAdd').addEventListener('click', () => {
			if (this.selection.count.selements != 2) {
				alert(t('S_TWO_MAP_ELEMENTS_SHOULD_BE_SELECTED'));

				return false;
			}

			const link = new Link(this);

			this.links[link.id] = link;
			this.map.invalidate('selements');
			this.updateImage();
			this.linkForm.updateList(this.selection.selements);
		});

		// Removes all of the links between the selected elements.
		document.getElementById('linkRemove').addEventListener('click', () => {
			const linkids = this.getLinksBySelementIds(this.selection.selements);

			// This check must be removed in future releases as it is not necessary to limit the user.
			if (this.selection.count.selements != 2) {
				alert(t('S_PLEASE_SELECT_TWO_ELEMENTS'));

				return false;
			}

			if (linkids.length && confirm(t('S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'))) {
				for (let i = 0, ln = linkids.length; i < ln; i++) {
					this.links[linkids[i]].remove();
				}

				this.linkForm.hide();
				this.linkForm.updateList({});
				this.updateImage();
			}
		});

		// Toggle expand macros.
		document.getElementById('expand_macros').addEventListener('click', (e) => {
			this.data.expand_macros = this.data.expand_macros == SYSMAP_EXPAND_MACROS_ON
				? SYSMAP_EXPAND_MACROS_OFF
				: SYSMAP_EXPAND_MACROS_ON;
			e.currentTarget.innerHTML = this.data.expand_macros == SYSMAP_EXPAND_MACROS_ON
				? t('S_ON')
				: t('S_OFF');
			this.updateImage();
		});

		// Toggle grid visibility.
		document.getElementById('gridshow').addEventListener('click', (e) => {
			this.data.grid_show = this.data.grid_show == SYSMAP_GRID_SHOW_ON
				? SYSMAP_GRID_SHOW_OFF
				: SYSMAP_GRID_SHOW_ON;
			e.currentTarget.innerHTML = this.data.grid_show == SYSMAP_GRID_SHOW_ON
				? t('S_SHOWN')
				: t('S_HIDDEN');
			this.updateImage();
		});

		// Toggle auto align.
		document.getElementById('gridautoalign').addEventListener('click', (e) => {
			this.data.grid_align = this.data.grid_align == SYSMAP_GRID_ALIGN_ON
				? SYSMAP_GRID_ALIGN_OFF
				: SYSMAP_GRID_ALIGN_ON;
			e.currentTarget.innerHTML = this.data.grid_align == SYSMAP_GRID_ALIGN_ON ? t('S_ON') : t('S_OFF');
		});

		// Change grid size.
		document.getElementById('gridsize').addEventListener('change', (e) => {
			const value = e.currentTarget.value;

			if (this.data.grid_size != value) {
				this.data.grid_size = value;
				this.updateImage();
			}
		});

		// Perform "Align map elements" button press.
		document.getElementById('gridalignall').addEventListener('click', () => {
			for (const selementid in this.selements) {
				this.selements[selementid].align(true);
			}

			this.updateImage();
		});

		// Save map.
		document.getElementById('sysmap_update').addEventListener('click', () => this.#save());
	}

	/**
	 * Add map element event listeners. Creates even listeners for map element and shape clicks as well as
	 * context menu.
	 */
	#addMapElementEventListeners() {
		// Click map element or shape.
		this.container[0].addEventListener('click', (e) => {
			const element = e.target.closest('.sysmap_element, .sysmap_shape');

			if (!element || !this.container[0].contains(element)) {
				return;
			}

			this.#selectElements([{...element.dataset}], e.ctrlKey || e.metaKey);
		});

		// Open context menu for map element or shape.
		this.container[0].addEventListener('contextmenu', (e) => {
			const element = e.target,
				item_data = {...element.dataset},
				can_paste = this.copypaste_buffer.items && this.copypaste_buffer.items.length > 0;

			let can_copy = false,
				can_remove = false,
				can_reorder = false;

			if (item_data.id === undefined) {
				this.#clearSelection();
			}
			else if (item_data.type && this.selection[item_data.type][item_data.id] === undefined) {
				this.#selectElements([item_data], false, true);
			}

			can_copy = this.selection.count.shapes > 0 || this.selection.count.selements > 0;
			can_remove = can_copy;
			can_reorder = can_copy;

			e.preventDefault();
			e.stopPropagation();

			const overlay = overlays_stack.end();

			if (overlay !== undefined && 'element' in overlay && overlay.element !== element) {
				$('.menu-popup-top').menuPopup('close', null, false);
			}

			if (!(can_copy || can_paste || can_remove || can_reorder)) {
				return false;
			}

			/**
			 * Callback function to execute upon pressing one of menu entries "Bring to front", "Bring forward",
			 * "Send backward", "Send to back".
			 *
			 * @param {string} position  Where to position the selected shape or map element.
			 *                           Possible values: 'last', 'next', 'previous', 'first'.
			 */
			const reorderSelection = (position) => {
				['shapes', 'selements'].forEach((type) => {
					if (this.selection[type] && Object.keys(this.selection[type]).length > 0) {
						this.#reorderItems(type, this[type], this.selection[type], position);
					}
				});
			};

			const order_actions = [
				{label: t('S_BRING_TO_FRONT'), action: 'last'},
				{label: t('S_BRING_FORWARD'), action: 'next'},
				{label: t('S_SEND_BACKWARD'), action: 'previous'},
				{label: t('S_SEND_TO_BACK'), action: 'first'}
			].map(({label, action}) => ({
				label,
				disabled: !can_reorder,
				clickCallback: () => reorderSelection(action),
			}));

			/**
			 * Callback function to execute upon pressing one of menu entries "Paste" or
			 * "Paste without external links".
			 *
			 * @param {boolean} simple  True if paste is performed with links. False - links not included.
			 */
			const pasteSelection = (simple) => {
				const offset = $(this.container).offset();

				let delta_x = e.pageX - offset.left - this.copypaste_buffer.left,
					delta_y = e.pageY - offset.top - this.copypaste_buffer.top;

				delta_x = Math.min(delta_x, parseInt(this.data.width, 10) - this.copypaste_buffer.right);
				delta_y = Math.min(delta_y, parseInt(this.data.height, 10) - this.copypaste_buffer.bottom);

				const selectedids = this.#pasteSelectionBuffer(delta_x, delta_y, this, simple);

				this.#selectElements(selectedids, false);

				if (simple) {
					this.map.invalidate('selements');
				}

				this.updateImage();
				this.linkForm.updateList(this.selection.selements);
			};

			const copy_actions = [
				{
					label: t('S_COPY'),
					disabled: !can_copy,
					action: () => this.copypaste_buffer = this.getSelectionBuffer(this)
				},
				{
					label: t('S_PASTE'),
					disabled: !can_paste,
					action: () => pasteSelection(true)
				},
				{
					label: t('S_PASTE_SIMPLE'),
					disabled: !can_paste,
					action: () => pasteSelection(false)
				},
				{
					label: t('S_REMOVE'),
					disabled: !can_remove,
					action: () => {
						if (this.selection.count.selements || this.selection.count.shapes) {
							Object.keys(this.selection.selements).forEach((selementid) => {
								this.selements[selementid].remove();
								this.#removeLinksBySelementId(selementid);
							});

							Object.keys(this.selection.shapes).forEach((shapeid) => this.shapes[shapeid].remove());
						}

						this.#toggleForm();
						this.updateImage();
					}
				},
			].map(({label, disabled, action}) => ({label, disabled, clickCallback: action}));

			// Add both menu entry blocks to the context menu.
			const items = [order_actions, copy_actions].map((items) => ({items}));

			$(element).menuPopup(items, e, {
				position: {
					of: e,
					my: 'left top',
					at: 'left bottom',
					using: (pos, data) => {
						let max_left = data.horizontal === 'left'
							? document.getElementById(this.containerid).clientWidth
							: document.getElementById(this.containerid).clientWidth - data.element.width;

						pos.top = Math.max(0, pos.top);
						pos.left = Math.max(0, Math.min(max_left, pos.left));

						data.element.element[0].style.top = `${pos.top}px`;
						data.element.element[0].style.left = `${pos.left}px`;
					}
				},
				background_layer: false
			});
		});
	}

	/*
	 * Add map form event listeners.
	 */
	#addFormEventListeners() {
		document.getElementById('elementType').addEventListener('change', (e) => {
			const value = +e.currentTarget.value;

			switch (value) {
				case SVGMapElement.TYPE_HOST:
					$('#elementNameHost').multiSelect('clean');
					break;

				case SVGMapElement.TYPE_MAP:
					$('#elementNameMap').multiSelect('clean');
					break;

				case SVGMapElement.TYPE_TRIGGER:
					$('#elementNameTriggers').multiSelect('clean');
					break;

				case SVGMapElement.TYPE_HOST_GROUP:
					$('#elementNameHostGroup').multiSelect('clean');
					break;
			}

			document.querySelector('#triggerContainer tbody').innerHTML = '';
		});

		// Close any form via [X] or "Close" button.
		document.querySelectorAll('#map-window .btn-overlay-close, #elementClose, #shapeClose, #shapeMassClose,'
				+ ' #massClose').forEach((element) => {
			element.addEventListener('click', () => {
				this.#clearSelection();
				this.#toggleForm();
			});
		});

		// Remove map element via form "Remove" button.
		document.querySelectorAll('#elementRemove, #massRemove').forEach((element) => {
			element.addEventListener('click', this.#deleteSelectedElements.bind(this));
		});

		// Update map element via form "Apply" button.
		document.getElementById('elementApply').addEventListener('click', () => {
			if (this.selection.count.selements != 1) {
				throw 'Try to single update element, when more than one selected.';
			}

			const values = this.form.getValues();

			if (values) {
				for (const id in this.selection.selements) {
					this.selements[id].update(values, true);
				}
			}
		});

		// Update shape via form "Apply" button.
		document.getElementById('shapeApply').addEventListener('click', () => {
			if (this.selection.count.shapes != 1) {
				throw 'Trying to single update shape, when more than one selected.';
			}

			const values = this.shapeForm.getValues();

			if (values) {
				for (const id in this.selection.shapes) {
					this.shapes[id].update(values);
				}
			}
		});

		// All URL to map element.
		document.getElementById('newSelementUrl').addEventListener('click', () => this.form.addUrls());

		// Add chosen triggers to map trigger type element.
		document.getElementById('newSelementTriggers').addEventListener('click', () => this.form.addTriggers());

		// Add event listeners on numeric input fields "X", "Y" and area size "Width" and "Height".
		Array.from(this.form.domNode).forEach((container) => {
			if (container.nodeType == Node.ELEMENT_NODE) {
				// Set a default value of 0 for "X" and "Y" if value was invalid integer.
				container.querySelectorAll('#x, #y').forEach((element) => {
					element.addEventListener('change', (e) => {
						const value = parseInt(e.target.value, 10);

						e.target.value = isNaN(value) || value < 0 ? 0 : value;
					});
				});

				// Set a default value of 10 for area size "Width" and "Height" if value was invalid integer.
				container.querySelectorAll('#areaSizeWidth, #areaSizeHeight').forEach((element) => {
					element.addEventListener('change', (e) => {
						const value = parseInt(e.target.value, 10);

						e.target.value = isNaN(value) || value < 10 ? 10 : value;
					});
				});
			}
		});

		const sortable_triggers = new CSortable(document.querySelector('#triggerContainer tbody'),
			{selector_handle: 'div.drag-icon'}
		);

		sortable_triggers.on(CSortable.EVENT_SORT, SelementForm.recalculateTriggerSortOrder);

		// Init tag fields.
		$('#selement-tags')
			.dynamicRows({template: '#tag-row-tmpl', counter: 0, allow_empty: true})
			.on('beforeadd.dynamicRows', () => {
				let options = $('#selement-tags').data('dynamicRows');

				options.counter = ++options.counter;
			})
			.on('afteradd.dynamicRows', (e) => {
				const rows = e.target.querySelectorAll('.form_row');

				new CTagFilterItem(rows[rows.length - 1]);
			});

		/**
		 * Helper function to attach event listeners for "Apply" button in mass element and shape forms.
		 *
		 * @param {string} buttonid    HTML ID of the "Apply" button.
		 * @param {object} form        Form element.
		 * @param {object} ids         Selected element or shape IDs (key and value pairs).
		 * @param {object} collection  Elements or shapes collection to update.
		 */
		const attachMassApply = (buttonid, form, ids, collection) => {
			document.getElementById(buttonid).addEventListener('click', () => {
				const values = form.getValues();

				if (values) {
					this.buffered_expand = true;

					for (const id in ids) {
						collection[id].update(values);
					}

					this.buffered_expand = false;
					this.expandMacros(null);
				}
			});
		};

		attachMassApply('massApply', this.massForm, this.selection.selements, this.selements);
		attachMassApply('shapeMassApply', this.massShapeForm, this.selection.shapes, this.shapes);

		// Open link form.
		document.querySelectorAll('.element-links').forEach((container) => {
			container.addEventListener('click', (e) => {
				const openlink = e.target.closest('.openlink');

				if (openlink && container.contains(openlink)) {
					this.current_linkid = openlink.dataset.linkid;

					const link_data = this.links[this.current_linkid].getData();

					this.linkForm.setValues(link_data);
					this.linkForm.show();
				}
			});
		});

		// Remove link via link form.
		document.getElementById('formLinkRemove').addEventListener('click', () => {
			this.links[this.current_linkid].remove();
			this.linkForm.updateList(this.selection.selements);
			this.linkForm.hide();
			this.updateImage();
		});

		// Apply link changes via link form.
		document.getElementById('formLinkApply').addEventListener('click', () => {
			const map_window = document.getElementById('map-window');

			map_window.classList.add('is-loading', 'is-loading-fadein');

			this.linkForm
				.getValues()
				.then((values) => {
					this.map.invalidate('selements');
					this.links[this.current_linkid].update(values);
					this.linkForm.updateList(this.selection.selements);
				})
				.catch((exception) => {
					// Timeout for removing spinner.
					setTimeout(() => alert(exception), 50);
				})
				.finally(() => map_window.classList.remove('is-loading', 'is-loading-fadein'));
		});

		// Close link form.
		document.getElementById('formLinkClose').addEventListener('click', () => this.linkForm.hide());

		// Add event listeners on link indicator, threshold and highlight "Remove" buttons.
		Array.from(this.linkForm.domNode).forEach(container => {
			if (container.nodeType !== Node.ELEMENT_NODE) {
				return;
			}

			container.addEventListener('click', (e) => {
				const {index} = e.target.dataset,
					handleRemove = (selector, prefix) => {
						const element = e.target.closest(selector);

						if (element && container.contains(element)) {
							if (prefix === 'linktrigger') {
								const triggerid = document.getElementById(`${prefix}_${index}_triggerid`).value;

								document.getElementById(`${prefix}_${index}`).remove();
								delete this.linkForm.triggerids[triggerid];
							}
							else {
								document.getElementById(`${prefix}_${index}`).remove();
							}

							return true;
						}
					};

				handleRemove('.trigger-remove', 'linktrigger');
				handleRemove('.threshold-remove', 'threshold');
				handleRemove('.highlight-remove', 'highlight');
			});
		});

		// Add event listener on shape border type change in single shape form.
		document.getElementById('border_type').addEventListener('change', (e) => {
			const disable = e.target.value == SVGMapShape.BORDER_TYPE_NONE;

			document.getElementById('border_width').disabled = disable;
			document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="border_color"]`).disabled = disable;
		});

		// Add event listeners on border type and width in shape mass update form.
		document.querySelectorAll('#mass_border_type, #chkboxBorderType').forEach((element) =>
			element.addEventListener('change', () => {
				const mass_border_type = document.getElementById('mass_border_type'),
					chkbox_border_type = document.getElementById('chkboxBorderType'),
					chkbox_border_width = document.getElementById('chkboxBorderWidth'),
					chkbox_border_color = document.getElementById('chkboxBorderColor'),
					disable = mass_border_type.value == SVGMapShape.BORDER_TYPE_NONE
							&& chkbox_border_type.checked;

				chkbox_border_width.disabled = disable;
				chkbox_border_color.disabled = disable;
				document.getElementById('mass_border_width').disabled = disable || !chkbox_border_width.checked;
				document.querySelector(`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="mass_border_color"]`).disabled =
					disable || !chkbox_border_color.checked;
			})
		);

		// Add change event listener to each radio input in #shapeForm.
		document.querySelectorAll('#shapeForm input[type=radio][name=type]').forEach((radio) => {
			radio.addEventListener('change', (e) => {
				const value = parseInt(e.target.value, 10),
					last_shape_type = document.getElementById('last_shape_type'),
					last_value = parseInt(last_shape_type.value, 10);

				document.querySelectorAll('#shape-text-row, #shape-background-row').forEach((element) => {
					element.style.display = value !== SVGMapShape.TYPE_LINE ? '' : 'none';
				});

				document.querySelectorAll('.switchable-content').forEach((element) => {
					const data_attr = `data-value-${value}`;

					element.textContent = element.hasAttribute(data_attr)
						? element.getAttribute(data_attr)
						: element.dataset.value;
					});

					if ((last_value == SVGMapShape.TYPE_LINE) !== (value == SVGMapShape.TYPE_LINE)) {
						const shape_x = document.getElementById('shapeX'),
							shape_y = document.getElementById('shapeY'),
							shape_area_size_width = document.getElementById('shapeAreaSizeWidth'),
							shape_area_size_height = document.getElementById('shapeAreaSizeHeight'),
							x = parseInt(shape_x.value, 10),
							y = parseInt(shape_y.value, 10),
							width = parseInt(shape_area_size_width.value, 10),
							height = parseInt(shape_area_size_height.value, 10);

						if (value == SVGMapShape.TYPE_LINE) {
							shape_area_size_width.value = x + width;
							shape_area_size_height.value = y + height;
						}
						else {
							const mx = Math.min(x, width),
								my = Math.min(y, height);

							shape_x.value = mx;
							shape_y.value = my;
							shape_area_size_width.value = Math.max(x, width) - mx;
							shape_area_size_height.value = Math.max(y, height) - my;
						}
					}

					last_shape_type.value = value;
			});
		});

		// Close context menu popup on horizontal scroll.
		document.querySelector('.sysmap-scroll-container').addEventListener('scroll', (e) => {
			if (!e.target.dataset.last_scroll_at || Date.now() - e.target.dataset.last_scroll_at > 1000) {
				$('.menu-popup-top').menuPopup('close', null, false);

				e.target.dataset.last_scroll_at = Date.now();
			}
		});
	}

	/**
	 * Returns virtual DOM element used by draggable.
	 *
	 * @param {object} node
	 *
	 * @return {object}
	 */
	dragGroupPlaceholder(node) {
		return $('<div>').css({
			width: $(node).width(),
			height: $(node).height()
		});
	}

	/**
	 * Recalculate x and y position of moved elements.
	 *
	 * @param {object} cmap     Map object.
	 * @param {number} delta_x  Shift between old and new x position.
	 * @param {number} delta_y  Shift between old and new y position.
	 *
	 * @return {object}         Object of elements with recalculated positions.
	 */
	dragGroupRecalculate(cmap, delta_x, delta_y) {
		const dragged = cmap.draggable_buffer;

		dragged.items.forEach((item) => {
			const node = cmap[item.type][item.id];

			if ('updatePosition' in node) {
				const dimensions = node.getDimensions();

				node.updatePosition({
					x: dimensions.x + delta_x,
					y: dimensions.y + delta_y
				}, false);
			}
		});
	}

	/**
	 * Initializes multiple elements dragging.
	 *
	 * @param {object} draggable  Draggable DOM element where drag event was started.
	 */
	dragGroupInit(draggable) {
		let buffer;

		const dimensions = draggable.getDimensions();

		if (draggable.selected) {
			buffer = draggable.sysmap.getSelectionBuffer(draggable.sysmap);
		}
		else {
			const $draggable_node = $(draggable.domNode);

			// Create getSelectionBuffer structure if drag event was started on unselected element.
			buffer = {
				items: [{
					type: $draggable_node.attr('data-type'),
					id: draggable.id
				}],
				left: dimensions.x,
				right: dimensions.x + $draggable_node.width(),
				top: dimensions.y,
				bottom: dimensions.y + $draggable_node.height()
			};
		}

		buffer.xaxis = {
			min: dimensions.x - buffer.left,
			max: (draggable.sysmap.container).width() - (buffer.right - dimensions.x)
		};

		buffer.yaxis = {
			min: dimensions.y - buffer.top,
			max: (draggable.sysmap.container).height() - (buffer.bottom - dimensions.y)
		};

		draggable.sysmap.draggable_buffer = buffer;
	}

	/**
	 * Handler for drag event.
	 *
	 * @param {object} data       jQuery UI draggable data.
	 * @param {object} draggable  Element where drag event occurred.
	 */
	dragGroupDrag(data, draggable) {
		const cmap = draggable.sysmap,
			dimensions = draggable.getDimensions();

		let delta_x = data.position.left - dimensions.x,
			delta_y = data.position.top - dimensions.y;

		if (data.position.left < cmap.draggable_buffer.xaxis.min) {
			delta_x = dimensions.x < cmap.draggable_buffer.xaxis.min
				? 0
				: cmap.draggable_buffer.xaxis.min - dimensions.x;
		}
		else if (data.position.left > cmap.draggable_buffer.xaxis.max) {
			delta_x = dimensions.x > cmap.draggable_buffer.xaxis.max
				? 0
				: cmap.draggable_buffer.xaxis.max - dimensions.x;
		}

		if (data.position.top < cmap.draggable_buffer.yaxis.min) {
			delta_y = dimensions.y < cmap.draggable_buffer.yaxis.min
				? 0
				: cmap.draggable_buffer.yaxis.min - dimensions.y;
		}
		else if (data.position.top > cmap.draggable_buffer.yaxis.max) {
			delta_y = dimensions.y > cmap.draggable_buffer.yaxis.max
				? 0
				: cmap.draggable_buffer.yaxis.max - dimensions.y;
		}

		if (delta_x != 0 || delta_y != 0) {
			cmap.dragGroupRecalculate(cmap, Math.round(delta_x), Math.round(delta_y));
			cmap.updateImage();
		}
	}

	/**
	 * Final tasks for dragged element on drag stop event.
	 *
	 * @param {object} draggable  Element where drag stop event occurred.
	 */
	dragGroupStop(draggable) {
		const cmap = draggable.sysmap,
			should_align = cmap.data.grid_align == SYSMAP_GRID_ALIGN_ON;

		if (should_align) {
			cmap.draggable_buffer.items.forEach(function(item) {
				const node = cmap[item.type][item.id];

				if ('updatePosition' in node) {
					const dimensions = node.getDimensions();

					node.updatePosition({
						x: dimensions.x,
						y: dimensions.y
					});
				}
			});
		}
	}

	/**
	 * Paste that.copypaste_buffer content at new location.
	 *
	 * @param {number}  delta_x              Shift between desired and actual x position.
	 * @param {number}  delta_y              Shift between desired and actual y position.
	 * @param {object}  that                 CMap object.
	 * @param {boolean} keep_external_links  Should be links to non selected elements copied or not.
	 *
	 * @return {array}
	 */
	#pasteSelectionBuffer(delta_x, delta_y, that, keep_external_links) {
		const selectedids = [],
			source_cloneids = {};

		that.copypaste_buffer.items.forEach((element_data) => {
			const data = $.extend({}, element_data.data, false),
				type = element_data.type;

			let element;

			switch (type) {
				case 'selements':
					element = new Selement(that);
					delete data.selementid;
					break;

				case 'shapes':
					element = new Shape(that);
					delete data.sysmap_shapeid;
					break;

				default:
					throw 'Unsupported element type found in copy buffer!';
			}

			if (element) {
				data.x = parseInt(data.x, 10) + delta_x;
				data.y = parseInt(data.y, 10) + delta_y;
				element.expanded = element_data.expanded;

				if (type === 'shapes' && data.type == SVGMapShape.TYPE_LINE) {
					// Additional shift for line shape.
					data.width = parseInt(data.width, 10) + delta_x;
					data.height = parseInt(data.height, 10) + delta_y;
				}

				element.update(data);
				that[type][element.id] = element;
				selectedids.push({id: element.id, type});
				source_cloneids[element_data.id] = element.id;

				if (that.data.grid_align == SYSMAP_GRID_ALIGN_ON) {
					element.align(true);
				}
			}
		});

		that.copypaste_buffer.links.forEach((link_data) => {
			const data = $.extend({}, link_data.data, false);

			if (!keep_external_links && (data.selementid1 in source_cloneids === false
					|| data.selementid2 in source_cloneids === false)) {
				return;
			}

			const link = new Link(that);

			delete data.linkid;

			const fromid = (data.selementid1 in source_cloneids)
					? source_cloneids[data.selementid1]
					: data.selementid1,
				toid = (data.selementid2 in source_cloneids)
					? source_cloneids[data.selementid2]
					: data.selementid2;

			data.selementid1 = fromid;
			data.selementid2 = toid;
			link.update(data);
			that.links[link.id] = link;
		});

		return selectedids;
	}

	/**
	 * Return object with selected elements data and links.
	 *
	 * @param  {object}	that CMap object.
	 *
	 * @return {object}
	 */
	getSelectionBuffer(that) {
		const items = [];

		let left = null,
			top = null,
			right = null,
			bottom = null;

		for (const type in that.selection) {
			if (type in that === false || typeof that[type] !== 'object') {
				continue;
			}

			for (const id in that.selection[type]) {
				if ('getData' in that[type][id] === false) {
					continue;
				}

				// Get current data without observers.
				const data = $.extend({}, that[type][id].getData(), false),
					dimensions = that[type][id].getDimensions(),
					dom_node = that[type][id].domNode,
					x = dimensions.x,
					y = dimensions.y;

				left = Math.min(x, left === null ? x : left);
				top = Math.min(y, top === null ? y : top);
				right = Math.max(x + dom_node.outerWidth(true), right === null ? 0 : right);
				bottom = Math.max(y + dom_node.outerHeight(true), bottom === null ? 0 : bottom);

				items.push({id, type, data});
			}
		}

		// Sort items array according to item.data.zindex value.
		items.sort((a, b) => {
			const aindex = parseInt(a.data.zindex, 10) || 0,
				bindex = parseInt(b.data.zindex, 10) || 0;

			return aindex - bindex;
		});

		const links = [];

		for (const id in that.links) {
			// Get current data without observers.
			const data = $.extend({}, that.links[id].getData(), false);

			if (data.selementid1 in that.selection.selements || data.selementid2 in that.selection.selements) {
				links.push({id, data});
			}
		}

		return {items, links, top, left, right, bottom};
	}

	#clearSelection() {
		['selements', 'shapes'].forEach((type) => {
			for (const id in this.selection[type]) {
				this.selection.count[type]--;
				this[type][id].toggleSelect(false);
				delete this.selection[type][id];
			}
		});

		// Clean trigger selement.
		if (document.getElementById('elementType').value == SVGMapElement.TYPE_TRIGGER) {
			$('#elementNameTriggers').multiSelect('clean');
			document.querySelector('#triggerContainer tbody').innerHTML = '';
		}

		this.form.cleanTagsField();
	}

	/**
	 * Re-order elements and shapes.
	 *
	 * @param {string} type      Element type. Currently supported types 'shapes' and 'elements'.
	 * @param {object} items     Shapes or map elements with their attributes.
	 * @param {object} ids       IDs (key and value pairs) of shapes or map elements.
	 * @param {string} position  Where to position the selected shape or map element.
	 *                           Possible values: 'last', 'next', 'previous', 'first'.
	 */
	#reorderItems(type, items, ids, position) {
		const ignore = [],
			selection = [];

		let elements = [],
			target,
			temp;

		Object.keys(items).forEach((key) => elements.push(items[key]));
		elements = elements.sort((a, b) => a.data.zindex - b.data.zindex);
		elements.forEach((value, index) => {
			if (ids[value.id] !== undefined) {
				selection.push(index);
			}
		});

		// All shapes or elements are selected, no need to update order.
		if (elements.length == selection.length) {
			return;
		}

		switch (position) {
			case 'first':
				target = [];

				for (let i = selection.length - 1; i >= 0; i--) {
					target.unshift(elements.splice(selection[i], 1)[0]);
				}
				for (let i = 0; i < target.length; i++) {
					$(target[i].domNode).insertBefore(elements[0].domNode);
				}

				elements = target.concat(elements);
				elements.forEach((element, index) => element.data.zindex = index);
				break;

			case 'last':
				target = [];

				for (let i = selection.length - 1; i >= 0; i--) {
					target.unshift(elements.splice(selection[i], 1)[0]);
				}
				for (let i = target.length - 1; i >= 0 ; i--) {
					$(target[i].domNode).insertAfter(elements[elements.length-1].domNode);
				}

				elements = elements.concat(target);
				elements.forEach((element, index) => element.data.zindex = index);
				break;

			case 'next':
				ignore.push(elements.length - 1);

				for (let i = selection.length - 1; i >= 0; i--) {
					target = selection[i];

					// No need to update.
					if (ignore.indexOf(target) !== -1) {
						ignore.push(target - 1);
						continue;
					}

					$(elements[target].domNode).insertAfter(elements[target + 1].domNode);
					elements[target + 1].data.zindex--;
					elements[target].data.zindex++;

					temp = elements[target + 1];
					elements[target + 1] = elements[target];
					elements[target] = temp;
				}
				break;

			case 'previous':
				ignore.push(0);

				for (let i = 0; i < selection.length; i++) {
					target = selection[i];

					// No need to update.
					if (ignore.indexOf(target) !== -1) {
						ignore.push(target + 1);
						continue;
					}

					$(elements[target].domNode).insertBefore(elements[target - 1].domNode);
					elements[target - 1].data.zindex++;
					elements[target].data.zindex--;

					temp = elements[target - 1];
					elements[target - 1] = elements[target];
					elements[target] = temp;
				}
				break;
		}

		this.map.invalidate(type);
		this.updateImage();
	}

	#selectElements(ids, addSelection, prevent_form_open) {
		$('.menu-popup-top').menuPopup('close', null, false);

		if (!addSelection) {
			this.#clearSelection();
		}

		for (const {id, type} of ids) {
			if (id === undefined || type === undefined) {
				continue;
			}

			const selected = this[type][id].toggleSelect();

			this.selection.count[type] += selected ? 1 : -1;
			selected ? this.selection[type][id] = id : delete this.selection[type][id];
		}

		if (prevent_form_open === undefined || !prevent_form_open) {
			this.#toggleForm();
		}
	}

	#toggleForm() {
		this.shapeForm.hide();
		this.linkForm.hide();

		if (this.selection.count.selements + this.selection.count.shapes == 0
				|| (this.selection.count.selements > 0 && this.selection.count.shapes > 0)) {
			$('#map-window').hide();
		}
		else {
			this.linkForm.updateList(this.selection.selements);

			// Only one element selected.
			if (this.selection.count.selements == 1) {
				for (const id in this.selection.selements) {
					this.form.setValues(this.selements[id].getData());
				}

				this.massForm.hide();
				this.massShapeForm.hide();

				$('#link-connect-to').show();
				this.form.show();
			}
			// Only one shape is selected.
			else if (this.selection.count.shapes == 1) {
				this.form.hide();
				this.massForm.hide();
				this.massShapeForm.hide();

				for (const id in this.selection.shapes) {
					this.shapeForm.setValues(this.shapes[id].getData());
				}

				this.shapeForm.show();
			}
			// Multiple elements selected.
			else if (this.selection.count.selements > 1) {
				this.form.hide();
				this.massShapeForm.hide();
				$('#link-connect-to').hide();
				this.massForm.show();
			}
			// Multiple shapes selected.
			else {
				let figures = null;

				for (const id in this.selection.shapes) {
					if (figures === null) {
						figures = this.shapes[id].data.type != SVGMapShape.TYPE_LINE;
					}
					else if (figures !== (this.shapes[id].data.type != SVGMapShape.TYPE_LINE)) {
						// Different shape types are selected (lines and figures).
						$('#map-window').hide();
						this.massShapeForm.hide();

						return;
					}
				}

				this.form.hide();
				this.massForm.hide();
				$('#link-connect-to').hide();
				this.massShapeForm.show(figures);
			}
		}
	}

	/**
	 * Performs selement or shape form field updates after element has been moved.
	 *
	 * @param {object} element
	 */
	afterMove(element) {
		const {x, y, width, height} = element.data;

		if (element instanceof Shape) {
			if (this.selection.count.shapes == 1 && this.selection.count.selements == 0
					&& this.selection.shapes[element.id] !== undefined) {
				document.getElementById('shapeX').value = x;
				document.getElementById('shapeY').value = y;
				document.querySelector('#shapeForm input[name=width]').value = width;
				document.querySelector('#shapeForm input[name=height]').value = height;
			}

			this.updateImage();
		}

		if (element instanceof Selement) {
			if (this.selection.count.selements == 1 && this.selection.count.shapes == 0
					&& this.selection.selements[element.id] !== undefined) {
				document.getElementById('x').value = x;
				document.getElementById('y').value = y;

				if (width !== undefined) {
					document.getElementById('areaSizeWidth').value = width;
				}

				if (height !== undefined) {
					document.getElementById('areaSizeHeight').value = height;
				}
			}

			if (this.buffered_expand === false) {
				this.updateImage();
			}
		}
	}
}
