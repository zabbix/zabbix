<?php
	$page["title"] = "Configuration of Zabbix";
	$page["file"] = "config.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_right("Configuration of Zabbix","U",0))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($HTTP_GET_VARS["register"]) && ($HTTP_GET_VARS["register"]=="update"))
	{
		$result=update_config($HTTP_GET_VARS["smtp_server"],$HTTP_GET_VARS["smtp_helo"],
			$HTTP_GET_VARS["smtp_email"],$HTTP_GET_VARS["alarm_history"],
			$HTTP_GET_VARS["alert_history"]);
		show_messages($result, "Configuration updated", "Configuration was NOT updated");
	}
?>

<?php
	show_table_header("CONFIGURATION OF ZABBIX");
	echo "<br>";
?>

<?php
	$config=select_config();
?>

<?php
	show_table2_header_begin();
	echo "Configuration";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"config.php\">";
	echo "SMTP server";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$config["smtp_server"]."\"size=40>";

	show_table2_v_delimiter();
	echo "Value for SMTP HELO authentification (can be empty)";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$config["smtp_helo"]."\"size=40>";

	show_table2_v_delimiter();
	echo "ZABBIX email address to send alarms from";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$config["smtp_email"]."\"size=40>";

	show_table2_v_delimiter();
	echo "Do not keep alerts older than (in days)";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alert_history\" value=\"".$config["alert_history"]."\"size=8>";

	show_table2_v_delimiter();
	echo "Do not keep alarms older than (in days)";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\"size=8>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?php
	show_footer();
?>
