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
	$page["title"] = "S_MEDIA";
	$page["file"] = "media.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_right("User","U",$_REQUEST["userid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font
>");
                show_page_footer();
                exit;
        }
?>

<?php
	if(isset($_REQUEST["save"])&&isset($_REQUEST["mediaid"]))
	{
		$severity=array();
		if(isset($_REQUEST["0"]))	$severity=array_merge($severity,array(0));
		if(isset($_REQUEST["1"]))	$severity=array_merge($severity,array(1));
		if(isset($_REQUEST["2"]))	$severity=array_merge($severity,array(2));
		if(isset($_REQUEST["3"]))	$severity=array_merge($severity,array(3));
		if(isset($_REQUEST["4"]))	$severity=array_merge($severity,array(4));
		if(isset($_REQUEST["5"]))	$severity=array_merge($severity,array(5));
		$result=update_media($_REQUEST["mediaid"], $_REQUEST["userid"], $_REQUEST["mediatypeid"], $_REQUEST["sendto"],$severity,$_REQUEST["active"],$_REQUEST["period"]);
		show_messages($result,S_MEDIA_UPDATED,S_CANNOT_UPDATE_MEDIA);
	}

	if(isset($_REQUEST["save"])&&!isset($_REQUEST["mediaid"]))
	{
		$severity=array();
		if(isset($_REQUEST["0"]))	$severity=array_merge($severity,array(0));
		if(isset($_REQUEST["1"]))	$severity=array_merge($severity,array(1));
		if(isset($_REQUEST["2"]))	$severity=array_merge($severity,array(2));
		if(isset($_REQUEST["3"]))	$severity=array_merge($severity,array(3));
		if(isset($_REQUEST["4"]))	$severity=array_merge($severity,array(4));
		if(isset($_REQUEST["5"]))	$severity=array_merge($severity,array(5));
		$result=add_media( $_REQUEST["userid"], $_REQUEST["mediatypeid"], $_REQUEST["sendto"],$severity,$_REQUEST["active"],$_REQUEST["period"]);
		show_messages($result, S_MEDIA_ADDED, S_CANNOT_ADD_MEDIA);
	}

	if(isset($_REQUEST["delete"]))
	{
		$result=delete_media( $_REQUEST["mediaid"] );
		show_messages($result,S_MEDIA_DELETED, S_CANNOT_DELETE_MEDIA);
		unset($_REQUEST["mediaid"]);
	}

	if(isset($_REQUEST["action"])&&($_REQUEST["action"]=="enable"))
	{
		$result=activate_media( $_REQUEST["mediaid"] );
		show_messages($result, S_MEDIA_ACTIVATED, S_CANNOT_ACTIVATE_MEDIA);
	}

	
	if(isset($_REQUEST["action"])&&($_REQUEST["action"]=="disable"))
	{
		$result=disactivate_media( $_REQUEST["mediaid"] );
		show_messages($result, S_MEDIA_DISABLED, S_CANNOT_DISABLE_MEDIA);
	}
?>

<?php
	$h1=S_MEDIA_BIG;
	$h2="<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_MEDIA."\">";
	$h2=$h2."<input name=\"userid\" type=\"hidden\" value=".$_REQUEST["userid"].">";
	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"media.php\">", "</form>");
?>

<?php

	if(!isset($_REQUEST["form"]))
	{
		$sql="select m.mediaid,mt.description,m.sendto,m.active,m.period from media m,media_type mt where m.mediatypeid=mt.mediatypeid and m.userid=".$_REQUEST["userid"]." order by mt.type,m.sendto";
		$result=DBselect($sql);

		$table = new CTableInfo(S_NO_MEDIA_DEFINED);
		$table->setHeader(array(S_TYPE,S_SEND_TO,S_WHEN_ACTIVE,S_STATUS,S_ACTIONS));

		$col=0;
		while($row=DBfetch($result))
		{
			if($row["active"]==0) 
			{
				$status="<a href=\"media.php?action=disable&mediaid=".$row["mediaid"]."&userid=".$_REQUEST["userid"]."\"><font color=\"00AA00\">".S_ENABLED."</font></A>";
			}
			else
			{
				$status="<a href=\"media.php?action=enable&mediaid=".$row["mediaid"]."&userid=".$_REQUEST["userid"]."\"><font color=\"AA0000\">".S_DISABLED."</font></A>";
			}
			$actions="<A HREF=\"media.php?register=change&form=0&mediaid=".$row["mediaid"]."&userid=".$_REQUEST["userid"]."\">".S_CHANGE."</A>";
			$table->addRow(array(
				$row["description"],
				$row["sendto"],
				$row["period"],
				$status,
				$actions
				));
		}
		$table->show();
	}
	else
	{
		insert_media_form();
	}
?>

<?php
	show_page_footer();
?>
