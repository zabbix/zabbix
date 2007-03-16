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
function get_scroll_pos()
{
	var scrOfX = 0, scrOfY = 0;
	if( typeof( window.pageYOffset ) == 'number' )
	{	//Netscape compliant
		scrOfY = window.pageYOffset;
		scrOfX = window.pageXOffset;
	}
	else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) )
	{	//DOM compliant
		scrOfY = document.body.scrollTop;
		scrOfX = document.body.scrollLeft;
	}
	else if( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) )
	{	//IE6 standards compliant mode
		scrOfY = document.documentElement.scrollTop;
		scrOfX = document.documentElement.scrollLeft;
	}
	return [ scrOfX, scrOfY ];
}
function get_cursor_position(e)
{
	e = e || window.event;
	var cursor = {x:0, y:0};
	if (e.pageX || e.pageY) {
		cursor.x = e.pageX;
		cursor.y = e.pageY;
	} 
	else {
		var de = document.documentElement;
		var b = document.body;
		cursor.x = e.clientX + 
		(de.scrollLeft || b.scrollLeft) - (de.clientLeft || 0);
		cursor.y = e.clientY + 
		(de.scrollTop || b.scrollTop) - (de.clientTop || 0);
	}
	return cursor;
}

function Redirect(url) {
	window.location = url;
	return false;
}	

function create_var(form_name, var_name, var_val, submit)
{
	var frmForm = document.forms[form_name];

	if(!frmForm) return false;

	var objVar = document.createElement('input');

	if(!objVar) return false;

	objVar.setAttribute('type', 	'hidden');
	objVar.setAttribute('name', 	var_name);
	objVar.setAttribute('value', 	var_val);

	frmForm.appendChild(objVar);
	if(submit)
		frmForm.submit();

	return false;
}

function Confirm(msg)
{
	if(confirm(msg,'title'))
		return true;
	else
		return false;
}
function PopUp(url,width,height,form_name)
{
	if(!width) width = 600;
	if(!height) height = 450;
	if(!form_name) form_name = 'zbx_popup';

	var left = (screen.width-(width+150))/2; 
	var top = (screen.height-(height+150))/2;
	
	var popup = window.open(url,form_name,'width=' + width +',height=' + height + ',top='+ top +',left='+ left +
			',resizable=yes,scrollbars=yes,location=no,menubar=no');

	popup.focus();
	
	return false;
}

function CheckAll(form_name, chkMain, shkName)
{
	var frmForm = document.forms[form_name];
	var value = frmForm.elements[chkMain].checked;
	for (var i=0; i < frmForm.length; i++)
	{
		name = frmForm.elements[i].name.split('[')[0];
		if(frmForm.elements[i].type != 'checkbox') continue;
		if(name == chkMain) continue;
		if(shkName && shkName != name) continue;
		if(frmForm.elements[i].disabled == true) continue;
		frmForm.elements[i].checked = value;
	}
}

function GetSelectedText(obj)
{
	if (navigator.appName == "Microsoft Internet Explorer")
	{
		obj.focus();
		return document.selection.createRange().text;
	}
	else (obj.selectionStart)
	{
		if(obj.selectionStart != obj.selectionEnd) {
			var s = obj.selectionStart;
			var e = obj.selectionEnd;
			return obj.value.substring(s, e);
		}
	}
	return obj.value;
}

function ScaleChartToParenElement(obj_name)
{
	var obj = document.getElementsByName(obj_name);

	if(obj.length <= 0) throw "Can't find objects with name [" + obj_name +"]";

	for(i = obj.length-1; i>=0; i--)
	{
		obj[i].src += "&width=" + (obj[i].parentNode.offsetWidth - obj[i].parentNode.offsetLeft - 10);
	}
}
