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


ZABBIX.namespace('classes.Observer');

ZABBIX.classes.Observer = (function() {
	var Observer = function() {
		this.listeners = {};
	};

	Observer.prototype = {
		constructor: ZABBIX.classes.Observer,

		bind: function(e, callback) {
			if (typeof callback === 'function') {
				e = ('' + e).toLowerCase().split(/\s+/);

				for (let i = 0; i < e.length; i++) {
					if (this.listeners[e[i]] === void(0)) {
						this.listeners[e[i]] = [];
					}

					this.listeners[e[i]].push(callback);
				}
			}

			return this;
		},

		trigger: function(e, target) {
			e = e.toLowerCase();

			const handlers = this.listeners[e] || [];

			if (handlers.length) {
				e = jQuery.Event(e);

				for (let i = 0; i < handlers.length; i++) {
					try {
						if (handlers[i](e, target) === false || e.isDefaultPrevented()) {
							break;
						}
					}
					catch(ex) {
						window.console && window.console.log && window.console.log(ex);
					}
				}
			}

			return this;
		}
	};

	Observer.makeObserver = function(object) {
		for (const key in Observer.prototype) {
			if (Observer.prototype.hasOwnProperty(key) && typeof Observer.prototype[key] === 'function') {
				object[key] = Observer.prototype[key];
			}
		}

		object.listeners = {};
	};

	return Observer;
}());

ZABBIX.namespace('apps.map');

ZABBIX.apps.map = (function($) {
	// dependencies
	var Observer = ZABBIX.classes.Observer;

	function createMap(containerid, mapData) {
		var CMap = function(containerid, mapData) {
			this.selements = {}; // element objects
			this.shapes = {}; // shape objects
			this.links = {}; // map links array
			this.selection = {
				count: { // number of selected items
					selements: 0,
					shapes: 0
				},
				selements: {}, // selected elements
				shapes: {} // selected shapes
			};
			this.current_linkid = '0'; // linkid of currently edited link
			this.allLinkTriggerIds = {};
			this.sysmapid = mapData.sysmap.sysmapid;
			this.data = mapData.sysmap;
			this.background = null;
			this.iconList = mapData.iconList;
			this.defaultAutoIconId = mapData.defaultAutoIconId;
			this.defaultIconId = mapData.defaultIconId;
			this.defaultIconName = mapData.defaultIconName;
			this.csrf_token = mapData.csrf_token;
			this.container = $(`#${containerid}`);

			if (this.container.length == 0) {
				this.container = $(document.body);
			}

			this.images = {};
			Object.values(this.iconList).forEach((item) => this.images[item.imageid] = item);

			this.map = new SVGMap({
				theme: mapData.theme,
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
			this.formContainer = $('<div>', {
					id: 'map-window',
					class: 'overlay-dialogue',
					style: 'display: none; top: 0; left: 0; padding-top: 13px;'
				})
				.appendTo('.wrapper')
				.draggable({
					containment: [0, 0, 3200, 3200]
				});

			this.updateImage();
			this.form = new SelementForm(this.formContainer, this);
			this.massForm = new MassForm(this.formContainer, this);
			this.linkForm = new LinkForm(this.formContainer, this);
			this.shapeForm = new ShapeForm(this.formContainer, this);
			this.massShapeForm = new MassShapeForm(this.formContainer, this);

			this.addMapActionsEventListeners();
			this.addMapElementEventListeners();
			this.addFormEventListeners();

			// Initialize selectable.
			this.container.selectable({
				start: (e) => {
					if (!e.ctrlKey && !e.metaKey) {
						this.clearSelection();
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

					this.selectElements(ids, e.ctrlKey || e.metaKey);
				}
			});
		};

		CMap.prototype = {
			copypaste_buffer: [],
			buffered_expand: false,
			expand_sources: [],

			save: function() {
				const url = new Curl();

				this.correctSelementZIndexes();

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
			},

			/**
			 * Automatically correct element z-index values. Useful if map was imported from older version where all
			 * z-index values are 0. Or map was added in API where "zindex" could be set to any numeric value like -3,
			 * for example. Firstly sort elements by "zindex" and secondly sort by "selementid". Since "selementid"
			 * can also be a new element "new0", "new1" etc., compare those as strings. The resulting
			 * this.data.selements will yield corrected "zindex" values for elements.
			 */
			correctSelementZIndexes: function() {
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
			},

			setExpandedLabels: function (elements, labels) {
				elements.forEach((element, i) => element.expanded = labels ? labels[i] : null);

				this.updateImage();

				if (labels === null) {
					alert(t('S_MACRO_EXPAND_ERROR'));
				}
			},

			expandMacros: function(source) {
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

							this.setExpandedLabels(sources, data);
						},
						error: () => this.setExpandedLabels(sources, null)
					});
				}
				else if (this.buffered_expand === false) {
					this.updateImage();
				}
			},

			updateImage: function() {
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
					elements,
					links,
					shapes,
					label_location: this.data.label_location
				});
			},

			deleteSelectedElements: function() {
				if (this.selection.count.selements && confirm(t('S_DELETE_SELECTED_ELEMENTS_Q'))) {
					Object.keys(this.selection.selements).forEach((id) => {
						this.selements[id].remove();
						this.removeLinksBySelementId(id);
					});

					this.toggleForm();
					this.updateImage();
				}
			},

			deleteSelectedShapes: function() {
				if (this.selection.count.shapes && confirm(t('S_DELETE_SELECTED_SHAPES_Q'))) {
					Object.keys(this.selection.shapes).forEach((id) => this.shapes[id].remove());

					this.toggleForm();
					this.updateImage();
				}
			},

			removeLinksBySelementId: function(selementid) {
				const linkids = this.getLinksBySelementIds({[selementid]: selementid});

				linkids.forEach((id) => this.links[id].remove());
			},

			/**
			 * Returns the links between the given elements.
			 *
			 * @param {array} selementids  Map element IDs (key and value pairs).
			 *
			 * @return {array}
			 */
			getLinksBySelementIds: function(selementids) {
				const selements_count = Object.keys(selementids).length;

				return Object.entries(this.data.links)
					.filter(([linkid, link]) => {
						const has_both = selementids[link.selementid1] && selementids[link.selementid2],
							has_one = selements_count == 1
								&& (selementids[link.selementid1] || selementids[link.selementid2]);

						return has_both || has_one;
					})
					.map(([linkid]) => linkid);
			},

			/**
			 * Add map panel event listeners.
			 */
			addMapActionsEventListeners: function() {
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
					.addEventListener('click', this.deleteSelectedElements.bind(this));

				// Add shape.
				document.getElementById('shapeAdd').addEventListener('click', () => {
					const shape = new Shape(this);

					this.shapes[shape.id] = shape;
					this.updateImage();
				});

				// Remove shapes.
				document.querySelectorAll('#shapeRemove, #shapesRemove, #shapeMassRemove').forEach((element) => {
					element.addEventListener('click', this.deleteSelectedShapes.bind(this));
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
				document.getElementById('sysmap_update').addEventListener('click', () => this.save());
			},

			/**
			 * Add map element event listeners. Creates even listeners for map element and shape clicks as well as
			 * context menu.
			 */
			addMapElementEventListeners: function() {
				// Click map element or shape.
				this.container[0].addEventListener('click', (e) => {
					const element = e.target.closest('.sysmap_element, .sysmap_shape');

					if (!element || !this.container[0].contains(element)) {
						return;
					}

					this.selectElements([{...element.dataset}], e.ctrlKey || e.metaKey);
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
						this.clearSelection();
					}
					else if (item_data.type && this.selection[item_data.type][item_data.id] === undefined) {
						this.selectElements([item_data], false, true);
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
								this.reorderItems(type, this[type], this.selection[type], position);
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

						const selectedids = this.pasteSelectionBuffer(delta_x, delta_y, this, simple);

						this.selectElements(selectedids, false);

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
									Object.keys(this.selection.selements).forEach(selementid => {
										this.selements[selementid].remove();
										this.removeLinksBySelementId(selementid);
									});

									Object.keys(this.selection.shapes).forEach(shapeid => {
										this.shapes[shapeid].remove();
									});
								}

								this.toggleForm();
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
									? document.getElementById(containerid).clientWidth
									: document.getElementById(containerid).clientWidth - data.element.width;

								pos.top = Math.max(0, pos.top);
								pos.left = Math.max(0, Math.min(max_left, pos.left));

								data.element.element[0].style.top = `${pos.top}px`;
								data.element.element[0].style.left = `${pos.left}px`;
							}
						},
						background_layer: false
					});
				});
			},

			/*
			 * Add map form event listeners.
			 */
			addFormEventListeners: function() {
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
						this.clearSelection();
						this.toggleForm();
					});
				});

				// Remove map element via form "Remove" button.
				document.querySelectorAll('#elementRemove, #massRemove').forEach((element) => {
					element.addEventListener('click', this.deleteSelectedElements.bind(this));
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

				sortable_triggers.on(CSortable.EVENT_SORT, SelementForm.prototype.recalculateTriggerSortOrder);

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
					try {
						const link_data = this.linkForm.getValues();

						this.map.invalidate('selements');
						this.links[this.current_linkid].update(link_data);
						this.linkForm.updateList(this.selection.selements);
					}
					catch (err) {
						alert(err);
					}
				});

				// Close link form.
				document.getElementById('formLinkClose').addEventListener('click', () => this.linkForm.hide());

				// Add event listener on link indicator "Remove" button.
				Array.from(this.linkForm.domNode).forEach((container) => {
					if (container.nodeType == Node.ELEMENT_NODE) {
						container.addEventListener('click', (e) => {
							const element = e.target.closest('.triggerRemove');

							if (element && container.contains(element)) {
								const linktriggerid = e.target.dataset.linktriggerid,
									triggerid = document.getElementById(`linktrigger_${linktriggerid}_triggerid`).value;

								document.getElementById(`linktrigger_${linktriggerid}`).remove();

								for (const _triggerid in this.linkForm.triggerids) {
									if (_triggerid === triggerid) {
										delete this.linkForm.triggerids[_triggerid];
									}
								}
							}
						});
					}
				});

				// Add event listener on shape border type change in single shape form.
				document.getElementById('border_type').addEventListener('change', (e) => {
					const disable = e.target.value == SVGMapShape.BORDER_TYPE_NONE;

					document.getElementById('border_width').disabled = disable;
					document.getElementById('border_color').disabled = disable;
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
						document.getElementById('mass_border_color').disabled = disable || !chkbox_border_color.checked;
					})
				);

				// Add change event listener to each radio input in #shapeForm.
				document.querySelectorAll('#shapeForm input[type=radio][name=type]').forEach((radio) => {
					radio.addEventListener('change', (e) => {
						const value = parseInt(e.target.value, 10),
							last_shape_type = document.querySelector('#shapeForm #last_shape_type'),
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

							if ((last_value == SVGMapShape.TYPE_LINE) != (value == SVGMapShape.TYPE_LINE)) {
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
			},

			/**
			 * Buffer for draggable elements and elements group bounds.
			 */
			draggable_buffer: null,

			/**
			 * Returns virtual DOM element used by draggable.
			 *
			 * @return {object}
			 */
			dragGroupPlaceholder: function() {
				return $('<div>').css({
					width: $(this.domNode).width(),
					height: $(this.domNode).height()
				});
			},

			/**
			 * Recalculate x and y position of moved elements.
			 *
			 * @param {object} cmap     Map object.
			 * @param {number} delta_x  Shift between old and new x position.
			 * @param {number} delta_y  Shift between old and new y position.
			 *
			 * @return {object}         Object of elements with recalculated positions.
			 */
			dragGroupRecalculate: function(cmap, delta_x, delta_y) {
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
			},

			/**
			 * Initializes multiple elements dragging.
			 *
			 * @param {object} draggable  Draggable DOM element where drag event was started.
			 */
			dragGroupInit: function(draggable) {
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
			},

			/**
			 * Handler for drag event.
			 *
			 * @param {object} data       jQuery UI draggable data.
			 * @param {object} draggable  Element where drag event occurred.
			 */
			dragGroupDrag: function(data, draggable) {
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
			},

			/**
			 * Final tasks for dragged element on drag stop event.
			 *
			 * @param {object} draggable  Element where drag stop event occurred.
			 */
			dragGroupStop: function(draggable) {
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
			},

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
			pasteSelectionBuffer: function(delta_x, delta_y, that, keep_external_links) {
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
			},

			/**
			 * Return object with selected elements data and links.
			 *
			 * @param  {object}	that CMap object.
			 *
			 * @return {object}
			 */
			getSelectionBuffer: function(that) {
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
							x = dimensions.x;
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
			},

			clearSelection: function() {
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
			},

			/**
			 * Re-order elements and shapes.
			 *
			 * @param {string} type      Element type. Currently supported types 'shapes' and 'elements'.
			 * @param {object} items     Shapes or map elements with their attributes.
			 * @param {object} ids       IDs (key and value pairs) of shapes or map elements.
			 * @param {string} position  Where to position the selected shape or map element.
			 *                           Possible values: 'last', 'next', 'previous', 'first'.
			 */
			reorderItems: function(type, items, ids, position) {
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
			},

			selectElements: function(ids, addSelection, prevent_form_open) {
				$('.menu-popup-top').menuPopup('close', null, false);

				if (!addSelection) {
					this.clearSelection();
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
					this.toggleForm();
				}
			},

			toggleForm: function() {
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
		};

		/**
		 * Creates a new Link.
		 *
		 * @class represents connector between two Elements
		 *
		 * @property {object} sysmap  Reference to Map object.
		 * @property {object} data    Link db values.
		 * @property {string} id      Link ID (linkid).
		 *
		 * @param {object} sysmap     Map object.
		 * @param {object} linkData   Link data from DB.
		 */
		function Link(sysmap, linkData) {
			this.sysmap = sysmap;

			if (!linkData) {
				linkData = {
					label: '',
					selementid1: null,
					selementid2: null,
					linktriggers: {},
					drawtype: 0,
					color: '00CC00'
				};

				for (const selementid in this.sysmap.selection.selements) {
					if (linkData.selementid1 === null) {
						linkData.selementid1 = selementid;
					}
					else {
						linkData.selementid2 = selementid;
					}
				}

				// Generate unique linkid.
				linkData.linkid =  getUniqueId();
			}
			else {
				if ($.isArray(linkData.linktriggers)) {
					linkData.linktriggers = {};
				}
			}

			this.data = linkData;
			this.id = this.data.linkid;
			this.expanded = this.data.expanded;
			delete this.data.expanded;

			for (const linktrigger in this.data.linktriggers) {
				this.sysmap.allLinkTriggerIds[linktrigger.triggerid] = true;
			}

			// Assign by reference.
			this.sysmap.data.links[this.id] = this.data;
		}

		Link.prototype = {
			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean}
			 *
			 * @return {string} Label with expanded macros.
			 */
			getLabel: function (expand) {
				let label = this.data.label;

				if (expand === undefined) {
					expand = true;
				}

				if (expand && typeof(this.expanded) === 'string'
						&& this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON) {
					label = this.expanded;
				}

				return label;
			},

			/**
			 * Updates values in property data.
			 *
			 * @param {object} data
			 */
			update: function(data) {
				const invalidate = this.data.label !== data.label;

				Object.assign(this.data, data);

				sysmap[invalidate ? 'expandMacros' : 'updateImage'](this);
			},

			/**
			 * Removes Link object and delete all references to it.
			 */
			remove: function() {
				delete this.sysmap.data.links[this.id];
				delete this.sysmap.links[this.id];

				if (sysmap.form.active) {
					sysmap.linkForm.updateList(sysmap.selection.selements);
				}

				sysmap.linkForm.hide();
			},

			/**
			 * Gets Link data.
			 *
			 * @return {object}
			 */
			getData: function() {
				return this.data;
			}
		};

		Observer.makeObserver(Link.prototype);

		/**
		 * Creates a new Shape.
		 *
		 * @class represents shape (static) element
		 *
		 * @property {object} sysmap     Reference to Map object
		 * @property {object} data       Shape values from DB.
		 * @property {string} id         Shape ID (shapeid).
		 *
		 * @param {object} sysmap        Map object
		 * @param {object} [shape_data]  shape data from db
		 */
		function Shape(sysmap, shape_data) {
			const default_data = {
				type: SVGMapShape.TYPE_RECTANGLE,
				x: 10,
				y: 10,
				width: 50,
				height: 50,
				border_color: '000000',
				background_color: '',
				border_width: 2,
				font: 9, // Helvetica
				font_size: 11,
				font_color: '000000',
				text_valign: 0,
				text_halign: 0,
				text: '',
				border_type: SVGMapShape.BORDER_TYPE_SOLID
			};

			this.sysmap = sysmap;

			if (!shape_data) {
				shape_data = default_data;

				// Generate unique sysmap_shapeid.
				shape_data.sysmap_shapeid = getUniqueId();
				shape_data.zindex = Object.keys(sysmap.shapes).length;
			}
			else {
				Object.keys(default_data).forEach((field) => {
					if (shape_data[field] === undefined) {
						shape_data[field] = default_data[field];
					}
				});
			}

			this.data = shape_data;
			this.id = this.data.sysmap_shapeid;
			this.expanded = this.data.expanded;
			delete this.data.expanded;

			// Assign by reference.
			this.sysmap.data.shapes[this.id] = this.data;

			// Create dom.
			this.domNode = $('<div>', {
					style: 'position: absolute; z-index: 1; background: url("data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7") 0 0 repeat',
				})
				.appendTo(this.sysmap.container)
				.addClass('cursor-pointer sysmap_shape')
				.attr('data-id', this.id)
				.attr('data-type', 'shapes');

			this.makeDraggable(true);
			this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

			const dimensions = this.getDimensions();

			Object.assign(this.domNode[0].style, {
				top: `${dimensions.y}px`,
				left: `${dimensions.x}px`,
				width: `${dimensions.width}px`,
				height: `${dimensions.height}px`
			});
		}

		Shape.prototype = {
			/**
			 * Updates values in property data.
			 *
			 * @param {object} data
			 */
			update: function(data) {
				const invalidate = (data.type != SVGMapShape.TYPE_LINE && data.text !== undefined
							&& this.data.text !== data.text);

				if (data.type !== undefined && /^[0-9]+$/.test(this.data.sysmap_shapeid) === true
						&& (data.type == SVGMapShape.TYPE_LINE) != (this.data.type == SVGMapShape.TYPE_LINE)) {
					delete data.sysmap_shapeid;
					this.data.sysmap_shapeid = getUniqueId();
				}

				Object.assign(this.data, data);

				['x', 'y', 'width', 'height'].forEach((name) => this.data[name] = parseInt(this.data[name], 10));

				const dimensions = this.getDimensions();

				Object.assign(this.domNode[0].style, {
					width: `${dimensions.width}px`,
					height: `${dimensions.height}px`
				});

				this.makeDraggable(true);
				this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

				this.align(false);
				this.trigger('afterMove', this);

				Object.assign(this.data, data);

				sysmap[invalidate ? 'expandMacros' : 'updateImage'](this);
			},

			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean} return label with expanded macros.
			 *
			 * @return {string}
			 */
			getLabel: function (expand) {
				let label = this.data.text;

				if (expand === undefined) {
					expand = true;
				}

				if (expand && typeof(this.expanded) === 'string'
						&& this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON) {
					label = this.expanded;
				}

				return label;
			},

			/**
			 * Gets shape dimensions.
			 */
			getDimensions: function () {
				let dimensions = {
					x: parseInt(this.data.x, 10),
					y: parseInt(this.data.y, 10),
					width: parseInt(this.data.width, 10),
					height: parseInt(this.data.height, 10)
				};

				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					const width = parseInt(this.sysmap.data.width),
						height = parseInt(this.sysmap.data.height),
						x = Math.min(Math.max(0, Math.min(dimensions.x, dimensions.width)), width),
						y = Math.min(Math.max(0, Math.min(dimensions.y, dimensions.height)), height),
						dx = Math.max(dimensions.x, dimensions.width) - x,
						dy = Math.max(dimensions.y, dimensions.height) - y;

					dimensions = {
						x: x,
						y: y,
						width: Math.min(Math.max(0, dx), width - x),
						height: Math.min(Math.max(0, dy), height - y)
					};
				}

				return dimensions;
			},

			updateHandles: function() {
				if (this.handles === undefined) {
					this.handles = [
						$('<div>', {'class': 'ui-resize-dot cursor-move'}),
						$('<div>', {'class': 'ui-resize-dot cursor-move'})
					];

					this.domNode.parent().append(this.handles);

					for (let i = 0; i < 2; i++) {
						this.handles[i].data('id', i);
						this.handles[i].draggable({
							containment: 'parent',
							drag: (e, data) => {
								if (data.helper.data('id') === 0) {
									this.data.x = parseInt(data.position.left, 10) + 4;
									this.data.y = parseInt(data.position.top, 10) + 4;
								}
								else {
									this.data.width = parseInt(data.position.left, 10) + 4;
									this.data.height = parseInt(data.position.top, 10) + 4;
								}

								const dimensions = this.getDimensions();

								Object.assign(this.domNode[0].style, {
									top: `${dimensions.y}px`,
									left: `${dimensions.x}px`,
									width: `${dimensions.width}px`,
									height: `${dimensions.height}px`
								});

								this.trigger('afterMove', this);
							}
						});
					}
				}

				this.handles[0].css({
					left: (this.data.x - 3) + 'px',
					top: (this.data.y - 3) + 'px'
				});

				this.handles[1].css({
					left: (this.data.width - 3) + 'px',
					top: (this.data.height - 3) + 'px'
				});
			},

			/**
			 * Allow dragging of shape.
			 */
			makeDraggable: function(enable) {
				const node = this.domNode,
					enabled = node[0].classList.contains('ui-draggable');

				if (enable) {
					if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
						this.updateHandles();
					}
					else {
						if (this.handles !== undefined) {
							this.handles.forEach((handle) => handle.remove());
							delete this.handles;
						}
					}

					if (!enabled) {
						node.draggable({
							containment: 'parent',
							helper: () => this.sysmap.dragGroupPlaceholder(),
							start: () => {
								node[0].classList.add('cursor-dragging');
								node[0].classList.remove('cursor-pointer');
								this.sysmap.dragGroupInit(this);
							},
							drag: (e, data) => this.sysmap.dragGroupDrag(data, this),
							stop: () => {
								node[0].classList.add('cursor-pointer');
								node[0].classList.remove('cursor-dragging');
								this.sysmap.dragGroupStop(this);
							}
						});
					}
				}
				else {
					if (this.handles !== undefined) {
						this.handles.forEach((handle) => handle.remove());
						delete this.handles;
					}

					if (enabled) {
						node.draggable('destroy');
					}
				}
			},

			/**
			 * Allow resizing of shape.
			 */
			makeResizable: function(enable) {
				const node = this.domNode,
					enabled = node[0].classList.contains('ui-resizable');

				if (enable && !enabled) {
					const handles = {};

					['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'].forEach((key) => {
						const handle = document.createElement('div');

						handle.classList.add('ui-resizable-handle', `ui-resizable-${key}`);

						if (['n', 'e', 's', 'w'].indexOf(key) >= 0) {
							const dot = document.createElement('div'),
								border = document.createElement('div');

							dot.className = 'ui-resize-dot';
							border.className = `ui-resizable-border-${key}`;

							handle.appendChild(dot);
							handle.appendChild(border);
						}

						node[0].appendChild(handle);
						handles[key] = handle;
					});

					node[0].classList.add('ui-inner-handles');

					node.resizable({
						handles: handles,
						autoHide: true,
						stop: (e, data) => {
							this.updatePosition({
								x: parseInt(data.position.left, 10),
								y: parseInt(data.position.top, 10)
							});
						}
					});
				}
				else if (!enable && enabled) {
					node[0].classList.remove('ui-inner-handles', 'ui-resizable', 'ui-resizable-autohide');
					Object.values(this.domNode[0].childNodes).forEach((child) => child.remove());
					node.resizable('destroy');
				}
			},

			/**
			 * Toggle shape selection.
			 *
			 * @param {bool} state
			 */
			toggleSelect: function(state) {
				state = state || !this.selected;
				this.selected = state;

				if (this.selected) {
					this.domNode[0].classList.add('map-element-selected');
				}
				else {
					this.domNode[0].classList.remove('map-element-selected');
				}

				return this.selected;
			},

			/**
			 * Align shape to map or map grid.
			 *
			 * @param {bool} doAutoAlign if we should align element to grid
			 */
			align: function(doAutoAlign) {
				const dims = {
						height: this.domNode.height(),
						width: this.domNode.width()
					},
					dimensions = this.getDimensions(),
					x = dimensions.x,
					y = dimensions.y,
					shiftX = Math.round(dims.width / 2),
					shiftY = Math.round(dims.height / 2),
					gridSize = parseInt(this.sysmap.data.grid_size, 10);

					let newX = x,
						newY = y,
						newWidth = Math.round(dims.width),
						newHeight = Math.round(dims.height);

				// Lines should not be aligned.
				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					Object.assign(this.domNode[0].style, {
						top: `${dimensions.y}px`,
						left: `${dimensions.x}px`,
						width: `${dimensions.width}px`,
						height: `${dimensions.height}px`
					});

					return;
				}

				// If 'fit to map' area coords are 0 always.
				if (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
						&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
						&& this.data.areatype == SVGMapElement.AREA_TYPE_FIT) {
					newX = 0;
					newY = 0;
				}
				// If autoalign is off.
				else if (doAutoAlign === false
						|| (doAutoAlign === undefined && this.sysmap.data.grid_align == SYSMAP_GRID_ALIGN_OFF)) {
					if ((x + dims.width) > this.sysmap.data.width) {
						newX = this.sysmap.data.width - dims.width;
					}
					if ((y + dims.height) > this.sysmap.data.height) {
						newY = this.sysmap.data.height - dims.height;
					}
					if (newX < 0) {
						newX = 0;
						newWidth = this.sysmap.data.width;
					}
					if (newY < 0) {
						newY = 0;
						newHeight = this.sysmap.data.height;
					}
				}
				else {
					newX = x + shiftX;
					newY = y + shiftY;

					newX = Math.floor(newX / gridSize) * gridSize;
					newY = Math.floor(newY / gridSize) * gridSize;

					newX += Math.round(gridSize / 2) - shiftX;
					newY += Math.round(gridSize / 2) - shiftY;

					while ((newX + dims.width) > this.sysmap.data.width) {
						newX -= gridSize;
					}
					while ((newY + dims.height) > this.sysmap.data.height) {
						newY -= gridSize;
					}
					while (newX < 0) {
						newX += gridSize;
					}
					while (newY < 0) {
						newY += gridSize;
					}
				}

				this.data.y = newY;
				this.data.x = newX;

				if (this instanceof Shape || this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS) {
					this.data.width = newWidth;
					this.data.height = newHeight;
				}

				Object.assign(this.domNode[0].style, {
					top: `${this.data.y}px`,
					left: `${this.data.x}px`,
					width: newWidth,
					height: newHeight
				});
			},

			/**
			 * Updates element position.
			 *
			 * @param {object} coords
			 *
			 */
			updatePosition: function(coords, invalidate) {
				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					const dx = coords.x - Math.min(parseInt(this.data.x, 10), parseInt(this.data.width, 10)),
						dy = coords.y - Math.min(parseInt(this.data.y, 10), parseInt(this.data.height, 10));

					this.data.x = parseInt(this.data.x, 10) + dx;
					this.data.y = parseInt(this.data.y, 10) + dy;
					this.data.width = parseInt(this.data.width, 10) + dx;
					this.data.height = parseInt(this.data.height, 10) + dy;

					this.updateHandles();
				}
				else {
					this.data.x = coords.x;
					this.data.y = coords.y;
				}

				if (invalidate !== false) {
					this.align();
					this.trigger('afterMove', this);
				}
				else {
					const dimensions = this.getDimensions();

					Object.assign(this.domNode[0].style, {
						top: `${dimensions.y}px`,
						left: `${dimensions.x}px`
					});
				}
			},

			/**
			 * Removes Shape object, delete all reference to it.
			 */
			remove: function() {
				this.makeDraggable(false);
				this.domNode.remove();
				delete this.sysmap.data.shapes[this.id];
				delete this.sysmap.shapes[this.id];

				if (this.sysmap.selection.shapes[this.id] !== undefined) {
					this.sysmap.selection.count.shapes--;
				}

				delete this.sysmap.selection.shapes[this.id];
			},

			/**
			 * Gets Shape data.
			 *
			 * @return {object}
			 */
			getData: function() {
				return this.data;
			}
		};

		Observer.makeObserver(Shape.prototype);

		/**
		 * @class Creates a new Selement.
		 *
		 * @property {object} sysmap     Reference to Map object.
		 * @property {object} data       Selement DB values.
		 * @property {bool}   selected   If element is now selected by user.
		 * @property {string} id         Element ID.
		 * @property {object} domNode    Reference to related DOM element.
		 *
		 * @param {object} sysmap        Reference to Map object.
		 * @param {object} selementData  Element DB values.
		 */
		function Selement(sysmap, selementData) {
			this.sysmap = sysmap;
			this.selected = false;

			if (!selementData) {
				selementData = {
					selementid: getUniqueId(),
					elementtype: SVGMapElement.TYPE_IMAGE,
					elements: {},
					iconid_off: this.sysmap.defaultIconId, // first imageid
					label: t('S_NEW_ELEMENT'),
					label_location: -1, // set default map label location
					x: 0,
					y: 0,
					urls: {},
					elementName: this.sysmap.defaultIconName, // first image name
					use_iconmap: SYSMAP_ELEMENT_USE_ICONMAP_ON,
					evaltype: TAG_EVAL_TYPE_AND_OR,
					tags: [],
					inherited_label: null,
					zindex: Object.keys(sysmap.selements).length
				};
			}
			else {
				if ($.isArray(selementData.urls)) {
					selementData.urls = {};
				}
			}

			this.data = selementData;
			this.updateLabel();
			this.id = this.data.selementid;
			this.expanded = this.data.expanded;
			delete this.data.expanded;

			// Assign by reference.
			this.sysmap.data.selements[this.id] = this.data;

			// Create dom.
			this.domNode = $('<div>', {style: 'position: absolute; z-index: 100'})
				.appendTo(this.sysmap.container)
				.addClass('cursor-pointer sysmap_element')
				.attr('data-id', this.id)
				.attr('data-type', 'selements');

			this.makeDraggable(true);
			this.makeResizable(this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
					&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
					&& this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM
			);

			this.updateIcon();

			Object.assign(this.domNode[0].style, {
				top: `${this.data.y}px`,
				left: `${this.data.x}px`
			});
		}

		Selement.prototype = {
			/**
			 * Returns element data.
			 */
			getData: Shape.prototype.getData,

			/**
			 * Allows dragging of element.
			 */
			makeDraggable: Shape.prototype.makeDraggable,

			/**
			 * Allows resizing of element.
			 */
			makeResizable: Shape.prototype.makeResizable,

			/**
			 * Update label data inherited from map configuration.
			 */
			updateLabel: function () {
				if (this.sysmap.data.label_format != 0) {
					switch (parseInt(this.data.elementtype, 10)) {
						case SVGMapElement.TYPE_HOST_GROUP:
							this.data.label_type = this.sysmap.data.label_type_hostgroup;

							if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_hostgroup;
							}
							break;

						case SVGMapElement.TYPE_HOST:
							this.data.label_type = this.sysmap.data.label_type_host;

							if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_host;
							}
							break;

						case SVGMapElement.TYPE_TRIGGER:
							this.data.label_type = this.sysmap.data.label_type_trigger;

							if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_trigger;
							}
							break;

						case SVGMapElement.TYPE_MAP:
							this.data.label_type = this.sysmap.data.label_type_map;

							if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_map;
							}
							break;

						case SVGMapElement.TYPE_IMAGE:
							this.data.label_type = this.sysmap.data.label_type_image;

							if (this.data.label_type == SVGMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_image;
							}
							break;
					}
				}
				else {
					this.data.label_type = this.sysmap.data.label_type;
					this.data.inherited_label = null;
				}

				if (this.data.label_type == SVGMap.LABEL_TYPE_LABEL) {
					this.data.inherited_label = this.data.label;
				}
				else if (this.data.label_type == SVGMap.LABEL_TYPE_NAME) {
					if (this.data.elementtype != SVGMapElement.TYPE_IMAGE) {
						this.data.inherited_label = this.data.elements[0].elementName;
					}
					else {
						this.data.inherited_label = t('S_IMAGE');
					}
				}

				if (this.data.label_type != SVGMap.LABEL_TYPE_CUSTOM && this.data.label_type != SVGMap.LABEL_TYPE_LABEL
						&& this.data.label_type != SVGMap.LABEL_TYPE_IP) {
					this.data.expanded = null;
				}
				else if (this.data.label_type == SVGMap.LABEL_TYPE_IP
						&& this.data.elementtype == SVGMapElement.TYPE_HOST) {
					this.data.inherited_label = '{HOST.IP}';
				}
			},

			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean}
			 *
			 * @return {string|null}  Label with expanded macros.
			 */
			getLabel: function (expand) {
				let label = this.data.label;

				if (expand === undefined) {
					expand = true;
				}

				if (this.data.label_type != SVGMap.LABEL_TYPE_NOTHING
						&& this.data.label_type != SVGMap.LABEL_TYPE_STATUS) {
					if (expand && typeof(this.expanded) === 'string'
							&& (this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON
								|| (this.data.label_type == SVGMap.LABEL_TYPE_IP
								&& this.data.elementtype == SVGMapElement.TYPE_HOST))) {
						label = this.expanded;
					}
					else if (typeof this.data.inherited_label === 'string') {
						label = this.data.inherited_label;
					}
				}
				else {
					label = '';
				}

				return label;
			},

			/**
			 * Updates element fields.
			 *
			 * @param {object}  data
			 * @param {boolean} unset_undefined  If true, all fields that are not in data parameter will be removed
			 *                                   from element.
			 */
			update: function(data, unset_undefined = false) {
				const data_fields = ['elementtype', 'elements', 'iconid_off', 'iconid_on', 'iconid_maintenance',
						'iconid_disabled', 'label', 'label_location', 'x', 'y', 'elementsubtype',  'areatype', 'width',
						'height', 'viewtype', 'urls', 'elementName', 'use_iconmap', 'evaltype', 'tags'
					],
					fields_unsettable = ['iconid_off', 'iconid_on', 'iconid_maintenance', 'iconid_disabled'];

				let invalidate = ((data.label !== undefined && this.data.label !== data.label)
							|| (data.elementtype !== undefined && this.data.elementtype != data.elementtype)
							|| (data.elements !== undefined
							&& Object.keys(this.data.elements).length != Object.keys(data.elements).length));

				if (!invalidate && data.elements) {
					invalidate = Object.keys(this.data.elements).some((id) =>
						Object.keys(this.data.elements[id]).some((key) =>
							this.data.elements[id][key] !== data.elements[id][key]
						)
					);
				}

				data_fields.forEach((field) => {
					if (data[field] !== undefined) {
						this.data[field] = data[field];
					}
					else if (unset_undefined && !fields_unsettable.includes(field)) {
						delete this.data[field];
					}
				});

				if (unset_undefined) {
					// If elementsubtype is not set, it should be 0.
					if (this.data.elementsubtype === undefined) {
						this.data.elementsubtype = SVGMapElement.SUBTYPE_HOST_GROUP;
					}
					if (this.data.use_iconmap === undefined) {
						this.data.use_iconmap = SYSMAP_ELEMENT_USE_ICONMAP_OFF;
					}
				}

				this.makeResizable(
					this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
						&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
						&& this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM
				);

				if (this.data.elementtype == SVGMapElement.TYPE_IMAGE) {
					// If element is image, unset advanced icons.
					this.data.iconid_on = '0';
					this.data.iconid_maintenance = '0';
					this.data.iconid_disabled = '0';

					// If image element, set elementName to image name.
					for (const i in this.sysmap.iconList) {
						if (this.sysmap.iconList[i].imageid === this.data.iconid_off) {
							this.data.elementName = this.sysmap.iconList[i].name;
						}
					}
				}
				else {
					this.data.elementName = this.data.elements[0].elementName;
				}

				this.updateLabel();
				this.updateIcon();
				this.align(false);
				this.trigger('afterMove', this);

				if (invalidate) {
					this.sysmap.expandMacros(this);
				}
			},

			/**
			 * Updates element position.
			 *
			 * @param {object} coords
			 */
			updatePosition: Shape.prototype.updatePosition,

			/**
			 * Remove element.
			 */
			remove: function() {
				this.domNode.remove();
				delete this.sysmap.data.selements[this.id];
				delete this.sysmap.selements[this.id];

				if (this.sysmap.selection.selements[this.id] !== undefined) {
					this.sysmap.selection.count.selements--;
				}

				delete this.sysmap.selection.selements[this.id];
			},

			/**
			 * Toggle element selection.
			 *
			 * @param {bool} state
			 */
			toggleSelect: Shape.prototype.toggleSelect,

			/**
			 * Align element to map or map grid.
			 *
			 * @param {bool} doAutoAlign if we should align element to grid
			 */
			align: Shape.prototype.align,

			/**
			 * Get element dimensions.
			 */
			getDimensions: Shape.prototype.getDimensions,

			/**
			 * Updates element icon and height/width in case element is area type.
			 */
			updateIcon: function() {
				const old_icon_class = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);

				if (old_icon_class !== null) {
					this.domNode[0].classList.remove(old_icon_class[0]);
				}

				if ((this.data.use_iconmap == SYSMAP_ELEMENT_USE_ICONMAP_ON && this.sysmap.data.iconmapid != 0)
						&& (this.data.elementtype == SVGMapElement.TYPE_HOST
							|| (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
									&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS))) {
					this.domNode[0].classList.add('sysmap_iconid_' + this.sysmap.defaultAutoIconId);
				}
				else {
					this.domNode[0].classList.add('sysmap_iconid_' + this.data.iconid_off);
				}

				if (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
						&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS) {
					if (this.data.areatype == SVGMapElement.AREA_TYPE_CUSTOM) {
						Object.assign(this.domNode[0].style, {
							width: `${this.data.width}px`,
							height: `${this.data.height}px`
						});
					}
					else {
						Object.assign(this.domNode[0].style, {
							width: `${this.sysmap.data.width}px`,
							height: `${this.sysmap.data.height}px`
						});
					}

					this.domNode[0].classList.add('map-element-area-bg');
				}
				else {
					Object.assign(this.domNode[0].style, {
						width: '',
						height: ''
					});

					this.domNode[0].classList.remove('map-element-area-bg');
				}
			},

			getName: function () {
				let name;

				if (this.data.elementName === undefined) {
					name = this.data.elements[0].elementName;

					if (Object.keys(this.data.elements).length > 1) {
						name += '...';
					}
				}
				else {
					name = this.data.elementName;
				}

				return name;
			}
		};

		Observer.makeObserver(Selement.prototype);

		/**
		 * Form for elements.
		 *
		 * @param {object} formContainer  jQuery object.
		 * @param {object} sysmap
		 */
		function SelementForm(formContainer, sysmap) {
			const formTplData = {sysmapid: sysmap.sysmapid},
				tpl = new Template(document.getElementById('mapElementFormTpl').innerHTML),
				formActions = [
					{
						action: 'show',
						value: '#subtypeRow, #hostGroupSelectRow',
						cond: [{
							elementType: SVGMapElement.TYPE_HOST_GROUP
						}]
					},
					{
						action: 'show',
						value: '#hostSelectRow',
						cond: [{
							elementType: SVGMapElement.TYPE_HOST
						}]
					},
					{
						action: 'show',
						value: '#triggerSelectRow, #triggerListRow',
						cond: [{
							elementType: SVGMapElement.TYPE_TRIGGER
						}]
					},
					{
						action: 'show',
						value: '#mapSelectRow',
						cond: [{
							elementType: SVGMapElement.TYPE_MAP
						}]
					},
					{
						action: 'show',
						value: '#areaTypeRow, #areaPlacingRow',
						cond: [{
							elementType: SVGMapElement.TYPE_HOST_GROUP,
							subtypeHostGroupElements: 'checked'
						}]
					},
					{
						action: 'show',
						value: '#areaSizeRow',
						cond: [{
							elementType: SVGMapElement.TYPE_HOST_GROUP,
							subtypeHostGroupElements: 'checked',
							areaTypeCustom: 'checked'
						}]
					},
					{
						action: 'hide',
						value: '#iconProblemRow, #iconMainetnanceRow, #iconDisabledRow',
						cond: [{
							elementType: SVGMapElement.TYPE_IMAGE
						}]
					},
					{
						action: 'disable',
						value: '#iconid_off, #iconid_on, #iconid_maintenance, #iconid_disabled',
						cond: [
							{
								use_iconmap: 'checked',
								elementType: SVGMapElement.TYPE_HOST
							},
							{
								use_iconmap: 'checked',
								elementType: SVGMapElement.TYPE_HOST_GROUP,
								subtypeHostGroupElements: 'checked'
							}
						]
					},
					{
						action: 'show',
						value: '#useIconMapRow',
						cond: [
							{
								elementType: SVGMapElement.TYPE_HOST
							},
							{
								elementType: SVGMapElement.TYPE_HOST_GROUP,
								subtypeHostGroupElements: 'checked'
							}
						]
					},
					{
						action: 'show',
						value: '#tags-select-row',
						cond: [
							{
								elementType: SVGMapElement.TYPE_HOST
							},
							{
								elementType: SVGMapElement.TYPE_HOST_GROUP
							}
						]
					}
				];

			this.active = false;
			this.sysmap = sysmap;
			this.formContainer = formContainer;

			// create form
			this.domNode = $(tpl.evaluate(formTplData)).appendTo(formContainer);

			// populate icons selects
			const select_icon_off = document.getElementById('iconid_off'),
				select_icon_on = document.getElementById('iconid_on'),
				select_icon_maintenance = document.getElementById('iconid_maintenance'),
				select_icon_disabled = document.getElementById('iconid_disabled'),
				default_option = {
					label: t('S_DEFAULT'),
					value: '0',
					class_name: ZBX_STYLE_DEFAULT_OPTION,
				};

			[select_icon_on, select_icon_maintenance, select_icon_disabled]
				.forEach((select) => select.addOption(default_option));

			Object.values(this.sysmap.iconList).forEach((icon) => {
				const option = {label: icon.name, value: icon.imageid};

				[select_icon_off, select_icon_on, select_icon_maintenance, select_icon_disabled]
					.forEach((select) => select.addOption(option));
			});

			$('#elementNameHost').multiSelectHelper({
				id: 'elementNameHost',
				object_name: 'hosts',
				name: 'elementValue',
				selectedLimit: 1,
				popup: {
					parameters: {
						srctbl: 'hosts',
						srcfld1: 'hostid',
						dstfrm: 'selementForm',
						dstfld1: 'elementNameHost'
					}
				}
			});

			$('#elementNameMap').multiSelectHelper({
				id: 'elementNameMap',
				object_name: 'sysmaps',
				name: 'elementValue',
				selectedLimit: 1,
				popup: {
					parameters: {
						srctbl: 'sysmaps',
						srcfld1: 'sysmapid',
						dstfrm: 'selementForm',
						dstfld1: 'elementNameMap'
					}
				}
			});

			$('#elementNameTriggers').multiSelectHelper({
				id: 'elementNameTriggers',
				object_name: 'triggers',
				name: 'elementValue',
				objectOptions: {
					real_hosts: true
				},
				popup: {
					parameters: {
						srctbl: 'triggers',
						srcfld1: 'triggerid',
						dstfrm: 'selementForm',
						dstfld1: 'elementNameTriggers',
						with_triggers: '1',
						real_hosts: '1',
						multiselect: '1'
					}
				}
			});

			$('#elementNameHostGroup').multiSelectHelper({
				id: 'elementNameHostGroup',
				object_name: 'hostGroup',
				name: 'elementValue',
				selectedLimit: 1,
				popup: {
					parameters: {
						srctbl: 'host_groups',
						srcfld1: 'groupid',
						dstfrm: 'selementForm',
						dstfld1: 'elementNameHostGroup'
					}
				}
			});

			this.actionProcessor = new ActionProcessor(formActions);
			this.actionProcessor.process();
		}

		SelementForm.prototype = {
			/**
			 * Shows element form.
			 */
			show: function() {
				this.formContainer.draggable('option', 'handle', '#formDragHandler');
				this.formContainer.show();
				this.domNode.show();
				// Element must first be visible so that outerWidth() and outerHeight() are correct.
				this.formContainer.positionOverlayDialogue();
				this.active = true;

				addToOverlaysStack('map-window', document.activeElement, 'map-window');

				document.getElementById('elementType').focus();
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;

				removeFromOverlaysStack('map-window');
			},

			/**
			 * Adds element urls to form.
			 *
			 * @param {object} urls
			 */
			addUrls: function(urls) {
				const tpl = new Template(document.getElementById('selementFormUrls').innerHTML),
					tbody = document.querySelector('#urlContainer tbody');

				let urlid = tbody.querySelectorAll('tr[id^="urlrow"]').length;

				if (urls === undefined || Object.keys(urls).length == 0) {
					urls = {empty: {}};
				}

				Object.values(urls).forEach((url) => {
					while (document.getElementById(`urlrow_${urlid}`)) {
						urlid++;
					}
					url.selementurlid = urlid;
					tbody.insertAdjacentHTML('beforeend', tpl.evaluate(url));
				});
			},

			/**
			 * Append form tag field options.
			 *
			 * @param {array} tags
			 */
			addTags: function(tags) {
				const tpl = new Template(document.getElementById('tag-row-tmpl').innerHTML),
					add_btn_row = document.querySelector('#selement-tags .element-table-add').closest('tr');

				let counter = $('#selement-tags').data('dynamicRows').counter;

				for (const i in tags) {
					const tag = jQuery.extend({tag: '', operator: 0, value: '', rowNum: ++counter}, tags[i]),
						$row = $(tpl.evaluate(tag));

					$row.insertBefore(add_btn_row);

					['tag', 'operator', 'value'].forEach((field) =>
						$row
							.find(`[name="tags[${tag.rowNum}][${field}]"]`)
							.val(tag[field])
					);

					new CTagFilterItem($row[0]);
				}

				$('#selement-tags').data('dynamicRows').counter = counter;
			},

			/**
			 * Add triggers to the list.
			 */
			addTriggers: function(triggers) {
				const tpl = new Template(document.getElementById('selementFormTriggers').innerHTML),
					selected_triggers = $('#elementNameTriggers').multiSelect('getData'),
					triggerids = [],
					triggers_to_insert = [];

				if (triggers === undefined || $.isEmptyObject(triggers)) {
					triggers = [];
				}

				triggers = triggers.concat(selected_triggers);

				if (triggers) {
					triggers.forEach((trigger) => {
						if ($(`input[name^="element_id[${trigger.id}]"]`).length == 0) {
							triggerids.push(trigger.id);
							triggers_to_insert[trigger.id] = {
								id: trigger.id,
								name: trigger.prefix === undefined ? trigger.name : trigger.prefix + trigger.name
							};
						}
					});

					if (triggerids.length != 0) {
						const url = new Curl('jsrpc.php');

						url.setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);

						$.ajax({
							url: url.getUrl(),
							type: 'post',
							dataType: 'html',
							data: {
								method: 'trigger.get',
								triggerids
							},
							success: function(data) {
								data = JSON.parse(data);
								triggers.forEach((sorted_trigger) => {
									data.result.forEach((trigger) => {
										if (sorted_trigger.id === trigger.triggerid) {
											if ($(`input[name^="element_id[${trigger.triggerid}]"]`).length == 0) {
												trigger.name = triggers_to_insert[trigger.triggerid].name;
												$(tpl.evaluate(trigger)).appendTo('#triggerContainer tbody');

												return false;
											}
										}
									});
								});

								SelementForm.prototype.recalculateTriggerSortOrder();
							}
						});
					}

					$('#elementNameTriggers').multiSelect('clean');
				}
			},

			/**
			 * Set form controls with element fields values.
			 *
			 * @param {object} selement
			 */
			setValues: function(selement) {
				// jQuery left here for convenience purposes.
				for (const name in selement) {
					$(`[name=${name}]`, this.domNode).val([selement[name]]);
				}

				['iconid_on', 'iconid_disabled', 'iconid_maintenance'].forEach((name) => {
					$(`[name=${name}]`, this.domNode).val(0);
				});

				// Clear urls.
				document.querySelectorAll('#urlContainer tbody tr').forEach((tr) => tr.remove());
				this.addUrls(selement.urls);

				// Set tag properties.
				let tags = selement.tags;

				if (!tags || Object.getOwnPropertyNames(tags).length == 0) {
					tags = {0: {}};
				}

				this.cleanTagsField();
				this.addTags(tags);

				// Iconmap.
				if (this.sysmap.data.iconmapid == 0) {
					const use_iconmap = document.getElementById('use_iconmap');

					if (use_iconmap) {
						use_iconmap.checked = false;
						use_iconmap.disabled = true;
					}
				}

				this.actionProcessor.process();

				switch (+selement.elementtype) {
					case SVGMapElement.TYPE_HOST:
						$('#elementNameHost').multiSelect('addData', [{
							'id': selement.elements[0].hostid,
							'name': selement.elements[0].elementName
						}]);
						break;

					case SVGMapElement.TYPE_MAP:
						$('#elementNameMap').multiSelect('addData', [{
							'id': selement.elements[0].sysmapid,
							'name': selement.elements[0].elementName
						}]);
						break;

					case SVGMapElement.TYPE_TRIGGER:
						const triggers = Object.values(selement.elements).map((element) => ({
							id: element.triggerid,
							name: element.elementName
						}));

						this.addTriggers(triggers);
						break;

					case SVGMapElement.TYPE_HOST_GROUP:
						$('#elementNameHostGroup').multiSelect('addData', [{
							'id': selement.elements[0].groupid,
							'name': selement.elements[0].elementName
						}]);
						break;
				}
			},

			/**
			 * Remove tag filter rows from DOM.
			 */
			cleanTagsField: function() {
				document.querySelectorAll('#selement-tags .form_row').forEach((element) => element.remove());
			},

			/**
			 * Gets form values for element fields.
			 *
			 * @return {object|boolean}
			 */
			getValues: function() {
				const values = $(':input', '#selementForm')
						.not(this.actionProcessor.hidden)
						.not('[name^="tags"]')
						.serializeArray(),
					data = {
						urls: {}
					},
					url_pattern = /^url_(\d+)_(name|url)$/,
					url_names = {};

				let elements_data = {};

				values.forEach(({name, value}) => {
					const match = url_pattern.exec(name);

					if (match) {
						data.urls[match[1]] = data.urls[match[1]] || {};
						data.urls[match[1]][match[2]] = value;
					}
					else {
						data[name] = value;
					}
				});

				if (data.elementtype == SVGMapElement.TYPE_HOST
						|| data.elementtype == SVGMapElement.TYPE_HOST_GROUP) {
					data.tags = {};

					$('input, z-select', '#selementForm')
						.filter(function() {
							return this.name.match(/tags\[\d+\]\[tag\]/);
						})
						.each(function() {
							if (this.value !== '') {
								const num = parseInt(this.name.match(/^tags\[(\d+)\]\[tag\]$/)[1]);

								data.tags[Object.getOwnPropertyNames(data.tags).length] = {
									tag: this.value,
									operator: $(`[name="tags[${num}][operator]"]`).val(),
									value: $(`[name="tags[${num}][value]"]`).val()
								};
							}
					});
				}

				data.elements = {};

				// Set element ID and name.
				switch (+data.elementtype) {
					case SVGMapElement.TYPE_HOST:
						elements_data = $('#elementNameHost').multiSelect('getData');

						if (elements_data.length != 0) {
							data.elements[0] = {
								hostid: elements_data[0].id,
								elementName: elements_data[0].name
							};
						}
						break;

					case SVGMapElement.TYPE_MAP:
						elements_data = $('#elementNameMap').multiSelect('getData');

						if (elements_data.length != 0) {
							data.elements[0] = {
								sysmapid: elements_data[0].id,
								elementName: elements_data[0].name
							};
						}
						break;

					case SVGMapElement.TYPE_TRIGGER:
						let i = 0;

						const triggers_list = document.querySelectorAll('input[name^="element_id"]');

						triggers_list.forEach((input) => {
							const triggerid = input.value,
								elementName = document.querySelector(`input[name^="element_name[${triggerid}]"]`).value,
								priority = document
									.querySelector(`input[name^="element_priority[${triggerid}]"]`).value;

							data.elements[i++] = {triggerid, elementName, priority};
						});
						break;

					case SVGMapElement.TYPE_HOST_GROUP:
						elements_data = $('#elementNameHostGroup').multiSelect('getData');

						if (elements_data.length != 0) {
							data.elements[0] = {
								groupid: elements_data[0].id,
								elementName: elements_data[0].name
							};
						}
						break;
				}

				// Validate URLs.
				for (const key in data.urls) {
					const {name, url} = data.urls[key];

					if (name === '' && url === '') {
						delete data.urls[key];
						continue;
					}

					if (name === '' || url === '') {
						alert(t('S_INCORRECT_ELEMENT_MAP_LINK'));

						return false;
					}

					if (url_names[name] !== undefined) {
						alert(t('S_EACH_URL_SHOULD_HAVE_UNIQUE') + " '" + name + "'.");

						return false;
					}

					url_names[name] = 1;
				}

				// Validate element ID.
				if ($.isEmptyObject(data.elements) && data.elementtype != SVGMapElement.TYPE_IMAGE) {
					const messages = {
						[SVGMapElement.TYPE_HOST]: t('Host is not selected.'),
						[SVGMapElement.TYPE_MAP]: t('Map is not selected.'),
						[SVGMapElement.TYPE_TRIGGER]: t('Trigger is not selected.'),
						[SVGMapElement.TYPE_HOST_GROUP]: t('Host group is not selected.')
					};

					alert(messages[+data.elementtype]);

					return false;
				}

				return data;
			},

			/**
			 * Sorting triggers by severity.
			 */
			recalculateTriggerSortOrder: function() {
				const triggers_list = document.querySelectorAll('input[name^="element_id"]');

				if (triggers_list.length != 0) {
					const triggers = [];

					triggers_list.forEach((input) => {
						const triggerid = input.value,
							priority = document.querySelector(`input[name^="element_priority[${triggerid}]"]`).value,
							html = document.getElementById(`triggerrow_${triggerid}`).outerHTML;

						if (!triggers[priority]) {
							triggers[priority] = {priority, html};
						}
						else {
							triggers[priority].html += html;
						}
					});

					triggers.sort((a, b) => b.priority - a.priority);

					const container = document.querySelector('#triggerContainer tbody');

					if (container) {
						container.innerHTML = '';
						triggers.forEach((trigger) => container.insertAdjacentHTML('beforeend', trigger.html));
					}
				}
			}
		};

		/**
		 * Elements mass update form.
		 *
		 * @param {object} formContainer jQuery object
		 * @param {object} sysmap
		 */
		function MassForm(formContainer, sysmap) {
			const tpl = new Template(document.getElementById('mapMassFormTpl').innerHTML),
				formActions = [
					{
						action: 'enable',
						value: '#massLabel',
						cond: [{
							chkboxLabel: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massLabelLocation',
						cond: [{
							chkboxLabelLocation: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massUseIconmap',
						cond: [{
							chkboxMassUseIconmap: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massIconidOff',
						cond: [{
							chkboxMassIconidOff: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massIconidOn',
						cond: [{
							chkboxMassIconidOn: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massIconidMaintenance',
						cond: [{
							chkboxMassIconidMaintenance: 'checked'
						}]
					},
					{
						action: 'enable',
						value: '#massIconidDisabled',
						cond: [{
							chkboxMassIconidDisabled: 'checked'
						}]
					}
				];

			this.sysmap = sysmap;
			this.formContainer = formContainer;

			// create form
			this.domNode = $(tpl.evaluate()).appendTo(formContainer);

			// populate icons selects
			const select_icon_off = document.getElementById('massIconidOff'),
				select_icon_on = document.getElementById('massIconidOn'),
				select_icon_maintenance = document.getElementById('massIconidMaintenance'),
				select_icon_disabled = document.getElementById('massIconidDisabled'),
				default_option = {
					label: t('S_DEFAULT'),
					value: '0',
					class_name: ZBX_STYLE_DEFAULT_OPTION,
				};

			[select_icon_on, select_icon_maintenance, select_icon_disabled]
				.forEach((select) => select.addOption(default_option));

			Object.values(this.sysmap.iconList).forEach((icon) => {
				const option = {label: icon.name, value: icon.imageid};

				[select_icon_off, select_icon_on, select_icon_maintenance, select_icon_disabled]
					.forEach((select) => select.addOption(option));
			});

			document.getElementById('massLabelLocation').selectedIndex = 0;

			select_icon_off.selectedIndex = 0;
			select_icon_on.selectedIndex = 0;
			select_icon_maintenance.selectedIndex = 0;
			select_icon_disabled.selectedIndex = 0;

			this.actionProcessor = new ActionProcessor(formActions);
			this.actionProcessor.process();
		}

		MassForm.prototype = {
			/**
			 * Show mass update form.
			 */
			show: function() {
				this.formContainer.draggable('option', 'handle', '#massDragHandler');
				this.formContainer.show();
				this.domNode.show();
				// Element must first be visible so that outerWidth() and outerHeight() are correct.
				this.formContainer.positionOverlayDialogue();
				this.updateList();

				addToOverlaysStack('map-window', document.activeElement, 'map-window');

				document.getElementById('chkboxLabel').focus();
			},

			/**
			 * Hide mass update form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				$(':checkbox', this.domNode).prop('checked', false);
				$('z-select', this.domNode).each(function() {
					this.selectedIndex = 0;
				});
				$('textarea', this.domNode).val('');
				this.actionProcessor.process();

				removeFromOverlaysStack('map-window');
			},

			/**
			 * Get values from mass update form that should be updated in all selected elements.
			 *
			 * @return array
			 */
			getValues: function() {
				const values = $('#massForm').serializeArray(),
					data = {};

				for (const {name, value} of values) {
					// Special case for use iconmap checkbox, because unchecked checkbox is not submitted with form.
					if (name === 'chkbox_use_iconmap') {
						data.use_iconmap = SYSMAP_ELEMENT_USE_ICONMAP_OFF;
					}

					if (/^chkbox_/.test(name)) {
						continue;
					}

					data[name] = value;
				}

				return data;
			},

			/**
			 * Updates list of selected elements in mass update form.
			 */
			updateList: function() {
				const tpl = new Template(document.getElementById('mapMassFormListRow').innerHTML);

				$('#massList tbody').empty();

				const list = Object.values(this.sysmap.selection.selements).map((id) => {
					const element = this.sysmap.selements[id],
						type = +element.data.elementtype;

					let text;

					switch (type) {
						case SVGMapElement.TYPE_HOST:
							text = t('S_HOST');
							break;

						case SVGMapElement.TYPE_MAP:
							text = t('S_MAP');
							break;

						case SVGMapElement.TYPE_TRIGGER:
							text = t('S_TRIGGER');
							break;

						case SVGMapElement.TYPE_HOST_GROUP:
							text = t('S_HOST_GROUP');
							break;

						case SVGMapElement.TYPE_IMAGE:
							text = t('S_IMAGE');
							break;
					}

					return {
						elementType: text,
						elementName: element.getName()
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;')
							.replace(/"/g, '&quot;')
							.replace(/'/g, '&apos;')
					};
				});

				// Sort by element type and then by element name.
				list.sort((a, b) =>
					a.elementType.toLowerCase().localeCompare(b.elementType.toLowerCase())
						|| a.elementName.toLowerCase().localeCompare(b.elementName.toLowerCase())
				);

				list.forEach((item) => $(tpl.evaluate(item)).appendTo('#massList tbody'));
			}
		};

		/**
		 * Form for shape editing.
		 *
		 * @param {object} formContainer jQuery object.
		 * @param {object} sysmap
		 */
		function ShapeForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template(document.getElementById('mapShapeFormTpl').innerHTML).evaluate())
				.appendTo(formContainer);
			this.domNode.find('.color-picker input').colorpicker();
		}

		ShapeForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.formContainer.draggable('option', 'handle', '#shapeDragHandler');
				this.formContainer.show();
				this.domNode.show();
				// Element must first be visible so that outerWidth() and outerHeight() are correct.
				this.formContainer.positionOverlayDialogue();
				this.active = true;

				addToOverlaysStack('map-window', document.activeElement, 'map-window');

				document.querySelector('#shapeForm [name="type"]:checked').focus();
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;

				removeFromOverlaysStack('map-window');
			},

			/**
			 * Set form controls with shape fields values.
			 *
			 * @param {object} shape
			 */
			setValues: function(shape) {
				for (const field in shape) {
					$(`[name=${field}]`, this.domNode).val([shape[field]]);
				}

				$('.color-picker input', this.domNode).change();

				document.getElementById('border_type').dispatchEvent(new Event('change'));
				document.getElementById('last_shape_type').value = shape.type;

				document.querySelectorAll('input[type=radio][name=type]:checked')
					.forEach((input) => input.dispatchEvent(new Event('change')));
			},

			/**
			 * Get values from shape update form that should be updated
			 *
			 * @return {object}
			 */
			getValues: function() {
				const values = $('#shapeForm').serializeArray(),
					width = parseInt(this.sysmap.data.width),
					height = parseInt(this.sysmap.data.height),
					data = values.reduce((acc, {name, value}) => {
						acc[name] = value.toString();

						return acc;
					}, {});

				data.x = parseInt(data.x, 10);
				data.y = parseInt(data.y, 10);
				data.width = parseInt(data.width, 10);
				data.height = parseInt(data.height, 10);

				data.x = isNaN(data.x) ? 0 : Math.min(Math.max(0, data.x), width);
				data.y = isNaN(data.y) ? 0 : Math.min(Math.max(0, data.y), height);

				const min_size = data.type != SVGMapShape.TYPE_LINE ? 1 : 0;

				data.width = isNaN(data.width) ? min_size : Math.min(Math.max(min_size, data.width), width);
				data.height = isNaN(data.height) ? min_size : Math.min(Math.max(min_size, data.height), height);

				return data;
			}
		};

		/**
		 * Form for shape editing.
		 *
		 * @param {object} formContainer jQuery object
		 * @param {object} sysmap
		 */
		function MassShapeForm(formContainer, sysmap) {
			const form_actions = [],
				mapping = {
					chkboxType: '[name="mass_type"]',
					chkboxText: '#mass_text',
					chkboxFont: '#mass_font',
					chkboxFontSize: '#mass_font_size',
					chkboxFontColor: '#mass_font_color',
					chkboxTextHalign: '#mass_text_halign',
					chkboxTextValign: '#mass_text_valign',
					chkboxBackground: '#mass_background_color',
					chkboxBorderType: '#mass_border_type',
					chkboxBorderWidth: '#mass_border_width',
					chkboxBorderColor: '#mass_border_color'
				};

			Object.keys(mapping).forEach((key) => form_actions.push({
				action: 'enable',
				value: mapping[key],
				cond: [{[key]: 'checked'}]
			}));

			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template(document.getElementById('mapMassShapeFormTpl').innerHTML).evaluate())
				.appendTo(formContainer);

			this.domNode.find('.color-picker input').colorpicker();
			this.actionProcessor = new ActionProcessor(form_actions);
			this.actionProcessor.process();
		}

		MassShapeForm.prototype = {
			/**
			 * Show form.
			 */
			show: function(figures) {
				const value = figures ? 0 : 2;

				$('.shape_figure_row', this.domNode).toggle(figures);
				$('.switchable-content', this.domNode).each((i, element) => {
					element.textContent = element.hasAttribute(`data-value-${value}`)
						? element.getAttribute(`data-value-${value}`)
						: element.dataset.value;
				});

				this.formContainer.draggable('option', 'handle', '#massShapeDragHandler');
				this.formContainer.show();
				this.domNode.show();
				// Element must first be visible so that outerWidth() and outerHeight() are correct.
				this.formContainer.positionOverlayDialogue();
				this.active = true;

				addToOverlaysStack('map-window', document.activeElement, 'map-window');

				document.getElementById(figures ? 'chkboxType' : 'chkboxBorderType').focus();
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;
				$(':checkbox', this.domNode).prop('checked', false).prop("disabled", false);
				$('textarea, input[type=text]', this.domNode).val('');
				$('.color-picker input', this.domNode).change();
				this.actionProcessor.process();

				removeFromOverlaysStack('map-window');
			},

			/**
			 * Get values from mass update form that should be updated in all selected shapes.
			 *
			 * @return {object}
			 */
			getValues: function() {
				return Object.fromEntries($('#massShapeForm').serializeArray()
					.filter((v) => v.name.startsWith('mass_'))
					.map((v) => [v.name.slice(5), v.value.toString()]));
			}
		};

		/**
		 * Form for editing links.
		 *
		 * @param {object} formContainer jQuery object.
		 * @param {object} sysmap
		 */
		function LinkForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template(document.getElementById('linkFormTpl').innerHTML).evaluate())
				.appendTo(formContainer);

			this.domNode.find('.color-picker input').colorpicker();
		}

		LinkForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.domNode.show();
				document.querySelectorAll('.element-edit-control').forEach((element) => element.disabled = true);
			},

			/**
			 * Hide form.
			 */
			hide: function() {
				$('#linkForm').hide();
				document.querySelectorAll('.element-edit-control').forEach((element) =>
					element.removeAttribute('disabled')
				);
			},

			/**
			 * Get form values for link fields.
			 */
			getValues: function() {
				const data = {linktriggers: {}},
					link_trigger_pattern = /^linktrigger_(\w+)_(triggerid|linktriggerid|drawtype|color|desc_exp)$/;

				$('#linkForm').serializeArray().forEach(({name, value}) => {
					const link_trigger = link_trigger_pattern.exec(name);

					value = value.toString();

					if ((name === 'color' || link_trigger?.[2] === 'color') && !isColorHex(`#${value}`)) {
						throw sprintf(t('S_COLOR_IS_NOT_CORRECT'), value);
					}

					if (link_trigger) {
						data.linktriggers[link_trigger[1]] ??= {};
						data.linktriggers[link_trigger[1]][link_trigger[2]] = value;
					}
					else {
						data[name] = value;
					}
				});

				return data;
			},

			/**
			 * Update form controls with values from link.
			 *
			 * @param {object} link
			 */
			setValues: function(link) {
				// If only one element is selected and no shapes, swap link IDs if needed.
				if (this.sysmap.selection.count.selements == 1 && this.sysmap.selection.count.shapes == 0) {
					const selement1 = this.sysmap.selements[Object.keys(this.sysmap.selection.selements)[0]];

					if (selement1.id !== link.selementid1) {
						[link.selementid1, link.selementid2] = [selement1.id, link.selementid1];
					}
				}

				const connect_to_select = document.createElement('z-select');

				connect_to_select._button.id = 'label-selementid2';
				connect_to_select.id = 'selementid2';
				connect_to_select.name = 'selementid2';

				// Sort by type.
				const optgroups = {};

				Object.values(this.sysmap.selements).forEach((selement) => {
				if (selement.id === link.selementid1) {
						return;
					}

					const type = selement.data.elementtype;

					(optgroups[type] = optgroups[type] || []).push(selement);
				});

				Object.keys(optgroups).forEach((type) => {
					let label;

					switch (+type) {
						case SVGMapElement.TYPE_HOST:
							label = t('S_HOST');
							break;

						case SVGMapElement.TYPE_MAP:
							label = t('S_MAP');
							break;

						case SVGMapElement.TYPE_TRIGGER:
							label = t('S_TRIGGER');
							break;

						case SVGMapElement.TYPE_HOST_GROUP:
							label = t('S_HOST_GROUP');
							break;

						case SVGMapElement.TYPE_IMAGE:
							label = t('S_IMAGE');
							break;
					}

					const optgroup = {
						label,
						options: optgroups[type].map((element) => ({value: element.id, label: element.getName()}))
					};

					connect_to_select.addOptionGroup(optgroup);
				});

				$('#selementid2').replaceWith(connect_to_select);

				// Set values for form elements.
				Object.keys(link).forEach((name) => $(`[name=${name}]`, this.domNode).val(link[name]));

				// Clear triggers.
				this.triggerids = {};
				document.querySelectorAll('#linkTriggerscontainer tbody tr').forEach((tr) => tr.remove());
				this.addLinkTriggers(link.linktriggers);
			},

			/**
			 * Add link triggers to link form.
			 *
			 * @param {object} triggers
			 */
			addLinkTriggers: function(triggers) {
				const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
					$table = $('#linkTriggerscontainer tbody');

				Object.values(triggers).forEach((trigger) => {
					this.triggerids[trigger.triggerid] = true;
					$(tpl.evaluate(trigger)).appendTo($table);
					$(`#linktrigger_${trigger.linktriggerid}_drawtype`).val(trigger.drawtype);
				});

				$table.find('.color-picker input').colorpicker();
				$('.color-picker input', this.domNode).change();
			},

			/**
			 * Add new triggers which were selected in popup to trigger list.
			 *
			 * @param {object} triggers
			 */
			addNewTriggers: function(triggers) {
				const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
					linkTrigger = {color: 'DD0000'},
					$table = $('#linkTriggerscontainer tbody');

				for (let i = 0, ln = triggers.length; i < ln; i++) {
					if (this.triggerids[triggers[i].triggerid] !== undefined) {
						continue;
					}

					const linktriggerid = getUniqueId();

					// Store linktriggerid to generate every time unique one.
					this.sysmap.allLinkTriggerIds[linktriggerid] = true;

					// Store triggerid to forbid selecting same trigger twice.
					this.triggerids[triggers[i].triggerid] = linktriggerid;
					linkTrigger.linktriggerid = linktriggerid;
					linkTrigger.desc_exp = triggers[i].description;
					linkTrigger.triggerid = triggers[i].triggerid;

					$(tpl.evaluate(linkTrigger)).appendTo($table);
				}

				$table.find('.color-picker input').colorpicker();
				$('.color-picker input', this.domNode).change();
			},

			/**
			 * Updates links list for element.
			 *
			 * @param {string} selementids
			 */
			updateList: function(selementids) {
				const links = this.sysmap.getLinksBySelementIds(selementids),
					list = [];

				let	$link_table,
					row_tpl;

				$('.element-links').hide();
				$('.element-links tbody').empty();

				if (links.length) {
					$('#mapLinksContainer').show();

					if (objectSize(selementids) > 1) {
						row_tpl = 'massElementLinkTableRowTpl';
						$link_table = $('#mass-element-links');
					}
					else {
						row_tpl = 'elementLinkTableRowTpl';
						$link_table = $('#element-links');
					}

					row_tpl = new Template(document.getElementById(row_tpl).innerHTML);

					links.forEach((linkid) => {
						const link = this.sysmap.links[linkid].data;

						/*
						 * If one element selected and it's not link.selementid1, we need to swap link.selementid1
						 * and link.selementid2 in order that sorting works correctly.
						 */
						if (objectSize(selementids) == 1 && !selementids[link.selementid1]) {
							const selected = this.sysmap.selements[Object.keys(this.sysmap.selection.selements)[0]];

							if (selected.id !== link.selementid1) {
								[link.selementid1, link.selementid2] = [selected.id, link.selementid1];
							}
						}

						const linktriggers = Object.values(link.linktriggers).map((trigger) => trigger.desc_exp),
							fromElementName = this.sysmap.selements[link.selementid1].getName(),
							toElementName = this.sysmap.selements[link.selementid2].getName();

						list.push({fromElementName, toElementName, linkid: link.linkid, linktriggers});
					});

					// Sort by "From" element, then by "To" element and then by "linkid".
					list.sort((a, b) => a.fromElementName.toLowerCase().localeCompare(b.fromElementName.toLowerCase())
							|| a.toElementName.toLowerCase().localeCompare(b.toElementName.toLowerCase())
							|| a.linkid.localeCompare(b.linkid)
					);

					list.forEach((item) => {
						const row = $(row_tpl.evaluate(item)),
							row_urls = $('.element-urls', row);

						item.linktriggers.forEach((trigger, index) => {
							if (index != 0) {
								row_urls.append($('<br>'));
							}

							row_urls.append($('<span>').text(trigger));
						});

						row.appendTo($link_table.find('tbody'));
					});

					$link_table.closest('.element-links').show();
				}
				else {
					$('#mapLinksContainer').hide();
				}
			}
		};

		const sysmap = new CMap(containerid, mapData);

		Shape.prototype.bind('afterMove', function(e, element) {
			if (sysmap.selection.count.shapes == 1 && sysmap.selection.count.selements == 0
					&& sysmap.selection.shapes[element.id] !== undefined) {
				document.getElementById('shapeX').value = element.data.x;
				document.getElementById('shapeY').value = element.data.y;

				if (element.data.width !== undefined) {
					document.querySelector('#shapeForm input[name=width]').value = element.data.width;
				}
				if (element.data.height !== undefined) {
					document.querySelector('#shapeForm input[name=height]').value = element.data.height;
				}
			}

			sysmap.updateImage();
		});

		Selement.prototype.bind('afterMove', function(e, element) {
			if (sysmap.selection.count.selements == 1 && sysmap.selection.count.shapes == 0
					&& sysmap.selection.selements[element.id] !== undefined) {
				document.getElementById('x').value = element.data.x;
				document.getElementById('y').value = element.data.y;

				if (element.data.width !== undefined) {
					document.getElementById('areaSizeWidth').value = element.data.width;
				}
				if (element.data.height !== undefined) {
					document.getElementById('areaSizeHeight').value = element.data.height;
				}
			}

			if (sysmap.buffered_expand === false) {
				sysmap.updateImage();
			}
		});

		return sysmap;
	}

	return {
		object: null,
		run: function(containerid, mapData) {
			if (this.object !== null) {
				throw new Error('Map has already been run.');
			}

			this.object = createMap(containerid, mapData);
		}
	};
}(jQuery));

jQuery(function ($) {
	/*
	 * Reposition the overlay dialogue window. The previous position is remembered using offset(). Each time overlay
	 * dialogue is opened, it could have different content (shape form, element form etc) and different size, so the
	 * new top and left position must be calculated. If the overlay dialogue is opened for the first time, position is
	 * set depending on map size and canvas top position. This makes map more visible at first. In case popup window is
	 * dragged outside visible view port or window is resized, popup will again be repositioned so it doesn't go outside
	 * the viewport. In case the popup is too large, position it with a small margin depending on whether is too long
	 * or too wide.
	 */
	$.fn.positionOverlayDialogue = function () {
		var $map = $('#map-area'),
			map_offset = $map.offset(),
			map_margin = 10,
			$dialogue = $(this),
			$dialogue_host = $dialogue.offsetParent(),
			dialogue_host_offset = $dialogue_host.offset(),
			// Usable area relative to host.
			dialogue_host_x_min = $dialogue_host.scrollLeft(),
			dialogue_host_x_max = Math.min($dialogue_host[0].scrollWidth,
				$(window).width() + $(window).scrollLeft() - dialogue_host_offset.left + $dialogue_host.scrollLeft()
			) - 1,
			dialogue_host_y_min = $dialogue_host.scrollTop(),
			dialogue_host_y_max = Math.min($dialogue_host[0].scrollHeight,
				$(window).height() + $(window).scrollTop() - dialogue_host_offset.top + $dialogue_host.scrollTop()
			) - 1,
			// Coordinates of map's top right corner relative to dialogue host.
			pos_x = map_offset.left + $map[0].scrollWidth - dialogue_host_offset.left + $dialogue_host.scrollLeft(),
			pos_y = map_offset.top - map_margin - dialogue_host_offset.top + $dialogue_host.scrollTop();

		return this.css({
			left: Math.max(dialogue_host_x_min, Math.min(dialogue_host_x_max - $dialogue.outerWidth(), pos_x)),
			top: Math.max(dialogue_host_y_min, Math.min(dialogue_host_y_max - $dialogue.outerHeight(), pos_y))
		});
	};
});
