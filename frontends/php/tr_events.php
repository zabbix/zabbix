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
	require_once "include/acknow.inc.php";
	require_once "include/triggers.inc.php";

	$page["title"]		= "S_ALARMS";
	$page["file"]		= "tr_events.php";

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"triggerid"=>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
		"limit"=>		array(T_ZBX_STR, O_OPT,	null,	IN('"100","NO"'),	null),

	/* actions */
		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
	);
	check_fields($fields);
	
	$denyed_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, PERM_MODE_LT);
	
	if(! ($trigger_data = DBfetch(DBselect('select h.host, t.* from hosts h, items i, functions f, triggers t '.
	                        ' where i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid='.$_REQUEST["triggerid"].
				" and i.hostid not in (".$denyed_hosts.") and h.hostid=i.hostid ".
				" and ".DBid2nodeid("t.triggerid")."=".$ZBX_CURNODEID
				))))
	{
		access_deny();
	}
?>
<?php
	$_REQUEST["limit"] = get_request("limit","NO");

	$expression	= explode_exp($trigger_data["expression"],1);
	$description	= expand_trigger_description_by_data($trigger_data);

	$form = new CForm();
	$form->AddVar("triggerid",$_REQUEST["triggerid"]);
	$cmbLimit = new CComboBox("limit",$_REQUEST["limit"],"submit()");
	$cmbLimit->AddItem('NO',S_SHOW_ALL);
	$cmbLimit->AddItem("100",S_SHOW_ONLY_LAST_100);
	$form->AddItem($cmbLimit);

	show_table_header(S_ALARMS_BIG.": \"".$description."\"".BR."$expression", $form);
?>
<?php
	$result=DBselect("select * from events where triggerid=".$_REQUEST["triggerid"].
		" order by clock desc",
		$_REQUEST["limit"]);

	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_STATUS,S_ACKNOWLEDGED,S_DURATION,S_SUM,"%"));
	$table->ShowStart();

	$truesum=0;
	$falsesum=0;
	$dissum=0;
	$clock=mktime();
	while($row=DBfetch($result))
	{
		$lclock=$clock;
		$clock=$row["clock"];
		$leng=$lclock-$row["clock"];


		if($row["value"]==1)
		{
			$istrue=new CCol(S_TRUE_BIG,"on");
			$truesum=$truesum+$leng;
			$sum=$truesum;
		}
		elseif($row["value"]==0)
		{
			$istrue=new CCol(S_FALSE_BIG,"off");
			$falsesum=$falsesum+$leng;
			$sum=$falsesum;
		}
		else
		{
			$istrue=new CCol(S_UNKNOWN_BIG,"unknown");
			$dissum=$dissum+$leng;
			$sum=$dissum;
		}
	
		$proc=(100*$sum)/($falsesum+$truesum+$dissum);
		$proc=round($proc*100)/100;
		$proc="$proc%";
 
		if($leng>60*60*24)
		{
			$leng= round(($leng/(60*60*24))*10)/10;
			$leng="$leng days";
		}
		elseif ($leng>60*60)
		{
			$leng= round(($leng/(60*60))*10)/10;
			$leng="$leng hours"; 
		}
		elseif ($leng>60)
		{
			$leng= round(($leng/(60))*10)/10;
			$leng="$leng mins";
		}
		else
		{
			$leng="$leng secs";
		}

		if($sum>60*60*24)
		{
			$sum= round(($sum/(60*60*24))*10)/10;
			$sum="$sum days";
		}
		elseif ($sum>60*60)
		{
			$sum= round(($sum/(60*60))*10)/10;
			$sum="$sum hours"; 
		}
		elseif ($sum>60)
		{
			$sum= round(($sum/(60))*10)/10;
			$sum="$sum mins";
		}
		else
		{
			$sum="$sum secs";
		}
  
		$ack = "-";
		if($row["value"] == 1 && $row["acknowledged"] == 1)
		{
			$db_acks = get_acknowledges_by_eventid($row["eventid"]);
			$rows=0;
			while($a=DBfetch($db_acks))	$rows++;
			$ack=array(
				new CSpan(S_YES,"off"),
				SPACE."(".$rows.SPACE,
				new CLink(S_SHOW,
					"acknow.php?eventid=".$row["eventid"],"action"),
				")"
				);
		}

		$table->ShowRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$istrue,
			$ack,
			$leng,
			$sum,
			$proc
			));
	}
	$table->ShowEnd();
?>

<?php

include_once "include/page_footer.php";

?>
