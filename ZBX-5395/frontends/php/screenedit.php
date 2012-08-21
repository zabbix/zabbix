<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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

require_once('include/config.inc.php');
require_once('include/screens.inc.php');
require_once('include/forms.inc.php');
require_once('include/blocks.inc.php');

$page['title'] = 'S_CONFIGURATION_OF_SCREENS';
$page['file'] = 'screenedit.php';
$page['hist_arg'] = array('screenid');
$page['scripts'] = array('class.cscreen.js','class.calendar.js','gtlc.js');

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'screenid'=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),

		'screenitemid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,			'(isset({form})&&({form}=="update"))&&(!isset({x})||!isset({y}))'),
		'resourcetype'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,16),	'isset({save})'),
		'caption'=>		array(T_ZBX_STR, O_OPT,  null,  null,	null),
		'resourceid'=>	array(T_ZBX_INT, O_OPT,  null,  DB_ID, 	'isset({save})'),
		'width'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	null),
		'height'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),	null),
		'colspan'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		'rowspan'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		'elements'=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,65535),	null),
		'valign'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(VALIGN_MIDDLE,VALIGN_BOTTOM),		null),
		'halign'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(HALIGN_CENTER,HALIGN_RIGHT),		null),
		'style'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(STYLE_HORISONTAL,STYLE_VERTICAL),	'isset({save})'),
		'url'=>			array(T_ZBX_STR, O_OPT,  null,  null,			'isset({save})'),
		'dynamic'=>		array(T_ZBX_INT, O_OPT,  null,  null,			null),
		'x'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),		'isset({save})&&(isset({form})&&({form}!="update"))'),
		'y'=>			array(T_ZBX_INT, O_OPT,  null,  BETWEEN(1,100),		'isset({save})&&(isset({form})&&({form}!="update"))'),

// STATUS OF TRIGGER
		'tr_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
		'tr_hostid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),

		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),

		'add_row'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		'add_col'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		'rmv_row'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
		'rmv_col'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),

		'sw_pos'=>		array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,100),		null),
	);

	check_fields($fields);
	$_REQUEST['dynmic'] = get_request('dynamic',SCREEN_SIMPLE_ITEM);
?>
<?php
	$trigg_wdgt = new CWidget();
	$trigg_wdgt->addPageHeader(S_CONFIGURATION_OF_SCREEN_BIG);

	//show_table_header(S_CONFIGURATION_OF_SCREEN_BIG);

	$options = array(
		'screenids' => $_REQUEST['screenid'],
		'editable' => 1,
		'output' => API_OUTPUT_EXTEND
	);
	$screens = CScreen::get($options);
	if(empty($screens)) access_deny();

	$screen = reset($screens);

	$trigg_wdgt->addHeader($screen['name']);
	$trigg_wdgt->addItem(BR());

	if(isset($_REQUEST['save'])){
		if(!isset($_REQUEST['elements'])) $_REQUEST['elements'] = 0;

		DBstart();

		if (isset($_REQUEST['screenitemid'])) {
			$msg_ok = S_ITEM_UPDATED;
			$msg_err = S_CANNOT_UPDATE_ITEM;

			$result = CScreenItem::update(array($_REQUEST));
		}
		else {
			$msg_ok = S_ITEM_ADDED;
			$msg_err = S_CANNOT_ADD_ITEM;

			$result = CScreenItem::create(array($_REQUEST));
		}

		DBend($result);
		show_messages($result, $msg_ok, $msg_err);

		// success
		if ($result) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, ' Name ['.$screen['name'].'] cell changed '.
				(isset($_REQUEST['screenitemid']) ? '['.$_REQUEST['screenitemid'].']' : '['.$_REQUEST['x'].','.$_REQUEST['y'].']'));

			unset($_REQUEST['form']);
		}
	}
	elseif (isset($_REQUEST['delete'])) {
		DBstart();
		$result = CScreenItem::delete($_REQUEST['screenitemid']);
		$result = DBend($result);

		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
		if($result){
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Item deleted');
		}
		unset($_REQUEST['x']);
	}
	else if(isset($_REQUEST['add_row'])){
		$add_row = get_request('add_row',0);
		DBexecute('UPDATE screens SET vsize=(vsize+1) WHERE screenid='.$screen['screenid']);
		if($screen['vsize'] > $add_row){
			DBexecute('UPDATE screens_items SET y=(y+1) WHERE screenid='.$screen['screenid'].' AND y>='.$add_row);
		}
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Row added');
	}
	else if(isset($_REQUEST['add_col'])){
		$add_col = get_request('add_col',0);
		DBexecute('UPDATE screens SET hsize=(hsize+1) WHERE screenid='.$screen['screenid']);
		if($screen['hsize'] > $add_col){
			DBexecute('UPDATE screens_items SET x=(x+1) WHERE screenid='.$screen['screenid'].' AND x>='.$add_col);
		}
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Column added');
	}
	else if(isset($_REQUEST['rmv_row'])){
		$rmv_row = get_request('rmv_row',0);
		if($screen['vsize'] > 1){
			DBexecute('UPDATE screens SET vsize=(vsize-1) WHERE screenid='.$screen['screenid']);
			DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND y='.$rmv_row);
			DBexecute('UPDATE screens_items SET y=(y-1) WHERE screenid='.$screen['screenid'].' AND y>'.$rmv_row);
         add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Row deleted');
		} else {
			error(S_SCREEN_SHOULD_CONTAIN_ONE_ROW_AND_COLUMN);
			show_messages(false, '', S_CANNOT_REMOVE_ROW_OR_COLUMN);
      }
	}
	else if(isset($_REQUEST['rmv_col'])){
		$rmv_col = get_request('rmv_col',0);
		if($screen['hsize'] > 1){
			DBexecute('UPDATE screens SET hsize=(hsize-1) WHERE screenid='.$screen['screenid']);
			DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND x='.$rmv_col);
			DBexecute('UPDATE screens_items SET x=(x-1) WHERE screenid='.$screen['screenid'].' AND x>'.$rmv_col);
         add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Column deleted');
		} else {
			error(S_SCREEN_SHOULD_CONTAIN_ONE_ROW_AND_COLUMN);
			show_messages(false, '', S_CANNOT_REMOVE_ROW_OR_COLUMN);
      }
	}
	else if(isset($_REQUEST['sw_pos'])){
		$sw_pos = get_request('sw_pos', array());
		if(count($sw_pos) > 3){
			$sql = 'SELECT screenitemid, colspan, rowspan '.
					' FROM screens_items '.
					' WHERE y='.$sw_pos[0].
						' AND x='.$sw_pos[1].
						' AND screenid='.$screen['screenid'];
			$fitem = DBfetch(DBselect($sql));

			$sql = 'SELECT screenitemid, colspan, rowspan '.
					' FROM screens_items '.
					' WHERE y='.$sw_pos[2].
						' AND x='.$sw_pos[3].
						' AND screenid='.$screen['screenid'];
			$sitem = DBfetch(DBselect($sql));

			if($fitem){
				DBexecute('UPDATE screens_items '.
							' SET y='.$sw_pos[2].',x='.$sw_pos[3].
							',colspan='.(isset($sitem['colspan']) ? $sitem['colspan'] : 0).
							',rowspan='.(isset($sitem['rowspan']) ? $sitem['rowspan'] : 0).
							' WHERE y='.$sw_pos[0].
								' AND x='.$sw_pos[1].
								' AND screenid='.$screen['screenid'].
								' AND screenitemid='.$fitem['screenitemid']);

			}

			if($sitem){
				DBexecute('UPDATE screens_items '.
							' SET y='.$sw_pos[0].',x='.$sw_pos[1].
							',colspan='.(isset($fitem['colspan']) ? $fitem['colspan'] : 0).
							',rowspan='.(isset($fitem['rowspan']) ? $fitem['rowspan'] : 0).
							' WHERE y='.$sw_pos[2].
								' AND x='.$sw_pos[3].
								' AND screenid='.$screen['screenid'].
								' AND screenitemid='.$sitem['screenitemid']);
			}
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Items switched');
		}
	}

	if($_REQUEST['screenid'] > 0){
		$table = get_screen($_REQUEST['screenid'], 1);
		$trigg_wdgt->addItem($table);
		zbx_add_post_js('init_screen("'.$_REQUEST['screenid'].'","iframe","'.$_REQUEST['screenid'].'");');
		zbx_add_post_js('timeControl.processObjects();');
	}

	$trigg_wdgt->show();
?>
<?php

include_once('include/page_footer.php');

?>
