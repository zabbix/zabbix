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
	$page["title"] = "S_CONFIGURATION_OF_SCREENS";
	$page["file"] = "screenedit.php";
	show_header($page["title"],0,0);
?>

<?php
	show_table_header(S_CONFIGURATION_OF_SCREEN_BIG);
	echo "<br>";
?>

<?php
	if(!check_right("Screen","U",$_GET["screenid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
//			if(isset($_GET["screenitemid"]))
//			{
//				delete_screen_item($_GET["screenitemid"]);
//				unset($_GET["screenitemid"]);
//			}
			$result=add_screen_item($_GET["resource"],$_GET["screenid"],$_GET["x"],$_GET["y"],$_GET["resourceid"],$_GET["width"],$_GET["height"],$_GET["colspan"],$_GET["rowspan"]);
			unset($_GET["x"]);
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_screen_item($_GET["screenitemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_GET["x"]);
		}
                if($_GET["register"]=="update")
                {
                        $result=update_screen_item($_GET["screenitemid"],$_GET["resource"],$_GET["resourceid"],$_GET["width"],$_GET["height"],$_GET["colspan"],$_GET["rowspan"]);
                        show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
			unset($_GET["x"]);
                }
		unset($_GET["register"]);

	}
?>

<?php
	$screenid=$_GET["screenid"];
	$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
	$row=DBfetch($result);
	show_table_header("<a href=\"screenedit.php?screenid=$screenid\">".$row["name"]."</a>");
	echo "<TABLE BORDER=1 COLS=".$row["cols"]." align=center WIDTH=100% BGCOLOR=\"#FFFFFF\"";
        for($r=0;$r<$row["rows"];$r++)
	{
	echo "<TR>";
	for($c=0;$c<$row["cols"];$c++)
	{
		$iresult=DBSelect("select * from screens_items where screenid=$screenid and x=$c and y=$r");
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
        	}
		else
		{
        		$screenitemid=0;
			$resource=0;
			$resourceid=0;
			$width=500;
			$height=100;
			$colspan=0;
			$rowspan=0;
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
#			$r=$r+$rowspan-1;
		}
		echo "<TD align=\"center\" valign=\"top\" $tmp>\n";
		echo "<a name=\"form\"></a>";
		echo "<form method=\"get\" action=\"screenedit.php\">";

		if(isset($_GET["x"])&&($_GET["x"]==$c)&&($_GET["y"]==$r))
		{
			$resource=@iif(isset($_GET["resource"]),$_GET["resource"],$resource);
			$resourceid=@iif(isset($_GET["resourceid"]),$_GET["resourceid"],$resourceid);
			$screenitemid=@iif(isset($_GET["screenitemid"]),$_GET["screenitemid"],$screenitemid);
			$width=@iif(isset($_GET["width"]),$_GET["width"],$width);
			$height=@iif(isset($_GET["height"]),$_GET["height"],$height);
			$colspan=@iif(isset($_GET["colspan"]),$_GET["colspan"],$colspan);
			$rowspan=@iif(isset($_GET["rowspan"]),$_GET["rowspan"],$colspan);

        		show_form_begin("screenedit.cell");
        		echo S_SCREEN_CELL_CONFIGURATION;

        		echo "<input name=\"screenid\" type=\"hidden\" value=$screenid>";
        		echo "<input name=\"screenitemid\" type=\"hidden\" value=$screenitemid>";
			echo "<input name=\"x\" type=\"hidden\" value=$c>";
			echo "<input name=\"y\" type=\"hidden\" value=$r>";
//			echo "<input name=\"resourceid\" type=\"hidden\" value=$resourceid>";
//			echo "<input name=\"resource\" type=\"hidden\" value='$resource'>";

			show_table2_v_delimiter();
			echo S_RESOURCE;
			show_table2_h_delimiter();
			echo "<select name=\"resource\" size=1 onChange=\"submit()\">";
			echo "<OPTION VALUE='0' ".iif($resource==0,"selected","").">".S_GRAPH;
			echo "<OPTION VALUE='1' ".iif($resource==1,"selected","").">".S_SIMPLE_GRAPH;
			echo "<OPTION VALUE='2' ".iif($resource==2,"selected","").">".S_MAP;
			echo "<OPTION VALUE='3' ".iif($resource==3,"selected","").">".S_PLAIN_TEXT;
			echo "</SELECT>";

// Simple graph
			if($resource == 1)
			{
				show_table2_v_delimiter();
				echo nbsp(S_PARAMETER);
				show_table2_h_delimiter();
				$result=DBselect("select h.host,i.description,i.itemid,i.key_ from hosts h,items i where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and i.status=0 order by h.host,i.description");
				echo "<select name=\"resourceid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$host_=DBget_field($result,$i,0);
					$description_=item_description(DBget_field($result,$i,1),DBget_field($result,$i,3));
					$itemid_=DBget_field($result,$i,2);
					echo "<OPTION VALUE='$itemid_' ".iif($resourceid==$itemid_,"selected","").">$host_: $description_";
				}
				echo "</SELECT>";
			}
// Plain text
			else if($resource == 3)
			{
				show_table2_v_delimiter();
				echo nbsp(S_PARAMETER);
				show_table2_h_delimiter();
				$result=DBselect("select h.host,i.description,i.itemid,i.key_ from hosts h,items i where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and i.status=0 order by h.host,i.description");
				echo "<select name=\"resourceid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$host_=DBget_field($result,$i,0);
					$description_=item_description(DBget_field($result,$i,1),DBget_field($result,$i,3));
					$itemid_=DBget_field($result,$i,2);
					echo "<OPTION VALUE='$itemid_' ".iif($resourceid==$itemid_,"selected","").">$host_: $description_";
				}
				echo "</SELECT>";
			}
// User-defined graph
			else if($resource == 0)
			{
				show_table2_v_delimiter();
				echo nbsp(S_GRAPH_NAME);
				show_table2_h_delimiter();
				$result=DBselect("select graphid,name from graphs order by name");
				echo "<select name=\"resourceid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$name_=DBget_field($result,$i,1);
					$graphid_=DBget_field($result,$i,0);
					echo "<OPTION VALUE='$graphid_' ".iif($resourceid==$graphid_,"selected","").">$name_";
				}
				echo "</SELECT>";
			}
// Map
			else if($resource == 2)
			{
				show_table2_v_delimiter();
				echo S_MAP;
				show_table2_h_delimiter();
				$result=DBselect("select sysmapid,name from sysmaps order by name");
				echo "<select name=\"resourceid\" size=1>";
				echo "<OPTION VALUE='0'>(none)";
				for($i=0;$i<DBnum_rows($result);$i++)
				{
					$name_=DBget_field($result,$i,1);
					$sysmapid_=DBget_field($result,$i,0);
					echo "<OPTION VALUE='$sysmapid_' ".iif($resourceid==$sysmapid_,"selected","").">$name_";
				}
				echo "</SELECT>";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"resourceid\" type=\"hidden\" size=1 value=\"$resourceid\">";
			}

			if($resource!=2)
			{
				show_table2_v_delimiter();
				echo S_WIDTH;
				show_table2_h_delimiter();
				echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";
				show_table2_v_delimiter();
				echo S_HEIGHT;
				show_table2_h_delimiter();
				echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";
			}
			else
			{
				echo "<input class=\"biginput\" name=\"width\" type=\"hidden\" size=5 value=\"$width\">";
				echo "<input class=\"biginput\" name=\"height\" type=\"hidden\" size=5 value=\"$height\">";
			}

			show_table2_v_delimiter();
			echo S_COLUMN_SPAN;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"colspan\" size=2 value=\"$colspan\">";

			show_table2_v_delimiter();
			echo S_ROW_SPAN;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"rowspan\" size=2 value=\"$rowspan\">";

			show_table2_v_delimiter2();
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
			if($resourceid!=0) 
			{ 
				echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
			}
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\">";

			show_table2_header_end();
		}
		else if( ($screenitemid!=0) && ($resource==0) )
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r><img src='chart2.php?graphid=$resourceid&width=$width&height=$height&period=3600' border=0></a>";
		}
		else if( ($screenitemid!=0) && ($resource==1) )
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r><img src='chart.php?itemid=$resourceid&width=$width&height=$height&period=3600' border=0></a>";
		}
		else if( ($screenitemid!=0) && ($resource==2) )
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r><img src='map.php?noedit=1&sysmapid=$resourceid&width=$width&height=$height&period=3600' border=0></a>";
		}
		else if( ($screenitemid!=0) && ($resource==3) )
		{
			show_screen_plaintext($resourceid);
			echo "<p align=center>";
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r>".S_CHANGE."</a>";
		}
		else
		{
			echo "<a href=screenedit.php?register=edit&screenid=$screenid&x=$c&y=$r>".S_EMPTY."</a>";
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
