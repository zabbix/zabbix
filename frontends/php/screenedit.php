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
			if(isset($HTTP_GET_VARS["screenitemid"]))
			{
				delete_screen_item($HTTP_GET_VARS["screenitemid"]);
			}
			$result=add_screen_item($HTTP_GET_VARS["screenid"],$HTTP_GET_VARS["x"],$HTTP_GET_VARS["y"],$HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result,"Item added","Cannot add item");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_screen_item($HTTP_GET_VARS["screenitemid"]);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($gitemid);
		}
                if($HTTP_GET_VARS["register"]=="update")
                {
                        $result=update_screen_item($HTTP_GET_VARS["screenitemid"],$HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
                        show_messages($result,"Item updated","Cannot update item");
                }

	}
?>

<?php
	$screenid=$HTTP_GET_VARS["screenid"];
	$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
	$row=DBfetch($result);
	show_table_header($row["name"]);
	echo "<TABLE BORDER=1 COLS=".$row["cols"]." align=center WIDTH=100% BGCOLOR=\"#FFFFFF\"";
        for($r=0;$r<$row["rows"];$r++)
	{
	echo "<TR>";
	for($c=0;$c<$row["cols"];$c++)
	{
		echo "<TD align=\"center\">\n";

		echo "<a name=\"form\"></a>";
		echo "<form method=\"get\" action=\"screenedit.php\">";
		$iresult=DBSelect("select * from screens_items where screenid=$screenid and x=$c and y=$r");
        	if($iresult)
        	{
        		$irow=DBfetch($iresult);
        		$screenitemid=$irow["screenitemid"];
			$graphid=$irow["graphid"];
			$width=$irow["width"];
			$height=$irow["height"];
        	}
		else
		{
                	$screenitemid=0;
                	$graphid=0;
                	$width=100;
                	$height=50;
		}

		if(isset($HTTP_GET_VARS["register"])&&($HTTP_GET_VARS["register"]=="edit")&&($HTTP_GET_VARS["x"]==$c)&&($HTTP_GET_VARS["y"]==$r))
		{
        		show_table2_header_begin();
        		echo "Screen item configuration";
        		show_table2_v_delimiter();
        		echo "<input name=\"screenid\" type=\"hidden\" value=$screenid>";
			echo "<input name=\"x\" type=\"hidden\" value=$c>";
			echo "<input name=\"y\" type=\"hidden\" value=$r>";
			echo "<input name=\"screenitemid\" type=\"hidden\" value=$screenitemid>";

			echo "Graph name";
			show_table2_h_delimiter();

			if($graphid!=0) 
			{
				$result=DBselect("select name from graphs where graphid=$graphid");
				if($result)  $name=DBget_field($result,0,0);
				else $name="(none)";
			}
			else $name="(none)";
			
			$result=DBselect("select graphid,name from graphs");
			echo "<select name=\"graphid\" size=1>";
			echo "<OPTION VALUE='$graphid'>$name";

			for($i=0;$i<DBnum_rows($result);$i++)
			{
				$name_=DBget_field($result,$i,1);
				$graphid_=DBget_field($result,$i,0);
				echo "<OPTION VALUE='$graphid_'>$name_";
			}
			echo "</SELECT>";

			show_table2_v_delimiter();
			echo "Width";
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";
                        show_table2_v_delimiter();
                        echo "Height";
                        show_table2_h_delimiter();
                        echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

			show_table2_v_delimiter2();
			echo "<input type=\"submit\" name=\"register\" value=\"add\">";
			if($screenitemid!=0) 
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
