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
	show_header($page["title"],1,0);

	$PAGE_SIZE = 100;
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
	$result = DBselect("select u.alias,a.clock,a.action,a.resourcetype,a.details from auditlog a, users u".
		" where u.userid=a.userid and mod(u.userid,100)=".$ZBX_CURNODEID.
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

	show_header2(S_AUDIT_LOG_BIG,$form);

	$table->show();
?>

<?php
	show_page_footer();
?>
