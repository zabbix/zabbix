<?php
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
?>
<?php
	require_once('include/config.inc.php');
	require_once('include/users.inc.php');

	$page['title'] = "S_GROUPS";
	$page['file'] = 'popup_usrgrp.php';

	define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>		array(T_ZBX_STR, O_MAND,	P_SYS,	NOT_EMPTY,	NULL),
		'new_groups'=>	array(T_ZBX_STR, O_OPT,		P_SYS,	NOT_EMPTY,	NULL),

		'select'=>		array(T_ZBX_STR, O_OPT,		P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);

// destination form
	$dstfrm	= get_request('dstfrm',	0);
	$new_groups = get_request('new_groups', array());
?>
<?php
	show_table_header(S_GROUPS);
?>
<script language="JavaScript" type="text/javascript">
<!--
function add_var_to_opener_obj(obj,name,value){
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}
-->
</script>
<?php

	if(isset($_REQUEST['select']) && count($new_groups) > 0){
?>
<script language="JavaScript" type="text/javascript">
form = window.opener.document.forms['<?php echo $dstfrm; ?>'];
<!--
<?php
		foreach($new_groups as $id){
			echo 'add_var_to_opener_obj(form,"new_groups['.$id.']","'.$id.'")'."\r";
		}
?>
if(form){
	form.submit();
	close_window();
}
-->
</script>
<?php
	}

	$form = new CForm();
	$form->addVar('dstfrm', $dstfrm);

	$form->setName('groups');

	$table = new CTableInfo(S_NO_GROUPS_DEFINED);
	$table->setHeader(array(
		new CCheckBox("all_groups",NULL,"checkAll('".$form->getName()."','all_groups','new_groups');"),
		S_NAME
		));

	$result = DBselect('select * from usrgrp where '.DBin_node('usrgrpid').' order by name');
	while($row = DBfetch($result)){
		$table->addRow(array(
			new CCheckBox('new_groups['.$row['usrgrpid'].']',isset($new_groups[$row['usrgrpid']]),NULL,$row['usrgrpid']),
			$row['name']
		));
	}
	$table->setFooter(new CCol(new CButton('select', S_SELECT)));

	$form->addItem($table);
	$form->show();
?>
<?php

include_once('include/page_footer.php');

?>
