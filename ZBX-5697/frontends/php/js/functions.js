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
**
*/
// JavaScript Document

/**********************************************************************************************************************/
//	Trigger log expression
/**********************************************************************************************************************/
var logexpr_count = 0;
var key_count = 0;

function nextObject(n) {
	var t = n.parentNode.tagName;
	do{
		n = n.nextSibling;
	} while (n && n.nodeType != 1 && n.parentNode.tagName == t);

return n;
}

function previousObject(p) {
	var t = p.parentNode.tagName;
	do{
		p = p.previousSibling;
	} while (p && p.nodeType != 1 && p.parentNode.tagName == t);

return p;
}

function call_ins_macro_menu(ev){
	show_popup_menu(ev,
					[
						[locale['S_INSERT_MACRO'],null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['TRIGGER.VALUE=0', 'javascript: set_macro(0);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE=1', 'javascript: set_macro(1);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE=2', 'javascript: set_macro(2);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#0', 'javascript: set_macro(10);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#1', 'javascript: set_macro(11);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['TRIGGER.VALUE#2', 'javascript: set_macro(12);',
						 null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
					],150);
	return false;
}

function call_triggerlog_menu(evnt,id,name, menu_options){
 	var tname = locale['S_CREATE_LOG_TRIGGER'];

	if(typeof(menu_options) != 'undefined'){
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						[tname, "javascript: openWinCentered('tr_logform.php?sform=1&itemid="+id+"','TriggerLog',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');",{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						menu_options
					],240);
	}
	else {
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						[tname, "javascript: openWinCentered('tr_logform.php?sform=1&itemid="+id+"','ServiceForm',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');", {'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]
					],140);
	}
return false;
}


function add_logexpr(){
	var REGEXP_INCLUDE = 0;
	var REGEXP_EXCLUDE = 1;
	try{
		var expr = document.getElementById('logexpr');
		var expr_t = document.getElementById('expr_type');
		var bt_and = document.getElementById('add_key_and');
		var bt_or = document.getElementById('add_key_or');
		var iregexp = document.getElementById('iregexp');
	}
	catch(e){
		throw('Error: '+(IE?e.description:e));
	}

	var ex = bt_and.disabled ? '|' : '&';
	var ex_v = bt_and.disabled ? ' OR ' : ' AND ';
	if (expr_t.value == REGEXP_EXCLUDE) {
		ex = bt_and.disabled ? '&' : '|';
	}

	var expression = '';
	var expr_v = '';
	var lp;
	for (lp = 0; lp < key_count; lp++) {
		var key = document.getElementsByName('keys['+lp+'][value]')[0];
		var typ = document.getElementsByName('keys['+lp+'][type]')[0];
		if((typeof(key) != 'undefined') && (typeof(typ) != 'undefined')) {
			if (expression != '') {
				expression += ex;
				expr_v += ex_v;
			}
			expression += typ.value + '(' + key.value + ')';
			expr_v += typ.value + '(' + key.value + ')';
			remove_keyword('keytr'+lp);
		}

	}

	if (typeof(expr.value) != 'undefined' && expr.value != '') {
		if (expression != '') {
			expression += ex;
			expr_v += ex_v;
		}
		expression += iregexp.checked ? 'iregexp' : 'regexp';
		expression += '(' + expr.value + ')';
		expr_v += iregexp.checked ? 'iregexp' : 'regexp';
		expr_v += '(' + expr.value + ')';
	}

	if(expression == '') return false;

	var classattr = (IE)?'className':'class';


	var tr = document.createElement('tr');
	document.getElementById('exp_list').firstChild.appendChild(tr);

	tr.setAttribute(classattr,'even_row');
	tr.setAttribute('id','logtr'+logexpr_count);


	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(expr_v));

	var input = (IE)?document.createElement('<input name="expressions['+logexpr_count+'][value]" />'):document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',expression);
	(!IE)?input.setAttribute('name','expressions['+logexpr_count+'][value]'):'';

	td.appendChild(input);

	var input = (IE)?document.createElement('<input name="expressions['+logexpr_count+'][view]" />'):document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',expr_v);
	(!IE)?input.setAttribute('name','expressions['+logexpr_count+'][view]'):'';

	td.appendChild(input);

	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(expr_t.options[expr_t.selectedIndex].text));

	var input = (IE)?document.createElement('<input name="expressions['+logexpr_count+'][type]" />'):document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',expr_t.value);
	(!IE)?input.setAttribute('name','expressions['+logexpr_count+'][type]'):'';

	td.appendChild(input);

// optional
	var td = document.createElement('td');
	tr.appendChild(td);

	td.setAttribute((IE)?'cssText':'style','white-space: nowrap;');

	var img = document.createElement('img');
	img.setAttribute('src','images/general/arrowup.gif');
	img.setAttribute('border','0');
	img.setAttribute('alt','up');

	var url = document.createElement('a');
	url.setAttribute('href','javascript:  element_up("logtr'+logexpr_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(img);

	td.appendChild(url);

	td.appendChild(document.createTextNode(' '));

	var img = document.createElement('img');
	img.setAttribute('src','images/general/arrowdown.gif');
	img.setAttribute('border','0');
	img.setAttribute('alt','down');

	var url = document.createElement('a');
	url.setAttribute('href','javascript:  element_down("logtr'+logexpr_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(img);

	td.appendChild(url);


	var td = document.createElement('td');
	tr.appendChild(td);

	var url = document.createElement('a');
	url.setAttribute('href','javascript: if(confirm("'+locale['S_DELETE_EXPRESSION_Q']+'")) remove_expression("logtr'+logexpr_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(document.createTextNode(locale['S_DELETE']));

	td.appendChild(url);

	logexpr_count++;
	expr.value = '';
	expr_t.selectedIndex=0;
	bt_and.disabled = false;
	bt_or.disabled = false;
}

function remove_expression(expr_id){
	var expr_tr = document.getElementById(expr_id);
	var id = getIdFromNodeId(expr_id);
	if(is_number(id)){
		var elm_v = document.getElementsByName('expressions['+id+'][value]')[0];
		var elm_t = document.getElementsByName('expressions['+id+'][type]')[0];
		var elm_s = document.getElementsByName('expressions['+id+'][view]')[0];

		if(typeof(elm_v) != 'undefined') elm_v.parentNode.removeChild(elm_v);
		if(typeof(elm_t) != 'undefined') elm_t.parentNode.removeChild(elm_t);
		if(typeof(elm_s) != 'undefined') elm_s.parentNode.removeChild(elm_s);
	}
	if(typeof(expr_tr) != 'undefined'){
		expr_tr.parentNode.removeChild(expr_tr);
	}
}

function getIdFromNodeId(id){
	if(typeof(id)=='string'){
		var reg = /logtr([0-9])/i;
		id = parseInt(id.replace(reg,"$1"));
	}
	if(typeof(id)=='number') return id;
//	var elm = document.getElementsByName('expressions['+id+'][value]')[0];

return null;
}

function element_up(elementid){
	var c_obj = document.getElementById(elementid);
	var p_obj = c_obj.parentNode;

	if(typeof(p_obj) == 'undefined') return;

	var c2_obj = previousObject(c_obj);
	if(c2_obj && c2_obj.id.length > 0){
		swapNodes(c2_obj,c_obj);
		swapNodesNames(c2_obj,c_obj);
	}
}

function element_down(elementid){
	var c_obj = document.getElementById(elementid);
	var p_obj = c_obj.parentNode;

	if(typeof(p_obj) == 'undefined') return;

	var c2_obj = nextObject(c_obj);
	if(c2_obj && (c2_obj.id.length > 0)){
		swapNodes(c_obj,c2_obj);
		swapNodesNames(c_obj,c2_obj);
	}
}


function swapNodes(n1, n2){
	var p1,p2,b;

	if((p1 = n1.parentNode) && (p2 = n2.parentNode)){
		b = nextObject(n2);
		if(n1 == b) return;

		p1.replaceChild(n2, n1); // new,old
		if(b){
// n1 - the node which we insert
// b - the node before which we insert 
			p2.insertBefore(n1, b);
		}
		else {
			p2.appendChild(n1);
		}
	}
}

function swapNodesNames(n1,n2){
	var id1 = n1.id;
	var id2 = n2.id;
	if(is_string(id1) && is_string(id2)){
		var reg = /logtr([0-9])/i;
		id1 = parseInt(id1.replace(reg,"$1"));
		id2 = parseInt(id2.replace(reg,"$1"));
	}

	if(is_number(id1) && is_number(id2)){
		var elm = new Array();
		elm[0] = document.getElementsByName('expressions['+id1+'][value]')[0];
		elm[1] = document.getElementsByName('expressions['+id1+'][type]')[0];
		elm[2] = document.getElementsByName('expressions['+id1+'][view]')[0];
		elm[3] = document.getElementsByName('expressions['+id2+'][value]')[0];
		elm[4] = document.getElementsByName('expressions['+id2+'][type]')[0];
		elm[5] = document.getElementsByName('expressions['+id2+'][view]')[0];

//		alert(elm[1].parentNode.tagName);
//		alert(elm[3].name);

		swapNodes(elm[0],elm[3]);
		swapNodes(elm[1],elm[4]);
		swapNodes(elm[2],elm[5]);

		return true;
	}

return false;
}

function closeForm(page) {
	try {
		// set header confirmation message to opener
		var msg = IE ? document.getElementById('page_msg').innerText : document.getElementById('page_msg').textContent;
		window.opener.location.replace(page + '?msg=' + encodeURI(msg));
	}
	catch(e) {
		zbx_throw(e);
	}

	if (IE) {
		// close current popup after 1s, wait when opener window is refreshed (IE7 issue)
		window.setTimeout(function() {window.self.close()}, 1000);
	}
	else {
		window.self.close();
	}
}

function add_keyword(bt_type){
	try{
		var expr = document.getElementById('logexpr');
		var iregexp = document.getElementById('iregexp');
		var cb = document.getElementById(bt_type == 'and' ?  'add_key_or' : 'add_key_and');
	}
	catch(e){
		throw('Error: '+(IE?e.description:e));
		return false;
	}

	if(typeof(expr.value) == 'undefined' || expr.value == '') return false;

	cb.disabled = true;

	var classattr = (IE)?'className':'class';

	var tr = document.createElement('tr');
	document.getElementById('key_list').firstChild.appendChild(tr);

	tr.setAttribute(classattr,'even_row');
	tr.setAttribute('id','keytr'+key_count);

	// keyword
	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(expr.value));

	var input = (IE)?document.createElement('<input name="keys['+key_count+'][value]" />'):document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',expr.value);
	(!IE)?input.setAttribute('name','keys['+key_count+'][value]'):'';

	td.appendChild(input);

	// type
	var td = document.createElement('td');
	tr.appendChild(td);

	td.appendChild(document.createTextNode(iregexp.checked ? 'iregexp' : 'regexp'));

	var input = (IE)?document.createElement('<input name="keys['+key_count+'][type]" />'):document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',iregexp.checked ? 'iregexp' : 'regexp');
	(!IE)?input.setAttribute('name','keys['+key_count+'][type]'):'';

	td.appendChild(input);

	// delete
	var td = document.createElement('td');
	tr.appendChild(td);

	var url = document.createElement('a');
	url.setAttribute('href','javascript: if(confirm("'+locale['S_DELETE_KEYWORD_Q']+'")) remove_keyword("keytr'+key_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(document.createTextNode(locale['S_DELETE']));

	td.appendChild(url);

	key_count++;
	expr.value = '';
}

function add_keyword_and(){
	add_keyword('and');
}

function add_keyword_or(){
	add_keyword('or');
}

function getIdFromNodeKeyId(id) {
	if(typeof(id)=='string'){
		var reg = /keytr([0-9])/i;
		id = parseInt(id.replace(reg,"$1"));
	}
	if(typeof(id)=='number') return id;

	return null;
}

function remove_keyword(key_id){
	var key_tr = document.getElementById(key_id);
	var id = getIdFromNodeKeyId(key_id);
	if(is_number(id)){
		var elm_v = document.getElementsByName('keys['+id+'][value]')[0];
		var elm_t = document.getElementsByName('keys['+id+'][type]')[0];

		if(typeof(elm_v) == 'undefined') elm_v.parentNode.removeChild(elm_v);
		if(typeof(elm_t) == 'undefined') elm_t.parentNode.removeChild(elm_t);
	}
	if(typeof(key_tr) != 'undefined'){
		key_tr.parentNode.removeChild(key_tr);
	}

	var lp;
	var bData = false;
	for (lp = 0; lp < key_count; lp++) {
		var elm_v = document.getElementsByName('keys['+lp+'][value]')[0];
		if(typeof(elm_v) != 'undefined') bData = true;
	}
	if (!bData) {
		var bt_and = document.getElementById('add_key_and');
		var bt_or = document.getElementById('add_key_or');
		if (typeof(bt_and) != 'undefined') bt_and.disabled = false;
		if (typeof(bt_or) != 'undefined') bt_or.disabled = false;
	}
}

function check_target(e){
	var targets = document.getElementsByName('expr_target_single');
	for (var i = 0; i < targets.length; ++i) {
		targets[i].checked = targets[i] == e;
	}
}

function delete_expression(expr_id){
	document.getElementsByName('remove_expression')[0].value = expr_id;
}

function copy_expression(id){
	var expr_temp = document.getElementsByName('expr_temp')[0];
	if (expr_temp.value.length > 0 && !confirm(locale['DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q'])) return;

	var src = document.getElementById(id);
	if (typeof src.textContent != 'undefined') expr_temp.value = src.textContent;
	else expr_temp.value = src.innerText;
}

function set_macro(v){
	var expr_temp = document.getElementsByName('expr_temp')[0];
	if (expr_temp.value.length > 0 && !confirm(locale['DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q'])) return;

	var sign = '=';
	if(v >= 10) {
		v %= 10;
		sign = '#';
	}

	expr_temp.value = '{TRIGGER.VALUE}' + sign + v;
}
/************************************************************************************/
/*								GRAPH RELATED STUFF 								*/
/************************************************************************************/
var graphs = {
graphtype : 0,

submit : function(obj){
	if(obj.name == 'graphtype'){
		if(((obj.selectedIndex > 1) && (this.graphtype < 2)) || ((obj.selectedIndex < 2) && (this.graphtype > 1))){
			var refr = document.getElementsByName('form_refresh');
			refr[0].value = 0;
		}
	}
	document.getElementsByName('frm_graph')[0].submit();
}
}

/************************************************************************************/
/*										SERVICES 									*/
/************************************************************************************/
function call_menu(evnt,id,name){
	if(id != 0){
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['Add Service',"javascript: openWinCentered('services_form.php?sform=1&parentid="+id+"&parentname="+name+"','ServiceForm',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['Edit Service',"javascript: openWinCentered('services_form.php?sform=1&serviceid="+id+"','ServiceForm',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');",null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						['Delete Service',"javascript: if(Confirm('Delete selected services?')){ openWinCentered('services_form.php?saction=1&delete=1&serviceid="+id+"','ServiceForm',400,300,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');}",null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]
					],120);
	} else {
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['Add Service',"javascript: openWinCentered('services_form.php?sform=1&parentid="+id+"&parentname="+name+"','ServiceForm',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]
					],120);
	}
return false;
}

function services_showsla(sla){
try{
	if(sla.checked){
		document.getElementById('SLA').style.display = 'none';
	} else {
		document.getElementById('SLA').style.display = (!IE || OP)?('table-row'):('block');
	}
} catch(e){
	alert(e);
}
}

function add_child_service(name,serviceid,trigger,triggerid){
//	alert(name+','+serviceid+','+trigger+','+triggerid);
	var tr = document.createElement('tr');
	document.getElementById('service_childs').firstChild.appendChild(tr);

	var classattr = (IE)?'className':'class';

	tr.setAttribute(classattr,'even_row');

	var td = document.createElement('td');

	var chkbx = document.createElement('input');
	chkbx.type = 'checkbox';
	chkbx.value = serviceid;
	chkbx.name = 'childs_to_del['+serviceid+'][serviceid]';

	var input = document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',serviceid);
	input.setAttribute('name','childs['+serviceid+'][serviceid]');

	td.appendChild(chkbx);
	td.appendChild(input);
	tr.appendChild(td);

	var td = document.createElement('td');

	var url = document.createElement('a');
	url.setAttribute('href','services_form.php?sform=1&serviceid='+serviceid);
	url.appendChild(document.createTextNode(name));

	var input = document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',name);
	input.setAttribute('name','childs['+serviceid+'][name]');

	td.appendChild(url);
	td.appendChild(input);
	tr.appendChild(td);

	var td = document.createElement('td');

	var chkbx = document.createElement('input');
	chkbx.type = 'checkbox';
	chkbx.value = '1';
	chkbx.name = 'childs['+serviceid+'][soft]';

	td.appendChild(chkbx);
	tr.appendChild(td);

	var td = document.createElement('td');

	var input = document.createElement('input');
	input.setAttribute('type','hidden');
	input.setAttribute('value',triggerid);
	input.setAttribute('name','childs['+serviceid+'][triggerid]');

	td.appendChild(document.createTextNode(trigger));
	td.appendChild(input);
	tr.appendChild(td);
}

function check_childs(form_name, chkMain, chkName){
	var frmForm = document.forms[form_name];
	var value = frmForm.elements[chkName].checked;

	for (var i=0; i < frmForm.length; i++){
		if(frmForm.elements[i].type != 'checkbox') continue;
		if(frmForm.elements[i].disabled == true) continue;

		var splt = frmForm.elements[i].name.split('[');
		var name = splt[0];
		var serviceid = splt[1];

		if(chkName && chkName == name) continue;
		if(chkMain && chkMain != name) continue;

		if(frmForm.elements[i].name != chkMain+'['+serviceid+'[serviceid]') continue;
		frmForm.elements[i].checked = value;
	}
}


function display_element(name){
	var elmnt = document.getElementById(name);
	if((typeof(elmnt) == 'undefined')){
		return;
	}
	else if((elmnt.offsetWidth == 0) || (elmnt.style.display == 'none')){
		elmnt.style.display = IE?'block':'table-row';
	}
	else {
		elmnt.style.display = 'none';
	}
}

//------------------------------------------------------
//					DASHBOARD JS MENU
//------------------------------------------------------

function create_page_menu(e,id){
	if(!e) var e = window.event;
	id='menu_'+id;

	var dbrd_menu = new Array();

	// to create a copy of array, but not references!!!!
	for (var i=0; i < page_menu[id].length; i++) {
		if (typeof(page_menu[id][i]) != 'undefined' && !empty(page_menu[id][i])) {
			dbrd_menu[i] = page_menu[id][i].clone();
		}
	}

	for (var i=0; i < page_submenu[id].length; i++) {
		if (typeof(page_submenu[id][i]) != 'undefined' && !empty(page_submenu[id][i])) {
			var row = page_submenu[id][i];
			var menu_row = new Array(row.name, "javascript: rm4favorites('"+row.favobj+"','"+row.favid+"','"+i+"');");
			dbrd_menu[dbrd_menu.length-1].push(menu_row);
		}
	}

	show_popup_menu(e, dbrd_menu);
}

//------------------------------------------------------
//					TRIGGERS JS MENU
//------------------------------------------------------

function create_mon_trigger_menu(e, args, items){
	var tr_menu = new Array(['Triggers',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
								['Events','events.php?triggerid='+args[0].triggerid+'&nav_time='+args[0].lastchange,null]);

	if((args.length > 1) && !is_null(args[1])) tr_menu.push(args[1]);
	if((args.length > 1) && !is_null(args[2])) tr_menu.push(args[2]);

	// getting info about types of items that we have
	var has_char_items = false;
	var has_int_items = false;

	// checking every item
	for(var itemid in items){
		// if no info about type is given
		if(!isset(itemid, items)) continue;
		if(!isset('value_type', items[itemid])) continue;

		// 1, 2, 4 - character types
		if (items[itemid].value_type == '1' || items[itemid].value_type == '2' || items[itemid].value_type == '4'){
			has_char_items = true;
		}
		// 0, 3 - numeric types
		if (items[itemid].value_type == '0' || items[itemid].value_type == '3'){
			has_int_items = true;
		}
	}

	var history_section_caption = '';
	// we have chars and numbers, or we have none (probably 'value_type' key was not set)
	if (has_char_items == has_int_items) {
		history_section_caption = locale['S_HISTORY_AND_SIMPLE_GRAPHS'];
	}
	// we have only character items, so 'history' should be shown
	else if (has_char_items) {
		history_section_caption = locale['S_HISTORY'];
	}
	// we have only numeric items, so 'simple graphs' should be shown
	else {
		history_section_caption = locale['S_SIMPLE_GRAPHS'];
	}


	tr_menu.push([history_section_caption,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}]);

	// for(var i=0; i < items.length; i++){
	for(var itemid in items){
		if(!isset(itemid, items)) continue;
		tr_menu.push([items[itemid].description,'history.php?action='+items[itemid].action+'&itemid='+items[itemid].itemid,null]);
	}

//to create a copy of array, but not references!!!!
//alert(id+' : '+page_menu[id]);

//alert(page_menu[id]);
	show_popup_menu(e,tr_menu,280);
}


//------------------------------------------------------
//					USERS FORM
//------------------------------------------------------

function testUserSound(idx){
	var sound = $(idx).options[$(idx).selectedIndex].value;
	var repeat = $('messages[sounds.repeat]').options[$('messages[sounds.repeat]').selectedIndex].value;

	if(repeat == 1) AudioList.play(sound);
	else if(repeat > 1) AudioList.loop(sound, {'seconds': repeat});
	else AudioList.loop(sound, {'seconds': $('messages[timeout]').value});
}
