// JavaScript Document
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
	chkbx.name = 'childs['+serviceid+'][serviceid]';
	
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
	url.setAttribute(classattr,'action');
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
		if(frmForm.elements[i].name != rmvbyname+'['+serviceid+'[serviceid]') continue;

		remove_element(frmForm.elements[i],tag);
		i--;
	}
}

function remove_element(elmnt,tag){
	if(elmnt.nodeName == tag){
		elmnt.parentNode.removeChild(elmnt);
	} else if(elmnt.nodeType == 9){
		return;
	} else {
		remove_element(elmnt.parentNode,tag);
	}
}


function display_element(name){
	var elmnt = document.getElementById(name);
	if(!isset(elmnt)){
		return;
	}
	else if((elmnt.offsetWidth == 0) || (elmnt.style.display == 'none')){
		elmnt.style.display = IE?'block':'table-row';
	}
	else {
		elmnt.style.display = 'none';
	}
}

function closeform(page){
	var msg="";
	try{
		msg = (IE)?(document.getElementsByTagName('p')[0].innerText):(document.getElementsByTagName('p')[0].textContent);
	} catch(e){
		alert(e);
	}
	opener.location.replace('services.php?msg='+encodeURI(msg));
	self.close();
}