/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

if(typeof(zbx_templates) == 'undefined'){
	var ZBX_TPL = {};
}

Object.extend(ZBX_TPL,{
'hostInterface': '<tr id="hostInterface_#{interfaceid}">'+
	'<td>'+
		'<input type="hidden" name="interfaces[#{interfaceid}][new]" value="#{new}">'+
		'<input type="hidden" name="interfaces[#{interfaceid}][interfaceid]" value="#{interfaceid}">'+
		'<input class="biginput" name="interfaces[#{interfaceid}][ip]" type="text" size="39" value="#{ip}">'+
	'</td>'+
	'<td><input class="biginput" name="interfaces[#{interfaceid}][dns]" type="text" size="30" value="#{dns}"></td>'+
	'<td><input class="biginput" name="interfaces[#{interfaceid}][port]" type="text" size="5" value="#{port}" maxlength="5" onkeypress=" var c = (window.event) ? event.keyCode : event.which; if(event.ctrlKey || c &lt;= 31 || (c &gt;= 48 &amp;&amp; c &lt;= 57) || (c &gt;= 37 &amp;&amp; c &lt;= 40) || c==46 || c==35 || c==36) return true; else return false; " onchange=" if(isNaN(parseInt(this.value,10))) this.value = 0;  else this.value = parseInt(this.value,10);"></td>'+
	'<td><select name="interfaces[#{interfaceid}][useip]" id="interfaces_#{interfaceid}_useip" class="select">'+
		'<option value="0">'+locale['S_DNS_NAME']+'</option>'+
		'<option value="1">'+locale['S_IP_ADDRESS']+'</option>'+
		'</select>'+
	'</td>'+
	'<td><span class="link_menu" onclick="$(\'hostInterface_#{interfaceid}\').remove();">'+locale['S_REMOVE']+'</span></td>'+
	'</tr>'
}
);
