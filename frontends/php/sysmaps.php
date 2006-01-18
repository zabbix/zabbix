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
	$page["file"] = "sysmaps.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Network map","U"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_page_footer();
		exit;
	}
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["save"])&&!isset($_REQUEST["sysmapid"]))
	{
		$result=add_sysmap($_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["background"],$_REQUEST["label_type"]);
		show_messages($result,"Network map added","Cannot add network map");
	}

	if(isset($_REQUEST["save"])&&isset($_REQUEST["sysmapid"]))
	{
		$result=update_sysmap($_REQUEST["sysmapid"],$_REQUEST["name"],$_REQUEST["width"],$_REQUEST["height"],$_REQUEST["background"],$_REQUEST["label_type"]);
		show_messages($result,"Network map updated","Cannot update network map");
	}

	if(isset($_REQUEST["delete"]))
	{
		$result=delete_sysmap($_REQUEST["sysmapid"]);
		show_messages($result,"Network map deleted","Cannot delete network map");
		unset($_REQUEST["sysmapid"]);
	}
?>

<?php
	$h1=S_CONFIGURATION_OF_NETWORK_MAPS;
	$h2="<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_MAP."\">";
	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"sysmaps.php\">", "</form>");
?>

<?php
	if(!isset($_REQUEST["form"]))
	{
		$table = new CTableInfo(S_NO_MAPS_DEFINED);
		$table->setHeader(array(S_ID,S_NAME,S_WIDTH,S_HEIGHT,S_ACTIONS));

		$result=DBselect("select s.sysmapid,s.name,s.width,s.height from sysmaps s order by s.name");
		$col=0;
		while($row=DBfetch($result))
		{
		        if(!check_right("Network map","U",$row["sysmapid"]))
		        {
		                continue;
		        }
	
			$table->addRow(array(
				$row["sysmapid"],
				"<a href=\"sysmap.php?sysmapid=".$row["sysmapid"]."\">".$row["name"]."</a>",
				$row["width"],
				$row["height"],
				"<A HREF=\"sysmaps.php?sysmapid=".$row["sysmapid"]."&amp;form=0#form\">Change</A>"
				));
		}
		$table->show();
	}
	else
	{

	if(isset($_REQUEST["sysmapid"]))
	{
		$result=DBselect("select * from sysmaps where sysmapid=".$_REQUEST["sysmapid"]);
		$row=DBfetch($result);
		$name=$row["name"];
		$width=$row["width"];
		$height=$row["height"];
		$background=$row["background"];
		$label_type=$row["label_type"];
	}
	else
	{
		$name="";
		$width=800;
		$height=600;
		$background="";
		$label_type=0;
	}

	show_form_begin("sysmaps.map");
	echo "New system map";

	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" enctype=\"multipart/form-data\" action=\"sysmaps.php\">";
	if(isset($_REQUEST["sysmapid"]))
	{
		echo "<input class=\"biginput\" name=\"sysmapid\" type=\"hidden\" value=".$_REQUEST["sysmapid"].">";
	}
	echo S_NAME;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"name\" value=\"$name\" size=32>";

	show_table2_v_delimiter($col++);
	echo S_WIDTH;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"width\" size=5 value=\"$width\">";

	show_table2_v_delimiter($col++);
	echo S_HEIGHT;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"height\" size=5 value=\"$height\">";

	show_table2_v_delimiter($col++);
	echo S_BACKGROUND_IMAGE;
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"background\" size=1>";
	$result=DBselect("select name from images where imagetype=2 order by name");
	echo "<OPTION VALUE=''>No image...";
	while($row=DBfetch($result))
	{
		$name=$row["name"];
		if(isset($_REQUEST["sysmapid"]) && ($background==$name))
		{
			echo "<OPTION VALUE='".$name."' SELECTED>".$name;
		}
		else
		{
			echo "<OPTION VALUE='".$name."'>".$name;
		}
	}
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo S_ICON_LABEL_TYPE;
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"label_type\" size=1>";
	if($label_type==0)
	{
		echo "<OPTION VALUE='0' SELECTED>".S_HOST_LABEL;
		echo "<OPTION VALUE='1'>".S_IP_ADDRESS;
		echo "<OPTION VALUE='2'>".S_HOST_NAME;
		echo "<OPTION VALUE='3'>".S_STATUS_ONLY;
		echo "<OPTION VALUE='4'>".S_NOTHING;
	}
	else if($label_type==1)
	{
		echo "<OPTION VALUE='0'>".S_HOST_LABEL;
		echo "<OPTION VALUE='1' SELECTED>".S_IP_ADDRESS;
		echo "<OPTION VALUE='2'>".S_HOST_NAME;
		echo "<OPTION VALUE='3'>".S_STATUS_ONLY;
		echo "<OPTION VALUE='4'>".S_NOTHING;
	}
	else if($label_type==2)
	{
		echo "<OPTION VALUE='0'>".S_HOST_LABEL;
		echo "<OPTION VALUE='1'>".S_IP_ADDRESS;
		echo "<OPTION VALUE='2' SELECTED>".S_HOST_NAME;
		echo "<OPTION VALUE='3'>".S_STATUS_ONLY;
		echo "<OPTION VALUE='4'>".S_NOTHING;
	}
	else if($label_type==3)
	{
		echo "<OPTION VALUE='0'>".S_HOST_LABEL;
		echo "<OPTION VALUE='1'>".S_IP_ADDRESS;
		echo "<OPTION VALUE='2'>".S_HOST_NAME;
		echo "<OPTION VALUE='3' SELECTED>".S_STATUS_ONLY;
		echo "<OPTION VALUE='4'>".S_NOTHING;
	}
	else if($label_type==4)
	{
		echo "<OPTION VALUE='0'>".S_HOST_LABEL;
		echo "<OPTION VALUE='1'>".S_IP_ADDRESS;
		echo "<OPTION VALUE='2'>".S_HOST_NAME;
		echo "<OPTION VALUE='3'>".S_STATUS_ONLY;
		echo "<OPTION VALUE='4' SELECTED>".S_NOTHING;
	}
	echo "</SELECT>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";
	if(isset($_REQUEST["sysmapid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"delete\" value=\"".S_DELETE."\" onClick=\"return Confirm('Delete system map?');\">";
	}
	echo "<input class=\"button\" type=\"submit\" name=\"cancel\" value=\"".S_CANCEL."\">";

	}
	show_table2_header_end();
?>

<?php
	show_page_footer();
?>
