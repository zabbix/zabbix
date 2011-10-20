<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

$page['title'] = _('Configuration of screens');
$page['file'] = 'screenedit.php';
$page['hist_arg'] = array('screenid');
$page['scripts'] = array('class.cscreen.js', 'class.calendar.js', 'gtlc.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');
?>
<?php
//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'screenid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,	null),
	'screenitemid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	'(isset({form})&&({form}=="update"))&&(!isset({x})||!isset({y}))'),
	'resourcetype' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,16), 'isset({save})'),
	'caption' =>		array(T_ZBX_STR, O_OPT, null,	null,	null),
	'resourceid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,	'isset({save})'),
	'templateid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,	null),
	'width' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,65535), null),
	'height' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,65535), null),
	'colspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100), null),
	'rowspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100), null),
	'elements' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(1,65535), null),
	'sort_triggers' =>	array(T_ZBX_INT, O_OPT, null,	IN(array(SCREEN_SORT_TRIGGERS_DATE_DESC, SCREEN_SORT_TRIGGERS_SEVERITY_DESC, SCREEN_SORT_TRIGGERS_HOST_NAME_ASC)), null),
	'valign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(VALIGN_MIDDLE,VALIGN_BOTTOM), null),
	'halign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(HALIGN_CENTER,HALIGN_RIGHT), null),
	'style' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,2), 'isset({save})'),
	'url' =>			array(T_ZBX_STR, O_OPT, null,	null,	'isset({save})'),
	'dynamic' =>		array(T_ZBX_INT, O_OPT, null,	null,	null),
	'x' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1,100), 'isset({save})&&(isset({form})&&({form}!="update"))'),
	'y' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1,100), 'isset({save})&&(isset({form})&&({form}!="update"))'),
	'screen_type' =>	array(T_ZBX_INT, O_OPT, null,	null,	null),
	'tr_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'tr_hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	// actions
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,	null),
	'add_row' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100),	null),
	'add_col' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100),	null),
	'rmv_row' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100),	null),
	'rmv_col' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100),	null),
	'sw_pos' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,100),	null),
	'ajaxAction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
);
check_fields($fields);
$_REQUEST['dynmic'] = get_request('dynamic', SCREEN_SIMPLE_ITEM);
?>
<?php
$options = array(
	'screenids' => $_REQUEST['screenid'],
	'editable' => 1,
	'output' => API_OUTPUT_EXTEND,
	'selectScreenItems' => API_OUTPUT_EXTEND
);
$screens = API::Screen()->get($options);
if (empty($screens)) {
	$screens = API::TemplateScreen()->get($options);
	if (empty($screens)) {
		access_deny();
	}
}
$screen = reset($screens);

/*
 * Ajax
 */
if (isset($_REQUEST['ajaxAction'])) {
	switch ($_REQUEST['ajaxAction']) {
		case 'sw_pos':
			$sw_pos = get_request('sw_pos', array());
			if (count($sw_pos) > 3) {
				$sql = 'SELECT s.screenitemid,s.colspan,s.rowspan'.
						' FROM screens_items s'.
						' WHERE s.y='.$sw_pos[0].
							' AND s.x='.$sw_pos[1].
							' AND s.screenid='.$screen['screenid'];
				$fitem = DBfetch(DBselect($sql));

				$sql = 'SELECT s.screenitemid,s.colspan,s.rowspan'.
						' FROM screens_items s'.
						' WHERE s.y='.$sw_pos[2].
							' AND s.x='.$sw_pos[3].
							' AND s.screenid='.$screen['screenid'];
				$sitem = DBfetch(DBselect($sql));

				if ($fitem) {
					DBexecute('UPDATE screens_items '.
								' SET y='.$sw_pos[2].',x='.$sw_pos[3].
								',colspan='.(isset($sitem['colspan']) ? $sitem['colspan'] : 0).
								',rowspan='.(isset($sitem['rowspan']) ? $sitem['rowspan'] : 0).
								' WHERE y='.$sw_pos[0].
									' AND x='.$sw_pos[1].
									' AND screenid='.$screen['screenid'].
									' AND screenitemid='.$fitem['screenitemid']);
				}

				if ($sitem) {
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
			echo '{"result": true}';
		break;
	}
}
if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
	include_once('include/page_footer.php');
	exit();
}
if (isset($_REQUEST['save'])) {
	if (!isset($_REQUEST['elements'])) {
		$_REQUEST['elements'] = 0;
	}
	if (!isset($_REQUEST['sort_triggers'])) {
		$_REQUEST['sort_triggers'] = SCREEN_SORT_TRIGGERS_DATE_DESC;
	}

	try {
		DBstart();

		if (isset($_REQUEST['screenitemid'])) {
			$msg_ok = _('Item updated');
			$msg_err = _('Cannot update item');
		}
		else {
			$msg_ok = _('Item added');
			$msg_err = _('Cannot add item');
		}
		if (isset($_REQUEST['screenitemid'])) {
			$result = update_screen_item($_REQUEST['screenitemid'],
				$_REQUEST['resourcetype'], $_REQUEST['resourceid'], $_REQUEST['width'],
				$_REQUEST['height'], $_REQUEST['colspan'], $_REQUEST['rowspan'],
				$_REQUEST['elements'], $_REQUEST['sort_triggers'], $_REQUEST['valign'],
				$_REQUEST['halign'], $_REQUEST['style'], $_REQUEST['url'], $_REQUEST['dynmic']);
			if (!$result) {
				throw new Exception();
			}
		}
		else {
			$result = add_screen_item(
				$_REQUEST['resourcetype'], $_REQUEST['screenid'],
				$_REQUEST['x'], $_REQUEST['y'], $_REQUEST['resourceid'],
				$_REQUEST['width'], $_REQUEST['height'], $_REQUEST['colspan'],
				$_REQUEST['rowspan'], $_REQUEST['elements'], $_REQUEST['sort_triggers'], $_REQUEST['valign'],
				$_REQUEST['halign'], $_REQUEST['style'], $_REQUEST['url'], $_REQUEST['dynmic']);
			if (!$result) {
				throw new Exception();
			}
		}

		DBend(true);

		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, ' Name ['.$screen['name'].'] cell changed '.
			(isset($_REQUEST['screenitemid']) ? '['.$_REQUEST['screenitemid'].']' : '['.$_REQUEST['x'].','.$_REQUEST['y'].']'));

		unset($_REQUEST['form']);
		show_messages(true, $msg_ok, $msg_err);
	}
	catch(Exception $e) {
		DBend(false);
		error($e->getMessage());
		show_messages(false, $msg_ok, $msg_err);
	}
}
elseif (isset($_REQUEST['delete'])) {
	DBstart();
	$result = delete_screen_item($_REQUEST['screenitemid']);
	$result = DBend($result);

	show_messages($result, _('Item deleted'), _('Cannot delete item'));
	if ($result) {
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, ' Name ['.$screen['name'].'] Item deleted');
	}
	unset($_REQUEST['x']);
}
elseif (isset($_REQUEST['add_row'])) {
	$add_row = get_request('add_row', 0);
	DBexecute('UPDATE screens SET vsize=(vsize+1) WHERE screenid='.$screen['screenid']);
	if ($screen['vsize'] > $add_row) {
		DBexecute('UPDATE screens_items SET y=(y+1) WHERE screenid='.$screen['screenid'].' AND y>='.$add_row);
	}
	add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Row added');
}
elseif (isset($_REQUEST['add_col'])) {
	$add_col = get_request('add_col', 0);
	DBexecute('UPDATE screens SET hsize=(hsize+1) WHERE screenid='.$screen['screenid']);
	if ($screen['hsize'] > $add_col) {
		DBexecute('UPDATE screens_items SET x=(x+1) WHERE screenid='.$screen['screenid'].' AND x>='.$add_col);
	}
	add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Column added');
}
elseif (isset($_REQUEST['rmv_row'])) {
	$rmv_row = get_request('rmv_row', 0);
	if ($screen['vsize'] > 1) {
		DBexecute('UPDATE screens SET vsize=(vsize-1) WHERE screenid='.$screen['screenid']);
		DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND y='.$rmv_row);
		DBexecute('UPDATE screens_items SET y=(y-1) WHERE screenid='.$screen['screenid'].' AND y>'.$rmv_row);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Row deleted');
	}
	else {
		error(_('Screen should contain at least one row and column'));
		show_messages(false, '', _('Impossible to remove last row and column'));
	}
}
elseif (isset($_REQUEST['rmv_col'])) {
	$rmv_col = get_request('rmv_col', 0);
	if ($screen['hsize'] > 1) {
		DBexecute('UPDATE screens SET hsize=(hsize-1) WHERE screenid='.$screen['screenid']);
		DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND x='.$rmv_col);
		DBexecute('UPDATE screens_items SET x=(x-1) WHERE screenid='.$screen['screenid'].' AND x>'.$rmv_col);
		add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN,' Name ['.$screen['name'].'] Column deleted');
	}
	else {
		error(_('Screen should contain at least one row and column'));
		show_messages(false, '', _('Impossible to remove last row and column'));
	}
}

$screen_wdgt = new CWidget();
$screen_wdgt->addPageHeader(_('CONFIGURATION OF SCREEN'));
$screen_wdgt->addHeader($screen['name']);
$screen_wdgt->addItem(BR());

if ($screen['templateid']) {
	$screen_wdgt->addItem(get_header_host_table($screen['templateid']));
}

// getting updated screen, so we wont have to refresh the page to see changes
$screens = API::Screen()->get($options);
if (empty($screens)) {
	$screens = API::TemplateScreen()->get($options);
	if (empty($screens)) {
		access_deny();
	}
}
$screen = reset($screens);

$table = get_screen($screen, 1); // 1 - edit mode
$screen_wdgt->addItem($table);
zbx_add_post_js('init_screen("'.$_REQUEST['screenid'].'", "iframe", "'.$_REQUEST['screenid'].'");');
zbx_add_post_js('timeControl.processObjects();');

$screen_wdgt->show();

include_once('include/page_footer.php');
?>
