<?php
	include "include/config.inc.php";
	$page["title"] = "User defined screens";
	$page["file"] = "screens.php";

	$nomenu=0;
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($HTTP_GET_VARS["screenid"]))
	{
		show_header($page["title"],60,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?php
	if(!isset($HTTP_GET_VARS["fullscreen"]))
	{
		show_table_header_begin();
		echo "SCREENS";

		show_table_v_delimiter();

		echo "<font size=2>";

		$result=DBselect("select screenid,name,cols,rows from screens order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Screen","R",$row["screenid"]))
			{
				continue;
			}
			if( isset($HTTP_GET_VARS["screenid"]) && ($HTTP_GET_VARS["screenid"] == $row["screenid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='screens.php?screenid=".$row["screenid"]."'>".$row["name"]."</a>";
			if(isset($HTTP_GET_VARS["screenid"]) && ($HTTP_GET_VARS["screenid"] == $row["screenid"]) )
			{
				echo "]</b>";
			}
			echo " ";
		}

		if(DBnum_rows($result) == 0)
		{
			echo "No screens to display";
		}

		echo "</font>";
		show_table_header_end();
		echo "<br>";
	}

?>

<?php
	if(isset($HTTP_GET_VARS["screenid"]))
	{
		$screenid=$HTTP_GET_VARS["screenid"];
		$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
		$row=DBfetch($result);
		if(isset($HTTP_GET_VARS["fullscreen"]))
		{
			$map="<a href=\"screens.php?screenid=".$HTTP_GET_VARS["screenid"]."\">".$row["name"]."</a>";
		}
		else
		{
			$map="<a href=\"screens.php?screenid=".$HTTP_GET_VARS["screenid"]."&fullscreen=1\">".$row["name"]."</a>";
		}
	show_table_header($map);
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
			$graphid=0;
			$itemid=0;
			$width=100;
			$height=50;
		}

		$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
		$iresult=DBSelect($sql);
		if(DBnum_rows($iresult)>0)
		{
			$irow=DBfetch($iresult);
			$screenitemid=$irow["screenitemid"];
			$graphid=$irow["graphid"];
			$width=$irow["width"];
			$height=$irow["height"];
		}
		$sql="select * from screens_graphs where screenid=$screenid and x=$c and y=$r";
		$iresult=DBSelect($sql);
		if(DBnum_rows($iresult)>0)
		{
			$irow=DBfetch($iresult);
			$screengraphid=$irow["screengraphid"];
			$itemid=$irow["itemid"];
			$width=$irow["width"];
			$height=$irow["height"];
		}

		if($graphid!=0)
		{
			echo "<a href=charts.php?graphid=$graphid><img src='chart2.php?graphid=$graphid&width=$width&height=$height&period=3600&noborder=1' border=0></a>";
		}
		if($itemid!=0)
		{
			echo "<a href=history.php?action=showhistory&itemid=$itemid><img src='chart.php?itemid=$itemid&width=$width&height=$height&period=3600&noborder=1' border=0></a>";
		}
		echo "</form>\n";
		echo "</TD>";
          }
          echo "</TR>\n";
          }
          echo "</TABLE>";
	}
	else
	{
		show_table_header("Please select screen to display");		
	}
?>

<?php
	show_footer();
?>
