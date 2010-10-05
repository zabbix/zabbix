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
			'<tr id="urlfooter"><td colspan="3"><input id="newSelementUrl" type="button" value="Add" class="button"></td></tr>'+
		'</tbody></table>'+
		'</td>',
'selementFormUrls': '<tr id="urlrow[#{sysmapelementurlid}]">'+
			'<td>'+
				'<input name="urlid" type="hidden" value="#{sysmapelementurlid}">'+
				'<input class="biginput" name="urls[#{sysmapelementurlid}][name]" id="urls[#{sysmapelementurlid}][name]" type="text" size="16" value="#{name}">'+
			'</td>'+
			'<td><input class="biginput" name="urls[#{sysmapelementurlid}][url]" id="urls[#{sysmapelementurlid}][url]" type="text" size="32" value="#{url}"></td>'+
			'<td><input class="button" type="button" value="X" name="remove" title="Remove" onclick="$(\'urlrow[#{sysmapelementurlid}]\').remove();"></td>'+
		'</tr>'
}
);