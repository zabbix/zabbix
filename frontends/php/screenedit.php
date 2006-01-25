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
	include "include/forms.inc.php";
	$page["title"] = "S_CONFIGURATION_OF_SCREENS";
	$page["file"] = "screenedit.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	show_table_header(S_CONFIGURATION_OF_SCREEN_BIG);

	if(isset($_REQUEST["screenid"]))
	{
		echo BR;
		if(!check_right("Screen","U",$_REQUEST["screenid"]))
		{
			show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
			show_page_footer();
			exit;
		}

		if(isset($_REQUEST["save"]))
		{
			if(!isset($_REQUEST["elements"]))	$_REQUEST["elements"]=0;

			if(isset($_REQUEST["screenitemid"]))
			{
				$result=update_screen_item($_REQUEST["screenitemid"],
					$_REQUEST["resource"],$_REQUEST["resourceid"],$_REQUEST["width"],
					$_REQUEST["height"],$_REQUEST["colspan"],$_REQUEST["rowspan"],
					$_REQUEST["elements"]);

				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
			}
			else
			{
				$result=add_screen_item(
					$_REQUEST["resource"],$_REQUEST["screenid"],
					$_REQUEST["x"],$_REQUEST["y"],$_REQUEST["resourceid"],
					$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["colspan"],
					$_REQUEST["rowspan"],$_REQUEST["elements"]);

				show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			}
			if($result){
				unset($_REQUEST["form"]);
			}
		} elseif(isset($_REQUEST["delete"])) {
			$result=delete_screen_item($_REQUEST["screenitemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_REQUEST["x"]);
		}
?>

<?php
		$screenid=$_REQUEST["screenid"];
		$result=DBselect("select name,cols,rows from screens where screenid=$screenid");
		$row=DBfetch($result);
		show_table_header(new CLink($row["name"],"screenedit.php?screenid=$screenid"));

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
			new CLink("No rows in screen ".$row["name"],"screenconf.php?form=0&screenid=".$screenid),
			"screen");
	
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

			if(isset($_REQUEST["form"]) && 
				isset($_REQUEST["x"]) && $_REQUEST["x"]==$c &&
				isset($_REQUEST["y"]) && $_REQUEST["y"]==$r)
			{
				$item = get_screen_item_form();
			}
			elseif(isset($_REQUEST["form"]) && 
				isset($_REQUEST["screenitemid"]) && $_REQUEST["screenitemid"]==$screenitemid)
			{
				$item = get_screen_item_form();
			}
			else if( ($screenitemid!=0) && ($resource==0) )
			{

				$item = new CLink(
					new CImg("chart2.php?graphid=$resourceid&width=$width&height=$height".
						"&period=3600' border=0"),
					"screenedit.php?form=0".url_param("screenid").
					"&screenitemid=$screenitemid#form"
					);
			}
			else if( ($screenitemid!=0) && ($resource==1) )
			{

				$item = new CLink(
					new CImg("chart.php?itemid=$resourceid&width=$width&height=$height".
                                        	"&period=3600"),
					"screenedit.php?form=0".url_param("screenid").
					"&screenitemid=$screenitemid#form"
					);
			}
			else if( ($screenitemid!=0) && ($resource==2) )
			{

				$item = new CLink(
					new CImg("map.php?noedit=1&sysmapid=$resourceid".
	                                        "&width=$width&height=$height&period=3600"),
					"screenedit.php?form=0".url_param("screenid").
					"&screenitemid=$screenitemid#form"
					);
			}
			else if( ($screenitemid!=0) && ($resource==3) )
			{
				$item = array(get_screen_plaintext($resourceid,$elements));
				array_push($item, new CLink(S_CHANGE,
					"screenedit.php?form=0".url_param("screenid").
					"&screenitemid=$screenitemid#form"
					));
			}
			else
			{
				$item = new CLink(
					S_EMPTY,
					"screenedit.php?form=0".url_param("screenid")."&x=$c&y=$r#form"
					);
			}
			
			$new_col = new CCol($item,"screen");

			if($colspan) $new_col->SetColSpan($colspan);
			if($rowspan) $new_col->SetRowSpan($rowspan);

			array_push($new_cols, $new_col);
		}
		$table->AddRow(new CRow($new_cols));
		}

		$table->Show();
	}
?>

<?php
	show_page_footer();
?>
