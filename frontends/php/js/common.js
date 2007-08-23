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
var OP = window.opera?true:false;
var IE = ((!OP) && (document.all))?true:false;

if (!Array.prototype.forEach)
{
  Array.prototype.forEach = function(fun /*, thisp*/)
  {
    var len = this.length;
    if (typeof fun != "function")
      throw new TypeError();

    var thisp = arguments[1];
    for (var i = 0; i < len; i++)
    {
      if (i in this)
        fun.call(thisp, this[i], i, this);
    }
  };
}

function SDI(msg)
{
	alert("DEBUG INFO: " + msg);
}

function close_window()
{
	window.setTimeout("window.close()", 500); /* Solve bug for Internet Explorer */
	return false;
}

function add_variable(o_el, s_name, x_value, s_formname, o_document)
{
	var form;

	if(!o_document)	o_document = document;

	if(s_formname)
	{
		if( !(form = o_document.forms[s_formname]) )
			 throw "Missed form with name '"+s_formname+"'.";
	}
	else if(o_el)
	{
		
		if( !(form = o_el.form) )
			throw "Missed form in 'o_el' object";
	}
	else
	{
		if( !(form = this.form) )
			throw "Missed form in 'this' object";
	}

        var o_variable = o_document.createElement('input');

	if( !o_variable )	throw "Can't create element";

        o_variable.type = 'hidden';
        o_variable.name = s_name;
        o_variable.value = x_value;

        form.appendChild(o_variable);

        return true;
}

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

function Confirm(msg){
	if(confirm(msg,'title'))
		return true;
	else
		return false;
}

function PopUp(url,width,height,form_name){
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

function CheckAll(form_name, chkMain, shkName){
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

function insert_sizeable_graph(url)
{
	var width;

	if(document.body.clientWidth)
		width = document.body.clientWidth;
	else if(document.width)
		width = document.width;

	if(width) url += "&amp;width=" + (width - 108);

	document.write("<IMG SRC=\"" + url + "\">");
}

function openWinCentered(loc, winname, iwidth, iheight, params){
		tp=Math.ceil((screen.height-iheight)/2);
		lf=Math.ceil((screen.width-iwidth)/2);
		if (params.length > 0){
			params = ', ' + params;
		}

	var WinObjReferer = window.open(loc,winname,"width="+iwidth+",height="+iheight+",top="+tp+",left="+lf+params);
	WinObjReferer.focus();
}

function isset(obj){
	return (typeof(obj) != 'undefined');
}

function empty(obj){
	if(isset(obj) && obj) return true;
	return false;
}

function is_number(obj){
	return (typeof(obj) == 'number');
}

function is_string(obj){
	return (typeof(obj) == 'string');
}