<?php
	include "include/config.inc.php";
	$page["title"] = "User defined screens";
	$page["file"] = "screens.php";

	$nomenu=0;
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($HTTP_GET_VARS["scid"]))
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

		$result=DBselect("select scid,name,cols,rows from screens order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Screen","R",$row["scid"]))
			{
				continue;
			}
			if( isset($HTTP_GET_VARS["scid"]) && ($HTTP_GET_VARS["scid"] == $row["scid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='screens.php?scid=".$row["scid"]."'>".$row["name"]."</a>";
			if(isset($HTTP_GET_VARS["scid"]) && ($HTTP_GET_VARS["scid"] == $row["scid"]) )
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
	if(isset($HTTP_GET_VARS["scid"]))
	{
          $scid=$HTTP_GET_VARS["scid"];
          $result=DBselect("select name,cols,rows from screens where scid=$scid");
          $row=DBfetch($result);
          echo "<TABLE BORDER=1 COLS=".$row["cols"]." align=center WIDTH=100% BGCOLOR=\"#FFFFFF\"";
          for($r=0;$r<$row["rows"];$r++)
          {
          echo "<TR>";
          for($c=0;$c<$row["cols"];$c++)
          {
                echo "<TD align=\"center\">\n";

                echo "<a name=\"form\"></a>";
                echo "<form method=\"get\" action=\"screenedit.php\">";
                $iresult=DBSelect("select * from screens_items where scid=$scid and x=$c and y=$r");
                if($iresult)
                {
                        $irow=DBfetch($iresult);
                        $scitemid=$irow["scitemid"];
                        $graphid=$irow["graphid"];
                        $width=$irow["width"];
                        $height=$irow["height"];
                }
                else
                {
                        $scitemid=0;
                        $graphid=0;
                        $width=100;
                        $height=50;
                }

                if($graphid!=0)
                {
                        echo "<a href=charts.php?graphid=$graphid><img src='chart2.php?graphid=$graphid&width=$width&height=$height&period=3600&noborder=1' border=0></a>";
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
