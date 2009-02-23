/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
function hide_color_picker(){
	if(!color_picker) return;

	color_picker.style.visibility="hidden"
	color_picker.style.left	= "-" + ((color_picker.style.width) ? color_picker.style.width : 100) + "px";

	curr_lbl = null;
	curr_txt = null;
}

function show_color_picker(name){
	if(!color_picker) return;

	curr_lbl = document.getElementById("lbl_" + name);
	curr_txt = document.getElementById(name);
	
	var pos = getPosition(curr_lbl);

	color_picker.x	= pos.left;
	color_picker.y	= pos.top;

	color_picker.style.left	= color_picker.x + "px";
	color_picker.style.top	= color_picker.y + "px";

	color_picker.style.visibility = "visible";
}

function create_color_picker(){
	if(color_picker) return;

	color_picker = document.createElement("div");
	color_picker.setAttribute("id", "color_picker");
	color_picker.innerHTML = color_table;
	document.body.appendChild(color_picker);

	hide_color_picker();
}

function set_color(color){
	if(curr_lbl)	curr_lbl.style.background = curr_lbl.style.color = "#" + color;
	if(curr_txt)	curr_txt.value = color;

	hide_color_picker();
}

function set_color_by_name(name, color){
	curr_lbl = document.getElementById("lbl_" + name);
	curr_txt = document.getElementById(name);
	
	set_color(color);
}