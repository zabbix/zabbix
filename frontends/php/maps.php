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
	$page["title"] = "S_NETWORK_MAPS";
	$page["file"] = "maps.php";

	$nomenu=0;
	if(isset($_REQUEST["fullscreen"]))
	{
		$nomenu=1;
	}
	if(isset($_REQUEST["sysmapid"]))
	{
		show_header($page["title"],1,$nomenu);
	}
	else
	{
		show_header($page["title"],0,$nomenu);
	}
?>

<?php
	if(isset($_REQUEST["sysmapid"])&&!check_right("Network map","R",$_REQUEST["sysmapid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>

<?php
	$_REQUEST["sysmapid"]=@iif(isset($_REQUEST["sysmapid"]),$_REQUEST["sysmapid"],get_profile("web.maps.sysmapid",0));
	update_profile("web.maps.sysmapid",$_REQUEST["sysmapid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
//	if(!isset($_REQUEST["fullscreen"]))
	{
		show_table3_header_begin();

		if(isset($_REQUEST["sysmapid"])&&($_REQUEST["sysmapid"]==0))
		{
			unset($_REQUEST["sysmapid"]);
		}

		if(isset($_REQUEST["sysmapid"]))
		{
			$result=get_map_by_sysmapid($_REQUEST["sysmapid"]);
			$h1=$result["name"];
			$h1=iif(isset($_REQUEST["fullscreen"]),
				"<a href=\"maps.php?sysmapid=".$_REQUEST["sysmapid"]."\">".$h1."</a>",
				"<a href=\"maps.php?sysmapid=".$_REQUEST["sysmapid"]."&fullscreen=1\">".$h1."</a>");
		}
		else
		{
			$h1=S_SELECT_MAP_TO_DISPLAY;
		}

		$h1=S_NETWORK_MAPS_BIG.nbsp(" / ").$h1;

		$h2="";

		if(isset($_REQUEST["fullscreen"]))
		{
			$h2=$h2."<input name=\"fullscreen\" type=\"hidden\" value=".$_REQUEST["fullscreen"].">";
		}

		if(isset($_REQUEST["sysmapid"])&&($_REQUEST["sysmapid"]==0))
		{
			unset($_REQUEST["sysmapid"]);
		}

		$h2=$h2."<select class=\"biginput\" name=\"sysmapid\" onChange=\"submit()\">";
		$h2=$h2.form_select("sysmapid",0,S_SELECT_MAP_DOT_DOT_DOT);

		$result=DBselect("select sysmapid,name from sysmaps order by name");
		while($row=DBfetch($result))
		{
			if(!check_right("Network map","R",$row["sysmapid"]))
			{
				continue;
			}
			$h2=$h2.form_select("sysmapid",$row["sysmapid"],$row["name"]);
		}
		$h2=$h2."</select>";

		show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"maps.php\">","</form>");
	}
?>

<?php
	echo "<TABLE BORDER=0 align=center WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=\"#EEEEEE\">";
	echo "<TR BGCOLOR=\"#DDDDDD\">";
	echo "<TD ALIGN=CENTER>";
	if(isset($_REQUEST["sysmapid"]))
	{
		echo get_map_imagemap($_REQUEST["sysmapid"]);
		echo "<IMG SRC=\"map.php?noedit=1&sysmapid=".$_REQUEST["sysmapid"]."\" border=0 usemap=#links".$_REQUEST["sysmapid"].">";
	}
	else
	{
		echo "...";
	}
	echo "</TD>";
	echo "</TR>";
	echo "</TABLE>";
?>

<?php
	show_page_footer();
?>
