<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	$page["title"] = S_STATUS_OF_ZABBIX;
	$page["file"] = "report1.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header(S_STATUS_OF_ZABBIX_BIG);

	echo "<TABLE BORDER=0 WIDTH=100% BGCOLOR=\"#AAAAAA\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=\"#CCCCCC\"><TD WIDTH=10%><B>".S_PARAMETER."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_VALUE."</B></TD>";
	echo "</TR>";

	$stats=get_stats();
?>

	<tr bgcolor="#eeeeee">
	<td><?php echo S_ZABBIX_SUCKERD_IS_RUNNING; ?></td>
	<?php
		$str="<font color=\"AA0000\">".S_NO."</font>";
		if( (exec("ps -ef|grep zabbix_suckerd|grep -v grep|wc -l")>0) || (exec("ps -ax|grep zabbix_suckerd|grep -v grep|wc -l")>0) )
		{
			$str="<font color=\"00AA00\">".S_YES."</font>";
		}
	?>
	<td><?php echo $str; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td><?php echo S_ZABBIX_TRAPPERD_IS_RUNNING; ?></td>
	<?php
		$str="<font color=\"AA0000\">".S_NO."</font>";
		if( (exec("ps -ef|grep zabbix_trapperd|grep -v grep|wc -l")>0) || (exec("ps -ax|grep zabbix_trapperd|grep -v grep|wc -l")>0) )
		{
			$str="<font color=\"00AA00\">".S_YES."</font>";
		}
	?>
	<td><?php echo $str; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td><?php echo S_NUMBER_OF_VALUES_STORED; ?></td>
	<td><?php echo $stats["history_count"]; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td><?php echo S_NUMBER_OF_TRENDS_STORED; ?></td>
	<td><?php echo $stats["trends_count"]; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td><?php echo S_NUMBER_OF_ALARMS; ?></td>
	<td><?php echo $stats["alarms_count"]; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td><?php echo S_NUMBER_OF_ALERTS; ?></td>
	<td><?php echo $stats["alerts_count"]; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td><?php echo S_NUMBER_OF_TRIGGERS_ENABLED_DISABLED; ?></td>
	<td><?php echo $stats["triggers_count"],"(",$stats["triggers_count_enabled"],"/",$stats["triggers_count_disabled"],")"; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td><?php echo S_NUMBER_OF_ITEMS_ACTIVE_TRAPPER; ?></td>
	<td><?php echo $stats["items_count"],"(",$stats["items_count_active"],"/",$stats["items_count_trapper"],"/",$stats["items_count_not_active"],"/",$stats["items_count_not_supported"],")"; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td><?php echo S_NUMBER_OF_USERS; ?></td>
	<td><?php echo $stats["users_count"]; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td><?php echo S_NUMBER_OF_HOSTS_MONITORED; ?></td>
	<td><?php echo $stats["hosts_count"],"(",$stats["hosts_count_monitored"],"/",$stats["hosts_count_not_monitored"],"/",$stats["hosts_count_template"],")"; ?></td>
	</tr>

	</table>

<?php
	show_footer();
?>
