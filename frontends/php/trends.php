<?
	include "include/config.inc.php";
	$page["title"] = "Latest values";
	$page["file"] = "latest.php";
	show_header($page["title"],0,0);
?>

<?
	show_table_header_begin();
	$result=DBselect("select i.description,h.host,h.hostid from items i,hosts h where i.hostid=h.hostid and i.itemid=$itemid");
	$description=DBget_field($result,0,0);
	$host=DBget_field($result,0,1);
	$hostid=DBget_field($result,0,2);

	echo "<A HREF='latest.php?hostid=$hostid'>$host</A> : <a href='compare.php?action=showhistory&itemid=$itemid'>$description</a>";

	show_table_v_delimiter();

	echo "<font size=2>";

	if(isset($type)&&$type=="12hours")
	{
		echo "<b>[<a href='trends.php?itemid=$itemid&type=12hours'>12hours</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=$itemid&type=12hours'>12hours</a> ";
	}
	if(isset($type)&&$type=="4hours")
	{
		echo "<b>[<a href='trends.php?itemid=$itemid&type=4hours'>4hours</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=$itemid&type=4hours'>4hours</a> ";
	}
	if(isset($type)&&$type=="hour")
	{
		echo "<b>[<a href='trends.php?itemid=$itemid&type=hour'>hour</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=$itemid&type=hour'>hour</a> ";
	}
	if(isset($type)&&$type=="30min")
	{
		echo "<b>[<a href='trends.php?itemid=$itemid&type=30min'>30min</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=$itemid&type=30min'>30min</a> ";
	}
	if(isset($type)&&$type=="15min")
	{
		echo "<b>[<a href='trends.php?itemid=$itemid&type=15min'>15min</a>]</b> ";
	}
	else
	{
		echo "<a href='trends.php?itemid=$itemid&type=15min'>15min</a> ";
	}
	echo "</font>";


	if(isset($type))
	{
		show_table_v_delimiter();
		if(isset($trendavg))
		{
			echo "<a href='trends.php?itemid=$itemid&type=$type'>ALL</a> ";
		}
		else
		{
			echo "<a href='trends.php?itemid=$itemid&type=$type&trendavg=1'>AVG</a> ";
		}
	}

	show_table_header_end();
	echo "<br>";
?>

<?
	if(isset($itemid)&&isset($type))
	{
		show_table_header(strtoupper($type));
	}
	else
	{
		show_table_header("Select type of trend");
	}
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($itemid)&&isset($type))
	{
		if(isset($trendavg))
		{
			echo "<IMG SRC=\"trend.php?itemid=$itemid&type=$type&trendavg=1\">";
		}
		else
		{
			echo "<IMG SRC=\"trend.php?itemid=$itemid&type=$type\">";
		}
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

?>

<?
	show_footer();
?>
