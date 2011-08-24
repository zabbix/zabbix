/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
**/
// JavaScript Document
// Tree manipulations class
// author: Aly


var CTree = Class.create();
CTree.prototype = {
tree_name: null,
treenode: new Array(),   

initialize : function(tree_name, treenode){
	this.tree_name = tree_name;
	this.treenode = treenode;

	if((tree_init = cookie.read(tree_name)) != null){
		var nodes = tree_init.split(',');
		var c = nodes.length-1;
		for(var i=0; i<c; i++){
			this.onStartSetStatus(nodes[i]);
		}
		this.onStartOpen(nodes);
	}
},

getNodeStatus : function(id){
	try{
		if(this.treenode[id].status == 'close'){
			return 'close';
		} else {
			return 'open';
		}
	} 
	catch(e){
		return 'close';
	}
},

ChangeNodeStatus : function(id){
	try{
		if(this.treenode[id].status == 'close'){
			this.treenode[id].status = 'open';
		} 
		else {
			this.treenode[id].status = 'close';
		}
		var cookie_str='';
		for(var i = 1; i < this.treenode.length; i++){
			if(typeof(this.treenode[i]) != 'undefined'){
				if(this.treenode[i].status == 'open'){
					cookie_str+=i+',';
				}
			}
		}
		cookie.create(this.tree_name,cookie_str);
	} 
	catch(e){
		IE?(alert(e.description)):(alert(e));
	}
},


closeSNodeX : function(id,img){
	try{
		nodelist = this.treenode[id].nodelist.split(',');
		if(this.getNodeStatus(id) == 'close'){
			this.OpenNode(nodelist);
			img.src = 'images/general/tree/minus.gif';
		} 
		else {
			this.CloseNode(nodelist);
			img.src = 'images/general/tree/plus.gif';
		}
		this.ChangeNodeStatus(id);
	} 
	catch(e){
		throw('JSTree ERROR [closeSNodeX]: '+e);
		return;
	}
},

OpenNode : function(nodelist){
	try{
		var c = nodelist.length-1;
		for(var i=0; i<c; i++){
			document.getElementById('id_'+nodelist[i]).style.display = (IE)?('block'):('table-row');
			if(this.checkParent(nodelist[i])){
				if(this.getNodeStatus(nodelist[i]) == 'open'){
					this.OpenNode(this.treenode[nodelist[i]].nodelist.split(','));
				}
			}
		}
	} 
	catch(e){
		throw('JSTree ERROR [OpenNode]: '+e);
	}
},
	
CloseNode : function(nodelist){
	try{
		var c = nodelist.length-1;
		for(var i=0; i<c; i++){
			document.getElementById('id_'+nodelist[i]).style.display = 'none';
			if(this.checkParent(nodelist[i])){
				if(this.getNodeStatus(nodelist[i]) == 'open'){
					this.CloseNode(this.treenode[nodelist[i]].nodelist.split(','));
				}
			}
		}
	} 
	catch(e){ 
		throw('JSTree ERROR [CloseNode]: '+e);
	}
},

onStartOpen : function(nodes){
	var nodes = tree_init.split(',');
	var c = nodes.length-1;
	for(var i=0; i<c;i++){
		if(typeof(nodes[i]) != 'undefined'){
			try{
//				alert(nodes[i]+' : '+this.checkParent(nodes[i]));
				if(this.checkParent(nodes[i])){
					var nodelist = this.treenode[nodes[i]].nodelist.split(',');
					this.OpenNode(nodelist);
				}
			} 
			catch(e){
				cookie.erase(this.tree_name);
				throw('JSTree ERROR [OnStartOpen]: '+e);
			}
		}
	}
},

onStartSetStatus : function(id){
	try{
		if(typeof(this.treenode[id]) == 'undefined') return;
		var img_id='idi_'+id;
		var img = document.getElementById(img_id);
		img.src = 'images/general/tree/minus.gif';
		
		this.treenode[id].status = 'open';
	} 
	catch(e){
		throw('JSTree ERROR [OnStartSetStatus]: '+e);
	}
},

checkParent : function(id){
	try{
		if(id == '0'){
			return true;
		} 
		else if(typeof(this.treenode[id]) == 'undefined'){
			return false;
		}
		else if(this.treenode[id].status != 'open'){
			return false;
		} 
		else {
			return this.checkParent(this.treenode[id].parentid);
		}
	} 
	catch(e){
		throw('JSTree ERROR [checkPparent]: '+e);
	}
}
}
