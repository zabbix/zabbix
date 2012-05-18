// JavaScript Document
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
// Title: Cmap class
// Author: Aly

// [!CDATA[
var ZBX_SYSMAPS = new Array();			// sysmaps obj reference

// sysmapid ALWAYS must be a STRING (js doesn't support uint64) !!!!
function create_map(container,sysmapid,id){
	if(typeof(id) == 'undefined'){
		id = ZBX_SYSMAPS.length;
	}

	if(is_number(sysmapid) && (sysmapid > 100000000000000)){
		throw('Error: Wrong type of arguments passed to function [create_map]');
	}


	ZBX_SYSMAPS[id] = new Object;
	ZBX_SYSMAPS[id].map = new Cmap(container,sysmapid,id);
}

var Cmap = Class.create(CDebug,{
id:	null,							// own id
sysmapid: null,						// sysmapid
container: null,					// selements and links HTML container (D&D droppable area)
mapimg: null,						// HTML element map img

grid: null,							// grid object

sysmap:	{},							// map data
selements: {},						// map selements array
links:	{},							// map links array

selection: {
	count: 0,						// numer of selected elements
	position: 0,					// elements numerate
	selements: new Array()			// selected SElements
},

menu_active: 0,						// To recognize D&D

mselement: {
	selementid:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	elementtype:		4,			// 5-UNDEFINED
	elementid:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	elementName:		'',			// element name
	iconid_off:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_on:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_unknown:		0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_maintenance:	0,		// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_disabled:	0,			// ALWAYS must be a STRING (js doesn't support uint64)
	label:				locale['S_NEW_ELEMENT'],	// Element label
	label_expanded:		locale['S_NEW_ELEMENT'],	// Element label macros expanded
	label_location:		3,
	x:					0,
	y:					0,
	url:				'',
	html_obj:			null,			// reference to html obj
	html_objid:			null,			// html elements id
	selected:			0				// element is not selected
},

mlink: {
	linkid:			0,				// ALWAYS must be a STRING (js doesn't support uint64)
	label:			'',				// Link label
	label_expanded: '',				// Link label (Expand macros)
	selementid1:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	selementid2:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	linktriggers:	null,			// ALWAYS must be a STRING (js doesn't support uint64)
	tr_desc:		locale['S_SELECT'],		// default trigger caption
	drawtype:		0,
	color:			'00CC00',
	status:			1				// status of link 1 - active, 2 - passive
},


mlinktrigger: {
	linktriggerid:	0,					// ALWAYS must be a STRING (js doesn't support uint64)
	triggerid:		0,					// ALWAYS must be a STRING (js doesn't support uint64)
	desc_exp:		locale['S_SET_TRIGGER'],		// default trigger caption
	drawtype:		0,
	color:			'CC0000'
},

selementForm:		{},					// container for Selement form dom objects
linkForm:			{},					// container for link form dom objects

initialize: function($super, container, sysmapid, id){
	this.id = id;
	$super('CMap['+id+']');

	this.container = $(container);

	if(is_null(this.container)){
		this.container = document.body;
//		this.error('Map initialization failed. Unavailable container.');
	}
	else{
//		var pos = getPosition(this.container);
//		this.container.style.position = 'relative'; //absolute; top:'+pos.top+'px; left:'+pos.left+'px;');
	}

	if(typeof(sysmapid) != 'undefined'){
		this.sysmapid = sysmapid;

// add event listeners
// sysmap
		addListener($('sysmap_save'), 'click', this.saveSysmap.bindAsEventListener(this), false);

// grid
		var gridCtrlElemetns = {
			'gridsize': $('gridsize'),
			'gridautoalign': $('gridautoalign'),
			'gridshow': $('gridshow'),
			'gridalignall': $('gridalignall')
		};

		this.grid = new CGrid(this.id, gridCtrlElemetns);

// selement
		addListener($('selement_add'), 'click', this.addNewElement.bindAsEventListener(this), false);
		addListener($('selement_rmv'), 'click', this.remove_selements.bindAsEventListener(this), false);
// link
		addListener($('link_add'), 'click', this.add_empty_link.bindAsEventListener(this), false);
		addListener($('link_rmv'), 'click', this.remove_links.bindAsEventListener(this), false);


//------

		this.getSysmapBySysmapid(this.sysmapid);
	}

	Position.includeScrollOffsets = true;
},


// SYSMAP
getSysmapBySysmapid: function(){
	this.debug('getSysmapBySysmapid');

	var url = new Curl(location.href);
	var params = {
		'favobj': 	'sysmap',
		'favid':	this.id,
		'sysmapid': this.sysmapid,
		'action':	'get'
	};

	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onSuccess': function(){ },
						'onFailure': function(){ throw('Get selements FAILED.'); }
					}
	);
},

selementDragEnd: function(dragable) {
	this.debug('selementDragEnd');

	this.deactivate_menu();

	var element = dragable.element;
	var element_id = element.id.split('_');
	var selementid = element_id[(element_id.length - 1)];

	var pos = new Array();
	pos.x = parseInt(element.style.left,10);
	pos.y = parseInt(element.style.top,10);

	this.selements[selementid].y = pos.y;
	this.selements[selementid].x = pos.x;

	this.alignSelement(selementid);

	if(isset('selementid', this.selementForm) && isset('x', this.selementForm) && isset('y', this.selementForm)){
		if(this.selementForm.selementid.value == selementid){
			this.selementForm.x.value = this.selements[selementid].x;
			this.selementForm.y.value = this.selements[selementid].y;
		}
	}

	this.sysmapUpdate();
},

sysmapUpdate: function(){
	this.debug('sysmapUpdate');
	this.updateMapImage();
//	alert(id+' : '+this.selementids[id]);
},

// ---------- FORMS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

//ELEMENTS
addNewElement: function(){
	this.debug('addNewElement');

//	var selement = this.mselement;

	var selement = {};
	for(var key in this.mselement){
		selement[key] = this.mselement[key];
	}

	var url = new Curl(location.href);

	var params = {
		'favobj': 	'selements',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'new_selement'
	};

	params['selements'] = Object.toJSON({'0': selement});

	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(){ },
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},

// CONNECTORS
add_empty_link: function(e){
	this.debug('add_empty_link');
//--

	if(this.selection.count == 2){
		var selementid1 = null;
		var selementid2 = null;

		for(var i=0; i < this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;

			if(is_null(selementid1)){
				selementid1 = this.selection.selements[i];
			}
			else{
				selementid2 = this.selection.selements[i];
				break;
			}
		}
	}
	else{
		this.info(locale['S_TWO_ELEMENTS_SHOULD_BE_SELECTED']);
		return false;
	}

	var mlink = {};
	for(var key in this.mlink){
		mlink[key] = this.mlink[key];
	}

	mlink['selementid1'] = selementid1;
	mlink['selementid2'] = selementid2;

	mlink['linktriggers'] = {};

	this.add_link(mlink,1);
	this.update_linkContainer(e);
},

// SYSMAP FORM
saveSysmap: function(){
	this.debug('saveSysmap');

	var url = new Curl(location.href);
	var params = {
		'favobj': 	'sysmap',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'save'
	};

	params = this.get_update_params(params);
//SDJ(params);
	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(){ },
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},
//------------------------------------------------------------------------

// ---------- ELEMENTS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

select_selement: function(selementid, multi){
	this.debug('select_selement');
//--
	if(!isset(selementid, this.selements) || empty(this.selements[selementid])) return false;

	var multi = multi || false;
	var selement = this.selements[selementid];

	if((typeof(this.selements[selementid]) != 'undefiend') && !empty(this.selements[selementid])){
		var position = null;

		if(is_null(this.selements[selementid].selected)){
			position = this.selection.position;

			this.selection.selements[position] = selementid;
			this.selements[selementid].selected = position;

			selement.html_obj.style.border = '1px #3333FF solid';
			selement.html_obj.style.backgroundColor = '#00AAAA';
			selement.html_obj.style.opacity = '.60';

			this.selection.count++;
			this.selection.position++;
		}
		else if((this.selection.count > 1) && !multi){
// if selected several selements and then we clicked on one of them
		}
		else{
			this.selection.count--;
			position = selement.selected;

			this.selection.selements[position] = null;
			delete(this.selection.selements[position]);

			this.selements[selementid].selected = null;

			selement.html_obj.style.border = '0px';
			selement.html_obj.style.backgroundColor = 'transparent';
			selement.html_obj.style.opacity = '1';
		}

		if(!multi && (this.selection.count > 1)){
			for(var i=0; i<this.selection.position; i++){
				if(!isset(i,this.selection.selements) || (this.selection.selements[i] == selementid)) continue;

				this.selection.count--;

				this.selements[this.selection.selements[i]].selected = null;
				this.selements[this.selection.selements[i]].html_obj.style.border = '0px';
				this.selements[this.selection.selements[i]].html_obj.style.backgroundColor = 'transparent';
				this.selements[this.selection.selements[i]].html_obj.style.opacity = '1';

				this.selection.selements[i] = null;
				delete(this.selection.selements[i]);
			}
		}
	}

return false;
},

alignSelement: function(selementid){
	this.debug('placeSelement');
//--

	if(!this.grid.autoAlign) return true;

	if(!isset(selementid, this.selements) || empty(this.selements[selementid])) return false;

	var selement = 	this.selements[selementid];

	var dims = getDimensions(selement.html_obj);
	var shiftX = Math.round(dims.width / 2);
	var shiftY = Math.round(dims.height / 2);

	var newX = parseInt(selement.x, 10) + shiftX;
	var newY = parseInt(selement.y, 10) + shiftY;

	newX = Math.floor(newX / this.grid.gridSize) * this.grid.gridSize;
	newY = Math.floor(newY / this.grid.gridSize) * this.grid.gridSize;

// centrillize
	newX += Math.round(this.grid.gridSize / 2) - shiftX;
	newY += Math.round(this.grid.gridSize / 2) - shiftY;

// limits
	if(newX < shiftX) newX = 0;
	else if((newX + dims.width) > this.sysmap.width) newX = this.sysmap.width - dims.width;

	if(newY < shiftY) newY = 0;
	else if((newY + dims.height) > this.sysmap.height) newY = this.sysmap.height - dims.height;
//--

	this.selements[selementid].y = newY;
	this.selements[selementid].x = newX;

	this.selements[selementid].html_obj.style.top = newY+'px';
	this.selements[selementid].html_obj.style.left = newX+'px';
},

add_selement: function(selement, update_icon){
	this.debug('add_selement');

	var selementid = 0;
	if((typeof(selement['selementid']) == 'undefined') || (selement['selementid'] == 0)){
		do{
			selementid = parseInt(Math.random(1000000000) * 1000000000);
			selementid = selementid.toString();
		}while(isset(selementid, this.selements));

		selement['new'] = 'new';
		selement['selementid'] = selementid;
	}
	else{
		selementid = selement.selementid;
	}

	if(typeof(this.selements[selementid]) == 'undefined'){
		selement.selected = null;
	}
	else{
		selement.selected = this.selements[selementid].selected;
	}

	if((typeof(update_icon) != 'undefined') && (update_icon != 0)){
		selement.html_obj = this.addSelementImage(selement);
		selement.image = null;
	}

	this.selements[selementid] = selement;
},

updateSelementOption: function(selementid, params){ // params = {'key': value, 'key': value}
	this.debug('updateSelementOption');
//--

	if(!isset(selementid, this.selements) || empty(this.selements[selementid])) return false;


	for(var key in params){
		if(is_null(params[key])) continue;

		if(is_number(params[key])) params[key] = params[key].toString();
		this.selements[selementid][key] = params[key];
	}

	this.updateSelement(this.selements[selementid]);
},

updateSelement: function(selement){
	this.debug('updateSelement');
//--

	var url = new Curl(location.href);

	var params = {
		'favobj': 	'selements',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'get_img'
	};

	params['selements'] = Object.toJSON({'0': selement});

	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(){ },
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},

remove_selements: function(e){
	this.debug('remove_selements');
//--

	if(Confirm(locale['S_DELETE_SELECTED_ELEMENTS_Q'])){
		for(var i=0; i<this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;

			this.remove_selement(this.selection.selements[i]);
		}

		this.hideForm(e);
		this.updateMapImage();
	}
},

remove_selement: function(selementid, update_map){
	this.debug('remove_selement');
//--

	if(!isset(selementid, this.selements) || empty(this.selements[selementid])) return false;

// Unselect
	this.selection.count--;
	this.selection.selements[this.selements[selementid].selected] = null;
	delete(this.selection.selements[this.selements[selementid].selected]);

// Remove related links
	this.remove_links_by_selementid(selementid);
// remove icon
	this.remove_selement_img(this.selements[selementid]);

//		this.selements[selementid].html_obj.remove();
// remove selement
	this.selements[selementid] = null;
	delete(this.selements[selementid]);


	if((typeof(update_map) != 'undefined') && (update_map != 0)){
		this.updateMapImage();
	}
},

// ---------- CONNECTORS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

get_linkid_by_selementids: function(selementid1,selementid2){
	this.debug('get_linkid_by_selementids');
//--

	if(typeof(selementid2) == 'undefined') selementid2 = null;

	var links = {};
	for(var linkid in this.links){
		if(empty(this.links[linkid])) continue;

		if(is_null(selementid2)){
			if((this.links[linkid].selementid1 == selementid1) || (this.links[linkid].selementid2 == selementid1))
				links[linkid] = linkid;
		}
		else{
			if((this.links[linkid].selementid1 == selementid1) && (this.links[linkid].selementid2 == selementid2))
				links[linkid] = linkid;
			else if((this.links[linkid].selementid1 == selementid2) && (this.links[linkid].selementid2 == selementid1))
				links[linkid] = linkid;
		}
	}

return links;
},

add_link: function(mlink, update_map){
	this.debug('add_link');
//--

	var linkid = 0;
	if(!isset('linkid', mlink) || (mlink['linkid'] == 0)){
		do{
			linkid = parseInt(Math.random(1000000000) * 1000000000);
			linkid = linkid.toString();
		}while(isset(linkid, this.links));

		mlink['new'] = 'new';
		mlink['linkid'] = linkid;
	}
	else{
		linkid = mlink.linkid;
	}

	if(is_array(mlink.linktriggers)){
		var tmp_lts = {};
		for(var i=0; i<mlink.linktriggers.length; i++){
			if(!isset(i, mlink.linktriggers) || empty(mlink.linktriggers[i])) continue;
			var tmp_lt = mlink.linktriggers[i];

			tmp_lts[tmp_lt.linktriggerid] = {};

			tmp_lts[tmp_lt.linktriggerid].linktriggerid = tmp_lt.linktriggerid;
			tmp_lts[tmp_lt.linktriggerid].linkid = tmp_lt.linkid;
			tmp_lts[tmp_lt.linktriggerid].triggerid = tmp_lt.triggerid;
			tmp_lts[tmp_lt.linktriggerid].drawtype = tmp_lt.drawtype;
			tmp_lts[tmp_lt.linktriggerid].color = tmp_lt.color;
			tmp_lts[tmp_lt.linktriggerid].desc_exp = tmp_lt.desc_exp;
		}

		mlink.linktriggers = tmp_lts;
	}

	mlink.status = 1;
	this.links[linkid] = mlink;

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},


update_link_option: function(linkid, params, update_map){ // params = [{'key': key, 'value':value},{'key': key, 'value':value},...]
	this.debug('update_link_option');
//--

	if(!isset(linkid, this.links) || empty(this.links[linkid])) return false;
//SDI(key+' : '+value);
	for(var key in params){
		if(is_null(params[key])) continue;

		if(key == 'selementid1'){
			if(this.links[linkid]['selementid2'] == params[key])
			return false;
		}

		if(key == 'selementid2'){
			if(this.links[linkid]['selementid1'] == params[key])
			return false;
		}

		if(is_number(params[key])) params[key] = params[key].toString();
		this.links[linkid][key] = params[key];
//SDI(key+' : '+value);
	}

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},

remove_links: function(e){
	this.debug('remove_links');
//--

	if(this.selection.count == 2){
		var selementid1 = null;
		var selementid2 = null;

		for(var i=0; i < this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;

			if(is_null(selementid1)){
				selementid1 = this.selection.selements[i];
			}
			else{
				selementid2 = this.selection.selements[i];
				break;
			}
		}
	}
	else{
		this.info(locale['S_PLEASE_SELECT_TWO_ELEMENTS']);
		return false;
	}

	var linkids = this.get_linkid_by_selementids(selementid1,selementid2);

	if(linkids !== false){
		if(Confirm(locale['S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'])){
			for(var linkid in linkids){
				this.remove_link(linkid);
			}

			this.hideForm(e);
			this.updateMapImage();
		}
	}
},


remove_link: function(linkid, update_map){
	this.debug('remove_link');
//--

	if(!isset(linkid, this.links) || empty(this.links[linkid])) return false;

	this.links[linkid] = null;
	delete(this.links[linkid]);

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},


remove_links_by_selementid: function(selementid){
	this.debug('remove_links_by_selementid');

	for(var linkid in this.links){
		if(empty(this.links[linkid])) continue;

		if((this.links[linkid].selementid1 == selementid) || (this.links[linkid].selementid2 == selementid)){
			this.remove_link(linkid);
		}
	}
},

add_linktrigger: function(linkid, linktrigger, update_map){
	this.debug('add_linktrigger');
//--

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;

	for(var ltid in this.links[linkid].linktriggers){
		if(!isset(ltid, this.links[linkid].linktriggers)) continue;
		if(this.links[linkid].linktriggers[ltid].triggerid === linktrigger.triggerid){
			linktrigger.linktriggerid = ltid;
			break;
		}
	}

	var linktriggerid = 0;
	if(!isset('linktriggerid',linktrigger) || (linktrigger['linktriggerid'] == 0)){
		do{
			linktriggerid = parseInt(Math.random(1000000000) * 1000000000);
			linktriggerid = linktriggerid.toString();
		}while(typeof(this.links[linkid].linktriggers[linktriggerid]) != 'undefined');

		linktrigger['new'] = 'new';
		linktrigger['linktriggerid'] = linktriggerid;
	}
	else{
		linktriggerid = linktrigger.linktriggerid;
	}

	this.links[linkid].linktriggers[linktriggerid] = linktrigger;

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},

update_linktrigger_option: function(linkid, linktriggerid, params, update_map){
	this.debug('update_linktrigger_option');
//--

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;


//SDI(key+' : '+value);
	for(var key in params){
		if(is_null(params[key])) continue;
		if(!isset(linktriggerid, this.links[linkid].linktriggers) || empty(this.links[linkid].linktriggers[linktriggerid])) continue;

		if(is_number(params[key])) params[key] = params[key].toString();
		this.links[linkid].linktriggers[linktriggerid][pair.key] = params[key];
	}

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},

remove_linktrigger: function(linkid, linktriggerid, update_map){
	this.debug('remove_linktrigger');
//--

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;
	if(!isset(linktriggerid, this.links[linkid].linktriggers) || empty(this.links[linkid].linktriggers[linktriggerid])) return false;
//SDI(key+' : '+value);
	this.links[linkid].linktriggers[linktriggerid] = null;
	delete(this.links[linkid].linktriggers[linktriggerid]);

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.updateMapImage();
	}
},
// ---------- IMAGES MANIPULATION ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

// ELEMENTS
addSelementImage: function(selement){
	this.debug('addSelementImage');

	var dom_id = 'selement_'+selement.selementid;

	var selement_div = $(dom_id);
	if(is_null(selement_div)){
//		var selement_div = document.createElement('img');
		var selement_div = document.createElement('div');
		this.container.appendChild(selement_div);

		selement_div.setAttribute('id',dom_id);
//		selement_div.setAttribute('alt','selement_'+selement.id);
		selement_div.style.position = 'absolute';
		selement_div.style.visibility = 'hidden';

		this.makeSelementDragable(selement_div);
	}

	var position = {};
	position.top = parseInt(selement.y,10);
	position.left = parseInt(selement.x,10);

//	selement_div.setAttribute('src','data:image/png;base64,'+selement.image);
//	selement_div.setAttribute('src','imgstore.php?iconid='+selement.image);
	selement_div.className = 'pointer sysmap_iconid_'+selement.image;

	selement_div.style.zIndex = '10';
	selement_div.style.position = 'absolute';
	selement_div.style.top = position.top+'px';
	selement_div.style.left = position.left+'px';
	selement_div.style.visibility = 'visible';

	if(!is_null(selement.selected)){
		selement_div.style.border = '1px #3333FF solid';
		selement_div.style.backgroundColor = '#00AAAA';
		selement_div.style.opacity = '.60';
	}

return selement_div;
},

updateSelementsIcon: function(){
	this.debug('updateSelementsIcon');

	if(is_null(this.mapimg)){
		setTimeout('ZBX_SYSMAPS['+this.id+'].map.updateSelementsIcon();',500);
	}
	else{
		for(var selementid in this.selements){
			if(empty(this.selements[selementid])) continue;

			this.selements[selementid].html_obj = this.addSelementImage(this.selements[selementid]);
			this.selements[selementid].image = null;
		}
	}
},

remove_selement_img: function(selement){
	this.debug('remove_selement_img');

	Draggables.unregister(selement.html_obj);
	selement.html_obj.remove();
},

makeSelementDragable: function(selement){
	this.debug('makeSelementDragable');

//	addListener(selement, 'click', this.select_selement.bindAsEventListener(this), false);
	addListener(selement, 'click', this.show_menu.bindAsEventListener(this), false);
	addListener(selement, 'mousedown', this.activate_menu.bindAsEventListener(this), false);

	new Draggable(selement,{
				ghosting: true,
				snap: this.get_dragable_dimensions.bind(this),
				onEnd: this.selementDragEnd.bind(this)
				});

},

// MAP

updateMapImage: function(){
	this.debug('updateMapImage');
//--

// grid
	var urlGrid = '';
	if(this.grid.showGrid){
		urlGrid += '&grid='+this.grid.gridSize;
	}
//--

	var params = {
		'output': 'ajax',
		'sysmapid': this.sysmapid,
		'noselements':	1,
		'nolinks':	1
	};

	params = this.get_update_params(params);
//SDJ(params);

	var url = new Curl(location.href);
	new Ajax.Request('map.php'+'?sid='+url.getArgument('sid')+urlGrid,
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){SDI(resp.responseText);},
						'onSuccess': this.set_mapimg.bind(this),
						'onFailure': function(){ alert('failed'); }
					}
	);
},

set_mapimg: function(resp){
	this.debug('set_mapimg');
//--

//SDI(resp.responseText);
	if(is_null(this.mapimg)){
		this.mapimg = $('sysmap_img');
//		this.container.appendChild(this.mapimg);

		this.mapimg.setAttribute('alt','Sysmap');
		this.mapimg.setAttribute('id','mapimg_'+this.sysmapid);
		this.mapimg.className = 'image';

		this.mapimg.style.zIndex = '1';

		addListener(this.mapimg, 'load', this.set_container.bindAsEventListener(this), false);
		addListener(window, 'resize', this.set_container.bindAsEventListener(this), false);
	}

//	this.mapimg.setAttribute('src','data:image/png;base64,'+resp.responseText);
	this.mapimg.setAttribute('src','imgstore.php?imageid='+resp.responseText);
},

set_container: function(event){
	var sysmap_pn = getPosition(this.mapimg);
	var sysmap_ds = Element.getDimensions(this.mapimg);

	var container_pn = getPosition(this.container);
	var container_ds = Element.getDimensions(this.container);

	if((container_pn.top != sysmap_pn.top) ||
		(container_pn.left != sysmap_pn.left) ||
		(container_ds.height != sysmap_ds.height) ||
		(container_ds.width != sysmap_ds.width))
	{
		this.container.style.top = sysmap_pn.top+'px';
		this.container.style.left = sysmap_pn.left+'px';
		this.container.style.height = sysmap_ds.height+'px';
		this.container.style.width = sysmap_ds.width+'px';
	}

	Event.stop(event)
},
//--------------------------------------------------------------------------------

// ---------- MISC FUNCTIONS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------
get_window_dimensions: function(x,y,draggable){
	this.debug('get_window_dimensions');
//--

	function constrain(n, lower, upper) {
		if (n > upper) return upper;
		else if (n < lower) return lower;
		else return n;
	}

	var h = parseInt(document.body.offsetHeight);
	var w = parseInt(document.body.offsetWidth);

	return[
		constrain(x, 0, w),
		constrain(y, 0, h)
	];
},

get_dragable_dimensions: function(x,y,draggable){
	this.debug('get_dragable_dimensions');
//--

	function constrain(n, lower, upper) {
		if (n > upper) return upper;
		else if (n < lower) return lower;
		else return n;
	}

	var element_dimensions = Element.getDimensions(draggable.element);
	var parent_dimensions = Element.getDimensions(this.mapimg);

	return[
		constrain(x, 0, parent_dimensions.width - element_dimensions.width),
		constrain(y, 0, parent_dimensions.height - element_dimensions.height)
	];
},

get_update_params: function(params){
	this.debug('get_update_params');

	if(typeof(params) == 'undefined'){
		params = {};
	}

	params = this.get_selements_params(params);
	params = this.get_links_params(params);

return params;
},

get_selements_params: function(params, selementid){
	this.debug('get_selements_params');

	if(typeof(params) == 'undefined'){
		params = {};
	}

	if(typeof(selementid) != 'undefined'){
		if(isset(selementid, this.selements)){
			params['selements['+selementid+']'] = Object.toJSON(this.selements[selementid]);
		}
	}
	else{
		params['selements'] = Object.toJSON(this.selements);
	}

return params;
},

get_links_params: function(params, linkid){
	this.debug('get_links_params');

	if(typeof(params) == 'undefined'){
		params = {};
	}

	if(typeof(linkid) != 'undefined'){
		if(isset(linkid, this.links)){
			params['links['+linkid+']'] = Object.toJSON(this.links[linkid]);
		}
	}
	else{
		params['links'] = Object.toJSON(this.links);
	}

return params;
},

activate_menu: function(){
	this.debug('activate_menu');
	this.menu_active = 1;
},

deactivate_menu: function(){
	this.debug('deactivate_menu');
	this.menu_active = 0;
},


//------------------------------------------------------------------------------------------------------
// ---------- MENU ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

show_menu: function(e){
	this.debug('show_menu');
	if(this.menu_active != 1) return true;

	var e = e || window.event;
	var element = Event.element(e);
	var element_id = element.id.split('_');
	var selementid = element_id[(element_id.length - 1)];

	if(e.ctrlKey || e.shiftKey){
		this.select_selement(selementid, true);
	}
	else{
		this.select_selement(selementid);
	}

	if(this.selection.count == 0){
		this.hideForm(e);
	}
	else{
		this.showForm(e, selementid);
	}
},


//  Form  ------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------

showForm: function(e, selementid){
	this.debug('showForm');
//--

	var divForm = document.getElementById('divSelementForm');

	if((typeof(divForm) == 'undefined') || empty(divForm)){
		var divForm = document.createElement('div');
		var doc_body = document.getElementsByTagName('body')[0];
		doc_body.appendChild(divForm);

		divForm.setAttribute('id','divSelementForm');
		divForm.style.backgroundColor = '#999999';
		divForm.style.zIndex = 100;
		divForm.style.position = 'absolute';
		divForm.style.top = '50px';
		divForm.style.left = '500px';



		divForm.style.border = '1px #999999 solid';
	}


// Form init
	this.updateForm_selement(e, selementid);
	this.update_multiContainer(e);
	this.update_linkContainer(e);
	this.hideForm_link(e);
//---

	new Draggable(divForm,{
				  			'handle': this.selementForm.dragHandler,
							'snap': this.get_window_dimensions.bind(this),
							'starteffect': function(){ return true; },
							'endeffect': function(){ return true; }
						});
	$(divForm).show();
},

hideForm: function(e){
	this.debug('hideForm');

	var divForm = $('divSelementForm');
	if(!is_null(divForm)) divForm.hide();

	for(var i=0; i<this.selection.position; i++){
		if(!isset(i,this.selection.selements)) continue;

		this.select_selement(this.selection.selements[i], true);
	}
},


//  Multi Container  ------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------------------------

create_multiContainer: function(e, selementid){
	this.debug('create_multiContainer');
//--

// var initialization
	this.multiContainer = {};


	var e_div_1 = document.createElement('div');
this.multiContainer.container = e_div_1;
	e_div_1.setAttribute('id',"multiContainer");
	e_div_1.style.overflow = 'auto';

//	e_td_4.appendChild(e_div_1);
},

update_multiContainer: function(e){
	this.debug('update_multiContainer');
//--

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
	for(var i=0; i<this.selection.position; i++){
		if(!isset(i, this.selection.selements)) continue;
		if(!isset(this.selection.selements[i], this.selements)) continue;

		count++;
		selement = this.selements[this.selection.selements[i]];

		if(count > 4) this.multiContainer.container.style.height = '127px';
		else this.multiContainer.container.style.height = 'auto';

		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "even_row";
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
			case '0': elementtypeText = locale['S_HOST']; break;
			case '1': elementtypeText = locale['S_MAP']; break;
			case '2': elementtypeText = locale['S_TRIGGER']; break;
			case '3': elementtypeText = locale['S_HOST_GROUP']; break;
			case '4':
			default: elementtypeText = locale['S_IMAGE']; break;
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
	this.debug('create_multiContainer');
//--

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
	this.debug('update_linkContainer');
//--

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
	for(var i=0; i<this.selection.position; i++){
		if(!isset(i, this.selection.selements)) continue;
		if(!isset(this.selection.selements[i], this.selements)) continue;

		selementid = this.selection.selements[i];

		var current_linkids = this.get_linkid_by_selementids(selementid);
		for(var linkid in current_linkids){
			if(empty(current_linkids[linkid])) continue;

			linkids[linkid] = current_linkids[linkid];
		}
	}

	this.linkContainer.container.style.height = 'auto';

	var count = 0;
	var maplink = null;
	for(var linkid in linkids){
		if(!isset(linkid, this.links)) continue;

		count++;
		maplink = this.links[linkid];

		if(count > 4) this.linkContainer.container.style.height = '120px';

		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "even_row";
		e_tbody_2.appendChild(e_tr_3);


		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);


		var e_span_5 = document.createElement('span');
		e_span_5.className = "link";
		addListener(e_span_5, 'click', this.updateForm_link.bindAsEventListener(this, linkid));
		e_span_5.appendChild(document.createTextNode(locale['S_LINK']+' '+count));
		e_td_4.appendChild(e_span_5);


		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(this.selements[maplink.selementid1].label_expanded));
		e_tr_3.appendChild(e_td_4);


		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(this.selements[maplink.selementid2].label_expanded));
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
		e_tr_3.className = "even_row";
		e_tbody_2.appendChild(e_tr_3);

		var e_td_4 = document.createElement('td');
		e_td_4.setAttribute('colSpan',4);
		e_td_4.className = 'center';
		e_td_4.appendChild(document.createTextNode(locale['S_NO_LINKS']));
		e_tr_3.appendChild(e_td_4);
	}

	$(this.linkContainer.container).update(e_table_1);
},



//  SELEMENTS FORM ----------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------

createForm_selement: function(e){
this.debug('createForm_selement');

// var initialization of diferent types of form
	this.selementForm.typeDOM = {};
	this.selementForm.massEdit = {};

// Form creation
	var e_form_1 = document.createElement('form');
this.selementForm.form = e_form_1;

	e_form_1.setAttribute('id',"selementForm");
	e_form_1.setAttribute('name',"selementForm");
	e_form_1.setAttribute('accept-charset',"utf-8");
	e_form_1.setAttribute('action',"sysmap.php");
	e_form_1.setAttribute('method',"post");


// HIDDEN
	var e_input_2 = document.createElement('input');
this.selementForm.selementid = e_input_2;
	e_input_2.setAttribute('type',"hidden");
	e_input_2.setAttribute('value','');
	e_input_2.setAttribute('id',"selementid");
	e_input_2.setAttribute('name',"selementid");
	e_form_1.appendChild(e_input_2);


	var e_input_2 = document.createElement('input');
this.selementForm.elementid = e_input_2;
	e_input_2.setAttribute('type',"hidden");
	e_input_2.setAttribute('value',"");
	e_input_2.setAttribute('id',"elementid");
	e_input_2.setAttribute('name',"elementid");
	e_form_1.appendChild(e_input_2);


// TABLE
	var e_table_2 = document.createElement('table');
	e_table_2.setAttribute('cellSpacing',"0");
	e_table_2.setAttribute('cellPadding',"1");
	e_table_2.setAttribute('align',"center");
	e_table_2.style.width = '100%';
	e_table_2.className = "formtable";

	e_form_1.appendChild(e_table_2);

	var e_tbody_3 = document.createElement('tbody');
	e_table_2.appendChild(e_tbody_3);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "header";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
this.selementForm.dragHandler = e_td_5;
	e_td_5.setAttribute('colSpan',"2");
	e_td_5.className = "form_row_first move";
	e_tr_4.appendChild(e_td_5);


	var e_span_6 = document.createElement('span');
	e_span_6.setAttribute('target',"_blank");
	e_span_6.setAttribute('style',"padding-left: 5px; float: right; text-decoration: none;");
	e_span_6.setAttribute('onclick','window.open("http://www.zabbix.com/documentation.php");');
	e_td_5.appendChild(e_span_6);

	var e_div_7 = document.createElement('div');
	e_div_7.className = "iconhelp";
	e_div_7.appendChild(document.createTextNode(' '));
	if(!IE)	e_span_6.appendChild(e_div_7);


	e_td_5.appendChild(document.createTextNode(locale['S_EDIT_MAP_ELEMENT']));


	var e_tr_4 = document.createElement('tr');
this.selementForm.massEdit.elementtype = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_TYPE']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.elementtype = e_select_6;

	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"elementtype");
	e_select_6.setAttribute('id',"elementtype");
	e_td_5.appendChild(e_select_6);

	addListener(e_select_6, 'change', this.updateForm_selementByType.bindAsEventListener(this,false));


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"0");
	e_option_7.appendChild(document.createTextNode(locale['S_HOST']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"1");
	e_option_7.appendChild(document.createTextNode(locale['S_MAP']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"2");
	e_option_7.appendChild(document.createTextNode(locale['S_TRIGGER']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"3");
	e_option_7.appendChild(document.createTextNode(locale['S_HOST_GROUP']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"4");
	e_option_7.appendChild(document.createTextNode(locale['S_IMAGE']));
	e_select_6.appendChild(e_option_7);

// LABEL
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxLabel = e_input_6
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxLabel");
	e_input_6.setAttribute('id', "chkboxLabel");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_LABEL']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_textarea_6 = document.createElement('textarea');
this.selementForm.label = e_textarea_6;

	e_textarea_6.setAttribute('cols',"56");
	e_textarea_6.setAttribute('rows',"4");
	e_textarea_6.setAttribute('name',"label");
	e_textarea_6.className = "biginput";
	e_td_5.appendChild(e_textarea_6);

// LABEL LOCATION
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";


	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxLabelLocation = e_input_6
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxLabelLocation");
	e_input_6.setAttribute('id', "chkboxLabelLocation");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));

	e_td_5.appendChild(document.createTextNode(locale['S_LABEL_LOCATION']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.label_location = e_select_6;

	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"label_location");
	e_select_6.setAttribute('id',"label_location");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"-1");
	e_option_7.appendChild(document.createTextNode('-'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"0");
	e_option_7.appendChild(document.createTextNode(locale['S_BOTTOM']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"1");
	e_option_7.appendChild(document.createTextNode(locale['S_LEFT']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"2");
	e_option_7.appendChild(document.createTextNode(locale['S_RIGHT']));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"3");
	e_option_7.appendChild(document.createTextNode(locale['S_TOP']));
	e_select_6.appendChild(e_option_7);

// Element Name
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.elementName = e_tr_4;
this.selementForm.massEdit.elementName = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
this.selementForm.typeDOM.elementCaption = e_td_5;

	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_HOST']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.elementName = e_input_6;

	e_input_6.setAttribute('readonly',"readonly");
	e_input_6.setAttribute('value',"");
	e_input_6.setAttribute('size',"56");
	e_input_6.setAttribute('id',"elementName");
	e_input_6.setAttribute('name',"elementName");
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode('  '));

	var e_span_6 = document.createElement('span');
this.selementForm.elementTypeSelect = e_span_6;

	e_span_6.className = "link";
	e_span_6.appendChild(document.createTextNode(locale['S_SELECT']));
	e_td_5.appendChild(e_span_6);

// ICON OFF
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_off = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";


	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxIconid_off = e_input_6
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxIconid_off");
	e_input_6.setAttribute('id', "chkboxIconid_off");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));

	e_td_5.appendChild(document.createTextNode(locale['S_ICON_DEFAULT']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.iconid_off = e_select_6;

	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"iconid_off");
	e_select_6.setAttribute('id',"iconid_off");
	e_td_5.appendChild(e_select_6);


// ADVANCED ICONS
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.advanced_icons = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_USE_ADVANCED_ICONS']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.advanced_icons = e_input_6;

	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "advanced_icons");
	e_input_6.setAttribute('id', "advanced_icons");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);
	addListener(e_input_6, 'click', this.updateForm_selementByIcons.bindAsEventListener(this));

	var icons = zbxSelementIcons['icons'];
	for(var iconid in icons){
		if(empty(icons[iconid])) continue;


		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value', iconid);
		e_option_7.appendChild(document.createTextNode(icons[iconid]));

		e_select_6.appendChild(e_option_7);
	}

// ICON ON
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_on = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxIconid_on = e_input_6;
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxIconid_on");
	e_input_6.setAttribute('id', "chkboxIconid_on");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_ICON_PROBLEM']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.iconid_on = e_select_6;

	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"iconid_on");
	e_select_6.setAttribute('id',"iconid_on");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value', '0');
	e_option_7.appendChild(document.createTextNode(locale['S_DEFAULT']));
	e_select_6.appendChild(e_option_7);


	var icons = zbxSelementIcons['icons'];
	for(var iconid in icons){
		if(empty(icons[iconid])) continue;

		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value', iconid);
		e_option_7.appendChild(document.createTextNode(icons[iconid]));
		e_select_6.appendChild(e_option_7);
	}


// ICON UNKNOWN
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_unknown = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxIconid_unknown = e_input_6;
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxIconid_unknown");
	e_input_6.setAttribute('id', "chkboxIconid_unknown");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_ICON_UNKNOWN']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.iconid_unknown = e_select_6;

	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"iconid_unknown");
	e_select_6.setAttribute('id',"iconid_unknown");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value', '0');
	e_option_7.appendChild(document.createTextNode(locale['S_DEFAULT']));
	e_select_6.appendChild(e_option_7);


	var icons = zbxSelementIcons['icons'];
	for(var iconid in icons){
		if(empty(icons[iconid])) continue;

		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value', iconid);
		e_option_7.appendChild(document.createTextNode(icons[iconid]));
		e_select_6.appendChild(e_option_7);
	}


// ICON MAINTENANCE
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_maintenance = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxIconid_maintenance = e_input_6;
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxIconid_maintenance");
	e_input_6.setAttribute('id', "chkboxIconid_maintenance");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_ICON_MAINTENANCE']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.iconid_maintenance = e_select_6;

	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"iconid_maintenance");
	e_select_6.setAttribute('id',"iconid_maintenance");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value', '0');
	e_option_7.appendChild(document.createTextNode(locale['S_DEFAULT']));
	e_select_6.appendChild(e_option_7);


	var icons = zbxSelementIcons['icons'];
	for(var iconid in icons){
		if(empty(icons[iconid])) continue;

		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value', iconid);
		e_option_7.appendChild(document.createTextNode(icons[iconid]));
		e_select_6.appendChild(e_option_7);
	}


// ICON DISABLED
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_disabled = e_tr_4;

	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxIconid_disabled = e_input_6;
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxIconid_disabled");
	e_input_6.setAttribute('id', "chkboxIconid_disabled");
	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_ICON_DISABLED']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.selementForm.iconid_disabled = e_select_6;

	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"iconid_disabled");
	e_select_6.setAttribute('id',"iconid_disabled");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value', '0');
	e_option_7.appendChild(document.createTextNode(locale['S_DEFAULT']));
	e_select_6.appendChild(e_option_7);


	var icons = zbxSelementIcons['icons'];
	for(var iconid in icons){
		if(empty(icons[iconid])) continue;

		var e_option_7 = document.createElement('option');
		e_option_7.setAttribute('value', iconid);
		e_option_7.appendChild(document.createTextNode(icons[iconid]));
		e_select_6.appendChild(e_option_7);
	}

// X
	var e_tr_4 = document.createElement('tr');
this.selementForm.massEdit.x = e_tr_4;
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_COORDINATE_X']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.x = e_input_6;

	e_input_6.setAttribute('onchange'," if(isNaN(parseInt(this.value,10))) this.value = 0;  else this.value = parseInt(this.value,10);");
	e_input_6.setAttribute('style',"text-align: right;");
	e_input_6.setAttribute('maxlength',"5");
	e_input_6.setAttribute('value', '0');
	e_input_6.setAttribute('size',"5");
	e_input_6.setAttribute('id',"x");
	e_input_6.setAttribute('name',"x");
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);

// Y
	var e_tr_4 = document.createElement('tr');
this.selementForm.massEdit.y = e_tr_4;
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_COORDINATE_Y']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.y = e_input_6;

	e_input_6.setAttribute('onchange'," if(isNaN(parseInt(this.value,10))) this.value = 0;  else this.value = parseInt(this.value,10);");
	e_input_6.setAttribute('style',"text-align: right;");
	e_input_6.setAttribute('maxlength',"5");
	e_input_6.setAttribute('value', '0');
	e_input_6.setAttribute('size',"5");
	e_input_6.setAttribute('id',"y");
	e_input_6.setAttribute('name',"y");
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);


// URL
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";

	var e_input_6 = document.createElement('input');
this.selementForm.massEdit.chkboxURL = e_input_6;
	e_input_6.setAttribute('type', 'checkbox');
	e_input_6.setAttribute('name', "chkboxURL");
	e_input_6.setAttribute('id', "chkboxURL");

	e_input_6.className = 'checkbox';
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(document.createTextNode(locale['S_URL']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.url = e_input_6;
	e_input_6.setAttribute('value', '');
	e_input_6.setAttribute('size',"56");
	e_input_6.setAttribute('id',"url");
	e_input_6.setAttribute('name',"url");
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);


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
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_APPLY']);


	addListener(e_input_6, 'click', this.saveForm_selement.bindAsEventListener(this));

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"remove");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_REMOVE']);

	addListener(e_input_6, 'click', this.deleteForm_selement.bindAsEventListener(this));

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"close");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_CLOSE']);

	addListener(e_input_6, 'click', this.hideForm.bindAsEventListener(this));

	e_td_5.appendChild(e_input_6);
},

updateForm_selement: function(e, selementid){
	this.debug('updateForm_selement');
//--

// Create if not exists
	if(is_null($('selementForm'))){
		this.createForm_selement(e);
		$('divSelementForm').appendChild(this.selementForm.form);
		$('divSelementForm').appendChild(document.createElement('br'));
	}
//--

	if(this.selection.count == 1){
		var selement = this.selements[selementid];

// SELEMENT
		this.selementForm.selementid.value = selementid;

// Element Type
		this.selementForm.elementtype.selectedIndex = selement.elementtype;

// Label
		this.selementForm.label.value = selement.label;

// Label Location
		this.selementForm.label_location.selectedIndex = parseInt(selement.label_location,10)+1;

// Element
		this.selementForm.elementid.value = selement.elementid;
		this.selementForm.elementName.value = selement.elementName;

// Icon OK
		for(var i=0; i<this.selementForm.iconid_off.options.length; i++){
			if(!isset(i, this.selementForm.iconid_off.options)) continue;

			if(this.selementForm.iconid_off.options[i].value === selement.iconid_off){
				this.selementForm.iconid_off.options[i].selected = true;
			}
		}

		var advanced_icons = false;

// Icon PROBLEM
		advanced_icons = advanced_icons || (selement.iconid_on != 0);
		for(var i=0; i<this.selementForm.iconid_on.options.length; i++){
			if(!isset(i, this.selementForm.iconid_on.options)) continue;

			if(this.selementForm.iconid_on.options[i].value === selement.iconid_on){
				this.selementForm.iconid_on.options[i].selected = true;
			}
		}

// Icon UNKNOWN
		advanced_icons = advanced_icons || (selement.iconid_unknown != 0);
		for(var i=0; i<this.selementForm.iconid_unknown.options.length; i++){
			if(!isset(i, this.selementForm.iconid_unknown.options)) continue;

			if(this.selementForm.iconid_unknown.options[i].value === selement.iconid_unknown){
				this.selementForm.iconid_unknown.options[i].selected = true;
			}
		}

// Icon MAINTENANCE
		advanced_icons = advanced_icons || (selement.iconid_maintenance != 0);
		for(var i=0; i<this.selementForm.iconid_maintenance.options.length; i++){
			if(!isset(i, this.selementForm.iconid_maintenance.options)) continue;

			if(this.selementForm.iconid_maintenance.options[i].value === selement.iconid_maintenance){
				this.selementForm.iconid_maintenance.options[i].selected = true;
			}
		}

// Icon DISABLED

		advanced_icons = advanced_icons || (selement.iconid_disabled != 0);
		for(var i=0; i<this.selementForm.iconid_disabled.options.length; i++){
			if(!isset(i, this.selementForm.iconid_disabled.options)) continue;

			if(this.selementForm.iconid_disabled.options[i].value === selement.iconid_disabled){
				this.selementForm.iconid_disabled.options[i].selected = true;
			}
		}

// ADVANCED ICONS
		this.selementForm.advanced_icons.checked = (advanced_icons != 0);


// X & Y
		$(this.selementForm.x).value = selement.x;
		$(this.selementForm.y).value = selement.y;

// URL
		this.selementForm.url.value = selement.url;

		this.updateForm_selementByType(e, false);
	}
	else{
// SELEMENT
		this.selementForm.selementid.value = selementid;

// Label
		this.selementForm.label.value = '';

// Label Location
		this.selementForm.label_location.selectedIndex = 0;

// Icon OK
		this.selementForm.iconid_off.selectedIndex = 0;

// Icon PROBLEM
		this.selementForm.iconid_on.selectedIndex = 0;

// Icon UNKNOWN
		this.selementForm.iconid_unknown.selectedIndex = 0;

// Icon MAINTENANCE
		this.selementForm.iconid_maintenance.selectedIndex = 0;

// Icon DISABLED
		this.selementForm.iconid_disabled.selectedIndex = 0;

// URL
		this.selementForm.url.value = '';

		this.updateForm_selementByType(e,true);
	}
},

// UPDATE FORM BY element TYPE
updateForm_selementByIcons: function(e){
	this.debug('updateForm_selementByIcons');
//--

	var advanced = this.selementForm.advanced_icons.checked;
	var display_style = IE?'block':'table-row';

	if(advanced){
		this.selementForm.typeDOM.iconid_on.style.display = display_style;
		this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
		this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
		this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
	}
	else{
		this.selementForm.typeDOM.iconid_on.style.display = 'none';
		this.selementForm.typeDOM.iconid_unknown.style.display = 'none';
		this.selementForm.typeDOM.iconid_maintenance.style.display = 'none';
		this.selementForm.typeDOM.iconid_disabled.style.display = 'none';
	}

},

// UPDATE FORM BY element TYPE
updateForm_selementByType: function(e, multi){
	this.debug('updateForm_selementByType');
//--
	if(typeof(multi) == 'undefined') multi = false;
	var display_style = IE?'block':'table-row';

	if(multi){
		this.selementForm.massEdit.chkboxLabel.style.display = 'inline';
		this.selementForm.massEdit.chkboxLabelLocation.style.display = 'inline';
		this.selementForm.massEdit.chkboxIconid_off.style.display = 'inline';
		this.selementForm.massEdit.chkboxIconid_on.style.display = 'inline';
		this.selementForm.massEdit.chkboxIconid_unknown.style.display = 'inline';
		this.selementForm.massEdit.chkboxIconid_maintenance.style.display = 'inline';
		this.selementForm.massEdit.chkboxIconid_disabled.style.display = 'inline';
		this.selementForm.massEdit.chkboxURL.style.display = 'inline';

		this.selementForm.massEdit.elementtype.style.display = 'none';
		this.selementForm.massEdit.elementName.style.display = 'none';

		this.selementForm.typeDOM.iconid_off.style.display = display_style;

		this.selementForm.typeDOM.advanced_icons.style.display = display_style;
		this.selementForm.typeDOM.iconid_on.style.display = display_style;
		this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
		this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
		this.selementForm.typeDOM.iconid_disabled.style.display = display_style;

		this.selementForm.massEdit.x.style.display = 'none';
		this.selementForm.massEdit.y.style.display = 'none';

		this.selementForm.advanced_icons.checked = true;

		this.updateForm_selementByIcons(e);
		return true;
	}
	else{
		this.selementForm.massEdit.chkboxLabel.style.display = 'none';
		this.selementForm.massEdit.chkboxLabelLocation.style.display = 'none';
		this.selementForm.massEdit.chkboxIconid_off.style.display = 'none';
		this.selementForm.massEdit.chkboxIconid_on.style.display = 'none';
		this.selementForm.massEdit.chkboxIconid_unknown.style.display = 'none';
		this.selementForm.massEdit.chkboxIconid_maintenance.style.display = 'none';
		this.selementForm.massEdit.chkboxIconid_disabled.style.display = 'none';
		this.selementForm.massEdit.chkboxURL.style.display = 'none';

		this.selementForm.massEdit.chkboxLabel.checked = false;
		this.selementForm.massEdit.chkboxLabelLocation.checked = false;
		this.selementForm.massEdit.chkboxIconid_off.checked = false;
		this.selementForm.massEdit.chkboxIconid_on.checked = false;
		this.selementForm.massEdit.chkboxIconid_unknown.checked = false;
		this.selementForm.massEdit.chkboxIconid_maintenance.checked = false;
		this.selementForm.massEdit.chkboxIconid_disabled.checked = false;
		this.selementForm.massEdit.chkboxURL.checked = false;

		this.selementForm.massEdit.elementtype.style.display = display_style;
		this.selementForm.massEdit.elementName.style.display = display_style;
		this.selementForm.massEdit.x.style.display = display_style;
		this.selementForm.massEdit.y.style.display = display_style;
	}

	var selementid = this.selementForm.selementid.value;
	var elementtype = this.selementForm.elementtype.selectedIndex;

	if(this.selements[selementid].elementtype != elementtype){
		this.selementForm.elementName.value = '';
		this.selementForm.elementid.value = '0';
	}

	var srctbl = '';
	var srcfld1 = '';
	var srcfld2 = '';


	switch(elementtype.toString()){
		case '0':
// host
			var srctbl = 'hosts';
			var srcfld1 = 'hostid';
			var srcfld2 = 'host';
			$(this.selementForm.typeDOM.elementCaption).update(locale['S_HOST']);

			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;

			this.selementForm.typeDOM.advanced_icons.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
			this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
		break;
		case '1':
// maps
			var srctbl = 'sysmaps';
			var srcfld1 = 'sysmapid';
			var srcfld2 = 'name';
			$(this.selementForm.typeDOM.elementCaption).update(locale['S_MAP']);

			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;

			this.selementForm.typeDOM.advanced_icons.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = 'none';
			this.selementForm.typeDOM.iconid_maintenance.style.display = 'none';
			this.selementForm.typeDOM.iconid_disabled.style.display = 'none';
		break;
		case '2':
// trigger
			var srctbl = 'triggers';
			var srcfld1 = 'triggerid';
			var srcfld2 = 'description';
			$(this.selementForm.typeDOM.elementCaption).update(locale['S_TRIGGER']);

			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;

			this.selementForm.typeDOM.advanced_icons.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
			this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
		break;
		case '3':
// host group
			var srctbl = 'host_group';
			var srcfld1 = 'groupid';
			var srcfld2 = 'name';
			$(this.selementForm.typeDOM.elementCaption).update(locale['S_HOST_GROUP']);

			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;

			this.selementForm.typeDOM.advanced_icons.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = 'none';
			this.selementForm.typeDOM.iconid_disabled.style.display = 'none';

		break;
		case '4':
// image
			$(this.selementForm.typeDOM.elementCaption).update(locale['S_IMAGE']);

			this.selementForm.typeDOM.elementName.style.display = 'none';
			this.selementForm.typeDOM.iconid_off.style.display = display_style;

// initiats icons hide
			this.selementForm.advanced_icons.checked = false;
			this.selementForm.typeDOM.advanced_icons.style.display = 'none';
		break;
	}

	if(!empty(srctbl)){
		var popup_url = 'popup.php?writeonly=1&real_hosts=1&dstfrm=selementForm&dstfld1=elementid&dstfld2=elementName';
		popup_url+= '&srctbl='+srctbl;
		popup_url+= '&srcfld1='+srcfld1;
		popup_url+= '&srcfld2='+srcfld2;

		if(elementtype.toString() == '1') popup_url+= '&excludeids[]='+this.sysmapid;

		this.selementForm.elementTypeSelect.onclick =  function(){ PopUp(popup_url,450,450);};
	}

	this.updateForm_selementByIcons(e);
},

saveForm_selement: function(e){
	this.debug('saveForm_selement');
//--

	if(this.selection.count == 1){
		var selementid = this.selementForm.selementid.value;
		var params = {};

// Element Type
		params.elementtype = this.selementForm.elementtype.selectedIndex;

// Label
		params.label = this.selementForm.label.value;

// Label Location
		params.label_location = parseInt(this.selementForm.label_location.selectedIndex, 10) - 1;


// Element
		params.elementid = this.selementForm.elementid.value;
		params.elementName = this.selementForm.elementName.value;

		if((params.elementid == 0) && (params.elementtype != 4)){
			switch(params.elementtype.toString()){
//host
				case '0': this.info('Host is not selected.'); return false; break;
//map
				case '1': this.info('Map is not selected.'); return false; break;
//tr
				case '2': this.info('Trigger is not selected.'); return false; break;
//hg
				case '3': this.info('Host group is not selected.'); return false; break;
// image
				case '4':
				default:
			}
		}


// Icon OK
		params.iconid_off = this.selementForm.iconid_off.options[this.selementForm.iconid_off.selectedIndex].value;

// Icon PROBLEM
		params.iconid_on = this.selementForm.iconid_on.options[this.selementForm.iconid_on.selectedIndex].value;

// Icon UNKNOWN
		params.iconid_unknown = this.selementForm.iconid_unknown.options[this.selementForm.iconid_unknown.selectedIndex].value;

// Icon MAINTENANCE
		params.iconid_maintenance = this.selementForm.iconid_maintenance.options[this.selementForm.iconid_maintenance.selectedIndex].value;

// Icon DISABLED
		params.iconid_disabled = this.selementForm.iconid_disabled.options[this.selementForm.iconid_disabled.selectedIndex].value;

// Advanced icons
		if(!this.selementForm.advanced_icons.checked){
			params.iconid_on = 0;
			params.iconid_unknown = 0;
			params.iconid_maintenance = 0;
			params.iconid_disabled = 0;
		}

// X & Y
		var dims = getDimensions(this.selements[selementid].html_obj);

		params.x = parseInt(this.selementForm.x.value, 10);
		params.y = parseInt(this.selementForm.y.value, 10);

		if((params.x+dims.width) > this.sysmap.width) params.x = this.sysmap.width - dims.width;
		else if(params.x < 0) params.x = 0;

		if((params.y+dims.height) > this.sysmap.height) params.y = this.sysmap.height - dims.height;
		else if(params.y < 0) params.y = 0;

		this.selementForm.x.value = params.x;
		this.selementForm.y.value = params.y;

// URL
		params.url = this.selementForm.url.value;

		this.updateSelementOption(selementid, params);
	}
	else{
		var params = {};

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

// Icon UNKNOWN
		if(this.selementForm.massEdit.chkboxIconid_unknown.checked)
			params.iconid_unknown = this.selementForm.iconid_unknown.options[this.selementForm.iconid_unknown.selectedIndex].value;

// Icon MAINTENANCE
		if(this.selementForm.massEdit.chkboxIconid_maintenance.checked)
			params.iconid_maintenance = this.selementForm.iconid_maintenance.options[this.selementForm.iconid_maintenance.selectedIndex].value;

// Icon DISABLED
		if(this.selementForm.massEdit.chkboxIconid_disabled.checked)
			params.iconid_disabled = this.selementForm.iconid_disabled.options[this.selementForm.iconid_disabled.selectedIndex].value;

// URL
		if(this.selementForm.massEdit.chkboxURL.checked)
			params.url = this.selementForm.url.value;

		for(var i=0; i < this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;
			if(!isset(this.selection.selements[i], this.selements)) continue;

			var selementid = this.selection.selements[i];
			this.updateSelementOption(selementid, params);
		}
		this.updateSelementOption(selementid, params);
	}

	this.updateMapImage();
	this.update_multiContainer(e);
//	this.hideForm();
},

deleteForm_selement: function(e){
	this.debug('deleteForm_selement');
//--
	//removing all selected elements
	this.remove_selements();

},

//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************



// LINK FORM
//**************************************************************************************************************************************************
//**************************************************************************************************************************************************

hideForm_link: function(e){
	this.debug('hideForm_link');
//--

	if(!isset('form', this.linkForm) || empty(this.linkForm.form)) return false;

	this.linkForm.form.parentNode.removeChild(this.linkForm.form);
	this.linkForm.form = null;
},

createForm_link: function(e){
	this.debug('createForm_link');
//--


// var initialization of diferent types of form
//	this.linkForm.massEdit = {};

// Form creation
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


	var e_span_6 = document.createElement('span');
	e_span_6.setAttribute('target',"_blank");
	e_span_6.setAttribute('style',"padding-left: 5px; float: right; text-decoration: none;");
	e_span_6.setAttribute('onclick','window.open("http://www.zabbix.com/documentation.php");');

	e_td_5.appendChild(e_span_6);


	var e_div_7 = document.createElement('div');
	e_div_7.className = "iconhelp";
	e_div_7.appendChild(document.createTextNode(' '));
	e_span_6.appendChild(e_div_7);


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
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_LABEL']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_textarea_6 = document.createElement('textarea');
this.linkForm.linklabel = e_textarea_6;

	e_textarea_6.setAttribute('cols',"48");
	e_textarea_6.setAttribute('rows',"4");
	e_textarea_6.setAttribute('name',"linklabel");
	e_textarea_6.setAttribute('id',"linklabel");
	e_textarea_6.className = "biginput";
	e_td_5.appendChild(e_textarea_6);


// SELEMENTID1
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_ELEMENT']+' 1'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.linkForm.selementid1 = e_select_6;
	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"selementid1");
	e_select_6.setAttribute('id',"selementid1");
	e_td_5.appendChild(e_select_6);

// SELEMENTID2
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_tr_4.appendChild(e_td_5);


	e_td_5.appendChild(document.createTextNode(locale['S_ELEMENT']+' 2'));


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.linkForm.selementid2 = e_select_6;
	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"selementid2");
	e_select_6.setAttribute('id',"selementid2");
	e_td_5.appendChild(e_select_6);


// LINK STATUS INDICATORS
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_LINK_INDICATORS']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
this.linkForm.linkIndicatorsTable = e_td_5;

	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);

// LINE TYPE OK
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_tr_4.appendChild(e_td_5);
	e_td_5.appendChild(document.createTextNode(locale['S_TYPE_OK']));


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
this.linkForm.drawtype = e_select_6;

	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
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
	e_tr_4.className = "even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode(locale['S_COLOR_OK']));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
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
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);


	var e_div_6 = document.createElement('div');
this.linkForm.colorPicker = e_div_6;

	e_div_6.setAttribute('title',"#000055");
	e_div_6.setAttribute('id',"lbl_color");
	e_div_6.setAttribute('name',"lbl_color");
	e_div_6.className = "pointer";
	addListener(e_div_6, 'click', function(){ show_color_picker('color');});
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
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_APPLY']);
	e_td_5.appendChild(e_input_6);
	addListener(e_input_6, 'click', this.saveForm_link.bindAsEventListener(this));


	e_td_5.appendChild(document.createTextNode(' '));


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"remove");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_REMOVE']);
	e_td_5.appendChild(e_input_6);
	addListener(e_input_6, 'click', this.deleteForm_link.bindAsEventListener(this));


	e_td_5.appendChild(document.createTextNode(' '));


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"close");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',locale['S_CLOSE']);
	addListener(e_input_6, 'click', this.hideForm_link.bindAsEventListener(this));


	e_td_5.appendChild(e_input_6);
},

updateForm_link: function(e, linkid){
	this.debug('updateForm_link');
//--

	if(!isset(linkid, this.links)) return false;

	if(is_null($('linkForm'))){
		this.createForm_link(e);
		$('divSelementForm').appendChild(this.linkForm.form);
	}

	var maplink = this.links[linkid];

// LINKID
	this.linkForm.linkid.value = linkid;


// LABEL
	this.linkForm.linklabel.value = maplink.label;

// SELEMENTID1
	$(this.linkForm.selementid1).update();
	for(var selementid in this.selements){
		if(empty(this.selements[selementid])) continue;

		var e_option_7 = document.createElement('option');

		if(maplink.selementid1 == selementid){
			e_option_7.setAttribute('selected',"selected");
		}
		else if(maplink.selementid2 == selementid){
			continue;
		}

		e_option_7.setAttribute('value', selementid);
		e_option_7.appendChild(document.createTextNode(this.selements[selementid].label_expanded));

		this.linkForm.selementid1.appendChild(e_option_7);
	}


// SELEMENTID2
	$(this.linkForm.selementid2).update();
	for(var selementid in this.selements){
		if(empty(this.selements[selementid])) continue;

		var e_option_7 = document.createElement('option');

		if(maplink.selementid2 == selementid){
			e_option_7.setAttribute('selected',"selected");
		}
		else if(maplink.selementid1 == selementid){
			continue;
		}

		e_option_7.setAttribute('value', selementid);
		e_option_7.appendChild(document.createTextNode(this.selements[selementid].label_expanded));

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

		this.linkForm_addLinktrigger(maplink.linktriggers[linktriggerid]);
	}

	$(this.linkForm.linkIndicatorsTable).update(e_table_6);

	var e_br_6 = document.createElement('br');
	this.linkForm.linkIndicatorsTable.appendChild(e_br_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"Add");
	e_input_6.setAttribute('value',locale['S_ADD']);
	e_input_6.className = "button";
	this.linkForm.linkIndicatorsTable.appendChild(e_input_6);

	var url = 'popup_link_tr.php?form=1&mapid='+this.id;
	addListener(e_input_6, 'click', function(){ PopUp(url,640, 420, 'ZBX_Link_Indicator'); });


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"Remove");
	e_input_6.setAttribute('value',locale['S_REMOVE']);
	e_input_6.className = "button";
	this.linkForm.linkIndicatorsTable.appendChild(e_input_6);

	addListener(e_input_6, 'click', function(){ remove_childs('linkForm','link_triggerids','tr'); });
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


linkForm_addLinktrigger: function(linktrigger){
	this.debug('linkForm_addLinktrigger');
//--

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
	e_tr_8.className = "even_row";
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

	// var e_input_10 = document.createElement('input');
	// e_input_10.setAttribute('name',"link_triggers["+triggerid+"][color]");
	// e_input_10.setAttribute('id',"link_triggers["+triggerid+"][color]");
	// e_input_10.setAttribute('type',"hidden");
	// e_input_10.setAttribute('value',linktrigger.color);
	// e_td_9.appendChild(e_input_10);

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
	e_select_10.className = 'biginput';

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
	e_input_22.className = "biginput";
	e_td_9.appendChild(e_input_22);

	var e_div_10 = document.createElement('div');
//this.linkForm.colorPicker = e_div_10;

	e_div_10.setAttribute('title', '#'+linktrigger.color);
	e_div_10.setAttribute('id',"lbl_link_triggers["+triggerid+"][color]");
	e_div_10.setAttribute('name',"lbl_link_triggers["+triggerid+"][color]");
	e_div_10.className = "pointer";
	addListener(e_div_10, 'click', function(){ show_color_picker("link_triggers["+triggerid+"][color]");});
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

saveForm_link: function(e){
	this.debug('saveForm_link');
//--

	var linkid = this.linkForm.linkid.value;
	if(!isset(linkid, this.links)) return false;

	var maplink = this.links[linkid];


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
		this.remove_linktrigger(maplink.linkid, linktriggerid);
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

		this.add_linktrigger(linkid, linktrigger);
	}
//--

//SDJ(params);
	this.update_link_option(linkid, params);
//SDJ(this.links[linkid]);

	this.update_linkContainer(e);

	/**
	 * Commented out, because form does not need to be hidden when "apply" is pressed
	 * @see ZBX-1442
	 * @author Konstantin Buravcov
	 * @since 08.09.2010
	 */
	//this.hideForm_link(e);

	this.updateMapImage();
},

deleteForm_link: function(e){
	this.debug('deleteForm_link');
//--

	var linkid = this.linkForm.linkid.value;
	if(!isset(linkid, this.links)) return false;

	var maplink = this.links[linkid];

	if(Confirm('Remove link between "'+this.selements[maplink.selementid1].label+'" and "'+this.selements[maplink.selementid2].label+'"?')){
		this.remove_link(linkid, true);
		this.update_linkContainer(e);
		this.hideForm_link(e);
	}
	else
		return false;
}

//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************
});

var CGrid = Class.create(CDebug, {
mapObjectId:	null,						// own id
sysmapid:	null,				// sysmapid
showGrid:	true,				// grid On|Off
autoAlign:	true,				// align icons on drag end
gridSize:	'50',				// grid size

initialize: function($super, id, params){
	this.mapObjectId = id;

	$super('CGrid['+id+']');

	if(!params) var params = {};

	if(isset('gridsize', params))
		addListener(params.gridsize, 'change', this.setGridSize.bindAsEventListener(this), false);
	if(isset('gridautoalign', params))
		addListener(params.gridautoalign, 'click', this.setGridAutoAlign.bindAsEventListener(this), false);
	if(isset('gridshow', params))
		addListener(params.gridshow, 'click', this.setGridView.bindAsEventListener(this), false);
	if(isset('gridalignall', params))
		addListener(params.gridalignall, 'click', this.gridAlignIcons.bindAsEventListener(this), false);
},

setGridSize: function(e){
	this.debug('setGridSize');
//--

	var domObject = Event.element(e);
	var tmpGS = domObject.options[domObject.selectedIndex].value.split('x');

	if(!isset(0, tmpGS)) this.gridSize = 50;
	else this.gridSize = tmpGS[0];

	this.updateMapView(e);
},

setGridAutoAlign: function(e){
	this.debug('setGridAutoAlign');
//--

	var domObject = Event.element(e);
	if(this.autoAlign){
		this.autoAlign = false;
		domObject.update(locale['S_OFF']);
	}
	else{
		this.autoAlign = true;
		domObject.update(locale['S_ON']);
	}
},

setGridView: function(e){
	this.debug('setGridView');
//--
	var domObject = Event.element(e);
	if(this.showGrid){
		this.showGrid = false;
		$(domObject).update(locale['S_HIDDEN']);
	}
	else{
		this.showGrid = true;
		$(domObject).update(locale['S_SHOWN']);
	}

	this.updateMapView(e);
},

// ACTION
gridAlignIcons: function(e){
	this.debug('gridAlignIcons');

	var tmpAutoAlign = this.autoAlign;
	this.autoAlign = true;

	for(var selementid in ZBX_SYSMAPS[this.mapObjectId].map.selements){
		if(empty(ZBX_SYSMAPS[this.mapObjectId].map.selements[selementid])) continue;

		ZBX_SYSMAPS[this.mapObjectId].map.alignSelement(selementid);
	}

	this.autoAlign = tmpAutoAlign;
	this.updateMapView(e);
},

// Update
updateMapView: function(e){
	this.debug('setGridView');
//--

	ZBX_SYSMAPS[this.mapObjectId].map.updateMapImage();
}
});
//]]

// *******************************************************************
//		SELEMENT object (unfinished)
// *******************************************************************
var CSelement = Class.create(CDebug, {
sysmap:				null,		// sysmap object reference

data: {
	selementid:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	elementtype:		4,			// 5-UNDEFINED
	elementid:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	elementName:		'',			// element name
	iconid_off:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_on:			0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_unknown:		0,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_maintenance:	0,		// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_disabled:	0,			// ALWAYS must be a STRING (js doesn't support uint64)
	label:				locale['S_NEW_ELEMENT'],	// Element label
	label_expanded:		locale['S_NEW_ELEMENT'],	// Element label macros expanded
	label_location:		3,
	x:					0,
	y:					0,
	url:				'',
	html_obj:			null,			// reference to html obj
	html_objid:			null,			// html elements id
	selected:			0				// element is not selected
},

initialize: function($super, sysmap, params){
	this.sysmap = sysmap;
	$super('CSelement['+id+']');
//--

	if(typeof(params) == 'undefined'){
		var url = new Curl(location.href);
		var params = {
			'favobj': 	'selements',
			'favid':	this.id,
			'sysmapid':	this.sysmapid,
			'action':	'new_selement'
		};

		params['selements'] = Object.toJSON({'0': selement});

		new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
						{
							'method': 'post',
							'parameters':params,
							'onSuccess': function(resp){ },
	//						'onSuccess': function(resp){ SDI(resp.responseText); },
							'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
						}
		);
	}
	else{
		var selementid = 0;
		if((typeof(params['selementid']) == 'undefined') || (params['selementid'] == 0)){
			do{
				selementid = parseInt(Math.random(1000000000) * 1000000000);
				selementid = selementid.toString();
			}while(isset(selementid, this.sysmap.selements));

			params['new'] = 'new';
			params['selementid'] = selementid;
		}
		else{
			selementid = params.selementid;
		}

		if(typeof(this.selements[selementid]) == 'undefined'){
			selement.selected = null;
		}
		else{
			selement.selected = this.sysmap.selements[selementid].selected;
		}

		if(isset('updateIcon', params) && (params['updateIcon'] != 0)){
			selement.html_obj = this.addImage(params);
			selement.image = null;
		}

		for(var key in params){
			if(is_null(params[key])) continue;

			if(is_number(params[key])) params[key] = params[key].toString();
			this.data[key] = params[key];
		}
	}
},

updateOption: function(params){ // params = {'key': value, 'key': value}
	this.debug('updateOption');
//--

	for(var key in params){
		if(is_null(params[key])) continue;

		if(is_number(params[key])) params[key] = params[key].toString();
		this.data[key] = params[key];
	}

	this.update();
},

update: function(){
	this.debug('update');
//--

	var url = new Curl(location.href);

	var params = {
		'favobj': 	'selements',
		'favid':	this.sysmap.id,
		'sysmapid':	this.sysmap.sysmapid,
		'action':	'get_img'
	}

	params['selements'] = Object.toJSON({'0': this.data});

	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},

remove: function(){
	this.debug('remove');
//--

	this.removeImage();

// remove selement
	this.sysmap.selements[selementid] = null;
	delete(this.sysmap.selements[selementid]);

	this.sysmap.updateMapImage();
},

select: function(multi){
	this.debug('select');
//--

	var multi = multi || false;

	var position = null;

	if(is_null(this.selected)){
		position = this.sysmap.selection.position;

		this.sysmap.selection.selements[position] = selementid;
		this.selected = position;

		this.html_obj.style.border = '1px #3333FF solid';
		this.html_obj.style.backgroundColor = '#00AAAA';
		this.html_obj.style.opacity = '.60';

		this.sysmap.selection.count++;
		this.sysmap.selection.position++;
	}
	else if((this.sysmap.selection.count > 1) && !multi){
// if selected several selements and then we clicked on one of them
	}
	else{
		this.sysmap.selection.count--;
		position = this.selected;

		this.sysmap.selection.selements[position] = null;
		delete(this.sysmap.selection.selements[position]);

		this.sysmap.selements[selementid].selected = null;

		this.html_obj.style.border = '0px';
		this.html_obj.style.backgroundColor = 'transparent';
		this.html_obj.style.opacity = '1';
	}

	if(!multi && (this.sysmap.selection.count > 1)){
		for(var i=0; i<this.sysmap.selection.position; i++){
			if(!isset(i,this.sysmap.selection.selements) || (this.sysmap.selection.selements[i] == selementid)) continue;;

			this.sysmap.selection.count--;

			var tmp_selementid = this.sysmap.selection.selements[i];

			this.sysmap.selements[this.selection.selements[i]].selected = null;
			this.sysmap.selements[this.selection.selements[i]].html_obj.style.border = '0px';
			this.sysmap.selements[this.selection.selements[i]].html_obj.style.backgroundColor = 'transparent';
			this.sysmap.selements[this.selection.selements[i]].html_obj.style.opacity = '1';

			this.sysmap.selection.selements[i] = null;
			delete(this.sysmap.selection.selements[i]);
		}
	}

return false;
},

// ELEMENTS
addImage: function(){
	this.debug('addImage');

	var dom_id = 'selement_'+this.selementid;

	var selement_div = $(dom_id);
	if(is_null(selement_div)){
//		var selement_div = document.createElement('img');
		var selement_div = document.createElement('div');
		this.container.appendChild(selement_div);

		selement_div.setAttribute('id',dom_id);
//		selement_div.setAttribute('alt','selement_'+selement.id);
		selement_div.style.position = 'absolute';
		selement_div.style.visibility = 'hidden';

		this.makeSelementDragable(selement_div);
	}

	var position = {};
	position.top = parseInt(selement.y,10);
	position.left = parseInt(selement.x,10);

//	selement_div.setAttribute('src','data:image/png;base64,'+selement.image);
//	selement_div.setAttribute('src','imgstore.php?iconid='+selement.image);
	selement_div.className = 'pointer sysmap_iconid_'+selement.image;

	selement_div.style.zIndex = '10';
	selement_div.style.position = 'absolute';
	selement_div.style.top = position.top+'px';
	selement_div.style.left = position.left+'px';
	selement_div.style.visibility = 'visible';

	if(!is_null(selement.selected)){
		selement_div.style.border = '1px #3333FF solid';
		selement_div.style.backgroundColor = '#00AAAA';
		selement_div.style.opacity = '.60';
	}

return selement_div;
},

updateIcon: function(){
	this.debug('updateSelementsIcon');

	if(is_null(this.mapimg)){
		setTimeout('ZBX_SYSMAPS['+this.id+'].map.updateSelementsIcon();',500);
	}
	else{
		for(var selementid in this.selements){
			if(empty(this.selements[selementid])) continue;

			this.selements[selementid].html_obj = this.addSelementImage(this.selements[selementid]);
			this.selements[selementid].image = null;
		}
	}
},

removeImage: function(){
	this.debug('remove_selement_img');

	Draggables.unregister(this.html_obj);
	this.html_obj.remove();
},

makeDragable: function(){
	this.debug('makeSelementDragable');

	addListener(selement, 'click', this.sysmap.show_menu.bindAsEventListener(this.sysmap), false);
	addListener(selement, 'mousedown', this.sysmap.activate_menu.bindAsEventListener(this.sysmap), false);

	new Draggable(selement,{
				ghosting: true,
				snap: this.sysmap.get_dragable_dimensions.bind(this.sysmap),
				onEnd: this.sysmap.sysmapUpdate.bind(this.sysmap)
				});

}
});
//]]
