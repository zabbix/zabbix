/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

var ZABBIX = ZABBIX || {};

ZABBIX.namespace = function(namespace) {
	var parts = namespace.split('.'),
		parent = this,
		i;

	for (i = 0; i < parts.length; i++) {
		if (typeof parent[parts[i]] === 'undefined') {
			parent[parts[i]] = {};
		}

		parent = parent[parts[i]];
	}

	return parent;
};

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
				linkid,
				setContainer;

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
				selements: {}, // selected elements { elementid: elementid, ... }
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
				this.shapes[shape.shapeid] = new Shape(this, shape);
			}, this);

			for (linkid in this.data.links) {
				if (this.data.selements.hasOwnProperty(selementid)) {
					this.links[linkid] = new Link(this, this.data.links[linkid]);
				}
			}

			// create container for forms
			this.formContainer = $('<div></div>', {
					id: 'map-window',
					class: 'overlay-dialogue',
					style: 'display:none; top: 50px; left: 500px'})
				.appendTo('body')
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
					this.hideContextMenus();

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

		CMap.prototype = {
			save: function() {
				var url = new Curl(location.href);

				$.ajax({
					url: url.getPath() + '?output=ajax&sid=' + url.getArgument('sid'),
					type: 'post',
					data: {
						favobj: 'sysmap',
						action: 'update',
						sysmapid: this.sysmapid,
						sysmap: Object.toJSON(this.data) // TODO: remove prototype method
					},
					error: function() {
						throw new Error('Cannot update map.');
					}
				});
			},

			updateImage: function() {
				if (this.data.grid_show === '1') {
					this.map.setGrid(parseInt(this.data.grid_size));
				}
				else {
					this.map.setGrid(0);
				}

				var elements = [];
				Object.keys(this.selements).forEach(function(key) {
					var element = {};
					['selementid', 'x', 'y', 'label', 'label_location'].forEach(function (name) {
						element[name] = this.selements[key].data[name];
					}, this);

					// host group elements
					if (this.selements[key].data.elementtype == '3' && this.selements[key].data.elementsubtype == '1') {
						element.width = this.selements[key].data.width;
						element.height = this.selements[key].data.height;
					}

					element.icon = this.selements[key].data.iconid_off;
					elements.push(element);
				}, this);

				var links = [];
				Object.keys(this.links).forEach(function(key) {
					var link = {};
					['linkid', 'selementid1', 'selementid2', 'drawtype', 'color', 'label'].forEach(function (name) {
						link[name] = this.links[key].data[name];
					}, this);

					links.push(link);
				}, this);

				var shapes = [];
				Object.keys(this.shapes).forEach(function(key) {
					var shape = {};
					Object.keys(this.shapes[key].data).forEach(function (name) {
						shape[name] = this.shapes[key].data[name];
					}, this);
					shapes.push(shape);
				}, this);

				this.map.update({
					'background': this.data.backgroundid,
					'elements': elements,
					'links': links,
					'shapes': shapes
				});
			},

			// elements
			deleteSelectedElements: function() {
				var selementid;

				if (this.selection.count.selements && confirm(locale['S_DELETE_SELECTED_ELEMENTS_Q'])) {
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

				if (this.selection.count.shapes && confirm(locale['S_DELETE_SELECTED_SHAPES_Q'])) {
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
					$(this).html((that.data.grid_align === '1') ? locale['S_ON'] : locale['S_OFF']);
				});

				// toggle grid visibility
				$('#gridshow').click(function() {
					that.data.grid_show = (that.data.grid_show === '1') ? '0' : '1';
					$(this).html((that.data.grid_show === '1') ? locale['S_SHOWN'] : locale['S_HIDDEN']);
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
						alert(locale['S_NO_IMAGES']);

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
						alert(locale['S_TWO_ELEMENTS_SHOULD_BE_SELECTED']);

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
						alert(locale['S_PLEASE_SELECT_TWO_ELEMENTS']);

						return false;
					}

					linkids = that.getLinksBySelementIds(that.selection.selements);

					if (linkids.length && confirm(locale['S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'])) {
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
				// delegate selements icons clicks
				$(this.container).on('click', '.sysmap_element, .sysmap_shape', function(event) {
					that.selectElements([{
							id: $(this).attr('data-id'),
							type: $(this).attr('data-type')
					}], event.ctrlKey || event.metaKey);
				});

				$(this.container).on('contextmenu', '.sysmap_shape', function(event) {
					event.preventDefault();
					var item = $(this).data('id');
					$(this).menuPopup([
						{
							'items': [
								{
									label: locale['S_BRING_TO_FRONT'],
									clickCallback: function()
									{
										that.reorderShapes(item, 'last');
										that.hideContextMenus();
									}
								},
								{
									label: locale['S_BRING_FORWARD'],
									clickCallback: function()
									{
										that.reorderShapes(item, 'next');
										that.hideContextMenus();
									}
								},
								{
									label: locale['S_SEND_BACKWARD'],
									clickCallback: function()
									{
										that.reorderShapes(item, 'previous');
										that.hideContextMenus();
									}
								},
								{
									label: locale['S_SEND_TO_BACK'],
									clickCallback: function()
									{
										that.reorderShapes(item, 'first');
										that.hideContextMenus();
									}
								}
							]
						},
						{
							'items': [
								{
									label: locale['S_REMOVE'],
									clickCallback: function()
									{
										that.shapes[item].remove();
										that.hideContextMenus();
										that.toggleForm();
										that.updateImage();
									}
								}
							]
						}
					], event);
				});

				/*
				 * Form events
				 */
				$('#elementType').change(function() {
					var obj = $(this);

					switch (obj.val()) {
						// host
						case '0':
							jQuery('#elementNameHost').multiSelect('clean');
							break;

						// host group
						case '3':
							jQuery('#elementNameHostGroup').multiSelect('clean');
							break;

						// others types
						default:
							$('input[name=elementName]').val('');
							$('#elementid').val('0');
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
							this.selements[selementid].update(values);
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
						for (var shapeid in this.selection.shapes) {
							this.shapes[shapeid].update(values);
						}
					}
				}, this));

				$('#newSelementUrl').click($.proxy(function() {
					this.form.addUrls();
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
				$('#application-select').click(function() {
					var data = $('#elementNameHost').multiSelect('getData');

					PopUp('popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application'
						+ '&with_applications=1&dstfrm=selementForm'
						+ ((data.length > 0 && $('#elementType').val() == '4') ? '&hostid='+ data[0].id : '')
					);
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
						for (var selementid in this.selection.selements) {
							this.selements[selementid].update(values);
						}
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

				// changes for color inputs
				$('.input-color-picker input').on('change', function() {
					var id = $(this).attr('id');

					set_color_by_name(id, this.value);
				});

				$('#border_type').on('change', function() {
					$(this).parent().find('input').prop("disabled", this.value === '-1');
				});

				$('#mass_border_type, #chkboxBorderType').on('change', function() {
					var disable = $('#mass_border_type').val() === '-1' && $('#chkboxBorderType').is(":checked");

					$('#chkboxBorderWidth, #chkboxBorderColor').prop("disabled", disable);
					$('#mass_border_width').prop("disabled", disable || !$('#chkboxBorderWidth').is(":checked"));
					$('#mass_border_color').prop("disabled", disable || !$('#chkboxBorderColor').is(":checked"));
				});
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
			},

			reorderShapes: function(id, position) {
				var shapes = [],
					shape = null;

				Object.keys(this.shapes).forEach(function(key) {
					shapes.push(this.shapes[key]);
				}, this);

				shapes = shapes.sort(function (a,b) {
					return a.data.zindex - b.data.zindex;
				});

				shapes.forEach(function(value, index) {
					if (value.id == id) {
						shape = index;
					}
				});

				// shape was not found
				if (shape === null) {
					return;
				}

				switch (position.toLowerCase()) {
					case 'first':
						// no need to update
						if (shape === 0) {
							return;
						}

						$(shapes[shape].domNode).insertBefore(shapes[0].domNode);
						shapes.splice(0, 0, shapes.splice(shape, 1)[0]);
						shapes.forEach(function(shape, index) {
							shape.data.zindex = index;
						});
					break;

					case 'last':
						// no need to update
						if (shape === shapes.length - 1) {
							return;
						}

						$(shapes[shape].domNode).insertAfter(shapes[shapes.length - 1].domNode);
						shapes.splice(shapes.length-1, 0, shapes.splice(shape, 1)[0]);
						shapes.forEach(function(shape, index) {
							shape.data.zindex = index;
						});
					break;

					case 'next':
						// no need to update
						if (shape === shapes.length - 1) {
							return;
						}

						$(shapes[shape].domNode).insertAfter(shapes[shape + 1].domNode);
						shapes[shape + 1].data.zindex--;
						shapes[shape].data.zindex++;
					break;

					case 'previous':
						// no need to update
						if (shape === 0) {
							return;
						}

						$(shapes[shape].domNode).insertBefore(shapes[shape - 1].domNode);
						shapes[shape - 1].data.zindex++;
						shapes[shape].data.zindex--;
					break;
				}

				this.map.invalidate('shapes');
				this.updateImage();
			},

			hideContextMenus: function () {
				$('.action-menu').each(function() {
					$(this).data('is-active', false).fadeOut(0);
				});
			},

			selectElements: function(ids, addSelection) {
				var i, ln;

				if (!addSelection) {
					this.clearSelection();
				}

				for (i = 0, ln = ids.length; i < ln; i++) {
					var id = ids[i].id,
						type = ids[i].type,
						selected = this[type][id].toggleSelect();

					if (selected) {
						this.selection.count[type]++;
						this.selection[type][id] = id;
					}
					else {
						this.selection.count[type]--;
						delete this.selection[type][id];
					}
				}

				this.toggleForm();
			},

			toggleForm: function() {
				var id;

				this.shapeForm.hide();
				this.linkForm.hide();

				if (this.selection.count.selements + this.selection.count.shapes === 0 ||
					(this.selection.count.selements > 0 && this.selection.count.shapes > 0)) {
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

						// resize multiselect
						$('.multiselect').multiSelect('resize');
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
						this.form.hide();
						this.massForm.hide();
						$('#link-connect-to').hide();

						this.massShapeForm.show();
					}
				}
			}
		};

		/**
		 * Creates a new Link.
		 *
		 * @class represents connector between two Elements
		 *
		 * @property {Object} sysmap reference to Map object
		 * @property {Object} data link db values
		 * @property {String} id linkid
		 *
		 * @param {Object} sysmap Map object
		 * @param {Object} [linkData] link data from db
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

			for (var linktrigger in this.data.linktriggers) {
				this.sysmap.allLinkTriggerIds[linktrigger.triggerid] = true;
			}

			// assign by reference
			this.sysmap.data.links[this.id] = this.data;
		}

		Link.prototype = {
			/**
			 * Updades values in property data.
			 *
			 * @param {Object} data
			 */
			update: function(data) {
				var key;

				for (key in data) {
					this.data[key] = data[key];
				}

				sysmap.updateImage();
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
		 * @property {Object} sysmap reference to Map object
		 * @property {Object} data shape db values
		 * @property {String} id shapeid
		 *
		 * @param {Object} sysmap Map object
		 * @param {Object} [shapeData] shape data from db
		 */
		function Shape(sysmap, shapeData) {
			this.sysmap = sysmap;

			if (!shapeData) {
				shapeData = {
					type: 0,
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
					text_valign: -1,
					text_halign: -1,
					text: '',
					border_type: 0
				};

				// generate unique shapeid
				shapeData.shapeid = getUniqueId();
				shapeData.zindex = Object.keys(sysmap.shapes).length;
			}

			shapeData.elementsubtype = '1';

			this.data = shapeData;
			this.id = this.data.shapeid;

			// assign by reference
			this.sysmap.data.shapes[this.id] = this.data;

			// create dom
			this.domNode = $('<div></div>', {
					style: 'position:absolute; z-index: 1;\
						background: url("data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7") 0 0 repeat',
				})
				.appendTo(this.sysmap.container)
				.addClass('pointer sysmap_shape')
				.attr('data-id', this.id)
				.attr('data-type', 'shapes');

			this.domNode.draggable({
				containment: 'parent',
				opacity: 0.5,
				helper: 'clone',
				stop: $.proxy(function(event, data) {
					this.updatePosition({
						x: parseInt(data.position.left, 10),
						y: parseInt(data.position.top, 10)
					});
				}, this)
			});

			this.makeResizable(true);

			this.domNode.css({
				top: this.data.y + 'px',
				left: this.data.x + 'px',
				width: this.data.width + 'px',
				height: this.data.height + 'px'
			});
		}

		Shape.prototype = {
			/**
			 * Updades values in property data.
			 *
			 * @param {Object} data
			 */
			update: function(data) {
				var key;

				for (key in data) {
					this.data[key] = data[key];
				}

				this.domNode
					.css({
						width: this.data.width + 'px',
						height: this.data.height + 'px'
					});

				this.align(false);
				this.trigger('afterMove', this);

				sysmap.updateImage();
			},

			/**
			 * Allows resizing of shape
			 */
			makeResizable: function(enable) {
				var node = this.domNode,
					isEnabled = node.hasClass('ui-resizable');

				if (enable === isEnabled) {
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
			 * @param {Boolean} state
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
			 * @param {Boolean} doAutoAlign if we should align element to grid
			 */
			align: function(doAutoAlign) {
				var dims = {
						height: this.domNode.height(),
						width: this.domNode.width()
					},
					x = parseInt(this.data.x, 10),
					y = parseInt(this.data.y, 10),
					shiftX = Math.round(dims.width / 2),
					shiftY = Math.round(dims.height / 2),
					newX = x,
					newY = y,
					newWidth = dims.width,
					newHeight = dims.height,
					gridSize = parseInt(this.sysmap.data.grid_size, 10);

				// if 'fit to map' area coords are 0 always
				if (this.data.elementsubtype === '1' && this.data.areatype === '0') {
					newX = 0;
					newY = 0;
				}

				// if autoalign is off
				else if (doAutoAlign === false || (typeof doAutoAlign === 'undefined' && this.sysmap.data.grid_align == '0')) {
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

				if (this.data.elementsubtype === '1') {
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
			 * @param {Object} coords
			 */
			updatePosition: function(coords) {
				this.data.x = coords.x;
				this.data.y = coords.y;
				this.align();
				this.trigger('afterMove', this);
			},

			/**
			 * Removes Shape object, delete all reference to it.
			 */
			remove: function() {
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
		 * @property {Object} sysmap reference to Map object
		 * @property {Object} data selement db values
		 * @property {Boolean} selected if element is now selected by user
		 * @property {String} id elementid
		 * @property {Object} domNode reference to related DOM element
		 *
		 * @param {Object} sysmap reference to Map object
		 * @param {Object} selementData element db values
		 */
		function Selement(sysmap, selementData) {
			this.sysmap = sysmap;
			this.selected = false;

			if (!selementData) {
				selementData = {
					selementid: getUniqueId(),
					elementtype: '4', // image
					elementid: 0,
					iconid_off: this.sysmap.defaultIconId, // first imageid
					label: locale['S_NEW_ELEMENT'],
					label_location: -1, // set default map label location
					x: 0,
					y: 0,
					urls: {},
					elementName: this.sysmap.defaultIconName, // first image name
					use_iconmap: '1',
					application: ''
				};
			}
			else {
				if ($.isArray(selementData.urls)) {
					selementData.urls = {};
				}
			}

			this.data = selementData;
			this.id = this.data.selementid;

			// assign by reference
			this.sysmap.data.selements[this.id] = this.data;

			// create dom
			this.domNode = $('<div></div>', {style: 'position:absolute; z-index: 100'})
				.appendTo(this.sysmap.container)
				.addClass('pointer sysmap_element')
				.attr('data-id', this.id)
				.attr('data-type', 'selements');

			this.domNode.draggable({
				containment: 'parent',
				opacity: 0.5,
				helper: 'clone',
				stop: $.proxy(function(event, data) {
					this.updatePosition({
						x: parseInt(data.position.left, 10),
						y: parseInt(data.position.top, 10)
					});
				}, this)
			});

			this.makeResizable(selementData.elementtype == 3 && selementData.elementsubtype == 1);

			this.updateIcon();

			this.domNode.css({
				top: this.data.y + 'px',
				left: this.data.x + 'px'
			});
		}

		Selement.prototype = {
			/**
			 * Returns element data.
			 */
			getData: Shape.prototype.getData,

			/**
			 * Allows resizing of element
			 */
			makeResizable: Shape.prototype.makeResizable,

			/**
			 * Updates element fields.
			 *
			 * @param {Object} data
			 * @param {Boolean} unsetUndefined if true, all fields that are not in data parameter will be removed from element
			 */
			update: function(data, unsetUndefined) {
				var fieldName,
					dataFelds = [
						'elementtype', 'elementid', 'iconid_off', 'iconid_on', 'iconid_maintenance',
						'iconid_disabled', 'label', 'label_location', 'x', 'y', 'elementsubtype',  'areatype', 'width',
						'height', 'viewtype', 'urls', 'elementName', 'use_iconmap', 'elementExpressionTrigger',
						'application'
					],
					fieldsUnsettable = ['iconid_off', 'iconid_on', 'iconid_maintenance', 'iconid_disabled'],
					i,
					ln;

				unsetUndefined = unsetUndefined || false;

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

				this.makeResizable(this.data.elementtype == 3 && this.data.elementsubtype == 1);

				// if element is image we unset advanced icons
				if (this.data.elementtype === '4') {
					this.data.iconid_on = '0';
					this.data.iconid_maintenance = '0';
					this.data.iconid_disabled = '0';

					// if image element, set elementName to image name
					for (i in this.sysmap.iconList) {
						if (this.sysmap.iconList[i].imageid === this.data.iconid_off) {
							this.data.elementName = this.sysmap.iconList[i].name;
						}
					}
				}

				this.updateIcon();
				this.align(false);
				this.trigger('afterMove', this);
			},

			/**
			 * Updates element position.
			 *
			 * @param {Object} coords
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
			 * @param {Boolean} state
			 */
			toggleSelect: Shape.prototype.toggleSelect,

			/**
			 * Align element to map or map grid.
			 *
			 * @param {Boolean} doAutoAlign if we should align element to grid
			 */
			align: Shape.prototype.align,

			/**
			 * Updates element icon and height/witdh in case element is area type.
			 */
			updateIcon: function() {
				var oldIconClass = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);

				if (oldIconClass !== null) {
					this.domNode.removeClass(oldIconClass[0]);
				}

				if ((this.data.use_iconmap === '1' && this.sysmap.data.iconmapid !== '0')
						&& (this.data.elementtype === '0' || (this.data.elementtype === '3' && this.data.elementsubtype === '1'))) {
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
			}
		};

		Observer.makeObserver(Selement.prototype);

		/**
		 * Form for elements.
		 *
		 * @param {Object} formContainer jQuery object
		 * @param {Object} sysmap
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
						value: '#triggerSelectRow',
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
			for (i in this.sysmap.iconList) {
				icon = this.sysmap.iconList[i];
				$('#iconid_off, #iconid_on, #iconid_maintenance, #iconid_disabled')
					.append('<option value="' + icon.imageid + '">' + icon.name + '</option>');
			}
			$('#iconid_on, #iconid_maintenance, #iconid_disabled')
				.prepend('<option value="0">' + locale['S_DEFAULT'] + '</option>');
			$('#iconid_on, #iconid_maintenance, #iconid_disabled').val(0);

			// hosts
			$('#elementNameHost').multiSelectHelper({
				id: 'elementNameHost',
				objectName: 'hosts',
				name: 'elementValue',
				selectedLimit: 1,
				objectOptions: {
					editable: true
				},
				popup: {
					parameters: 'srctbl=hosts&dstfrm=selementForm&dstfld1=elementNameHost' +
						'&srcfld1=hostid'
				}
			});

			// host group
			$('#elementNameHostGroup').multiSelectHelper({
				id: 'elementNameHostGroup',
				objectName: 'hostGroup',
				name: 'elementValue',
				selectedLimit: 1,
				objectOptions: {
					editable: true
				},
				popup: {
					parameters: 'srctbl=host_groups&dstfrm=selementForm&dstfld1=elementNameHostGroup' +
						'&srcfld1=groupid'
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
			 * @param {Object} urls
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
			 * Set form controls with element fields values.
			 *
			 * @param {Object} selement
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

				// set multiselect values
				if (selement.elementtype == 0 || selement.elementtype == 3) {
					var item = {
						'id': selement.elementid,
						'name': selement.elementName
					};

					switch (selement.elementtype) {
						// host
						case '0':
							$('#elementNameHost').multiSelect('addData', item);
							break;

						// host group
						case '3':
							$('#elementNameHostGroup').multiSelect('addData', item);
							break;
					}
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
					urlNames = {};

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

				// set element id and name
				switch (data.elementtype) {
					// host
					case '0':
						var elementData = $('#elementNameHost').multiSelect('getData');

						if (empty(elementData)) {
							data.elementid = '0';
							data.elementName = '';
						}
						else {
							data.elementid = elementData[0].id;
							data.elementName = elementData[0].name;
						}
						break;

					// host group
					case '3':
						var elementData = $('#elementNameHostGroup').multiSelect('getData');

						if (empty(elementData)) {
							data.elementid = '0';
							data.elementName = '';
						}
						else {
							data.elementid = elementData[0].id;
							data.elementName = elementData[0].name;
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
						alert(locale['S_INCORRECT_ELEMENT_MAP_LINK']);

						return false;
					}

					if (typeof urlNames[data.urls[i].name] !== 'undefined') {
						alert(locale['S_EACH_URL_SHOULD_HAVE_UNIQUE'] + " '" + data.urls[i].name + "'.");

						return false;
					}

					urlNames[data.urls[i].name] = 1;
				}

				// validate element id
				if (data.elementid === '0' && data.elementtype !== '4') {
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
			}
		};

		/**
		 * Elements mass update form.
		 *
		 * @param {Object} formContainer jQuery object
		 * @param {Object} sysmap
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
			for (i in this.sysmap.iconList) {
				icon = this.sysmap.iconList[i];
				$('#massIconidOff, #massIconidOn, #massIconidMaintenance, #massIconidDisabled')
					.append('<option value="' + icon.imageid + '">' + icon.name + '</option>');
			}
			$('#massIconidOn, #massIconidMaintenance, #massIconidDisabled')
				.prepend('<option value="0">' + locale['S_DEFAULT'] + '</option>');

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
				this.updateList();
			},

			/**
			 * Hide mass update form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				$(':checkbox', this.domNode).prop('checked', false);
				$('select', this.domNode).each(function() {
					var select = $(this);
					select.val($('option:first', select).val());
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
					ln;

				$('#massList tbody').empty();

				for (id in this.sysmap.selection.selements) {
					element = this.sysmap.selements[id];

					switch (element.data.elementtype) {
						case '0': elementTypeText = locale['S_HOST']; break;
						case '1': elementTypeText = locale['S_MAP']; break;
						case '2': elementTypeText = locale['S_TRIGGER']; break;
						case '3': elementTypeText = locale['S_HOST_GROUP']; break;
						case '4': elementTypeText = locale['S_IMAGE']; break;
					}

					list.push({
						elementType: elementTypeText,
						elementName: element.data.elementName
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
		 * @param {Object} formContainer jQuery object
		 * @param {Object} sysmap
		 */
		function ShapeForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#mapShapeFormTpl').html()).evaluate()).appendTo(formContainer);
		}

		ShapeForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.formContainer.draggable('option', 'handle', '#shapeDragHandler');
				this.formContainer.show();
				this.domNode.show();
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
			 * @param {Object} shape
			 */
			setValues: function(shape) {
				for (var field in shape) {
					$('[name=' + field + ']', this.domNode).val([shape[field]]);
				}

				$('.input-color-picker input', this.domNode).change();
				$('#border_type').change();
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
					ln;

				for (i = 0, ln = values.length; i < ln; i++) {
					data[values[i].name] = values[i].value.toString();
				}

				return data;
			}
		};

		/**
		 * Form for shape editing.
		 *
		 * @param {Object} formContainer jQuery object
		 * @param {Object} sysmap
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

				formActions.push(
					{
						action: 'enable',
						value: mapping[key],
						cond: [condition]
					}
				);
			});

			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#mapMassShapeFormTpl').html()).evaluate()).appendTo(formContainer);

			this.actionProcessor = new ActionProcessor(formActions);
			this.actionProcessor.process();
		}

		MassShapeForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.formContainer.draggable('option', 'handle', '#massShapeDragHandler');
				this.formContainer.show();
				this.domNode.show();
				this.active = true;
			},

			/**
			 * Hides element form.
			 */
			hide: function() {
				this.domNode.toggle(false);
				this.active = false;
				$(':checkbox', this.domNode).prop('checked', false);
				$('select', this.domNode).each(function() {
					var select = $(this);
					select.val($('option:first', select).val());
				});
				$('textarea', this.domNode).val('');
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
		 * @param {Object} formContainer jQuesry object
		 * @param {Object} sysmap
		 */
		function LinkForm(formContainer, sysmap) {
			this.sysmap = sysmap;
			this.formContainer = formContainer;
			this.triggerids = {};
			this.domNode = $(new Template($('#linkFormTpl').html()).evaluate()).appendTo(formContainer);
		}

		LinkForm.prototype = {
			/**
			 * Show form.
			 */
			show: function() {
				this.domNode.show();
				$('.element-edit-control').attr('disabled', true);
			},

			/**
			 * Hide form.
			 */
			hide: function() {
				$('#linkForm').hide();
				$('.element-edit-control').attr('disabled', false);
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
						if (linkTrigger[2] == 'color' && !colorPattern.match(values[i].value.toString())) {
							throw sprintf(t('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).'), values[i].value);
						}

						if (typeof data.linktriggers[linkTrigger[1]] === 'undefined') {
							data.linktriggers[linkTrigger[1]] = {};
						}

						data.linktriggers[linkTrigger[1]][linkTrigger[2]] = values[i].value.toString();
					}
					else {
						if (values[i].name == 'color' && !colorPattern.match(values[i].value.toString())) {
							throw sprintf(t('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).'), values[i].value);
						}

						data[values[i].name] = values[i].value.toString();
					}
				}

				return data;
			},

			/**
			 * Update form controls with values from link.
			 *
			 * @param {Object} link
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
					optgroupDom,
					i,
					ln;

				// if only one element is selected, make sure that element1 is equal to the selected element and
				// element2 - to the connected
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
				$('#selementid2').empty();

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
						case '0': optgroupLabel = locale['S_HOST']; break;
						case '1': optgroupLabel = locale['S_MAP']; break;
						case '2': optgroupLabel = locale['S_TRIGGER']; break;
						case '3': optgroupLabel = locale['S_HOST_GROUP']; break;
						case '4': optgroupLabel = locale['S_IMAGE']; break;
					}

					optgroupDom = $('<optgroup label="' + optgroupLabel + '"></optgroup>');

					for (i = 0, ln = optgroups[optgroupType].length; i < ln; i++) {
						optgroupDom.append('<option value="' + optgroups[optgroupType][i].id + '">' + optgroups[optgroupType][i].data.elementName + '</option>')
					}

					$('#selementid2').append(optgroupDom);
				}

				// set values for form elements
				for (elementName in link) {
					$('[name=' + elementName + ']', this.domNode).val(link[elementName]);
				}

				// clear triggers
				this.triggerids = {};
				$('#linkTriggerscontainer tbody tr').remove();
				this.addTriggers(link.linktriggers);
			},

			/**
			 * Add triggers to link form.
			 *
			 * @param {Object} triggers
			 */
			addTriggers: function(triggers) {
				var tpl = new Template($('#linkTriggerRow').html()),
					linkTrigger;

				for (linkTrigger in triggers) {
					this.triggerids[triggers[linkTrigger].triggerid] = linkTrigger;
					$(tpl.evaluate(triggers[linkTrigger])).appendTo('#linkTriggerscontainer tbody');
					$('#linktrigger_' + triggers[linkTrigger].linktriggerid + '_drawtype').val(triggers[linkTrigger].drawtype);
				}

				$('.input-color-picker input', this.domNode).change();
			},

			/**
			 * Add new triggers which were selected in popup to trigger list.
			 *
			 * @param {Object} triggers
			 */
			addNewTriggers: function(triggers) {
				var tpl = new Template($('#linkTriggerRow').html()),
					linkTrigger = {
						color: 'DD0000'
					},
					linktriggerid,
					i,
					ln;

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
					$(tpl.evaluate(linkTrigger)).appendTo('#linkTriggerscontainer tbody');
				}

				$('.input-color-picker input', this.domNode).change();
			},

			/**
			 * Updates links list for element.
			 *
			 * @param {String} selementIds
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
					linktriggers;

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

						// if one element selected and it's not link.selementid1
						// we need to swap link.selementid1 and link.selementid2
						// in order that sorting works correctly
						if (objectSize(selementIds) == 1 && !selementIds[link.selementid1]) {
							// get currently selected element
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

						list.push({
							fromElementName: this.sysmap.selements[link.selementid1].data.elementName,
							toElementName: this.sysmap.selements[link.selementid2].data.elementName,
							linkid: link.linkid,
							linktriggers: linktriggers
						});
					}

					// sort by "from" element and then by "to" element
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

				$('#shapeForm input[name=x]').val(element.data.x);
				$('#shapeForm input[name=y]').val(element.data.y);

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

			sysmap.updateImage();
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
