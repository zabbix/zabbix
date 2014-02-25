<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
$page['scripts'] = array('class.cscreen.js', 'class.calendar.js', 'gtlc.js', 'flickerfreescreen.js', 'multiselect.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'screenid' =>		array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,			null),
	'screenitemid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'resourcetype' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 16),	'isset({save})'),
	'caption' =>		array(T_ZBX_STR, O_OPT, null,	null,			null),
	'resourceid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({save})',
		hasRequest('save') ? getResourceNameByType($_REQUEST['resourcetype']) : null),
	'templateid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,			null),
	'width' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Width')),
	'height' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Height')),
	'colspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null, _('Column span')),
	'rowspan' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null, _('Row span')),
	'elements' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), null, _('Show lines')),
	'sort_triggers' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(SCREEN_SORT_TRIGGERS_DATE_DESC, SCREEN_SORT_TRIGGERS_RECIPIENT_DESC), null),
	'valign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(VALIGN_MIDDLE, VALIGN_BOTTOM), null),
	'halign' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(HALIGN_CENTER, HALIGN_RIGHT), null),
	'style' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 2),	'isset({save})'),
	'url' =>			array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'dynamic' =>		array(T_ZBX_INT, O_OPT, null,	null,			null),
	'x' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})&&isset({form})&&{form}!="update"'),
	'y' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})&&isset({form})&&{form}!="update"'),
	'screen_type' =>	array(T_ZBX_INT, O_OPT, null,	null,			null),
	'tr_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'tr_hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'application' =>	array(T_ZBX_STR, O_OPT, null,	null,			null),
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
$_REQUEST['dynamic'] = getRequest('dynamic', SCREEN_SIMPLE_ITEM);

/*
 * Permissions
 */
$options = array(
	'output' => API_OUTPUT_EXTEND,
	'selectScreenItems' => API_OUTPUT_EXTEND,
	'screenids' => getRequest('screenid'),
	'editable' => true
);
$screens = API::Screen()->get($options);

if (!$screens) {
	$screens = API::TemplateScreen()->get($options);

	if (!$screens) {
		access_deny();
	}
}

$screen = reset($screens);

/*
 * Ajax
 */
if (getRequest('ajaxAction') === 'sw_pos') {
	$sw_pos = getRequest('sw_pos', array());

	if (count($sw_pos) > 3) {
		$fitem = DBfetch(DBselect(
			'SELECT s.screenitemid,s.colspan,s.rowspan'.
			' FROM screens_items s'.
			' WHERE s.y='.zbx_dbstr($sw_pos[0]).
				' AND s.x='.zbx_dbstr($sw_pos[1]).
				' AND s.screenid='.zbx_dbstr($screen['screenid'])
		));

		$sitem = DBfetch(DBselect(
			'SELECT s.screenitemid,s.colspan,s.rowspan'.
			' FROM screens_items s'.
			' WHERE s.y='.zbx_dbstr($sw_pos[2]).
				' AND s.x='.zbx_dbstr($sw_pos[3]).
				' AND s.screenid='.zbx_dbstr($screen['screenid'])
		));

		if ($fitem) {
			DBexecute(
				'UPDATE screens_items'.
				' SET y='.zbx_dbstr($sw_pos[2]).',x='.zbx_dbstr($sw_pos[3]).
				',colspan='.(isset($sitem['colspan']) ? zbx_dbstr($sitem['colspan']) : 1).
				',rowspan='.(isset($sitem['rowspan']) ? zbx_dbstr($sitem['rowspan']) : 1).
				' WHERE y='.zbx_dbstr($sw_pos[0]).
					' AND x='.zbx_dbstr($sw_pos[1]).
					' AND screenid='.zbx_dbstr($screen['screenid']).
					' AND screenitemid='.zbx_dbstr($fitem['screenitemid'])
			);
		}

		if ($sitem) {
			DBexecute(
				'UPDATE screens_items'.
				' SET y='.zbx_dbstr($sw_pos[0]).',x='.zbx_dbstr($sw_pos[1]).
				',colspan='.(isset($fitem['colspan']) ? zbx_dbstr($fitem['colspan']) : 1).
				',rowspan='.(isset($fitem['rowspan']) ? zbx_dbstr($fitem['rowspan']) : 1).
				' WHERE y='.zbx_dbstr($sw_pos[2]).
					' AND x='.zbx_dbstr($sw_pos[3]).
					' AND screenid='.zbx_dbstr($screen['screenid']).
					' AND screenitemid='.zbx_dbstr($sitem['screenitemid'])
			);
		}

		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Screen items switched');
	}

	echo '{"result": true}';
}

if (in_array($page['type'], array(PAGE_TYPE_JS, PAGE_TYPE_HTML_BLOCK))) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Actions
 */
if (hasRequest('save')) {
	$screenItem = array(
		'screenid' => getRequest('screenid'),
		'resourceid' => getRequest('resourceid'),
		'resourcetype' => getRequest('resourcetype'),
		'caption' => getRequest('caption'),
		'style' => getRequest('style'),
		'url' => getRequest('url'),
		'width' => getRequest('width'),
		'height' => getRequest('height'),
		'halign' => getRequest('halign'),
		'valign' => getRequest('valign'),
		'colspan' => getRequest('colspan'),
		'rowspan' => getRequest('rowspan'),
		'dynamic' => getRequest('dynamic'),
		'elements' => getRequest('elements', 0),
		'sort_triggers' => getRequest('sort_triggers', SCREEN_SORT_TRIGGERS_DATE_DESC),
		'application' => getRequest('application', '')
	);

	DBstart();

	if (hasRequest('screenitemid')) {
		$screenItem['screenitemid'] = getRequest('screenitemid');

		$result = API::ScreenItem()->update($screenItem);

		show_messages($result, _('Item updated'), _('Cannot update item'));
	}
	else {
		$screenItem['x'] = getRequest('x');
		$screenItem['y'] = getRequest('y');

		$result = API::ScreenItem()->create($screenItem);

		show_messages($result, _('Item added'), _('Cannot add item'));
	}

	DBend($result);

	if ($result) {
		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'],
			'Cell changed '.
			(hasRequest('screenitemid') ? 'screen itemid "'.getRequest('screenitemid').'"' : '').
			((hasRequest('x') && hasRequest('y')) ? ' coordinates "'.getRequest('x').','.getRequest('y').'"' : '').
			(hasRequest('resourcetype') ? ' resource type "'.getRequest('resourcetype').'"' : '')
		);

		unset($_REQUEST['form']);
	}
}
elseif (hasRequest('delete')) {
	DBstart();

	$deletedScreenItems = API::ScreenItem()->delete(getRequest('screenitemid'));

	if ($deletedScreenItems) {
		$screenItemId = reset($deletedScreenItems['screenitemids']);

		add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'],
			'Screen itemid "'.$screenItemId.'"'
		);
	}

	$result = DBend($deletedScreenItems);

	show_messages($result, _('Item deleted'), _('Cannot delete item'));
}
elseif (hasRequest('add_row')) {
	DBexecute('UPDATE screens SET vsize=(vsize+1) WHERE screenid='.zbx_dbstr($screen['screenid']));

	$addRow = getRequest('add_row', 0);

	if ($screen['vsize'] > $addRow) {
		DBexecute(
			'UPDATE screens_items SET y=(y+1)'.
			' WHERE screenid='.zbx_dbstr($screen['screenid']).
				' AND y>='.zbx_dbstr($addRow)
		);
	}

	add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Row added');
}
elseif (hasRequest('add_col')) {
	DBexecute('UPDATE screens SET hsize=(hsize+1) WHERE screenid='.zbx_dbstr($screen['screenid']));

	$addColumn = getRequest('add_col', 0);

	if ($screen['hsize'] > $addColumn) {
		DBexecute(
			'UPDATE screens_items SET x=(x+1)'.
			' WHERE screenid='.zbx_dbstr($screen['screenid']).
				' AND x>='.zbx_dbstr($addColumn)
		);
	}

	add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Column added');
}
elseif (hasRequest('rmv_row')) {
	$result = false;

	if ($screen['vsize'] > 1) {
		DBstart();

		$result = API::Screen()->update(array(
			'screenid' => getRequest('screenid'),
			'vsize' => getRequest('rmv_row')
		));

		add_audit_details(
			AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Row deleted'
		);

		$result = DBend($result);
	}

	if (!$result) {
		error(_('Screen should contain at least one row and column.'));
		show_error_message(_('Impossible to remove last row and column.'));
	}
}
elseif (hasRequest('rmv_col')) {
	$result = false;

	if ($screen['hsize'] > 1) {
		DBstart();

		$result = API::Screen()->update(array(
			'screenid' => getRequest('screenid'),
			'hsize' => getRequest('rmv_col')
		));

		add_audit_details(
			AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Column deleted'
		);

		$result = DBend($result);
	}

	if (!$result) {
		error(_('Screen should contain at least one row and column.'));
		show_error_message(_('Impossible to remove last row and column.'));
	}
}

/*
 * Display
 */
$data = array(
	'screenid' => getRequest('screenid', 0)
);

// getting updated screen, so we wont have to refresh the page to see changes
$data['screen'] = API::Screen()->get($options);

if (!$data['screen']) {
	$data['screen'] = API::TemplateScreen()->get($options);

	if (!$data['screen']) {
		access_deny();
	}
}

$data['screen'] = reset($data['screen']);

// render view
$screenView = new CView('configuration.screen.constructor.list', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
