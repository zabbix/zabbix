<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	include "include/config.inc.php";
	$page["title"] = "User defined graphs";
	$page["file"] = "charts.php";

	$nomenu=0;
	if(isset($HTTP_GET_VARS["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($HTTP_GET_VARS["graphid"]) && !isset($HTTP_GET_VARS["period"]))
	{
		show_header($page["title"],30,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}

?>

<?php
// BEGIN - IGMI - keep default value
	if(!isset($HTTP_GET_VARS["keep"]))
	{
		$HTTP_GET_VARS["keep"]=1;
	}
// END - IGMI - keep default value

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
// BEGIN - IGMI - keep support
			$str="";
			if(isset($HTTP_GET_VARS["keep"]))
			{
				if($HTTP_GET_VARS["keep"] == 1)
				{
					if(isset($HTTP_GET_VARS["from"]))
					{
						$str=$str."&from=".$HTTP_GET_VARS["from"];
					}
					if(isset($HTTP_GET_VARS["period"]))
					{
						$str=$str."&period=".$HTTP_GET_VARS["period"];
					}
				}
				$str=$str."&keep=".$HTTP_GET_VARS["keep"];
			}
			echo "<a href='charts.php?graphid=".$row["graphid"].$str."'>".$row["name"]."</a>";
// END - IGMI - keep support
//			echo "<a href='charts.php?graphid=".$row["graphid"]."'>".$row["name"]."</a>";
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
		$str="";
		if(isset($HTTP_GET_VARS["from"]))
		{
			$str=$str."&from=".$HTTP_GET_VARS["from"];
		}
		if(isset($HTTP_GET_VARS["period"]))
		{
			$str=$str."&period=".$HTTP_GET_VARS["period"];
		}
// BEGIN - IGMI - keep support added
		if(isset($HTTP_GET_VARS["fullscreen"]))
		{
			$map="<a href=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"].$str."&keep=".$HTTP_GET_VARS["keep"]."\">".$row["name"]."</a>";
		}
		else
		{
			$map="<a href=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&fullscreen=1".$str."&keep=".$HTTP_GET_VARS["keep"]."\">".$row["name"]."</a>";
		}
// END - IGMI - keep support added
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
	echo "<TR BGCOLOR=#DDDDDD>";
	echo "<TD ALIGN=CENTER>";
	if(isset($HTTP_GET_VARS["graphid"]))
	{
		echo "<script language=\"JavaScript\">";
		echo "document.write(\"<IMG SRC='chart2.php?graphid=".$HTTP_GET_VARS["graphid"]."&period=".$HTTP_GET_VARS["period"]."&from=".$HTTP_GET_VARS["from"]."&width=\"+(document.width-108)+\"'>\")";
		echo "</script>";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

	if(isset($HTTP_GET_VARS["graphid"])/*&&(!isset($HTTP_GET_VARS["fullscreen"]))*/)
	{
// BEGIN - IGMI - just another way of navigation
	echo "<TABLE BORDER=0 align=center COLS=2 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=#FFFFFF>";
	echo "<TD ALIGN=LEFT>";
		echo("<div align=left>");

		echo("<b>Set&nbsp;Period:</b>&nbsp;(&nbsp;");

		$hour=3600;
		foreach(array(1,2,4,8,12,24) as $count){
			$tmp=$hour*$count;
			echo("[<A HREF=\"charts.php?period=$tmp".url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">");
			echo($count."h</A>]&nbsp;");
		}

		echo("[<A HREF=\"charts.php?period=".(7*24*3600).url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">week</A>]&nbsp;");
		echo("[<A HREF=\"charts.php?period=".(30*24*3600).url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">month</A>]&nbsp;");
		echo("[<A HREF=\"charts.php?period=".(365*24*3600).url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">year</A>]&nbsp;");

            echo("or&nbsp;");
		$tmp=$HTTP_GET_VARS["period"]+$hour;
		echo("[<A HREF=\"charts.php?period=$tmp".url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">");
		echo("+1h</A>]&nbsp;");

		if ($HTTP_GET_VARS["period"]>$hour) 
		{
			$tmp=$HTTP_GET_VARS["period"]-$hour;
//			echo("[<A HREF=\"charts.php?graphid=".$HTTP_GET_VARS["graphid"]."&from=".$HTTP_GET_VARS["from"]."&period=".$tmp."&keep=".$HTTP_GET_VARS["keep"]."\">");
			echo("[<A HREF=\"charts.php?period=$tmp".url_param("graphid").url_param("from").url_param("keep").url_param("fullscreen")."\">");
			echo("-1h</A>]&nbsp;");
		}
		else
		{
			echo("[-1h]&nbsp;");
		}

		echo(")");

		echo(" <b>Go&nbsp;Back:</b>&nbsp;(&nbsp;");

		$day=24;
		foreach(array(0,1,2,7,14) as $count){
			$tmp=$day*$count;
			echo("[<A HREF=\"charts.php?from=$tmp".url_param("graphid").url_param("period").url_param("keep").url_param("fullscreen")."\">");
			echo($count."d</A>]&nbsp;");
		}
            
		echo(")");

		if(isset($HTTP_GET_VARS["keep"]))
		{
			echo(" <b>Keep&nbsp;period&nbsp;=</b>&nbsp;");
			if($HTTP_GET_VARS["keep"] == 1)
			{
				echo("<A HREF=\"charts.php?keep=0".url_param("graphid").url_param("from").url_param("period").url_param("fullscreen")."\">On</a>");
			}
			else
			{
				echo("<A HREF=\"charts.php?keep=1".url_param("graphid").url_param("from").url_param("period").url_param("fullscreen")."\">Off</a>");
			}
		}
		echo("</div>");

	echo "</TD>";
	echo "<TD BGCOLOR=#FFFFFF WIDTH=15% ALIGN=RIGHT>";
//		echo("<div align=right>");
		echo "<form method=\"put\" action=\"charts.php\">";
		echo "<input name=\"graphid\" type=\"hidden\" value=\"".$HTTP_GET_VARS["graphid"]."\" size=12>";
		echo "<input name=\"sdate\"  value=\"yyyymmdd\" size=8>";
		echo "<input class=\"button\" type=\"submit\" name=\"action\" value=\"go\">";
		echo "</form>";
//		echo("</div>");
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";

// END - IGMI - just another way of navigation
	}
	
?>

<?php
	show_footer();
?>
