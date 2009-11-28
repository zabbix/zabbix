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

selements: {},						// map selements array
links:	{},							// map links array

selection: {
	count: 0,						// numer of selected elements
	position: 0,					// elements numerate
	selements: new Array()			// selected SElements
},

menu_active: 0,						// To recognize D&D
debug_status: 0,					// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: '',						// debug string

mselement: {
	selementid: 	0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	elementtype:	4,			// 5-UNDEFINED
	elementid: 		0,			// ALWAYS must be a STRING (js doesn't support uint64) 
	elementName:	'',			// element name
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
	linkid:			0,				// ALWAYS must be a STRING (js doesn't support uint64)
	selementid1:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	selementid2:	0,				// ALWAYS must be a STRING (js doesn't support uint64)
	linktriggers:	{},				// ALWAYS must be a STRING (js doesn't support uint64)
	tr_desc:		'Select',		// default trigger caption
	drawtype:		0,
	color:			'0000CC',
	status:			1				// status of link 1 - active, 2 - passive
},


mlinktrigger: {
	linktriggerid:	0,					// ALWAYS must be a STRING (js doesn't support uint64)
	triggerid:		0,					// ALWAYS must be a STRING (js doesn't support uint64)
	desc_exp:		'Set Trigger',		// default trigger caption
	drawtype:		0,
	color:			'CC0000'
},

selementForm:		{},					// container for Selement form dom objects
linkForm:			{},					// container for link form dom objects

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
	var selementid = element_id[(element_id.length - 1)];
	
	var pos = new Array();
	pos.x = parseInt(element.style.left,10);
	pos.y = parseInt(element.style.top,10);

	this.selements[selementid].x = pos.x;
	this.selements[selementid].y = pos.y;
	
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
		this.info('Two elements should be selected');
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


add_empty_linktrigger: function(linkid){
	this.debug('add_empty_link');

	var id = this.linkids[linkid];

	var mlinktrigger = {};
	for(var key in this.mlinktrigger){
		mlinktrigger[key] = this.mlinktrigger[key];
	}


	this.add_linktrigger(id, mlinktrigger, 1);
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

			selement.html_obj.style.border = '1px #FF0000 solid';

			this.selection.count++;
			this.selection.position++;
		}
		else if((this.selection.count > 1) && !multi){
// if selected several eements and then we clicked on one of them
		}
		else{
			this.selection.count--;
			position = selement.selected;
			
			this.selection.selements[position] = null;
			delete(this.selection.selements[position]);

			this.selements[selementid].selected = null;

			selement.html_obj.style.border = '0px';
		}

		if(!multi && (this.selection.count > 1)){
			for(var i=0; i<this.selection.position; i++){
				if(!isset(i,this.selection.selements) || (this.selection.selements[i] == selementid)) continue;;

				this.selection.count--;

				var tmp_selementid = this.selection.selements[i];

				this.selements[this.selection.selements[i]].selected = null;
				this.selements[this.selection.selements[i]].html_obj.style.border = '0px';

				this.selection.selements[i] = null;
				delete(this.selection.selements[i]);
			}
		}
	}

return false;
},

add_selement: function(selement, update_icon){
	this.debug('add_selement');

	var selementid = 0;
	if((typeof(selement['selementid']) == 'undefined') || (selement['selementid'] == 0)){
		do{
			selementid = parseInt(Math.random(1000000000) * 1000000000);
			selementid = selementid.toString();
		}while(isset(selementid, this.selements));
		
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
		selement.html_obj = this.add_selement_img(selement);
		selement.image = null;
	}

	this.selements[selementid] = selement;
},

update_selement_option: function(selementid, params){ // params = {'key': value, 'key': value}
	this.debug('update_selement_option');
//--
	if(!isset(selementid, this.selements) || empty(this.selements[selementid])) return false;

	for(var key in params){
		if(!isset(key, params) || is_null(params[key])) continue;
		this.selements[selementid][key] = params[key].toString();
//SDI(key+' : '+params[key]);
	}

	this.update_selement(this.selements[selementid]);
},

update_selement: function(selement){
	this.debug('update_selement');
//--
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
		for(var i=0; i<this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;

			this.remove_selement(this.selection.selements[i]);
		}

		this.update_mapimg();
	}	
},

remove_selement: function(selementid, update_map){
	this.debug('remove_selement');

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
		this.update_mapimg();
	}
},

// ---------- CONNECTORS ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

get_linkid_by_selementids: function(selementid1,selementid2){
	this.debug('get_linkid_by_selementids');
//--
	if(typeof(selementid2) == 'undefined') var selementid2 = null;
	
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
		
		mlink['linkid'] = linkid;
	}
	else{
		linkid = mlink.linkid;
	}

	mlink.status = 1;
	this.links[linkid] = mlink;
	
	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.update_mapimg();
	}
},


update_link_option: function(linkid, params){ // params = [{'key': key, 'value':value},{'key': key, 'value':value},...]
	this.debug('update_link_option');
	
	if(!isset(linkid, this.links) || empty(this.links[linkid])) return false;
//SDI(key+' : '+value);
	for(var key in params){
		if(empty(params[key])) continue;
		
		if(key == 'selementid1'){
			if(this.links[linkid]['selementid2'] == params[key])
			return false;
		}

		if(key == 'selementid2'){
			if(this.links[linkid]['selementid1'] == params[key])
			return false;
		}

		this.links[linkid][key] = params[key];
//SDI(key+' : '+value);
	}

	this.update_mapimg();
},

remove_links: function(){
	this.debug('remove_links');

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
		this.info('Please select two elements');
		return false;
	}
	
	var linkids = this.get_linkid_by_selementids(selementid1,selementid2);

	if(linkids !== false){
		if(Confirm('Delete Links between selected elements?')){			
			for(var linkid in linkids){
				this.remove_link(linkid);
			}
			this.update_mapimg();
		}
	}
},

remove_link: function(linkid){
	this.debug('remove_link');

	if(!isset(linkid, this.links) || empty(this.links[linkid])) return false;

	this.links[linkid] = null;
	delete(this.links[linkid]);
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
//SDJ(linktrigger);

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;

	var linktriggerid = 0;
	if(!isset('linktriggerid',linktrigger) || (linktrigger['linktriggerid'] == 0)){
		do{
			linktriggerid = parseInt(Math.random(1000000000) * 1000000000);
			linktriggerid = linktriggerid.toString();
		}while(typeof(this.links[linkid].linktriggers[linktriggerid]) != 'undefined');

		linktrigger['linktriggerid'] = linktriggerid;
	}
	else{
		linktriggerid = linktrigger.linktriggerid;
	}

	this.links[linkid].linktriggers[linktriggerid] = linktrigger;

	if((typeof(update_map) != 'undefined') && (update_map == 1)){
		this.update_mapimg();
	}
},

update_linktrigger_option: function(linkid, linktriggerid, params){
this.debug('update_linktrigger_option');

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;

//SDI(key+' : '+value);
	for(var i=0; i < params.length; i++){
		if(typeof(params[i]) != 'undefined'){
			var pair = params[i];

			if(isset(linktriggerid, this.links[linkid].linktriggers) && !empty(this.links[linkid].linktriggers[linktriggerid])){
				this.links[linkid].linktriggers[linktriggerid][pair.key] = pair.value;
			}
		}
	}

	this.update_mapimg();
},

remove_linktrigger: function(linkid, linktriggerid){
this.debug('remove_linktrigger');

	if(!isset(linkid,this.links) || empty(this.links[linkid])) return false;
	if(!isset(linktriggerid, this.links[linkid].linktriggers) || empty(this.links[linkid].linktriggers[linktriggerid])) return false;
//SDI(key+' : '+value);
	this.links[linkid].linktriggers[linktriggerid] = null;
	delete(this.links[linkid].linktriggers[linktriggerid]);

	this.update_mapimg();
},
// ---------- IMAGES MANIPULATION ------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------

// ELEMENTS
add_selement_img: function(selement){
	this.debug('add_selement_img');

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
		for(var selementid in this.selements){
			if(!empty(this.selements[selementid])){
				this.selements[selementid].html_obj = this.add_selement_img(this.selements[selementid]);
				this.selements[selementid].image = null;
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
		var params = {};		
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
	var element = eventTarget(e);
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
		divForm.style.backgroundColor = '#FAFAFA';
		divForm.style.zIndex = 100;
		divForm.style.position = 'absolute';
		divForm.style.top = '50px';
		divForm.style.left = '500px';
		


		divForm.style.border = '1px red solid';
	}



	this.updateForm_selement(e, selementid);
	this.update_multiContainer(e);
	this.update_linkContainer(e);


	if(is_null($('linkForm'))){
		this.createForm_link(e);
//		divForm.appendChild(this.linkForm.form);
	}
//	this.updateForm_link(e, selementid);


	new Draggable(divForm,{'handle': this.selementForm.dragHandler});	
	$(divForm).show();
},

hideForm: function(e){
	this.debug('hideForm');
//--

	var divForm = $('divSelementForm');
	if(!is_null(divForm)) divForm.hide();
	
	for(var i=0; i<this.selection.position; i++){
		if(!isset(i,this.selection.selements)) continue;;

		this.selection.count--;

		this.selements[this.selection.selements[i]].selected = null;
		this.selements[this.selection.selements[i]].html_obj.style.border = '0px';

		this.selection.selements[i] = null;
		delete(this.selection.selements[i]);
	}
},


//  Multi Container  ------------------------------------------------------------------------------------
// ------------------------------------------------------------------------------------------------------

create_multiContainer: function(e, selementid){
	this.debug('create_multiContainer');
//--

// var initialization 
	this.multiContainer = {};


// Down Stream
/*
	var e_table_1 = document.createElement('table');
this.multiContainer.containerHeader = e_table_1;
	e_table_1.setAttribute('id',"multiContainer");
	e_table_1.setAttribute('cellSpacing',"0");
	e_table_1.setAttribute('cellPadding',"0");
	e_table_1.style.width = "100%";


	var e_tbody_2 = document.createElement('tbody');
	e_table_1.appendChild(e_tbody_2);


	var e_tr_3 = document.createElement('tr');
	e_tbody_2.appendChild(e_tr_3);


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);
*/	
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
		this.create_multiContainer(e);
		$('divSelementForm').appendChild(this.multiContainer.container);
//		$('divSelementForm').appendChild(document.createElement('br'));
	}
//---

// HEADER
	var e_table_1 = document.createElement('table');
	e_table_1.setAttribute('cellspacing',"0");
	e_table_1.setAttribute('cellpadding',"1");
	e_table_1.setAttribute('class',"header");


	var e_tbody_2 = document.createElement('tbody');
	e_table_1.appendChild(e_tbody_2);


	var e_tr_3 = document.createElement('tr');
	e_tbody_2.appendChild(e_tr_3);


	var e_td_4 = document.createElement('td');
	e_td_4.setAttribute('class',"header_l");
	e_td_4.appendChild(document.createTextNode('Map Elements'));
	e_tr_3.appendChild(e_td_4);

	
	var e_td_4 = document.createElement('td');
	e_td_4.setAttribute('align',"right");
	e_td_4.setAttribute('class',"header_r");
	
	e_tr_3.appendChild(e_td_4);
	
	$(this.multiContainer.container).update(e_table_1);
//-----------	

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
	e_td_4.appendChild(document.createTextNode('Label'));


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);	
	e_td_4.appendChild(document.createTextNode('Type'));


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);
	e_td_4.appendChild(document.createTextNode('Description'));


	var count = 0;
	var selement = null;
	for(var i=0; i<this.selection.position; i++){
		if(!isset(i, this.selection.selements)) continue;
		if(!isset(this.selection.selements[i], this.selements)) continue;
		
		count++;
		selement = this.selements[this.selection.selements[i]];
		
		if(count > 4) this.multiContainer.container.style.height = '130px';
		else this.multiContainer.container.style.height = 'auto';

		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "even_row";
		e_tbody_2.appendChild(e_tr_3);
		
	
		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
	
	
		var e_span_5 = document.createElement('span');
//		e_span_5.setAttribute('href',"sysmap.php?sysmapid=100100000000002&amp;form=update&amp;selementid=100100000000004&amp;sid=791bd54e24454e2b");
//		e_span_5.className = "link";
		e_td_4.appendChild(e_span_5);
		
		e_span_5.appendChild(document.createTextNode(selement.label));

		var elementtypeText = '';
		switch(selement.elementtype){
			case '0': elementtypeText = 'Host'; break;
			case '1': elementtypeText = 'Map'; break;
			case '2': elementtypeText = 'Trigger'; break;
			case '3': elementtypeText = 'Group'; break;
			case '4':
			default: elementtypeText = 'Image'; break;
		}

		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(elementtypeText));
		e_tr_3.appendChild(e_td_4);		
	
		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(selement.elementName));
		e_tr_3.appendChild(e_td_4);	
	}


	this.multiContainer.container.appendChild(e_table_1);
},

// LINK CONTAINER
//**************************************************************************************************************************************************
create_linkContainer: function(e, selementid){
	this.debug('create_multiContainer');
//--

// var initialization 
	this.linkContainer = {};


// Down Stream
/*
	var e_table_1 = document.createElement('table');
this.linkContainer.containerHeader = e_table_1;
	e_table_1.setAttribute('id',"linkContainer");
	e_table_1.setAttribute('cellSpacing',"0");
	e_table_1.setAttribute('cellPadding',"0");
	e_table_1.style.width = "100%";


	var e_tbody_2 = document.createElement('tbody');
	e_table_1.appendChild(e_tbody_2);


	var e_tr_3 = document.createElement('tr');
	e_tbody_2.appendChild(e_tr_3);


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);
*/	
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
		this.create_linkContainer(e);
		$('divSelementForm').appendChild(this.linkContainer.container);
//		$('divSelementForm').appendChild(document.createElement('br'));
	}
//---

// HEADER
	var e_table_1 = document.createElement('table');
	e_table_1.setAttribute('cellspacing',"0");
	e_table_1.setAttribute('cellpadding',"1");
	e_table_1.setAttribute('class',"header");


	var e_tbody_2 = document.createElement('tbody');
	e_table_1.appendChild(e_tbody_2);


	var e_tr_3 = document.createElement('tr');
	e_tbody_2.appendChild(e_tr_3);


	var e_td_4 = document.createElement('td');
	e_td_4.setAttribute('class',"header_l");
	e_td_4.appendChild(document.createTextNode('Connectors'));
	e_tr_3.appendChild(e_td_4);

	
	var e_td_4 = document.createElement('td');
	e_td_4.setAttribute('align',"right");
	e_td_4.setAttribute('class',"header_r");
	
	e_tr_3.appendChild(e_td_4);
	
	$(this.linkContainer.container).update(e_table_1);
//-----------	

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
	e_td_4.appendChild(document.createTextNode('Link'));


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);	
	e_td_4.appendChild(document.createTextNode('Element 1'));


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);
	e_td_4.appendChild(document.createTextNode('Element 2'));


	var e_td_4 = document.createElement('td');
	e_tr_3.appendChild(e_td_4);
	e_td_4.appendChild(document.createTextNode('Link status indicator'));


	var selementid = 0;
	for(var i=0; i<this.selection.position; i++){
		if(!isset(i, this.selection.selements)) continue;
		if(!isset(this.selection.selements[i], this.selements)) continue;
		
		selementid = this.selection.selements[i];
		break;
	}


	var selement = this.selements[selementid];
	var linkids = this.get_linkid_by_selementids(selementid);


	var count = 0;
	var maplink = null;
	for(var linkid in linkids){
		if(!isset(linkid, this.links)) continue;

		count++;
		maplink = this.links[linkid];
		
		if(count > 4) this.linkContainer.container.style.height = '100x';
		else this.linkContainer.container.style.height = 'auto';

		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "even_row";
		e_tbody_2.appendChild(e_tr_3);
		
	
		var e_td_4 = document.createElement('td');
		e_tr_3.appendChild(e_td_4);
	
	
		var e_span_5 = document.createElement('span');
//		e_span_5.setAttribute('href',"sysmap.php?sysmapid=100100000000002&amp;form=update&amp;selementid=100100000000004&amp;sid=791bd54e24454e2b");
//		e_span_5.className = "link";
		e_span_5.appendChild(document.createTextNode('Link '+count));
		e_td_4.appendChild(e_span_5);


		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(this.selements[maplink.selementid1].label));
		e_tr_3.appendChild(e_td_4);		


		var e_td_4 = document.createElement('td');
		e_td_4.appendChild(document.createTextNode(this.selements[maplink.selementid2].label));
		e_tr_3.appendChild(e_td_4);


		var e_td_4 = document.createElement('td');
		for(var linktriggerid in maplink.linktriggers){
			if(empty(maplink.linktriggers[linktriggerid])) continue;
			
			e_td_4.appendChild(document.createTextNode(maplink.linktriggers[linktriggerid].desc_exp));
			e_td_4.appendChild(document.createElement('br'));
		}
		e_tr_3.appendChild(e_td_4);
	}
s
	if(count == 0){
		var e_tr_3 = document.createElement('tr');
		e_tr_3.className = "even_row";
		e_tbody_2.appendChild(e_tr_3);
		
		var e_td_4 = document.createElement('td');
		e_td_4.setAttribute('colSpan',4);
		e_td_4.setAttribute('class','center');
		e_td_4.appendChild(document.createTextNode('No links'));
		e_tr_3.appendChild(e_td_4);
	}

	this.linkContainer.container.appendChild(e_table_1);
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
	e_td_5.className = "form_row_first pointer";
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


	e_td_5.appendChild(document.createTextNode('Edit map element'));


	var e_tr_4 = document.createElement('tr');
this.selementForm.massEdit.elementtype = e_tr_4;

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Type'));
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

	addListener(e_select_6, 'change', this.updateForm_selementByType.bindAsEventListener(this));


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"0");
	e_option_7.appendChild(document.createTextNode('Host'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"1");
	e_option_7.appendChild(document.createTextNode('Map'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"2");
	e_option_7.appendChild(document.createTextNode('Trigger'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"3");
	e_option_7.appendChild(document.createTextNode('Host group'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"4");
	e_option_7.appendChild(document.createTextNode('Image'));
	e_select_6.appendChild(e_option_7);

// LABEL
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Label'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_textarea_6 = document.createElement('textarea');
this.selementForm.label = e_textarea_6;

	e_textarea_6.setAttribute('cols',"32");
	e_textarea_6.setAttribute('rows',"4");
	e_textarea_6.setAttribute('name',"label");
	e_textarea_6.className = "biginput";
	e_td_5.appendChild(e_textarea_6);

// LABEL LOCATION
	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Label location'));
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
	e_option_7.appendChild(document.createTextNode('Bottom'));
	e_select_6.appendChild(e_option_7);
	
	
	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"1");
	e_option_7.appendChild(document.createTextNode('Left'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"2");
	e_option_7.appendChild(document.createTextNode('Right'));
	e_select_6.appendChild(e_option_7);
	
	
	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"3");
	e_option_7.appendChild(document.createTextNode('Top'));
	e_select_6.appendChild(e_option_7);

// Element Name
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.elementName = e_tr_4;
this.selementForm.massEdit.elementName = e_tr_4;

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
this.selementForm.typeDOM.elementCaption = e_td_5;

	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Host'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.elementName = e_input_6;

	e_input_6.setAttribute('readonly',"readonly");
	e_input_6.setAttribute('value',"");
	e_input_6.setAttribute('size',"32");
	e_input_6.setAttribute('id',"elementName");
	e_input_6.setAttribute('name',"elementName");
	e_input_6.className = "biginput";
	e_td_5.appendChild(e_input_6);

	e_td_5.appendChild(document.createTextNode('  '));
	
	var e_span_6 = document.createElement('span');
this.selementForm.elementTypeSelect = e_span_6;

	e_span_6.className = "link";
	e_span_6.appendChild(document.createTextNode('Select'));
	e_td_5.appendChild(e_span_6);

// ICON OFF
	var e_tr_4 = document.createElement('tr');
this.selementForm.typeDOM.iconid_off = e_tr_4;

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Icon (ok)'));
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


	var icons = zbx_selement_form_menu['icons'];
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

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Icon (problem)'));
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


	var icons = zbx_selement_form_menu['icons'];
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

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Icon (unknown)'));
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


	var icons = zbx_selement_form_menu['icons'];
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

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Icon (In maintenance)'));
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


	var icons = zbx_selement_form_menu['icons'];
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

	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Icon (disabled)'));
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


	var icons = zbx_selement_form_menu['icons'];
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
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Coordinate X'));
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
//	e_td_5.appendChild(e_input_6);

	var e_span_6 = document.createElement('span');
this.selementForm.x = e_span_6;
	e_td_5.appendChild(e_span_6);

// Y
	var e_tr_4 = document.createElement('tr');
this.selementForm.massEdit.y = e_tr_4;
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Coordinate Y'));
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
//	e_td_5.appendChild(e_input_6);
	
	var e_span_6 = document.createElement('span');
this.selementForm.y = e_span_6;
	e_td_5.appendChild(e_span_6);
	

	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('URL'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
this.selementForm.url = e_input_6;
	e_input_6.setAttribute('value', '');
	e_input_6.setAttribute('size',"42");
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
	e_input_6.setAttribute('value',"Apply");
	
	addListener(e_input_6, 'click', this.saveForm_selement.bindAsEventListener(this));
	
	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"remove");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Remove");
	
	addListener(e_input_6, 'click', this.deleteForm_selement.bindAsEventListener(this));

	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"close");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Close");
	
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
// If we already editing it than do not update it
		if(this.selementForm.selementid.value == selementid) return false;
		
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
		
// Icon PROBLEM
		for(var i=0; i<this.selementForm.iconid_on.options.length; i++){
			if(!isset(i, this.selementForm.iconid_on.options)) continue;
			
			if(this.selementForm.iconid_on.options[i].value === selement.iconid_on){
				this.selementForm.iconid_on.options[i].selected = true;
			}
		}
	
// Icon UNKNOWN
		for(var i=0; i<this.selementForm.iconid_unknown.options.length; i++){
			if(!isset(i, this.selementForm.iconid_unknown.options)) continue;
			
			if(this.selementForm.iconid_unknown.options[i].value === selement.iconid_unknown){
				this.selementForm.iconid_unknown.options[i].selected = true;
			}
		}
	
// Icon MAINTENANCE
		for(var i=0; i<this.selementForm.iconid_maintenance.options.length; i++){
			if(!isset(i, this.selementForm.iconid_maintenance.options)) continue;
			
			if(this.selementForm.iconid_maintenance.options[i].value === selement.iconid_maintenance){
				this.selementForm.iconid_maintenance.options[i].selected = true;
			}
		}
	
// Icon DISABLED
		for(var i=0; i<this.selementForm.iconid_disabled.options.length; i++){
			if(!isset(i, this.selementForm.iconid_disabled.options)) continue;
			
			if(this.selementForm.iconid_disabled.options[i].value === selement.iconid_disabled){
				this.selementForm.iconid_disabled.options[i].selected = true;
			}
		}

// X & Y
//		this.selementForm.x.value = selement.x;
//		this.selementForm.y.value = selement.y;
		$(this.selementForm.x).update(selement.x);
		$(this.selementForm.y).update(selement.y);

// URL
		this.selementForm.url.value = selement.url;

		this.updateForm_selementByType(e);
	}
	else{

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
updateForm_selementByType: function(e, multi){
	this.debug('updateForm_selementByType');
//--
	var multi = multi || false;
	var display_style = IE?'block':'table-row';

	if(multi){
		this.selementForm.massEdit.elementtype.style.display = 'none';
		this.selementForm.massEdit.elementName.style.display = 'none';

		this.selementForm.typeDOM.iconid_off.style.display = display_style;
		this.selementForm.typeDOM.iconid_on.style.display = display_style;
		this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
		this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
		this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
		
		this.selementForm.massEdit.x.style.display = 'none';
		this.selementForm.massEdit.y.style.display = 'none';
		return true;
	}
	else{
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
			$(this.selementForm.typeDOM.elementCaption).update('Host');
			
			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
			this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
		break;
		case '1':
// maps
			var srctbl = 'maps';
			var srcfld1 = 'mapid';
			var srcfld2 = 'name';
			$(this.selementForm.typeDOM.elementCaption).update('Map');
			
			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;
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
			$(this.selementForm.typeDOM.elementCaption).update('Trigger');
			
			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = display_style;
			this.selementForm.typeDOM.iconid_disabled.style.display = display_style;
		break;
		case '3':
// host group
			var srctbl = 'groups';
			var srcfld1 = 'groupid';
			var srcfld2 = 'name';
			$(this.selementForm.typeDOM.elementCaption).update('Group');
			
			this.selementForm.typeDOM.elementName.style.display = display_style;
			this.selementForm.typeDOM.iconid_off.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = display_style;
			this.selementForm.typeDOM.iconid_unknown.style.display = display_style;
			this.selementForm.typeDOM.iconid_maintenance.style.display = 'none';
			this.selementForm.typeDOM.iconid_disabled.style.display = 'none';

		break;
		case '4':
// image
			$(this.selementForm.typeDOM.elementCaption).update('Image');
			
			this.selementForm.typeDOM.elementName.style.display = 'none';
			this.selementForm.typeDOM.iconid_off.style.display = display_style;
			this.selementForm.typeDOM.iconid_on.style.display = 'none';
			this.selementForm.typeDOM.iconid_unknown.style.display = 'none';
			this.selementForm.typeDOM.iconid_maintenance.style.display = 'none';
			this.selementForm.typeDOM.iconid_disabled.style.display = 'none';

		break;
	}
	
	if(!empty(srctbl)){
		var popup_url = 'popup.php?dstfrm=selementForm&dstfld1=elementid&dstfld2=elementName';
		popup_url+= '&srctbl='+srctbl;
		popup_url+= '&srcfld1='+srcfld1;
		popup_url+= '&srcfld2='+srcfld2;
		
		this.selementForm.elementTypeSelect.onclick =  function(){ PopUp(popup_url,450,450);};
	}

},

saveForm_selement: function(e){
	this.debug('saveForm_selement');
//--

	if(this.selection.count == 1){
		var selementid = this.selementForm.selementid.value;
		var selement = this.selements[selementid];
	
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
	
// X & Y
//	params.x = this.selementForm.x.value;
//	params.y = this.selementForm.y.value;

// URL
		params.url = this.selementForm.url.value;
		
		this.update_selement_option(selementid, params);
	}
	else{
		for(var i=0; i < this.selection.position; i++){
			if(!isset(i, this.selection.selements)) continue;
			if(!isset(this.selection.selements[i], this.selements)) continue;
			
						
			var selementid = this.selection.selements[i];
			var selement = this.selements[selementid];
	
			var params = {};
	
// Label
			params.label = this.selementForm.label.value;

// Label Location
			params.label_location = parseInt(this.selementForm.label_location.selectedIndex, 10) - 1;
			
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
	
// URL
			params.url = this.selementForm.url.value;


			this.update_selement_option(selementid, params);
		}
	}
	this.update_multiContainer(e);
//	this.hideForm();
},

deleteForm_selement: function(e){
	this.debug('deleteForm_selement');
//--

	var selementid = this.selementForm.selementid.value;	
	var selement = this.selements[selementid];
	
	if(Confirm('Delete element "'+selement.elementName+'"?')){
		this.remove_selement(selementid, true);
		this.hideForm(e);
	}
	else
		return false;
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
createForm_link: function(e){
	this.debug('createForm_link');
//--


// var initialization of diferent types of form
	this.linkForm.typeDOM = {};
	this.linkForm.massEdit = {};

// Form creation
	var e_form_1 = document.createElement('form');
this.linkForm.form = e_form_1;

	e_form_1.setAttribute('id',"linkForm");
	e_form_1.setAttribute('name',"web.sysmap.connector.php");
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
	e_span_6.setAttribute('class',"http://www.zabbix.com/documentation.php");
	e_td_5.appendChild(e_span_6);


	var e_div_7 = document.createElement('div');
	e_div_7.className = "iconhelp";
	e_div_7.appendChild(document.createTextNode(' '));
	e_span_6.appendChild(e_div_7);


	e_td_5.appendChild(document.createTextNode('Edit connector'));


	var e_input_4 = document.createElement('input');
	e_input_4.setAttribute('type',"hidden");
	e_input_4.setAttribute('value',"791bd54e24454e2b");
	e_input_4.setAttribute('id',"sid");
	e_input_4.setAttribute('name',"sid");
	e_tbody_3.appendChild(e_input_4);


	var e_input_4 = document.createElement('input');
	e_input_4.setAttribute('type',"hidden");
	e_input_4.setAttribute('value',"update");
	e_input_4.setAttribute('id',"form");
	e_input_4.setAttribute('name',"form");
	e_tbody_3.appendChild(e_input_4);


	var e_input_4 = document.createElement('input');
	e_input_4.setAttribute('type',"hidden");
	e_input_4.setAttribute('value',"1");
	e_input_4.setAttribute('id',"form_refresh");
	e_input_4.setAttribute('name',"form_refresh");
	e_tbody_3.appendChild(e_input_4);


	var e_input_4 = document.createElement('input');
	e_input_4.setAttribute('type',"hidden");
	e_input_4.setAttribute('value',"100100000000002");
	e_input_4.setAttribute('id',"sysmapid");
	e_input_4.setAttribute('name',"sysmapid");
	e_tbody_3.appendChild(e_input_4);


	var e_input_4 = document.createElement('input');
	e_input_4.setAttribute('type',"hidden");
	e_input_4.setAttribute('value',"100100000000018");
	e_input_4.setAttribute('id',"linkid");
	e_input_4.setAttribute('name',"linkid");
	e_tbody_3.appendChild(e_input_4);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Element 1'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"selementid1");
	e_select_6.setAttribute('id',"selementid1");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('selected',"selected");
	e_option_7.setAttribute('value',"100100000000002");
	e_option_7.appendChild(document.createTextNode('ZABBIX Server:ZABBIX-Server'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"100100000000005");
	e_option_7.appendChild(document.createTextNode('ZABBIX Server:ZABBIX-Server'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"100100000000004");
	e_option_7.appendChild(document.createTextNode('hpg_3000:hpg_3000'));
	e_select_6.appendChild(e_option_7);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_tr_4.appendChild(e_td_5);


	e_td_5.appendChild(document.createTextNode('Element 2'));


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"selementid2");
	e_select_6.setAttribute('id',"selementid2");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"100100000000002");
	e_option_7.appendChild(document.createTextNode('ZABBIX Server:ZABBIX-Server'));
	e_select_6.appendChild(e_option_7);



	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"100100000000005");
	e_option_7.appendChild(document.createTextNode('ZABBIX-Server2:ZABBIX-Server'));
	e_select_6.appendChild(e_option_7);



	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('selected',"selected");
	e_option_7.setAttribute('value',"100100000000004");
	e_option_7.appendChild(document.createTextNode('hpg_3000:hpg_3000'));
	e_select_6.appendChild(e_option_7);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Link status indicators'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_table_6 = document.createElement('table');
	e_table_6.setAttribute('cellSpacing',"1");
	e_table_6.setAttribute('cellPadding',"3");
	e_table_6.setAttribute('id',"link_triggers");
	e_table_6.className = "tableinfo";
	e_td_5.appendChild(e_table_6);


	var e_tbody_7 = document.createElement('tbody');
	e_table_6.appendChild(e_tbody_7);


	var e_tr_8 = document.createElement('tr');
	e_tr_8.className = "header";
	e_tbody_7.appendChild(e_tr_8);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"checkbox");
	e_input_10.setAttribute('onclick',"checkAll('web.sysmap.connector.php','all_triggers','triggers');");
	e_input_10.setAttribute('id',"all_triggers");
	e_input_10.setAttribute('name',"all_triggers");
	e_input_10.setAttribute('value',"yes");
	e_input_10.className = "checkbox";
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_td_9.appendChild(document.createTextNode('Triggers'));
	e_tr_8.appendChild(e_td_9);


	var e_td_9 = document.createElement('td');
	e_td_9.appendChild(document.createTextNode('Type'));
	e_tr_8.appendChild(e_td_9);


	var e_td_9 = document.createElement('td');
	e_td_9.appendChild(document.createTextNode('Colour'));
	e_tr_8.appendChild(e_td_9);


	var e_tr_8 = document.createElement('tr');
	e_tr_8.className = "even_row";
	e_tbody_7.appendChild(e_tr_8);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);
	
	
	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"checkbox");
	e_input_10.setAttribute('id',"triggers[100100000013490][triggerid]");
	e_input_10.setAttribute('name',"triggers[100100000013490][triggerid]");
	e_input_10.setAttribute('value',"100100000013490");
	e_input_10.className = "checkbox";
	e_td_9.appendChild(e_input_10);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"100100000013490");
	e_input_10.setAttribute('id',"triggers[100100000013490][triggerid]");
	e_input_10.setAttribute('name',"triggers[100100000013490][triggerid]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_span_10 = document.createElement('span');
	e_span_10.className = "link";
	e_span_10.setAttribute('onclick',"javascript: alert('ZBX_Link_Indicator');");
	e_span_10.appendChild(document.createTextNode('gzip compression is off for connector http-8080 on Conflict'));	//openWinCentered('popup_link_tr.php?form=1&amp;dstfrm=web.sysmap.connector.php&triggerid=100100000013490&drawtype=0&color=000077','ZBX_Link_Indicator',560,260,'scrollbars=1, toolbar=0, menubar=0, resizable=0');");
	e_td_9.appendChild(e_span_10);



	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"gzip compression is off for connector http-8080 on Conflict");
	e_input_10.setAttribute('id',"triggers[100100000013490][description]");
	e_input_10.setAttribute('name',"triggers[100100000013490][description]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);
	e_td_9.appendChild(document.createTextNode('Line'));


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"0");
	e_input_10.setAttribute('id',"triggers[100100000013490][drawtype]");
	e_input_10.setAttribute('name',"triggers[100100000013490][drawtype]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_span_10 = document.createElement('span');
	e_span_10.setAttribute('style',"text-decoration: none; outline-color: black; outline-style: solid; outline-width: 1px; background-color: rgb(0, 0, 119);");
	e_span_10.appendChild(document.createTextNode('   '));
	e_td_9.appendChild(e_span_10);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"000077");
	e_input_10.setAttribute('id',"triggers[100100000013490][color]");
	e_input_10.setAttribute('name',"triggers[100100000013490][color]");
	e_td_9.appendChild(e_input_10);


	var e_tr_8 = document.createElement('tr');
	e_tr_8.className = "even_row";
	e_tbody_7.appendChild(e_tr_8);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"checkbox");
	e_input_10.setAttribute('id',"triggers[100100000013492][triggerid]");
	e_input_10.setAttribute('name',"triggers[100100000013492][triggerid]");
	e_input_10.setAttribute('value',"100100000013492");
	e_input_10.className = "checkbox";
	e_td_9.appendChild(e_input_10);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"100100000013492");
	e_input_10.setAttribute('id',"triggers[100100000013492][triggerid]");
	e_input_10.setAttribute('name',"triggers[100100000013492][triggerid]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_span_10 = document.createElement('span');
	e_span_10.setAttribute('onclick',"javascript: openWinCentered('popup_link_tr.php?form=1&amp;dstfrm=web.sysmap.connector.php&amp;triggerid=100100000013492&amp;drawtype=0&amp;color=007700','ZBX_Link_Indicator',560,260,'scrollbars=1, toolbar=0, menubar=0, resizable=0');");
	e_span_10.className = "link";
	e_span_10.appendChild(document.createTextNode('70% http-8080 worker threads busy on Conflict'));
	e_td_9.appendChild(e_span_10);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"70% http-8080 worker threads busy on Conflict");
	e_input_10.setAttribute('id',"triggers[100100000013492][description]");
	e_input_10.setAttribute('name',"triggers[100100000013492][description]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);
	e_td_9.appendChild(document.createTextNode('Line'));
	
	
	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"0");
	e_input_10.setAttribute('id',"triggers[100100000013492][drawtype]");
	e_input_10.setAttribute('name',"triggers[100100000013492][drawtype]");
	e_td_9.appendChild(e_input_10);


	var e_td_9 = document.createElement('td');
	e_tr_8.appendChild(e_td_9);


	var e_span_10 = document.createElement('span');
	e_span_10.setAttribute('style',"text-decoration: none; outline-color: black; outline-style: solid; outline-width: 1px; background-color: rgb(0, 119, 0);");
	e_span_10.appendChild(document.createTextNode('   '));
	e_td_9.appendChild(e_span_10);


	var e_input_10 = document.createElement('input');
	e_input_10.setAttribute('type',"hidden");
	e_input_10.setAttribute('value',"007700");
	e_input_10.setAttribute('id',"triggers[100100000013492][color]");
	e_input_10.setAttribute('name',"triggers[100100000013492][color]");
	e_td_9.appendChild(e_input_10);


	var e_br_6 = document.createElement('br');
	e_td_5.appendChild(e_br_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('accesskey',"T");
	e_input_6.setAttribute('title',"Add [Alt+T]");
	e_input_6.setAttribute('onclick',"javascript: openWinCentered('popup_link_tr.php?form=1&amp;dstfrm=web.sysmap.connector.php','ZBX_Link_Indicator',560,260,'scrollbars=1, toolbar=0, menubar=0, resizable=0');");
	e_input_6.setAttribute('name',"btn1");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Add");
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"submit");
	e_input_6.setAttribute('accesskey',"T");
	e_input_6.setAttribute('title',"Remove [Alt+T]");
	e_input_6.setAttribute('onclick',"javascript: remove_childs_6('web.sysmap.connector.php','triggers','tr');");
	e_input_6.setAttribute('name',"btn1");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Remove");
	e_td_5.appendChild(e_input_6);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_tr_4.appendChild(e_td_5);
	e_td_5.appendChild(document.createTextNode('Type (OK)'));


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_select_6 = document.createElement('select');
	e_select_6.setAttribute('size',"1");
	e_select_6.className = "biginput";
	e_select_6.setAttribute('name',"drawtype");
	e_select_6.setAttribute('id',"drawtype");
	e_td_5.appendChild(e_select_6);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"0");
	e_option_7.appendChild(document.createTextNode('Line'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"2");
	e_option_7.appendChild(document.createTextNode('Bold line'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"3");
	e_option_7.appendChild(document.createTextNode('Dot'));
	e_select_6.appendChild(e_option_7);


	var e_option_7 = document.createElement('option');
	e_option_7.setAttribute('value',"4");
	e_option_7.appendChild(document.createTextNode('Dashed line'));
	e_select_6.appendChild(e_option_7);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "form_even_row";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_l";
	e_td_5.appendChild(document.createTextNode('Colour (OK)'));
	e_tr_4.appendChild(e_td_5);


	var e_td_5 = document.createElement('td');
	e_td_5.className = "form_row_r";
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
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
	e_div_6.setAttribute('onclick',"javascript: show_color_picker('color')");
	e_div_6.setAttribute('style',"border: 1px solid black; display: inline; width: 10px; height: 10px; text-decoration: none; background-color: rgb(0, 0, 85);");
	e_div_6.setAttribute('title',"#000055");
	e_div_6.setAttribute('id',"lbl_color");
	e_div_6.setAttribute('name',"lbl_color");
	e_div_6.className = "pointer";
	e_div_6.appendChild(document.createTextNode('   '));
	e_td_5.appendChild(e_div_6);


	var e_tr_4 = document.createElement('tr');
	e_tr_4.className = "footer";
	e_tbody_3.appendChild(e_tr_4);


	var e_td_5 = document.createElement('td');
	e_td_5.setAttribute('colSpan',"2");
	e_td_5.className = "form_row_last";
	e_td_5.appendChild(document.createTextNode(' '));
	e_tr_4.appendChild(e_td_5);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"submit");
	e_input_6.setAttribute('name',"save_link_6");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Save");
	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"submit");
	e_input_6.setAttribute('onclick',"if(Confirm('Delete link?')) return redirect('sysmap.php?delete=1&amp;linkid=100100000000018&amp;sysmapid=100100000000002&amp;sid=791bd54e24454e2b'); else return false;");
	e_input_6.setAttribute('name',"delete");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Delete");
	e_td_5.appendChild(document.createTextNode(' '));
	e_td_5.appendChild(e_input_6);


	var e_input_6 = document.createElement('input');
	e_input_6.setAttribute('type',"button");
	e_input_6.setAttribute('name',"cancel");
	e_input_6.className = "button";
	e_input_6.setAttribute('value',"Cancel");

	e_td_5.appendChild(e_input_6);
},

updateForm_link: function(e){
},



//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************
//**************************************************************************************************************

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
		for(var form_key in zbx_link_menu){
			var caption = zbx_link_menu[form_key];
	
			var sub_menu = new Array(caption,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
			sub_menu.push([caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);
			sub_menu.push(['<span onclick="javascript: ZBX_SYSMAPS['+this.id+'].map.add_empty_linktrigger('+id+');">Add Trigger</span>',
					'#',
					function(){return false;},
					{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}
					]);

			if(form_key == 'triggers'){
//SDJ(mlink);
				for(var j=0; j < mlink.linktriggers.length; j++){
					if((typeof(mlink.linktriggers[j]) == 'undefined') || is_null(mlink.linktriggers[j])) continue;

					var linktrigger = mlink.linktriggers[j];
					var desc_exp_trunc = linktrigger.desc_exp.substr(0, 40)+'...';
//SDJ(linktrigger);
					var ssub_menu = new Array(desc_exp_trunc,null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
					ssub_menu.push([desc_exp_trunc,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

					for(var lt_field in linktrigger){
						if(lt_field == 'triggerid'){
							var srctbl1 = 'triggers';
							var srcfld1 = 'triggerid';

							var value_action = 'javascript: '+
								"PopUp('popup.php?srctbl="+srctbl1+
									'&reference=sysmap_linktrigger'+
									'&sysmapid='+this.sysmapid+
									'&cmapid='+this.id+
									'&sid='+id+
									'&ssid='+linktrigger.linktriggerid+
									'&dstfrm=null'+
									'&srcfld1='+srcfld1+
									"&dstfld1="+srcfld1+"',800,450); void(0);";


							value_action = '<span onclick="'+value_action+'">'+desc_exp_trunc+'</span>';

							ssub_menu.push([value_action,'#',function(){return false;}]);
						}
						else{
							if(typeof(zbx_link_form_menu[lt_field]) == 'undefined') continue;

							var fields = zbx_link_form_menu[lt_field];

							var sssub_menu = new Array(zbx_link_menu[lt_field],null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']});
							sssub_menu.push([zbx_link_menu[lt_field],null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

							for(var k=0; k < fields.length; k++){
								if(typeof(fields[k]) != 'undefined'){
									var values = fields[k];
									var value_action = "javascript: ZBX_SYSMAPS["+this.id+"].map.update_linktrigger_option("+id+","+linktrigger.linktriggerid+",[{'key':'"+lt_field+"','value':'"+values['key']+"'}]);";
									value_action = '<span onclick="'+value_action+'">'+values['value']+'</span>';

									if(linktrigger[lt_field] == values['key'])
										sssub_menu.push([value_action,'#',null,{'outer' : ['pum_b_submenu'],'inner' : ['pum_i_submenu']}]);
									else
										sssub_menu.push([value_action,'#',function(){return false;}]);
								}
							}
							ssub_menu.push(sssub_menu);
						}
					}

					sub_menu.push(ssub_menu);
				}
			}
			else if((form_key == 'selementid1') || (form_key == 'selementid2')){
				for(var j=0; j<this.selementids.length; j++){
					if((typeof(this.selementids[j]) != 'undefined') && !is_null(this.selementids[j])){
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