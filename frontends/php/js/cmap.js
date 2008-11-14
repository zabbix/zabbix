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
container: null,					// elements and links HTML container (D&D dropable area)
mapimg: null,						// HTML element map img

elementids: new Array(),
elements: new Array(),				// map elements array

linkids: new Array(),
links:	new Array(),				// map links array

selects: new Array(),				// selected Elements

menu_active: 0,						// To recognize D&D
debug_status: 0,					// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: '',						// debug string

melement: {
	id:				null,			// internal element id
	selementid: 	0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	elementtype:	4,			// UNDEFINED
	elementid: 		0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	iconid_on:		19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_off:		19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_unknown:	19,			// ALWAYS must be a STRING (js doesn't support uint64)
	iconid_maintenance:19,		// ALWAYS must be a STRING (js doesn't support uint64)
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
	triggerid:		0,				// ALWAYS must be a STRING (js doesn't support uint64)
	drawtype_off:	0,
	color_off:		'Green',
	drawtype_on:	0,
	color_on:		'Red',
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
	
	addListener($('element_add'), 'click', this.add_empty_element.bindAsEventListener(this), false);
	addListener($('element_rmv'), 'click', this.remove_elements.bindAsEventListener(this), false);

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
			
	new Ajax.Request(url.getPath()+'?output=ajax',
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){ SDI(resp.responseText); },
						'onSuccess': function(resp){ },
						'onFailure': function(){ throw('Get elements FAILED.'); }
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

	this.elements[this.elementids[id]].x = pos.x;
	this.elements[this.elementids[id]].y = pos.y;
	
	this.update_mapimg();
//	alert(id+' : '+this.elementids[id]);
},


// ---------- FORMS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

//ELEMENTS
add_empty_element: function(){
	this.debug('add_empty_element');
	
	var element = this.melement;
	
	var element = new Array();
	for(var key in this.melement){
		element[key] = this.melement[key];
	}
	
	var url = new Curl(location.href);
	
	var params = {
		'favobj': 	'elements',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'get_img'
	}
	
	params = this.get_element_params_by_element(params, 0, 0, element);
			
	new Ajax.Request(url.getPath()+'?output=ajax',
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },
//						'onSuccess': function(resp){ alert(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},

// CONNECTORS
add_empty_link: function(){
	this.debug('add_empty_link');

	var id = this.linkids.length;
	
	if((this.selects.length > 1) && !is_null(this.selects[0]) && !is_null(this.selects[1])){			
		var selementid1 = this.elementids[this.selects[0]];
		var selementid2 = this.elementids[this.selects[1]];
	}
	else{
		this.info('Elements are not selected');
		return false;
	}
	
	var id = this.get_linkid_by_elementids(selementid1,selementid2);
	
	if(id !== false){
		return false;		
	}
	
	var mlink = new Array();
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
	new Ajax.Request(url.getPath()+'?output=ajax',
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

select_element: function(id){
	this.debug('select_element');
	var element = this.elements[this.elementids[id]].html_obj;
	
	if((typeof(this.elementids[id]) != 'undefiend') && !is_null(this.elementids[id])){
		var position = null;
		var elementid = this.elementids[id];
		
		if(!is_null(this.elements[elementid].selected)){
			position = this.elements[elementid].selected;

			this.selects[position] = null;
			this.elements[elementid].selected = null;

			for(var i=position; i<1; i++){
				if((typeof(this.selects[i+1]) != 'undefined') && !is_null(this.selects[i+1])){
					this.selects[i] = this.selects[i+1];
					this.elements[this.elementids[this.selects[i]]].selected = i;
				}
			}
			this.selects[1] = null;

			element.style.border = '0px';
		}
		else{
			for(var i=0; i<2; i++){
				if(is_null(this.selects[i])){
					this.selects[i] = id;
					this.elements[elementid].selected = i;
					break;
				}
			}

			if(is_null(this.elements[elementid].selected)){
				this.elements[this.elementids[this.selects[0]]].html_obj.style.border = '0px';
				this.elements[this.elementids[this.selects[0]]].selected = null;
				
				for(var i=0; i<1; i++){
					this.selects[i] = this.selects[i+1];
					this.elements[this.elementids[this.selects[i]]].selected = i;
				}
				
				this.selects[1] = id;
				this.elements[elementid].selected = 1;
			}
			
			element.style.border = '1px #FF0000 solid';
		}		
	}
return false;
},

add_element: function(selement, update_icon){
	this.debug('add_element');

	var sid = 0;
	if((typeof(selement['selementid']) == 'undefined') || (selement['selementid'] == 0)){
		do{
			sid = parseInt(Math.random(1000000000) * 1000000000);
			sid = sid.toString();
		}while(typeof(this.elements[sid]) != 'undefined');
		
		selement['selementid'] = sid;
	}
	else{
		sid = selement.selementid;
	}
	
	selement.id = this.elementids.length;
	
	if(typeof(this.elements[sid]) == 'undefined'){
		this.elementids.push(sid);
		selement.selected = null;
	}
	else{
		selement.id = this.elements[sid].id;
		selement.selected = this.elements[sid].selected;
	}
	
	if((typeof(update_icon) != 'undefined') && (update_icon != 0)){
		selement.html_obj = this.add_element_img(selement);
		selement.image = null;
	}
	
	this.elements[sid] = selement;
},

update_element_option: function(id, params){ // params = [{'key': key, 'value':value},{'key': key, 'value':value}]
	this.debug('update_element_option');
	
	if((typeof(this.elementids[id]) != 'undefined') && !is_null(this.elementids[id])){
		for(var i=0; i < params.length; i++){
			if(typeof(params[i]) != 'undefined'){
				var pair = params[i];
//SDI(pair.key+' : '+pair.value);
				this.elements[this.elementids[id]][pair.key] = pair.value;
			}
		}
		
//SDJ(this.elements[this.elementids[id]]);
		this.update_element(this.elements[this.elementids[id]],1);
	}
},

update_element: function(selement){
	this.debug('update_element');

	var url = new Curl(location.href);
	
	var params = {
		'favobj': 	'elements',
		'favid':	this.id,
		'sysmapid':	this.sysmapid,
		'action':	'get_img'
	}
	
	params = this.get_element_params_by_element(params, 0, 0, selement);
			
	new Ajax.Request(url.getPath()+'?output=ajax',
					{
						'method': 'post',
						'parameters':params,
						'onSuccess': function(resp){ },
//						'onSuccess': function(resp){ alert(resp.responseText); },
						'onFailure': function(){ document.location = url.getPath()+'?'+Object.toQueryString(params); }
					}
	);
},

remove_elements: function(){
	this.debug('remove_elements');

	if(Confirm('Delete selected elements?')){
		for(var i=0; i<this.selects.length; i++){
			this.remove_element_by_id(this.selects[i]);
		}

		this.update_mapimg();
	}	
},

remove_element_by_id: function(id){
	this.debug('remove_element_by_id');

	if((typeof(this.elementids[id]) != 'undefined') && (!is_null(this.elementids[id]))){
		var elementid = this.elementids[id];
		
// Unselect
		this.selects[this.elements[elementid].selected] = null;
// Remove related links
		this.remove_links_by_elementid(elementid);
// remove icon
		this.remove_element_img(this.elements[elementid]);
		
//		this.elements[elementid].html_obj.remove();		
// remove element
		this.elements[elementid] = null;
		this.elementids[id] = null;
	}
},

// ---------- CONNECTORS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

get_linkid_by_elementids: function(selementid1,selementid2){
	this.debug('get_linkid_by_elementids');
	var linkid = 0;
	for(var i=0; i < this.linkids.length; i++){
		linkid = this.linkids[i];

		if((typeof(this.links[linkid]) != 'undefined') && !is_null(this.links[linkid])){
			if((this.links[linkid].selementid1 == selementid1) && (this.links[linkid].selementid2 == selementid2)){
				return i;
			}
			else if((this.links[linkid].selementid1 == selementid2) && (this.links[linkid].selementid2 == selementid1)){
				return i;
			}
		}
	}
return false;
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
		var selementid1 = this.elementids[this.selects[0]];
		var selementid2 = this.elementids[this.selects[1]];
		
		var id = this.get_linkid_by_elementids(selementid1,selementid2);
		if(id !== false){
			if(Confirm('Delete Link between selected elements?')){
				this.remove_link_by_id(id);
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
	}
},

remove_links_by_elementid: function(elementid){
	this.debug('remove_links_by_elementid');

	for(var i=0; i < this.linkids.length; i++){
		if((typeof(this.linkids[i]) != 'undefined') && !is_null(this.linkids[i])){
			var linkid = this.linkids[i];
			
			if((this.links[linkid].selementid1 == elementid) || (this.links[linkid].selementid2 == elementid)){
				this.remove_link_by_id(i);
			}
		}
	}
},


// ---------- IMAGES MANIPULATION ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

// ELEMENTS
add_element_img: function(selement){
	this.debug('add_element_img');

	var dom_id = 'element_'+selement.id;

	var element_div = $(dom_id);
	if(is_null(element_div)){
//		var element_div = document.createElement('img');
		var element_div = document.createElement('div');
		this.container.appendChild(element_div);
	
		element_div.setAttribute('id',dom_id);
//		element_div.setAttribute('alt','element_'+selement.id);
		element_div.style.position = 'absolute';
		element_div.style.visibility = 'hidden';
		
		this.make_element_dragable(element_div);
	}
		
	var position = {};	
	position.top = parseInt(selement.y,10);
	position.left = parseInt(selement.x,10);

//	element_div.setAttribute('src','data:image/png;base64,'+selement.image);
//	element_div.setAttribute('src','imgstore.php?iconid='+selement.image);
	element_div.className = 'pointer sysmap_iconid_'+selement.image;
	
	element_div.style.zIndex = '10';
	element_div.style.position = 'absolute';
	element_div.style.top = position.top+'px';
	element_div.style.left = position.left+'px';
	element_div.style.visibility = 'visible';
	
	if(!is_null(selement.selected)){
		element_div.style.border = '1px #FF0000 solid';
	}

return element_div;
},

update_elements_icon: function(){
	this.debug('update_elements_icon');
	
	if(is_null(this.mapimg)){
		setTimeout('ZBX_SYSMAPS['+this.id+'].map.update_elements_icon();',500);
	}
	else{
		for(var i=0; i < this.elementids.length; i++){
			if((typeof(this.elementids[i]) != 'undeifned') && !is_null(this.elementids[i])){
				this.elements[this.elementids[i]].html_obj = this.add_element_img(this.elements[this.elementids[i]]);
				this.elements[this.elementids[i]].image = null;
			}
		}
	}
},

remove_element_img: function(selement){
	this.debug('remove_element_img');

	Draggables.unregister(selement.html_obj);
	selement.html_obj.remove();	
},

make_element_dragable: function(element){
	this.debug('make_element_dragable');

//	addListener(element, 'click', this.select_element.bindAsEventListener(this), false);
	addListener(element, 'click', this.show_menu.bindAsEventListener(this), false);
	addListener(element, 'mousedown', this.activate_menu.bindAsEventListener(this), false);

	new Draggable(element,{
				ghosting: true,
				snap: this.get_dragable_dimensions.bind(this),
				onEnd: this.dragend_sysmap_update.bind(this)
				});

},

// MAP

update_mapimg: function(){
	this.debug('update_mapimg');

	var url = 'map.php';	
	var params = {
		'output': 'ajax',
		'sysmapid': this.sysmapid,
		'noelements':	1,
		'nolinks':	1
	}

	params = this.get_update_params(params);
//SDJ(params);
	new Ajax.Request(url,
					{
						'method': 'post',
						'parameters':params,
//						'onSuccess': function(resp){alert(resp.responseText);},
						'onSuccess': this.set_mapimg.bind(this),
						'onFailure': function(resp){ alert('failed'); }
					}
	);
},

set_mapimg: function(resp){
	this.debug('set_mapimg');

//alert(resp.responseText);
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
	
	params = this.get_elements_params(params);
	params = this.get_links_params(params);
	
return params;
},

get_elements_params: function(params, elementid){
	this.debug('get_elements_params');

	if(typeof(params) == 'undefined'){
		var params = {};		
	}
	
	if(typeof(elementid) != 'undefined'){
		var id = this.elementids[elementid];
		
		if(typeof(this.elements[id]) != 'undefined'){
			params = this.get_element_params_by_element(params, 0, elementid, this.elements[id]);
		}
		
		return params;
	}
	else{
		for(var i=0; i<this.elementids.length; i++){
			var id = this.elementids[i];
			if(typeof(this.elements[id]) != 'undefined'){
				params = this.get_element_params_by_element(params, id, i, this.elements[id]);
			}
		}
	}
return params;
},

get_element_params_by_element: function(params, count, id, element){
	this.debug('get_element_params_by_element');
	
	params['elements['+count+'][obj_id]']			=	id;
	params['elements['+count+'][selementid]']		=	element.selementid;
	params['elements['+count+'][elementid]']		=	element.elementid;
	params['elements['+count+'][elementtype]']		=	element.elementtype;
	params['elements['+count+'][label]']			=	element.label;
	params['elements['+count+'][label_location]']	=	element.label_location;
	params['elements['+count+'][iconid_on]'] 		=	element.iconid_on;
	params['elements['+count+'][iconid_off]']		= 	element.iconid_off;
	params['elements['+count+'][iconid_unknown]']	= 	element.iconid_unknown;
	params['elements['+count+'][iconid_maintenance]']=	element.iconid_maintenance;
	params['elements['+count+'][url]']				=	element.url;
	params['elements['+count+'][x]']				=	element.x;
	params['elements['+count+'][y]']				=	element.y;
	
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
			params = this.get_link_params_by_link(params, 0, linkid, this.links[id]);			
		}
		
		return params;
	}
	else{
		for(var i=0; i<this.linkids.length; i++){
			var id = this.linkids[i];

			if((typeof(this.links[id]) != 'undefined') && (this.links[id].status == 1)){
				params = this.get_link_params_by_link(params, id, i, this.links[id]);				
			}
		}		
	}
return params;
},

get_link_params_by_link: function(params, count, id, mlink){
	this.debug('get_link_params_by_link');

	params['links['+count+'][obj_id]']		=	id;
	params['links['+count+'][linkid]']		=	mlink.linkid;
	params['links['+count+'][selementid1]']	=	mlink.selementid1;
	params['links['+count+'][selementid2]']	=	mlink.selementid2;
	params['links['+count+'][triggerid]']	=	mlink.triggerid;
	params['links['+count+'][drawtype_off]']= 	mlink.drawtype_off;
	params['links['+count+'][color_off]']	=	mlink.color_off;
	params['links['+count+'][drawtype_on]']	=	mlink.drawtype_on;
	params['links['+count+'][color_on]']	= 	mlink.color_on;	
	
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
		this.select_element(id);
	}
	else{
		if((this.selects.length > 1) && !is_null(this.selects[0]) && !is_null(this.selects[1]) && ((this.selects[0] == id) || (this.selects[1] == id))){	
			this.show_link_menu(e);
		}
		else{
			this.show_element_menu(e);
		}
	}
	
},

show_element_menu: function(e){
	this.debug('show_element_menu');
//	if(!e.ctrlKey) return true;
	
	var element = eventTarget(e);
	var element_id = element.id.split('_');
	var id = element_id[(element_id.length - 1)];
	
	element = this.elements[this.elementids[id]];

	var el_menu = new Array();
	el_menu.push(['Element menu',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

	el_menu.push(['<span onclick="javascript: ZBX_SYSMAPS['+this.id+'].map.select_element('+id+');">Select</span>', 
					'#', 
					function(){return false;},
					{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}
					]);
	
	var elementtypes = ['hostid_hosts','sysmapid_sysmaps','triggerid_triggers','groupid_host_group'];
	
	for(var i=0; i<zbx_element_menu.length; i++){
		var form_key = zbx_element_menu[i]['form_key'];
		var caption = zbx_element_menu[i]['value'];
//SDI(form_field+' : '+caption);
		var sub_menu = new Array(caption,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
		sub_menu.push([caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
		
		var fields = zbx_element_form_menu[form_key];
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
						var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_element_option("+id+",[{'key':'"+form_key+"','value': '"+values['key']+"'}]);";
						value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';
					}
					
					if(element[form_key] == values['key'])
						sub_menu.push([value_action,'#',function(){return false;},{'outer' : ['pum_b_submenu'],'inner' : ['pum_i_submenu']}]);
					else
						sub_menu.push([value_action,'#',function(){return false;}]);
				}
				else{
					values['key'] = element[form_key];

//					var value_action = "javascript: this.disabled=true; ZBX_SYSMAPS["+this.id+"].map.update_element_option("+id+",'"+form_key+"',this.value);";
					var value_action = "javascript: this.disabled=true; ZBX_SYSMAPS["+this.id+"].map.update_element_option("+id+",[{'key':'"+form_key+"','value': this.value}]);";
					value_action = '<input type="text" value="'+values['key']+'" onmouseover="javascript: this.focus();" onchange="'+value_action+'" class="biginput" />';
					value_action += ' <span class="pointer" onclick="javascript: this.innerHTML=\'Changed\';">Change</span>';
					sub_menu.push([value_action,null,function(){return false;},{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}]);
				}
			}
		}
		
		el_menu.push(sub_menu);
	}
	
	show_popup_menu(e,el_menu,280);// JavaScript Document
},

show_link_menu: function(e){
	this.debug('show_link_menu');
	
	var selementid1 = this.elementids[this.selects[0]];
	var selementid2 = this.elementids[this.selects[1]];

	var id = this.get_linkid_by_elementids(selementid1,selementid2);
	
	if(id === false){
		this.show_element_menu(e);
		return false;
	}
	
	var mlink = this.links[this.linkids[id]];

	var ln_menu = new Array();
	ln_menu.push(['Link menu',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
	
	for(var i=0; i<zbx_link_menu.length; i++){
		var form_key = zbx_link_menu[i]['form_key'];
		var caption = zbx_link_menu[i]['value'];

		var sub_menu = new Array(caption,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
		sub_menu.push([caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
		
		if(form_key == 'triggerid'){
			var values = zbx_link_form_menu[form_key][0];
			
			var srctbl1 = 'triggers';
			var srcfld1 = form_key;

			var value_action = 'javascript: '+
						"PopUp('popup.php?srctbl="+srctbl1+
							'&reference=sysmap_link'+
							'&sysmapid='+this.sysmapid+
							'&cmapid='+this.id+
							'&sid='+id+
							'&dstfrm=null'+
							'&srcfld1='+srcfld1+
							"&dstfld1="+srcfld1+"',800,450); void(0);",
			value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';					
			sub_menu.push([value_action,'#',function(){return false;}]);
		}
		else if((form_key == 'selementid1') || (form_key == 'selementid2')){
			for(var j=0; j<this.elementids.length; j++){
				if((typeof(this.elementids[j] != 'undefined')) && !is_null(this.elementids[j])){
					var element = this.elements[this.elementids[j]];
					
					var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_link_option("+id+",[{'key':'"+form_key+"','value':'"+element['selementid']+"'}]);";
					value_action = '<span onclick="'+value_action+'">'+element['label']+'</span>';
					
					if(mlink[form_key] == element['selementid'])
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

		
		ln_menu.push(sub_menu);
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
	msg = msg || 'Map element failed.'
	alert(msg);
},

error: function(msg){
	msg = msg || 'Map element failed.'
	throw(msg);
}
}
//]]