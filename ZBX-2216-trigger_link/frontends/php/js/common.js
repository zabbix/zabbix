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
// javascript document
var agt = navigator.userAgent.toLowerCase();
var OP = (agt.indexOf("opera") != -1) && window.opera;
var IE = (agt.indexOf("msie") != -1) && document.all && !OP;
var IE8 = (agt.indexOf("msie 8.0") != -1) && document.all && !OP;
var IE7 = IE && !IE8 && document.all && !OP;
var IE6 = (agt.indexOf("msie 6.0") != -1) && document.all && !OP;
var CR = (agt.indexOf("chrome") != -1);
var SF = (agt.indexOf("safari") != -1) && !CR;
var WK = (agt.indexOf("applewebkit") != -1);
var KQ = (agt.indexOf("khtml") != -1) && !WK;
var GK = (agt.indexOf("gecko") != -1) && !KQ && !WK;
var MC = (agt.indexOf("mac") != -1)

function checkBrowser(){
 if(OP) alert('Opera');
 if(IE) alert('IE');
 if(IE6) alert('IE6');
 if(IE7) alert('IE7');
 if(IE8) alert('IE8');
 if(CR) alert('Chrome');
 if(SF) alert('Safari');
 if(WK) alert('Apple Webkit');
 if(KQ) alert('Konqueror');
 if(MC) alert('Mac');
 if(GK) alert('FireFox');
return 0;
}

function isset(key, obj){
	if(typeof(obj[key]) != 'undefined') return true;
return false;
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
	if(isNaN(obj)) return false;
	if(typeof(obj) === 'number') return true;
return false;
}

function is_string(obj){
	return (typeof(obj) === 'string');
}

function is_array(obj) {
	return (obj != null) && (typeof obj == "object") && ('splice' in obj) && ('join' in obj);
}

function SDI(msg){
	var div_help = document.getElementById('div_help');

	if((div_help == 'undefined') || empty(div_help)){
		var div_help = document.createElement('div');
		var doc_body = document.getElementsByTagName('body')[0];
		doc_body.appendChild(div_help);
		
		div_help.setAttribute('id','div_help');
		div_help.setAttribute('style','position: absolute; right: 100px; top: 10px; border: 1px red solid; width: 500px; height: 400px; background-color: white; font-size: 12px; overflow: auto; z-index: 20;');
		
//		new Draggable(div_help,{});
	}
	
	var pre = document.createElement('pre');
	pre.appendChild(document.createTextNode(msg));
	
	div_help.appendChild(document.createTextNode("DEBUG INFO: "));
	div_help.appendChild(document.createElement("br"));
	div_help.appendChild(pre);
	div_help.appendChild(document.createElement("br"));
	div_help.appendChild(document.createElement("br"));
	
	div_help.scrollTop = div_help.scrollHeight;

}

function INFO(msg){
	var div_info = document.getElementById('div_info');

	if((div_info == 'undefined') || empty(div_info)){
		var div_info = document.createElement('div');
		var doc_body = document.getElementsByTagName('body')[0];
		doc_body.appendChild(div_info);
		
		div_info.setAttribute('id','div_info');
		div_info.setAttribute('style','position: absolute; left: 20px; top: 20px; height: 200px; width: 480px; border: 1px grey solid; background-color: white; font-size: 12px; overflow: auto; z-index: 20;');
	}
	
	var pre = document.createElement('pre');
	pre.appendChild(document.createTextNode(msg));
	
	div_info.appendChild(document.createTextNode("INFO: "));
	div_info.appendChild(document.createElement("br"));
	div_info.appendChild(pre);
	div_info.appendChild(document.createElement("br"));
	
	div_info.scrollTop = div_info.scrollHeight;
}

function SDJ(obj){
	var debug = '';
//	debug = obj.toSource();
//	SDI(debug);
//return null;

	for(var key in obj){
		var value = obj[key];
		debug+=key+': '+value+'  key: '+typeof(key)+'\n';
	}
	SDI(debug);
}

/// Alpha-Betic sorting
function addListener(element, eventname, expression, bubbling){
	var bubbling = bubbling || false;

	element = $(element);
	if(element.addEventListener){
		element.addEventListener(eventname, expression, bubbling);
		return true;
	} 
	else if(element.attachEvent){
		element.attachEvent('on'+eventname, expression);
		return true;
	} 
	else return false;
}

function add_variable(o_el, s_name, x_value, s_formname, o_document){
	var form;
	
	if(!o_document)	o_document = document;
	
	if(s_formname){
		if( !(form = o_document.forms[s_formname]) )
			 throw "Missing form with name '"+s_formname+"'.";
	}
	else if(o_el){
		if( !(form = o_el.form) )
			throw "Missing form in 'o_el' object";
	}
	else{
		if( !(form = this.form) )
			throw "Missing form in 'this' object";
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

function checkAll(form_name, chkMain, shkName){
	var frmForm = document.forms[form_name];
	var value = frmForm.elements[chkMain].checked;

	chkbxRange.checkAll(shkName, value);
return true;
}

function checkLocalAll(form_name, chkMain, chkName){
	var frmForm = document.forms[form_name];

	var checkboxes = $$('input[name='+chkName+']');
	for(var i=0; i<checkboxes.length; i++){
		if(isset('type', checkboxes[i]) && (checkboxes[i].type == 'checkbox')){
			checkboxes[i].checked = frmForm.elements[chkMain].checked;
		}
	}

return true;
}

function clearAllForm(form){
	form = $(form);
	var count = 0;
	
	var inputs = form.getElementsByTagName('input');
	for(var i=0; i<inputs.length;i++){
		var type = inputs[i].getAttribute('type');

		switch(type){
			case 'button':
			case 'hidden':
			case 'submit':
				break;
			case 'checkbox':
				inputs[i].checked = false;
				break;
			case 'text':
			case 'password':
			default:
				inputs[i].value = '';
		}
	}

	var selects = form.getElementsByTagName('select');
	for(var i=0; i<selects.length;i++){
		selects[i].selectedIndex = 0;
	}
	
	var areas = form.getElementsByTagName('textarea');
	for(var i=0; i<areas.length;i++){
		areas[i].innerHTML = '';
	}

return true;
}

function close_window(){
	
	window.setTimeout('window.close();', 500); /* Solve bug for Internet Explorer */
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
		objVar.setAttribute('type',	'hidden');
		
		if(!objVar) return false;

		frmForm.appendChild(objVar);
		
		objVar.setAttribute('name',	var_name);
		objVar.setAttribute('id',	var_name);
	}

	if(is_null(var_val)){
		objVar.parentNode.removeChild(objVar);
	}
	else{
		objVar.value = var_val;
	}
	
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

function eventTarget(e){
	var targ = false;

	if (!e) var e = window.event;
	if (e.target) targ = e.target;
	else if (e.srcElement) targ = e.srcElement;

// defeat Safari bug
	if (targ.nodeType == 3) targ = targ.parentNode;

return targ;
}

function getDimensions(obj, trueSide){
	obj = $(obj);
	
	if(typeof(trueSide) == 'undefined') trueSide = false;
	
	var dim = {
		'left':		0,
		'top':		0,
		'right':	0,
		'bottom':	0,
		'width':	0,
		'height':	0
	}

	if(!is_null(obj) && (typeof(obj.offsetParent) != 'undefined')){
		var dim = {
			'left':		parseInt(obj.style.left,10),
			'top':		parseInt(obj.style.top,10),
			'right':	parseInt(obj.style.right,10),
			'bottom':	parseInt(obj.style.bottom,10),
			'width':	parseInt(obj.style.width,10),
			'height':	parseInt(obj.style.height,10)
		}

		if(!is_number(dim.top)) dim.top = parseInt(obj.offsetTop,10);
		if(!is_number(dim.left)) dim.left = parseInt(obj.offsetLeft,10);
		if(!is_number(dim.width)) dim.width = parseInt(obj.offsetWidth,10);
		if(!is_number(dim.height)) dim.height = parseInt(obj.offsetHeight,10);
		
		if(!trueSide){
			dim.right = dim.left + dim.width;
			dim.bottom = dim.top + dim.height;
		}
	}
	
return dim;
}

function getParent(obj, name){
	if(obj.parentNode.nodeName.toLowerCase() == name.toLowerCase()) return obj.parentNode;
	else if(obj.parentNode.nodeName.toLowerCase() == 'body') return null;
	else return getParent(obj.parentNode, name);
}

function getPosition(obj){
	obj = $(obj);
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
		cursor.x = e.clientX + (de.scrollLeft || b.scrollLeft) - (de.clientLeft || 0);
		cursor.y = e.clientY + (de.scrollTop || b.scrollTop) - (de.clientTop || 0);
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

function insert_in_element(element_name, text){
	if(IE)
		var elems = $$('[name='+element_name+']');
	else
		var elems = document.getElementsByName(element_name);

	for(var key=0; key < elems.length; key++){
		if((typeof(elems[key]) != 'undefined') && !is_null(elems[key])){
			$(elems[key]).update(text);
		}
	}
}

function insert_sizeable_graph(graph_id,url){
	if((typeof(ZBX_G_WIDTH) != 'undefined')) url += "&amp;width="+ZBX_G_WIDTH;

	document.write('<img id="'+graph_id+'" src="'+url+'" alt="graph" /><br />');
}

function is_empty_form(id){
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

function openWinCentered(loc, winname, iwidth, iheight, params){
		var uri = new Curl(loc);
		loc = uri.getUrl();

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

function redirect(uri) {
	uri = new Curl(uri);
	var loc = uri.getUrl();
	window.location = loc;
	return false;
}

function removeListener(element, eventname, expression, bubbling){
	bubbling = bubbling || false;

	element = $(element);
	if(element.removeEventListener){
		element.removeEventListener(eventname, expression, bubbling);
		return true;
	} 
	else if(element.detachEvent){
		element.detachEvent('on'+eventname, expression);
		return true;
	} 
	else return false;
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

function showHideByName(name, style){
	if(typeof(style) == 'undefined') var style = 'none';

	var objs = $$('[name='+name+']');

	if(empty(objs)){
		throw 'ShowHide(): Object not found.';
		return false;
	}
	
	for(var i=0; i<objs.length; i++){
		var obj = objs[i];
		obj.style.display = style;
	}

}

function showHideEffect(obj, eff, time, cb_afterFinish){
	obj = $(obj);
	if(!obj){
		throw 'showHideEffect(): Object not found.';
		return false;
	}

	if(typeof(Effect) == 'undefined'){
		eff = 'none';
	}
	
	if(typeof(cb_afterFinish) == 'undefined'){
		cb_afterFinish = function(){};
	}

	var timeShow = (typeof(time) == 'undefined')?0.5:(parseInt(time)/1000);
	var show = (obj.style.display != 'none')?0:1;

	switch(eff){
		case 'blind':
			if(show)
				Effect.BlindDown(obj, { afterFinish: cb_afterFinish, duration: timeShow, queue: {position: 'end',scope: eff,limit: 2}} );
			else
				Effect.BlindUp(obj, { afterFinish: cb_afterFinish, duration: timeShow, queue: {position: 'end',scope: eff,limit: 2}} );
			break;
		case 'slide':
			if(show)
				Effect.SlideDown(obj, { afterFinish: cb_afterFinish, duration: timeShow, queue: {position: 'end',scope: eff,limit: 2}} );
			else
				Effect.SlideUp(obj, { afterFinish: cb_afterFinish, duration: timeShow, queue: {position: 'end',scope: eff,limit: 2}} );
			break;
		default:
			if(show)
				obj.show();
			else
				obj.hide();

			cb_afterFinish();
			break;
	}
return show;
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
/*									Pages stuff										*/
/************************************************************************************/
function openPage(start){
	var lnk = new Curl(location.href); 
	lnk.setArgument('start', start);
	location.href = lnk.getUrl();
	
return false;
}

function ScaleChartToParenElement(obj_name){
	var obj = document.getElementsByName(obj_name);

	if(obj.length <= 0) throw "Can't find objects with name [" + obj_name +"]";

	for(i = obj.length-1; i>=0; i--){
		obj[i].src += "&width=" + (obj[i].parentNode.offsetWidth - obj[i].parentNode.offsetLeft - 10);
	}
}


/************************************************************************************/
/*										IE 6 FIXES 									*/
/************************************************************************************/

function hidePopupDiv(iFrameID){
	if(!IE6) return;

	$(iFrameID).hide();
	$(iFrameID).remove();
}

function showPopupDiv(divID,iFrameID){
	if(!IE6) return;

	var iFrame = $(iFrameID);
	var divPopup = $(divID);

	if(is_null(iFrame)){
		var iFrame = document.createElement('iframe');
		document.body.appendChild(iFrame);
		
		//Match IFrame position with divPopup
		iFrame.setAttribute('id',iFrameID);
		iFrame.style.position='absolute';
	}
	
	if(divPopup.style.display == 'none'){
		iFrame.style.display = 'none';
		return;
	}

//Increase default zIndex of div by 1, so that DIV appears before IFrame
	divPopup.style.zIndex=divPopup.style.zIndex+1;
//	iFrame.style.zIndex = 1;


	var divCumOff = $(divID).cumulativeOffset();
	iFrame.style.display = 'block';
	iFrame.style.left = divCumOff.left + 'px';
	iFrame.style.top = divCumOff.top + 'px';
	iFrame.style.width = divPopup.offsetWidth + 'px';
	iFrame.style.height = divPopup.offsetHeight + 'px';
}