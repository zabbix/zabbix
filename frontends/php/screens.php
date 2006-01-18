<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	$page["title"] = "S_CUSTOM_SCREENS";
	$page["file"] = "screens.php";

	$nomenu=0;
	if(isset($_REQUEST["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($_REQUEST["screenid"]))
	{
		show_header($page["title"],1,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?php
	$effectiveperiod=navigation_bar_calc();
?>

<?php
	$_REQUEST["screenid"]=@iif(isset($_REQUEST["screenid"]),$_REQUEST["screenid"],get_profile("web.screens.screenid",0));
	update_profile("web.screens.screenid",$_REQUEST["screenid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
		if(isset($_REQUEST["screenid"])&&($_REQUEST["screenid"]==0))
		{
			unset($_REQUEST["screenid"]);
		}

		if(isset($_REQUEST["screenid"]))
		{
			$screen=get_screen_by_screenid($_REQUEST["screenid"]);
			$map=$screen["name"];
			$map=iif(isset($_REQUEST["fullscreen"]),
				"<a href=\"screens.php?screenid=".$_REQUEST["screenid"]."\">".$map."</a>",
				"<a href=\"screens.php?screenid=".$_REQUEST["screenid"]."&fullscreen=1\">".$map."</a>");
		}
		else
		{
			$map=S_SELECT_SCREEN_TO_DISPLAY;
		}

		$h1=S_SCREENS_BIG.nbsp(" / ").$map;

		$h2="";
		if(isset($_REQUEST["fullscreen"]))
		{
			$h2=$h2."<input name=\"fullscreen\" type=\"hidden\" value=".$_REQUEST["fullscreen"].">";
		}

		$h2=$h2."<select class=\"biginput\" name=\"screenid\" onChange=\"submit()\">";
		$h2=$h2.form_select("screenid",0,S_SELECT_SCREEN_DOT_DOT_DOT);

		$result=DBselect("select screenid,name from screens order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Screen","R",$row["screenid"]))
			{
				continue;
			}
			$h2=$h2.form_select("screenid",$row["screenid"],$row["name"]);
		}
		$h2=$h2."</select>";

		show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"screens.php\">","</form>");
?>

<?php
//	if(isset($_REQUEST["screenid"]))
	if( isset($_REQUEST["screenid"]) && check_right("Screen","R",$_REQUEST["screenid"]))
	{
		$screenid=$_REQUEST["screenid"];
		$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
		$row=DBfetch($result);
/*		if(isset($_REQUEST["fullscreen"]))
		{
			$map="<a href=\"screens.php?screenid=".$_REQUEST["screenid"]."\">".$row["name"]."</a>";
		}
		else
		{
			$map="<a href=\"screens.php?screenid=".$_REQUEST["screenid"]."&fullscreen=1\">".$row["name"]."</a>";
		}
	show_table_header($map);*/
	for($r=0;$r<$row["rows"];$r++)
	{
		for($c=0;$c<$row["cols"];$c++)
		{
			$spancheck[$r][$c]=1;
		}
	}
	for($r=0;$r<$row["rows"];$r++)
	{
		for($c=0;$c<$row["cols"];$c++)
		{
			$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
			$iresult=DBSelect($sql);
			$colspan=0;
			$rowspan=0;
			if(DBnum_rows($iresult)>0)
			{
				$irow=DBfetch($iresult);
				$colspan=$irow["colspan"];
				$rowspan=$irow["rowspan"];
			}
			for($i=0;$i<$rowspan;$i++)
				for($j=0;$j<$colspan;$j++)
					if(($i!=0)||($j!=0))	$spancheck[$r+$i][$c+$j]=0;
				
		}
	}

          echo "<TABLE BORDER=1 COLS=".$row["cols"]." align=center WIDTH=100% BGCOLOR=\"#FFFFFF\">\n";
          for($r=0;$r<$row["rows"];$r++)
          {
          echo "<TR>\n";
          for($c=0;$c<$row["cols"];$c++)
          {
		if($spancheck[$r][$c]==0)	continue;

		$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
		$iresult=DBSelect($sql);
		$colspan=0;
		$rowspan=0;
		if(DBnum_rows($iresult)>0)
		{
			$irow=DBfetch($iresult);
			$screenitemid=$irow["screenitemid"];
			$resource=$irow["resource"];
			$resourceid=$irow["resourceid"];
			$width=$irow["width"];
			$height=$irow["height"];
			$colspan=$irow["colspan"];
			$rowspan=$irow["rowspan"];
			$elements=$irow["elements"];
		}


		$tmp="";
		if($colspan!=0)
		{
			$tmp=$tmp." colspan=\"$colspan\" ";
			$c=$c+$colspan-1;
		}
		if($rowspan!=0)
		{
			$tmp=$tmp." rowspan=\"$rowspan\" ";
		}

               	echo "<TD align=\"center\" valign=\"top\" $tmp>\n";
		if(DBnum_rows($iresult)>0)
		{
			if($resource == 0)
			{
				echo "<a href=charts.php?graphid=$resourceid".url_param("period").url_param("inc").url_param("dec")."><img src='chart2.php?graphid=$resourceid&width=$width&height=$height&period=$effectiveperiod".url_param("from")."border=0' border=0></a>";
				//echo "<a href=charts.php?graphid=$resourceid><img src='chart2.php?graphid=$resourceid&width=$width&height=$height&".url_param("period").url_param("from")."border=0' border=0></a>";
			}
			else if($resource == 1)
			{
				echo "<a href=history.php?action=showhistory&itemid=$resourceid".url_param("period").url_param("inc").url_param("dec")."><img src='chart.php?itemid=$resourceid&width=$width&height=$height&period=$effectiveperiod".url_param("from")."border=0' border=0></a>";
				//echo "<a href=history.php?action=showhistory&itemid=$resourceid><img src='chart.php?itemid=$resourceid&width=$width&height=$height&".url_param("period").url_param("from")."border=0' border=0></a>";
			}
			else if($resource == 2)
			{
				echo get_map_imagemap($resourceid);
				echo "<img src='map.php?sysmapid=$resourceid&noedit=true&border=1' border=0 usemap=#links$resourceid>";
			}
			else if($resource == 3)
			{
				show_screen_plaintext($resourceid,$elements);
			}
		}
		else 
		{
			echo "&nbsp;";
		}
		echo "</TD>\n";
          }
          echo "</TR>\n";
          }
          echo "</TABLE>\n";
		navigation_bar("screens.php");
	}
	else
	{
//		show_table_header(S_SELECT_SCREEN_TO_DISPLAY);		
		echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "...";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>

<?php
	show_page_footer();
?>
