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
		var id = ZBX_SYSMAPS.length;
	}

	if(is_number(sysmapid) && (sysmapid > 100000000000000)){
		throw('Error: Wrong type of arguments passed to function [create_map]');
	}
	

	ZBX_SYSMAPS[id] = new Object;
	ZBX_SYSMAPS[id].map = new Cmap(container,sysmapid,id);
}

var Cmap = Class.create();

Cmap.prototype = {
id:	null,							// own id
sysmapid: null,						// sysmapid
container: null,					// selements and links HTML container (D&D dropable area)
mapimg: null,						// HTML element map img

selementids: new Array(),
selements: {},				// map selements array

linkids: new Array(),
links:	{},				// map links array

selects: new Array(),				// selected Elements

menu_active: 0,						// To recognize D&D
debug_status: 0,					// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: '',						// debug string

mselement: {
	id:				null,			// internal element id
	selementid: 	0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	elementtype:	5,			// 5-UNDEFINED
	elementid: 		0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	iconid_on:		19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_off:		19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_unknown:	19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_maintenance:19,		// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_disabled:19,			// ALWAYS must be a STRING (js doesn't support uint64)
	label:			'New Element',
	label_location:	3,
	x:				0,
	y:				0,
	url:			'',	
	html_obj:		null,			// reference to html obj
	html_objid:		null,			// html elements id
	selected:		0				// element is not selected
},

mlink: {
	id:				null,			// internal link id
	linkid:			0,				// ALWAYS must be a STRING (js doesn't support uint64)
	selementid1:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	selementid2:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	linktriggers:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	tr_desc:		'Select',		// default trigger caption
	drawtype:		0,
	color:			'Green',
	status:			1				// status of link 1 - active, 2 - passive
},

initialize: function(container, sysmapid, id){
	this.debug('initialize');

	this.id = id;
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
		this.get_sysmap_by_sysmapid(this.sysmapid);
	}
	
	Position.includeScrollOffsets = true;
},


// SYSMAP
get_sysmap_by_sysmapid: function(sysmapid){
	this.debug('get_sysmap_by_sysmapid');

	var url = new Curl(location.href);
	
	addListener($('selement_add'), 'click', this.add_empty_selement.bindAsEventListener(this), false);
	addListener($('selement_rmv'), 'click', this.remove_selements.bindAsEventListener(this), false);

	addListener($('link_add'), 'click', this.add_empty_link.bindAsEventListener(this), false);
	addListener($('link_rmv'), 'click', this.remove_links.bindAsEventListener(this), false);

	addListener($('sysmap_save'), 'click', this.save_sysmap.bindAsEventListener(this), false);
//	this.add_mapimg();

	var url = new Curl(location.href);
	var params = {
		'favobj': 	'sysmap',
		'favid':	this.id,
		'sysmapid': this.sysmapid,
		'action':	'get'
	}
			
	new Ajax.Request(url.getPath()+'?output=ajax'+'&sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onSuccess': function(resp){ },
						'onFailure': function(){ throw('Get selements FAILED.'); }
					}
	);
},

dragend_sysmap_update: function(dragable,e){
	this.debug('dragend_sysmap_update');
	
	this.deactivate_menu();
	
	var element = dragable.element;
	var element_id = element.id.split('_');
	var id = element_id[(element_id.length - 1)];
	
	var pos = new Array();
	pos.x = parseInt(element.style.left,10);
	pos.y = parseInt(element.style.top,10);

	this.selements[this.selementids[id]].x = pos.x;
	this.selements[this.selementids[id]].y = pos.y;
	
	this.update_mapimg();
//	alert(id+' : '+this.selementids[id]);
},


// ---------- FORMS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

//ELEMENTS
add_empty_selement: function(){
	this.debug('add_empty_selement');
	
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
	}
	
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
},

// CONNECTORS
add_empty_link: function(){
	this.debug('add_empty_link');

	var id = this.linkids.length;
	
	if((this.selects.length > 1) && !is_null(this.selects[0]) && !is_null(this.selects[1])){			
		var selementid1 = this.selementids[this.selects[0]];
		var selementid2 = this.selementids[this.selects[1]];
	}
	else{
		this.info('Elements are not selected');
		return false;
	}
		
	var mlink = {};
	for(var key in this.mlink){
		mlink[key] = this.mlink[key];
	}
	mlink['selementid1'] = selementid1;
	mlink['selementid2'] = selementid2;

	this.add_link(mlink,1);
},


// SYSMAP FORM
save_sysmap: function(){
	this.debug('save_sysmap');
	
	var url = new Curl(location.href);	
	var params = {
		'favobj': 	'sysmap',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'save'
	}
	
	params = this.get_update_params(params);
//SDJ(params);
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
//------------------------------------------------------------------------

// ---------- ELEMENTS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

select_selement: function(id){
	this.debug('select_selement');
	var selement = this.selements[this.selementids[id]].html_obj;
	
	if((typeof(this.selementids[id]) != 'undefiend') && !is_null(this.selementids[id])){
		var position = null;
		var selementid = this.selementids[id];
		
		if(!is_null(this.selements[selementid].selected)){
			position = this.selements[selementid].selected;

			this.selects[position] = null;
			this.selements[selementid].selected = null;

			for(var i=position; i<1; i++){
				if((typeof(this.selects[i+1]) != 'undefined') && !is_null(this.selects[i+1])){
					this.selects[i] = this.selects[i+1];
					this.selements[this.selementids[this.selects[i]]].selected = i;
				}
			}
			this.selects[1] = null;

			selement.style.border = '0px';
		}
		else{
			for(var i=0; i<2; i++){
				if(is_null(this.selects[i])){
					this.selects[i] = id;
					this.selements[selementid].selected = i;
					break;
				}
			}

			if(is_null(this.selements[selementid].selected)){
				this.selements[this.selementids[this.selects[0]]].html_obj.style.border = '0px';
				this.selements[this.selementids[this.selects[0]]].selected = null;
				
				for(var i=0; i<1; i++){
					this.selects[i] = this.selects[i+1];
					this.selements[this.selementids[this.selects[i]]].selected = i;
				}
				
				this.selects[1] = id;
				this.selements[selementid].selected = 1;
			}
			
			selement.style.border = '1px #FF0000 solid';
		}		
	}
return false;
},

add_selement: function(selement, update_icon){
	this.debug('add_selement');

	var sid = 0;
	if((typeof(selement['selementid']) == 'undefined') || (selement['selementid'] == 0)){
		do{
			sid = parseInt(Math.random(1000000000) * 1000000000);
			sid = sid.toString();
		}while(typeof(this.selements[sid]) != 'undefined');
		
		selement['selementid'] = sid;
	}
	else{
		sid = selement.selementid;
	}
	
	selement.id = this.selementids.length;
	
	if(typeof(this.selements[sid]) == 'undefined'){
		this.selementids.push(sid);
		selement.selected = null;
	}
	else{
		selement.id = this.selements[sid].id;
		selement.selected = this.selements[sid].selected;
	}
	
	if((typeof(update_icon) != 'undefined') && (update_icon != 0)){
		selement.html_obj = this.add_selement_img(selement);
		selement.image = null;
	}

	this.selements[sid] = selement;
},

update_selement_option: function(id, params){ // params = [{'key': key, 'value':value},{'key': key, 'value':value}]
	this.debug('update_selement_option');
	
	if((typeof(this.selementids[id]) != 'undefined') && !is_null(this.selementids[id])){
		for(var i=0; i < params.length; i++){
			if(typeof(params[i]) != 'undefined'){
				var pair = params[i];
//SDI(pair.key+' : '+pair.value);
				this.selements[this.selementids[id]][pair.key] = pair.value;
			}
		}
		
//SDJ(this.selements[this.selementids[id]]);
		this.update_selement(this.selements[this.selementids[id]],1);
	}
},

update_selement: function(selement){
	this.debug('update_selement');

	var url = new Curl(location.href);
	
	var params = {
		'favobj': 	'selements',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'get_img'
	}
	
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
},

remove_selements: function(){
	this.debug('remove_selements');

	if(Confirm('Delete selected elements?')){
		for(var i=0; i<this.selects.length; i++){
			this.remove_selement_by_id(this.selects[i]);
		}

		this.update_mapimg();
	}	
},

remove_selement_by_id: function(id){
	this.debug('remove_selement_by_id');

	if((typeof(this.selementids[id]) != 'undefined') && (!is_null(this.selementids[id]))){
		var selementid = this.selementids[id];
		
// Unselect
		this.selects[this.selements[selementid].selected] = null;
// Remove related links
		this.remove_links_by_selementid(selementid);
// remove icon
		this.remove_selement_img(this.selements[selementid]);
		
//		this.selements[selementid].html_obj.remove();
// remove selement
		this.selements[selementid] = null;
		this.selementids[id] = null;

		delete(this.selements[selementid]);
	}
},

// ---------- CONNECTORS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

get_linkid_by_selementids: function(selementid1,selementid2){
	this.debug('get_linkid_by_selementids');

	var result = false;
	var links = {};
	var linkid = 0;
	for(var i=0; i < this.linkids.length; i++){
		linkid = this.linkids[i];

		if((typeof(this.links[linkid]) != 'undefined') && !is_null(this.links[linkid])){
			if((this.links[linkid].selementid1 == selementid1) && (this.links[linkid].selementid2 == selementid2)){
				links[i] = i;
				result = links;
			}
			else if((this.links[linkid].selementid1 == selementid2) && (this.links[linkid].selementid2 == selementid1)){
				links[i] = i;
				result = links;
			}
		}
	}
	
	return result;
},

add_link: function(mlink, update_map){
	this.debug('add_link');

	var mid = 0;
	if((typeof(mlink['linkid']) == 'undefined') || (mlink['linkid'] == 0)){
		do{
			mid = parseInt(Math.random(1000000000) * 1000000000);
			mid = mid.toString();
		}while(typeof(this.links[mid]) != 'undefined');
		
		mlink['linkid'] = mid;
	}
	else{
		mid = mlink.linkid;
	}

	mlink.id = this.linkids.length;
	mlink.status = 1;
	
	if(typeof(this.links[mid]) == 'undefined'){
		this.linkids.push(mid);
	}
	else{
		mlink.id = this.links[mid].id
	}

	this.links[mid] = mlink;
	
	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.update_mapimg();
	}
},


update_link_option: function(id, params){ // params = [{'key': key, 'value':value},{'key': key, 'value':value},...]
	this.debug('update_link_option');
	
	if((typeof(this.linkids[id]) != 'undefined') && !is_null(this.linkids[id])){
//SDI(key+' : '+value);
		for(var i=0; i < params.length; i++){
			if(typeof(params[i]) != 'undefined'){
				var pair = params[i];
				if(pair.key == 'selementid1'){
					if(this.links[this.linkids[id]]['selementid2'] == pair.value)
					return false;
				}
				
				if(pair.key == 'selementid2'){
					if(this.links[this.linkids[id]]['selementid1'] == pair.value)
					return false;
				}
				
				this.links[this.linkids[id]][pair.key] = pair.value;
			}
		}

		this.update_mapimg();
	}
},

remove_links: function(){
	this.debug('remove_links');

	if((this.selects.length > 1) && !is_null(this.selects[0]) && !is_null(this.selects[1])){			
		var selementid1 = this.selementids[this.selects[0]];
		var selementid2 = this.selementids[this.selects[1]];
		
		var link_ids = this.get_linkid_by_selementids(selementid1,selementid2);
		if(link_ids !== false){
			if(Confirm('Delete Links between selected elements?')){			
				for(var id in link_ids){
					this.remove_link_by_id(id);
				}
				this.update_mapimg();
			}
		}
	}
	else{
		this.info('Elements are not selected');
		return false;
	}	
},

remove_link_by_id: function(id){
	this.debug('remove_link_by_id');

	if((typeof(this.linkids[id]) != 'undefined') && !is_null(this.linkids[id])){
		var linkid = this.linkids[id];
		
		this.linkids[id] = null;
		this.links[linkid] = null;
		delete(this.links[linkid]);
	}
},

remove_links_by_selementid: function(selementid){
	this.debug('remove_links_by_selementid');

	for(var i=0; i < this.linkids.length; i++){
		if((typeof(this.linkids[i]) != 'undefined') && !is_null(this.linkids[i])){
			var linkid = this.linkids[i];
			
			if((this.links[linkid].selementid1 == selementid) || (this.links[linkid].selementid2 == selementid)){
				this.remove_link_by_id(i);
			}
		}
	}
},


// ---------- IMAGES MANIPULATION ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

// ELEMENTS
add_selement_img: function(selement){
	this.debug('add_selement_img');

	var dom_id = 'selement_'+selement.id;

	var selement_div = $(dom_id);
	if(is_null(selement_div)){
//		var selement_div = document.createElement('img');
		var selement_div = document.createElement('div');
		this.container.appendChild(selement_div);
	
		selement_div.setAttribute('id',dom_id);
//		selement_div.setAttribute('alt','selement_'+selement.id);
		selement_div.style.position = 'absolute';
		selement_div.style.visibility = 'hidden';
		
		this.make_selement_dragable(selement_div);
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
		selement_div.style.border = '1px #FF0000 solid';
	}

return selement_div;
},

update_selements_icon: function(){
	this.debug('update_selements_icon');
	
	if(is_null(this.mapimg)){
		setTimeout('ZBX_SYSMAPS['+this.id+'].map.update_selements_icon();',500);
	}
	else{
		for(var i=0; i < this.selementids.length; i++){
			if((typeof(this.selementids[i]) != 'undefined') && !is_null(this.selementids[i])){
				this.selements[this.selementids[i]].html_obj = this.add_selement_img(this.selements[this.selementids[i]]);
				this.selements[this.selementids[i]].image = null;
			}
		}
	}
},

remove_selement_img: function(selement){
	this.debug('remove_selement_img');

	Draggables.unregister(selement.html_obj);
	selement.html_obj.remove();	
},

make_selement_dragable: function(selement){
	this.debug('make_selement_dragable');

//	addListener(selement, 'click', this.select_selement.bindAsEventListener(this), false);
	addListener(selement, 'click', this.show_menu.bindAsEventListener(this), false);
	addListener(selement, 'mousedown', this.activate_menu.bindAsEventListener(this), false);

	new Draggable(selement,{
				ghosting: true,
				snap: this.get_dragable_dimensions.bind(this),
				onEnd: this.dragend_sysmap_update.bind(this)
				});

},

// MAP

update_mapimg: function(){
	this.debug('update_mapimg');

	var url = new Curl(location.href);	
	var params = {
		'output': 'ajax',
		'sysmapid': this.sysmapid,
		'noselements':	1,
		'nolinks':	1
	}

	params = this.get_update_params(params);
//SDJ(params);
	new Ajax.Request('map.php'+'?sid='+url.getArgument('sid'),
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){SDI(resp.responseText);},
						'onSuccess': this.set_mapimg.bind(this),
						'onFailure': function(resp){ alert('failed'); }
					}
	);
},

set_mapimg: function(resp){
	this.debug('set_mapimg');

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

get_dragable_dimensions: function(x,y,draggable){
	this.debug('get_dragable_dimensions');

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
		var params = {};		
	}
	
	params = this.get_selements_params(params);
	params = this.get_links_params(params);
	
return params;
},

get_selements_params: function(params, selementid){
	this.debug('get_selements_params');

	if(typeof(params) == 'undefined'){
		var params = {};		
	}
	
	if(typeof(selementid) != 'undefined'){
		var id = this.selementids[selementid];
		
		if(typeof(this.selements[id]) != 'undefined'){
			params['selements['+id+']'] = Object.toJSON(this.selements[id]);
//			params = this.get_selement_params_by_selement(params, 0, selementid, this.selements[id]);
		}
		
		return params;
	}
	else{
		params['selements'] = Object.toJSON(this.selements);
	}
return params;
},

get_links_params: function(params, linkid){
	this.debug('get_links_params');

	if(typeof(params) == 'undefined'){
		var params = {};		
	}

	if(typeof(linkid) != 'undefined'){
		var id = this.linkids[linkid];
		
		if((typeof(this.links[id]) != 'undefined') && (this.links[id].status == 1)){
			params['links['+id+']'] = Object.toJSON(this.links[id]);
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
// ---------- MENU ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

show_menu: function(e){
	this.debug('show_menu');	
	if(this.menu_active != 1) return true;
	
	var e = e || window.event;
	var element = eventTarget(e);
	var element_id = element.id.split('_');
	var id = element_id[(element_id.length - 1)];

	if(e.ctrlKey){
		this.select_selement(id);
	}
	else{
		if((this.selects.length > 1) && !is_null(this.selects[0]) && !is_null(this.selects[1]) && ((this.selects[0] == id) || (this.selects[1] == id))){	
			this.show_link_menu(e);
		}
		else{
			this.show_selement_menu(e);
		}
	}
	
},

show_selement_menu: function(e){
	this.debug('show_selement_menu');
//	if(!e.ctrlKey) return true;
	
	var element = eventTarget(e);
	var element_id = element.id.split('_');
	var id = element_id[(element_id.length - 1)];
	
	element = this.selements[this.selementids[id]];

	var el_menu = new Array();
	el_menu.push(['Element menu',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

	el_menu.push(['<span onclick="javascript: ZBX_SYSMAPS['+this.id+'].map.select_selement('+id+');">Select</span>',
					'#', 
					function(){return false;},
					{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}
					]);
	
	var elementtypes = ['hostid_hosts','sysmapid_sysmaps','triggerid_triggers','groupid_host_group',];
	
	for(var i=0; i<zbx_selement_menu.length; i++){
		var form_key = zbx_selement_menu[i]['form_key'];
		var caption = zbx_selement_menu[i]['value'];
//SDI(form_field+' : '+caption);
		var sub_menu = new Array(caption,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
		sub_menu.push([caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
		
		var fields = zbx_selement_form_menu[form_key];
//SDI(form_field);
		for(var j=0; j < fields.length; j++){
			if(typeof(fields[j]) != 'undefined'){
				var values = fields[j];
//SDI(element[form_field]+' == '+values['key']);
				if((form_key != 'label') && (form_key != 'url')){
					if((form_key == 'elementtype') && (typeof(elementtypes[values['key']]) != 'undefined')){
						var form_field = elementtypes[values['key']];
				
						var idx = form_field.indexOf('_');
						var srcfld1 = form_field.substring(0,idx);
						var srctbl1 = form_field.substring(idx+1);
						
						var value_action = 'javascript: '+
									"PopUp('popup.php?srctbl="+srctbl1+
										'&reference=sysmap_element'+
										'&sysmapid='+this.sysmapid+
										'&cmapid='+this.id+
										'&sid='+id+
										'&dstfrm=null'+
										'&srcfld1='+srcfld1+
										"&dstfld1=elementid',800,450); void(0);",
						value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';
					}
					else{
						var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_selement_option("+id+",[{'key':'"+form_key+"','value': '"+values['key']+"'}]);";
						value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';
					}
					
					if(element[form_key] == values['key'])
						sub_menu.push([value_action,'#',function(){return false;},{'outer' : ['pum_b_submenu'],'inner' : ['pum_i_submenu']}]);
					else
						sub_menu.push([value_action,'#',function(){return false;}]);
				}
				else{
					values['key'] = element[form_key];

//					var value_action = "javascript: this.disabled=true; ZBX_SYSMAPS["+this.id+"].map.update_selement_option("+id+",'"+form_key+"',this.value);";
					var value_action = "javascript: this.disabled=true; ZBX_SYSMAPS["+this.id+"].map.update_selement_option("+id+",[{'key':'"+form_key+"','value': this.value}]);";
					value_action = '<input type="text" value="'+values['key']+'" onmouseover="javascript: this.focus();" onchange="'+value_action+'" class="biginput"  size="45" />';
					value_action += ' <span class="pointer" onclick="javascript: this.innerHTML=\'Changed\';">Change</span>';
					sub_menu.push([value_action,null,function(){return false;},{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}]);
				}
			}
		}
		
		el_menu.push(sub_menu);
	}
	
	show_popup_menu(e,el_menu,320);// JavaScript Document
},

show_link_menu: function(e){
	this.debug('show_link_menu');
	
	var selementid1 = this.selementids[this.selects[0]];
	var selementid2 = this.selementids[this.selects[1]];

	var link_ids = this.get_linkid_by_selementids(selementid1,selementid2);
	
	if(link_ids === false){
		this.show_selement_menu(e);
		return false;
	}
	var ln_menu = new Array();
	ln_menu.push(['Links menu',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);	
	ln_menu.push(['<span onclick="javascript: ZBX_SYSMAPS['+this.id+'].map.add_empty_link();">Add Link</span>', 
					'#', 
					function(){return false;},
					{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}
					]);

	
	var link_count = 0;
	for(var id in link_ids){
		link_count++;
		var mlink = this.links[this.linkids[id]];
		
		var link_menu = new Array('Link '+link_count,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
		link_menu.push(['Link '+link_count,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
//SDJ(zbx_link_menu);
		for(var i=0; i<zbx_link_menu.length; i++){
			var form_key = zbx_link_menu[i]['form_key'];
			var caption = zbx_link_menu[i]['value'];
	
			var sub_menu = new Array(caption,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
			sub_menu.push([caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
			
			if(form_key == 'triggers'){
				var values = zbx_link_form_menu[form_key][0];
//SDJ(mlink);
				for(var j=0; j<mlink.linktriggers.length; j++){
					if((typeof(mlink.linktriggers[j] != 'undefined')) && !is_null(mlink.linktriggers[j])){
						var srctbl1 = 'triggers';
						var srcfld1 = form_key;

						var linktrigger = mlink.linktriggers[j];

						var value_action = 'javascript: '+
							"PopUp('popup.php?srctbl="+srctbl1+
								'&reference=sysmap_link'+
								'&sysmapid='+this.sysmapid+
								'&cmapid='+this.id+
								'&sid='+id+
								'&dstfrm=null'+
								'&srcfld1='+srcfld1+
								"&dstfld1="+srcfld1+"',800,450); void(0);";

						var desc_exp_trunc = linktrigger.desc_exp.substr(0, 40)+'...';
						value_action = '<span onclick="'+value_action+'">'+desc_exp_trunc+'</span>';

						sub_menu.push([value_action,'#',function(){return false;}]);
					}
				}
			}
			else if((form_key == 'selementid1') || (form_key == 'selementid2')){
				for(var j=0; j<this.selementids.length; j++){
					if((typeof(this.selementids[j] != 'undefined')) && !is_null(this.selementids[j])){
						var selement = this.selements[this.selementids[j]];
						
						var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_link_option("+id+",[{'key':'"+form_key+"','value':'"+selement['selementid']+"'}]);";
						value_action = '<span onclick="'+value_action+'">'+selement['label']+'</span>';
						
						if(mlink[form_key] == selement['selementid'])
							sub_menu.push([value_action,'#',null,{'outer' : ['pum_b_submenu'],'inner' : ['pum_i_submenu']}]);
						else
							sub_menu.push([value_action,'#',function(){return false;}]);
					}
				}
			}
			else{
				var fields = zbx_link_form_menu[form_key];
	
				for(var j=0; j < fields.length; j++){
					if(typeof(fields[j]) != 'undefined'){
						var values = fields[j];
						var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_link_option("+id+",[{'key':'"+form_key+"','value':'"+values['key']+"'}]);";
						value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';
	
						if(mlink[form_key] == values['key'])
							sub_menu.push([value_action,'#',null,{'outer' : ['pum_b_submenu'],'inner' : ['pum_i_submenu']}]);
						else
							sub_menu.push([value_action,'#',function(){return false;}]);
					}
				}
			}
			link_menu.push(sub_menu);
		}

		link_menu.push(['<span onclick="javascript: ZBX_SYSMAPS['+this.id+'].map.remove_link_by_id('+id+'); ZBX_SYSMAPS['+this.id+'].map.update_mapimg(); ">Remove Link</span>', 
					'#', 
					function(){return false;},
					{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}
					]);
		ln_menu.push(link_menu);
	}
	show_popup_menu(e,ln_menu,280);// JavaScript Document
},
// ---------- DEBUG ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------
debug: function(str){
	if(this.debug_status){
		this.debug_info += str + '\n';
		
		if(this.debug_status == 2){
			SDI(str);
		}
	}
	
},

info: function(msg){
	msg = msg || 'Map selement failed.'
	alert(msg);
},

error: function(msg){
	msg = msg || 'Map selement failed.'
	throw(msg);
}
}
//]]