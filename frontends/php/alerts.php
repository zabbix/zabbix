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
	$sql="select max(alertid) as max from alerts";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalertid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($HTTP_GET_VARS["start"]))
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries,ac.scope from alerts a,actions ac,media_type mt where a.actionid=ac.actionid and mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200 order by a.clock desc limit 200";
	}
	else
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries,ac.scope from alerts a,actions ac,media_type mt where a.actionid=ac.actionid and mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200-".$HTTP_GET_VARS["start"]." order by a.clock desc limit ".($HTTP_GET_VARS["start"]+500);
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
		if(($row["scope"]==0)&&!check_right_on_trigger("R",$row["triggerid"]))
                {
			continue;
		}
		if(($row["scope"]==1)&&!check_right("Host","R",$row["triggerid"]))
                {
			continue;
		}
		if(($row["scope"]==2)&&!check_anyright("Default permission","R"))
                {
			continue;
		}

		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		if($col>100)	break;

		if($row["scope"]==0)
		{
			echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("Y.M.d H:i:s",$row["clock"])."</a></TD>";
		}
		else
		{
			echo "<TD>".date("Y.M.d H:i:s",$row["clock"])."</TD>";
		}
		echo "<TD>".$row["description"]."</TD>";
		if($row["status"] == 1)
		{
			echo "<TD><font color=\"00AA00\">sent</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AA0000\">not sent</font></TD>";
		}
		echo "<TD>".htmlspecialchars($row["sendto"])."</TD>";
		echo "<TD><pre>".htmlspecialchars($row["subject"])."</pre></TD>";
		echo "<TD>";
		echo "<pre>".htmlspecialchars($row["message"])."</pre>";
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
		cr();
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=6 ALIGN=CENTER>-No alerts-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	show_footer();
?>
