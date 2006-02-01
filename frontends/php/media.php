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
	if(isset($_REQUEST["save"]))
	{
		$severity=array();
		if(isset($_REQUEST["0"]))	$severity=array_merge($severity,array(0));
		if(isset($_REQUEST["1"]))	$severity=array_merge($severity,array(1));
		if(isset($_REQUEST["2"]))	$severity=array_merge($severity,array(2));
		if(isset($_REQUEST["3"]))	$severity=array_merge($severity,array(3));
		if(isset($_REQUEST["4"]))	$severity=array_merge($severity,array(4));
		if(isset($_REQUEST["5"]))	$severity=array_merge($severity,array(5));

		if(isset($_REQUEST["mediaid"]))
		{
			$result=update_media($_REQUEST["mediaid"], $_REQUEST["userid"],
				$_REQUEST["mediatypeid"], $_REQUEST["sendto"],$severity,
				$_REQUEST["active"],$_REQUEST["period"]);

			show_messages($result,S_MEDIA_UPDATED,S_CANNOT_UPDATE_MEDIA);
		} else {
			$result=add_media( $_REQUEST["userid"], $_REQUEST["mediatypeid"], 
				$_REQUEST["sendto"],$severity,$_REQUEST["active"],$_REQUEST["period"]);

			show_messages($result, S_MEDIA_ADDED, S_CANNOT_ADD_MEDIA);
		}
		if($result){
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["mediaid"]))
	{
		$result=delete_media( $_REQUEST["mediaid"] );
		show_messages($result,S_MEDIA_DELETED, S_CANNOT_DELETE_MEDIA);
	}
	elseif(isset($_REQUEST["enable"])&&isset($_REQUEST["mediaid"]))
	{
		$result=activate_media($_REQUEST["mediaid"] );
		show_messages($result, S_MEDIA_ACTIVATED, S_CANNOT_ACTIVATE_MEDIA);
	}
	elseif(isset($_REQUEST["disable"])&&isset($_REQUEST["mediaid"]))
	{
		$result=disactivate_media( $_REQUEST["mediaid"] );
		show_messages($result, S_MEDIA_DISABLED, S_CANNOT_DISABLE_MEDIA);
	}
?>
<?php
	$form = new CForm("media.php");
	$form->AddVar("userid",$_REQUEST["userid"]);
	$form->AddItem(new CButton("form",S_CREATE_MEDIA));
	show_header2(S_MEDIA_BIG, $form);
?>
<?php

	if(isset($_REQUEST["form"]))
	{
		echo BR;
		insert_media_form();
	}
	else
	{
		$table = new CTableInfo(S_NO_MEDIA_DEFINED);
		$table->setHeader(array(S_TYPE,S_SEND_TO,S_WHEN_ACTIVE,S_STATUS,S_ACTIONS));

		$result=DBselect("select m.mediaid,mt.description,m.sendto,m.active,m.period".
			" from media m,media_type mt where m.mediatypeid=mt.mediatypeid".
			" and m.userid=".$_REQUEST["userid"]." order by mt.type,m.sendto");

		while($row=DBfetch($result))
		{
			if($row["active"]==0) 
			{
				$status=new CLink(S_ENABLED,
					"media.php?disable=1&mediaid=".$row["mediaid"].url_param("userid"),
					"enabled");
			}
			else
			{
				$status=new CLink(S_DISABLED,
					"media.php?enable=1&mediaid=".$row["mediaid"].url_param("userid"),
					"disabled");
			}

			$table->addRow(array(
				$row["description"],
				$row["sendto"],
				$row["period"],
				$status,
				new CLink(S_CHANGE,
					"media.php?form=update&mediaid=".$row["mediaid"].
						"&userid=".$_REQUEST["userid"]
					)
				));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>
