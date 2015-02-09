<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Configuration of screens');
$page['file'] = 'screenedit.php';
$page['hist_arg'] = array('screenid');
$page['scripts'] = array('class.cscreen.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'screenid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,			null),
	'screenitemid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'resourcetype' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 16),	'isset({save})'),
	'caption' =>		array(T_ZBX_STR, O_OPT, null,	null,			null),
	'resourceid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({save})'),
	'templateid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,			null),
	'width' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Width')),
	'height' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Height')),
	'colspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null, _('Column span')),
	'rowspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null, _('Row span')),
	'elements' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 65535), null, _('Show lines')),
	'sort_triggers' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(SCREEN_SORT_TRIGGERS_DATE_DESC, SCREEN_SORT_TRIGGERS_RECIPIENT_DESC), null),
	'valign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(VALIGN_MIDDLE, VALIGN_BOTTOM), null),
	'halign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(HALIGN_CENTER, HALIGN_RIGHT), null),
	'style' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 2),	'isset({save})'),
	'url' =>			array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'dynamic' =>		array(T_ZBX_INT, O_OPT, null,	null,			null),
	'x' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})&&(isset({form})&&({form}!="update"))'),
	'y' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})&&(isset({form})&&({form}!="update"))'),
	'screen_type' =>	array(T_ZBX_INT, O_OPT, null,	null,			null),
	'tr_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'tr_hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	// actions
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,			null),
	'add_row' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null),
	'add_col' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null),
	'rmv_row' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null),
	'rmv_col' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null),
	'sw_pos' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null),
	'ajaxAction' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,			null)
);
check_fields($fields);
$_REQUEST['dynamic'] = get_request('dynamic', SCREEN_SIMPLE_ITEM);

/*
 * Permissions
 */
$options = array(
	'screenids' => $_REQUEST['screenid'],
	'editable' => true,
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
if (!empty($_REQUEST['ajaxAction']) && $_REQUEST['ajaxAction'] == 'sw_pos') {
	$sw_pos = get_request('sw_pos', array());
	if (count($sw_pos) > 3) {
		$fitem = DBfetch(DBselect(
			'SELECT s.screenitemid,s.colspan,s.rowspan'.
			' FROM screens_items s'.
			' WHERE s.y='.$sw_pos[0].
				' AND s.x='.$sw_pos[1].
				' AND s.screenid='.$screen['screenid']
		));

		$sitem = DBfetch(DBselect(
			'SELECT s.screenitemid,s.colspan,s.rowspan'.
			' FROM screens_items s'.
			' WHERE s.y='.$sw_pos[2].
				' AND s.x='.$sw_pos[3].
				' AND s.screenid='.$screen['screenid']
		));

		if ($fitem) {
			DBexecute('UPDATE screens_items'.
						' SET y='.$sw_pos[2].',x='.$sw_pos[3].
						',colspan='.(isset($sitem['colspan']) ? $sitem['colspan'] : 1).
						',rowspan='.(isset($sitem['rowspan']) ? $sitem['rowspan'] : 1).
						' WHERE y='.$sw_pos[0].
							' AND x='.$sw_pos[1].
							' AND screenid='.$screen['screenid'].
							' AND screenitemid='.$fitem['screenitemid']
			);
		}
		if ($sitem) {
			DBexecute('UPDATE screens_items '.
						' SET y='.$sw_pos[0].',x='.$sw_pos[1].
						',colspan='.(isset($fitem['colspan']) ? $fitem['colspan'] : 1).
						',rowspan='.(isset($fitem['rowspan']) ? $fitem['rowspan'] : 1).
						' WHERE y='.$sw_pos[2].
							' AND x='.$sw_pos[3].
							' AND screenid='.$screen['screenid'].
							' AND screenitemid='.$sitem['screenitemid']
			);
		}
		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Screen items switched');
	}
	echo '{"result": true}';
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	$screenItem = array(
		'screenid' => get_request('screenid'),
		'resourceid' => get_request('resourceid'),
		'resourcetype' => get_request('resourcetype'),
		'caption' => get_request('caption'),
		'style' => get_request('style'),
		'url' => get_request('url'),
		'width' => get_request('width'),
		'height' => get_request('height'),
		'halign' => get_request('halign'),
		'valign' => get_request('valign'),
		'colspan' => get_request('colspan'),
		'rowspan' => get_request('rowspan'),
		'dynamic' => get_request('dynamic'),
		'elements' => get_request('elements', 0),
		'sort_triggers' => get_request('sort_triggers', SCREEN_SORT_TRIGGERS_DATE_DESC)
	);

	DBstart();
	if (!empty($_REQUEST['screenitemid'])) {
		$screenItem['screenitemid'] = $_REQUEST['screenitemid'];

		$result = API::ScreenItem()->update($screenItem);
		show_messages($result, _('Item updated'), _('Cannot update item'));
	}
	else {
		$screenItem['x'] = get_request('x');
		$screenItem['y'] = get_request('y');

		$result = API::ScreenItem()->create($screenItem);
		show_messages($result, _('Item added'), _('Cannot add item'));
	}

	DBend($result);

	if ($result) {
		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Cell changed '.
			(isset($_REQUEST['screenitemid']) ? 'screen itemid "'.$_REQUEST['screenitemid'].'"' : '').
			(isset($_REQUEST['x']) && isset($_REQUEST['y']) ? ' coordinates "'.$_REQUEST['x'].','.$_REQUEST['y'].'"' : '').
			(isset($_REQUEST['resourcetype']) ? ' resource type "'.$_REQUEST['resourcetype'].'"' : '')
		);
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete'])) {
	DBstart();
	$screenitemid = API::ScreenItem()->delete($_REQUEST['screenitemid']);
	$result = DBend($screenitemid);

	show_messages($result, _('Item deleted'), _('Cannot delete item'));
	if ($result && !empty($screenitemid)) {
		$screenitemid = reset($screenitemid);
		$screenitemid = reset($screenitemid);
		add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Screen itemid "'.$screenitemid.'"');
	}
	unset($_REQUEST['x']);
}
elseif (isset($_REQUEST['add_row'])) {
	DBexecute('UPDATE screens SET vsize=(vsize+1) WHERE screenid='.$screen['screenid']);

	$add_row = get_request('add_row', 0);
	if ($screen['vsize'] > $add_row) {
		DBexecute('UPDATE screens_items SET y=(y+1) WHERE screenid='.$screen['screenid'].' AND y>='.$add_row);
	}
	add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Row added');
}
elseif (isset($_REQUEST['add_col'])) {
	DBexecute('UPDATE screens SET hsize=(hsize+1) WHERE screenid='.$screen['screenid']);

	$add_col = get_request('add_col', 0);
	if ($screen['hsize'] > $add_col) {
		DBexecute('UPDATE screens_items SET x=(x+1) WHERE screenid='.$screen['screenid'].' AND x>='.$add_col);
	}
	add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Column added');
}
elseif (isset($_REQUEST['rmv_row'])) {
	if ($screen['vsize'] > 1) {
		$rmv_row = get_request('rmv_row', 0);

		DBexecute('UPDATE screens SET vsize=(vsize-1) WHERE screenid='.$screen['screenid']);
		DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND y='.$rmv_row);
		DBexecute('UPDATE screens_items SET y=(y-1) WHERE screenid='.$screen['screenid'].' AND y>'.$rmv_row);

		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Row deleted');
	}
	else {
		error(_('Screen should contain at least one row and column.'));
		show_error_message(_('Impossible to remove last row and column.'));
	}
}
elseif (isset($_REQUEST['rmv_col'])) {
	if ($screen['hsize'] > 1) {
		$rmv_col = get_request('rmv_col', 0);

		DBexecute('UPDATE screens SET hsize=(hsize-1) WHERE screenid='.$screen['screenid']);
		DBexecute('DELETE FROM screens_items WHERE screenid='.$screen['screenid'].' AND x='.$rmv_col);
		DBexecute('UPDATE screens_items SET x=(x-1) WHERE screenid='.$screen['screenid'].' AND x>'.$rmv_col);

		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Column deleted');
	}
	else {
		error(_('Screen should contain at least one row and column.'));
		show_error_message(_('Impossible to remove last row and column.'));
	}
}

/*
 * Display
 */
$data = array(
	'screenid' => get_request('screenid', 0)
);

// getting updated screen, so we wont have to refresh the page to see changes
$data['screen'] = API::Screen()->get($options);
if (empty($data['screen'])) {
	$data['screen'] = API::TemplateScreen()->get($options);
	if (empty($data['screen'])) {
		access_deny();
	}
}
$data['screen'] = reset($data['screen']);

// render view
$screenView = new CView('configuration.screen.constructor.list', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
