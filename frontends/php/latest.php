<?
	include "include/config.inc.php";
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
	if(isset($HTTP_GET_VARS["select"]))
	{
		unset($HTTP_GET_VARS["hostid"]);
	}
	
        if(isset($HTTP_GET_VARS["hostid"])&&!check_right("Host","R",$HTTP_GET_VARS["hostid"]))
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

//	echo "<font size=2>";

	if(isset($HTTP_GET_VARS["groupid"]))
	{
		echo "all ";
	}
	else
	{
		echo "<b>[<a href='latest.php'>all</a>]</b> ";
	}

	$result=DBselect("select groupid,name from groups order by name");

	while($row=DBfetch($result))
	{
//		if(!check_right("Host","R",$row["hostid"]))
//		{
//			continue;
//		}
		if( isset($HTTP_GET_VARS["groupid"]) && ($HTTP_GET_VARS["groupid"] == $row["groupid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='latest.php?groupid=".$row["groupid"]."'>".$row["name"]."</a>";
		if(isset($HTTP_GET_VARS["groupid"]) && ($HTTP_GET_VARS["groupid"] == $row["groupid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_v_delimiter();

	if(isset($HTTP_GET_VARS["groupid"]))
	{
		$result=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status in (0,2) and h.hostid=i.hostid and hg.groupid=".$HTTP_GET_VARS["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
	}
	else
	{
		$result=DBselect("select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");
	}

	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if( isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]) )
		{
			echo "<b>[";
		}
		if(isset($HTTP_GET_VARS["groupid"]))
		{
			echo "<a href='latest.php?groupid=".$HTTP_GET_VARS["groupid"]."&hostid=".$row["hostid"]."'>".$row["host"]."</a>";
		}
		else
		{
			echo "<a href='latest.php?hostid=".$row["hostid"]."'>".$row["host"]."</a>";
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	echo "</font>";
	if(!isset($HTTP_GET_VARS["hostid"])&&isset($HTTP_GET_VARS["select_form"])&&!isset($HTTP_GET_VARS["select"]))
	{
		show_table_v_delimiter();
		echo "<form name=\"form1\" method=\"get\" action=\"latest.php?select=true\">
	  	<input class=\"biginput\" type=\"text\" name=\"select\" value=\"".$HTTP_GET_VARS["txt_select"]."\">
	  	<input type=\"submit\" name=\"Select\" value=\"Select\">
		</form>";
	}
	else
	{
		show_table_v_delimiter();
		if(isset($HTTP_GET_VARS["select"]))
		{
			echo "<b>[<a href='latest.php?select_form=1'>Select</a>]</b>";
		}
		else
		{
			echo "[<a href='latest.php?select_form=1'>Select</a>]";
		}
	}
	show_table_header_end();

	if(!isset($HTTP_GET_VARS["sort"]))
	{
		$HTTP_GET_VARS["sort"]="description";
	}

	if(isset($HTTP_GET_VARS["hostid"]))
	{
		$result=DBselect("select host from hosts where hostid=".$HTTP_GET_VARS["hostid"]);
		if(DBnum_rows($result)==0)
		{
			unset($HTTP_GET_VARS["hostid"]);
		}
	}

	if(isset($HTTP_GET_VARS["hostid"])||isset($HTTP_GET_VARS["select"]))
	{

		echo "<br>";
		if(!isset($HTTP_GET_VARS["select"]))
		{
			$result=DBselect("select host from hosts where hostid=".$HTTP_GET_VARS["hostid"]);
			$host=DBget_field($result,0,0);
			show_table_header("<a href=\"latest.php?hostid=".$HTTP_GET_VARS["hostid"]."\">$host</a>");
		}
		else
		{
			show_table_header("Description is like *".$HTTP_GET_VARS["select"]."*");
		}
#		show_table_header_begin();
#		echo "<a href=\"latest.php?hostid=".$HTTP_GET_VARS["hostid"]."\">$host</a>";
#		show_table3_v_delimiter();

		echo "<TABLE BORDER=0 COLS=4 WIDTH=100% cellspacing=1 cellpadding=3>";
		cr();
		echo "<TR BGCOLOR=\"CCCCCC\">";
		cr();
		if(isset($HTTP_GET_VARS["select"]))
		{
			echo "<TD><B>Host</B></TD>";
		}
		if(!isset($HTTP_GET_VARS["sort"])||(isset($HTTP_GET_VARS["sort"])&&($HTTP_GET_VARS["sort"]=="description")))
		{
			echo "<TD><B>DESCRIPTION</B></TD>";
		}
		else
		{
			if(isset($HTTP_GET_VARS["select"]))
			{
				echo "<TD><B><a href=\"latest.php?select=".$HTTP_GET_VARS["select"]."&sort=description\">Description</B></TD>";
			}
			else
			{
				echo "<TD><B><a href=\"latest.php?hostid=".$HTTP_GET_VARS["hostid"]."&sort=description\">Description</B></TD>";
			}
		}
		if(isset($HTTP_GET_VARS["sort"])&&($HTTP_GET_VARS["sort"]=="lastcheck"))
		{
			echo "<TD WIDTH=12%><B>LAST CHECK</B></TD>";
		}
		else
		{
			if(isset($HTTP_GET_VARS["select"]))
			{
				echo "<TD WIDTH=12%><B><a href=\"latest.php?select=".$HTTP_GET_VARS["select"]."&sort=lastcheck\">Last check</B></TD>";
			}
			else
			{
				echo "<TD WIDTH=12%><B><a href=\"latest.php?hostid=".$HTTP_GET_VARS["hostid"]."&sort=lastcheck\">Last check</B></TD>";
			}
		}
		cr();
		echo "<TD WIDTH=10%><B>Last value</B></TD>"; 
		cr();
		echo "<TD WIDTH=5%><B>Change</B></TD>"; 
		cr();
		echo "<TD WIDTH=5% align=center><B>History</B></TD>";
		cr();
		echo "<TD WIDTH=5% align=center><B>Trends</B></TD>";
		cr();
		echo "<TD WIDTH=5% align=center><B>Compare</B></TD>";
		cr();
		echo "</TR>";
		cr();

		$col=0;
		if(isset($HTTP_GET_VARS["sort"]))
		{
			switch ($HTTP_GET_VARS["sort"])
			{
				case "description":
					$HTTP_GET_VARS["sort"]="order by i.description";
					break;
				case "lastcheck":
					$HTTP_GET_VARS["sort"]="order by i.lastclock";
					break;
				default:
					$HTTP_GET_VARS["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$HTTP_GET_VARS["sort"]="order by i.description";
		}
		if(isset($HTTP_GET_VARS["select"]))
		{
			$result=DBselect("select h.host,i.itemid,i.description,i.lastvalue,i.prevvalue,i.lastclock,i.status,h.hostid,i.value_type from items i,hosts h where h.hostid=i.hostid and h.status in (0,2) and i.status in (0,2) and i.description like '%".$HTTP_GET_VARS["select"]."%' ".$HTTP_GET_VARS["sort"]);
		}
		else
		{
			$result=DBselect("select h.host,i.itemid,i.description,i.lastvalue,i.prevvalue,i.lastclock,i.status,h.hostid,i.value_type from items i,hosts h where h.hostid=i.hostid and h.status in (0,2) and i.status in (0,2) and h.hostid=".$HTTP_GET_VARS["hostid"]." ".$HTTP_GET_VARS["sort"]);
		}
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

			if(isset($HTTP_GET_VARS["select"]))
			{
				echo "<td>".$row["host"]."</td>";
			}
			echo "<td>".$row["description"]."</td>";

			echo "<td>";
			if($row["status"] == 2)
			{
				echo "<font color=\"#FF6666\">";
			}

			if(!isset($row["lastclock"]))
			{
				echo "<div align=center>-</div>";
			}
			else
			{
				echo date("d M H:i:s",$row["lastclock"]);
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
				echo "<td align=center>-</td>";
			}
			if( isset($row["lastvalue"]) && isset($row["prevvalue"]) && $row["lastvalue"]-$row["prevvalue"] != 0 )
			{
//				echo "<td>"; echo $row["lastvalue"]-$row["prevvalue"]; echo "</td>";
//	sprintf("%+0.2f"); does not work
				if($row["lastvalue"]-$row["prevvalue"]<0)
				{
					printf("<td>%0.2f</td>",$row["lastvalue"]-$row["prevvalue"]);
				}
				else
				{
					printf("<td>+%0.2f</td>",$row["lastvalue"]-$row["prevvalue"]);
				}
			}
			else
			{
				echo "<td align=center>-</td>";
			}
			if($row["value_type"]==0)
			{
				echo "<td align=center><a href=\"history.php?action=showhistory&itemid=".$row["itemid"]."\">Show</a></td>";
			}
			else
			{
				echo "<td align=center><a href=\"history.php?action=showvalues&period=3600&itemid=".$row["itemid"]."\">Show</a></td>";
			}
			if($row["value_type"]==0)
			{
				echo "<td align=center><a href=\"trends.php?itemid=".$row["itemid"]."\">Show</a></td>";
			}
			else
			{
				echo "<td align=center>Show</td>";
			}
			if($row["value_type"]==0)
			{
				echo "<td align=center><a href=\"compare.php?itemid=".$row["itemid"]."\">Show</a></td>";
			}
			else
			{
				echo "<td align=center>Show</td>";
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
