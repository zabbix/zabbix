// JavaScript Document
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
**
*/

var hintBox = {
boxes:				{},				// array of dom Hint Boxes
boxesCount: 		0,				// unique box id


debug_status: 		0,				// debug status: 0 - off, 1 - on, 2 - SDI;
debug_info: 		'',				// debug string
debug_prev:			'',				// don't log repeated fnc

createBox: function(obj, hint_text, width, className, byClick){
	this.debug('createBox');
	
	var boxid = 'hintbox_'+this.boxesCount;
	
	var box = document.createElement('div');
	
	var obj_tag = obj.nodeName.toLowerCase();
	
	if((obj_tag == 'td') || (obj_tag == 'div') || (obj_tag == 'body')) obj.appendChild(box);
	else obj.parentNode.appendChild(box);
	
	box.setAttribute('id', boxid);
	box.className = 'hintbox';
	
	if(!empty(className)){
		hint_text = "<span class=" + className + ">" + hint_text + "</"+"span>";
	}
	
	if(!empty(width)){
		box.style.width = width+'px';
	}
	
	var close_link = '';
	if(byClick){
		close_link = '<div class="link" '+
						'style="text-align: right; backgground-color: #AAA; border-bottom: 1px #333 solid;" '+
						'onclick="javascript: hintBox.hide(event, \''+boxid+'\');">Close</div>';
	}

	box.innerHTML = close_link + hint_text;
	
/*	
	var box_close = document.createElement('div');
	box.appendChild(box_close);	
	box_close.appendChild(document.createTextNode('X'));
	box_close.className = 'link';
	box_close.setAttribute('style','text-align: right; backgground-color: #AAA;');
	box_close.onclick = eval("function(){ hintBox.hide('"+boxid+"'); }");
*/
	this.boxes[boxid] = box;
	this.boxesCount++;
	
return box;
},

showOver: function(e, obj, hint_text, width, className){
	this.debug('showOver');
	
	if (!e) var e = window.event;	
	
	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox)) 
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;

	if(!empty(byClick)) return;

	var hintbox = this.createBox(obj,hint_text, width, className, false);
	
	obj.setAttribute('hintid', hintbox.id);
	this.show(e, obj, hintbox);
},

hideOut: function(e, obj){
	this.debug('hideOut');
	
	if (!e) var e = window.event;	
	
	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox)) 
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;

	if(!empty(byClick)) return;
	
	if(!empty(hintid)){
		obj.removeAttribute('hintid');
		obj.removeAttribute('byclick');
	
		this.hide(e, hintid);
	}
},

onClick: function(e, obj, hint_text, width, className){
	this.debug('onClick');

	if (!e) var e = window.event;
	cancelEvent(e);
	
	var hintid = obj.getAttribute('hintid');
	var hintbox = $(hintid);

	if(!empty(hintbox)) 
		var byClick = hintbox.getAttribute('byclick');
	else
		var byClick = null;
	
	if(!empty(hintid) && empty(byClick)){
		obj.removeAttribute('hintid');
		this.hide(e, hintid);
		
		var hintbox = this.createBox(obj, hint_text, width, className, true);
		
		hintbox.setAttribute('byclick', 'true');
		obj.setAttribute('hintid', hintbox.id);
		
		this.show(e, obj, hintbox);
	}
	else if(!empty(hintid)){
		obj.removeAttribute('hintid');
		hintbox.removeAttribute('byclick');
		
		this.hide(e, hintid);
	}
	else{
		var hintbox = this.createBox(obj,hint_text, width, className, true);
		
		hintbox.setAttribute('byclick', 'true');
		obj.setAttribute('hintid', hintbox.id);
		
		this.show(e, obj, hintbox);
	}
},

show: function(e, obj, hintbox){
	this.debug('show');
	
	var hintid = hintbox.id;
	var body_width = get_bodywidth();

	var pos = getPosition(obj);
	var cursor = get_cursor_position(e);
	
// by Object
/*
	if(parseInt(pos.left+obj.offsetWidth+4+hintbox.offsetWidth) > body_width){
		pos.left-=parseInt(hintbox.offsetWidth);
		pos.left-=4;
		pos.left=(pos.left < 0)?0:pos.left;
	}
	else{
		pos.left+= obj.offsetWidth+4;
	}
	hintbox.x	= pos.left;
//*/
// by Cursor
//*
	if(parseInt(cursor.x+10+hintbox.offsetWidth) > body_width){
		cursor.x-=parseInt(hintbox.offsetWidth);
		cursor.x-=10;
		cursor.x=(cursor.x < 0)?0:cursor.x;
	}
	else{
		cursor.x+=10;
	}
	hintbox.x	= cursor.x;
//*/

	hintbox.y	= pos.top;

	hintbox.style.left = hintbox.x + 'px';
	hintbox.style.top	= hintbox.y + 10 + parseInt(obj.offsetHeight/2) + 'px';
},

hide: function(e, boxid){
	this.debug('hide');
	
	if (!e) var e = window.event;	
	cancelEvent(e);

	var hint = $(boxid);
	if(!is_null(hint)){
		delete(this.boxes[boxid]);
		
		hint.style.display = 'none';
		hint.remove();
	}
},

hideAll: function(){
	this.debug('hideAll');

	for(var id in this.boxes){
		if((typeof(this.boxes[id]) != 'undefined') && !empty(this.boxes[id])){
			this.hide(id);
		}
	}
},
	
debug: function(fnc_name, id){
	if(this.debug_status){
		var str = 'PMaster.'+fnc_name;
		if(typeof(id) != 'undefined') str+= ' :'+id;

		if(this.debug_prev == str) return true;

		this.debug_info += str + '\n';
		if(this.debug_status == 2){
			SDI(str);
		}
		
		this.debug_prev = str;
	}
}
}