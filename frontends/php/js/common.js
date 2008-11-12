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
var agt = navigator.userAgent.toLowerCase();

var OP = (agt.indexOf("opera") != -1) && window.opera;
var IE = (agt.indexOf("msie") != -1) && document.all && !OP;
var IE8 = (agt.indexOf("msie 8.0") != -1) && document.all && !OP;
var IE7 = IE && !IE8 && document.all && !OP;
var SF = (agt.indexOf("safari") != -1);
var KQ = (agt.indexOf("khtml") != -1) && (!SF);
var GK = (agt.indexOf("gecko") != -1) && !KQ && !SF;
var MC = (agt.indexOf('mac') != -1)

function checkBrowser(){
	if(OP) alert('Opera');
	if(IE) alert('IE');
	if(IE7) alert('IE7');
	if(IE8) alert('IE8');
	if(SF) alert('Safari');
	if(KQ) alert('Konqueror');
	if(MC) alert('Mac');
	if(GK) alert('FireFox');
return 0;
}

function empty(obj){
	if(is_null(obj)) return true;
	if(obj == false) return true;
	if((obj == 0) || (obj == '0')) return true;
	if(is_string(obj) && (obj == '')) return true;
	if(is_array(obj) && (obj.length == 0)) return true;
return false;
}

function is_null(obj){
	if(obj==null) return true;
return false;
}

function is_number(obj){
	return (typeof(obj) == 'number');
}

function is_string(obj){
	return (typeof(obj) == 'string');
}

function is_array(obj) {
	return obj != null && typeof obj == "object" &&
      'splice' in obj && 'join' in obj;	  
}

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

function SDI(msg){
	var div_help = document.getElementById('div_help');

	if((div_help == 'undefined') || empty(div_help)){
		var div_help = document.createElement('div');
		var doc_body = document.getElementsByTagName('body')[0];
		doc_body.appendChild(div_help);
		
		div_help.setAttribute('id','div_help');
		div_help.setAttribute('style','position: absolute; left: 10px; top: 10px; border: 1px red solid; width: 500px; height: 400px; background-color: white; overflow: auto; z-index: 20;');
		
		new Draggable(div_help,{});
	}
	
	div_help.appendChild(document.createTextNode("DEBUG INFO: "));
	div_help.appendChild(document.createElement("br"));
	div_help.appendChild(document.createTextNode(msg));
	div_help.appendChild(document.createElement("br"));
	div_help.appendChild(document.createElement("br"));
}

/*
function SDI(msg){
	alert("DEBUG INFO: " + msg);
}
//*/

function SDJ(obj){
	var debug = '';
	for(var key in obj) {
		var value = obj[key];
		debug+=key+': '+value+'\n';
	}
	SDI('\n'+debug);
}

function addListener(element, eventname, expression, bubbling){
	bubbling = bubbling || false;
		
	if(window.addEventListener)	{
		element.addEventListener(eventname, expression, bubbling);
		return true;
	} 
	else if(window.attachEvent) {
		element.attachEvent('on'+eventname, expression);
		return true;
	} 
	else return false;
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

	document.write('<img src="'+url+'" alt="graph">');
}

function resizeiframe(id){
	id = id || 'iframe';
	var iframe = document.getElementById(id);
	var indoc = (IE)?iframe.contentWindow.document:iframe.contentDocument;
	if(typeof(indoc) == 'undefined') return;
	var height = parseInt(indoc.getElementsByTagName('body')[0].scrollHeight);
	var height2 = parseInt(indoc.getElementsByTagName('body')[0].offsetHeight);
	
	if(height2 < height){
		height = height2;
	}

	iframe.style.height = (height)+'px';
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

function cancelEvent(e){
	if (!e) var e = window.event;	
//SDI(e);
	if(e){
		if(IE){
			e.cancelBubble = true;
			e.returnValue = false;
		}
		else{
			e.stopPropagation();
			e.preventDefault();
		}
	}
return false;
}

function deselectAll(){
	if(IE){
		document.selection.empty();
	}
	else if(!KQ){	
		var sel = window.getSelection();
		sel.removeAllRanges();
	}
}

function ShowHide(obj,style){
	if(typeof(style) == 'undefined')
		var style = 'inline';
	if(is_string(obj))
		obj = document.getElementById(obj);
	if(!obj){
		throw 'ShowHide(): Object not foun.';
		return false;
	}

	if(obj.style.display != 'none'){
		obj.style.display = 'none';
		return 0;
	}
	else{
		obj.style.display = style;
		return 1;
	}
return false;
}

function switchElementsClass(obj,class1,class2){
	obj = $(obj);
	if(!obj) return false;

	if(obj.className == class1){
		obj.className = class2;
		return class2;
	}
	else{
		obj.className = class1;
		return class1;
	}
return false;
}

function eventTarget(e){
	var targ = false;
	
	if (!e) var e = window.event;
	if (e.target) targ = e.target;
	else if (e.srcElement) targ = e.srcElement;
	
// defeat Safari bug
	if (targ.nodeType == 3) targ = targ.parentNode;
	
return targ;
}

function getPosition(obj){
	var pos = {top: 0, left: 0};
	if(typeof(obj.offsetParent) != 'undefined') {
		pos.left = obj.offsetLeft;
		pos.top = obj.offsetTop;
		while (obj = obj.offsetParent) {
			pos.left += obj.offsetLeft;
			pos.top += obj.offsetTop;
		}
	}
return pos;
}

function remove_childs(form_name,rmvbyname,tag){
	tag = tag.toUpperCase();
	var frmForm = document.forms[form_name];
	for (var i=0; i < frmForm.length; i++){
		if(frmForm.elements[i].type != 'checkbox') continue;
		if(frmForm.elements[i].disabled == true) continue;
		if(frmForm.elements[i].checked != true) continue;
		
		var splt = frmForm.elements[i].name.split('[');
		var name = splt[0];
		var serviceid = splt[1];

		if(rmvbyname && rmvbyname != name) continue;
//		if(frmForm.elements[i].name != rmvbyname+'['+serviceid+'[serviceid]') continue;

		remove_element(frmForm.elements[i],tag);
		i--;
	}
}

function remove_element(elmnt,tag){
	elmnt = $(elmnt);
	if(!is_null(elmnt)){
		if(('undefined' != typeof(elmnt.nodeName)) && (elmnt.nodeName.toLowerCase() == tag.toLowerCase())){
			elmnt.parentNode.removeChild(elmnt);
		} 
		else if(elmnt.nodeType == 9){
			return false;
		} 
		else {
			remove_element(elmnt.parentNode,tag);
		}
	}
return true;
}