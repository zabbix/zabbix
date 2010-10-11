/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

if(typeof(zbx_templates) == 'undefined'){
	var ZBX_TPL = {};
}

Object.extend(ZBX_TPL,{
'selementFormUrlContainer': '<td class="form_row_l">'+locale['S_URL']+'</td>'+
		'<td class="form_row_r">'+
		'<table><tbody id="urlContainer">'+
			'<tr class="header"><td>Name</td><td>Url</td><td></td></tr>'+
			'<tr id="urlfooter"><td colspan="3"><span id="newSelementUrl" class="link_menu" title="Add">'+locale['S_ADD']+'</span></td></tr>'+
		'</tbody></table>'+
		'</td>',
'selementFormUrls': '<tr id="urlrow_#{sysmapelementurlid}">'+
			'<td>'+
				'<input class="biginput" name="name" id="url_name_#{sysmapelementurlid}" type="text" size="16" value="#{name}">'+
			'</td>'+
			'<td><input class="biginput" name="url" id="url_url_#{sysmapelementurlid}" type="text" size="32" value="#{url}"></td>'+
			'<td><span class="link_menu" name="remove" onclick="$(\'urlrow_#{sysmapelementurlid}\').remove();">'+locale['S_REMOVE']+'</span></td>'+
		'</tr>'
}
);