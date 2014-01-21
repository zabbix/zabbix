<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/users.inc.php";

	$page["title"] = "S_USERS";
	$page["file"] = "popup_users.php";

	define('ZBX_PAGE_NO_MENU', 1);

include_once "include/page_header.php";

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm'=>	array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	NULL),
		'groupid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL)
	);

	check_fields($fields);

	$dstfrm		= get_request("dstfrm", 0);	// destination form
	$groupid 	= get_request("groupid", 0);
?>

<script language="JavaScript" type="text/javascript">
<!--
function add_users(formname) {
	var parent_document = window.opener.document;

	if(!parent_document) return close_window();

	$('usersid_left').immediateDescendants().each(
		function(e){
			add_variable('input', 'new_user['+e.value+']', e.text, formname, parent_document);
		});
	parent_document.forms[formname].submit();
	close_window();
}
-->
</script>

<?php
	$comboform = new CForm();
	$comboform->addVar('dstfrm',$dstfrm);

// create table header +
	$cmbGroups = new CComboBox('groupid', $groupid, 'submit()');
	$cmbGroups->addItem(0,S_ALL_S);

	$sql = 'SELECT usrgrpid, name FROM usrgrp WHERE '.DBin_node('usrgrpid').' ORDER BY name';
	$result=DBselect($sql);

	while($row=DBfetch($result)){
		$cmbGroups->addItem($row['usrgrpid'], $row['name']);
	}
	$comboform->addItem($cmbGroups);
	show_table_header(S_USERS, $comboform);
// -

// create user twinbox +
	$form = new CForm('users.php');
	$form->setAttribute('id', 'users');

	$user_tb = new CTweenBox($form, 'usersid', null, 10);

	$from = '';
	$where = '';
	if($groupid > 0) {
		$from = ', users_groups g ';
		$where = ' AND u.userid=g.userid AND g.usrgrpid='.$groupid;
	}
	$sql = 'SELECT u.userid, u.alias FROM users u '.$from.
	' WHERE '.DBin_node('u.userid').$where.
	' ORDER BY name';
	$result=DBselect($sql);

	while($row=DBfetch($result)){
		$user_tb->addItem($row['userid'], $row['alias'], false);
	}

	$form->addItem($user_tb->get('asdasda','asdasdasdas'));
// -
	$button = new CButton('select', S_SELECT, 'add_users("'.$dstfrm.'")');
	$button->setType('button');

	$form->addItem($button);
	$form->show();

include_once "include/page_footer.php";
?>
