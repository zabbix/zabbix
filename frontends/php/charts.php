<?
	include "include/config.inc.php";
	$page["title"] = "User defined graphs";
	$page["file"] = "charts.php";

	$nomenu=0;
	if(isset($fullscreen))
	{
		$nomenu=1;
	}
	if(isset($graphid))
	{
		show_header($page["title"],30,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?
	if(!isset($fullscreen))
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
			if( isset($graphid) && ($graphid == $row["graphid"]) )
			{
				echo "<b>[";
			}
			echo "<a href='charts.php?graphid=".$row["graphid"]."'>".$row["name"]."</a>";
			if(isset($graphid) && ($graphid == $row["graphid"]) )
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

<?
	if(isset($graphid))
	{
		$result=DBselect("select name from graphs where graphid=$graphid");
		$row=DBfetch($result);
		if(isset($fullscreen))
		{
			$map="<a href=\"charts.php?graphid=$graphid\">".$row["name"]."</a>";
		}
		else
		{
			$map="<a href=\"charts.php?graphid=$graphid&fullscreen=1\">".$row["name"]."</a>";
		}
	}
	else
	{
		$map="Select graph to display";
	}
	if(!isset($from))
	{
		$from=0;
	}
	if(!isset($period))
	{
		$period=3600;
	}

	show_table_header($map);
	echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#EEEEEE>";
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($graphid))
	{
		echo "<IMG SRC=\"chart2.php?graphid=$graphid&period=$period&from=$from\">";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	if(isset($graphid)&&(!isset($fullscreen)))
	{
		echo("<div align=center>");
		echo("<hr>");
		$tmp=$from+12*14;
		echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
		echo("Week back</A>] ");

		$tmp=$from+12;
		echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
		echo("12h back</A>] ");
		
		$tmp=$from+1;
		echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
		echo("1h back</A>] ");

		$tmp=$period+3600;
		echo("[<A HREF=\"charts.php?graphid=$graphid&from=$from&period=$tmp\">");
		echo("+1h</A>] ");

		if ($period>3600) 
		{
			$tmp=$period-3600;
			echo("[<A HREF=\"charts.php?graphid=$graphid&from=$from&period=$tmp\">");
			echo("-1h</A>] ");
		}
		else
		{
			echo("[-1h]");
		}
	
		if ($from>0) // HOUR FORWARD
		{
			$tmp=$from-1;
			echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
			echo("1h forward</A>] ");
		}
		else
		{
			echo("[1h forward]");  
		}
		if (isset($From) && ($From>0))
		{
			$tmp=$from-12;
			echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
			echo("12h forward</A>] ");
		}
		else
		{
			echo("[12h forward]");
		}
	
		if (isset($From) && ($From>0))
		{
			$tmp=$from-12*14;
			echo("[<A HREF=\"charts.php?graphid=$graphid&from=$tmp&period=$period\">");
			echo("Week forward</A>] ");
		}
		else
		{
			echo("[Week forward]");
		}
		echo("</div>");
	}
	
?>

<?
	show_footer();
?>
