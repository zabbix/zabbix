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
	$page["title"] = "Helpdesk";
	$page["file"] = "helpdesk.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header_begin();
	echo "IT HELPDESK";
 
	show_table_v_delimiter(); 
?>

<?php
        if(isset($start)&&($start<=0))
        {
                unset($start);
        }
        if(isset($start))
        {
                echo "[<A HREF=\"alerts.php?start=".($start-100)."\">";
                echo "Show previous 100</A>] ";
                echo "[<A HREF=\"alerts.php?start=".($start+100)."\">";
                echo "Show next 100</A>]";
        }
        else
        {
                echo "[<A HREF=\"alerts.php?start=100\">";
                echo "Show next 100</A>]";
        }

	show_table_header_end();
	echo "<br>";

	show_table_header("PROBLEMS");
?>


<FONT COLOR="#000000">
<?php
	if(!isset($start))
	{
		$sql="select problemid,clock,status,description,priority,userid,triggerid,lastupdate,categoryid from problems where status=0 order by clock,priority limit 1000";
	}
	else
	{
		$sql="select a.alertid,a.clock,a.type,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries from alerts a,actions ac where a.actionid=ac.actionid order by a.clock desc limit ".($start+1000);
	}
	$result=DBselect($sql);

	echo "<TABLE WIDTH=100% BORDER=0 align=center BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=10%><b>Registered at</b></TD>";
	echo "<TD WIDTH=10%><b>Severity</b></TD>";
	echo "<TD WIDTH=10%><b>Category</b></TD>";
	echo "<TD WIDTH=10%><b>Description</b></TD>";
	echo "<TD WIDTH=5%><b>Status</b></TD>";
	echo "<TD><b>History</b></TD>";
	echo "</TR>";
	$col=0;
	$zzz=0;
	while($row=DBfetch($result))
	{
		$zzz++;	
		if(isset($start)&&($zzz<$start))
		{
			continue;
		}
//		if(!check_right_on_trigger("R",$row["triggerid"]))
 //               {
//			continue;
//		}

		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD valign=top>"; }
		else		{ echo "<tr bgcolor=#EEEEEE valign=top>"; }

		if($col>100)	break;

		echo "<TD><pre>".date("Y.M.d H:i:s",$row["clock"])."</pre></TD>";
		if($row["priority"]==0)		echo "<TD ALIGN=CENTER><pre>Not classified</pre></TD>";
		elseif($row["priority"]==1)	echo "<TD ALIGN=CENTER><pre>Information</pre></TD>";
		elseif($row["priority"]==2)	echo "<TD ALIGN=CENTER><pre>Warning</pre></TD>";
		elseif($row["priority"]==3)	echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA><pre>Average</pre></TD>";
		elseif($row["priority"]==4)	echo "<TD ALIGN=CENTER BGCOLOR=#FF8888><pre>High</pre></TD>";
		elseif($row["priority"]==5)	echo "<TD ALIGN=CENTER BGCOLOR=RED><pre>Disaster !!!</pre></TD>";
		else				echo "<TD ALIGN=CENTER><pre><B>".$row["priority"]."</B></pre></TD>";
		if(isset($row["categoryid"]))
		{
			echo "<TD align=center><pre>".$row["categoryid"]."</pre></TD>";
		}
		else
		{
			echo "<TD align=center><pre>-</pre></TD>";
		}
		echo "<TD><pre>".$row["description"]."</pre></TD>";
		if($row["status"]==0)
		{
			echo "<TD><pre>Active</pre></TD>";
		}
		else
		{
			echo "<TD><pre>Closed</pre></TD>";
		}
		echo "<TD>";

		$sql="select commentid,problemid,clock,status_before,status_after,comment from problems_comments where problemid=".$row["problemid"]." order by clock";
		$result2=DBselect($sql);
		while($row2=DBfetch($result2))
		{
			echo "<table WIDTH=100% BORDER=1 BGCOLOR=\"#EEEEEE\" cellspacing=0 cellpadding=1>";
			echo "<tr>";
			echo "<td><b>Registered at:</b></td>";
			echo "<td>".date("Y.M.d H:i:s",$row2["clock"])."</td>";
			echo "</tr>";
			echo "<tr>";
			echo "<td><b>Commented by:</b></td>";
			if(isset($row2["userid"]))
			{
				$user=get_user_by_userid($row2["userid"]);
				echo "<td>".$user["name"]." ".$user["surname"]."</td>";
			}
			else
			{
				echo "<td>Zabbix</td>";
			}
			echo "</tr>";
			echo "<tr>";
			echo "<td><pre>".$row2["comment"]."</pre></td>";
			echo "<td><pre>".$row2["comment"]."</pre></td>";
			echo "</tr>";
			echo "<tr>";
			echo "</tr>";
			echo "</table>";
			echo "<hr>";
		}
		echo "[<a href=\"helpdesk.php?action=add_comment&problemid=".$row["problemid"]."\">Add comment</a>]";
		echo " [<a href=\"helpdesk.php?action=change_problem&problemid=".$row["problemid"]."\">Change problem</a>]";
	}
	echo "</TABLE>";
?>

<?php
		echo "<a name=\"form\"></a>";
		insert_problem_form($HTTP_GET_VARS["problemid"]);
?>

<?php
	show_footer();
?>
