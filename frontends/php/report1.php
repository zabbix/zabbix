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

	$table->setHeader(array(S_PARAMETER,S_VALUE));

	$stats=get_stats();

	$col=0;
	$str=array("value"=>S_NO,"class"=>"on");
	if( (exec("ps -ef|grep zabbix_server|grep -v grep|wc -l")>0) || (exec("ps -ax|grep zabbix_server|grep -v grep|wc -l")>0) )
	{
		$str=array("value"=>S_YES,"class"=>"off");
	}
	$table->addRow(array(S_ZABBIX_SERVER_IS_RUNNING,$str),$col++);

	$table->addRow(array(S_NUMBER_OF_VALUES_STORED,$stats["history_count"]),$col++);
	$table->addRow(array(S_NUMBER_OF_TRENDS_STORED,$stats["trends_count"]),$col++);
	$table->addRow(array(S_NUMBER_OF_ALARMS,$stats["alarms_count"]),$col++);
	$table->addRow(array(S_NUMBER_OF_ALERTS,$stats["alerts_count"]),$col++);
	$table->addRow(array(S_NUMBER_OF_TRIGGERS_ENABLED_DISABLED,$stats["triggers_count"]."(".$stats["triggers_count_enabled"]."/".$stats["triggers_count_disabled"].")"),$col++);
	$table->addRow(array(S_NUMBER_OF_ITEMS_ACTIVE_TRAPPER,$stats["items_count"]."(".$stats["items_count_active"]."/".$stats["items_count_trapper"]."/".$stats["items_count_not_active"]."/".$stats["items_count_not_supported"].")"),$col++);
	$table->addRow(array(S_NUMBER_OF_USERS,$stats["users_count"]),$col++);
	$table->addRow(array(S_NUMBER_OF_HOSTS_MONITORED,$stats["hosts_count"]."(".$stats["hosts_count_monitored"]."/".$stats["hosts_count_not_monitored"]."/".$stats["hosts_count_template"]."/".$stats["hosts_count_deleted"].")"),$col++);

	$table->show();
?>

<?php
	show_page_footer();
?>
