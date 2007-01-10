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
	include "include/config.inc.php";
	$page["title"] = "S_AUDIT_LOG";
	$page["file"] = "audit.php";
	show_header($page["title"],1,0);
?>

<?php
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["start"])&&isset($_REQUEST["prev"]))
	{
		$_REQUEST["start"]-=100;
		if($_REQUEST["start"]<=0)
			unset($_REQUEST["start"]);
	}
	if(isset($_REQUEST["next"]))
	{
		if(isset($_REQUEST["start"]))
		{
			$_REQUEST["start"]+=100;
		}
		else
		{
			$_REQUEST["start"]=100;
		}
	}
?>

<?php
	$form = new CForm();

	$btnPrev = new CButton("prev","<< Prev 100");
	if(isset($_REQUEST["start"]))   {
		$form->AddVar("start",$_REQUEST["start"]);
	} else {
		$btnPrev->SetEnabled('no');
	}
	$form->AddItem($btnPrev);

	$form->AddItem(new CButton("next","Next 100 >>"));

	show_header2(S_AUDIT_LOG_BIG,$form);

?>

<?php
	$sql="select max(auditid) as max from auditlog";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxauditid=@iif($row,$row["max"],0);

	if(!isset($_REQUEST["start"]))
	{
		$sql="select u.alias,a.clock,a.action,a.resourcetype,a.details from auditlog a, users u".
			" where u.userid=a.userid and a.auditid>$maxauditid-200 order by clock desc";
		$limit = 200;
	}
	else
	{
		$sql="select u.alias,a.clock,a.action,a.resourcetype,a.details from auditlog a, users u".
			" where u.userid=a.userid and a.auditid>$maxauditid-".($_REQUEST["start"]+200).
			" order by clock desc";
		$limit = $_REQUEST["start"]+200;

	}
	$result=DBselect($sql,$limit);

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME,S_USER,S_RESOURCE,S_ACTION,S_DETAILS));
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($_REQUEST["start"])&&($i<$_REQUEST["start"]))	continue;
		if($i>100)	break;

		if($row["resourcetype"]==AUDIT_RESOURCE_USER)
			$resource=S_USER;
		else if($row["resourcetype"]==AUDIT_RESOURCE_ZABBIX_CONFIG)
			$resource=S_CONFIGURATION_OF_ZABBIX;
		else if($row["resourcetype"]==AUDIT_RESOURCE_MEDIA_TYPE)
			$resource=S_MEDIA_TYPE;
		else if($row["resourcetype"]==AUDIT_RESOURCE_HOST)
			$resource=S_HOST;
		else if($row["resourcetype"]==AUDIT_RESOURCE_ACTION)
			$resource=S_ACTION;
		else if($row["resourcetype"]==AUDIT_RESOURCE_GRAPH)
			$resource=S_GRAPH;
		else if($row["resourcetype"]==AUDIT_RESOURCE_GRAPH_ELEMENT)
			$resource=S_GRAPH_ELEMENT;
		else
			$resource=S_UNKNOWN_RESOURCE;

		if($row["action"]==AUDIT_ACTION_ADD)			$action = S_ADDED;
		else if($row["action"]==AUDIT_ACTION_UPDATE)		$action = S_UPDATED;
		else if($row["action"]==AUDIT_ACTION_DELETE)		$action = S_DELETED;
		else if($row["action"]==AUDIT_ACTION_LOGIN)		$action = S_LOGIN;
		else if($row["action"]==AUDIT_ACTION_LOGOUT)		$action = S_LOGOUT;
		else							$action = S_UNKNOWN_ACTION;

		$table->addRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$row["alias"],
			$resource,
			$action,
			$row["details"]
		));
	}
	$table->show();
?>

<?php
	show_page_footer();
?>
