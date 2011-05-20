/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**
*/

var ZBX_SYSMAPS = new Array();			// sysmaps obj reference

// sysmapid ALWAYS must be a STRING (js doesn't support uint64) !!!!
function create_map(container, sysmapid){
	if(is_number(sysmapid) && (sysmapid > 100000000000000)){
		throw('Error: Wrong type of arguments passed to function [create_map]');
	}

	var id = ZBX_SYSMAPS.length;
	ZBX_SYSMAPS[id] = {
		map: new CMap(container, sysmapid, id)
	};
}

(function(window){
	"use strict";

	window.CMap = function(containerid, sysmapid, id){
		this.id = id;
		this.sysmapid = sysmapid;

		this.container = jQuery('#' + containerid);
		if(this.container.length == 0){
			this.container = jQuery(document.body);
		}

		// create container for forms
		this.formContainer = jQuery('<div></div>', {id: 'divSelementForm'})
				.css({
					zIndex: 100,
					position: 'absolute',
					top: '50px',
					left: '500px'
				})
				.appendTo('body')
				.draggable({
					containment: [0,0,3200,3200]
				});

		this.mapimg = jQuery('#sysmap_img');

		// getting map data from server
		var url = new Curl();
		var ajaxData = jQuery.ajax({
			url: url.getPath() + '?output=json&sid=' + url.getArgument('sid'),
			type: 'post',
			data: {
				'favobj': 'sysmap',
				'sysmapid': this.sysmapid,
				'action': 'get'
			},
			success: jQuery.proxy(function(result){
				this.data = result.data.mapData;
				this.iconList = result.data.iconList;
			}, this),
			error: function(){
				throw('Get selements FAILED.');
			}
		});

		// perform initialization actions after data recieved from server
		jQuery.when(ajaxData).then(jQuery.proxy(function(){
			for(var selementid in this.data.selements){
				this.selements[selementid] = new CSelement(this, this.data.selements[selementid]);
			}
			for(var linkid in this.data.links){
				this.links[linkid] = new CLink(this, this.data.links[linkid]);
			}

			this.updateImage();
			this.form = new CElementForm(this.formContainer, this);
			this.massForm = new CMassForm(this.formContainer, this);
			this.linkForm = new CLinkForm(this.formContainer, this);
			this.bindActions();
		}, this));


		// initialize SELECTABLE
		this.container.selectable({
			start: jQuery.proxy(function(event){
				if(!event.ctrlKey)
					this.clearSelection();
			}, this),
			stop: jQuery.proxy(function(event){
				var selected = jQuery('.ui-selected', this.container);
				var ids = new Array();
				for(var i = 0; i < selected.length; i++){
					ids.push(jQuery(selected[i]).data('id'));

					// remove ui-selected class, to not confuse next selection
					selected.removeClass('ui-selected');
				}
				this.selectElements(ids, event.ctrlKey);
			}, this)
		});


		var listeners = {};

		this.bind = function(event, callback){
			var i;

			if(typeof callback == 'function'){
				event = ('' + event).toLowerCase().split(/\s+/);

				for(i = 0; i < event.length; i++){
					if(listeners[event[i]] === void(0)){
						listeners[event[i]] = [];
					}
					listeners[event[i]].push(callback);
				}
			}
			return this;
		};

		this.trigger = function(event, target){
			event = event.toLowerCase();
			var	handlers = listeners[event] || [],
				i;

			if(handlers.length){
				event = jQuery.Event(event);
				for(i = 0; i < handlers.length; i++){
					try{
						if(handlers[i](event, target) === false || event.isDefaultPrevented()){
							break;
						}
					}catch(ex){
						window.console && window.console.log && window.console.log(ex);
					}
				}
			}
			return this;
		};

		// bind events
		this.bind('elementMoved', jQuery.proxy(function(event, target){
			if((this.selection.count === 1) && (this.selection.selements[target.id]) !== void(0)){
				jQuery('#x').val(target.data.x);
				jQuery('#y').val(target.data.y);
			}
		}, this));
	};
	CMap.prototype = {
		data: {},							// local sysmap DB :)
		iconList: {}, // list of available icons [{imageid: name}, ...]

		container: null, // selements and links HTML container (D&D droppable area)
		formContainer: null, // jQuery dom object contining forms
		mapimg: null, // HTML element map img

		// FORMS
		form: null, // element form
		listForm: null, // element list form
		massForm: null, // element mass update form

		selements: {}, // element objects
		links:	{},	// map links array

		selection: {
			count: 0, // number of selected elements
			selements: {} // selected elements { elementid: elementid, ... }
		},

		currentLinkId: '0', // linkid of currently edited link

		mlinktrigger: {
			linktriggerid:	0,					// ALWAYS must be a STRING (js doesn't support uint64)
			triggerid:		0,					// ALWAYS must be a STRING (js doesn't support uint64)
			desc_exp:		locale['S_SET_TRIGGER'],		// default trigger caption
			drawtype:		0,
			color:			'CC0000'
		},

		save: function(){
			var url = new Curl(location.href);
			jQuery.ajax({
				url: url.getPath() + '?output=ajax' + '&sid=' + url.getArgument('sid'),
				type: "post",
				data: {
					favobj: "sysmap",
					action: "save",
					sysmapid: this.sysmapid,
					sysmap: Object.toJSON(this.data) //TODO: remove prototype method
				},
				error: function(){
					document.location = url.getPath() + '?' + Object.toQueryString(params);
				}
			});
		},

		updateImage: function(){
			var url = new Curl();
			var urlText = 'map.php' + '?sid=' + url.getArgument('sid');

			// grid
			if(this.data.grid_show == '1')
				urlText += '&grid=' + this.data.grid_size;

			jQuery.ajax({
				url: urlText,
				type: 'post',
				data: {
					'output': 'json',
					'sysmapid': this.sysmapid,
					'noselements': 1,
					'nolinks': 1,
					'nocalculations': 1,
					'selements': Object.toJSON(this.data.selements),
					'links': Object.toJSON(this.data.links)
				},
				success: jQuery.proxy(function(data){
					this.mapimg.attr('src', 'imgstore.php?imageid=' + data.result);
				}, this),
				error: function(){
					alert('Map image update failed');
				}
			});
		},
		setContainer: function(){
			var sysmap_pn = this.mapimg.position();
			var sysmapHeight = this.mapimg.height();
			var sysmapWidth = this.mapimg.width();

			var container_pn = this.container.position();

			if((container_pn.top != sysmap_pn.top) || (container_pn.left != sysmap_pn.left) || (this.container.height() != sysmapHeight) || (this.container.width() != sysmapWidth)){
				this.container.css({
					top: sysmap_pn.top + 'px',
					left: sysmap_pn.left + 'px',
					height: sysmapHeight + 'px',
					width: sysmapWidth + 'px'
				});
			}
		},

	// ---------- ELEMENTS ------------------------------------------------------------------------------------
		deleteSelectedElements: function(){
			if(Confirm(locale['S_DELETE_SELECTED_ELEMENTS_Q'])){
				for(var selementid in this.selection.selements){
					this.selements[selementid].remove();
					this.removeLinksBySelementId(selementid);
				}

				this.toggleForm();
				this.updateImage();
			}
		},

	// CONNECTORS
		removeLinks: function(){
			var selementid1 = null;
			var selementid2 = null;

			if(this.selection.count !== 2){
				alert(locale['S_PLEASE_SELECT_TWO_ELEMENTS']);
				return false;
			}

			for(var selementid in this.selection.selements){
				if(selementid1 === null)
					selementid1 = selementid;
				else
					selementid2 = selementid;
			}

			var linkids = this.getLinksBySelementIds(selementid1, selementid2);
			if(linkids.length === 0)
				return false;

			if(Confirm(locale['S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'])){
				for(var i = 0; i < linkids.length; i++){
					this.links[linkids[i]].remove();
				}

				this.linkForm.hide();
				this.updateImage();
			}
		},

		removeLinksBySelementId: function(selementid){
			var linkids = this.getLinksBySelementIds(selementid);
			for(var i = 0; i < linkids.length; i++){
				this.links[linkids[i]].remove();
			}
		},

		getLinksBySelementIds: function(selementid1, selementid2){
			if(typeof(selementid2) == 'undefined') selementid2 = null;

			var links = [];
			for(var linkid in this.data.links){
				if(empty(this.data.links[linkid])) continue;

				if(is_null(selementid2)){
					if((this.data.links[linkid].selementid1 == selementid1) || (this.data.links[linkid].selementid2 == selementid1))
						links.push(linkid);
				} else{
					if((this.data.links[linkid].selementid1 == selementid1) && (this.data.links[linkid].selementid2 == selementid2))
						links.push(linkid); else if((this.data.links[linkid].selementid1 == selementid2) && (this.data.links[linkid].selementid2 == selementid1))
						links.push(linkid);
				}
			}

			return links;
		},

	//--------------------------------------------------------------------------------

		bindActions: function(){
			var that = this;

			// MAP IMAGE EVENTS
			// resize div on window resize
			jQuery(window).resize(jQuery.proxy(this.setContainer, this));
			// resize div on image change
			this.mapimg.load(jQuery.proxy(this.setContainer, this));


			// MAP PANEL EVENTS
			// change grid size
			jQuery('#gridsize').change(function(){
				var value = jQuery(this).val();
				if(that.data.grid_size != value){
					that.data.grid_size = value;
					that.updateImage();
				}
			});

			// toggle autoalign
			jQuery('#gridautoalign').click(function(){
				that.data.grid_align = that.data.grid_align == '1' ? '0' : '1';
				jQuery(this).html(that.data.grid_align == '1' ? locale['S_ON'] : locale['S_OFF']);
			});

			// toggle grid visibility
			jQuery('#gridshow').click(function(){
				that.data.grid_show = that.data.grid_show == '1' ? '0' : '1';
				jQuery(this).html(that.data.grid_show == '1' ? locale['S_SHOWN'] : locale['S_HIDDEN']);
				that.updateImage();
			});

			// perform align all
			jQuery('#gridalignall').click(function(){
				for(var selementid in that.selements){
					that.selements[selementid].align(true);
				}
				that.updateImage();
			});

			// save map
			jQuery('#sysmap_save').click(function(){
				that.save();
			});

			// add element
			jQuery('#selement_add').click(function(){
				var selement = new CSelement(that);
				that.selements[selement.id] = selement;
				that.updateImage();
			});

			// remove element
			jQuery('#selement_remove').click(function(){
				that.removeLinks();
			});

			// add link
			jQuery('#link_add').click(function(){
				if(that.selection.count != 2){
					alert(locale['S_TWO_ELEMENTS_SHOULD_BE_SELECTED']);
					return false;
				}
				var link = new CLink(that);
				that.links[link.id] = link;
				that.updateImage();
			});

			// remove link
			jQuery('#link_remove').click(function(){
				that.removeLinks();
			});


			// SELEMENTS EVENTS
			// delegate selements icons clicks
			jQuery(this.container).delegate('.sysmap_element', 'click', function(event){
				that.selectElements([jQuery(this).data('id')], event.ctrlKey);
			});


			// FORM EVENTS
			// when change elementType, we clear elementnames and elementid
			jQuery('#elementType').change(function(){
				jQuery('input[name=elementName]').val('');
				jQuery('#elementid').val('0');
			});

			jQuery('#elementClose').click(function(){
				that.clearSelection();
				that.toggleForm();
			});
			jQuery('#elementRemove').click(jQuery.proxy(this.deleteSelectedElements, this));
			jQuery('#elementApply').click(jQuery.proxy(function(){
				if(this.selection.count != 1) throw 'Try to single update element, when more than one selected.';
				var values = this.form.getValues();
				if(values){
					for(var selementid in this.selection.selements){
						this.selements[selementid].update(values, true);
					}
				}

			}, this));
			jQuery('#newSelementUrl').click(jQuery.proxy(function(){
				this.form.addUrls();
			}, this));


			// mass update form
			jQuery('#massClose').click(function(){
				that.clearSelection();
				that.toggleForm();
			});
			jQuery('#massRemove').click(jQuery.proxy(this.deleteSelectedElements, this));
			jQuery('#massApply').click(jQuery.proxy(function(){
				var values = this.massForm.getValues();
				if(values){
					for(var selementid in this.selection.selements){
						this.selements[selementid].update(values);
					}
				}
			}, this));

			// open link form
			jQuery('#linksList').delegate('.openlink', 'click', function(){
				that.currentLinkId = jQuery(this).data('linkid');
				var linkData = that.links[that.currentLinkId].getData();
				that.linkForm.setValues(linkData);
				that.linkForm.show();
			});

			// link form
			jQuery('#linkRemove').click(function(){
				that.links[that.currentLinkId].remove();
				that.linkForm.hide();
				that.updateImage();
			});
			jQuery('#linkApply').click(function(){
				var linkData = that.linkForm.getValues();
				that.links[that.currentLinkId].update(linkData)
			});
			jQuery('#linkClose').click(function(){
				that.linkForm.hide();
			});

			// changes for color inputs
			this.linkForm.domNode.delegate('.colorpicker', 'change', function(){
				var id = jQuery(this).attr('id');
				set_color_by_name(id, this.value);
			});
			this.linkForm.domNode.delegate('.colorpickerLabel', 'click', function(){
				var id = jQuery(this).attr('id');
				var input = id.match(/^lbl_(.+)$/);
				show_color_picker(input[1]);
			});

		},

		clearSelection: function(){
			for(var id in this.selection.selements){
				this.selection.count--;
				this.selements[id].toggleSelect(false);
				delete this.selection.selements[id];
			}
		},

		selectElements: function(ids, addSelection){
			if(!addSelection){
				this.clearSelection();
			}

			for(var i = 0; i < ids.length; i++){
				var selementid = ids[i];
				var selected = this.selements[selementid].toggleSelect();
				if(selected){
					this.selection.count++;
					this.selection.selements[selementid] = selementid;
				}
				else{
					this.selection.count--;
					delete this.selection.selements[selementid];
				}
			}

			this.toggleForm();
		},

		toggleForm: function(){
			this.linkForm.hide();
			if(this.selection.count == 0){
				this.form.hide();
				this.massForm.hide();
			}
			else if(this.selection.count == 1){
				for(var selementid in this.selection.selements){
					this.form.setValues(this.selements[selementid].getData());
				}
				this.massForm.hide();
				this.form.show();
			}
			else{
				this.form.hide();
				this.massForm.show();
			}
		}

	};


	// *******************************************************************
	//		LINK object
	// *******************************************************************
	function CLink(sysmap, linkData){
		this.sysmap = sysmap;

		var linkid;

		if(!linkData){
			linkData = {
				label:			'',
				selementid1:	null,
				selementid2:	null,
				linktriggers:	{},
				drawtype:		0,
				color:			'00CC00'
			};

			for(var selementid in this.sysmap.selection.selements){
				if(linkData.selementid1 === null)
					linkData.selementid1 = selementid;
				else
					linkData.selementid2 = selementid;
			}

			// generate unique linkid
			do{
				linkid = parseInt(Math.random(1000000000) * 10000000);
				linkid = linkid.toString();
			} while(typeof this.sysmap.data.links[linkid] !== 'undefined');
			linkData.linkid = linkid;
		}
		else{
			if(jQuery.isArray(linkData.linktriggers)){
				linkData.linktriggers = {};
			}
		}

		this.data = linkData;
		this.id = this.data.linkid;

		// assign by reference
		this.sysmap.data.links[this.id] = this.data;
	}
	CLink.prototype = {

		update: function(data){
			for(var key in data){
				this.data[key] = data[key];
			}

			this.sysmap.updateImage();
		},

		remove: function(){
			delete this.sysmap.data.links[this.id];
			delete this.sysmap.links[this.id];
		},

		getData: function(){
			return this.data;
		},

		createLinkTrigger: function(linktrigger){
			for(var ltid in this.data.linktriggers){
				if(this.data.linktriggers[ltid].triggerid === linktrigger.triggerid){
					linktrigger.linktriggerid = ltid;
					break;
				}
			}

			var linktriggerid = 0;
			if(!isset('linktriggerid',linktrigger) || (linktrigger['linktriggerid'] == 0)){
				do{
					linktriggerid = parseInt(Math.random(1000000000) * 1000000000);
					linktriggerid = linktriggerid.toString();
				}while(typeof(this.data.linktriggers[linktriggerid]) != 'undefined');

				linktrigger['linktriggerid'] = linktriggerid;
			}
			else{
				linktriggerid = linktrigger.linktriggerid;
			}

			this.data.linktriggers[linktriggerid] = linktrigger;
		}

	};

	function CSelement(sysmap, selementData){
		this.sysmap = sysmap;

		var selementid;

		if(!selementData){
			selementData = {
				elementtype: '4', // image
				iconid_off: this.sysmap.iconList[0].imageid, // first imageid
				label: locale['S_NEW_ELEMENT'],
				label_location: this.sysmap.data.label_location, // set default map label location
				x: 0,
				y: 0,
				urls: {},
				elementName: this.sysmap.iconList[0].name, // image name
				image: this.sysmap.iconList[0].imageid
			};

			// generate unique selementid
			do{
				selementid = parseInt(Math.random(1000000000) * 10000000);
				selementid = selementid.toString();
			} while(typeof this.sysmap.data.selements[selementid] !== 'undefined');
			selementData.selementid = selementid;
		}
		else{
			if(jQuery.isArray(selementData.urls)){
				selementData.urls = {};
			}
		}

		this.elementName = selementData.elementName;
		delete selementData.elementName;

		this.data = selementData;

		this.id = this.data.selementid;
		// assign by reference
		this.sysmap.data.selements[this.id] = this.data;


		// create dom
		this.domNode = jQuery('<div></div>')
				.appendTo(this.sysmap.container)
				.addClass('pointer sysmap_element')
				.data('id', this.id);


		jQuery(this.domNode).draggable({
			containment: 'parent',
			opacity: 0.5,
			helper: 'clone',
			stop: jQuery.proxy(function(event, data){
				this.update({
					x: parseInt(data.position.left, 10),
					y: parseInt(data.position.top, 10)
				});
			}, this)
		});

		this.updateIcon();
		this.align();

		// TODO: grid snap
		//	if(this.sysmap.auto_align){
		//		jQuery(this.domNode).draggable('option', 'grid', [this.sysmap.grid_size, this.sysmap.grid_size]);
		//	}
	}
	CSelement.prototype = {
		selected: false,

		getData: function(){
			return jQuery.extend({}, this.data, { elementName: this.elementName });
		},

		update: function(data, unsetUndefined){
			unsetUndefined = unsetUndefined || false;

			var	fieldName,
					dataFelds = [
						'elementtype', 'elementid', 'iconid_off', 'iconid_on', 'iconid_maintenance',
						'iconid_disabled', 'label', 'label_location', 'x', 'y', 'elementsubtype',  'areatype', 'width',
						'height', 'viewtype', 'urls'
					],
					i;

			// elementName
			if(typeof data.elementName !== 'undefined'){
				this.elementName = data.elementName;
			}

			for(i = 0; i < dataFelds.length; i++){
				fieldName = dataFelds[i];
				if(typeof data[fieldName] !== 'undefined'){
					this.data[fieldName] = data[fieldName];
				}
				else if(unsetUndefined){
					delete this.data[fieldName];
				}
			}

			// if we update all data and advanced_icons turned off or element is image, reset advanced iconids
			if((unsetUndefined && (typeof data.advanced_icons === 'undefined')) || this.data.elementtype === '4'){
				this.data.iconid_on = '0';
				this.data.iconid_maintenance = '0';
				this.data.iconid_disabled = '0';
			}

			// if image element, set elementName to image name
			if(this.data.elementtype === '4'){
				for(i = 0; i < this.sysmap.iconList.length; i++){
					if(this.sysmap.iconList[i].imageid === this.data.iconid_off){
						this.elementName = this.sysmap.iconList[i].name;
					}
				}
			}

			this.updateIcon();
			this.align();
			this.sysmap.trigger('elementMoved', this);

			this.sysmap.updateImage();
		},

		remove: function(){
			this.domNode.remove();
			delete this.sysmap.data.selements[this.id];
			delete this.sysmap.selements[this.id];

			if(typeof this.sysmap.selection.selements[this.id] !== 'undefined')
				this.sysmap.selection.count--;
			delete this.sysmap.selection.selements[this.id];
		},

		toggleSelect: function(state){
			state = state || !this.selected;

			this.selected = state;
			if(this.selected)
				this.domNode.addClass('selected');
			else
				this.domNode.removeClass('selected');

			return this.selected;
		},

		align: function(force){
			force = force || false;

			var dims = {
				height: this.domNode.height(),
				width: this.domNode.width()
			},
					x = parseInt(this.data.x, 10),
					y = parseInt(this.data.y, 10);


			if(!force && (this.sysmap.data.grid_align == '0')){
				if((x + dims.width) > this.sysmap.data.width){
					this.data.x = this.sysmap.data.width - dims.width;
				}
				if((y + dims.height) > this.sysmap.data.height){
					this.data.y = this.sysmap.data.height - dims.height;
				}
			}
			else{
				var shiftX = Math.round(dims.width / 2);
				var shiftY = Math.round(dims.height / 2);

				var newX = parseInt(this.data.x, 10) + shiftX;
				var newY = parseInt(this.data.y, 10) + shiftY;

				var gridSize = parseInt(this.sysmap.data.grid_size, 10);

				newX = Math.floor(newX / gridSize) * gridSize;
				newY = Math.floor(newY / gridSize) * gridSize;

				// centrillize
				newX += Math.round(gridSize / 2) - shiftX;
				newY += Math.round(gridSize / 2) - shiftY;

				// limits
				if(newX < shiftX)
					newX = 0;
				else if((newX + dims.width) > this.sysmap.data.width)
					newX = this.sysmap.data.width - dims.width;

				if(newY < shiftY)
					newY = 0;
				else if((newY + dims.height) > this.sysmap.data.height)
					newY = this.sysmap.data.height - dims.height;
				//--

				this.data.y = newY;
				this.data.x = newX;
			}

			this.domNode.css({
				top: this.data.y + 'px',
				left: this.data.x + 'px'
			});
		},

		updateIcon: function(){
			var oldIconClass = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);
			if(oldIconClass !== null){
				this.domNode.removeClass(oldIconClass[0]);
			}

			this.domNode.addClass('sysmap_iconid_'+this.data.iconid_off)
		}
	};

	// *******************************************************************
	//		FORM object
	// *******************************************************************
	function CElementForm(formContainer, sysmap){
		this.sysmap = sysmap;
		this.formContainer = formContainer;

		// create form
		var formTplData = {
			sysmapid: this.sysmap.sysmapid
		};
		var tpl = new Template(jQuery('#mapElementFormTpl').html());
		this.domNode = jQuery(tpl.evaluate(formTplData)).appendTo(formContainer);


		// populate icons selects
		for(var i = 0; i < this.sysmap.iconList.length; i++){
			var icon = this.sysmap.iconList[i];
			jQuery('#iconid_off, #iconid_on, #iconid_maintenance, #iconid_disabled')
					.append('<option value="' + icon.imageid + '">' + icon.name + '</option>');
		}
		jQuery('#iconid_on, #iconid_maintenance, #iconid_disabled')
				.prepend('<option value="0">' + locale['S_DEFAULT'] + '</option>');

		// apply jQuery UI elements
		jQuery('#elementApply, #elementRemove, #elementClose').button();


		// create action processor
		var formActions = [
			{
				action: 'show',
				value: '#subtypeRow, #hostGroupSelectRow',
				cond: {
					elementType: '3'
				}
			},
			{
				action: 'show',
				value: '#hostSelectRow',
				cond: {
					elementType: '0'
				}
			},
			{
				action: 'show',
				value: '#triggerSelectRow',
				cond: {
					elementType: '2'
				}
			},
			{
				action: 'show',
				value: '#mapSelectRow',
				cond: {
					elementType: '1'
				}
			},
			{
				action: 'show',
				value: '#areaTypeRow, #areaPlacingRow',
				cond: {
					elementType: '3',
					subtypeHostGroupElements: 'checked'
				}
			},
			{
				action: 'show',
				value: '#areaSizeRow',
				cond: {
					elementType: '3',
					subtypeHostGroupElements: 'checked',
					areaTypeCustom: 'checked'
				}
			},
			{
				action: 'show',
				value: '#iconProblemRow, #iconMainetnanceRow, #iconDisabledRow',
				cond: {
					advanced_icons: 'checked'
				}
			},
			{
				action: 'hide',
				value: '#advancedIconsRow',
				cond: {
					elementType: '4'
				}
			}
		];
		this.actionProcessor = new ActionProcessor(formActions);
		this.actionProcessor.process();
	}
	CElementForm.prototype = {

		show: function(){
			this.formContainer.draggable("option", "handle", '#formDragHandler');
			this.domNode.toggle(true);
		},

		hide: function(){
			this.domNode.toggle(false);
		},

		addUrls: function(urls){
			if((typeof urls === 'undefined') || jQuery.isEmptyObject(urls)){
				urls = {empty: {}};
			}

			var tpl = new Template(jQuery('#selementFormUrls').html());

			for(var i in urls){
				var url = urls[i];

				// generate unique urlid
				url.selementurlid = jQuery('#urlContainer tr[id^=urlrow]').length;
				while(jQuery('#urlrow_'+url.selementurlid).length){
					url.selementurlid++;
				}

				jQuery(tpl.evaluate(url)).appendTo('#urlContainer');
			}
		},

		setValues: function(selement){
			for(var elementName in selement){
				jQuery('[name='+elementName+']', this.domNode).val(selement[elementName]);
			}

			jQuery('#advanced_icons').attr('checked', (selement.iconid_on != 0) || (selement.iconid_maintenance != 0) || (selement.iconid_disabled != 0));

			// clear urls
			jQuery('#urlContainer tr').remove();
			this.addUrls(selement.urls);

			this.actionProcessor.process();


			this.updateList(selement.selementid);
		},

		getValues: function(){
			var values = jQuery('#selementForm').serializeArray(),
				data = {
					urls: {}
				},
				i,
				urlPattern = /^url_(\d+)_(name|url)$/,
				url;

			for(i = 0; i < values.length; i++){
				url = urlPattern.exec(values[i].name);
				if(url !== null){
					if(typeof data.urls[url[1]] === 'undefined'){
						data.urls[url[1]] = {};
					}
					data.urls[url[1]][url[2]] = values[i].value.toString();
				}
				else{
					data[values[i].name] = values[i].value.toString();
				}
			}

			var urlNames = {};
			for(i in data.urls){
				if((data.urls[i].name === '') && (data.urls[i].url === '')){
					delete data.urls[i];
					continue;
				}

				if((data.urls[i].name === '') || (data.urls[i].url === '')){
					alert(locale['S_INCORRECT_ELEMENT_MAP_LINK']);
					return false;
				}

				if(typeof urlNames[data.urls[i].name] !== 'undefined'){
					alert(locale['S_EACH_URL_SHOULD_HAVE_UNIQUE'] + " '" + data.urls[i].name + "'.");
					return false;
				}
				urlNames[data.urls[i].name] = 1;
			}


			if((data.elementid === '0') && (data.elementtype !== '4')){
				switch(data.elementtype){
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

			data.x = Math.abs(parseInt(data.x, 10));
			data.y = Math.abs(parseInt(data.y, 10));

			return data;
		},

		updateList: function(selementid){
			var links = this.sysmap.getLinksBySelementIds(selementid),
				rowTpl,
				list,
				i,
				link,
				linkedSelementid,
				element,
				elementTypeText,
				linktrigger,
				linktriggers;

			if(links.length){
				jQuery('#mapLinksContainer').toggle(true);
				jQuery('#linksList').empty();

				rowTpl = new Template(jQuery('#mapLinksRow').html());

				list = new Array();
				for(i = 0; i < links.length; i++){
					link = this.sysmap.links[links[i]].data;

					linkedSelementid = (selementid == link.selementid1) ? link.selementid2 : link.selementid1;
					element = this.sysmap.selements[linkedSelementid];

					elementTypeText = '';
					switch(element.data.elementtype){
						case '0': elementTypeText = locale['S_HOST']; break;
						case '1': elementTypeText = locale['S_MAP']; break;
						case '2': elementTypeText = locale['S_TRIGGER']; break;
						case '3': elementTypeText = locale['S_HOST_GROUP']; break;
						case '4': elementTypeText = locale['S_IMAGE']; break;
					}

					linktriggers = [];
					for(linktrigger in link.linktriggers){
						linktriggers.push(link.linktriggers[linktrigger].desc_exp);
					}

					list.push({
						elementType: elementTypeText,
						elementName: element.elementName,
						linkid: link.linkid,
						linktriggers: linktriggers.join('\n')
					});
				}

				// sort by elementtype and then by element name
				list.sort(function(a, b){
					if(a.elementType < b.elementType){ return -1; }
					if(a.elementType > b.elementType){ return 1; }
					if(a.elementType == b.elementType){
						var elementTypeA = a.elementName.toLowerCase();
						var elementTypeB = b.elementName.toLowerCase();

						if(elementTypeA < elementTypeB){ return -1; }
						if(elementTypeA > elementTypeB){ return 1; }
					}
					return 0;
				});
				for(i = 0; i < list.length; i++){
					jQuery(rowTpl.evaluate(list[i])).appendTo('#linksList');
				}

				jQuery('#linksList tr:nth-child(odd)').addClass('odd_row');
				jQuery('#linksList tr:nth-child(even)').addClass('even_row');
			}
			else{
				jQuery('#mapLinksContainer').toggle(false);
			}
		}

	};


	function CMassForm(formContainer, sysmap){
		this.sysmap = sysmap;
		this.formContainer = formContainer;

		// create form
		var tpl = new Template(jQuery('#mapMassFormTpl').html());
		this.domNode = jQuery(tpl.evaluate()).appendTo(formContainer);


		// populate icons selects
		for(var i = 0; i < this.sysmap.iconList.length; i++){
			var icon = this.sysmap.iconList[i];
			jQuery('#massIconidOff, #massIconidOn, #massIconidMaintenance, #massIconidDisabled')
					.append('<option value="' + icon.imageid + '">' + icon.name + '</option>')
		}
		jQuery('#massIconidOn, #massIconidMaintenance, #massIconidDisabled')
				.prepend('<option value="0">' + locale['S_DEFAULT'] + '</option>');

		// apply jQuery UI elements
		jQuery('#massApply, #massRemove, #massClose').button();

		var formActions = [
			{
				action: 'enable',
				value: '#massLabel',
				cond: {
					chkboxLabel: 'checked'
				}
			},
			{
				action: 'enable',
				value: '#massLabelLocation',
				cond: {
					chkboxLabelLocation: 'checked'
				}
			},
			{
				action: 'enable',
				value: '#massIconidOff',
				cond: {
					chkboxMassIconidOff: 'checked'
				}
			},
			{
				action: 'enable',
				value: '#massIconidOn',
				cond: {
					chkboxMassIconidOn: 'checked'
				}
			},
			{
				action: 'enable',
				value: '#massIconidMaintenance',
				cond: {
					chkboxMassIconidMaintenance: 'checked'
				}
			},
			{
				action: 'enable',
				value: '#massIconidDisabled',
				cond: {
					chkboxMassIconidDisabled: 'checked'
				}
			}
		];
		this.actionProcessor = new ActionProcessor(formActions);
		this.actionProcessor.process();
	}
	CMassForm.prototype = {
		sysmap: null, // reference to CMap object
		domNode: null, // jQuery object
		formContainer: null, // jQuery object

		show: function(){
			this.formContainer.draggable("option", "handle", '#massDragHandler');
			jQuery('#massElementCount').text(this.sysmap.selection.count);
			this.domNode.toggle(true);

			this.updateList();
		},

		hide: function(){
			this.domNode.toggle(false);
			jQuery(':checkbox', this.domNode).prop('checked', false);
			jQuery('select', this.domNode).each(function(){
				var select = jQuery(this);
				select.val(jQuery('option:first', select).val());
			});
			jQuery('textarea', this.domNode).val('');
			this.actionProcessor.process();
		},

		getValues: function(){
			var values = jQuery('#massForm').serializeArray(),
				data = {},
				i;

			for(i = 0; i < values.length; i++){
				if(values[i].name.match(/^chkbox_/) !== null) continue;

				data[values[i].name] = values[i].value.toString();
			}

			return data;
		},

		updateList: function(){
			var tpl = new Template(jQuery('#mapMassFormListRow').html());

			jQuery('#massList').empty();
			var list = new Array();
			for(var id in this.sysmap.selection.selements){
				var element = this.sysmap.selements[id];

				var elementTypeText = '';
				switch(element.data.elementtype){
					case '0': elementTypeText = locale['S_HOST']; break;
					case '1': elementTypeText = locale['S_MAP']; break;
					case '2': elementTypeText = locale['S_TRIGGER']; break;
					case '3': elementTypeText = locale['S_HOST_GROUP']; break;
					case '4': elementTypeText = locale['S_IMAGE']; break;
				}
				list.push({
					elementType: elementTypeText,
					elementName: element.elementName
				});
			}

			// sort by element type and then by element name
			list.sort(function(a, b){
				var elementTypeA = a.elementType.toLowerCase();
				var elementTypeB = b.elementType.toLowerCase();
				if(elementTypeA < elementTypeB){ return -1; }
				if(elementTypeA > elementTypeB){ return 1; }

				var elementNameA = a.elementName.toLowerCase();
				var elementNameB = b.elementName.toLowerCase();
				if(elementNameA < elementNameB){ return -1; }
				if(elementNameA > elementNameB){ return 1; }

				return 0;
			});
			for(var i = 0; i < list.length; i++){
				jQuery(tpl.evaluate(list[i])).appendTo('#massList');
			}

			jQuery('#massList tr:nth-child(odd)').addClass('odd_row');
			jQuery('#massList tr:nth-child(even)').addClass('even_row');
		}

	};

	function CLinkForm(formContainer, sysmap){
		this.sysmap = sysmap;
		this.formContainer = formContainer;

		this.domNode = jQuery('#linkForm');


		// apply jQuery UI elements
		jQuery('#linkApply, #linkRemove, #linkClose').button();
	}
	CLinkForm.prototype = {
		sysmap: null, // reference to CMap object
		domNode: null, // jQuery object
		formContainer: null, // jQuery object

		show: function(){
			this.domNode.toggle(true);
		},

		hide: function(){
			this.domNode.toggle(false);
		},

		getValues: function(){
			var values = jQuery('#linkForm').serializeArray(),
					data = {},
					i;

			for(i = 0; i < values.length; i++){
				data[values[i].name] = values[i].value.toString();
			}

			return data;
		},

		setValues: function(link){
			var selement1,
				tmp,
				selementid,
				selement,
				optgroups = {},
				optgroupType,
				optgroupLabel,
				optgroupDom,
				i;

			// get currenlty selected element
			for(selementid in this.sysmap.selection.selements){
				selement1 = this.sysmap.selements[selementid];
			}
			// make that selementi1 always equal to selected element and selementid2 to connected
			if(selement1.id !== link.selementid1){
				tmp = link.selementid1;
				link.selementid1 = selement1.id;
				link.selementid2 = tmp;
			}

			// populate list of elements to connect with
			jQuery('#selementid2').empty();

			// sort by type
			for(selementid in this.sysmap.selements){
				selement = this.sysmap.selements[selementid];
				if(selement.id == link.selementid1) continue;

				if(optgroups[selement.data.elementtype] === void(0)){
					optgroups[selement.data.elementtype] = [];
				}
				optgroups[selement.data.elementtype].push(selement);
			}

			for(optgroupType in optgroups){
				switch(optgroupType){
					case '0': optgroupLabel = locale['S_HOST']; break;
					case '1': optgroupLabel = locale['S_MAP']; break;
					case '2': optgroupLabel = locale['S_TRIGGER']; break;
					case '3': optgroupLabel = locale['S_HOST_GROUP']; break;
					case '4': optgroupLabel = locale['S_IMAGE']; break;
				}

				optgroupDom = jQuery('<optgroup label="'+optgroupLabel+'"></optgroup>');
				for(i = 0; i < optgroups[optgroupType].length; i++){
					optgroupDom.append('<option value="' + optgroups[optgroupType][i].id + '">' + optgroups[optgroupType][i].elementName + '</option>')
				}

				jQuery('#selementid2').append(optgroupDom);
			}


			// set values for form elements
			for(var elementName in link){
				jQuery('[name='+elementName+']', this.domNode).val(link[elementName]);
			}

			// clear triggers
			jQuery('#linkTriggerscontainer tr').remove();
			this.addTriggers(link.linktriggers);

			// trigger color change
			jQuery('.colorpicker', this.domNode).change();
		},

		addTriggers: function(triggers){
			var tpl = new Template(jQuery('#linkTriggerRow').html()),
				linkTrigger;

			for(linkTrigger in triggers){
				jQuery(tpl.evaluate(triggers[linkTrigger])).appendTo('#linkTriggerscontainer');

				jQuery('#link_triggers_'+triggers[linkTrigger].linktriggerid+'_drawtype').val(triggers[linkTrigger].drawtype);
			}
		}

	};

}(window));
