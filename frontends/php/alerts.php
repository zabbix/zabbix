<?
	include "include/config.inc";
	$page["title"] = "Alert history";
	$page["file"] = "alerts.php";
	show_header($page["title"],30,0);
?>

<?
	show_table_header_begin();
	echo "ALERT HISTORY";
 
	show_table_v_delimiter(); 
?>

<?
	if(isset($limit)&&($limit==200))
	{
		echo "[<A HREF=\"alerts.php?limit=50\">";
		echo "Show last 50</A>]";
		$limit=" limit 50";
	}
	else 
	{
		echo "[<A HREF=\"alerts.php?limit=200\">";
		echo "Show last 200</A>]";
		$limit=" limit 200";
	}

	show_table_header_end();
	echo "<br>";

	show_table_header("ALERTS");
?>


<FONT COLOR="#000000">
<?
	$sql="select a.alertid,a.clock,a.type,a.sendto,a.subject,a.message,ac.triggerid from alerts a,actions ac where a.actionid=ac.actionid order by a.clock desc $limit";
	$result=DBselect($sql);

	echo "<CENTER>";
	echo "<TABLE WIDTH=100% BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=\"10%\"><b>Time</b></TD>";
	echo "<TD WIDTH=\"5%\"><b>Type</b></TD>";
	echo "<TD WIDTH=\"15%\"><b>Send to</b></TD>";
	echo "<TD><b>Subject</b></TD>";
	echo "<TD><b>Message</b></TD>";
	echo "</TR>";
	$col=0;
	while($row=DBfetch($result))
	{
                if(!check_right("User","R",$row["userid"]))
                {
			continue;
		}

		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("Y.M.d H:i:s",$row["clock"])."</a></TD>";
		if($row["type"]=="EMAIL")
		{
			echo "<TD>E-mail</TD>";
		}
		else
		{
			echo "<TD>Unknown media type</TD>";
		}
		echo "<TD>".$row["sendto"]."</TD>";
		echo "<TD>".$row["subject"]."</TD>";
		echo "<TD>".$row["message"]."</TD>";
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
