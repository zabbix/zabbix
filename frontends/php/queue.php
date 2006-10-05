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
	require_once "include/items.inc.php";

	$page["title"] = "S_QUEUE_BIG";
	$page["file"] = "queue.php";
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"show"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL)
	);

	check_fields($fields);
?>

<?php
	$_REQUEST["show"] = get_request("show", 0);

	$form = new CForm();
	$cmbMode = new CComboBox("show", $_REQUEST["show"], "submit();");
	$cmbMode->AddItem(0, S_OVERVIEW);
	$cmbMode->AddItem(1,S_DETAILS);
	$form->AddItem($cmbMode);

	show_header2(S_QUEUE_OF_ITEMS_TO_BE_UPDATED_BIG, $form);
?>

<?php
	$now = time();

	$result = DBselect("select i.itemid, i.nextcheck, i.description, i.key_, h.host,h.hostid ".
		" from items i,hosts h ".
		" where i.status=".ITEM_STATUS_ACTIVE." and i.type not in (".ITEM_TYPE_TRAPPER.") ".
		" and ((h.status=".HOST_STATUS_MONITORED." and h.available != ".HOST_AVAILABLE_FALSE.") ".
		" or (h.status=".HOST_STATUS_MONITORED." and h.available=".HOST_AVAILABLE_FALSE." and h.disable_until<=$now)) ".
		" and i.hostid=h.hostid and i.nextcheck<$now and i.key_ not in ('status','icmpping','icmppingsec','zabbix[log]') ".
		" and h.hostid in (".get_accessible_hosts_by_userid($USER_DETAILS['userid'],PERM_READ_ONLY,null,null,$ZBX_CURNODEID).")".
		" order by i.nextcheck,h.host,i.description,i.key_");

	$table = new CTableInfo(S_THE_QUEUE_IS_EMPTY);

	if($_REQUEST["show"]==0)
	{
		$sec_5 = $sec_10 = $sec_30 = $sec_60 = $sec_300 = $sec_rest = 0;

		while($row=DBfetch($result))
		{
			if($now-$row["nextcheck"]<=5)		$sec_5++;
			elseif($now-$row["nextcheck"]<=10)	$sec_10++;
			elseif($now-$row["nextcheck"]<=30)	$sec_30++;
			elseif($now-$row["nextcheck"]<=60)	$sec_60++;
			elseif($now-$row["nextcheck"]<=300)	$sec_300++;
			else					$sec_rest++;

		}
		$table->SetHeader(array(S_DELAY,		S_COUNT));
		$table->AddRow(array(S_5_SECONDS,		$sec_5));
		$table->AddRow(array(S_10_SECONDS,		$sec_10));
		$table->AddRow(array(S_30_SECONDS,		$sec_30));
		$table->AddRow(array(S_1_MINUTE,		$sec_60));
		$table->AddRow(array(S_5_MINUTES,		$sec_300));
		$table->AddRow(array(S_MORE_THAN_5_MINUTES,	$sec_rest));
	}
	else
	{
		$table->SetHeader(array(S_NEXT_CHECK,S_HOST,S_DESCRIPTION));
		while($row=DBfetch($result))
		{
			$table->AddRow(array(
				date("m.d.Y H:i:s",
				$row["nextcheck"]),
				$row["host"],
				item_description($row["description"],$row["key_"])
				));
		}
	}

	$table->Show();
?>
<?php
	if($_REQUEST["show"]!=0)
	{
		show_table_header(S_TOTAL.": ".$table->GetNumRows());
	}
?>

<?php

include "include/page_footer.php";

?>
