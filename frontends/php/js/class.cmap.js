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

function CMap(containerid, sysmapid, id){
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
}
CMap.prototype = {
	id:	null,							// own id
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
				'noselements':	1,
				'nolinks':	1,
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
	createLink: function(e, linkData){
		linkData = linkData || {};

		if(!isset('linkid', linkData) || (linkData['linkid'] == 0)){
			if(this.selection.count != 2){
				this.info(locale['S_TWO_ELEMENTS_SHOULD_BE_SELECTED']);
				return false;
			}

			do{
				linkData.linkid = parseInt(Math.random(1000000000) * 1000000000);
				linkData.linkid = linkData.linkid.toString();
			} while(isset(linkData.linkid, this.links));

			linkData.selementid1 = null;
			linkData.selementid2 = null;

			for(var selementid in this.selection.selements){
				if(!is_null(linkData.selementid2)) break;

				if(is_null(linkData.selementid1))
					linkData.selementid1 = selementid; else
					linkData.selementid2 = selementid;
			}
		}

		this.links[linkData.linkid] = new CLink(this, linkData);
		this.links[linkData.linkid].create();


// link created by event (need to update sysmap)
		if(!is_null(e))
			this.updateImage();
	},

	removeLinks: function(e){
		if(this.selection.count != 2){
			this.info(locale['S_PLEASE_SELECT_TWO_ELEMENTS']);
			return false;
		}

		var selementid1 = null;
		var selementid2 = null;

		for(var selementid in this.selection.selements){
			if(!is_null(selementid2)) break;

			if(is_null(selementid1))
				selementid1 = selementid; else
				selementid2 = selementid;
		}

		var linkids = this.getLinksBySelementIds(selementid1, selementid2);

		if(linkids.length == 0) return false;

		if(Confirm(locale['S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'])){
			for(var i = 0; i < linkids.length; i++){
				this.links[linkids[i]].remove();
			}

			if(!is_null(this.form))
				this.form.hide(e);

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
		// TODO: maybe it's not needed, or needed only when image sizes changed
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
			that.deleteSelectedElements();
		});

		// add link
		jQuery('#link_add').click(function(){
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
		// close element form
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

		// close element form
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
		jQuery('#formList').delegate('.link_menu', 'click', function(){
			that.linkForm.show();
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
var CLink = Class.create(CDebug, {
	id: null,
	sysmap: null,
	data: null,

	initialize: function($super, sysmap, linkData){
		this.id = linkData.linkid;
		$super('CLink['+this.id+']');
	//--
		this.sysmap = sysmap;

		this.data = {
			linkid:			0,				// ALWAYS must be a STRING (js doesn't support uint64)
			label:			'',				// Link label
			selementid1:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
			selementid2:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
			linktriggers:	{},				// linktriggers list
			tr_desc:		locale['S_SELECT'],		// default trigger caption
			drawtype:		0,
			color:			'00CC00',
			status:			1				// status of link 1 - active, 2 - passive
		};

		for(var key in linkData){
			if(is_null(linkData[key])) continue;

			if(is_number(linkData[key]))
				linkData[key] = linkData[key].toString();

			this.data[key] = linkData[key];
		}
	},

	create: function(e){
	// assign by reference!!
		this.sysmap.data.links[this.id] = this.data;
		//this.sysmap.links[this.id] = this;
	//--
	},

	update: function(params){ // params = [{'key': key, 'value':value},{'key': key, 'value':value},...]
		for(var key in params){
			if(is_null(params[key])) continue;

	//SDI(key+' : '+params[key]);
			if(key == 'selementid1'){
				if(this.data.selementid2 == params[key])
				return false;
			}

			if(key == 'selementid2'){
				if(this.data.selementid1 == params[key])
				return false;
			}

			if(is_number(params[key])) params[key] = params[key].toString();
			this.data[key] = params[key];
		}
	},

	remove: function(){
		this.sysmap.links[this.id] = null;
		delete(this.sysmap.links[this.id]);
	},

	reload: function(data){
		if(typeof(data) != "undefined")
			this.update(data);

		this.sysmap.updateImage();
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
	},

	updateLinkTrigger: function(linktriggerid, params){
		for(var key in params){
			if(is_null(params[key])) continue;

			if(is_number(params[key])) params[key] = params[key].toString();
			this.data.linktriggers[linktriggerid][key] = params[key];
		}
	},

	removeLinkTrigger: function(linktriggerid){
		this.data.linktriggers[linktriggerid] = null;
		delete(this.data.linktriggers[linktriggerid]);
	}
});


// *******************************************************************
//		SELEMENT object
// *******************************************************************
function CSelement(sysmap, selementData){
	this.sysmap = sysmap;

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
		var selementid;
		do{
			selementid = parseInt(Math.random(1000000000) * 10000000);
			selementid = selementid.toString();
		} while(typeof this.sysmap.data.selements[selementid] !== 'undefined');
		selementData.selementid = selementid;
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

	// TODO: grid snap
//	if(this.sysmap.auto_align){
//		jQuery(this.domNode).draggable('option', 'grid', [this.sysmap.grid_size, this.sysmap.grid_size]);
//	}
}
CSelement.prototype = {
	id: null, // selement id
	sysmap: null, // Parent sysmap reference
	dataFelds: [
		'elementtype', 'elementid', 'iconid_off', 'iconid_on', 'iconid_maintenance',
		'iconid_disabled', 'label', 'label_location', 'x', 'y', 'elementsubtype',  'areatype', 'width',
		'height', 'viewtype', 'urls'
	],
	data: {},
	domNode: null, // jQuery object
	selected: false,
	elementName: '', // element name

	getData: function(){
		return jQuery.extend({}, this.data, { elementName: this.elementName});
	},

	update: function(data, unsetUndefined){
		unsetUndefined = unsetUndefined || false;

		var	fieldName;


		if(typeof data.elementName !== 'undefined'){
			this.elementName = data.elementName;
		}

		for(var i = 0; i < this.dataFelds.length; i++){
			fieldName = this.dataFelds[i];

			if(typeof data[fieldName] === 'undefined'){
				if(unsetUndefined) delete this.data[fieldName];
			}
			else{
				this.data[fieldName] = data[fieldName];
			}
		}

		// if we update all data and advanced_icons turned off, reset advanced iconids
		if(unsetUndefined && (typeof data.advanced_icons === 'undefined')){
			this.data.iconid_on = '0';
			this.data.iconid_maintenance = '0';
			this.data.iconid_disabled = '0';
		}

		this.updateIcon();
		this.align();
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

		if(!force && (this.sysmap.data.grid_align == '0')) return true;

		var dims = {
			height: jQuery(this.domNode).height(),
			width: jQuery(this.domNode).width()
		};

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

		jQuery(this.domNode).css({
			top: newY + 'px',
			left: newX + 'px'
		});
	},

	updateIcon: function(){

		var oldIconClass = this.domNode.get(0).className.match(/sysmap_iconid_\d+/);
		if(oldIconClass !== null){
			this.domNode.removeClass(oldIconClass[0]);
		}

		this.domNode
				.addClass('sysmap_iconid_'+this.data.iconid_off)
				.css({
					top: this.data.y +'px',
					left: this.data.x + 'px'
				});
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
	sysmap: null, // reference to CMap object
	domNode: null, // jQuery dom object
	formContainer: null, // jQuery object

	show: function(){
		this.formContainer.draggable("option", "handle", '#formDragHandler');
		this.domNode.toggle(true);
	},

	hide: function(){
		this.domNode.toggle(false);
	},

	addUrls: function(urls){
		if((typeof urls === 'undefined') || (urls.length === 0)){
			urls = [{}];
		}

		var tpl = new Template(jQuery('#selementFormUrls').html());

		for(var i = 0; i < urls.length; i++){
			var url = urls[i];

			// generate unique urlid
			url.selementurlid = jQuery('#urlContainer tr[id^=urlrow]').length;
			while(jQuery('#urlrow_'+url.selementurlid).length){
				url.selementurlid++;
			}

			jQuery(tpl.evaluate(url)).insertBefore('#urlfooter');
		}
	},

	setValues: function(selement){
		for(var elementName in selement){
			jQuery('[name='+elementName+']', this.domNode).val(selement[elementName]);
		}

		jQuery('#advanced_icons').attr('checked', (selement.iconid_on != 0) || (selement.iconid_maintenance != 0) || (selement.iconid_disabled != 0));

		// clear urls
		jQuery('#urlContainer tr[id^=urlrow]').remove();
		this.addUrls(selement.urls);

		this.actionProcessor.process();


		this.updateList(selement.selementid);
	},

	getValues: function(){
		var values = jQuery('#selementForm').serializeArray(),
		data = {
			urls: []
		};

		var urlPattern = /^url_(\d+)_(name|url)$/;

		for(var i = 0; i < values.length; i++){
			var url = urlPattern.exec(values[i].name);
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
		for(var i = 0; i < data.urls.length; i++){
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


		if((data.elementid === 0) && (data.elementtype !== 4)){
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

		data.x = parseInt(data.x, 10);
		data.y = parseInt(data.y, 10);

		return data;

//		this.update_multiContainer(e);
	},

	updateList: function(selementid){
		var links = this.sysmap.getLinksBySelementIds(selementid),
			rowTpl,
			list,
			i,
			link,
			linkedSelementid,
			elementData,
			elementTypeText;

		if(links.length){
			jQuery('#linksList').empty();

			rowTpl = new Template(jQuery('#mapLinksRow').html());

			this.formContainer.append(jQuery('#mapLinksContainer').html());

			list = new Array();
			for(i = 0; i < links.length; i++){
				link = this.sysmap.links[links[i]].data;

				linkedSelementid = (selementid == link.selementid1) ? link.selementid2 : link.selementid1;
				elementData = this.sysmap.selements[linkedSelementid].data;

				elementTypeText = '';
				switch(elementData.elementtype){
					case '0': elementTypeText = locale['S_HOST']; break;
					case '1': elementTypeText = locale['S_MAP']; break;
					case '2': elementTypeText = locale['S_TRIGGER']; break;
					case '3': elementTypeText = locale['S_HOST_GROUP']; break;
					case '4': elementTypeText = locale['S_IMAGE']; break;
				}
				list.push({
					elementType: elementTypeText,
					elementName: elementData.elementName,
					linkid: link.id
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
				jQuery(rowTpl.evaluate(list[i])).appendTo('#formList');
			}

			jQuery('#formList tr:nth-child(odd)').addClass('odd_row');
			jQuery('#formList tr:nth-child(even)').addClass('even_row');
		}
	},

// LINK FORM
	form_link_addLinktrigger: function(linktrigger){
		var triggerid = linktrigger.triggerid;

		if(!isset('linkIndicatorsBody', this.linkForm) || empty(this.linkForm.linkIndicatorsBody)) return false;
		if(!isset('form', this.linkForm) || is_null(this.linkForm.form)) return false;

// If allready exsts just rewrite
		if($('link_triggers['+triggerid+'][triggerid]') != null){
			$('link_triggers['+triggerid+'][drawtype]').selectedIndex = (linktrigger.drawtype > 0)?(linktrigger.drawtype - 1):0;

			$('link_triggers['+triggerid+'][color]').value = linktrigger.color;
			$('lbl_link_triggers['+triggerid+'][color]').style.backgroundColor = '#'+linktrigger.color;
			return false;
		}


// ADD Linktrigger
		var e_tr_8 = document.createElement('tr');
		this.linkForm.linkIndicatorsBody.appendChild(e_tr_8);


		var e_td_9 = document.createElement('td');
		e_tr_8.appendChild(e_td_9);


// HIDDEN initialization
		if(isset('linktriggerid', linktrigger)){
			var e_input_10 = document.createElement('input');
			e_input_10.setAttribute('name',"link_triggers["+triggerid+"][linktriggerid]");
			e_input_10.setAttribute('type',"hidden");
			e_input_10.setAttribute('value',linktrigger.linktriggerid);
			e_input_10.setAttribute('id',"link_triggers["+triggerid+"][linktriggerid]");
			e_td_9.appendChild(e_input_10);
		}

		var e_input_10 = document.createElement('input');
		e_input_10.setAttribute('name',"link_triggers["+triggerid+"][triggerid]");
		e_input_10.setAttribute('id',"link_triggers["+triggerid+"][triggerid]");
		e_input_10.setAttribute('type',"hidden");
		e_input_10.setAttribute('value',linktrigger.triggerid);
		e_td_9.appendChild(e_input_10);

		var e_input_10 = document.createElement('input');
		e_input_10.setAttribute('name',"link_triggers["+triggerid+"][desc_exp]");
		e_input_10.setAttribute('id',"link_triggers["+triggerid+"][desc_exp]");
		e_input_10.setAttribute('type',"hidden");
		e_input_10.setAttribute('value',linktrigger.desc_exp);
		e_td_9.appendChild(e_input_10);

//-----
		var linktriggerid = isset('linktriggerid', linktrigger)?linktrigger.linktriggerid:0;

		var e_input_10 = document.createElement('input');
		e_input_10.setAttribute('type',"checkbox");
		e_input_10.setAttribute('name',"link_triggerids");
		e_input_10.setAttribute('value',triggerid);
		e_input_10.className = "checkbox";
		e_td_9.appendChild(e_input_10);

// Triggers
		var e_td_9 = document.createElement('td');
		e_tr_8.appendChild(e_td_9);


		var e_span_10 = document.createElement('span');
		e_span_10.appendChild(document.createTextNode(linktrigger.desc_exp));
		e_td_9.appendChild(e_span_10);

//	e_span_10.className = "link";
//	var url = 'popup_link_tr.php?form=1&mapid='+this.id+'&triggerid='+linktrigger.triggerid+'&drawtype='+linktrigger.drawtype+'&color='+linktrigger.color
//	addListener(e_span_10, 'click', function(){ PopUp(url,640, 480, 'ZBX_Link_Indicator'); });

// LINE
		var e_select_10 = document.createElement('select');

		var e_td_9 = document.createElement('td');
		e_tr_8.appendChild(e_td_9);
		e_td_9.appendChild(e_select_10);

		e_select_10.setAttribute('id',"link_triggers["+triggerid+"][drawtype]");
		e_select_10.setAttribute('name', 'link_triggers['+triggerid+'][drawtype]');
		e_select_10.className = 'input';

// items
		var e_option_11 = document.createElement('option');
		e_option_11.setAttribute('value', 0);
		e_option_11.appendChild(document.createTextNode(locale['S_LINE']));
		e_select_10.appendChild(e_option_11);

		var e_option_11 = document.createElement('option');
		e_option_11.setAttribute('value', 2);
		e_option_11.appendChild(document.createTextNode(locale['S_BOLD_LINE']));
		e_select_10.appendChild(e_option_11);

		var e_option_11 = document.createElement('option');
		e_option_11.setAttribute('value', 3);
		e_option_11.appendChild(document.createTextNode(locale['S_DOT']));
		e_select_10.appendChild(e_option_11);

		var e_option_11 = document.createElement('option');
		e_option_11.setAttribute('value', 4);
		e_option_11.appendChild(document.createTextNode(locale['S_DASHED_LINE']));
		e_select_10.appendChild(e_option_11);
//--
		e_select_10.selectedIndex = (linktrigger.drawtype > 0)?(linktrigger.drawtype - 1):0;

// COLOR
		var e_td_9 = document.createElement('td');
		e_tr_8.appendChild(e_td_9);

		var e_input_22 = document.createElement('input');
		e_input_22.setAttribute('style',"margin-top: 0px; margin-bottom: 0px;");
		e_input_22.setAttribute('onchange',"set_color_by_name('link_triggers["+triggerid+"][color]',this.value)");
		e_input_22.setAttribute('maxlength',"6");
		e_input_22.setAttribute('value',linktrigger.color);
		e_input_22.setAttribute('size',"7");
		e_input_22.setAttribute('id',"link_triggers["+triggerid+"][color]");
		e_input_22.setAttribute('name',"link_triggers["+triggerid+"][color]");
		e_input_22.className = "input";
		e_td_9.appendChild(e_input_22);

		var e_div_10 = document.createElement('div');
//this.linkForm.colorPicker = e_div_10;

		e_div_10.setAttribute('title', '#'+linktrigger.color);
		e_div_10.setAttribute('id',"lbl_link_triggers["+triggerid+"][color]");
		e_div_10.setAttribute('name',"lbl_link_triggers["+triggerid+"][color]");
		e_div_10.className = "pointer";
		addListener(e_div_10, 'click', function(){show_color_picker("link_triggers["+triggerid+"][color]");});
		// e_div_10.setAttribute('onclick',"javascript: show_color_picker('color')");

		e_div_10.style.marginLeft = '2px';
		e_div_10.style.border = '1px solid black';
		e_div_10.style.display = 'inline';
		e_div_10.style.width = '10px';
		e_div_10.style.height = '10px';
		e_div_10.style.textDecoration = 'none';
		e_div_10.style.backgroundColor = '#'+linktrigger.color;

		e_div_10.innerHTML = '&nbsp;&nbsp;&nbsp;';
		e_td_9.appendChild(e_div_10);
	},

	form_link_save: function(e){
		var linkid = this.linkForm.linkid.value;
		if(!isset(linkid, this.sysmap.data.links)) return false;

		var maplink = this.sysmap.data.links[linkid];


		var params = {};

// Label
		params.label = this.linkForm.linklabel.value;

// Selementid1
		params.selementid1 = this.linkForm.selementid1.options[this.linkForm.selementid1.selectedIndex].value;

// Selementid2
		params.selementid2 = this.linkForm.selementid2.options[this.linkForm.selementid2.selectedIndex].value;

// Type OK
		params.drawtype = this.linkForm.drawtype.options[this.linkForm.drawtype.selectedIndex].value;

// Color
		params.color = this.linkForm.color.value;


// LINK INDICATORS
		for(var linktriggerid in maplink.linktriggers){
			this.sysmap.links[maplink.linkid].removeLinkTrigger(linktriggerid);
		}

		var triggerid = null;
		var linktrigger = {};
		var linktriggerid = null;

		var indicators = $$('input[name=link_triggerids]');
		for(var i=0; i<indicators.length; i++){
			if(!isset(i, indicators)) continue;

			linktrigger = {};
			triggerid = indicators[i].value;

			linktrigger.triggerid = $('link_triggers['+triggerid+'][triggerid]').value;
			linktrigger.desc_exp = $('link_triggers['+triggerid+'][desc_exp]').value;

			var dom_drawtype = $('link_triggers['+triggerid+'][drawtype]');

			linktrigger.drawtype = dom_drawtype.options[dom_drawtype.selectedIndex].value;

			linktrigger.color = $('link_triggers['+triggerid+'][color]').value;

			linktriggerid = $('link_triggers['+triggerid+'][linktriggerid]');

			if(!is_null(linktriggerid))
				linktrigger.linktriggerid = linktriggerid.value;

			this.sysmap.links[linkid].createLinkTrigger(linktrigger);
		}
//--

		this.sysmap.links[linkid].update(params);
		this.update_linkContainer(e);
		this.sysmap.updateImage();
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
		jQuery('option', this.domNode).prop('selected', false);
		jQuery('textarea', this.domNode).val('');
	},

	getValues: function(){
		if(this.selementForm.massEdit.chkboxLabel.checked)
			params.label = this.selementForm.label.value;

// Label Location
		if(this.selementForm.massEdit.chkboxLabelLocation.checked)
			params.label_location = parseInt(this.selementForm.label_location.selectedIndex, 10) - 1;

// Icon OK
		if(this.selementForm.massEdit.chkboxIconid_off.checked)
			params.iconid_off = this.selementForm.iconid_off.options[this.selementForm.iconid_off.selectedIndex].value;

// Icon PROBLEM
		if(this.selementForm.massEdit.chkboxIconid_on.checked)
			params.iconid_on = this.selementForm.iconid_on.options[this.selementForm.iconid_on.selectedIndex].value;

// Icon MAINTENANCE
		if(this.selementForm.massEdit.chkboxIconid_maintenance.checked)
			params.iconid_maintenance = this.selementForm.iconid_maintenance.options[this.selementForm.iconid_maintenance.selectedIndex].value;

// Icon DISABLED
		if(this.selementForm.massEdit.chkboxIconid_disabled.checked)
			params.iconid_disabled = this.selementForm.iconid_disabled.options[this.selementForm.iconid_disabled.selectedIndex].value;

		for(var selementid in this.sysmap.selection.selements){
			if(!isset(selementid, this.sysmap.selements)) continue;
			this.sysmap.selements[selementid].reload(params);
		}

		this.sysmap.updateImage();
		this.update_multiContainer(e);
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
	jQuery('#massApply, #massRemove, #massClose').button();
}
CLinkForm.prototype = {
	sysmap: null, // reference to CMap object
	domNode: null, // jQuery object
	formContainer: null, // jQuery object

	show: function(){
		this.domNode.toggle(true);

//		this.updateList();
	},

	hide: function(){
		this.domNode.toggle(false);
	},

	updateList: function(){
		var tpl = new Template(jQuery('#mapMassFormListRow').html());

		jQuery('#massList').empty();
		var list = new Array();
		for(var id in this.sysmap.selection.selements){
			var elementData = this.sysmap.selements[id].data;

			var elementTypeText = '';
			switch(elementData.elementtype){
				case '0': elementTypeText = locale['S_HOST']; break;
				case '1': elementTypeText = locale['S_MAP']; break;
				case '2': elementTypeText = locale['S_TRIGGER']; break;
				case '3': elementTypeText = locale['S_HOST_GROUP']; break;
				case '4': elementTypeText = locale['S_IMAGE']; break;
			}
			list.push({
				elementType: elementTypeText,
				elementName: elementData.elementName
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
