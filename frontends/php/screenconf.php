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
	require_once "include/config.inc.php";
	require_once "include/screens.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_SCREENS";
	$page["file"] = "screenconf.php";

include_once "include/page_header.php";
	
?>
<?php
	$_REQUEST['config'] = get_request('config',get_profile('web.screenconf.config',0));

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"config"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null), // 0 - screens, 1 - slides

		"screenid"=>		array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,		'(isset({config})&&({config}==0))&&(isset({form})&&({form}=="update"))'),
		"hsize"=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),	'(isset({config})&&({config}==0))&&isset({save})'),
		"vsize"=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),	'(isset({config})&&({config}==0))&&isset({save})'),

		"slideshowid"=>		array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,		'(isset({config})&&({config}==1))&&(isset({form})&&({form}=="update"))'),
		"name"=>		array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		"delay"=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,86400),'(isset({config})&&({config}==1))&&isset({save})'),

		"steps"=>		array(null,	O_OPT,	null,	null,	null),
		"new_step"=>		array(null,	O_OPT,	null,	null,	null),

		"move_up"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		"move_down"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),

		"edit_step"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		"add_step"=>		array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),
		"cancel_step"=>		array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),

		"sel_step"=>		array(T_ZBX_INT, O_OPT,  P_ACT,  BETWEEN(0,65534), null),
		"del_sel_step"=>		array(T_ZBX_STR, O_OPT,  P_ACT,  null, null),

		"clone"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	check_fields($fields);

	$config = $_REQUEST['config'] = get_request('config', 0);

	update_profile('web.screenconf.config', $_REQUEST['config']);
?>
<?php
	if( 0 == $config )
	{
		if(isset($_REQUEST["screenid"]))
		{
			if(!screen_accessiable($_REQUEST["screenid"], PERM_READ_WRITE))
				access_deny();
		}

		if(isset($_REQUEST['clone']) && isset($_REQUEST['screenid']))
		{
			unset($_REQUEST['screenid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['save']))
		{
			if(isset($_REQUEST["screenid"]))
			{
				// TODO check permission by new value.
				$result=update_screen($_REQUEST["screenid"],
					$_REQUEST["name"],$_REQUEST["hsize"],$_REQUEST["vsize"]);
				$audit_action = AUDIT_ACTION_UPDATE;
				show_messages($result, S_SCREEN_UPDATED, S_CANNOT_UPDATE_SCREEN);
			} else {
				if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
					access_deny();

				$result=add_screen($_REQUEST["name"],$_REQUEST["hsize"],$_REQUEST["vsize"]);
				$audit_action = AUDIT_ACTION_ADD;
				show_messages($result,S_SCREEN_ADDED,S_CANNOT_ADD_SCREEN);
			}
			if($result){
				add_audit($audit_action,AUDIT_RESOURCE_SCREEN," Name [".$_REQUEST['name']."] ");
				unset($_REQUEST["form"]);
				unset($_REQUEST["screenid"]);
			}
		}
		if(isset($_REQUEST["delete"])&&isset($_REQUEST["screenid"]))
		{
			if($screen = get_screen_by_screenid($_REQUEST["screenid"]))
			{
				$result = delete_screen($_REQUEST["screenid"]);
				show_messages($result, S_SCREEN_DELETED, S_CANNOT_DELETE_SCREEN);
				add_audit_if($result, AUDIT_ACTION_DELETE,AUDIT_RESOURCE_SCREEN," Name [".$screen['name']."] ");
			}
			unset($_REQUEST["screenid"]);
			unset($_REQUEST["form"]);
		}
	}
	else
	{
		if(isset($_REQUEST['slideshowid']))
		{
			if(!slideshow_accessiable($_REQUEST['slideshowid'], PERM_READ_WRITE))
				access_deny();
		}

		if(isset($_REQUEST['clone']) && isset($_REQUEST['slideshowid']))
		{
			unset($_REQUEST['slideshowid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['save']))
		{
			$slides = get_request('steps', array());

			if(isset($_REQUEST['slideshowid']))
			{ /* update */
				$result=update_slideshow($_REQUEST['slideshowid'],$_REQUEST['name'],$_REQUEST['delay'],$slides);
				$audit_action = AUDIT_ACTION_UPDATE;
				show_messages($result, S_SLIDESHOW_UPDATED, S_CANNOT_UPDATE_SLIDESHOW);
			}
			else
			{ /* add */
				$result=add_slideshow($_REQUEST['name'],$_REQUEST['delay'],$slides);
				$audit_action = AUDIT_ACTION_ADD;
				show_messages($result, S_SLIDESHOW_ADDED, S_CANNOT_ADD_SLIDESHOW);
			}
			if($result){
				add_audit($audit_action,AUDIT_RESOURCE_SLIDESHOW," Name [".$_REQUEST['name']."] ");
				unset($_REQUEST['form'], $_REQUEST['slideshowid']);
			}
		}
		else if(isset($_REQUEST['cancel_step']))
		{
			unset($_REQUEST['add_step'], $_REQUEST['new_step']);
		}
		else if(isset($_REQUEST['add_step']))
		{
			if(isset($_REQUEST['new_step']))
			{
				if(isset($_REQUEST['new_step']['sid']))
					$_REQUEST['steps'][$_REQUEST['new_step']['sid']] = $_REQUEST['new_step'];
				else
					$_REQUEST['steps'][] = $_REQUEST['new_step'];

				unset($_REQUEST['add_step'], $_REQUEST['new_step']);
			}
			else
			{
				$_REQUEST['new_step'] = array();
			}
		}
		else if(isset($_REQUEST['edit_step']))
		{
			$_REQUEST['new_step'] = $_REQUEST['steps'][$_REQUEST['edit_step']];
			$_REQUEST['new_step']['sid'] = $_REQUEST['edit_step'];
		}
		else if(isset($_REQUEST['del_sel_step'])&&isset($_REQUEST['sel_step'])&&is_array($_REQUEST['sel_step']))
		{
			foreach($_REQUEST['sel_step'] as $sid)
				if(isset($_REQUEST['steps'][$sid]))
					unset($_REQUEST['steps'][$sid]);
		}
		else if(isset($_REQUEST['move_up']) && isset($_REQUEST['steps'][$_REQUEST['move_up']]))
		{
			$new_id = $_REQUEST['move_up'] - 1;

			if(isset($_REQUEST['steps'][$new_id]))
			{
				$tmp = $_REQUEST['steps'][$new_id];
				$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_up']];
				$_REQUEST['steps'][$_REQUEST['move_up']] = $tmp;
			}
		}
		else if(isset($_REQUEST['move_down']) && isset($_REQUEST['steps'][$_REQUEST['move_down']]))
		{
			$new_id = $_REQUEST['move_down'] + 1;

			if(isset($_REQUEST['steps'][$new_id]))
			{
				$tmp = $_REQUEST['steps'][$new_id];
				$_REQUEST['steps'][$new_id] = $_REQUEST['steps'][$_REQUEST['move_down']];
				$_REQUEST['steps'][$_REQUEST['move_down']] = $tmp;
			}
		}
		else if(isset($_REQUEST['delete'])&&isset($_REQUEST['slideshowid']))
		{
			if($slideshow = get_slideshow_by_slideshowid($_REQUEST['slideshowid']))
			{
				$result = delete_slideshow($_REQUEST['slideshowid']);
				show_messages($result, S_SLIDESHOW_DELETED, S_CANNOT_DELETE_SLIDESHOW);
				add_audit_if($result, AUDIT_ACTION_DELETE,AUDIT_RESOURCE_SLIDESHOW," Name [".$slideshow['name']."] ");
			}
			unset($_REQUEST['slideshowid']);
			unset($_REQUEST["form"]);
		}
	}
?>
<?php
	$form = new CForm();
	$form->SetMethod('get');
	
	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_SCREENS);
	$cmbConfig->AddItem(1, S_SLIDESHOWS);

	$form->AddItem($cmbConfig);
	$form->AddItem(new CButton("form", 0 == $config ? S_CREATE_SCREEN : S_SLIDESHOW));

	show_table_header(0 == $config ? S_CONFIGURATION_OF_SCREENS_BIG : S_CONFIGURATION_OF_SLIDESHOWS_BIG, $form);
	echo BR;

	if( 0 == $config )
	{
		if(isset($_REQUEST["form"]))
		{
			insert_screen_form();
		}
		else
		{
			show_table_header(S_SCREENS_BIG);

			$table = new CTableInfo(S_NO_SCREENS_DEFINED);
			$table->SetHeader(array(S_NAME,S_DIMENSION_COLS_ROWS,S_SCREEN));

			$result=DBselect('select screenid,name,hsize,vsize from screens where '.DBin_node('screenid').
					" order by name");
			while($row=DBfetch($result))
			{
				if(!screen_accessiable($row["screenid"], PERM_READ_WRITE)) continue;

				$table->AddRow(array(
					new CLink($row["name"],"?config=0&form=update&screenid=".$row["screenid"],
						'action'),
					$row["hsize"]." x ".$row["vsize"],
					new CLink(S_EDIT,"screenedit.php?screenid=".$row["screenid"])
					));
			}
			$table->Show();
		}
	}
	else
	{
		if(isset($_REQUEST["form"]))
		{
			insert_slideshow_form();
		}
		else
		{
			show_table_header(S_SLIDESHOWS_BIG);

			$table = new CTableInfo(S_NO_SLIDESHOWS_DEFINED);
			$table->SetHeader(array(S_NAME,S_DELAY,S_COUNT_OF_SLIDES));

			$db_slides = DBselect('select s.slideshowid, s.name, s.delay, count(*) as cnt '.
				' from slideshows s left join slides sl on sl.slideshowid=s.slideshowid '.
				' where '.DBin_node('s.slideshowid').
				' group by s.slideshowid,s.name,s.delay '.
				' order by s.name,s.slideshowid');
			while($slide_data = DBfetch($db_slides))
			{
				if(!slideshow_accessiable($slide_data['slideshowid'], PERM_READ_WRITE)) continue;

				$table->AddRow(array(
					new CLink($slide_data['name'],'?config=1&form=update&slideshowid='.$slide_data['slideshowid'],
						'action'),
					$slide_data['delay'],
					$slide_data['cnt']
					));
			}
			$table->Show();
		}

	}
?>

<?php

include_once "include/page_footer.php";

?>
