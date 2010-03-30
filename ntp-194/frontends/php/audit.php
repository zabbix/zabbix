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
	require_once "include/audit.inc.php";

	$page["title"] = "S_AUDIT_LOG";
	$page["file"] = "audit.php";

	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

	$PAGE_SIZE = 100;
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"start"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".$PAGE_SIZE."==0)",	NULL),
		"next"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL)
	);

	check_fields($fields);
?>
<?php
	$start	= get_request("start", 0);
	$prev	= get_request("prev", null);
	$next	= get_request("next", null);


	if($start > 0 && isset($prev))	$start -= $PAGE_SIZE;
	if(isset($next))		$start += $PAGE_SIZE;

	$limit = $start+$PAGE_SIZE;
?>
<?php
	global $USER_DETAILS;

	$result = DBselect("select u.alias,a.clock,a.action,a.resourcetype,a.details from auditlog a, users u".
		" where u.userid=a.userid ".
		' and '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
		" order by clock desc",
		$limit);

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME,S_USER,S_RESOURCE,S_ACTION,S_DETAILS));
	for($i=0; $row=DBfetch($result); $i++)
	{
		if($i<$start)	continue;

		if($row["action"]==AUDIT_ACTION_ADD)			$action = S_ADDED;
		else if($row["action"]==AUDIT_ACTION_UPDATE)		$action = S_UPDATED;
		else if($row["action"]==AUDIT_ACTION_DELETE)		$action = S_DELETED;
		else if($row["action"]==AUDIT_ACTION_LOGIN)		$action = S_LOGIN;
		else if($row["action"]==AUDIT_ACTION_LOGOUT)		$action = S_LOGOUT;
		else							$action = S_UNKNOWN_ACTION;

		$table->addRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$row["alias"],
			audit_resource2str($row["resourcetype"]),
			$action,
			$row["details"]
		));
	}

	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar("start",$start);

	$btnPrev = new CButton("prev","<< Prev ".$PAGE_SIZE);
	if($start <= 0)
		$btnPrev->SetEnabled('no');

	$btnNext = new CButton("next","Next ".$PAGE_SIZE." >>");
	if($i < $limit)
		$btnNext->SetEnabled('no');

	$form->AddItem(array(
		$btnPrev,
		$btnNext
		));

	show_table_header(S_AUDIT_LOG_BIG,$form);

	$table->show();
?>

<?php

include_once "include/page_footer.php";

?>
