<?php
	include "include/config.inc.php";
	$page["title"] = "Alert history";
	$page["file"] = "alerts.php";
	show_header($page["title"],30,0);
?>

<?php
	show_table_header_begin();
	echo "ALERT HISTORY";
 
	show_table_v_delimiter(); 
?>

<?php
        if(isset($HTTP_GET_VARS["start"])&&($HTTP_GET_VARS["start"]<=0))
        {
                unset($HTTP_GET_VARS["start"]);
        }
        if(isset($HTTP_GET_VARS["start"]))
        {
                echo "[<A HREF=\"alerts.php?start=".($HTTP_GET_VARS["start"]-100)."\">";
                echo "Show previous 100</A>] ";
                echo "[<A HREF=\"alerts.php?start=".($HTTP_GET_VARS["start"]+100)."\">";
                echo "Show next 100</A>]";
        }
        else
        {
                echo "[<A HREF=\"alerts.php?start=100\">";
                echo "Show next 100</A>]";
        }

	show_table_header_end();
	echo "<br>";

	show_table_header("ALERTS");
?>


<FONT COLOR="#000000">
<?php
	if(!isset($HTTP_GET_VARS["start"]))
	{
		$sql="select a.alertid,a.clock,a.type,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries from alerts a,actions ac where a.actionid=ac.actionid order by a.clock desc limit 500";
	}
	else
	{
		$sql="select a.alertid,a.clock,a.type,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries from alerts a,actions ac where a.actionid=ac.actionid order by a.clock desc limit ".($HTTP_GET_VARS["start"]+500);
	}
	$result=DBselect($sql);

	echo "<TABLE WIDTH=100% align=center BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=10%><b>Time</b></TD>";
	echo "<TD WIDTH=5%><b>Type</b></TD>";
	echo "<TD WIDTH=5%><b>Status</b></TD>";
	echo "<TD WIDTH=15%><b>Recipient(s)</b></TD>";
	echo "<TD><b>Subject</b></TD>";
	echo "<TD><b>Message</b></TD>";
	echo "</TR>";
	$col=0;
	$zzz=0;
	while($row=DBfetch($result))
	{
		$zzz++;	
		if(isset($HTTP_GET_VARS["start"])&&($zzz<$HTTP_GET_VARS["start"]))
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

		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("Y.M.d H:i:s",$row["clock"])."</a></TD>";
		if($row["type"]=="EMAIL")
		{
			echo "<TD>E-mail</TD>";
		}
		else
		{
			echo "<TD>Unknown media type</TD>";
		}
		if($row["status"] == 1)
		{
			echo "<TD><font color=\"00AA00\">sent</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AA0000\">not sent</font></TD>";
		}
		echo "<TD>".$row["sendto"]."</TD>";
		echo "<TD><pre>".$row["subject"]."</pre></TD>";
		echo "<TD>";
		echo "<pre>".$row["message"]."</pre>";;
//		for($i=0;$i<strlen($row["message"]);$i++)
//		{
//			if($row["message"][$i]=="\n")
//			{
//				echo "<br>";
//			}
//			else
//			{
//				echo $row["message"][$i];
//			}
//		}
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	show_footer();
?>
