<?php
	include "include/config.inc.php";
	$page["title"] = "Status of Zabbix";
	$page["file"] = "report1.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header("STATUS OF ZABBIX");

	echo "<TABLE BORDER=0 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=10%><B>Parameter</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Value</B></TD>";
	echo "</TR>";

	$stats=get_stats();
?>

	<tr bgcolor="#eeeeee">
	<td>Is zabbix_suckerd running ?</td>
	<?php
		$str="No";
		if(exec("ps -aef|grep zabbix_suckerd|grep -v grep|wc -l")>0)
		{
			$str="Yes";
		}
	?>
	<td><?php echo $str; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td>Is zabbix_trapperd running ?</td>
	<?php
		$str="No";
		if(exec("ps -aef|grep zabbix_trapperd|grep -v grep|wc -l")>0)
		{
			$str="Yes";
		}
	?>
	<td><?php echo $str; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td>Number of values stored in table history</td>
	<td><?php echo $stats["history_count"]; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td>Number of values stored in table alarms</td>
	<td><?php echo $stats["alarms_count"]; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td>Number of values stored in table alerts</td>
	<td><?php echo $stats["alerts_count"]; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td>Number of triggers (enabled/disabled)</td>
	<td><?php echo $stats["triggers_count"],"(",$stats["triggers_count_enabled"],"/",$stats["triggers_count_disabled"],")"; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td>Number of items (active/trapper/not active/not supported)</td>
	<td><?php echo $stats["items_count"],"(",$stats["items_count_active"],"/",$stats["items_count_trapper"],"/",$stats["items_count_not_active"],"/",$stats["items_count_not_supported"],")"; ?></td>
	</tr>

	<tr bgcolor="#dddddd">
	<td>Number of users</td>
	<td><?php echo $stats["users_count"]; ?></td>
	</tr>

	<tr bgcolor="#eeeeee">
	<td>Number of hosts (monitored/not monitored)</td>
	<td><?php echo $stats["hosts_count"],"(",$stats["hosts_count_monitored"],"/",$stats["hosts_count_not_monitored"],")"; ?></td>
	</tr>

	</table>

<?php
	show_footer();
?>
