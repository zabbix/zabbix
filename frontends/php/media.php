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
	$page["title"] = "Media";
	$page["file"] = "media.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_right("User","U",$HTTP_GET_VARS["userid"]))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>


<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="enable")
		{
			$result=activate_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media activated","Cannot activate media");
		}
		elseif($HTTP_GET_VARS["register"]=="disable")
		{
			$result=disactivate_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media disabled","Cannot disable media");
		}
		elseif($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_media( $HTTP_GET_VARS["userid"], $HTTP_GET_VARS["mediatypeid"], $HTTP_GET_VARS["sendto"],$HTTP_GET_VARS["severity"],$HTTP_GET_VARS["active"]);
			show_messages($result,"Media added","Cannot add media");
		}
		elseif($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_media($HTTP_GET_VARS["mediaid"], $HTTP_GET_VARS["userid"], $HTTP_GET_VARS["mediatypeid"], $HTTP_GET_VARS["sendto"],$HTTP_GET_VARS["severity"],$HTTP_GET_VARS["active"]);
			show_messages($result,"Media updated","Cannot update media");
		}
		elseif($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media deleted","Cannot delete media");
			unset($HTTP_GET_VARS["mediaid"]);
		}
	}
?>

<?php
	show_table_header("MEDIA");
?>

<FONT COLOR="#000000">
<?php
	$sql="select m.mediaid,mt.description,m.sendto,m.active from media m,media_type mt where m.mediatypeid=mt.mediatypeid and m.userid=".$HTTP_GET_VARS["userid"]." order by mt.type,m.sendto";
	$result=DBselect($sql);

	echo "<TABLE BORDER=0 WIDTH=100% align=center BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD><B>Type</B></TD>";
	echo "<TD><B>Send to</B></TD>";
	echo "<TD><B>Status</B></TD>";
	echo "<TD><B>Actions</B></TD>";
	echo "</TR>";

	$col=0;
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		if($col==1)
		{
			echo "<TR BGCOLOR=#DDDDDD>";
			$col=0;
		} else
		{
			echo "<TR BGCOLOR=#EEEEEE>";
			$col=1;
		}
		$mediaid=DBget_field($result,$i,0);
		$description=DBget_field($result,$i,1);
		echo "<TD>";
		echo $description;
		echo "</TD>";
		echo "<TD>",DBget_field($result,$i,2),"</TD>";
		echo "<TD>";
		if(DBget_field($result,$i,3)==0) 
		{
			echo "<a href=\"media.php?register=disable&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\"><font color=\"00AA00\">Enabled</font></A>";
		}
		else
		{
			echo "<a href=\"media.php?register=enable&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\"><font color=\"AA0000\">Disabled</font></A>";
		}
		echo "</TD>";
		echo "<TD>";
		echo "<A HREF=\"media.php?register=change&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\">Change</A>";
		echo "</TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TD COLSPAN=4 ALIGN=CENTER>-No media defined-</TD>";
		echo "<TR>";
	}

	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	if(isset($HTTP_GET_VARS["mediaid"]))
	{
		$sql="select m.severity,m.sendto,m.active,m.mediatypeid from media m where m.mediaid=".$HTTP_GET_VARS["mediaid"];
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

	echo "<br>";
	show_table2_header_begin();
	echo "New media";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"media.php\">";
	echo "<input name=\"userid\" type=\"hidden\" value=".$HTTP_GET_VARS["userid"].">";
	if(isset($HTTP_GET_VARS["mediaid"]))
	{
		echo "<input name=\"mediaid\" type=\"hidden\" value=".$HTTP_GET_VARS["mediaid"].">";
	}
	echo "Type";
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

	show_table2_v_delimiter();
	echo nbsp("Send to");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sendto\" size=20 value='$sendto'>";

	show_table2_v_delimiter();
	echo nbsp("Use if severity");
	show_table2_h_delimiter();
	echo "<select multiple class=\"biginput\" name=\"severity[]\" size=\"5\">";
	$selected=iif( (1&$severity) == 1,"selected","");
	echo "<option value=\"0\" $selected>Not classified";
	$selected=iif( (2&$severity) == 2,"selected","");
	echo "<option value=\"1\" $selected>Information";
	$selected=iif( (4&$severity) == 4,"selected","");
	echo "<option value=\"2\" $selected>Warning";
	$selected=iif( (8&$severity) == 8,"selected","");
	echo "<option value=\"3\" $selected>Average";
	$selected=iif( (16&$severity) ==16,"selected","");
	echo "<option value=\"4\" $selected>High";
	$selected=iif( (32&$severity) ==32,"selected","");
	echo "<option value=\"5\" $selected>Disaster";
	echo "</select>";

	show_table2_v_delimiter();
	echo "Status";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"active\" size=1>";
	if($active == 0)
	{
		echo "<OPTION VALUE=\"0\" SELECTED> Enabled";
		echo "<OPTION VALUE=\"1\"> Disabled";
	}
	else
	{
		echo "<OPTION VALUE=\"0\"> Enabled";
		echo "<OPTION VALUE=\"1\" SELECTED> Disabled";
	}
	echo "</select>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["mediaid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected media?');\">";
	}

	show_table2_header_end();

	show_footer();
?>
