<?
	$page["title"]="Zabbix main page";
	$page["file"]="index.php";

	include "include/config.inc";

	show_header($page["title"],0,0);
?>

<?
	echo "<center>";
	echo "<font face=\"arial,helvetica\" size=2>";
	echo "Connected as ".$USER_DETAILS["alias"]."</b>";
	echo "<br>";
	echo "<a href=\"index.php?reconnect=1\">RECONNECT</a>";	
	echo "</font>";
	echo "</center>";
?>

<?
	show_footer();
?>
