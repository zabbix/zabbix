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
			.appendTo('body');

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

			for(var selementid in this.data.selements){
				this.selements[selementid] = new CSelement(this, this.data.selements[selementid]);
			}
			for(var linkid in this.data.links){
				this.createLink(null, this.data.links[linkid]);
			}

			this.updateImage();

			this.iconList = result.data.iconList;
			this.form = new CElementForm(this.formContainer, this);
		}, this),
		error: function(){
			throw('Get selements FAILED.');
		}
	});


	this.container.selectable({
		stop: jQuery.proxy(function(){
			var selected = jQuery('.ui-selected', this.container);
			var ids = new Array();
			for(var i = 0; i < selected.length; i++){
				ids.push(jQuery(selected[i]).data('id'));
			}
			this.selectElements(ids);
		}, this)
	});

	// bind actions after data recieved and dom created
	jQuery.when(ajaxData).then(jQuery.proxy(this.bindActions, this));

}
CMap.prototype = {
	id:	null,							// own id
	data: {},							// local sysmap DB :)
	iconList: {}, // list of available icons [{imageid: name}, ...]

	container: null,					// selements and links HTML container (D&D droppable area)
	formContainer: null, // jQuery dom object contining forms
	mapimg: null,						// HTML element map img
	form:	null,

	selements: {},					// map selements array
	links:	{},						// map links array

	selection: {
		count: 0,						// number of selected elements
		selements: {}					// selected SElements
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

		var that = this;
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
			success: function(data){
				that.mapimg.attr('src', 'imgstore.php?imageid=' + data.result);
			},
			error: function(){
				alert('Map image update failed');
			}
		});
	},

// ---------- ELEMENTS ------------------------------------------------------------------------------------
	deleteSelectedElements: function(){
		if(Confirm(locale['S_DELETE_SELECTED_ELEMENTS_Q'])){
			for(var selementid in this.selection.selements){
				if(!isset(selementid, this.selements)) continue;

				this.removeLinksBySelementId(selementid);
				this.selements[selementid].remove();
			}

			if(!is_null(this.form))
				this.form.hide(e);

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

// update form
		if(!is_null(this.form))
			this.form.update_linkContainer(e);

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
			that.setGridSize(jQuery(this).val());
		});

		// toggle autoalign
		jQuery('#gridautoalign').click(function(){
			var autoAlign = that.switchAutoAlign();
			jQuery(this).html(autoAlign == '1' ? locale['S_ON'] : locale['S_OFF']);
		});

		// toggle grid visibility
		jQuery('#gridshow').click(function(){
			var showGrid = that.switchGridView();
			jQuery(this).html(showGrid == '1' ? locale['S_SHOWN'] : locale['S_HIDDEN']);
		});

		// perform align all
		jQuery('#gridalignall').click(function(){
			that.alignAll()
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
			that.createLink();
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
		jQuery('#elementClose').click(jQuery.proxy(this.handlerCloseSelementForm, this));
		jQuery('#elementDelete').click(jQuery.proxy(this.deleteSelectedElements, this));
		jQuery('#elementApply').click(jQuery.proxy(function(){
			if(this.selection.count != 1) throw 'Try to single update element, when more than one selected.';

			for(var selementid in this.selection.selements){
				this.selements[selementid].update(this.form.getValues());
			}
		}, this));
		jQuery('#newSelementUrl').click(jQuery.proxy(function(){
			this.form.addUrls();
		}, this));
	},

	setGridSize: function(value){
		if(this.data.grid_size != value){
			this.data.grid_size = value;
			this.updateImage();
		}
	},

	switchAutoAlign: function(){
		this.data.grid_align = this.data.grid_align == '1' ? '0' : '1';
		return this.data.grid_align;
	},

	switchGridView: function(){
		this.data.grid_show = this.data.grid_show == '1' ? '0' : '1';
		this.updateImage();
		return this.data.grid_show;
	},

	alignAll: function(){
		for(var selementid in this.selements){
			this.selements[selementid].align(true);
		}

		this.updateImage();
	},

	clearSelection: function(){
		for(var id in this.selection.selements){
			this.selection.count--;
			this.selements[id].toggleSelect(false);
			delete this.selection.selements[id];
		}
	},

	toggleForm: function(){
		if(this.selection.count == 0){
			this.form.hide();
		}
		else if(this.selection.count == 1){
			for(var selementid in this.selection.selements){
				this.form.setValues(this.selements[selementid].data);
				this.form.show();
			}
		}
		else{

		}
	},

	handlerCloseSelementForm: function(){
		this.clearSelection();
		this.form.hide();
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
	}
/*
	handlerElementSelect: function(event){
		var selementid = jQuery(event.target).data('id');

		// if we click on one already selected element, we should not deselect it
		if(!(event.ctrlKey || event.shiftKey) && !((this.selection.count == 1) && (typeof this.selection.selements[selementid] != 'undefined'))){
			this.clearSelection();
		}

		var selected = this.selements[selementid].toggleSelect();
		if(selected){
			this.selection.count++;
			this.selection.selements[selementid] = selementid;
		}
		else{
			this.selection.count--;
			delete this.selection.selements[selementid];
		}

		this.toggleForm();
	}
*/
};


// *******************************************************************
//		LINK object
// *******************************************************************
var CLink = Class.create(CDebug, {
	id:				null,			// selement id
	sysmap:			null,			// Parent sysmap reference
	data:			null,

	initialize: function($super, sysmap, linkData){
		this.id = linkData.linkid;
		$super('CLink['+this.id+']');
	//--
		this.sysmap = sysmap;

		this.data = {
			linkid:			0,				// ALWAYS must be a STRING (js doesn't support uint64)
			label:			'',				// Link label
			label_expanded: '',				// Link label (Expand macros)
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
var CSelement = function(sysmap, selementData){
	this.sysmap = sysmap;

	if(selementData){
		this.image = selementData.image;
		delete selementData.image;
	}
	else{
		selementData = {
			elementtype: 4,			// 5-UNDEFINED
			elementid: 0,			// ALWAYS must be a STRING (js doesn't support uint64)
			elementName: '',			// element name
			iconid_off: 0,			// ALWAYS must be a STRING (js doesn't support uint64)
			iconid_on: 0,			// ALWAYS must be a STRING (js doesn't support uint64)
			iconid_maintenance: 0,			// ALWAYS must be a STRING (js doesn't support uint64)
			iconid_disabled: 0,			// ALWAYS must be a STRING (js doesn't support uint64)
			label: locale['S_NEW_ELEMENT'],	// Element label
			label_expanded: locale['S_NEW_ELEMENT'],	// Element label macros expanded
			x: 0,
			y: 0,
			elementsubtype: 0,			// host group view types: 0 - host group, 1 - host group elements
			areatype: 0,			// how to show area: 0 - fit to map, 1 - custom size
			width: 200,		// area height
			height: 200,		// area width
			viewtype: 0,			// how to align icons inside area: 0 - 'left->right, top->bottom, evenly aligned'
			urls: {}
		};

		// generate random selementid
		do{
			selementData.selementid = parseInt(Math.random(1000000000) * 10000000);
			selementData.selementid = selementData.selementid.toString();
		} while(isset(selementData.selementid, this.sysmap.data.selements));

		// set default map label location
		selementData.label_location = this.sysmap.data.label_location;

		// take first available icon
		this.image = this.sysmap.iconList[0].imageid;
	}

	this.update(selementData);

	this.id = this.data.selementid;

// assign by reference
	this.sysmap.data.selements[this.id] = this.data;

	// create dom
	this.domNode = jQuery('<div></div>')
			.appendTo(this.sysmap.container)
			.attr("id", '#selement_'+this.data.selementid)
			.addClass('pointer sysmap_element sysmap_iconid_'+this.image)
			.css({
				top: this.data.y +'px',
				left: this.data.x + 'px'
			})
			.data('id', this.id);

	jQuery(this.domNode).draggable({
		containment: 'parent',
		opacity: 0.5,
		helper: 'clone',
		stop: jQuery.proxy(function(event, data){
			this.data.x = data.position.left;
			this.data.y = data.position.top;

			this.align();

			this.sysmap.updateImage();
		}, this)
	});


	// TODO: grid snap
//	if(this.sysmap.auto_align){
//		jQuery(this.domNode).draggable('option', 'grid', [this.sysmap.grid_size, this.sysmap.grid_size]);
//	}
};
CSelement.prototype = {
	id: null, // selement id
	sysmap: null, // Parent sysmap reference
	data: {},
	domNode: null, // dom reference to html obj
	selected: false, // element is not selected
	image: null,

	update: function(params){
		this.data = params;

		if(this.data.elementsubtype == '1'){
			this.domNode.css({
				width: this.data.width,
				height: this.data.height
			});
		}
	},

	remove: function(){
		this.toggleSelect(false,false);

		jQuery(this.domNode).draggable('destroy');
		jQuery(this.domNode).remove();

		this.sysmap.selements[this.id] = null;
		this.sysmap.data.selements[this.id] = null;
		delete(this.sysmap.selements[this.data.selementid]);
		delete(this.sysmap.data.selements[this.id]);

		this.domNode = null;
		this.selected = null;
		this.data = null;
		delete(this.data);

		this.sysmap = null;
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
			top: newY+"px",
			left: newX+"px"
		});
	},

	updateIcon: function(){
		var url = new Curl(location.href);

		var that = this;
		jQuery.ajax({
			url: url.getPath()+'?output=json&sid='+url.getArgument('sid'),
			type: "POST",
			data: {
				favobj: "selements",
				sysmapid: this.sysmap.sysmapid,
				action: "getIcon",
				selements: Object.toJSON([this.data]) // TODO: remove Prototype object
			},
			dataType: "json",
			success: function(data){
				that.image = data.image;
				jQuery(that.domNode).addClass('sysmap_iconid_'+data.image);
			}
		});
	}

};

// *******************************************************************
//		FORM object
// *******************************************************************
function CElementForm(formContainer, sysmap){
	this.sysmap = sysmap;

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
				.append('<option value="' + icon.imageid + '">' + icon.name + '</option>')
	}

	// apply jQuery UI elements
	jQuery('#elementApply, #elementRemove, #elementClose').button();


	// meke form draggable
	jQuery(this.domNode).draggable({
		handle: jQuery('#formDragHandler'),
		containment: [0,0,3200,3200]
	});


	// create action processor
	var formActions = [
		{
			action: 'show',
			value: '#subtypeRow, #hostGroupSelectRow',
			cond: {
				elementtype: '3'
			}
		},
		{
			action: 'show',
			value: '#hostSelectRow',
			cond: {
				elementtype: '0'
			}
		},
		{
			action: 'show',
			value: '#triggerSelectRow',
			cond: {
				elementtype: '2'
			}
		},
		{
			action: 'show',
			value: '#mapSelectRow',
			cond: {
				elementtype: '1'
			}
		},
		{
			action: 'show',
			value: '#areaTypeRow, #areaPlacingRow',
			cond: {
				elementtype: '3',
				subtypeHostGroupElements: 'checked'
			}
		},
		{
			action: 'show',
			value: '#areaSizeRow',
			cond: {
				elementtype: '3',
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
		}
	];
	this.actionProcessor = new ActionProcessor(formActions);
	this.actionProcessor.process();
}
CElementForm.prototype = {
	sysmap: null, // reference to CMap object
	domNode: null, // jQuery dom object

	show: function(){
		this.domNode.toggle(true);
	},

	hide: function(){
		this.domNode.toggle(false);
	},

	addUrls: function(urls){
		urls = urls || {empty: {}};
		var tpl = new Template(jQuery('#selementFormUrls').html());

		for(var i in urls){
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
		this.domNode.populate(selement);
		jQuery('#advanced_icons').attr('checked', (selement.iconid_on != 0) || (selement.iconid_maintenance != 0) || (selement.iconid_disabled != 0));

		// clear urls
		jQuery('#urlContainer tr[id^=urlrow]').remove();
		if(!jQuery.isPlainObject(selement.urls)){
			selement.urls = null;
		}
		this.addUrls(selement.urls);

		this.actionProcessor.process();
	},

//  Multi Container  ------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------------------------

	create_multiContainer: function(e){
// var initialization
		this.multiContainer = {};


		var e_div_1 = document.createElement('div');
		this.multiContainer.container = e_div_1;
		e_div_1.setAttribute('id',"multiContainer");
		e_div_1.style.overflow = 'auto';

//	e_td_4.appendChild(e_div_1);
	},

	update_multiContainer: function(e){
// Create if not exists
		if(is_null($('multiContainer'))){
// HEADER
			var e_table_1 = document.createElement('table');
			e_table_1.setAttribute('cellspacing',"0");
			e_table_1.setAttribute('cellpadding',"1");
			e_table_1.className = 'header';


			var e_tbody_2 = document.createElement('tbody');
			e_table_1.appendChild(e_tbody_2);


			var e_tr_3 = document.createElement('tr');
			e_tbody_2.appendChild(e_tr_3);


			var e_td_4 = document.createElement('td');
			e_td_4.className = 'header_l';
			e_td_4.appendChild(document.createTextNode(locale['S_MAP_ELEMENTS']));
			e_tr_3.appendChild(e_td_4);


			var e_td_4 = document.createElement('td');
			e_td_4.setAttribute('align',"right");
			e_td_4.className = 'header_r';

			e_tr_3.appendChild(e_td_4);

			$('divSelementForm').appendChild(e_table_1);
//-----------

			this.create_multiContainer(e);
			$('divSelementForm').appendChild(this.multiContainer.container);
//		$('divSelementForm').appendChild(document.createElement('br'));
		}
//---

		var e_table_1 = document.createElement('table');
		e_table_1.setAttribute('cellSpacing',"1");
		e_table_1.setAttribute('cellPadding',"3");
		e_table_1.className = "tableinfo";


		var e_tbody_2 = document.createElement('tbody');
		e_table_1.appendChild(e_tbody_2);


		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "header";
		e_tbody_2.appendChild(e_tr_3);


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_LABEL']));


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_TYPE']));


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_DESCRIPTION']));


		var count = 0;
		var selement = null;
		for(var selementid in this.sysmap.selection.selements){
			if(!isset(selementid, this.sysmap.selements)) continue;

			count++;
			selement = this.sysmap.data.selements[selementid];

			if(count > 4) this.multiContainer.container.style.height = '127px';
			else this.multiContainer.container.style.height = 'auto';

			var e_tr_3 = document.createElement('tr');
			e_tbody_2.appendChild(e_tr_3);


			var e_td_4 = document.createElement('td');
			e_tr_3.appendChild(e_td_4);


			var e_span_5 = document.createElement('span');
//		e_span_5.setAttribute('href',"sysmap.php?sysmapid=100100000000002&form=update&selementid=100100000000004&sid=791bd54e24454e2b");
//		e_span_5.className = "link";
			e_td_4.appendChild(e_span_5);

			e_span_5.appendChild(document.createTextNode(selement.label_expanded));

			var elementtypeText = '';
			switch(selement.elementtype){
				case '0':elementtypeText = locale['S_HOST'];break;
				case '1':elementtypeText = locale['S_MAP'];break;
				case '2':elementtypeText = locale['S_TRIGGER'];break;
				case '3':elementtypeText = locale['S_HOST_GROUP'];break;
				case '4':
				default:elementtypeText = locale['S_IMAGE'];break;
			}

			var e_td_4 = document.createElement('td');
			e_td_4.appendChild(document.createTextNode(elementtypeText));
			e_tr_3.appendChild(e_td_4);

			var e_td_4 = document.createElement('td');
			e_td_4.appendChild(document.createTextNode(selement.elementName));
			e_tr_3.appendChild(e_td_4);
		}


		$(this.multiContainer.container).update(e_table_1);
	},

// LINK CONTAINER
//**************************************************************************************************************************************************
	create_linkContainer: function(e, selementid){
// var initialization
		this.linkContainer = {};


// Down Stream

		var e_div_1 = document.createElement('div');
		this.linkContainer.container = e_div_1;
		e_div_1.setAttribute('id',"linkContainer");
		e_div_1.style.overflow = 'auto';

//	e_td_4.appendChild(e_div_1);
	},

	update_linkContainer: function(e){
// Create if not exists
		if(is_null($('linkContainer'))){
// HEADER
			var e_table_1 = document.createElement('table');
			e_table_1.setAttribute('cellspacing',"0");
			e_table_1.setAttribute('cellpadding',"1");
			e_table_1.className = 'header';


			var e_tbody_2 = document.createElement('tbody');
			e_table_1.appendChild(e_tbody_2);


			var e_tr_3 = document.createElement('tr');
			e_tbody_2.appendChild(e_tr_3);


			var e_td_4 = document.createElement('td');
			e_td_4.className = 'header_l';
			e_td_4.appendChild(document.createTextNode(locale['S_CONNECTORS']));
			e_tr_3.appendChild(e_td_4);


			var e_td_4 = document.createElement('td');
			e_td_4.setAttribute('align',"right");
			e_td_4.className = 'header_r';

			e_tr_3.appendChild(e_td_4);

			$('divSelementForm').appendChild(e_table_1);
//-----------

			this.create_linkContainer(e);
			$('divSelementForm').appendChild(this.linkContainer.container);
//		$('divSelementForm').appendChild(document.createElement('br'));
		}
//---

		var e_table_1 = document.createElement('table');
		e_table_1.setAttribute('cellSpacing',"1");
		e_table_1.setAttribute('cellPadding',"3");
		e_table_1.className = "tableinfo";


		var e_tbody_2 = document.createElement('tbody');
		e_table_1.appendChild(e_tbody_2);


		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "header";
		e_tbody_2.appendChild(e_tr_3);


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_LINK']));


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_ELEMENT']+' 1'));


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_ELEMENT']+' 2'));


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
		e_td_4.appendChild(document.createTextNode(locale['S_LINK_STATUS_INDICATOR']));

		var selementid = 0;
		var linkids = {};
		for(var selementid in this.sysmap.selection.selements){
			if(!isset(selementid, this.sysmap.selements)) continue;

			var current_linkids = this.sysmap.getLinksBySelementIds(selementid);
			for(var i=0; i<current_linkids.length;i++){
				linkids[current_linkids[i]] = current_linkids[i];
			}
		}

		this.linkContainer.container.style.height = 'auto';

		var count = 0;
		var maplink = null;
		for(var linkid in linkids){
			if(!isset(linkid, this.sysmap.links)) continue;

			count++;
			maplink = this.sysmap.data.links[linkid];

			if(count > 4) this.linkContainer.container.style.height = '120px';

			var e_tr_3 = document.createElement('tr');
			e_tbody_2.appendChild(e_tr_3);


			var e_td_4 = document.createElement('td');
			e_tr_3.appendChild(e_td_4);


			var e_span_5 = document.createElement('span');
			e_span_5.className = "link";
			addListener(e_span_5, 'click', this.form_link_update.bindAsEventListener(this, linkid));
			e_span_5.appendChild(document.createTextNode(locale['S_LINK']+' '+count));
			e_td_4.appendChild(e_span_5);


			var e_td_4 = document.createElement('td');
			e_td_4.appendChild(document.createTextNode(this.sysmap.data.selements[maplink.selementid1].label_expanded));
			e_tr_3.appendChild(e_td_4);


			var e_td_4 = document.createElement('td');
			e_td_4.appendChild(document.createTextNode(this.sysmap.data.selements[maplink.selementid2].label_expanded));
			e_tr_3.appendChild(e_td_4);


			var e_td_4 = document.createElement('td');
			for(var linktriggerid in maplink.linktriggers){
				if(empty(maplink.linktriggers[linktriggerid])) continue;

				e_td_4.appendChild(document.createTextNode(maplink.linktriggers[linktriggerid].desc_exp));
				e_td_4.appendChild(document.createElement('br'));
			}
			e_tr_3.appendChild(e_td_4);
		}

		if(count == 0){
			var e_tr_3 = document.createElement('tr');
			e_tbody_2.appendChild(e_tr_3);

			var e_td_4 = document.createElement('td');
			e_td_4.setAttribute('colSpan',4);
			e_td_4.className = 'center';
			e_td_4.appendChild(document.createTextNode(locale['S_NO_LINKS']));
			e_tr_3.appendChild(e_td_4);
		}

		$(this.linkContainer.container).update(e_table_1);
	},



	form_selement_save: function(e){
		var params = {};

		if(this.sysmap.selection.count == 1){
			var selementid = this.selementForm.selementid.value;

			params.elementtype = this.selementForm.elementtype.selectedIndex;
			params.label = this.selementForm.label.value;
			params.label_location = parseInt(this.selementForm.label_location.selectedIndex, 10) - 1;

// Element
			params.elementid = this.selementForm.elementid.value;
			params.elementName = this.selementForm.elementName.value;

			if((params.elementid == 0) && (params.elementtype != 4)){
				switch(params.elementtype.toString()){
//host
					case '0':this.info('Host is not selected.');return false;break;
//map
					case '1':this.info('Map is not selected.');return false;break;
//tr
					case '2':this.info('Trigger is not selected.');return false;break;
//hg
					case '3':this.info('Host group is not selected.');return false;break;
// image
					case '4':
					default:
				}
			}

			params.iconid_off = this.selementForm.iconid_off.options[this.selementForm.iconid_off.selectedIndex].value;
			params.iconid_on = this.selementForm.iconid_on.options[this.selementForm.iconid_on.selectedIndex].value;
			params.iconid_maintenance = this.selementForm.iconid_maintenance.options[this.selementForm.iconid_maintenance.selectedIndex].value;
			params.iconid_disabled = this.selementForm.iconid_disabled.options[this.selementForm.iconid_disabled.selectedIndex].value;

// Advanced icons
			if(!this.selementForm.advanced_icons.checked){
				params.iconid_on = 0;
				params.iconid_maintenance = 0;
				params.iconid_disabled = 0;
			}

// X & Y
			var dims = {
				height: jQuery(this.sysmap.selements[selementid].domNode).height(),
				width: jQuery(this.sysmap.selements[selementid].domNode).width()
			};

			params.x = parseInt(this.selementForm.x.value, 10);
			params.y = parseInt(this.selementForm.y.value, 10);

			if((params.x+dims.width) > this.sysmap.width) params.x = this.sysmap.width - dims.width;
			else if(params.x < 0) params.x = 0;

			if((params.y+dims.height) > this.sysmap.height) params.y = this.sysmap.height - dims.height;
			else if(params.y < 0) params.y = 0;

			this.selementForm.x.value = params.x;
			this.selementForm.y.value = params.y;

// URLS
			params.urls = {};
			var urlrows = $(this.selementForm.urls).select('tr[id^=urlrow]');

			//checking for duplicate URL names
			var urlNameList = new Array();

			for(var i=0; i < urlrows.length; i++){
				var urlid = urlrows[i].id.split('_')[1];

				var url = {
					'sysmapelementurlid': urlid,
					'name': $('url_name_'+urlid).value,
					'url': $('url_url_'+urlid).value
				};

				if(empty(url.name) && empty(url.url)) continue;

				if(typeof(urlNameList[url.name]) == 'undefined'){
					urlNameList[url.name] = true;
				}
				else{
					//element with this name already exists
					alert(locale['S_EACH_URL_SHOULD_HAVE_UNIQUE'] + " '" + url.name + "'.");
					return false;
				}

				if(empty(url.name) || empty(url.url)){
					alert(locale['S_INCORRECT_ELEMENT_MAP_LINK']);
					return false;
				}

				params.urls[url.name] = url;
			}

			this.sysmap.selements[selementid].reload(params);
		}
		else{
// Label
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
		}

		this.sysmap.updateImage();
		this.update_multiContainer(e);
//	this.formHide();
	},

// LINK FORM
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************

	form_link_hide: function(e){
		if(!isset('form', this.linkForm) || empty(this.linkForm.form)) return false;

		this.linkForm.form.parentNode.removeChild(this.linkForm.form);
		this.linkForm.form = null;
	},

	form_link_create: function(e){
		var e_form_1 = document.createElement('form');
		this.linkForm.form = e_form_1;

		e_form_1.setAttribute('id',"linkForm");
		e_form_1.setAttribute('name',"linkForm");
		e_form_1.setAttribute('accept-charset',"utf-8");
		e_form_1.setAttribute('action',"sysmap.php");
		e_form_1.setAttribute('method',"post");


		var e_table_2 = document.createElement('table');
		e_table_2.setAttribute('cellSpacing',"0");
		e_table_2.setAttribute('cellPadding',"1");
		e_table_2.setAttribute('align',"center");
		e_table_2.className = "formtable";
		e_table_2.style.width = '100%';
		e_form_1.appendChild(e_table_2);


		var e_tbody_3 = document.createElement('tbody');
		e_table_2.appendChild(e_tbody_3);


		var e_tr_4 = document.createElement('tr');
		e_tr_4.className = "header";
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.setAttribute('colSpan',"2");
		e_td_5.className = "form_row_first";
		e_tr_4.appendChild(e_td_5);

		e_td_5.appendChild(document.createTextNode(locale['S_EDIT_CONNECTOR']));

// HIDDEN
		var e_input_4 = document.createElement('input');
		this.linkForm.linkid = e_input_4;
		e_input_4.setAttribute('type',"hidden");
		e_input_4.setAttribute('value',"0");
		e_input_4.setAttribute('id',"linkid");
		e_input_4.setAttribute('name',"linkid");
		e_tbody_3.appendChild(e_input_4);


// LABEL
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.appendChild(document.createTextNode(locale['S_LABEL']));
		e_tr_4.appendChild(e_td_5);


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		var e_textarea_6 = document.createElement('textarea');
		this.linkForm.linklabel = e_textarea_6;

		e_textarea_6.setAttribute('cols',"48");
		e_textarea_6.setAttribute('rows',"4");
		e_textarea_6.setAttribute('name',"linklabel");
		e_textarea_6.setAttribute('id',"linklabel");
		e_textarea_6.className = "input";
		e_td_5.appendChild(e_textarea_6);


// SELEMENTID1
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.appendChild(document.createTextNode(locale['S_ELEMENT']+' 1'));
		e_tr_4.appendChild(e_td_5);


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		var e_select_6 = document.createElement('select');
		this.linkForm.selementid1 = e_select_6;
		e_select_6.setAttribute('size',"1");
		e_select_6.className = "input";
		e_select_6.setAttribute('name',"selementid1");
		e_select_6.setAttribute('id',"selementid1");
		e_td_5.appendChild(e_select_6);

// SELEMENTID2
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		e_td_5.appendChild(document.createTextNode(locale['S_ELEMENT']+' 2'));


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		var e_select_6 = document.createElement('select');
		this.linkForm.selementid2 = e_select_6;
		e_select_6.setAttribute('size',"1");
		e_select_6.className = "input";
		e_select_6.setAttribute('name',"selementid2");
		e_select_6.setAttribute('id',"selementid2");
		e_td_5.appendChild(e_select_6);


// LINK STATUS INDICATORS
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.appendChild(document.createTextNode(locale['S_LINK_INDICATORS']));
		e_tr_4.appendChild(e_td_5);


		var e_td_5 = document.createElement('td');
		this.linkForm.linkIndicatorsTable = e_td_5;

		e_tr_4.appendChild(e_td_5);

// LINE TYPE OK
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);
		e_td_5.appendChild(document.createTextNode(locale['S_TYPE_OK']));


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		var e_select_6 = document.createElement('select');
		this.linkForm.drawtype = e_select_6;

		e_select_6.setAttribute('size',"1");
		e_select_6.className = "input";
		e_select_6.setAttribute('name',"drawtype");
		e_select_6.setAttribute('id',"drawtype");
		e_td_5.appendChild(e_select_6);

		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value',"0");
		e_option_7.appendChild(document.createTextNode(locale['S_LINE']));
		e_select_6.appendChild(e_option_7);


		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value',"2");
		e_option_7.appendChild(document.createTextNode(locale['S_BOLD_LINE']));
		e_select_6.appendChild(e_option_7);


		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value',"3");
		e_option_7.appendChild(document.createTextNode(locale['S_DOT']));
		e_select_6.appendChild(e_option_7);


		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value',"4");
		e_option_7.appendChild(document.createTextNode(locale['S_DASHED_LINE']));
		e_select_6.appendChild(e_option_7);

// Colour OK
		var e_tr_4 = document.createElement('tr');
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.appendChild(document.createTextNode(locale['S_COLOR_OK']));
		e_tr_4.appendChild(e_td_5);


		var e_td_5 = document.createElement('td');
		e_tr_4.appendChild(e_td_5);


		var e_input_6 = document.createElement('input');
		this.linkForm.color = e_input_6;
		e_input_6.setAttribute('style',"margin-top: 0px; margin-bottom: 0px;");
		e_input_6.setAttribute('onchange',"set_color_by_name('color',this.value)");
		e_input_6.setAttribute('maxlength',"6");
		e_input_6.setAttribute('value',"000055");
		e_input_6.setAttribute('size',"7");
		e_input_6.setAttribute('id',"color");
		e_input_6.setAttribute('name',"color");
		e_input_6.className = "input";
		e_td_5.appendChild(e_input_6);


		var e_div_6 = document.createElement('div');
		this.linkForm.colorPicker = e_div_6;

		e_div_6.setAttribute('title',"#000055");
		e_div_6.setAttribute('id',"lbl_color");
		e_div_6.setAttribute('name',"lbl_color");
		e_div_6.className = "pointer";
		addListener(e_div_6, 'click', function(){show_color_picker('color');});
		// e_div_6.setAttribute('onclick',"javascript: show_color_picker('color')");

		e_div_6.style.marginLeft = '2px';
		e_div_6.style.border = '1px solid black';
		e_div_6.style.display = 'inline';
		e_div_6.style.width = '10px';
		e_div_6.style.height = '10px';
		e_div_6.style.textDecoration = 'none';
		e_div_6.style.backgroundColor = '#000000';

		e_div_6.innerHTML = '&nbsp;&nbsp;&nbsp;';
		e_td_5.appendChild(e_div_6);


// FOOTER
		var e_tr_4 = document.createElement('tr');
		e_tr_4.className = "footer";
		e_tbody_3.appendChild(e_tr_4);


		var e_td_5 = document.createElement('td');
		e_td_5.setAttribute('colSpan',"2");
		e_td_5.className = "form_row_last";
		e_td_5.appendChild(document.createTextNode(' '));
		e_tr_4.appendChild(e_td_5);


		var e_input_6 = document.createElement('input');
		e_input_6.setAttribute('type',"button");
		e_input_6.setAttribute('name',"apply");
		e_input_6.className = "input button shadow";
		e_input_6.setAttribute('value',locale['S_APPLY']);
		e_td_5.appendChild(e_input_6);
		addListener(e_input_6, 'click', this.form_link_save.bindAsEventListener(this));


		e_td_5.appendChild(document.createTextNode(' '));


		var e_input_6 = document.createElement('input');
		e_input_6.setAttribute('type',"button");
		e_input_6.setAttribute('name',"remove");
		e_input_6.className = "input button shadow";
		e_input_6.setAttribute('value',locale['S_REMOVE']);
		e_td_5.appendChild(e_input_6);
		addListener(e_input_6, 'click', this.form_link_delete.bindAsEventListener(this));


		e_td_5.appendChild(document.createTextNode(' '));


		var e_input_6 = document.createElement('input');
		e_input_6.setAttribute('type',"button");
		e_input_6.setAttribute('name',"close");
		e_input_6.className = "input button shadow";
		e_input_6.setAttribute('value',locale['S_CLOSE']);
		addListener(e_input_6, 'click', this.form_link_hide.bindAsEventListener(this));


		e_td_5.appendChild(e_input_6);
	},

	form_link_update: function(e, linkid){
		if(!isset(linkid, this.sysmap.links)) return false;

		if(is_null($('linkForm'))){
			this.form_link_create(e);
			$('divSelementForm').appendChild(this.linkForm.form);
		}

		var maplink = this.sysmap.data.links[linkid];

// LINKID
		this.linkForm.linkid.value = linkid;


// LABEL
		this.linkForm.linklabel.value = maplink.label;

// SELEMENTID1
		$(this.linkForm.selementid1).update();
		for(var selementid in this.sysmap.selements){
			if(empty(this.sysmap.selements[selementid])) continue;

			var e_option_7 = document.createElement('option');

			if(maplink.selementid1 == selementid){
				e_option_7.setAttribute('selected',"selected");
			}
			else if(maplink.selementid2 == selementid){
				continue;
			}

			e_option_7.setAttribute('value', selementid);
			e_option_7.appendChild(document.createTextNode(this.sysmap.data.selements[selementid].label_expanded));

			this.linkForm.selementid1.appendChild(e_option_7);
		}


// SELEMENTID2
		$(this.linkForm.selementid2).update();
		for(var selementid in this.sysmap.selements){
			if(empty(this.sysmap.selements[selementid])) continue;

			var e_option_7 = document.createElement('option');

			if(maplink.selementid2 == selementid){
				e_option_7.setAttribute('selected',"selected");
			}
			else if(maplink.selementid1 == selementid){
				continue;
			}

			e_option_7.setAttribute('value', selementid);
			e_option_7.appendChild(document.createTextNode(this.sysmap.data.selements[selementid].label_expanded));

			this.linkForm.selementid2.appendChild(e_option_7);
		}


// LINK INDICATOR TABLE
		var e_table_6 = document.createElement('table');
		e_table_6.setAttribute('cellSpacing',"1");
		e_table_6.setAttribute('cellPadding',"3");
		e_table_6.setAttribute('id',"linktriggers");
		e_table_6.className = "tableinfo";


		var e_tbody_7 = document.createElement('tbody');
		this.linkForm.linkIndicatorsBody = e_tbody_7;
		e_table_6.appendChild(e_tbody_7);


		var e_tr_8 = document.createElement('tr');
		e_tr_8.className = "header";
		e_tbody_7.appendChild(e_tr_8);


		var e_td_9 = document.createElement('td');
		e_tr_8.appendChild(e_td_9);


		var e_input_10 = document.createElement('input');
		e_input_10.setAttribute('type',"checkbox");
		e_input_10.setAttribute('onclick',"javascript: checkLocalAll('linkForm','all_link_triggerids','link_triggerids');");
		e_input_10.setAttribute('id',"all_link_triggerids");
		e_input_10.setAttribute('name',"all_link_triggerids");
		e_input_10.setAttribute('value',"yes");
		e_input_10.className = "checkbox";
		e_td_9.appendChild(e_input_10);


		var e_td_9 = document.createElement('td');
		e_td_9.appendChild(document.createTextNode(locale['S_TRIGGERS']));
		e_tr_8.appendChild(e_td_9);


		var e_td_9 = document.createElement('td');
		e_td_9.appendChild(document.createTextNode(locale['S_TYPE']));
		e_tr_8.appendChild(e_td_9);


		var e_td_9 = document.createElement('td');
		e_td_9.appendChild(document.createTextNode(locale['S_COLOR']));
		e_tr_8.appendChild(e_td_9);


// Indicators
		for(var linktriggerid in maplink.linktriggers){
			if(empty(maplink.linktriggers[linktriggerid])) continue;

			this.form_link_addLinktrigger(maplink.linktriggers[linktriggerid]);
		}

		$(this.linkForm.linkIndicatorsTable).update(e_table_6);

		var e_br_6 = document.createElement('br');
		this.linkForm.linkIndicatorsTable.appendChild(e_br_6);


		var e_input_6 = document.createElement('input');
		e_input_6.setAttribute('type',"button");
		e_input_6.setAttribute('name',"Add");
		e_input_6.setAttribute('value',locale['S_ADD']);
		e_input_6.setAttribute('style',"margin: 2px 4px");
		e_input_6.className = "input button link_menu";
		this.linkForm.linkIndicatorsTable.appendChild(e_input_6);

		var url = 'popup_link_tr.php?form=1&mapid='+this.id;
		addListener(e_input_6, 'click', function(){PopUp(url,640, 420, 'ZBX_Link_Indicator');});


		var e_input_6 = document.createElement('input');
		e_input_6.setAttribute('type',"button");
		e_input_6.setAttribute('name',"Remove");
		e_input_6.setAttribute('value',locale['S_REMOVE']);
		e_input_6.setAttribute('style',"margin: 2px 4px");
		e_input_6.className = "input button link_menu";
		this.linkForm.linkIndicatorsTable.appendChild(e_input_6);

		addListener(e_input_6, 'click', function(){remove_childs('linkForm','link_triggerids','tr');});
//----


// Type ok
		if(maplink.drawtype == 0) var dindex = 0; // S_LINE
		if(maplink.drawtype == 2) var dindex = 1; // S_BOLD_LINE
		if(maplink.drawtype == 3) var dindex = 2; // S_DOT
		if(maplink.drawtype == 4) var dindex = 3; // S_DASHED_LINE
		this.linkForm.drawtype.selectedIndex = dindex;


// COLOR OK
		this.linkForm.color.value = maplink.color;
		this.linkForm.colorPicker.style.backgroundColor = '#'+maplink.color;
	},


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
	},

	form_link_delete: function(e){
		var linkid = this.linkForm.linkid.value;
		if(!isset(linkid, this.sysmap.data.links)) return false;

		var maplink = this.sysmap.data.links[linkid];

		if(Confirm('Remove link between "'+this.sysmap.data.selements[maplink.selementid1].label+'" and "'+this.sysmap.data.selements[maplink.selementid2].label+'"?')){
			this.sysmap.remove_link(linkid, true);
			this.update_linkContainer(e);
			this.form_link_hide(e);
		}
		else
			return false;
	}

};



jQuery.fn.populate = function(obj, options) {
	var populateFormElement = function(form, name, value){
		var element	= form[name];

		if(element == undefined){
			// look for the element
			element = jQuery('#' + name, form);
			if(element){
				element.html(value);
				return true;
			}

			return false;
		}

// to array
		elements = element.type == undefined && element.length ? element : [element];

		for(var e = 0; e < elements.length; e++){
			var element = elements[e];

			if(!element || typeof element == 'undefined' || typeof element == 'function')
				continue;

			switch(element.type || element.tagName){
				case 'radio':
					element.checked = (element.value != '' && value.toString() == element.value);
				case 'checkbox':
					var values = value.constructor == Array ? value : [value];
					for(var j = 0; j < values.length; j++)
						element.checked |= element.value == values[j];

					//element.checked = (element.value != '' && value.toString().toLowerCase() == element.value.toLowerCase());
					break;
				case 'select-multiple':
					var values = value.constructor == Array ? value : [value];
					for(var i = 0; i < element.options.length; i++){
						for(var j = 0; j < values.length; j++)
							element.options[i].selected |= element.options[i].value == values[j];
					}
					break;
				case 'select':
				case 'select-one':
					element.value = value.toString() || value;
					break;
				case 'text':
				case 'button':
				case 'textarea':
				case 'submit':
				default:
					element.value	= (value == null) ? "" : value;
			}
		}
	};

	// options & setup
	if (obj === undefined) return this;

	// options
	var options = jQuery.extend({resetForm: true,identifier: 'id'},options);

	// main process function
	this.each(
		function(){
			var tagName	= this.tagName.toLowerCase();
			if(tagName == 'form' && options.resetForm)
				this.reset();

			for(var i in obj) populateFormElement(this, i, obj[i]);
		});

	return this;
};
