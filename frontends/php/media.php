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
	$page["title"] = S_MEDIA;
	$page["file"] = "media.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_right("User","U",$_GET["userid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font
>");
                show_footer();
                exit;
        }
?>


<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="enable")
		{
			$result=activate_media( $_GET["mediaid"] );
			show_messages($result, S_MEDIA_ACTIVATED, S_CANNOT_ACTIVATE_MEDIA);
		}
		elseif($_GET["register"]=="disable")
		{
			$result=disactivate_media( $_GET["mediaid"] );
			show_messages($result, S_MEDIA_DISABLED, S_CANNOT_DISABLE_MEDIA);
		}
		elseif($_GET["register"]=="add")
		{
			$severity=array();
			if(isset($_GET["0"]))	$severity=array_merge($severity,array(0));
			if(isset($_GET["1"]))	$severity=array_merge($severity,array(1));
			if(isset($_GET["2"]))	$severity=array_merge($severity,array(2));
			if(isset($_GET["3"]))	$severity=array_merge($severity,array(3));
			if(isset($_GET["4"]))	$severity=array_merge($severity,array(4));
			if(isset($_GET["5"]))	$severity=array_merge($severity,array(5));
			$result=add_media( $_GET["userid"], $_GET["mediatypeid"], $_GET["sendto"],$severity,$_GET["active"]);
			show_messages($result, S_MEDIA_ADDED, S_CANNOT_ADD_MEDIA);
		}
		elseif($_GET["register"]=="update")
		{
			$severity=array();
			if(isset($_GET["0"]))	$severity=array_merge($severity,array(0));
			if(isset($_GET["1"]))	$severity=array_merge($severity,array(1));
			if(isset($_GET["2"]))	$severity=array_merge($severity,array(2));
			if(isset($_GET["3"]))	$severity=array_merge($severity,array(3));
			if(isset($_GET["4"]))	$severity=array_merge($severity,array(4));
			if(isset($_GET["5"]))	$severity=array_merge($severity,array(5));
			$result=update_media($_GET["mediaid"], $_GET["userid"], $_GET["mediatypeid"], $_GET["sendto"],$severity,$_GET["active"]);
			show_messages($result,S_MEDIA_UPDATED,S_CANNOT_UPDATE_MEDIA);
		}
		elseif($_GET["register"]=="delete")
		{
			$result=delete_media( $_GET["mediaid"] );
			show_messages($result,S_MEDIA_DELETED, S_CANNOT_DELETE_MEDIA);
			unset($_GET["mediaid"]);
		}
	}
?>

<?php
	show_table_header(S_MEDIA_BIG);
?>

<?php
	$sql="select m.mediaid,mt.description,m.sendto,m.active from media m,media_type mt where m.mediatypeid=mt.mediatypeid and m.userid=".$_GET["userid"]." order by mt.type,m.sendto";
	$result=DBselect($sql);

	table_begin();
	table_header(array(S_TYPE, S_SEND_TO,S_STATUS,S_ACTIONS));

	$col=0;
	while($row=DBfetch($result))
	{
		if($row["active"]==0) 
		{
			$status="<a href=\"media.php?register=disable&mediaid=".$row["mediaid"]."&userid=".$_GET["userid"]."\"><font color=\"00AA00\">".S_ENABLED."</font></A>";
		}
		else
		{
			$status="<a href=\"media.php?register=enable&mediaid=".$row["mediaid"]."&userid=".$_GET["userid"]."\"><font color=\"AA0000\">".S_DISABLED."</font></A>";
		}
		$actions="<A HREF=\"media.php?register=change&mediaid=".$row["mediaid"]."&userid=".$_GET["userid"]."\">".S_CHANGE."</A>";
		table_row(array(
			$row["description"],
			$row["sendto"],
			$status,
			$actions
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_MEDIA_DEFINED."</TD>";
		echo "<TR>";
	}
	table_end();
?>

<?php
	if(isset($_GET["mediaid"]))
	{
		$sql="select m.severity,m.sendto,m.active,m.mediatypeid from media m where m.mediaid=".$_GET["mediaid"];
		$result=DBselect($sql);
		$severity=DBget_field($result,0,0);
		$sendto=DBget_field($result,0,1);
		$active=DBget_field($result,0,2);
		$mediatypeid=DBget_field($result,0,3);
	}
	else
	{
		$sendto="";
		$severity=63;
		$mediatypeid=-1;
		$active=0;
	}

	show_form_begin("media.media");
	echo S_NEW_MEDIA;

	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" action=\"media.php\">";
	echo "<input name=\"userid\" type=\"hidden\" value=".$_GET["userid"].">";
	if(isset($_GET["mediaid"]))
	{
		echo "<input name=\"mediaid\" type=\"hidden\" value=".$_GET["mediaid"].">";
	}
	echo S_TYPE;
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"mediatypeid\" size=1>";
	$sql="select mediatypeid,description from media_type order by type";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if($row["mediatypeid"] == $mediatypeid)
		{
			echo "<OPTION VALUE=\"".$row["mediatypeid"]."\" SELECTED>".$row["description"];
		}
		else
		{
			echo "<OPTION VALUE=\"".$row["mediatypeid"]."\">".$row["description"];
		}
		
	}
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo nbsp(S_SEND_TO);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sendto\" size=20 value='$sendto'>";

	show_table2_v_delimiter($col++);
/*	echo nbsp(S_USE_IF_SEVERITY);
	show_table2_h_delimiter();
	echo "<select multiple class=\"biginput\" name=\"severity[]\" size=\"5\">";
	$selected=iif( (1&$severity) == 1,"selected","");
	echo "<option value=\"0\" $selected>".S_NOT_CLASSIFIED;
	$selected=iif( (2&$severity) == 2,"selected","");
	echo "<option value=\"1\" $selected>".S_INFORMATION;
	$selected=iif( (4&$severity) == 4,"selected","");
	echo "<option value=\"2\" $selected>".S_WARNING;
	$selected=iif( (8&$severity) == 8,"selected","");
	echo "<option value=\"3\" $selected>".S_AVERAGE;
	$selected=iif( (16&$severity) ==16,"selected","");
	echo "<option value=\"4\" $selected>".S_HIGH;
	$selected=iif( (32&$severity) ==32,"selected","");
	echo "<option value=\"5\" $selected>".S_DISASTER;
	echo "</select>";*/

	echo nbsp(S_USE_IF_SEVERITY);
	show_table2_h_delimiter();
	$checked=iif( (1&$severity) == 1,"checked","");
	echo "<input type=checkbox name=\"0\" value=\"0\" $checked>".S_NOT_CLASSIFIED."<br>";
	$checked=iif( (2&$severity) == 2,"checked","");
	echo "<input type=checkbox name=\"1\" value=\"1\" $checked>".S_INFORMATION."<br>";
	$checked=iif( (4&$severity) == 4,"checked","");
	echo "<input type=checkbox name=\"2\" value=\"2\" $checked>".S_WARNING."<br>";
	$checked=iif( (8&$severity) == 8,"checked","");
	echo "<input type=checkbox name=\"3\" value=\"3\" $checked>".S_AVERAGE."<br>";
	$checked=iif( (16&$severity) ==16,"checked","");
	echo "<input type=checkbox name=\"4\" value=\"4\" $checked>".S_HIGH."<br>";
	$checked=iif( (32&$severity) ==32,"checked","");
	echo "<input type=checkbox name=\"5\" value=\"5\" $checked>".S_DISASTER."<br>";

	show_table2_v_delimiter($col++);
	echo "Status";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"active\" size=1>";
	if($active == 0)
	{
		echo "<OPTION VALUE=\"0\" SELECTED>".S_ENABLED;
		echo "<OPTION VALUE=\"1\">".S_DISABLED;
	}
	else
	{
		echo "<OPTION VALUE=\"0\">".S_ENABLED;
		echo "<OPTION VALUE=\"1\" SELECTED>".S_DISABLED;
	}
	echo "</select>";

	show_table2_v_delimiter2($col++);
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($_GET["mediaid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA_Q."');\">";
	}

	show_table2_header_end();

	show_footer();
?>
