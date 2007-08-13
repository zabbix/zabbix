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

        $page["title"] = "S_IT_NOTIFICATIONS";
        $page["file"] = "report4.php";

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"year"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	NULL,		NULL),
		"period"=>		array(T_ZBX_STR, O_OPT,	P_SYS|P_NZERO,	IN('"dayly","weekly","monthly","yearly"'),		NULL),
		"media_type"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL)
	);

	check_fields($fields);
?>
<?php
	$year		= get_request("year", 		intval(date("Y")));
	$period		= get_request("period",		"weekly");
	$media_type	= get_request("media_type",	0);
?>
<?php
	if( ($min_time = DBfetch(DBselect('select min(clock) as clock from alerts'))) && $min_time['clock'])
	{
		$MIN_YEAR = intval(date("Y", $min_time['clock']));
	}

	if( !isset($MIN_YEAR) )
	{
		$MIN_YEAR = intval(date("Y"));
	}
		
	$form = new CForm();

	$form->AddItem(SPACE.S_MEDIA_TYPE.SPACE);
	$cmbMedia = new CComboBox("media_type", $media_type, "submit();");
	$cmbMedia->AddItem(0,S_ALL_SMALL);
        $db_medias = DBselect('select * from media_type where '.DBin_node('mediatypeid').' order by description');
	while($media_data = DBfetch($db_medias))
	{
		$cmbMedia->AddItem($media_data["mediatypeid"], $media_data["description"]);
	}
	$form->AddItem($cmbMedia);

	$form->AddItem(SPACE.S_PERIOD.SPACE);
	$cmbPeriod = new CComboBox("period", $period, "submit();");
	$cmbPeriod->AddItem("dayly",	S_DAILY);
	$cmbPeriod->AddItem("weekly",	S_WEEKLY);
	$cmbPeriod->AddItem("monthly",	S_MONTHLY);
	$cmbPeriod->AddItem("yearly",	S_YEARLY);
	$form->AddItem($cmbPeriod);
	
	if($period != "yearly")
	{
		$form->AddItem(SPACE.S_YEAR.SPACE);
		$cmbYear = new CComboBox("year", $year, "submit();");
		for($y = $MIN_YEAR; $y <= date("Y"); $y++)
			$cmbYear->AddItem($y, $y);
		$form->AddItem($cmbYear);
	}
	
        show_table_header(S_NOTIFICATIONS_BIG, $form);
?>
<?php
	$_REQUEST["year"]	= $year;
	$_REQUEST["period"]	= $period;
	$_REQUEST["media_type"]	= $media_type;

	
        $table = new CTableInfo();

	$header = array();
	$db_users = DBselect('select * from users where '.DBin_node('userid').' order by alias,userid');
	while($user_data = DBfetch($db_users))
	{
		array_push($header, new CImg("vtext.php?text=".$user_data["alias"]));
		$users[$user_data['userid']] = $user_data['alias'];
	}

	$media_types = array();

	$db_media_types = DBselect('select * from media_type where '.DBin_node('mediatypeid').
		($media_type > 0 ? " and mediatypeid=".$media_type : "" ).
		" order by description,mediatypeid");
	while($media_type_data = DBfetch($db_media_types))
	{
		$media_types[$media_type_data['mediatypeid']] = $media_type_data['description'];
	}
	
        switch($period)
	{
		case "yearly":
			$from	= $MIN_YEAR;
			$to	= date("Y");
			array_unshift($header, new CCol(S_YEAR,"center"));
			function get_time($y)	{	return mktime(0,0,0,1,1,$y);		}
			function format_time($t){	return date("Y", $t);			}
			function format_time2($t){	return null; };
			break;
		case "monthly":
			$from	= 1;
			$to	= 12;
			array_unshift($header, new CCol(S_MONTH,"center"));
			function get_time($m)	{	global $year;	return mktime(0,0,0,$m,1,$year);	}
			function format_time($t){	return date("M Y",$t);			}
			function format_time2($t){	return null; };
			break;
		case "dayly":
			$from	= 1;
			$to	= 365;
			array_unshift($header, new CCol(S_DAY,"center"));
			function get_time($d)	{	global $year;	return mktime(0,0,0,1,$d,$year);	}
			function format_time($t){	return date("d M Y",$t);		}
			function format_time2($t){	return null; };
			break;
		case "weekly":
		default:
			$from	= 0;
			$to	= 52;
			array_unshift($header,new CCol(S_FROM,"center"),new CCol(S_TILL,"center"));
			function get_time($w)	{
				global $year;	

				$time	= mktime(0,0,0,1, 1, $year);
				$wd	= date("w", $time);
				$wd	= $wd == 0 ? 6 : $wd - 1;

				return ($time + ($w*7 - $wd)*24*3600);
			}
			function format_time($t){	return date("d M Y H:i",$t);	}
			function format_time2($t){	return format_time($t); };
			break;

	}

	$table->SetHeader($header,"vertical_header");

	for($t = $from; $t <= $to; $t++)
	{       
		if(($start = get_time($t)) > time())
			break;
		
		if(($end = get_time($t+1)) > time())
			$end = time();

		$table_row = array(format_time($start),format_time2($end));
		foreach($users as $userid => $alias)
		{
			$all = 0;
			$cnt_by_type = array();
			foreach($media_types as $mediatypeid => $description)
			{
				$cnt_data = DBfetch(DBselect("select count(*) as cnt from alerts a ".
					" where a.userid=".$userid." and a.mediatypeid=".$mediatypeid.
					" and clock>$start and clock<$end "));
				if(!$cnt_data)	$cnt_data = 0;
				else		$cnt_data = $cnt_data['cnt'];
				array_push($cnt_by_type, $cnt_data);
				$all += $cnt_data;
			}
			array_push($table_row,array($all, ($media_type == 0 ? SPACE."(".implode('/',$cnt_by_type).")" : "" )));
		}
		$table->AddRow($table_row);
	}
	$table->show();
	
	if($media_type == 0)
	{
		$table = new CTableInfo();
		$table->AddRow(new CSpan(SPACE.SPACE.SPACE.SPACE.SPACE.SPACE."all".SPACE."(".implode('/', $media_types).")","off"));
		$table->Show();
	}
?>
<?php

include_once "include/page_footer.php";

?>
