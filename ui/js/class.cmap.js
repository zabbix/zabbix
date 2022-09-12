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


ZABBIX.namespace('classes.Observer');

ZABBIX.classes.Observer = (function() {
	var Observer = function() {
		this.listeners = {};
	};

	Observer.prototype = {
		constructor: ZABBIX.classes.Observer,

		bind: function(event, callback) {
			var i;

			if (typeof callback === 'function') {
				event = ('' + event).toLowerCase().split(/\s+/);

				for (i = 0; i < event.length; i++) {
					if (this.listeners[event[i]] === void(0)) {
						this.listeners[event[i]] = [];
					}

					this.listeners[event[i]].push(callback);
				}
			}

			return this;
		},

		trigger: function(event, target) {
			event = event.toLowerCase();

			var handlers = this.listeners[event] || [],
				i;

			if (handlers.length) {
				event = jQuery.Event(event);

				for (i = 0; i < handlers.length; i++) {
					try {
						if (handlers[i](event, target) === false || event.isDefaultPrevented()) {
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
		var i;

		for (i in Observer.prototype) {
			if (Observer.prototype.hasOwnProperty(i) && typeof Observer.prototype[i] === 'function') {
				object[i] = Observer.prototype[i];
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

	function createMap(containerId, mapData) {
		var CMap = function(containerId, mapData) {
			var selementid,
				shapeid,
				linkid;

			this.reupdateImage = false; // if image should be updated again after last update is finished
			this.imageUpdating = false; // if ajax request for image updating is processing
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
			this.currentLinkId = '0'; // linkid of currently edited link
			this.allLinkTriggerIds = {};
			this.sysmapid = mapData.sysmap.sysmapid;
			this.data = mapData.sysmap;
			this.background = null;
			this.iconList = mapData.iconList;
			this.defaultAutoIconId = mapData.defaultAutoIconId;
			this.defaultIconId = mapData.defaultIconId;
			this.defaultIconName = mapData.defaultIconName;
			this.container = $('#' + containerId);

			if (this.container.length === 0) {
				this.container = $(document.body);
			}

			this.images = {};
			Object.keys(this.iconList).forEach(function (id) {
				var item = this.iconList[id];
				this.images[item.imageid] = item;
			}, this);

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
				width: this.data.width + 'px',
				height: this.data.height + 'px',
				overflow: 'hidden'
			});

			this.container.css('position', 'relative');
			this.base64image = true;
			$('#sysmap_img').remove();

			for (selementid in this.data.selements) {
				if (this.data.selements.hasOwnProperty(selementid)) {
					this.selements[selementid] = new Selement(this, this.data.selements[selementid]);
				}
			}

			var shapes = [];

			for (shapeid in this.data.shapes) {
				if (this.data.shapes.hasOwnProperty(shapeid)) {
					shapes.push(this.data.shapes[shapeid]);
				}
			}

			shapes = shapes.sort(function (a,b) {
				return a.zindex - b.zindex;
			});

			shapes.forEach(function (shape) {
				this.shapes[shape.sysmap_shapeid] = new Shape(this, shape);
			}, this);

			for (linkid in this.data.links) {
				if (this.data.selements.hasOwnProperty(selementid)) {
					this.links[linkid] = new Link(this, this.data.links[linkid]);
				}
			}

			// create container for forms
			this.formContainer = $('<div>', {
					id: 'map-window',
					class: 'overlay-dialogue',
					style: 'display: none; top: 0; left: 0;'
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
			this.bindActions();

			// initialize selectable
			this.container.selectable({
				start: $.proxy(function(event) {
					if (!event.ctrlKey && !event.metaKey) {
						this.clearSelection();
					}
				}, this),
				stop: $.proxy(function(event) {
					var selected = this.container.children('.ui-selected'),
						ids = [],
						i,
						ln;

					for (i = 0, ln = selected.length; i < ln; i++) {
						ids.push({
							id: $(selected[i]).data('id'),
							type: $(selected[i]).data('type')
						});

						// remove ui-selected class, to not confuse next selection
						selected.removeClass('ui-selected');
					}

					this.selectElements(ids, event.ctrlKey || event.metaKey);
				}, this)
			});
		};

		CMap.LABEL_TYPE_LABEL	= 0; // MAP_LABEL_TYPE_LABEL
		CMap.LABEL_TYPE_IP		= 1; // MAP_LABEL_TYPE_IP
		CMap.LABEL_TYPE_NAME	= 2; // MAP_LABEL_TYPE_NAME
		CMap.LABEL_TYPE_STATUS	= 3; // MAP_LABEL_TYPE_STATUS
		CMap.LABEL_TYPE_NOTHING	= 4; // MAP_LABEL_TYPE_NOTHING
		CMap.LABEL_TYPE_CUSTOM	= 5; // MAP_LABEL_TYPE_CUSTOM

		CMap.prototype = {
			copypaste_buffer: [],
			buffered_expand: false,
			expand_sources: [],

			save: function() {
				var url = new Curl();

				$.ajax({
					url: url.getPath() + '?output=ajax&sid=' + url.getArgument('sid'),
					type: 'post',
					data: {
						favobj: 'sysmap',
						action: 'update',
						sysmapid: this.sysmapid,
						sysmap: JSON.stringify(this.data)
					},
					error: function() {
						throw new Error('Cannot update map.');
					}
				});
			},

			setExpandedLabels: function (elements, labels) {
				for (var i = 0, ln = elements.length; i < ln; i++) {
					if (labels !== null) {
						elements[i].expanded = labels[i];
					}
					else {
						elements[i].expanded = null;
					}
				}

				this.updateImage();

				if (labels === null) {
					alert(t('S_MACRO_EXPAND_ERROR'));
				}
			},

			expandMacros: function(source) {
				var url = new Curl();

				if (source !== null) {
					if (/\{.+\}/.test(source.getLabel(false))) {
						this.expand_sources.push(source);
					}
					else {
						source.expanded = null;
					}
				}

				if (this.buffered_expand === false && this.expand_sources.length > 0) {
					var sources = this.expand_sources,
						post = [],
						map = this;

					this.expand_sources = [];

					for (var i = 0, ln = sources.length; i < ln; i++) {
						post.push(sources[i].data);
					}

					$.ajax({
						url: url.getPath() + '?output=ajax&sid=' + url.getArgument('sid'),
						type: 'post',
						dataType: 'html',
						data: {
							favobj: 'sysmap',
							action: 'expand',
							sysmapid: this.sysmapid,
							source: JSON.stringify(post)
						},
						success: function(data) {
							try {
								data = JSON.parse(data);
							}
							catch (e) {
								data = null;
							}

							map.setExpandedLabels(sources, data);
						},
						error: function() {
							map.setExpandedLabels(sources, null);
						}
					});
				}
				else if (this.buffered_expand === false) {
					this.updateImage();
				}
			},

			updateImage: function() {
				var shapes = [],
					links = [],
					elements = [],
					grid_size = (this.data.grid_show === '1') ? parseInt(this.data.grid_size, 10) : 0;

				if (grid_size !== this.data.last_grid_size) {
					this.map.setGrid(grid_size);
					this.data.last_grid_size = grid_size;
				}

				Object.keys(this.selements).forEach(function(key) {
					var element = {},
						data = this.selements[key].data;

					['selementid', 'x', 'y', 'label_location'].forEach(function (name) {
						element[name] = data[name];
					}, this);

					element['label'] = this.selements[key].getLabel();

					// host group elements
					if (data.elementtype === '3' && data.elementsubtype === '1') {
						element.width = (data.areatype === '0') ? this.data.width : data.width;
						element.height = (data.areatype === '0') ? this.data.height : data.height;
					}

					if ((data.use_iconmap === '1' && this.data.iconmapid !== '0')
							&& (data.elementtype === '0'
								|| (data.elementtype === '3' && data.elementsubtype === '1'))) {
						element.icon = this.defaultAutoIconId;
					}
					else {
						element.icon = data.iconid_off;
					}

					elements.push(element);
				}, this);

				Object.keys(this.links).forEach(function(key) {
					var link = {};
					['linkid', 'selementid1', 'selementid2', 'drawtype', 'color'].forEach(function (name) {
						link[name] = this.links[key].data[name];
					}, this);

					link['label'] = this.links[key].getLabel();
					links.push(link);
				}, this);

				Object.keys(this.shapes).forEach(function(key) {
					var shape = {};
					Object.keys(this.shapes[key].data).forEach(function (name) {
						shape[name] = this.shapes[key].data[name];
					}, this);

					shape['text'] = this.shapes[key].getLabel();

					if (this.data.expand_macros === '1' && typeof(shape['text']) === 'string' && shape['text'] !== '') {
						// Additional macro that is supported in shapes is {MAP.NAME}
						shape['text'] = shape['text'].replace(/\{MAP\.NAME\}/g, this.data.name);
					}

					shapes.push(shape);
				}, this);

				this.map.update({
					'background': this.data.backgroundid,
					'elements': elements,
					'links': links,
					'shapes': shapes,
					'label_location': this.data.label_location
				});
			},

			// elements
			deleteSelectedElements: function() {
				var selementid;

				if (this.selection.count.selements && confirm(t('S_DELETE_SELECTED_ELEMENTS_Q'))) {
					for (selementid in this.selection.selements) {
						this.selements[selementid].remove();
						this.removeLinksBySelementId(selementid);
					}

					this.toggleForm();
					this.updateImage();
				}
			},

			// shapes
			deleteSelectedShapes: function() {
				var shapeid;

				if (this.selection.count.shapes && confirm(t('S_DELETE_SELECTED_SHAPES_Q'))) {
					for (shapeid in this.selection.shapes) {
						this.shapes[shapeid].remove();
					}

					this.toggleForm();
					this.updateImage();
				}
			},

			removeLinksBySelementId: function(selementid) {
				var selementIds = {},
					linkids,
					i,
					ln;

				selementIds[selementid] = selementid;
				linkids = this.getLinksBySelementIds(selementIds);

				for (i = 0, ln = linkids.length; i < ln; i++) {
					this.links[linkids[i]].remove();
				}
			},

			/**
			 * Returns the links between the given elements.
			 *
			 * @param selementIds
			 *
			 * @return {Array} an array of link ids
			 */
			getLinksBySelementIds: function(selementIds) {
				var linkIds = [],
					link,
					linkid;

				for (linkid in this.data.links) {
					link = this.data.links[linkid];

					if (!!selementIds[link.selementid1] && !!selementIds[link.selementid2]
							|| (objectSize(selementIds) === 1 && (!!selementIds[link.selementid1] || !!selementIds[link.selementid2]))) {
						linkIds.push(linkid);
					}
				}

				return linkIds;
			},

			bindActions: function() {
				var that = this;

				/*
				 * Map panel events
				 */
				// toggle expand macros
				$('#expand_macros').click(function() {
					that.data.expand_macros = (that.data.expand_macros === '1') ? '0' : '1';
					$(this).html((that.data.expand_macros === '1') ? t('S_ON') : t('S_OFF'));
					that.updateImage();
				});

				// change grid size
				$('#gridsize').change(function() {
					var value = $(this).val();

					if (that.data.grid_size !== value) {
						that.data.grid_size = value;
						that.updateImage();
					}
				});

				// toggle autoalign
				$('#gridautoalign').click(function() {
					that.data.grid_align = (that.data.grid_align === '1') ? '0' : '1';
					$(this).html((that.data.grid_align === '1') ? t('S_ON') : t('S_OFF'));
				});

				// toggle grid visibility
				$('#gridshow').click(function() {
					that.data.grid_show = (that.data.grid_show === '1') ? '0' : '1';
					$(this).html((that.data.grid_show === '1') ? t('S_SHOWN') : t('S_HIDDEN'));
					that.updateImage();
				});

				// perform align all
				$('#gridalignall').click(function() {
					var selementid;

					for (selementid in that.selements) {
						that.selements[selementid].align(true);
					}

					that.updateImage();
				});

				// save map
				$('#sysmap_update').click(function() {
					that.save();
				});

				// add element
				$('#selementAdd').click(function() {
					if (typeof(that.iconList[0]) === 'undefined') {
						alert(t('S_NO_IMAGES'));

						return;
					}

					var selement = new Selement(that);

					that.selements[selement.id] = selement;
					that.updateImage();
				});

				// remove element
				$('#selementRemove').click($.proxy(this.deleteSelectedElements, this));

				// add shape
				$('#shapeAdd').click(function() {
					var shape = new Shape(that);

					that.shapes[shape.id] = shape;
					that.updateImage();
				});

				// remove shapes
				$('#shapeRemove, #shapesRemove, #shapeMassRemove').click($.proxy(this.deleteSelectedShapes, this));

				// add link
				$('#linkAdd').click(function() {
					var link;

					if (that.selection.count.selements !== 2) {
						alert(t('S_TWO_MAP_ELEMENTS_SHOULD_BE_SELECTED'));

						return false;
					}

					link = new Link(that);
					that.links[link.id] = link;
					that.updateImage();
					that.linkForm.updateList(that.selection.selements);
				});

				// removes all of the links between the selected elements
				$('#linkRemove').click(function() {
					var linkids;

					if (that.selection.count.selements !== 2) {
						alert(t('S_PLEASE_SELECT_TWO_ELEMENTS'));

						return false;
					}

					linkids = that.getLinksBySelementIds(that.selection.selements);

					if (linkids.length && confirm(t('S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'))) {
						for (var i = 0, ln = linkids.length; i < ln; i++) {
							that.links[linkids[i]].remove();
						}

						that.linkForm.hide();
						that.linkForm.updateList({});
						that.updateImage();
					}
				});

				/*
				 * Selements events
				 */
				// Delegate selements icons clicks.
				$(this.container).on('click', '.sysmap_element, .sysmap_shape', function(event) {
					that.selectElements([{
						id: $(this).attr('data-id'),
						type: $(this).attr('data-type')
					}], event.ctrlKey || event.metaKey);
				});

				$(this.container).on('contextmenu', function(event) {
					var target = $(event.target),
						item_data = {
							id: target.attr('data-id'),
							type: target.attr('data-type')
						},
						can_copy = false,
						can_paste = ('items' in that.copypaste_buffer && that.copypaste_buffer.items.length > 0),
						can_remove = false,
						can_reorder = false;

					if (typeof item_data.id === 'undefined') {
						that.clearSelection();
					}
					else if (item_data.type && typeof that.selection[item_data.type][item_data.id] === 'undefined') {
						that.selectElements([item_data], false, true);
					}

					can_copy = (that.selection.count.shapes > 0 || that.selection.count.selements > 0);
					can_remove = can_copy;
					can_reorder = (that.selection.count.shapes > 0);

					event.preventDefault();
					event.stopPropagation();

					const overlay = overlays_stack.end();

					if (typeof overlay !== 'undefined' && 'element' in overlay && overlay.element !== event.target) {
						$('.menu-popup-top').menuPopup('close', null, false);
					}

					if (!(can_copy || can_paste || can_remove || can_reorder)) {
						return false;
					}

					var items = [
						{
							'items': [
								{
									label: t('S_BRING_TO_FRONT'),
									disabled: !can_reorder,
									clickCallback: function() {
										that.reorderShapes(that.selection.shapes, 'last');
									}
								},
								{
									label: t('S_BRING_FORWARD'),
									disabled: !can_reorder,
									clickCallback: function() {
										that.reorderShapes(that.selection.shapes, 'next');
									}
								},
								{
									label: t('S_SEND_BACKWARD'),
									disabled: !can_reorder,
									clickCallback: function() {
										that.reorderShapes(that.selection.shapes, 'previous');
									}
								},
								{
									label: t('S_SEND_TO_BACK'),
									disabled: !can_reorder,
									clickCallback: function() {
										that.reorderShapes(that.selection.shapes, 'first');
									}
								}
							]
						},
						{
							'items': [
								{
									label: t('S_COPY'),
									disabled: !can_copy,
									clickCallback: function() {
										that.copypaste_buffer = that.getSelectionBuffer(that);
									}
								},
								{
									label: t('S_PASTE'),
									disabled: !can_paste,
									clickCallback: function() {
										var offset = $(that.container).offset(),
											delta_x = event.pageX - offset.left - that.copypaste_buffer.left,
											delta_y = event.pageY - offset.top - that.copypaste_buffer.top,
											selectedids;

										delta_x = Math.min(delta_x,
											parseInt(that.data.width, 10) - that.copypaste_buffer.right
										);
										delta_y = Math.min(delta_y,
											parseInt(that.data.height, 10) - that.copypaste_buffer.bottom
										);
										selectedids = that.pasteSelectionBuffer(delta_x, delta_y, that, true);
										that.selectElements(selectedids, false);
										that.updateImage();
										that.linkForm.updateList(that.selection.selements);
									}
								},
								{
									label: t('S_PASTE_SIMPLE'),
									disabled: !can_paste,
									clickCallback: function() {
										var offset = $(that.container).offset(),
											delta_x = event.pageX - offset.left - that.copypaste_buffer.left,
											delta_y = event.pageY - offset.top - that.copypaste_buffer.top,
											selectedids;

										delta_x = Math.min(delta_x,
											parseInt(that.data.width, 10) - that.copypaste_buffer.right
										);
										delta_y = Math.min(delta_y,
											parseInt(that.data.height, 10) - that.copypaste_buffer.bottom
										);
										selectedids = that.pasteSelectionBuffer(delta_x, delta_y, that, false);
										that.selectElements(selectedids, false);
										that.updateImage();
										that.linkForm.updateList(that.selection.selements);
									}
								},
								{
									label: t('S_REMOVE'),
									disabled: !can_remove,
									clickCallback: function() {
										if (that.selection.count.selements || that.selection.count.shapes) {
											for (selementid in that.selection.selements) {
												that.selements[selementid].remove();
												that.removeLinksBySelementId(selementid);
											}

											for (shapeid in that.selection.shapes) {
												that.shapes[shapeid].remove();
											}

										}

										that.toggleForm();
										that.updateImage();
									}
								}
							]
						}
					];

					$(event.target).menuPopup(items, event, {
						position: {
							of: event,
							my: 'left top',
							at: 'left bottom',
							using: (pos, data) => {
								let max_left = (data.horizontal === 'left')
									? document.getElementById(containerId).clientWidth
									: document.getElementById(containerId).clientWidth - data.element.width;

								pos.top = Math.max(0, pos.top);
								pos.left = Math.max(0, Math.min(max_left, pos.left));

								data.element.element[0].style.top = `${pos.top}px`;
								data.element.element[0].style.left = `${pos.left}px`;
							}
						},
						background_layer: false
					});
				});
				/*
				 * Form events
				 */
				$('#elementType').change(function() {
					var obj = $(this);

					switch (obj.val()) {
						// host
						case '0':
							$('#elementNameHost').multiSelect('clean');
							$('#triggerContainer tbody').html('');
							break;

						// triggers
						case '2':
							$('#elementNameTriggers').multiSelect('clean');
							$('#triggerContainer tbody').html('');
							break;

						// host group
						case '3':
							$('#elementNameHostGroup').multiSelect('clean');
							$('#triggerContainer tbody').html('');
							break;

						// others types
						default:
							$('input[name=elementName]').val('');
							$('#triggerContainer tbody').html('');
					}
				});

				$('#elementClose').click(function() {
					that.clearSelection();
					that.toggleForm();
				});

				$('#elementRemove').click($.proxy(this.deleteSelectedElements, this));

				$('#elementApply').click($.proxy(function() {
					if (this.selection.count.selements !== 1) {
						throw 'Try to single update element, when more than one selected.';
					}

					var values = this.form.getValues();

					if (values) {
						for (var selementid in this.selection.selements) {
							this.selements[selementid].update(values, true);
						}
					}
				}, this));

				$('#shapeApply').click($.proxy(function() {
					if (this.selection.count.shapes !== 1) {
						throw 'Trying to single update shape, when more than one selected.';
					}

					var values = this.shapeForm.getValues();

					if (values) {
						for (var id in this.selection.shapes) {
							this.shapes[id].update(values);
						}
					}
				}, this));

				$('#shapeClose, #shapeMassClose').click(function() {
					that.clearSelection();
					that.toggleForm();
				});

				$('#shapeMassApply').click($.proxy(function() {
					var values = this.massShapeForm.getValues();

					if (values) {
						this.buffered_expand = true;

						for (var shapeid in this.selection.shapes) {
							this.shapes[shapeid].update(values);
						}

						this.buffered_expand = false;
						this.expandMacros(null);
					}
				}, this));

				$('#newSelementUrl').click($.proxy(function() {
					this.form.addUrls();
				}, this));

				$('#newSelementTriggers').click($.proxy(function() {
					this.form.addTriggers();
				}, this));

				$('#x, #y', this.form.domNode).change(function() {
					var value = parseInt(this.value, 10);

					this.value = isNaN(value) || (value < 0) ? 0 : value;
				});

				$('#areaSizeWidth, #areaSizeHeight', this.form.domNode).change(function() {
					var value = parseInt(this.value, 10);

					this.value = isNaN(value) || (value < 10) ? 10 : value;
				});

				// application selection pop up
				$('#application-select').click(function(event) {
					var popup_options = {
							srctbl: 'applications',
							srcfld1: 'name',
							dstfrm: 'selementForm',
							dstfld1: 'application',
							real_hosts: '1',
							with_applications: '1'
						};

					if ($('#elementType').val() == '3') {
						popup_options = jQuery.extend(popup_options,
							getFirstMultiselectValue('elementNameHost', 'elementNameHostGroup')
						);
					}

					PopUp('popup.generic', popup_options, null, event.target);
				});

				// mass update form
				$('#massClose').click(function() {
					that.clearSelection();
					that.toggleForm();
				});

				$('#massRemove').click($.proxy(this.deleteSelectedElements, this));

				$('#massApply').click($.proxy(function() {
					var values = this.massForm.getValues();

					if (values) {
						this.buffered_expand = true;

						for (var selementid in this.selection.selements) {
							this.selements[selementid].update(values);
						}

						this.buffered_expand = false;
						this.expandMacros(null);
					}
				}, this));

				// open link form
				$('.element-links').on('click', '.openlink', function() {
					that.currentLinkId = $(this).attr('data-linkid');

					var linkData = that.links[that.currentLinkId].getData();

					that.linkForm.setValues(linkData);
					that.linkForm.show();
				});

				// link form
				$('#formLinkRemove').click(function() {
					that.links[that.currentLinkId].remove();
					that.linkForm.updateList(that.selection.selements);
					that.linkForm.hide();
					that.updateImage();
				});

				$('#formLinkApply').click(function() {
					try {
						var linkData = that.linkForm.getValues();
						that.links[that.currentLinkId].update(linkData);
						that.linkForm.updateList(that.selection.selements);
					}
					catch (err) {
						alert(err);
					}
				});

				$('#formLinkClose').click(function() {
					that.linkForm.hide();
				});

				this.linkForm.domNode.on('click', '.triggerRemove', function() {
					var triggerid,
						tid = $(this).attr('data-linktriggerid').toString();

					$('#linktrigger_' + tid).remove();

					for (triggerid in that.linkForm.triggerids) {
						if (that.linkForm.triggerids[triggerid] === tid) {
							delete that.linkForm.triggerids[triggerid];
						}
					}
				});

				$('#border_type').on('change', function() {
					$(this).parent().find('input').prop("disabled", this.value === '0');
				});

				$('#mass_border_type, #chkboxBorderType').on('change', function() {
					var disable = ($('#mass_border_type').val() === '0' && $('#chkboxBorderType').is(":checked"));

					$('#chkboxBorderWidth, #chkboxBorderColor').prop("disabled", disable);
					$('#mass_border_width').prop("disabled", disable || !$('#chkboxBorderWidth').is(":checked"));
					$('#mass_border_color').prop("disabled", disable || !$('#chkboxBorderColor').is(":checked"));
				});

				$('#shapeForm input[type=radio][name=type]').on('change', function() {
					var value = parseInt(this.value, 10),
						last_value = parseInt($('#shapeForm #last_shape_type').val(), 10);

					$('#shape-text-row, #shape-background-row').toggle(value !== SVGMapShape.TYPE_LINE);
					$('.switchable-content').each(function (i, element) {
						element.textContent = element.hasAttribute('data-value-' + value) ?
								element.getAttribute('data-value-' + value) :
								element.getAttribute('data-value');
					});

					if ((last_value === SVGMapShape.TYPE_LINE) !== (value === SVGMapShape.TYPE_LINE)) {
						var x = parseInt($('#shapeX').val(), 10),
							y = parseInt($('#shapeY').val(), 10),
							width = parseInt($('#shapeAreaSizeWidth').val(), 10),
							height = parseInt($('#shapeAreaSizeHeight').val(), 10);

						if (value === SVGMapShape.TYPE_LINE) {
							// Switching from figures to line.
							$('#shapeAreaSizeWidth').val(x + width);
							$('#shapeAreaSizeHeight').val(y + height);
						}
						else {
							// Switching from line to figures.
							var mx = Math.min(x, width),
								my = Math.min(y, height);

							$('#shapeX').val(mx);
							$('#shapeY').val(my);
							$('#shapeAreaSizeWidth').val(Math.max(x, width) - mx);
							$('#shapeAreaSizeHeight').val(Math.max(y, height) - my);
						}
					}

					$('#last_shape_type').val(value);
				});

				$(this.container).parents('.sysmap-scroll-container').eq(0)
					.on('scroll', (e) => {
						if (!e.target.dataset.last_scroll_at || Date.now() - e.target.dataset.last_scroll_at > 1000) {
							$('.menu-popup-top').menuPopup('close', null, false);

							e.target.dataset.last_scroll_at = Date.now();
						}
					});

				$('input[type=radio][name=type]:checked').change();
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
			 * @param {int} delta_x						Shift between old and new x position.
			 * @param {int} delta_y						Shift between old and new y position.
			 *
			 * @return {object}							Object of elements with recalculated positions.
			 */
			dragGroupRecalculate: function(cmap, delta_x, delta_y) {
				var dragged = cmap.draggable_buffer;

				dragged.items.forEach(function(item) {
					var node = cmap[item.type][item.id];

					if ('updatePosition' in node) {
						var dimensions = node.getDimensions();

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
			 * @param {object} draggable				Draggable DOM element where drag event was started.
			 */
			dragGroupInit: function(draggable) {
				var buffer,
					draggable_node = $(draggable.domNode),
					dimensions = draggable.getDimensions();

				if (draggable.selected) {
					buffer = draggable.sysmap.getSelectionBuffer(draggable.sysmap);
				}
				else {
					draggable_node = $(draggable.domNode);
					// Create getSelectionBuffer structure if drag event was started on unselected element.
					buffer = {
						items: [{
							type: draggable_node.attr('data-type'),
							id: draggable.id
						}],
						left: dimensions.x,
						right: dimensions.x + draggable_node.width(),
						top: dimensions.y,
						bottom: dimensions.y + draggable_node.height()
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
			 * @param {object} data						jQuery UI draggable data.
			 * @param {object} draggable				Element where drag event occurred.
			 */
			dragGroupDrag: function(data, draggable) {
				var cmap = draggable.sysmap,
					dimensions = draggable.getDimensions(),
					delta_x = data.position.left - dimensions.x,
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
			 * @param {object} draggable				Element where drag stop event occurred.
			 */
			dragGroupStop: function(draggable) {
				var cmap = draggable.sysmap,
					should_align = (cmap.data.grid_align === '1');

				if (should_align) {
					cmap.draggable_buffer.items.forEach(function(item) {
						var node = cmap[item.type][item.id];

						if ('updatePosition' in node) {
							var dimensions = node.getDimensions();

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
			 * @param	{number}	delta_x					Shift between desired and actual x position.
			 * @param	{number}	delta_y					Shift between desired and actual y position.
			 * @param	{object}	that					CMap object
			 * @param	{bool}		keep_external_links		Should be links to non selected elements copied or not.
			 *
			 * @return	{array}
			 */
			pasteSelectionBuffer: function(delta_x, delta_y, that, keep_external_links) {
				var selectedids = [],
					source_cloneids = {};

				that.copypaste_buffer.items.forEach(function(element_data) {
					var data = $.extend({}, element_data.data, false),
						type = element_data.type,
						element;

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
						selectedids.push({
							id: element.id,
							type: type
						});
						source_cloneids[element_data.id] = element.id;

						if (that.data.grid_align === '1') {
							element.align(true);
						}
					}
				});

				var link,
					fromid,
					toid,
					data;

				that.copypaste_buffer.links.forEach(function(link_data) {
					data = $.extend({}, link_data.data, false);

					if (!keep_external_links && (data.selementid1 in source_cloneids === false
							|| data.selementid2 in source_cloneids === false)) {
						return;
					}

					link = new Link(that);
					delete data.linkid;
					fromid = (data.selementid1 in source_cloneids)
						? source_cloneids[data.selementid1]
						: data.selementid1;
					toid = (data.selementid2 in source_cloneids) ? source_cloneids[data.selementid2] : data.selementid2;
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
			 * @param  {object}	that		CMap object
			 *
			 * @return {object}
			 */
			getSelectionBuffer: function(that) {
				var items = [],
					left = null,
					top = null,
					right = null,
					bottom = null;

				for (var type in that.selection) {
					if (type in that === false || typeof that[type] !== 'object') {
						continue;
					}

					var data,
						dimensions,
						dom_node,
						x,
						y;

					for (var id in that.selection[type]) {
						if ('getData' in that[type][id] === false) {
							continue;
						}

						// Get current data without observers.
						data = $.extend({}, that[type][id].getData(), false);
						dimensions = that[type][id].getDimensions();
						dom_node = that[type][id].domNode;
						x = dimensions.x;
						y = dimensions.y;
						left = Math.min(x, (left === null) ? x : left);
						top = Math.min(y, (top === null) ? y : top);
						right = Math.max(x + dom_node.outerWidth(true), (right === null) ? 0 : right);
						bottom = Math.max(y + dom_node.outerHeight(true), (bottom === null) ? 0 : bottom);
						items.push({
							id: id,
							type: type,
							data: data
						});
					}
				}

				// Sort items array according to item.data.zindex value.
				items = items.sort(function(a, b) {
					var aindex = parseInt(a.data.zindex, 10) || 0,
						bindex = parseInt(b.data.zindex, 10) || 0;

					return aindex - bindex;
				});

				var links = [];

				for (var id in that.links) {
					// Get current data without observers.
					var data = $.extend({}, that.links[id].getData(), false);

					if (data.selementid1 in that.selection.selements || data.selementid2 in that.selection.selements) {
						links.push({
							id: id,
							data: data
						})
					}
				}

				return {
					items: items,
					links: links,
					top: top,
					left: left,
					right: right,
					bottom: bottom
				};
			},

			clearSelection: function() {
				var id;

				['selements', 'shapes'].forEach(function (type) {
					for (id in this.selection[type]) {
						this.selection.count[type]--;
						this[type][id].toggleSelect(false);
						delete this.selection[type][id];
					}
				}, this);

				// Clean trigger selement.
				if ($('#elementType').val() == 2) {
					$('#elementNameTriggers').multiSelect('clean');
					$('#triggerContainer tbody').html('');
				}
			},

			reorderShapes: function(ids, position) {
				var shapes = [],
					target,
					temp,
					ignore = [],
					selection = [];

				Object.keys(this.shapes).forEach(function(key) {
					shapes.push(this.shapes[key]);
				}, this);

				shapes = shapes.sort(function (a,b) {
					return a.data.zindex - b.data.zindex;
				});

				shapes.forEach(function(value, index) {
					if (typeof ids[value.id] !== 'undefined') {
						selection.push(index);
					}
				});

				// All shapes are selected, no need to update order.
				if (shapes.length === selection.length)
				{
					return;
				}

				switch (position.toLowerCase()) {
					case 'first':
						target = [];

						for (var i = selection.length - 1; i >= 0; i--) {
							target.unshift(shapes.splice(selection[i], 1)[0]);
						}

						for (var i = 0; i < target.length; i++) {
							$(target[i].domNode).insertBefore(shapes[0].domNode);
						}

						shapes = target.concat(shapes);

						shapes.forEach(function(shape, index) {
							shape.data.zindex = index;
						});
						break;

					case 'last':
						target = [];

						for (var i = selection.length - 1; i >= 0; i--) {
							target.unshift(shapes.splice(selection[i], 1)[0]);
						}

						for (var i = target.length - 1; i >= 0 ; i--) {
							$(target[i].domNode).insertAfter(shapes[shapes.length-1].domNode);
						}

						shapes = shapes.concat(target);

						shapes.forEach(function(shape, index) {
							shape.data.zindex = index;
						});
						break;

					case 'next':
						ignore.push(shapes.length - 1);

						for (var i = selection.length - 1; i >= 0; i--) {
							target = selection[i];

							// No need to update.
							if (ignore.indexOf(target) !== -1) {
								ignore.push(target - 1);
								continue;
							}

							$(shapes[target].domNode).insertAfter(shapes[target + 1].domNode);
							shapes[target + 1].data.zindex--;
							shapes[target].data.zindex++;

							temp = shapes[target + 1];
							shapes[target + 1] = shapes[target];
							shapes[target] = temp;
						}
						break;

					case 'previous':
						ignore.push(0);

						for (var i = 0; i < selection.length; i++) {
							target = selection[i];

							// No need to update.
							if (ignore.indexOf(target) !== -1) {
								ignore.push(target + 1);
								continue;
							}

							$(shapes[target].domNode).insertBefore(shapes[target - 1].domNode);
							shapes[target - 1].data.zindex++;
							shapes[target].data.zindex--;

							temp = shapes[target - 1];
							shapes[target - 1] = shapes[target];
							shapes[target] = temp;
						}
						break;
				}

				this.map.invalidate('shapes');
				this.updateImage();
			},

			selectElements: function(ids, addSelection, prevent_form_open) {
				var i, ln;

				$('.menu-popup-top').menuPopup('close', null, false);

				if (!addSelection) {
					this.clearSelection();
				}

				for (i = 0, ln = ids.length; i < ln; i++) {
					var id = ids[i].id,
						type = ids[i].type;

					if (typeof id === 'undefined' || typeof type === 'undefined') {
						continue;
					}

					if (this[type][id].toggleSelect()) {
						this.selection.count[type]++;
						this.selection[type][id] = id;
					}
					else {
						this.selection.count[type]--;
						delete this.selection[type][id];
					}
				}

				if (typeof prevent_form_open === 'undefined' || !prevent_form_open) {
					this.toggleForm();
				}
			},

			toggleForm: function() {
				var id;

				this.shapeForm.hide();
				this.linkForm.hide();

				if (this.selection.count.selements + this.selection.count.shapes === 0
						|| (this.selection.count.selements > 0 && this.selection.count.shapes > 0)) {
					$('#map-window').hide();
				}
				else {
					this.linkForm.updateList(this.selection.selements);

					// only one element selected
					if (this.selection.count.selements === 1) {
						for (id in this.selection.selements) {
							this.form.setValues(this.selements[id].getData());
						}

						this.massForm.hide();
						this.massShapeForm.hide();

						$('#link-connect-to').show();
						this.form.show();
					}

					// only one shape is selected
					else if (this.selection.count.shapes === 1) {
						this.form.hide();
						this.massForm.hide();
						this.massShapeForm.hide();

						for (id in this.selection.shapes) {
							this.shapeForm.setValues(this.shapes[id].getData());
						}

						this.shapeForm.show();
					}

					// multiple elements selected
					else if (this.selection.count.selements > 1) {
						this.form.hide();
						this.massShapeForm.hide();
						$('#link-connect-to').hide();
						this.massForm.show();
					}

					// multiple shapes selected
					else {
						var figures = null;
						for (id in this.selection.shapes) {
							if (figures === null) {
								figures = (this.shapes[id].data.type != SVGMapShape.TYPE_LINE);
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
		 * @property {object} sysmap		Reference to Map object.
		 * @property {object} data			Link db values.
		 * @property {string} id			Link ID (linkid).
		 *
		 * @param {object} sysmap			Map object.
		 * @param {object} linkData			Link data from DB.
		 */
		function Link(sysmap, linkData) {
			var selementid;

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

				for (selementid in this.sysmap.selection.selements) {
					if (linkData.selementid1 === null) {
						linkData.selementid1 = selementid;
					}
					else {
						linkData.selementid2 = selementid;
					}
				}

				// generate unique linkid
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

			for (var linktrigger in this.data.linktriggers) {
				this.sysmap.allLinkTriggerIds[linktrigger.triggerid] = true;
			}

			// assign by reference
			this.sysmap.data.links[this.id] = this.data;
		}

		Link.prototype = {
			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean} return label with expanded macros.
			 *
			 * @returns {string}
			 */
			getLabel: function (expand) {
				var label = this.data.label;

				if (typeof(expand) === 'undefined') {
					expand = true;
				}

				if (expand && typeof(this.expanded) === 'string' && this.sysmap.data.expand_macros === '1') {
					label = this.expanded;
				}

				return label;
			},

			/**
			 * Updades values in property data.
			 *
			 * @param {object} data
			 */
			update: function(data) {
				var key,
					invalidate = (this.data.label !== data.label);

				for (key in data) {
					this.data[key] = data[key];
				}

				if (invalidate) {
					sysmap.expandMacros(this);
				}
				else {
					sysmap.updateImage();
				}
			},

			/**
			 * Removes Link object, delete all reference to it.
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
			 * @returns {Object}
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
		 * @property {object} sysmap	Reference to Map object
		 * @property {object} data		Shape values from DB.
		 * @property {string} id		Shape ID (shapeid).
		 *
		 * @param {object} sysmap Map object
		 * @param {object} [shape_data] shape data from db
		 */
		function Shape(sysmap, shape_data) {
			var default_data = {
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
				border_type: 1
			};

			this.sysmap = sysmap;

			if (!shape_data) {
				shape_data = default_data;

				// generate unique sysmap_shapeid
				shape_data.sysmap_shapeid = getUniqueId();
				shape_data.zindex = Object.keys(sysmap.shapes).length;
			}
			else {
				for (var field in default_data) {
					if (typeof shape_data[field] === 'undefined') {
						shape_data[field] = default_data[field];
					}
				}
			}

			this.data = shape_data;
			this.id = this.data.sysmap_shapeid;
			this.expanded = this.data.expanded;
			delete this.data.expanded;

			// assign by reference
			this.sysmap.data.shapes[this.id] = this.data;

			// create dom
			this.domNode = $('<div>', {
					style: 'position: absolute; z-index: 1;\
						background: url("data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7") 0 0 repeat',
				})
				.appendTo(this.sysmap.container)
				.addClass('cursor-pointer sysmap_shape')
				.attr('data-id', this.id)
				.attr('data-type', 'shapes');

			this.makeDraggable(true);
			this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

			var dimensions = this.getDimensions();
			this.domNode.css({
				top: dimensions.y + 'px',
				left: dimensions.x + 'px',
				width: dimensions.width + 'px',
				height: dimensions.height + 'px'
			});
		}

		Shape.prototype = {
			/**
			 * Updades values in property data.
			 *
			 * @param {object} data
			 */
			update: function(data) {
				var key,
					dimensions,
					invalidate = (data.type != SVGMapShape.TYPE_LINE && typeof(data.text) !== 'undefined'
							&& this.data.text !== data.text);

				if (typeof data['type'] !== 'undefined' && /^[0-9]+$/.test(this.data.sysmap_shapeid) === true
						&& (data['type'] == SVGMapShape.TYPE_LINE) != (this.data.type == SVGMapShape.TYPE_LINE)) {
					delete data['sysmap_shapeid'];
					this.data.sysmap_shapeid = getUniqueId();
				}

				for (key in data) {
					this.data[key] = data[key];
				}

				['x', 'y', 'width', 'height'].forEach(function(name) {
					this[name] = parseInt(this[name], 10);
				}, this.data);

				dimensions = this.getDimensions();

				this.domNode
					.css({
						width: dimensions.width + 'px',
						height: dimensions.height + 'px'
					});

				this.makeDraggable(true);
				this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

				this.align(false);
				this.trigger('afterMove', this);

				for (key in data) {
					this.data[key] = data[key];
				}

				if (invalidate) {
					sysmap.expandMacros(this);
				}
				else {
					sysmap.updateImage();
				}
			},

			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean} return label with expanded macros.
			 *
			 * @returns {string}
			 */
			getLabel: function (expand) {
				var label = this.data.text;

				if (typeof(expand) === 'undefined') {
					expand = true;
				}

				if (expand && typeof(this.expanded) === 'string' && this.sysmap.data.expand_macros === '1') {
					label = this.expanded;
				}

				return label;
			},

			/**
			 * Gets shape dimensions.
			 */
			getDimensions: function () {
				var dimensions = {
					x: parseInt(this.data.x, 10),
					y: parseInt(this.data.y, 10),
					width: parseInt(this.data.width, 10),
					height: parseInt(this.data.height, 10)
				};

				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					var width = parseInt(this.sysmap.data.width),
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
				if (typeof this.handles === 'undefined') {
					this.handles = [
						$('<div>', {'class': 'ui-resize-dot cursor-move'}),
						$('<div>', {'class': 'ui-resize-dot cursor-move'})
					];

					this.domNode.parent().append(this.handles);

					for (var i = 0; i < 2; i++) {
						this.handles[i].data('id', i);
						this.handles[i].draggable({
							containment: 'parent',
							drag: $.proxy(function(event, data) {
								var dimensions;
								if (data.helper.data('id') === 0) {
									this.data.x = parseInt(data.position.left, 10) + 4;
									this.data.y = parseInt(data.position.top, 10) + 4;
								}
								else {
									this.data.width = parseInt(data.position.left, 10) + 4;
									this.data.height = parseInt(data.position.top, 10) + 4;
								}

								dimensions = this.getDimensions();
								this.domNode.css({
									top: dimensions.y + 'px',
									left: dimensions.x + 'px',
									width: dimensions.width + 'px',
									height: dimensions.height + 'px'
								});

								this.trigger('afterMove', this);
							}, this)
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
				var node = this.domNode;

				if (enable) {
					if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
						this.updateHandles();
					}
					else {
						if (typeof this.handles !== 'undefined') {
							this.handles.forEach(function (handle) {
								handle.remove();
							});
							delete this.handles;
						}
					}

					if (!node.hasClass('ui-draggable')) {
						node.draggable({
							containment: 'parent',
							helper: $.proxy(function() {
								return this.sysmap.dragGroupPlaceholder();
							}, this),
							start: $.proxy(function() {
								this.domNode
									.addClass('cursor-dragging')
									.removeClass('cursor-pointer');
								this.sysmap.dragGroupInit(this);
							}, this),
							drag: $.proxy(function(event, data) {
								this.sysmap.dragGroupDrag(data, this);
							}, this),
							stop: $.proxy(function() {
								this.domNode
									.addClass('cursor-pointer')
									.removeClass('cursor-dragging');
								this.sysmap.dragGroupStop(this);
							}, this)
						});
					}
				}
				else {
					if (typeof this.handles !== 'undefined') {
						this.handles.forEach(function (handle) {
							handle.remove();
						});
						delete this.handles;
					}

					if (node.hasClass('ui-draggable')) {
						node.draggable("destroy");
					}
				}
			},

			/**
			 * Allow resizing of shape.
			 */
			makeResizable: function(enable) {
				var node = this.domNode,
					enabled = node.hasClass('ui-resizable');

				if (enable === enabled) {
					return;
				}

				if (enable) {
					var handles = {};

					$.each(['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'], function(index, key) {
						var handle = $('<div>').addClass('ui-resizable-handle').addClass('ui-resizable-' + key);

						if ($.inArray(key, ['n', 'e', 's', 'w']) >= 0) {
							handle
								.append($('<div>', {'class': 'ui-resize-dot'}))
								.append($('<div>', {'class': 'ui-resizable-border-' + key}));
						}

						node.append(handle);
						handles[key] = handle;
					});

					node.addClass('ui-inner-handles');
					node.resizable({
						handles: handles,
						autoHide: true,
						stop: $.proxy(function(event, data) {
							this.updatePosition({
								x: parseInt(data.position.left, 10),
								y: parseInt(data.position.top, 10)
							});
						}, this)
					});
				}
				else {
					node.removeClass('ui-inner-handles');
					node.resizable("destroy");
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
					this.domNode.addClass('map-element-selected');
				}
				else {
					this.domNode.removeClass('map-element-selected');
				}

				return this.selected;
			},

			/**
			 * Align shape to map or map grid.
			 *
			 * @param {bool} doAutoAlign if we should align element to grid
			 */
			align: function(doAutoAlign) {
				var dims = {
						height: this.domNode.height(),
						width: this.domNode.width()
					},
					dimensions = this.getDimensions(),
					x = dimensions.x,
					y = dimensions.y,
					shiftX = Math.round(dims.width / 2),
					shiftY = Math.round(dims.height / 2),
					newX = x,
					newY = y,
					newWidth = dims.width,
					newHeight = dims.height,
					gridSize = parseInt(this.sysmap.data.grid_size, 10);

				// Lines should not be aligned
				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					this.domNode.css({
						top: dimensions.y + 'px',
						left: dimensions.x + 'px',
						width: dimensions.width + 'px',
						height: dimensions.height + 'px'
					});

					return;
				}

				// if 'fit to map' area coords are 0 always
				if (this.data.elementsubtype === '1' && this.data.areatype === '0'
						&& this.data.elementtype === '3') {
					newX = 0;
					newY = 0;
				}

				// if autoalign is off
				else if (doAutoAlign === false
						|| (typeof doAutoAlign === 'undefined' && this.sysmap.data.grid_align == '0')) {
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

				if (this instanceof Shape || this.data.elementsubtype === '1') {
					this.data.width = newWidth;
					this.data.height = newHeight;
				}

				this.domNode.css({
					top: this.data.y + 'px',
					left: this.data.x + 'px',
					width: newWidth,
					height: newHeight
				});
			},

			/**
			 * Updates element position.
			 *
			 * @param {object} coords
			 */
			updatePosition: function(coords, invalidate) {
				if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
					var dx = coords.x - Math.min(parseInt(this.data.x, 10), parseInt(this.data.width, 10)),
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
					var dimensions = this.getDimensions();

					this.domNode.css({
						top: dimensions.y + 'px',
						left: dimensions.x + 'px'
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

				if (typeof this.sysmap.selection.shapes[this.id] !== 'undefined') {
					this.sysmap.selection.count.shapes--;
				}

				delete this.sysmap.selection.shapes[this.id];
			},

			/**
			 * Gets Shape data.
			 *
			 * @returns {Object}
			 */
			getData: function() {
				return this.data;
			}
		};

		Observer.makeObserver(Shape.prototype);

		/**
		 * @class Creates a new Selement.
		 *
		 * @property {object} sysmap reference to Map object
		 * @property {object} data selement db values
		 * @property {bool} selected if element is now selected by user
		 * @property {string} id elementid
		 * @property {object} domNode reference to related DOM element
		 *
		 * @param {object} sysmap reference to Map object
		 * @param {object} selementData element db values
		 */
		function Selement(sysmap, selementData) {
			this.sysmap = sysmap;
			this.selected = false;

			if (!selementData) {
				selementData = {
					selementid: getUniqueId(),
					elementtype: '4', // image
					elements: {},
					iconid_off: this.sysmap.defaultIconId, // first imageid
					label: t('S_NEW_ELEMENT'),
					label_location: -1, // set default map label location
					x: 0,
					y: 0,
					urls: {},
					elementName: this.sysmap.defaultIconName, // first image name
					use_iconmap: '1',
					application: '',
					inherited_label: null
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

			// assign by reference
			this.sysmap.data.selements[this.id] = this.data;

			// create dom
			this.domNode = $('<div>', {style: 'position: absolute; z-index: 100'})
				.appendTo(this.sysmap.container)
				.addClass('cursor-pointer sysmap_element')
				.attr('data-id', this.id)
				.attr('data-type', 'selements');

			this.makeDraggable(true);
			this.makeResizable(this.data.elementtype == 3 && this.data.elementsubtype == 1 && this.data.areatype == 1);

			this.updateIcon();

			this.domNode.css({
				top: this.data.y + 'px',
				left: this.data.x + 'px'
			});
		}

		Selement.TYPE_HOST			= 0; // SYSMAP_ELEMENT_TYPE_HOST
		Selement.TYPE_MAP			= 1; // SYSMAP_ELEMENT_TYPE_MAP
		Selement.TYPE_TRIGGER		= 2; // SYSMAP_ELEMENT_TYPE_TRIGGER
		Selement.TYPE_HOST_GROUP	= 3; // SYSMAP_ELEMENT_TYPE_HOST_GROUP
		Selement.TYPE_IMAGE			= 4; // SYSMAP_ELEMENT_TYPE_IMAGE

		Selement.prototype = {
			/**
			 * Returns element data.
			 */
			getData: Shape.prototype.getData,

			/**
			 * Allows dragging of element
			 */
			makeDraggable: Shape.prototype.makeDraggable,

			/**
			 * Allows resizing of element
			 */
			makeResizable: Shape.prototype.makeResizable,

			/**
			 * Update label data inherited from map configuration.
			 */
			updateLabel: function () {
				if (this.sysmap.data.label_format != 0) {
					switch (parseInt(this.data.elementtype, 10)) {
						case Selement.TYPE_HOST_GROUP:
							this.data.label_type = this.sysmap.data.label_type_hostgroup;
							if (this.data.label_type == CMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_hostgroup;
							}
							break;

						case Selement.TYPE_HOST:
							this.data.label_type = this.sysmap.data.label_type_host;
							if (this.data.label_type == CMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_host;
							}
							break;

						case Selement.TYPE_TRIGGER:
							this.data.label_type = this.sysmap.data.label_type_trigger;
							if (this.data.label_type == CMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_trigger;
							}
							break;

						case Selement.TYPE_MAP:
							this.data.label_type = this.sysmap.data.label_type_map;
							if (this.data.label_type == CMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_map;
							}
							break;

						case Selement.TYPE_IMAGE:
							this.data.label_type = this.sysmap.data.label_type_image;
							if (this.data.label_type == CMap.LABEL_TYPE_CUSTOM) {
								this.data.inherited_label = this.sysmap.data.label_string_image;
							}
							break;
					}
				}
				else {
					this.data.label_type = this.sysmap.data.label_type;
					this.data.inherited_label = null;
				}

				if (this.data.label_type == CMap.LABEL_TYPE_LABEL) {
					this.data.inherited_label = this.data.label;
				}
				else if (this.data.label_type == CMap.LABEL_TYPE_NAME) {
					if (this.data.elementtype != Selement.TYPE_IMAGE) {
						this.data.inherited_label = this.data.elements[0].elementName;
					}
					else {
						this.data.inherited_label = t('S_IMAGE');
					}
				}

				if (this.data.label_type != CMap.LABEL_TYPE_CUSTOM && this.data.label_type != CMap.LABEL_TYPE_LABEL
						&& this.data.label_type != CMap.LABEL_TYPE_IP) {
					this.data.expanded = null;
				}
				else if (this.data.label_type == CMap.LABEL_TYPE_IP && this.data.elementtype == Selement.TYPE_HOST) {
					this.data.inherited_label = '{HOST.IP}';
				}
			},

			/**
			 * Return label based on map constructor configuration.
			 *
			 * @param {boolean} return label with expanded macros.
			 *
			 * @returns {string} or null
			 */
			getLabel: function (expand) {
				var label = this.data.label;

				if (typeof(expand) === 'undefined') {
					expand = true;
				}

				if (this.data.label_type != CMap.LABEL_TYPE_NOTHING && this.data.label_type != CMap.LABEL_TYPE_STATUS) {
					if (expand && typeof(this.expanded) === 'string' && (this.sysmap.data.expand_macros === '1'
							|| (this.data.label_type == CMap.LABEL_TYPE_IP
							&& this.data.elementtype == Selement.TYPE_HOST))) {
						label = this.expanded;
					}
					else if (typeof this.data.inherited_label === 'string') {
						label = this.data.inherited_label;
					}
				}
				else {
					label = null;
				}

				return label;
			},

			/**
			 * Updates element fields.
			 *
			 * @param {object} data
			 * @param {bool} unsetUndefined			If true, all fields that are not in data parameter will be removed
			 *										from element.
			 */
			update: function(data, unsetUndefined) {
				var fieldName,
					dataFelds = ['elementtype', 'elements', 'iconid_off', 'iconid_on', 'iconid_maintenance',
						'iconid_disabled', 'label', 'label_location', 'x', 'y', 'elementsubtype',  'areatype', 'width',
						'height', 'viewtype', 'urls', 'elementName', 'use_iconmap', 'application'
					],
					fieldsUnsettable = ['iconid_off', 'iconid_on', 'iconid_maintenance', 'iconid_disabled'],
					i,
					ln,
					invalidate = ((typeof(data.label) !== 'undefined' && this.data.label !== data.label)
							|| (typeof(data.elementtype) !== 'undefined' && this.data.elementtype !== data.elementtype)
							|| (typeof(data.elements) !== 'undefined'
							&& Object.keys(this.data.elements).length !== Object.keys(data.elements).length));

				unsetUndefined = unsetUndefined || false;

				if (!invalidate && typeof(data.elements) !== 'undefined') {
					var k,
						id,
						key,
						kln,
						keys,
						ids = Object.keys(this.data.elements);

					for (i = 0, ln = ids.length; i < ln && !invalidate; i++) {
						id = ids[i];
						keys = Object.keys(this.data.elements[id]);

						for (k = 0, kln = keys.length; k < kln; k++) {
							key = keys[k];
							if (this.data.elements[id][key] !== data.elements[id][key]) {
								invalidate = true;
								break;
							}
						}
					}
				}

				// update elements fields, if not massupdate, remove fields that are not in new values
				for (i = 0, ln = dataFelds.length; i < ln; i++) {
					fieldName = dataFelds[i];

					if (typeof data[fieldName] !== 'undefined') {
						this.data[fieldName] = data[fieldName];
					}
					else if (unsetUndefined && (fieldsUnsettable.indexOf(fieldName) === -1)) {
						delete this.data[fieldName];
					}
				}

				// if elementsubtype is not set, it should be 0
				if (unsetUndefined && typeof this.data.elementsubtype === 'undefined') {
					this.data.elementsubtype = '0';
				}

				if (unsetUndefined && typeof this.data.use_iconmap === 'undefined') {
					this.data.use_iconmap = '0';
				}

				this.makeResizable(
						this.data.elementtype == 3 && this.data.elementsubtype == 1 && this.data.areatype == 1
				);

				if (this.data.elementtype === '2') {
					// For element type trigger not exist single element name.
					delete this.data['elementName'];
				}
				else if (this.data.elementtype === '4') {
					// If element is image, unset advanced icons.
					this.data.iconid_on = '0';
					this.data.iconid_maintenance = '0';
					this.data.iconid_disabled = '0';

					// If image element, set elementName to image name.
					for (i in this.sysmap.iconList) {
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

				if (typeof this.sysmap.selection.selements[this.id] !== 'undefined') {
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
				var oldIconClass = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);

				if (oldIconClass !== null) {
					this.domNode.removeClass(oldIconClass[0]);
				}

				if ((this.data.use_iconmap === '1' && this.sysmap.data.iconmapid !== '0')
						&& (this.data.elementtype === '0'
							|| (this.data.elementtype === '3' && this.data.elementsubtype === '1'))) {
					this.domNode.addClass('sysmap_iconid_' + this.sysmap.defaultAutoIconId);
				}
				else {
					this.domNode.addClass('sysmap_iconid_' + this.data.iconid_off);
				}

				if (this.data.elementtype === '3' && this.data.elementsubtype === '1') {
					if (this.data.areatype === '1') {
						this.domNode
							.css({
								width: this.data.width + 'px',
								height: this.data.height + 'px'
							})
							.addClass('map-element-area-bg');
					}
					else {
						this.domNode
							.css({
								width: this.sysmap.data.width + 'px',
								height: this.sysmap.data.height + 'px'
							})
							.addClass('map-element-area-bg');
					}
				}
				else {
					this.domNode
						.css({
							width: '',
							height: ''
						})
						.removeClass('map-element-area-bg');
				}
			},

			getName: function () {
				var name;

				if (typeof this.data.elementName === 'undefined') {
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
		 * @param {object} formContainer jQuery object
		 * @param {object} sysmap
		 */
		function SelementForm(formContainer, sysmap) {
			var formTplData = {
					sysmapid: sysmap.sysmapid
				},
				tpl = new Template($('#mapElementFormTpl').html()),
				i,
				icon,
				formActions = [
					{
						action: 'show',
						value: '#subtypeRow, #hostGroupSelectRow',
						cond: [{
							elementType: '3'
						}]
					},
					{
						action: 'show',
						value: '#hostSelectRow',
						cond: [{
							elementType: '0'
						}]
					},
					{
						action: 'show',
						value: '#triggerSelectRow, #triggerListRow',
						cond: [{
							elementType: '2'
						}]
					},
					{
						action: 'show',
						value: '#mapSelectRow',
						cond: [{
							elementType: '1'
						}]
					},
					{
						action: 'show',
						value: '#areaTypeRow, #areaPlacingRow',
						cond: [{
							elementType: '3',
							subtypeHostGroupElements: 'checked'
						}]
					},
					{
						action: 'show',
						value: '#areaSizeRow',
						cond: [{
							elementType: '3',
							subtypeHostGroupElements: 'checked',
							areaTypeCustom: 'checked'
						}]
					},
					{
						action: 'hide',
						value: '#iconProblemRow, #iconMainetnanceRow, #iconDisabledRow',
						cond: [{
							elementType: '4'
						}]
					},
					{
						action: 'disable',
						value: '#iconid_off, #iconid_on, #iconid_maintenance, #iconid_disabled',
						cond: [
							{
								use_iconmap: 'checked',
								elementType: '0'
							},
							{
								use_iconmap: 'checked',
								elementType: '3',
								subtypeHostGroupElements: 'checked'
							}
						]
					},
					{
						action: 'show',
						value: '#useIconMapRow',
						cond: [
							{
								elementType: '0'
							},
							{
								elementType: '3',
								subtypeHostGroupElements: 'checked'
							}
						]
					},
					{
						action: 'show',
						value: '#application-select-row',
						cond: [
							{
								elementType: '0'
							},
							{
								elementType: '3'
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
			const select_icon_off = document.getElementById('iconid_off');
			const select_icon_on = document.getElementById('iconid_on');
			const select_icon_maintenance = document.getElementById('iconid_maintenance');
			const select_icon_disabled = document.getElementById('iconid_disabled');

			select_icon_on.addOption({label: t('S_DEFAULT'), value: '0'});
			select_icon_maintenance.addOption({label: t('S_DEFAULT'), value: '0'});
			select_icon_disabled.addOption({label: t('S_DEFAULT'), value: '0'});

			for (i in this.sysmap.iconList) {
				icon = this.sysmap.iconList[i];
				select_icon_off.addOption({label: icon.name, value: icon.imageid});
				select_icon_on.addOption({label: icon.name, value: icon.imageid});
				select_icon_maintenance.addOption({label: icon.name, value: icon.imageid});
				select_icon_disabled.addOption({label: icon.name, value: icon.imageid});
			}

			// hosts
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

			// triggers
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
						multiselect: '1',
						noempty: '1'
					}
				}
			});

			// host group
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
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;
			},

			/**
			 * Adds element urls to form.
			 *
			 * @param {object} urls
			 */
			addUrls: function(urls) {
				var tpl = new Template($('#selementFormUrls').html()),
					i,
					url;

				if (typeof urls === 'undefined' || $.isEmptyObject(urls)) {
					urls = {empty: {}};
				}

				for (i in urls) {
					url = urls[i];

					// generate unique urlid
					url.selementurlid = $('#urlContainer tbody tr[id^=urlrow]').length;
					while ($('#urlrow_' + url.selementurlid).length) {
						url.selementurlid++;
					}
					$(tpl.evaluate(url)).appendTo('#urlContainer tbody');
				}
			},

			/**
			 * Add triggers to the list.
			 */
			addTriggers: function(triggers) {
				var tpl = new Template($('#selementFormTriggers').html()),
					selected_triggers = $('#elementNameTriggers').multiSelect('getData'),
					triggerids = [],
					triggers_to_insert = [];

				if (typeof triggers === 'undefined' || $.isEmptyObject(triggers)) {
					triggers = [];
				}

				triggers = triggers.concat(selected_triggers);

				if (triggers) {
					triggers.forEach(function(trigger) {
						if ($('input[name^="element_id[' + trigger.id + ']"]').length == 0) {
							triggerids.push(trigger.id);
							triggers_to_insert[trigger.id] = {
								id: trigger.id,
								name: typeof trigger.prefix == 'undefined'
									? trigger.name
									: trigger.prefix + trigger.name
							};
						}
					});

					if (triggerids.length != 0) {
						// get priority
						var ajaxUrl = new Curl('jsrpc.php');
						ajaxUrl.setArgument('type', 11);
						$.ajax({
							url: ajaxUrl.getUrl(),
							type: 'post',
							dataType: 'html',
							data: {
								method: 'trigger.get',
								triggerids: triggerids
							},
							success: function(data) {
								data = JSON.parse(data);
								triggers.forEach(function(sorted_trigger) {
									data.result.forEach(function(trigger) {
										if (sorted_trigger.id == trigger.triggerid) {
											if ($('input[name^="element_id[' + trigger.triggerid + ']"]').length == 0) {
												trigger.name = triggers_to_insert[trigger.triggerid].name;
												$(tpl.evaluate(trigger)).appendTo('#triggerContainer tbody');

												return false;
											}
										}
									});
								});

								SelementForm.prototype.recalculateSortOrder();
								SelementForm.prototype.initSortable();
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
				for (var elementName in selement) {
					$('[name=' + elementName + ']', this.domNode).val([selement[elementName]]);
				}

				// set default icon state
				if (empty(selement.iconid_on)) {
					$('[name=iconid_on]', this.domNode).val(0);
				}
				if (empty(selement.iconid_disabled)) {
					$('[name=iconid_disabled]', this.domNode).val(0);
				}
				if (empty(selement.iconid_maintenance)) {
					$('[name=iconid_maintenance]', this.domNode).val(0);
				}

				// clear urls
				$('#urlContainer tbody tr').remove();
				this.addUrls(selement.urls);

				if (this.sysmap.data.iconmapid === '0') {
					$('#use_iconmap').prop({
						checked: false,
						disabled: true
					});
				}

				this.actionProcessor.process();

				switch (selement.elementtype) {
					// host
					case '0':
						$('#elementNameHost').multiSelect('addData', [{
							'id': selement.elements[0].hostid,
							'name': selement.elements[0].elementName
						}]);
						break;

					// map
					case '1':
						$('#sysmapid').val(selement.elements[0].sysmapid);
						$('#elementNameMap').val(selement.elements[0].elementName);
						break;

					// trigger
					case '2':
						var triggers = [];

						for (i in selement.elements) {
							triggers[i] = {'id': selement.elements[i].triggerid, 'name': selement.elements[i].elementName};
						}

						this.addTriggers(triggers);
						break;

					// host group
					case '3':
						$('#elementNameHostGroup').multiSelect('addData', [{
							'id': selement.elements[0].groupid,
							'name': selement.elements[0].elementName
						}]);
						break;
				}
			},

			/**
			 * Gets form values for element fields.
			 *
			 * @retrurns {Object|Boolean}
			 */
			getValues: function() {
				var values = $(':input', '#selementForm').not(this.actionProcessor.hidden).serializeArray(),
					data = {
						urls: {}
					},
					i,
					urlPattern = /^url_(\d+)_(name|url)$/,
					url,
					urlNames = {},
					elementsData = {};

				for (i = 0; i < values.length; i++) {
					url = urlPattern.exec(values[i].name);

					if (url !== null) {
						if (typeof data.urls[url[1]] === 'undefined') {
							data.urls[url[1]] = {};
						}

						data.urls[url[1]][url[2]] = values[i].value.toString();
					}
					else {
						data[values[i].name] = values[i].value.toString();
					}
				}

				data.elements = {};

				// set element id and name
				switch (data.elementtype) {
					// host
					case '0':
						elementsData = $('#elementNameHost').multiSelect('getData');

						if (elementsData.length != 0) {
							data.elements[0] = {
								hostid: elementsData[0].id,
								elementName: elementsData[0].name
							};
						}
						break;

					// map
					case '1':
						if ($('#elementNameMap').val() !== '') {
							data.elements[0] = {
								sysmapid: $('#sysmapid').val(),
								elementName: $('#elementNameMap').val()
							};
						}
						break;

					// triggers
					case '2':
						i = 0;
						$('input[name^="element_id"]').each(function() {
							data.elements[i] = {
								triggerid: $(this).val(),
								elementName: $('input[name^="element_name[' + $(this).val() + ']"]').val(),
								priority: $('input[name^="element_priority[' + $(this).val() + ']"]').val()
							};
							i++;
						});
						break;

					// host group
					case '3':
						elementsData = $('#elementNameHostGroup').multiSelect('getData');

						if (elementsData.length != 0) {
							data.elements[0] = {
								groupid: elementsData[0].id,
								elementName: elementsData[0].name
							};
						}
						break;
				}

				// validate urls
				for (i in data.urls) {
					if (data.urls[i].name === '' && data.urls[i].url === '') {
						delete data.urls[i];
						continue;
					}

					if (data.urls[i].name === '' || data.urls[i].url === '') {
						alert(t('S_INCORRECT_ELEMENT_MAP_LINK'));

						return false;
					}

					if (typeof urlNames[data.urls[i].name] !== 'undefined') {
						alert(t('S_EACH_URL_SHOULD_HAVE_UNIQUE') + " '" + data.urls[i].name + "'.");

						return false;
					}

					urlNames[data.urls[i].name] = 1;
				}

				// validate element id
				if ($.isEmptyObject(data.elements) && data.elementtype !== '4') {
					switch (data.elementtype) {
						case '0': alert('Host is not selected.');
							return false;
						case '1': alert('Map is not selected.');
							return false;
						case '2': alert('Trigger is not selected.');
							return false;
						case '3': alert('Host group is not selected.');
							return false;
					}
				}

				return data;
			},

			/**
			 * Drag and drop trigger sorting.
			 */
			initSortable: function() {
				var triggerContainer = $('#triggerContainer');

				triggerContainer.sortable({
					disabled: (triggerContainer.find('tr.sortable').length < 2),
					items: 'tbody tr.sortable',
					axis: 'y',
					containment: 'parent',
					cursor: 'grabbing',
					handle: 'div.drag-icon',
					tolerance: 'pointer',
					opacity: 0.6,
					update: this.recalculateSortOrder,
					start: function(e, ui) {
						$(ui.placeholder).height($(ui.helper).height());
					}
				});
			},

			/**
			 * Sorting triggers by severity.
			 */
			recalculateSortOrder: function() {
				if ($('input[name^="element_id"]').length != 0) {
					var triggers = [],
						priority;
					$('input[name^="element_id"]').each(function() {
						priority = $('input[name^="element_priority[' + $(this).val() + ']"]').val()
						if (!triggers[priority]) {
							triggers[priority] = {
								'priority': priority,
								'html':	$('#triggerrow_' + $(this).val())[0].outerHTML
							}
						}
						else {
							triggers[priority].html += $('#triggerrow_' + $(this).val())[0].outerHTML;
						}
					});

					triggers.sort(function (a, b) {
						return b.priority - a.priority;
					});

					$('#triggerContainer tbody').html('');
					triggers.forEach(function(trigger) {
						$('#triggerContainer tbody').append(trigger.html);
					});
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
			var i,
				icon,
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
			var tpl = new Template($('#mapMassFormTpl').html());
			this.domNode = $(tpl.evaluate()).appendTo(formContainer);

			// populate icons selects
			const select_icon_off = document.getElementById('massIconidOff');
			const select_icon_on = document.getElementById('massIconidOn');
			const select_icon_maintenance = document.getElementById('massIconidMaintenance');
			const select_icon_disabled = document.getElementById('massIconidDisabled');

			select_icon_on.addOption({label: t('S_DEFAULT'), value: '0'});
			select_icon_maintenance.addOption({label: t('S_DEFAULT'), value: '0'});
			select_icon_disabled.addOption({label: t('S_DEFAULT'), value: '0'});

			for (i in this.sysmap.iconList) {
				icon = this.sysmap.iconList[i];
				select_icon_off.addOption({label: icon.name, value: icon.imageid});
				select_icon_on.addOption({label: icon.name, value: icon.imageid});
				select_icon_maintenance.addOption({label: icon.name, value: icon.imageid});
				select_icon_disabled.addOption({label: icon.name, value: icon.imageid});
			}

			document.getElementById('massLabelLocation').selectedIndex = 0;
			select_icon_off.selectedIndex = 0
			select_icon_on.selectedIndex = 0
			select_icon_maintenance.selectedIndex = 0
			select_icon_disabled.selectedIndex = 0

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
			},

			/**
			 * Get values from mass update form that should be updated in all selected elements.
			 *
			 * @return array
			 */
			getValues: function() {
				var values = $('#massForm').serializeArray(),
					data = {},
					i,
					ln;

				for (i = 0, ln = values.length; i < ln; i++) {
					// special case for use iconmap checkbox, because unchecked checkbox is not submitted with form
					if (values[i].name === 'chkbox_use_iconmap') {
						data['use_iconmap'] = '0';
					}
					if (values[i].name.match(/^chkbox_/) !== null) {
						continue;
					}

					data[values[i].name] = values[i].value.toString();
				}

				return data;
			},

			/**
			 * Updates list of selected elements in mass update form.
			 */
			updateList: function() {
				var tpl = new Template($('#mapMassFormListRow').html()),
					id,
					list = [],
					element,
					elementTypeText,
					i,
					ln,
					name;

				$('#massList tbody').empty();

				for (id in this.sysmap.selection.selements) {
					element = this.sysmap.selements[id];

					switch (element.data.elementtype) {
						case '0': elementTypeText = t('S_HOST'); break;
						case '1': elementTypeText = t('S_MAP'); break;
						case '2': elementTypeText = t('S_TRIGGER'); break;
						case '3': elementTypeText = t('S_HOST_GROUP'); break;
						case '4': elementTypeText = t('S_IMAGE'); break;
					}

					list.push({
						elementType: elementTypeText,
						elementName: element.getName().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
								.replace(/\"/g,'&quot;').replace(/\'/g,'&apos;')
					});
				}

				// sort by element type and then by element name
				list.sort(function(a, b) {
					var elementTypeA = a.elementType.toLowerCase(),
						elementTypeB = b.elementType.toLowerCase(),
						elementNameA,
						elementNameB;

					if (elementTypeA < elementTypeB) {
						return -1;
					}
					if (elementTypeA > elementTypeB) {
						return 1;
					}

					elementNameA = a.elementName.toLowerCase();
					elementNameB = b.elementName.toLowerCase();

					if (elementNameA < elementNameB) {
						return -1;
					}
					if (elementNameA > elementNameB) {
						return 1;
					}

					return 0;
				});

				for (i = 0, ln = list.length; i < ln; i++) {
					$(tpl.evaluate(list[i])).appendTo('#massList tbody');
				}
			}
		};

		/**
		 * Form for shape editing.
		 *
		 * @param {object} formContainer jQuery object
		 * @param {object} sysmap
		 */
		function ShapeForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#mapShapeFormTpl').html()).evaluate()).appendTo(formContainer);

			this.domNode.find('.input-color-picker input').colorpicker();
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
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;
			},

			/**
			 * Set form controls with shape fields values.
			 *
			 * @param {object} shape
			 */
			setValues: function(shape) {
				for (var field in shape) {
					$('[name=' + field + ']', this.domNode).val([shape[field]]);
				}

				$('.input-color-picker input', this.domNode).change();
				$('#border_type').change();

				$('#last_shape_type').val(shape.type);
				$('input[type=radio][name=type]:checked').change();
			},

			/**
			 * Get values from shape update form that should be updated
			 *
			 * @return array
			 */
			getValues: function() {
				var values = $('#shapeForm').serializeArray(),
					data = {},
					i,
					ln,
					min_size,
					width = parseInt(this.sysmap.data.width),
					height = parseInt(this.sysmap.data.height);

				for (i = 0, ln = values.length; i < ln; i++) {
					data[values[i].name] = values[i].value.toString();
				}

				data.x = parseInt(data.x, 10);
				data.y = parseInt(data.y, 10);
				data.width = parseInt(data.width, 10);
				data.height = parseInt(data.height, 10);

				data.x = isNaN(data.x) || (data.x < 0) ? 0 : data.x;
				data.y = isNaN(data.y) || (data.y < 0) ? 0 : data.y;

				min_size = (data.type != SVGMapShape.TYPE_LINE) ? 1 : 0;
				data.width = isNaN(data.width) || (data.width < min_size) ? min_size : data.width;
				data.height = isNaN(data.height) || (data.height < min_size) ? min_size : data.height;

				data.x = (data.x >= width) ? width : data.x;
				data.y = (data.y >= height) ? height : data.y;
				data.width = (data.width >= width) ? width : data.width;
				data.height = (data.height >= height) ? height : data.height;

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
			var formActions = [];

			var mapping = {
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

			Object.keys(mapping).forEach(function (key) {
				var condition = {};
				condition[key] = 'checked';

				formActions.push({
					action: 'enable',
					value: mapping[key],
					cond: [condition]
				});
			});

			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#mapMassShapeFormTpl').html()).evaluate()).appendTo(formContainer);

			this.domNode.find('.input-color-picker input').colorpicker();
			this.actionProcessor = new ActionProcessor(formActions);
			this.actionProcessor.process();
		}

		MassShapeForm.prototype = {
			/**
			 * Show form.
			 */
			show: function(figures) {
				var value = figures ? 0 : 2;

				$('.shape_figure_row', this.domNode).toggle(figures);
				$('.switchable-content', this.domNode).each(function (i, element) {
					element.textContent = element.hasAttribute('data-value-' + value) ?
							element.getAttribute('data-value-' + value) :
							element.getAttribute('data-value');
				});

				this.formContainer.draggable('option', 'handle', '#massShapeDragHandler');
				this.formContainer.show();
				this.domNode.show();
				// Element must first be visible so that outerWidth() and outerHeight() are correct.
				this.formContainer.positionOverlayDialogue();
				this.active = true;
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;
				$(':checkbox', this.domNode).prop('checked', false).prop("disabled", false);
				$('textarea, input[type=text]', this.domNode).val('');
				$('.input-color-picker input', this.domNode).change();
				this.actionProcessor.process();
			},

			/**
			 * Get values from mass update form that should be updated in all selected shapes.
			 *
			 * @return array
			 */
			getValues: function() {
				var values = $('#massShapeForm').serializeArray(),
					data = {},
					i,
					ln;

				for (i = 0, ln = values.length; i < ln; i++) {
					if (values[i].name.match(/^mass_/) !== null) {
						data[values[i].name.substring("mass_".length)] = values[i].value.toString();
					}
				}

				return data;
			}
		};

		/**
		 * Form for editin links.
		 *
		 * @param {object} formContainer jQuesry object
		 * @param {object} sysmap
		 */
		function LinkForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#linkFormTpl').html()).evaluate()).appendTo(formContainer);

			this.domNode.find('.input-color-picker input').colorpicker();
		}

		LinkForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.domNode.show();
				$('.element-edit-control').prop('disabled', true);
			},

			/**
			 * Hide form.
			 */
			hide: function() {
				$('#linkForm').hide();
				$('.element-edit-control').prop('disabled', false);
			},

			/**
			 * Get form values for link fields.
			 */
			getValues: function() {
				var values = $('#linkForm').serializeArray(),
					data = {
						linktriggers: {}
					},
					i,
					ln,
					linkTriggerPattern = /^linktrigger_(\w+)_(triggerid|linktriggerid|drawtype|color|desc_exp)$/,
					colorPattern = /^[0-9a-f]{6}$/i,
					linkTrigger;

				for (i = 0, ln = values.length; i < ln; i++) {
					linkTrigger = linkTriggerPattern.exec(values[i].name);

					if (linkTrigger !== null) {
						if (linkTrigger[2] == 'color' && !values[i].value.toString().match(colorPattern)) {
							throw sprintf(t('S_COLOR_IS_NOT_CORRECT'), values[i].value);
						}

						if (typeof data.linktriggers[linkTrigger[1]] === 'undefined') {
							data.linktriggers[linkTrigger[1]] = {};
						}

						data.linktriggers[linkTrigger[1]][linkTrigger[2]] = values[i].value.toString();
					}
					else {
						if (values[i].name == 'color' && !values[i].value.toString().match(colorPattern)) {
							throw sprintf(t('S_COLOR_IS_NOT_CORRECT'), values[i].value);
						}

						data[values[i].name] = values[i].value.toString();
					}
				}

				return data;
			},

			/**
			 * Update form controls with values from link.
			 *
			 * @param {object} link
			 */
			setValues: function(link) {
				var selement1,
					tmp,
					selementid,
					selement,
					elementName,
					optgroups = {},
					optgroupType,
					optgroupLabel,
					optgroup,
					i,
					ln;

				/*
				 * If only one element is selected, make sure that element1 is equal to the selected element and
				 * element2 - to the connected.
				 */
				if (this.sysmap.selection.count.selements === 1 && this.sysmap.selection.count.shapes === 0) {
					// get currently selected element
					for (selementid in this.sysmap.selection.selements) {
						selement1 = this.sysmap.selements[selementid];
					}

					if (selement1.id !== link.selementid1) {
						tmp = link.selementid1;
						link.selementid1 = selement1.id;
						link.selementid2 = tmp;
					}
				}

				// populate list of elements to connect with
				const connect_to_select = document.createElement('z-select');
				connect_to_select._button.id = 'label-selementid2';
				connect_to_select.id = 'selementid2';
				connect_to_select.name = 'selementid2';

				// sort by type
				for (selementid in this.sysmap.selements) {
					selement = this.sysmap.selements[selementid];

					if (selement.id == link.selementid1) {
						continue;
					}

					if (optgroups[selement.data.elementtype] === void(0)) {
						optgroups[selement.data.elementtype] = [];
					}

					optgroups[selement.data.elementtype].push(selement);
				}

				for (optgroupType in optgroups) {
					switch (optgroupType) {
						case '0':
							optgroupLabel = t('S_HOST');
							break;

						case '1':
							optgroupLabel = t('S_MAP');
							break;

						case '2':
							optgroupLabel = t('S_TRIGGER');
							break;

						case '3':
							optgroupLabel = t('S_HOST_GROUP');
							break;

						case '4':
							optgroupLabel = t('S_IMAGE');
							break;
					}

					optgroup = {label: optgroupLabel, options: []};

					for (i = 0, ln = optgroups[optgroupType].length; i < ln; i++) {
						optgroup.options.push({
							value: optgroups[optgroupType][i].id,
							label: optgroups[optgroupType][i].getName()
						});
					}

					connect_to_select.addOptionGroup(optgroup);
				}

				$('#selementid2').replaceWith(connect_to_select);

				// set values for form elements
				for (elementName in link) {
					$('[name=' + elementName + ']', this.domNode).val(link[elementName]);
				}

				// clear triggers
				this.triggerids = {};
				$('#linkTriggerscontainer tbody tr').remove();
				this.addLinkTriggers(link.linktriggers);
			},

			/**
			 * Add link triggers to link form.
			 *
			 * @param {object} triggers
			 */
			addLinkTriggers: function(triggers) {
				var tpl = new Template($('#linkTriggerRow').html()),
					linkTrigger,
					table = $('#linkTriggerscontainer tbody');

				for (linkTrigger in triggers) {
					this.triggerids[triggers[linkTrigger].triggerid] = linkTrigger;
					$(tpl.evaluate(triggers[linkTrigger])).appendTo(table);
					$('#linktrigger_' + triggers[linkTrigger].linktriggerid + '_drawtype')
						.val(triggers[linkTrigger].drawtype);
				}

				table.find('.input-color-picker input').colorpicker();
				$('.input-color-picker input', this.domNode).change();
			},

			/**
			 * Add new triggers which were selected in popup to trigger list.
			 *
			 * @param {object} triggers
			 */
			addNewTriggers: function(triggers) {
				var tpl = new Template($('#linkTriggerRow').html()),
					linkTrigger = {
						color: 'DD0000'
					},
					linktriggerid,
					i,
					ln,
					table = $('#linkTriggerscontainer tbody');

				for (i = 0, ln = triggers.length; i < ln; i++) {
					if (typeof this.triggerids[triggers[i].triggerid] !== 'undefined') {
						continue;
					}

					linktriggerid = getUniqueId();

					// store linktriggerid to generate every time unique one
					this.sysmap.allLinkTriggerIds[linktriggerid] = true;

					// store triggerid to forbid selecting same trigger twice
					this.triggerids[triggers[i].triggerid] = linktriggerid;
					linkTrigger.linktriggerid = linktriggerid;
					linkTrigger.desc_exp = triggers[i].description;
					linkTrigger.triggerid = triggers[i].triggerid;
					$(tpl.evaluate(linkTrigger)).appendTo(table);
				}

				table.find('.input-color-picker input').colorpicker();
				$('.input-color-picker input', this.domNode).change();
			},

			/**
			 * Updates links list for element.
			 *
			 * @param {string} selementIds
			 */
			updateList: function(selementIds) {
				var links = this.sysmap.getLinksBySelementIds(selementIds),
					linkTable,
					rowTpl,
					list,
					i, j,
					selement,
					tmp,
					ln,
					link,
					linktriggers,
					fromElementName,
					toElementName;

				$('.element-links').hide();
				$('.element-links tbody').empty();

				if (links.length) {
					$('#mapLinksContainer').show();

					if (objectSize(selementIds) > 1) {
						rowTpl = '#massElementLinkTableRowTpl';
						linkTable = $('#mass-element-links');
					}
					else {
						rowTpl = '#elementLinkTableRowTpl';
						linkTable = $('#element-links');
					}

					rowTpl = new Template($(rowTpl).html());

					list = [];
					for (i = 0, ln = links.length; i < ln; i++) {
						link = this.sysmap.links[links[i]].data;

						/*
						 * If one element selected and it's not link.selementid1, we need to swap link.selementid1
						 * and link.selementid2 in order that sorting works correctly.
						 */
						if (objectSize(selementIds) == 1 && !selementIds[link.selementid1]) {
							// Get currently selected element.
							for (var selementId in this.sysmap.selection.selements) {
								selement = this.sysmap.selements[selementId];
							}

							if (selement.id !== link.selementid1) {
								tmp = link.selementid1;
								link.selementid1 = selement.id;
								link.selementid2 = tmp;
							}
						}

						linktriggers = [];

						for (var linktrigger in link.linktriggers) {
							linktriggers.push(link.linktriggers[linktrigger].desc_exp);
						}

						fromElementName = this.sysmap.selements[link.selementid1].getName();
						toElementName = this.sysmap.selements[link.selementid2].getName();

						list.push({
							fromElementName: fromElementName,
							toElementName: toElementName,
							linkid: link.linkid,
							linktriggers: linktriggers
						});
					}

					// Sort by "from" element and then by "to" element.
					list.sort(function(a, b) {
						var fromElementA = a.fromElementName.toLowerCase(),
							fromElementB = b.fromElementName.toLowerCase(),
							toElementA = a.toElementName.toLowerCase(),
							toElementB = b.toElementName.toLowerCase(),
							linkIdA = a.linkid,
							linkIdB = b.linkid;

						if (fromElementA < fromElementB) {
							return -1;
						}
						else if (fromElementA > fromElementB) {
							return 1;
						}

						if (toElementA < toElementB) {
							return -1;
						}
						else if (toElementA > toElementB) {
							return 1;
						}

						if (linkIdA < linkIdB) {
							return -1;
						}
						else if (linkIdA > linkIdB) {
							return 1;
						}

						return 0;
					});

					for (i = 0, ln = list.length; i < ln; i++) {
						var row = $(rowTpl.evaluate(list[i])),
							row_urls = $('.element-urls', row);

						for (j = 0; j < list[i].linktriggers.length; j++) {
							if (j != 0) {
								row_urls.append($('<br>'));
							}
							row_urls.append($('<span>').text(list[i].linktriggers[j]));
						}

						row.appendTo(linkTable.find('tbody'));
					}

					linkTable.closest('.element-links').show();
				}
				else {
					$('#mapLinksContainer').hide();
				}
			}
		};

		var sysmap = new CMap(containerId, mapData);

		Shape.prototype.bind('afterMove', function(event, element) {
			if (sysmap.selection.count.shapes === 1 && sysmap.selection.count.selements === 0
					&& sysmap.selection.shapes[element.id] !== void(0)) {
				$('#shapeX').val(element.data.x);
				$('#shapeY').val(element.data.y);

				if (typeof element.data.width !== 'undefined') {
					$('#shapeForm input[name=width]').val(element.data.width);
				}
				if (typeof element.data.height !== 'undefined') {
					$('#shapeForm input[name=height]').val(element.data.height);
				}
			}

			sysmap.updateImage();
		});

		Selement.prototype.bind('afterMove', function(event, element) {
			if (sysmap.selection.count.selements === 1 && sysmap.selection.count.shapes === 0
					&& sysmap.selection.selements[element.id] !== void(0)) {
				$('#x').val(element.data.x);
				$('#y').val(element.data.y);

				if (typeof element.data.width !== 'undefined') {
					$('#areaSizeWidth').val(element.data.width);
				}
				if (typeof element.data.height !== 'undefined') {
					$('#areaSizeHeight').val(element.data.height);
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
		run: function(containerId, mapData) {
			if (this.object !== null) {
				throw new Error('Map has already been run.');
			}

			this.object = createMap(containerId, mapData);
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
	 * the viewport. In case the popup is too large, position it with a small margin depenging on whether is too long
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
