<?
	$page["title"] = "Latest alarms";
	$page["file"] = "latestalarms.php";

	include "include/config.inc.php";
	show_header($page["title"],30,0);
?>

<?
	show_table_header_begin();
	echo "HISTORY OF ALARMS";
 
	show_table_v_delimiter();
?>

<?
	if(isset($start)&&($start<=0))
	{
		unset($start);
	}
	if(isset($start))
	{
		echo "[<A HREF=\"latestalarms.php?start=".($start-100)."\">";
		echo "Show previous 100</A>] ";
		echo "[<A HREF=\"latestalarms.php?start=".($start+100)."\">";
		echo "Show next 100</A>]";
	}
	else 
	{
		echo "[<A HREF=\"latestalarms.php?start=100\">";
		echo "Show next 100</A>]";
	}

	show_table_header_end();
	echo "<br>";

	show_table_header("ALARMS");
?>

<FONT COLOR="#000000">
<?
	if(!isset($start))
	{
		$sql="select t.description,a.clock,a.value,t.triggerid from alarms a,triggers t where t.triggerid=a.triggerid order by clock desc limit 1000";
	}
	else
	{
		$sql="select t.description,a.clock,a.value,t.triggerid from alarms a,triggers t where t.triggerid=a.triggerid order by clock desc limit ".($start+1000);
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
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($start)&&($i<$start))
		{
			continue;
		}
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		if($col>100)	break;

		echo "<TD>",date("Y.M.d H:i:s",$row["clock"]),"</TD>";
		$description=$row["description"];
		if( strstr($description,"%s"))
		{
			$description=expand_trigger_description($row["triggerid"]);
		}
		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a></TD>";
		if($row["value"] == 0)
		{
			echo "<TD><font color=\"00AA00\">OFF</font></TD>";
		}
		elseif($row["value"] == 1)
		{
			echo "<TD><font color=\"AA0000\">ON</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AAAAAA\">UNKNOWN</font></TD>";
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
