<?
	$page["title"] = "Latest alarms";
	$page["file"] = "latestalarms.php";

	include "include/config.inc";
	show_header($page["title"],30,0);
?>

<?
	show_table_header_begin();
	echo "HISTORY OF ALARMS";
 
	show_table_v_delimiter();
?>

<?
	if(isset($limit))
	{
		echo "[<A HREF=\"latestalarms.php\">";
		echo "Show last 50</A>]";
	}
	else 
	{
		echo "[<A HREF=\"latestalarms.php?limit=200\">";
		echo "Show last 200</A>]";
	}

	show_table_header_end();
	echo "<br>";

	show_table_header("ALARMS");
?>

<FONT COLOR="#000000">
<?
	if(isset($limit))
	{
		$sql="select t.description,a.clock,a.value,t.triggerid from alarms a,triggers t where t.triggerid=a.triggerid order by clock desc limit $limit";
	}
	else
	{
		$sql="select t.description,a.clock,a.value,t.triggerid from alarms a,triggers t where t.triggerid=a.triggerid order by clock desc limit 50";
	}
	$result=DBselect($sql);

	echo "<CENTER>";
	echo "<TABLE WIDTH=100% BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD width=\"20%\"><b>Time</b></TD>";
	echo "<TD><b>Description</b></TD>";
	echo "<TD width=\"10%\"><b>Value</b></TD>";
	echo "</TR>";
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		echo "<TD>",date("Y.M.d H:i:s",$row["clock"]),"</TD>";
		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".$row["description"]."</a></TD>";
		if($row["value"] == 0)
		{
			echo "<TD><font color=\"00AA00\">OFF</font></TD>";
		}
		elseif($row["value"] == 1)
		{
			echo "<TD><font color=\"AAAAAA\">ON</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AA0000\">UNKNOWN</font></TD>";
		}
		echo "</TR>";
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE></CENTER>

<?
	show_footer();
?>
