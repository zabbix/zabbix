<?php
	include "include/config.inc.php";
	$page["title"] = "Configuration of screen";
	$page["file"] = "screenedit.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header("CONFIGURATION OF SCREEN");
	echo "<br>";
?>

<?php
	if(!check_right("Screen","R",$HTTP_GET_VARS["screenid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
//			if(isset($HTTP_GET_VARS["screenitemid"]))
//			{
//				delete_screen_item($HTTP_GET_VARS["screenitemid"]);
//				unset($HTTP_GET_VARS["screenitemid"]);
//			}
			if($HTTP_GET_VARS["resource"]==0)
			{
				$result=add_screen_graph($HTTP_GET_VARS["screenid"],$HTTP_GET_VARS["x"],$HTTP_GET_VARS["y"],$HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			}
			if($HTTP_GET_VARS["resource"]==1)
			{
				$result=add_screen_item($HTTP_GET_VARS["screenid"],$HTTP_GET_VARS["x"],$HTTP_GET_VARS["y"],$HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			}
			unset($HTTP_GET_VARS["x"]);
			show_messages($result,"Item added","Cannot add item");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_screen_item($HTTP_GET_VARS["screenitemid"]);
			$result=delete_screen_graph($HTTP_GET_VARS["screengraphid"]);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($HTTP_GET_VARS["x"]);
		}
                if($HTTP_GET_VARS["register"]=="update")
                {
                        $result=update_screen_item($HTTP_GET_VARS["screenitemid"],$HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
                        $result=update_screen_graph($HTTP_GET_VARS["screengraphid"],$HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
                        show_messages($result,"Item updated","Cannot update item");
			unset($HTTP_GET_VARS["x"]);
                }
		unset($HTTP_GET_VARS["register"]);

	}
?>

<?php
	$screenid=$HTTP_GET_VARS["screenid"];
	$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
	$row=DBfetch($result);
	show_table_header("<a href=\"screenedit.php?screenid=$screenid\">".$row["name"]."</a>");
	echo "<TABLE BORDER=1 COLS=".$row["cols"]." align=center WIDTH=100% BGCOLOR=\"#FFFFFF\"";
        for($r=0;$r<$row["rows"];$r++)
	{
	echo "<TR>";
	for($c=0;$c<$row["cols"];$c++)
	{
		echo "<TD align=\"center\">\n";

		echo "<a name=\"form\"></a>";
		echo "<form method=\"get\" action=\"screenedit.php\">";

		{
                	$screenitemid=0;
                	$screengraphid=0;
                	$graphid=0;
                	$sysmapid=0;
                	$itemid=0;
                	$width=500;
                	$height=100;
			$resource=@iif(isset($HTTP_GET_VARS["resource"]),$HTTP_GET_VARS["resource"],0);
		}

		$iresult=DBSelect("select * from screens_items where screenid=$screenid and x=$c and y=$r");
        	if(DBnum_rows($iresult)>0)
        	{
			$resource=1;
        		$irow=DBfetch($iresult);
        		$screenitemid=$irow["screenitemid"];
			$graphid=$irow["graphid"];
			$width=$irow["width"];
			$height=$irow["height"];
			$found=1;
        	}

		$iresult=DBSelect("select * from screens_graphs where screenid=$screenid and x=$c and y=$r");
        	if(DBnum_rows($iresult)>0)
        	{
			$resource=0;
        		$irow=DBfetch($iresult);
        		$screengraphid=$irow["screengraphid"];
			$itemid=$irow["itemid"];
			$width=$irow["width"];
			$height=$irow["height"];
        	}

		if(isset($HTTP_GET_VARS["x"])&&($HTTP_GET_VARS["x"]==$c)&&($HTTP_GET_VARS["y"]==$r))
		{
        		show_table2_header_begin();
        		echo "Screen item configuration";

        		echo "<input name=\"screenid\" type=\"hidden\" value=$screenid>";
			echo "<input name=\"x\" type=\"hidden\" value=$c>";
			echo "<input name=\"y\" type=\"hidden\" value=$r>";
			echo "<input name=\"screenitemid\" type=\"hidden\" value=$screenitemid>";
			echo "<input name=\"screengraphid\" type=\"hidden\" value=$screengraphid>";
			echo "<input name=\"resource\" type=\"hidden\" value=$resource>";

			show_table2_v_delimiter();
			echo "Resource";
			show_table2_h_delimiter();
			echo "<select name=\"resource\" size=1 onChange=\"submit()\">";
			echo "<OPTION VALUE='0' ".iif($resource==0,"selected","").">Simple graph";
			echo "<OPTION VALUE='1' ".iif($resource==1,"selected","").">Graph";
//			echo "<OPTION VALUE='2' ".iif($resource==2,"selected","").">Network map";
			echo "</SELECT>";

			if($resource == 0)
			{
				show_table2_v_delimiter();
				echo "Graph name";
				show_table2_h_delimiter();
				$result=DBselect("select h.host,i.description,i.itemid from hosts h,items i where h.hostid=i.hostid and h.status in (0,2) and i.status=0 order by h.host,i.description");
				echo "<select name=\"itemid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$host_=DBget_field($result,$i,0);
					$description_=DBget_field($result,$i,1);
					$itemid_=DBget_field($result,$i,2);
					echo "<OPTION VALUE='$itemid_' ".iif($itemid==$itemid_,"selected","")."'>$host_: $description_";
				}
				echo "</SELECT>";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"itemid\" type=\"hidden\" size=1 value=\"$itemid\">";
			}

			if($resource == 1)
			{
				show_table2_v_delimiter();
				echo "Graph name";
				show_table2_h_delimiter();
				$result=DBselect("select graphid,name from graphs order by name");
				echo "<select name=\"graphid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$name_=DBget_field($result,$i,1);
					$graphid_=DBget_field($result,$i,0);
					echo "<OPTION VALUE='$graphid_' ".iif($graphid==$graphid_,"selected","").">$name_";
				}
				echo "</SELECT>";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"graphid\" type=\"hidden\" size=1 value=\"$graphid\">";
			}

			if($resource == 2)
			{
				show_table2_v_delimiter();
				echo "Map";
				show_table2_h_delimiter();
				$result=DBselect("select sysmapid,name from sysmaps order by name");
				echo "<select name=\"sysmapid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$name_=DBget_field($result,$i,1);
					$sysmapid_=DBget_field($result,$i,0);
					echo "<OPTION VALUE='$sysmapid_' ".iif($sysmapid==$sysmapid_,"selected","").">$name_";
				}
				echo "</SELECT>";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"sysmapid\" type=\"hidden\" size=1 value=\"$sysmapid\">";
			}

			if($resource!=2)
			{
				show_table2_v_delimiter();
				echo "Width";
				show_table2_h_delimiter();
				echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";
				show_table2_v_delimiter();
				echo "Height";
				show_table2_h_delimiter();
				echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"width\" type=\"hidden\" size=5 value=\"$width\">";
				echo "<input class=\"biginput\" name=\"height\" type=\"hidden\" size=5 value=\"$height\">";
			}

			show_table2_v_delimiter2();
			echo "<input type=\"submit\" name=\"register\" value=\"add\">";
			if(($screenitemid!=0)||($screengraphid!=0)) 
			{ 
				echo "<input type=\"submit\" name=\"register\" value=\"update\">";
			}
			echo "<input type=\"submit\" name=\"register\" value=\"delete\">";

			show_table2_header_end();
		}
		else if($graphid!=0)
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r><img src='chart2.php?graphid=$graphid&width=$width&height=$height&period=3600' border=0></a>";
		}
		else if($itemid!=0)
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r><img src='chart.php?itemid=$itemid&width=$width&height=$height&period=3600' border=0></a>";
		}
		else
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r>Empty</a>";
		}
		echo "</form>\n";
        
		echo "</TD>";
        }
        echo "</TR>\n";
        }
        echo "</TABLE>";


?>

<?php
	show_footer();
?>
