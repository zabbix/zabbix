<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Resource');
$page['file'] = 'popup_right.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'dstfrm'=>		array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL),
	'permission'=>	array(T_ZBX_INT, O_MAND,P_SYS,	IN(PERM_DENY.','.PERM_READ.','.PERM_READ_WRITE),	NULL)
);

check_fields($fields);

	$dstfrm		= get_request('dstfrm',		0);			// destination form
	$permission	= get_request('permission',	PERM_DENY);		// right

	$frmTitle = new CForm();
	$frmTitle->addVar('dstfrm',$dstfrm);
	$frmTitle->addVar('permission', $permission);

	show_table_header(permission2str($permission),$frmTitle);

	$form = new CForm();
	$form->setAttribute('id', 'groups');

	$table = new CTableInfo(_('No host groups found.'));
	$table->setHeader(new CCol(array(new CCheckBox('all_groups', NULL, 'check_all(this.checked)'),_('Name'))));

	$count=0;
	$grouplist = array();

	$options = array(
		'output' => API_OUTPUT_EXTEND
	);
	$groups = API::HostGroup()->get($options);

	order_result($groups, 'name');

	foreach($groups as $gnum => $row){
		$grouplist[$count] = array(
			'groupid' => $row['groupid'],
			'name' => $row['name'],
			'permission' => $permission
		);

		$table->addRow(	new CCol(array(new CCheckBox('groups['.$count.']', NULL, NULL, $count), $row['name'])));
		$count++;
	}

	insert_js('var grouplist = '.zbx_jsvalue($grouplist).';');

	$button = new CButton('select', _('Select'), 'add_groups("'.$dstfrm.'")');
	$table->setFooter(new CCol($button,'right'));

	$form->addItem($table);
	$form->show();

	?>
<script language="JavaScript" type="text/javascript">
<!--
function add_groups(formname) {
	var parent_document = window.opener.document;

	if(!parent_document) return close_window();

	$('groups').getInputs("checkbox").each(
		function(box){
			if(box.checked && (box.name != "all_groups")){
				var groupid = grouplist[box.value].groupid;
				add_variable('input', 'new_right['+groupid+'][permission]', grouplist[box.value].permission, formname, parent_document);
				add_variable('input', 'new_right['+groupid+'][name]', grouplist[box.value].name, formname, parent_document);
			}
		});
	parent_document.forms[formname].submit();
	close_window();
}

function check_all(value) {
	$("groups").getInputs("checkbox").each(function(e){ e.checked = value });
}
-->
</script>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';
