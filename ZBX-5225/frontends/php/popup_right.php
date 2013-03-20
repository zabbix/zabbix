<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Resource');
$page['file'] = 'popup_right.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'dstfrm'=>		array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL),
	'permission'=>	array(T_ZBX_INT, O_MAND,P_SYS,	IN(PERM_DENY.','.PERM_READ_ONLY.','.PERM_READ_WRITE),	NULL),
	'nodeid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	NULL),
);

check_fields($fields);
?>
<?php
	$dstfrm		= get_request('dstfrm',		0);			// destination form
	$permission	= get_request('permission',	PERM_DENY);		// right
	$nodeid		= get_request('nodeid', 	CProfile::get('web.popup_right.nodeid.last',get_current_nodeid(false)));

	CProfile::update('web.popup_right.nodeid.last', $nodeid, PROFILE_TYPE_ID);

	$frmTitle = new CForm();
	$frmTitle->addVar('dstfrm',$dstfrm);
	$frmTitle->addVar('permission', $permission);

	if(ZBX_DISTRIBUTED){
		$available_nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_ONLY, PERM_RES_IDS_ARRAY);

		$cmbResourceNode = new CComboBox('nodeid',$nodeid,'submit();');
		$cmbResourceNode->addItem(0, _('All'));

		$sql = 'SELECT name,nodeid '.
			' FROM nodes '.
			' WHERE '.dbConditionInt('nodeid', $available_nodes);
		$db_nodes = DBselect($sql);
		while($node = DBfetch($db_nodes)){
			$cmbResourceNode->addItem($node['nodeid'], $node['name']);
		}

		$frmTitle->addItem(array(_('Node'), SPACE, $cmbResourceNode));
	}

	show_table_header(permission2str($permission),$frmTitle);

	$form = new CForm();
	$form->setAttribute('id', 'groups');

	$table = new CTableInfo(_('No resources defined.'));
	$table->setHeader(new CCol(array(new CCheckBox('all_groups', NULL, 'check_all(this.checked)'),_('Name'))));

// NODES
	if($nodeid == 0) $nodeids = get_current_nodeid(true);
	else $nodeids = $nodeid;

	$count=0;
	$grouplist = array();

	$options = array(
		'nodeids' => $nodeids,
		'output' => API_OUTPUT_EXTEND
	);
	$groups = API::HostGroup()->get($options);
	foreach($groups as $gnum => $row){
		$groups[$gnum]['nodename'] = get_node_name_by_elid($row['groupid'], true, ':').$row['name'];
		if($nodeid == 0) $groups[$gnum]['name'] = $groups[$gnum]['nodename'];
	}

	order_result($groups, 'name');

	foreach($groups as $gnum => $row){
		$grouplist[$count] = array(
			'groupid' => $row['groupid'],
			'name' => $row['nodename'],
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

?>
