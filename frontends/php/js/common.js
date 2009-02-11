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
**/


var agt = navigator.userAgent.toLowerCase();
var OP = (agt.indexOf("opera") != -1) && window.opera;
var IE = (agt.indexOf("msie") != -1) && document.all && !OP;
var SF = (agt.indexOf("safari") != -1);
var KQ = (agt.indexOf("khtml") != -1) && (!SF);
var GK = (agt.indexOf("gecko") != -1) && !KQ && !SF;
var MC = (agt.indexOf('mac') != -1)

function checkBrowser(){
	if(OP) SDI('Opera');
	if(IE) SDI('IE');
	if(SF) SDI('Safari');
	if(KQ) SDI('Konqueror');
	if(MC) SDI('Mac');
	if(GK) SDI('FireFox');
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
		div_help.setAttribute('style','position: absolute; left: 10px; top: 100px; border: 1px red solid; width: 500px; height: 400px; background-color: white; overflow: auto; z-index: 20;');
		
//		new Draggable(div_help,{});
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

/// Alpha-Betic sorting

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

function removeListener(element, eventname, expression, bubbling){
	bubbling = bubbling || false;
	
	if(window.removeEventListener)	{
		element.removeEventListener(eventname, expression, bubbling);
		return true;
	} 
	else if(window.detachEvent) {
		element.detachEvent('on'+eventname, expression);
		return true;
	} 
	else return false;
}

function add_variable(o_el, s_name, x_value, s_formname, o_document){
	var form;
	
	if(!o_document)	o_document = document;
	
	if(s_formname){
		if( !(form = o_document.forms[s_formname]) )
			 throw "Missed form with name '"+s_formname+"'.";
	}
	else if(o_el){
		if( !(form = o_el.form) )
			throw "Missed form in 'o_el' object";
	}
	else{
		if( !(form = this.form) )
			throw "Missed form in 'this' object";
	}
	
	var o_variable = o_document.createElement('input');
	
	if( !o_variable )	throw "Can't create element";
	
	o_variable.type = 'hidden';
	o_variable.name = s_name;
	o_variable.id = s_name;
	o_variable.value = x_value;

	form.appendChild(o_variable);
	
return true;
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

function CheckAll(form_name, chkMain, shkName){
	var frmForm = document.forms[form_name];
	var value = frmForm.elements[chkMain].checked;
	for (var i=0; i < frmForm.length; i++){
		name = frmForm.elements[i].name.split('[')[0];
		if(frmForm.elements[i].type != 'checkbox') continue;
		if(name == chkMain) continue;
		if(shkName && shkName != name) continue;
		if(frmForm.elements[i].disabled == true) continue;
		frmForm.elements[i].checked = value;
	}
}

function close_window(){
	
	window.setTimeout("window.close()", 500); /* Solve bug for Internet Explorer */
	return false;
}

function Confirm(msg){
	if(confirm(msg,'title'))
		return true;
	else
		return false;
}

function create_var(form_name, var_name, var_val, subm){
	var frmForm = (is_string(form_name))?document.forms[form_name]:form_name;
	if(!frmForm) return false;

	var objVar = (typeof(frmForm[var_name]) != 'undefined')?frmForm[var_name]:null;
//	objVar=(objVar.length>0)?objVar[0]:null;

	if(is_null(objVar)){
		objVar = document.createElement('input');
		objVar.setAttribute('type', 	'hidden');
		
		if(!objVar) return false;

		frmForm.appendChild(objVar);
		
		objVar.setAttribute('name', 	var_name);
		objVar.setAttribute('id', 		var_name);
	}

	objVar.value = var_val;
	
	if(subm)
		frmForm.submit();

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

function empty_form(id){
	id = $(id);
	var count = 0;
	
	var inputs = id.getElementsByTagName('input');
	for(var i=0; i<inputs.length;i++){
		if((inputs[i].type == 'text') && (typeof(inputs[i].hidden) == 'undefined') && !empty(inputs[i].value)) return false;
		if((inputs[i].type == 'checkbox') && (inputs[i].checked)) return false;
	}
	
	var selects = id.getElementsByTagName('select');
	for(var i=0; i<selects.length;i++){
		if((typeof(selects[i].hidden) == 'undefined') && (selects[i].selectedIndex)) return false;
	}
	
return true;
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
	if(!is_null(obj) && (typeof(obj.offsetParent) != 'undefined')){
		pos.left = obj.offsetLeft;
		pos.top = obj.offsetTop;
		try{
			while(!is_null(obj.offsetParent)){
				obj=obj.offsetParent;
				pos.left += obj.offsetLeft;
				pos.top += obj.offsetTop;
				
				if(IE && (obj.offsetParent.toString() == 'unknown')){
//					alert(obj.offsetParent.toString());
					break;
				}
			}
		} catch(e){
		}
	}
return pos;
}

function getSelectedText(obj){
	if(IE){
		obj.focus();
		return document.selection.createRange().text;
	}
	else if(obj.selectionStart){
		if(obj.selectionStart != obj.selectionEnd) {
			var s = obj.selectionStart;
			var e = obj.selectionEnd;
			return obj.value.substring(s, e);
		}
	}
	return obj.value;
}


function get_bodywidth(){
	var w = parseInt(document.body.scrollWidth);
	var w2 = parseInt(document.body.offsetWidth);

	if(KQ){
		w = (w2 < w)?w2:w;
		w-=16;
	}
	else{
		w = (w2 > w)?w2:w;
	}
return w;
}


function get_cursor_position(e){
	e = e || window.event;
	var cursor = {x:0, y:0};
	if(e.pageX || e.pageY){
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

function get_scroll_pos(){
	var scrOfX = 0, scrOfY = 0;
//Netscape compliant
	if( typeof( window.pageYOffset ) == 'number' ){
		scrOfY = window.pageYOffset;
		scrOfX = window.pageXOffset;
	}
//DOM compliant
	else if( document.body && ( document.body.scrollLeft || document.body.scrollTop ) ){
		scrOfY = document.body.scrollTop;
		scrOfX = document.body.scrollLeft;
	}
//IE6 standards compliant mode
	else if( document.documentElement && ( document.documentElement.scrollLeft || document.documentElement.scrollTop ) ){
		scrOfY = document.documentElement.scrollTop;
		scrOfX = document.documentElement.scrollLeft;
	}
	return [ scrOfX, scrOfY ];
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

function redirect(url) {
	window.location = url;
	return false;
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

function onload_update_scroll(id,w,period,stime,timel,bar_stime){
	var obj = $(id);
	if((typeof(obj) == 'undefined') || is_null(obj)){
		setTimeout('onload_update_scroll("'+id+'",'+w+','+period+','+stime+','+timel+','+bar_stime+');',1000);
		return;
	}
//	eval('var fnc = function(){ onload_update_scroll("'+id+'",'+w+','+period+','+stime+','+timel+','+bar_stime+');}');
	scrollinit(w,period,stime,timel,bar_stime);
	if(!is_null($('scroll')) && showgraphmenu){
		showgraphmenu(id);
	}
//	addListener(window,'resize', fnc );
}

function ShowHide(obj,style){
	if(typeof(style) == 'undefined') var style = 'inline';
	
	if(is_string(obj))
		obj = document.getElementById(obj);
		
	if(!obj){
		throw 'ShowHide(): Object not found.';
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


/************************************************************************************/
/*										 Pages stuff								*/
/************************************************************************************/
function ScaleChartToParenElement(obj_name){
	var obj = document.getElementsByName(obj_name);

	if(obj.length <= 0) throw "Can't find objects with name [" + obj_name +"]";

	for(i = obj.length-1; i>=0; i--){
		obj[i].src += "&width=" + (obj[i].parentNode.offsetWidth - obj[i].parentNode.offsetLeft - 10);
	}
}

function insert_sizeable_graph(graph_id,url){
	if((typeof(ZBX_G_WIDTH) != 'undefined')) url += "&amp;width="+ZBX_G_WIDTH;

	document.write('<img id="'+graph_id+'" src="'+url+'" alt="graph" /><br />');
}

/************************************************************************************/
/*									MAIN MENU stuff									*/
/************************************************************************************/

var MMenu = {
menus:			{'empty': 0, 'view': 0, 'cm': 0, 'reports': 0, 'config': 0, 'admin': 0},
def_label:		null,
sub_active: 	false,
timeout: 		null,

mouseOver: function(show_label){
	clearTimeout(this.timeout);
	MMenu.showSubMenu(show_label);
},

submenu_mouseOver: function(){
	clearTimeout(this.timeout);
},

mouseOut: function(){
	this.timeout = setTimeout('MMenu.showSubMenu("'+this.def_label+'")', 2000);
},

showSubMenu: function(show_label){
	var menu_div  = $('sub_'+show_label);
	if(!is_null(menu_div)){
		$(show_label).className = 'active';
		menu_div.show();
		for(var key in this.menus){
			if(key == show_label) continue;

			var menu_cell = $(key);
			if(!is_null(menu_cell)) menu_cell.className = '';
			
			var sub_menu_cell = $('sub_'+key);
			if(!is_null(sub_menu_cell)) sub_menu_cell.hide();
		}
	}
}
}
