<?
	include "include/config.inc.php";
	$page["title"] = "Graphs";
	$page["file"] = "graphs.php";
	show_header($page["title"],0,0);
?>

<?
	show_table_header("CONFIGURATION OF GRAPHS");
	echo "<br>";
?>

<?
	if(!check_right("Graph","U",0))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_graph($HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result,"Graph added","Cannot add graph");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_graph($HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["name"],$HTTP_GET_VARS["width"],$HTTP_GET_VARS["height"]);
			show_messages($result,"Graph updated","Cannot update graph");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_graph($HTTP_GET_VARS["graphid"]);
			show_messages($result,"Graph deleted","Cannot delete graph");
			unset($HTTP_GET_VARS["graphid"]);
		}
	}
?>

<?
	show_table_header("GRAPHS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=5% NOSAVE><B>Id</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Name</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Width</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Height</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select g.graphid,g.name,g.width,g.height from graphs g order by g.name");
	$col=0;
	while($row=DBfetch($result))
	{
		if(!check_right("Graph","R",$row["graphid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["graphid"]."</TD>";
		echo "<TD><a href=\"graph.php?graphid=".$row["graphid"]."\">".$row["name"]."</a></TD>";
		echo "<TD>".$row["width"]."</TD>";
		echo "<TD>".$row["height"]."</TD>";
		echo "<TD><A HREF=\"graphs.php?graphid=".$row["graphid"]."#form\">Change</A> - ";
		echo "<A HREF=\"graphs.php?register=delete&graphid=".$row["graphid"]."\">Delete</A></TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["graphid"]))
	{
		$result=DBselect("select g.graphid,g.name,g.width,g.height from graphs g where graphid=".$HTTP_GET_VARS["graphid"]);
		$row=DBfetch($result);
		$name=$row["name"];
		$width=$row["width"];
		$height=$row["height"];
	}
	else
	{
		$name="";
		$width=900;
		$height=200;
	}

	echo "<br>";
	show_table2_header_begin();
	echo "New graph";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"graphs.php\">";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<input name=\"graphid\" type=\"hidden\" value=".$HTTP_GET_VARS["graphid"].">";
	}
	echo "Name";
	show_table2_h_delimiter();
	echo "<input name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter();
	echo "Width";
	show_table2_h_delimiter();
	echo "<input name=\"width\" size=5 value=\"$width\">";

	show_table2_v_delimiter();
	echo "Height";
	show_table2_h_delimiter();
	echo "<input name=\"height\" size=5 value=\"$height\">";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();
?>

<?
	show_footer();
?>
