<?php
	include "include/config.inc.php";
	$page["title"] = "Screens";
	$page["file"] = "screenconf.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header("CONFIGURATION OF SCREENS");
	echo "<br>";
?>

<?php
	if(!check_right("Screen","U",0))
	{
//		show_table_header("<font color=\"AA0000\">No permissions !</font>");
//		show_footer();
//		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_screen($HTTP_GET_VARS["name"],$HTTP_GET_VARS["cols"],$HTTP_GET_VARS["rows"]);
			show_messages($result,"Screen added","Cannot add screen");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_screen($HTTP_GET_VARS["scid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["cols"],$HTTP_GET_VARS["rows"]);
			show_messages($result,"Screen updated","Cannot update screen");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_screen($HTTP_GET_VARS["scid"]);
			show_messages($result,"Screen deleted","Cannot delete screen");
			unset($HTTP_GET_VARS["scid"]);
		}
	}
?>

<?php
	show_table_header("SCREENS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>Id</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Name</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Columns</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Rows</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select scid,name,cols,rows from screens order by name");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Screen","R",$row["scid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["scid"]."</TD>";
		echo "<TD><a href=\"screenedit.php?scid=".$row["scid"]."\">".$row["name"]."</a></TD>";
		echo "<TD>".$row["cols"]."</TD>";
		echo "<TD>".$row["rows"]."</TD>";
		echo "<TD><A HREF=\"screenconf.php?scid=".$row["scid"]."#form\">Change</A> - ";
		echo "<A HREF=\"screenconf.php?register=delete&scid=".$row["scid"]."\">Delete</A></TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["scid"]))
	{
		$result=DBselect("select scid,name,cols,rows from screens g where scid=".$HTTP_GET_VARS["scid"]);
		$row=DBfetch($result);
		$name=$row["name"];
		$cols=$row["cols"];
		$rows=$row["rows"];
	}
	else
	{
		$name="";
		$cols=1;
		$rows=1;
	}

	echo "<br>";
	show_table2_header_begin();
	echo "New screen";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"screenconf.php\">";
	if(isset($HTTP_GET_VARS["scid"]))
	{
		echo "<input class=\"biginput\" name=\"scid\" type=\"hidden\" value=".$HTTP_GET_VARS["scid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo "Columns";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"cols\" size=5 value=\"$cols\">";

	show_table2_v_delimiter();
	echo "Rows";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"rows\" size=5 value=\"$rows\">";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["scid"]))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>
