<?
	include "include/config.inc.php";
	$page["title"] = "Latest values";
	$page["file"] = "latest.php";
	show_header($page["title"],300,0);
?>

<?
	show_table_header_begin();
	$result=DBselect("select i.description,h.host,h.hostid from items i,hosts h where i.hostid=h.hostid and i.itemid=$itemid");
	$description=DBget_field($result,0,0);
	$host=DBget_field($result,0,1);
	$hostid=DBget_field($result,0,2);

	echo "<A HREF='latest.php?hostid=$hostid'>$host</A> : <a href='history.php?action=showhistory&itemid=$itemid'>$description</a>";

	show_table_v_delimiter();

	echo "<font size=2>";

	if(isset($type)&&$type=="year")
	{
		echo "<b>[<a href='compare.php?itemid=$itemid&type=year'>year</a>]</b> ";
	}
	else
	{
		echo "<a href='compare.php?itemid=$itemid&type=year'>year</a> ";
	}
	if(isset($type)&&$type=="month")
	{
		echo "<b>[<a href='compare.php?itemid=$itemid&type=month'>month</a>]</b> ";
	}
	else
	{
		echo "<a href='compare.php?itemid=$itemid&type=month'>month</a> ";
	}
	if(isset($type)&&$type=="week")
	{
		echo "<b>[<a href='compare.php?itemid=$itemid&type=week'>week</a>]</b> ";
	}
	else
	{
		echo "<a href='compare.php?itemid=$itemid&type=week'>week</a> ";
	}
	echo "</font>";


	if(isset($type))
	{
		show_table_v_delimiter();
		echo "<b>[<a href='compare.php?itemid=$itemid&type=$type&type2=day'>day</a>]</b> ";
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
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($itemid)&&isset($type))
	{
		if(isset($trendavg))
		{
			echo "<IMG SRC=\"chart3.php?itemid=$itemid&type=$type&trendavg=1\">";
		}
		else
		{
			echo "<IMG SRC=\"chart3.php?itemid=$itemid&type=$type\">";
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
