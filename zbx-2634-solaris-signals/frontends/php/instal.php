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
require_once('include/config.inc.php');
require_once('include/forms.inc.php');

$page["title"] = "S_INSTALLATION";
$page["file"] = 'instal.php';

include_once('include/page_header.php');
include_once('setup.php');
include_once('include/page_footer.php');

/*******************************/
/* THIS POINT NEVER BE REACHED */
/*******************************/
?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

/* actions */
		"install"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"update"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
	);

	check_fields($fields);
?>
<?php
	if(isset($_REQUEST['install'])){
		jsRedirect('setup.php');
		exit();
	}
	else if(isset($_REQUEST['update'])){
		error('*UNDER CONSTRUCTION*');
	}

	$form = new CFormTable(S_INSTALLATION_UPDATE);
	$form->setHelp('install_source_web.php');
	$form->addRow(
		array(bold(S_NEW_INSTALLATION_BIG),BR(),BR(),
			bold(S_DESCRIPTION),BR(),
			'Not implemented yet!',
			BR(),BR(),BR()
			),
		new CButton('install',S_NEW_INSTALLATION));
	$form->addRow(
		array(bold(S_UPDATE_BIG),BR(),BR(),
			bold(S_DESCRIPTION),BR(),
			'Not implemented yet!',
			BR(),BR(),BR()
			),
		new CButton('update',S_UPDATE));
	$form->show();

?>
<?php

include_once('include/page_footer.php');

?>
