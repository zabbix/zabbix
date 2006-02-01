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
	$_REQUEST["screenid"]=get_request("screenid",get_profile("web.screens.screenid",0));
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
				if(isset($skip_field[$r][$c]))	continue;

				$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
				$iresult=DBSelect($sql);
				if(DBnum_rows($iresult)>0)
				{
					$irow=DBfetch($iresult);
					$colspan=$irow["colspan"];
					$rowspan=$irow["rowspan"];
				} else {
					$colspan=0;
					$rowspan=0;
				}
				for($i=0; $i < $rowspan || $i==0; $i++){
					for($j=0; $j < $colspan || $j==0; $j++){
						if($i!=0 || $j!=0)
							$skip_field[$r+$i][$c+$j]=1;
					}
				}
			}
		}

		$table = new CTable(
			new CLink("No rows in screen ".$row["name"],"screenconf.php?".
				"form=update&screenid=".$screenid),
			"screen_view");
	
		for($r=0;$r<$row["rows"];$r++)
		{
		$new_cols = array();
		for($c=0;$c<$row["cols"];$c++)
		{
			if(isset($skip_field[$r][$c]))		continue;
			
			$iresult=DBSelect("select * from screens_items".
				" where screenid=$screenid and x=$c and y=$r");

			if(DBnum_rows($iresult)>0)
			{
				$irow		= DBfetch($iresult);
				$screenitemid	= $irow["screenitemid"];
				$resource	= $irow["resource"];
				$resourceid	= $irow["resourceid"];
				$width		= $irow["width"];
				$height		= $irow["height"];
				$colspan	= $irow["colspan"];
				$rowspan	= $irow["rowspan"];
				$elements	= $irow["elements"];
			}
			else
			{
				$screenitemid	= 0;
				$screenitemid	= 0;
				$resource	= 0;
				$resourceid	= 0;
				$width		= 0;
				$height		= 0;
				$colspan	= 0;
				$rowspan	= 0;
				$elements	= 0;
			}

			if( ($screenitemid!=0) && ($resource==0) )
			{
				$item = new CLink(
					new CImg("chart2.php?graphid=$resourceid&width=$width&height=$height".
						"&period=3600' border=0"),
					"charts.php?graphid=$resourceid".url_param("period").
						url_param("inc").url_param("dec")
					);
			}
			else if( ($screenitemid!=0) && ($resource==1) )
			{
				$item = new CLink(
					new CImg("chart.php?itemid=$resourceid&width=$width&height=$height".
                                        	"&period=3600"),
					"history.php?action=showhistory&itemid=$resourceid".
						url_param("period").url_param("inc").url_param("dec")
					);
			}
			else if( ($screenitemid!=0) && ($resource==2) )
			{
				$item = new CImg("map.php?noedit=1&sysmapid=$resourceid".
	                                        "&width=$width&height=$height&period=3600");
			}
			else if( ($screenitemid!=0) && ($resource==3) )
			{
				$item = array(get_screen_plaintext($resourceid,$elements));
			}
			else
			{
				$item = SPACE;
			}
			$new_col = new CCol($item,"screen_view");

			if($colspan) $new_col->SetColSpan($colspan);
			if($rowspan) $new_col->SetRowSpan($rowspan);

			array_push($new_cols, $new_col);
		}
		$table->AddRow(new CRow($new_cols));
		}

		$table->Show();
		
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
	if(!isset($_REQUEST["fullscreen"]))
	{
		show_page_footer();
	}
?>
