<?php
	include "include/config.inc.php";
	$page["title"] = "User defined graphs";
	$page["file"] = "charts.php";

	$nomenu=0;
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		show_header($page["title"],30,$nomenu);
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
		echo "GRAPHS";

		show_table_v_delimiter();

		echo "<font size=2>";

		$result=DBselect("select graphid,name from graphs order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Graph","R",$row["graphid"]))
			{
				continue;
			}
			if( isset($HTTP_GET_VARS["graphid"]) && ($HTTP_GET_VARS["graphid"] == $row["graphid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='charts.php?graphid=".$row["graphid"]."'>".$row["name"]."</a>";
			if(isset($HTTP_GET_VARS["graphid"]) && ($HTTP_GET_VARS["graphid"] == $row["graphid"]) )
			{
				echo "]</b>";
			}
			echo " ";
		}

		if(DBnum_rows($result) == 0)
		{
			echo "No graphs to display";
		}

		echo "</font>";
		show_table_header_end();
		echo "<br>";
	}

?>

<?php
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		$result=DBselect("select name from graphs where graphid=".$HTTP_GET_VARS["graphid"]);
		$row=DBfetch($result);
		if(isset($HTTP_GET_VARS["fullscreen"]))
		{
			$map="<a href=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."\">".$row["name"]."</a>";
		}
		else
		{
			$map="<a href=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&fullscreen=1\">".$row["name"]."</a>";
		}
	}
	else
	{
		$map="Select graph to display";
	}
	if(!isset($HTTP_GET_VARS["from"]))
	{
		$HTTP_GET_VARS["from"]=0;
	}
	if(!isset($HTTP_GET_VARS["period"]))
	{
		$HTTP_GET_VARS["period"]=3600;
	}

	show_table_header($map);
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<script language=\"JavaScript\">";
		echo "document.write(\"<IMG SRC='chart2.php?graphid=".$HTTP_GET_VARS["graphid"]."&period=".$HTTP_GET_VARS["period"]."&from=".$HTTP_GET_VARS["from"]."&width=\"+(document.width-108)+\">\")";
		echo "</script>";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	if(isset($HTTP_GET_VARS["graphid"])&&(!isset($HTTP_GET_VARS["fullscreen"])))
	{
		echo("<div align=center>");
		echo("<hr>");
		$tmp=$HTTP_GET_VARS["from"]+12*14;
		echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
		echo("Week back</A>] ");

		$tmp=$HTTP_GET_VARS["from"]+12;
		echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
		echo("12h back</A>] ");
		
		$tmp=$HTTP_GET_VARS["from"]+1;
		echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
		echo("1h back</A>] ");

		$tmp=$HTTP_GET_VARS["period"]+3600;
		echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=".$HTTP_GET_VARS["from"]."&period=$tmp\">");
		echo("+1h</A>] ");

		if ($HTTP_GET_VARS["period"]>3600) 
		{
			$tmp=$HTTP_GET_VARS["period"]-3600;
			echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=".$HTTP_GET_VARS["from"]."&period=$tmp\">");
			echo("-1h</A>] ");
		}
		else
		{
			echo("[-1h]");
		}
	
		if ($HTTP_GET_VARS["from"]>0) // HOUR FORWARD
		{
			$tmp=$HTTP_GET_VARS["from"]-1;
			echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
			echo("1h forward</A>] ");
		}
		else
		{
			echo("[1h forward]");  
		}
		if (isset($HTTP_GET_VARS["from"]) && ($HTTP_GET_VARS["from"]>0))
		{
			$tmp=$HTTP_GET_VARS["from"]-12;
			echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
			echo("12h forward</A>] ");
		}
		else
		{
			echo("[12h forward]");
		}
	
		if (isset($HTTP_GET_VARS["from"]) && ($HTTP_GET_VARS["from"]>0))
		{
			$tmp=$HTTP_GET_VARS["from"]-12*14;
			echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=$tmp&period=".$HTTP_GET_VARS["period"]."\">");
			echo("Week forward</A>] ");
		}
		else
		{
			echo("[Week forward]");
		}
		echo("</div>");
	}
	
?>

<?php
	show_footer();
?>
