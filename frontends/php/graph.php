<?
	include "include/config.inc.php";
	$page["title"] = "Configuration of graph";
	$page["file"] = "graph.php";
	show_header($page["title"],0,0);
?>

<?
	show_table_header("CONFIGURATION OF GRAPH");
	echo "<br>";
?>

<?
	if(!check_right("Graph","R",$HTTP_GET_VARS["graphid"]))
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
			$result=add_item_to_graph($HTTP_GET_VARS["graphid"],$HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["color"]);
			show_messages($result,"Item added","Cannot add item");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_graphs_item($HTTP_GET_VARS["gitemid"]);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($gitemid);
		}
	}
?>

<?
	$result=DBselect("select name from graphs where graphid=".$HTTP_GET_VARS["graphid"]);
	$row=DBfetch($result);
	show_table_header($row["name"]);
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	echo "<IMG SRC=\"chart2.php?graphid=".$HTTP_GET_VARS["graphid"]."&period=3600&from=0\">";
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	show_table_header("DISPLAYED PARAMETERS");
	echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TD WIDTH=10% NOSAVE><B>Host</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Parameter</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Color</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";

	$sql="select i.itemid,h.host,i.description,gi.gitemid,gi.color from hosts h,graphs_items gi,items i where i.itemid=gi.itemid and gi.graphid=".$HTTP_GET_VARS["graphid"]." and h.hostid=i.hostid";
	$result=DBselect($sql);
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }

		echo "<TD>".$row["host"]."</TD>";
		echo "<TD><a href=\"chart.php?itemid=".$row["itemid"]."&period=3600&from=0\">".$row["description"]."</a></TD>";
		echo "<TD>".$row["color"]."</TD>";
		echo "<TD><A HREF=\"graph.php?register=delete&graphid=".$HTTP_GET_VARS["graphid"]."&gitemid=".$row["gitemid"]."\">Delete</A></TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?
	echo "<br>";
	echo "<a name=\"form\"></a>";

	show_table2_header_begin();
	echo "New item for graph";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"graph.php\">";
	echo "<input name=\"graphid\" type=\"hidden\" value=".$HTTP_GET_VARS["graphid"].">";
	if(isset($HTTP_GET_VARS["gitemid"]))
	{
		echo "<input name=\"gitemid\" type=\"hidden\" value=".$HTTP_GET_VARS["gitemid"].">";
	}

	echo "Parameter";
	show_table2_h_delimiter();
	$result=DBselect("select h.host,i.description,i.itemid from hosts h,items i where h.hostid=i.hostid and h.status in (0,2) and i.status=0 order by h.host,i.description");
	echo "<select name=\"itemid\" size=1>";
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		$host_=DBget_field($result,$i,0);
		$description_=DBget_field($result,$i,1);
		$itemid_=DBget_field($result,$i,2);
		echo "<OPTION VALUE='$itemid_'>$host_: $description_";
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Color";
	show_table2_h_delimiter();
	echo "<select name=\"color\" size=1>";
	echo "<OPTION VALUE='Black'>Black";
	echo "<OPTION VALUE='Blue'>Blue";
	echo "<OPTION VALUE='Cyan'>Cyan";
	echo "<OPTION VALUE='Dark Blue'>Dark blue";
	echo "<OPTION VALUE='Dark Green'>Dark green";
	echo "<OPTION VALUE='Dark Red'>Dark red";
	echo "<OPTION VALUE='Dark Yellow'>Dark yellow";
	echo "<OPTION VALUE='Green'>Green";
	echo "<OPTION VALUE='Red'>Red";
	echo "<OPTION VALUE='White'>White";
	echo "<OPTION VALUE='Yellow'>Yellow";
	echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";

	show_table2_header_end();
?>

<?
	show_footer();
?>
