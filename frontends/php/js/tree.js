/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
var IE = document.all?true:false;
var OP = window.opera?true:false;


var tree ={
init : function(){
	if((tree_init = cookie.read(tree_name)) != null){
		var nodes = tree_init.split('.');
		var c = nodes.length-1;
		for(var i=0; i<c;i++){
			this.onStartSetStatus(nodes[i]);
		}
		this.onStartOpen(nodes);
	}
},

getNodeStatus : function(id){
	try{
		if(treenode[id].status == 'close'){
			return 'close';
		} else {
			return 'open';
		}
	} catch(e){
		return 'close';
	}
},

ChangeNodeStatus : function(id){
	try{
		if(treenode[id].status == 'close'){
			treenode[id].status = 'open';
		} else {
			treenode[id].status = 'close';
		}
		var cookie_str='';
		for(var i = 1; i < treenode.length; i++){
			if(typeof(treenode[i]) != 'undefined'){
				if(treenode[i].status == 'open'){
					cookie_str+=i+'.';
				}
			}
		}
		cookie.create(tree_name,cookie_str);
	} catch(e){
		IE?(alert(e.description)):(alert(e));
	}
},


closeSNodeX : function(id,img){
	try{
		nodelist = treenode[id].nodelist.split('.');
		if(this.getNodeStatus(id) == 'close'){
			this.OpenNode(nodelist);
			img.src = 'images/general/tree/'+img.name.toUpperCase()+'.gif';
		} else {
			this.CloseNode(nodelist);
			img.src = 'images/general/tree/'+img.name.toUpperCase()+'c.gif';
		}
		this.ChangeNodeStatus(id);
	} catch(e){
//		alert('closeSNodeX: '+e);
		return;
	}
},

OpenNode : function(nodelist){
	try{
		var c = nodelist.length-1;
		for(var i=0; i<c; i++){
			document.getElementById(nodelist[i]).style.display = (!IE || OP)?("table-row"):('block');
			if(this.getNodeStatus(nodelist[i]) == 'open'){
				this.OpenNode(treenode[nodelist[i]].nodelist.split('.'));
			}
		}
	} catch(e){
//		alert('OpenNode: '+e);
	}
},
	
CloseNode : function(nodelist){
	try{
		var c = nodelist.length-1;
		for(var i=0; i<c; i++){
			document.getElementById(nodelist[i]).style.display = 'none';
			if(this.getNodeStatus(nodelist[i]) == 'open'){
				this.CloseNode(treenode[nodelist[i]].nodelist.split('.'));
			}
		}
	} catch(e){ 
//		alert('CloseNode: '+e);
	}
},

onStartOpen : function(nodes){
	var nodes = tree_init.split('.');
	var c = nodes.length-1;
	for(var i=0; i<c;i++){
		if(typeof(nodes[i]) != 'undefined'){
			try{
//				alert(nodes[i]+' : '+this.checkParent(nodes[i]));
				if(this.checkParent(nodes[i])){
					var nodelist = treenode[nodes[i]].nodelist.split('.');
					this.OpenNode(nodelist);
				}
			} catch(e){
				cookie.erase(tree_name);
//				alert('OnStartOpen: '+e);
			}
		}
	}
},

onStartSetStatus : function(id){
	try{
		if(typeof(treenode[id]) == 'undefined') return;
		var img_id=id+'I';;
		var img = document.getElementById(img_id);
		img.src = 'images/general/tree/'+img.name.toUpperCase()+'.gif';
		
		treenode[id].status = 'open';
	} catch(e){
//		alert('OnStartSetStatus: '+e);
	}
},

checkParent : function(id){
	try{
		
		if(id == '0'){
			return true;
		} else if(typeof(treenode[id]) == 'undefined'){
			return false;
		} else if(treenode[id].status != 'open'){
			return false;
		} else {
			return this.checkParent(treenode[id].parentid);
		}
	} catch(e){
//		alert('checkPparent: '+e);
	}
}
}
