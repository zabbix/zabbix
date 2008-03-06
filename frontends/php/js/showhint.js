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

function GetPos(obj){	
	var pos = getPosition(obj);
	
return [pos.left,pos.top];
}

var hint_box = null;

function hide_hint()
{
	if(!hint_box) return;

	hint_box.style.visibility="hidden"
	hint_box.style.left	= "-" + ((hint_box.style.width) ? hint_box.style.width : 100) + "px";
}

function show_hint(obj, e, hint_text)
{
	show_hint_ext(obj, e, hint_text, "", "");
}

function show_hint_ext(obj, e, hint_text, width, class_name)
{
	if(!hint_box) return;

	if(class_name != ""){
		hint_text = "<span class=" + class_name + ">" + hint_text + "</"+"span>";
	}

	hint_box.innerHTML = hint_text;
	hint_box.style.width = width;

	var cursor = get_cursor_position(e);
	var pos = GetPos(obj);

	var body_width = get_bodywidth();

	if(parseInt(cursor.x+10+hint_box.offsetWidth) > body_width){
		cursor.x-=parseInt(hint_box.offsetWidth);
		cursor.x-=10;
		cursor.x=(cursor.x < 0)?0:cursor.x;
	}
	else{
		cursor.x+=10;
	}

	hint_box.x	= cursor.x;
	hint_box.y	= pos[1];

	hint_box.style.left = cursor.x + "px";
//	hint_box.style.left	= hint_box.x + obj.offsetWidth + 10 + "px";
	hint_box.style.top	= hint_box.y + obj.offsetHeight + "px";

	hint_box.style.visibility = "visible";
	obj.onmouseout	= hide_hint;
}

function update_hint(obj, e)
{
	if(!hint_box) return;

	var cursor = get_cursor_position(e);
	var pos = GetPos(obj);
	
	var body_width = get_bodywidth();

	if(parseInt(cursor.x+10+hint_box.offsetWidth) > body_width){
		cursor.x-=parseInt(hint_box.offsetWidth);
		cursor.x-=10;
		cursor.x=(cursor.x < 0)?0:cursor.x;
	}
	else{
		cursor.x+=10;
	}

	hint_box.style.left     = cursor.x + "px";
//	hint_box.style.left		= hint_box.x + obj.offsetWidth + 10 + "px";
	hint_box.style.top      = hint_box.y + obj.offsetHeight + "px";
}

function create_hint_box()
{
	if(hint_box) return;

	hint_box = document.createElement("div");
	hint_box.setAttribute("id", "hint_box");
	document.body.appendChild(hint_box);

	hide_hint();
}

if (window.addEventListener)
{
	window.addEventListener("load", create_hint_box, false);
}
else if (window.attachEvent)
{
	window.attachEvent("onload", create_hint_box);
}
else if (document.getElementById)
{
	window.onload	= create_hint_box;
}
//-->
