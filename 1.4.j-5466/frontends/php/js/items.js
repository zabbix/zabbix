// JavaScript Document
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

function call_menu(evnt,id,name,ltype,menu_options){
 	var tname = 'Create Log Trigger';	
	if(isset(menu_options)){
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['Create Lot Trigger',"javascript: openWinCentered('tr_logform.php?sform=1&itemid="+id+"&ltype="+ltype+"','TriggerLog',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],
						menu_options
					],240);
	} else {
		show_popup_menu(evnt,
					[
						[name,null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],
						['Create Log Trigger',"javascript: openWinCentered('tr_logform.php?sform=1&itemid="+id+"&ltype="+ltype+"','ServiceForm',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]
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
	return false;
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
		if (isset(key) && isset(typ)) {
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
	url.setAttribute('href','javascript: if(confirm("Delete expression?")) remove_expression("logtr'+logexpr_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(document.createTextNode('Delete'));
	
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
		
		if(isset(elm_v)) elm_v.parentNode.removeChild(elm_v);
		if(isset(elm_t)) elm_t.parentNode.removeChild(elm_t);
		if(isset(elm_s)) elm_s.parentNode.removeChild(elm_s);
	}
	if(isset(expr_tr)){
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
	
	if(!isset(p_obj)) return;

	var c2_obj = previousObject(c_obj);
	if(c2_obj && c2_obj.id.length > 0){ 
		swapNodes(c2_obj,c_obj);
		swapNodesNames(c2_obj,c_obj);
	}
}

function element_down(elementid){
	var c_obj = document.getElementById(elementid);
	var p_obj = c_obj.parentNode;
	
	if(!isset(p_obj)) return;

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
			p2.insertBefore(n1, b);	//4to,pered 4em
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

function closeform(page){
	var msg="";
	try{
		msg = (IE)?(document.getElementsByTagName('p')[0].innerText):(document.getElementsByTagName('p')[0].textContent);
	} catch(e){
		alert(e);
	}
	opener.location.replace(page+'?msg='+encodeURI(msg));
	self.close();
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
	url.setAttribute('href','javascript: if(confirm("Delete keyword?")) remove_keyword("keytr'+key_count+'");');
	url.setAttribute(classattr,'action');
	url.appendChild(document.createTextNode('Delete'));
	
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
		
		if(isset(elm_v)) elm_v.parentNode.removeChild(elm_v);
		if(isset(elm_t)) elm_t.parentNode.removeChild(elm_t);
	}
	if(isset(key_tr)){
		key_tr.parentNode.removeChild(key_tr);
	}

	var lp;
	var bData = false;
	for (lp = 0; lp < key_count; lp++) {
		var elm_v = document.getElementsByName('keys['+lp+'][value]')[0];
		if (isset(elm_v)) bData = true;
	}
	if (!bData) {
		var bt_and = document.getElementById('add_key_and');
		var bt_or = document.getElementById('add_key_or');
		if (isset(bt_and)) bt_and.disabled = false;
		if (isset(bt_or)) bt_or.disabled = false;
	}
}
