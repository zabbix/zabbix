<?
	include "include/config.inc";
	$page["title"] = "Latest values";
	$page["file"] = "latest.php";
	show_header($page["title"],0,0);
?>

<?
        if(!check_right("Host","R",0))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?
	show_table_header_begin();
	echo "LATEST DATA";

	show_table_v_delimiter();

	echo "<font size=2>";

	$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if( isset($hostid) && ($hostid == $row["hostid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='latest.php?hostid=".$row["hostid"]."'>".$row["host"]."</a>";
		if(isset($hostid) && ($hostid == $row["hostid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	echo "</font>";
	show_table_header_end();

	if(!isset($sort))
	{
		$sort="description";
	}

	if(isset($hostid))
	{
		$result=DBselect("select host from hosts where hostid=$hostid");
		if(DBnum_rows($result)==0)
		{
			unset($hostid);
		}
	}

	if(isset($hostid))
	{
		$result=DBselect("select host from hosts where hostid=$hostid");
		$host=DBget_field($result,0,0);

		echo "<br>";
		show_table_header("<a href=\"latest.php?hostid=$hostid\">$host</a>");
#		show_table_header_begin();
#		echo "<a href=\"latest.php?hostid=$hostid\">$host</a>";
#		show_table3_v_delimiter();

		echo "<TABLE BORDER=0 COLS=4 WIDTH=\"100%\" cellspacing=1 cellpadding=3>";
		cr();
		echo "<TR BGCOLOR=\"CCCCCC\">";
		cr();
		if(!isset($sort)||(isset($sort)&&($sort=="description")))
		{
			echo "<TD><B>DESCRIPTION</B></TD>";
		}
		else
		{
			echo "<TD><B><a href=\"latest.php?hostid=$hostid&sort=description\">Description</B></TD>";
		}
		if(isset($sort)&&($sort=="lastcheck"))
		{
			echo "<TD WIDTH=\"12%\"><B>LAST CHECK</B></TD>";
		}
		else
		{
			echo "<TD WIDTH=\"12%\"><B><a href=\"latest.php?hostid=$hostid&sort=lastcheck\">Last check</B></TD>";
		}
		cr();
		echo "<TD WIDTH=\"10%\" NOSAVE><B>Last value</B></TD>"; 
		cr();
		echo "<TD WIDTH=\"5%\" NOSAVE><B>Change</B></TD>"; 
		cr();
		echo "<TD WIDTH=\"5%\" NOSAVE><center><B>History</B></center></TD>";
		cr();
		echo "<TD WIDTH=\"5%\" NOSAVE><center><B>Trends</B></center></TD>";
		cr();
		echo "</TR>";
		cr();

		$col=0;
		if(isset($sort))
		{
			switch ($sort)
			{
				case "description":
					$sort="order by i.description";
					break;
				case "lastcheck":
					$sort="order by i.lastclock";
					break;
				default:
					$sort="order by i.description";
					break;
			}
		}
		else
		{
			$sort="order by i.description";
		}
		$result=DBselect("select h.host,i.itemid,i.description,i.lastvalue,i.prevvalue,i.lastclock,i.status,h.hostid,i.value_type from items i,hosts h where h.hostid=i.hostid and h.status in (0,2) and i.status in (0,2) and h.hostid=$hostid $sort");
		while($row=DBfetch($result))
		{
        		if(!check_right("Item","R",$row["itemid"]))
			{
				continue;
			}
        		if(!check_right("Host","R",$row["hostid"]))
			{
				continue;
			}
			if($col++%2 == 1)	{ echo "<tr bgcolor=#DDDDDD>"; }
			else			{ echo "<tr bgcolor=#EEEEEE>"; }

			echo "<td>".$row["description"]."</td>";

			echo "<td>";
			if($row["status"] == 2)
			{
				echo "<font color=\"#FF6666\">";
			}

			if(!isset($row["lastclock"]))
			{
				echo "<center>-</center>";
			}
			else
			{
				echo date("d M H:i:s",$row["lastclock"]);
//				echo date("H:i:s",$row["lastclock"]);
			}
			echo "</font></td>";

			if(isset($row["lastvalue"]))
			{
				if(round($row["lastvalue"])==$row["lastvalue"])
				{ 
					if($row["value_type"] == 0 )
					{
						echo "<td>"; printf("%.0f",$row["lastvalue"]); echo "</td>";
					}
					else
					{
						echo "<td>"; echo substr($row["lastvalue"],0,20)," ..."; echo "</td>";
					}
				}
				else
				{
					echo "<td>"; printf("%.2f",$row["lastvalue"]); echo "</td>";
				}
			}
			else
			{
				echo "<td><center>-</center></td>";
			}
			if( isset($row["lastvalue"]) && isset($row["prevvalue"]) && $row["lastvalue"]-$row["prevvalue"] != 0 )
			{
				echo "<td>"; echo $row["lastvalue"]-$row["prevvalue"]; echo "</td>";
			}
			else
			{
				echo "<td><center>-</center></td>";
			}
			if($row["value_type"]==0)
			{
				echo "<td><center><a href=\"history.php?action=showhistory&itemid=".$row["itemid"]."\">Show</a></center></td>";
			}
			else
			{
				echo "<td><center><a href=\"history.php?action=showvalues&period=3600&itemid=".$row["itemid"]."\">Show</a></center></td>";
			}
			if($row["value_type"]==0)
			{
				echo "<td><center><a href=\"trends.php?itemid=".$row["itemid"]."\">Show</a></center></td>";
			}
			else
			{
				echo "<td><center>Show</center></td>";
			}
			echo "</tr>";
		}
		echo "</table>";
		show_table_header_end();
	}
?>

<?
	show_footer();
?>
