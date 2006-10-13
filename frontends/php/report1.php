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
	$page["title"] = "S_STATUS_OF_ZABBIX";
	$page["file"] = "report1.php";
	show_header($page["title"],0,0);
?>

<?php
	update_profile("web.menu.reports.last",$page["file"]);
?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
	);

	check_fields($fields);
?>

<?php
	show_table_header(S_STATUS_OF_ZABBIX_BIG);

	$table = new CTableInfo();

	$table->SetHeader(array(S_PARAMETER,S_VALUE));

	$status=get_status();

	if($status["zabbix_server"] == S_YES)
		$style = "off";
	else
		$style = "on";

	$table->AddRow(array(S_ZABBIX_SERVER_IS_RUNNING,new CSpan($status["zabbix_server"],$style)));
//	$table->AddRow(array(S_VALUES_STORED,$status["history_count"]));
//	$table->AddRow(array(S_TRENDS_STORED,$status["trends_count"]));
	$table->AddRow(array(S_NUMBER_OF_HOSTS,array($status["hosts_count"]."(",
		new CSpan($status["hosts_count_monitored"],"off"),"/",
		new CSpan($status["hosts_count_not_monitored"],"on"),"/",
		new CSpan($status["hosts_count_template"],"unknown"),"/",
		$status["hosts_count_deleted"].")")));
	$table->AddRow(array(S_NUMBER_OF_ITEMS,array($status["items_count"]."(",
		new CSpan($status["items_count_monitored"],"off"),"/",
		new CSpan($status["items_count_disabled"],"on"),"/",
		new CSpan($status["items_count_not_supported"],"unknown"),
		")[".$status["items_count_trapper"]."]")));
	$table->AddRow(array(S_NUMBER_OF_TRIGGERS,array($status["triggers_count"].
		"(".$status["triggers_count_enabled"]."/".$status["triggers_count_disabled"].")"."[",
		new CSpan($status["triggers_count_on"],"on"),"/",
		new CSpan($status["triggers_count_unknown"],"unknown"),"/",
		new CSpan($status["triggers_count_off"],"off"),"]"
		)));
//	$table->AddRow(array(S_NUMBER_OF_ALARMS,$status["alarms_count"]));
//	$table->AddRow(array(S_NUMBER_OF_ALERTS,$status["alerts_count"]));
	$table->Show();
?>

<?php
	show_page_footer();
?>
