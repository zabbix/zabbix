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
		"show_unknown"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	null),

	/* actions */
		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null)
	);
	
	check_fields($fields);
	
	$show_unknown = get_request('show_unknown',get_profile('web.events.show_unknown',0));
	update_profile('web.events.show_unknown',$show_unknown);
		
	if(!check_right_on_trigger_by_triggerid(PERM_READ_ONLY, $_REQUEST["triggerid"]))
		access_deny();

	$trigger_data = DBfetch(DBselect('SELECT h.host, t.* '.
						' FROM hosts h, items i, functions f, triggers t '.
	                        	' WHERE i.itemid=f.itemid AND f.triggerid=t.triggerid '.
						' AND t.triggerid='.$_REQUEST["triggerid"].
						' AND h.hostid=i.hostid AND '.DBin_node('t.triggerid')));
?>
<?php
	$_REQUEST["limit"] = get_request("limit","NO");

	$expression	= explode_exp($trigger_data["expression"],1);
	$description	= expand_trigger_description_by_data($trigger_data);

	$form = new CForm();
	$form->AddOption('name','events_menu');
	
	$form->AddVar("triggerid",$_REQUEST["triggerid"]);

	$chkbox = new CCheckBox('sh_unknown',
					(($show_unknown == 0)?'no':'yes'),
					'create_var("events_menu", "show_unknown", '.(($show_unknown == 0)?'1':'0').', true)'
					);
	$form->AddItem(array(S_SHOW_UNKNOWN, SPACE, $chkbox,SPACE, SPACE));

	$cmbLimit = new CComboBox("limit",$_REQUEST["limit"],"submit()");
	$cmbLimit->AddItem('NO',S_SHOW_ALL);
	$cmbLimit->AddItem("100",S_SHOW_ONLY_LAST_100);
	$form->AddItem($cmbLimit);


	show_table_header(S_EVENTS_BIG.': "'.$description.'"'.BR.$expression, $form);
?>
<?php
	$sql_cond = '';
	if($show_unknown == 0){
		$sql_cond = ' AND value<>2 ';
	}
	
	$result=DBselect('SELECT * FROM events WHERE objectid='.$_REQUEST['triggerid'].
						' AND object='.EVENT_OBJECT_TRIGGER.
						$sql_cond.
					' ORDER BY clock DESC',$_REQUEST['limit']);

	$table = new CTableInfo();
	$table->SetHeader(array(S_TIME,S_STATUS,S_ACKNOWLEDGED,S_DURATION,S_SUM,"%"));
	$table->ShowStart();

	$rows = array();
	$count = 0;
	while($row=DBfetch($result)){
		if(!empty($rows) && $rows[$count]['value'] != $row['value']){
			$count++;
		}
		$rows[$count] = $row;
	}

	$truesum=0;
	$falsesum=0;
	$dissum=0;
	$clock=time();
	
//	while($row=DBfetch($result))
	foreach($rows as $id => $row)
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
