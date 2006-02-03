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
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"userid"=>	array(T_ZBX_INT, O_MAND,P_SYS,	DB_ID,		NULL),
		"mediaid"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'{form}=="update"'),
		"mediatypeid"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({save})'),
		"sendto"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({save})'),
		"period"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({save})'),
		"active"=>	array(T_ZBX_INT, O_NO,	NULL,	IN(0,1),	'isset({save})'),

		"severity"=>	array(T_ZBX_INT, O_OPT,	NULL,	NOT_EMPTY,	NULL),

		"medias"=>	array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,		'isset({new_status})'),
/* actions */
		"new_status"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"enable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		"save"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
?>

<?php
	if(isset($_REQUEST["save"]))
	{
		$severity=get_request("severity",array());

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
		if($result){
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["new_status"])&&isset($_REQUEST["medias"]))
	{
		foreach($_REQUEST["medias"] as $mediaid)
		{
			if($_REQUEST["new_status"]!=0)
			{
				$result = activate_media($mediaid);
				show_messages($result, S_MEDIA_ACTIVATED, S_CANNOT_ACTIVATE_MEDIA);
			}
			else
			{
				$result = disactivate_media($mediaid);
				show_messages($result, S_MEDIA_DISABLED, S_CANNOT_DISABLE_MEDIA);
			}
		}
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
		$table->setHeader(array(S_TYPE,S_SEND_TO,S_WHEN_ACTIVE,S_STATUS));

		$result=DBselect("select m.mediaid,mt.description,m.sendto,m.active,m.period".
			" from media m,media_type mt where m.mediatypeid=mt.mediatypeid".
			" and m.userid=".$_REQUEST["userid"]." order by mt.type,m.sendto");

		while($row=DBfetch($result))
		{
			if($row["active"]==0) 
			{
				$status=new CLink(S_ENABLED,
					"media.php?new_status=0&medias%5B%5D=".$row["mediaid"].url_param("userid"),
					"enabled");
			}
			else
			{
				$status=new CLink(S_DISABLED,
					"media.php?new_status=1&medias%5B%5D=".$row["mediaid"].url_param("userid"),
					"disabled");
			}

			$table->addRow(array(
				new CLink($row["description"],
					"media.php?form=update&mediaid=".$row["mediaid"].
						url_param("userid"),
					'action'
					),
				$row["sendto"],
				$row["period"],
				$status
				));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>
