<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
$page['scripts'] = ['class.cscreen.js', 'class.calendar.js', 'gtlc.js', 'class.svg.canvas.js', 'class.svg.map.js',
	'flickerfreescreen.js', 'multiselect.js'
];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

$knownResourceTypes = [
	SCREEN_RESOURCE_GRAPH,
	SCREEN_RESOURCE_SIMPLE_GRAPH,
	SCREEN_RESOURCE_MAP,
	SCREEN_RESOURCE_PLAIN_TEXT,
	SCREEN_RESOURCE_HOST_INFO,
	SCREEN_RESOURCE_TRIGGER_INFO,
	SCREEN_RESOURCE_SERVER_INFO,
	SCREEN_RESOURCE_CLOCK,
	SCREEN_RESOURCE_SCREEN,
	SCREEN_RESOURCE_TRIGGER_OVERVIEW,
	SCREEN_RESOURCE_DATA_OVERVIEW,
	SCREEN_RESOURCE_URL,
	SCREEN_RESOURCE_ACTIONS,
	SCREEN_RESOURCE_EVENTS,
	SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
	SCREEN_RESOURCE_SYSTEM_STATUS,
	SCREEN_RESOURCE_HOST_TRIGGERS,
	SCREEN_RESOURCE_HISTORY,
	SCREEN_RESOURCE_CHART,
	SCREEN_RESOURCE_LLD_SIMPLE_GRAPH,
	SCREEN_RESOURCE_LLD_GRAPH
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'screenid' =>		[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,			null],
	'screenitemid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'resourcetype' =>	[T_ZBX_INT, O_OPT, null,	IN($knownResourceTypes), 'isset({add}) || isset({update})'],
	'caption' =>		[T_ZBX_STR, O_OPT, null,	null,			null],
	'resourceid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,			null,
		hasRequest('add') || hasRequest('update') ? getResourceNameByType(getRequest('resourcetype')) : null],
	'templateid' =>		[T_ZBX_INT, O_OPT, null,	DB_ID,			null],
	'width' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Width')],
	'height' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), null, _('Height')],
	'max_columns' =>	[T_ZBX_INT, O_OPT, null,
		BETWEEN(SCREEN_SURROGATE_MAX_COLUMNS_MIN, SCREEN_SURROGATE_MAX_COLUMNS_MAX), null, _('Max columns')
	],
	'colspan' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), null, _('Column span')],
	'rowspan' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), null, _('Row span')],
	'elements' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), null, _('Show lines')],
	'sort_triggers' =>	[T_ZBX_INT, O_OPT, null,	BETWEEN(SCREEN_SORT_TRIGGERS_DATE_DESC, SCREEN_SORT_TRIGGERS_RECIPIENT_DESC), null],
	'valign' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(VALIGN_MIDDLE, VALIGN_BOTTOM), null],
	'halign' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(HALIGN_CENTER, HALIGN_RIGHT), null],
	'style' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 2),	'isset({add}) || isset({update})'],
	'url' =>			[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'dynamic' =>		[T_ZBX_INT, O_OPT, null,	null,			null],
	'x' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), '(isset({add}) || isset({update})) && isset({form}) && {form} != "update"'],
	'y' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), '(isset({add}) || isset({update})) && isset({form}) && {form} != "update"'],
	'screen_type' =>	[T_ZBX_INT, O_OPT, null,	null,			null],
	'tr_groupid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'tr_hostid' =>		[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'application' =>	[T_ZBX_STR, O_OPT, null,	null,			null],
	// actions
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'delete' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'cancel' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,	null,			null],
	'add_row' =>		[T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(0, 100), null],
	'add_col' =>		[T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(0, 100), null],
	'rmv_row' =>		[T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(0, 100), null],
	'rmv_col' =>		[T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(0, 100), null],
	'sw_pos' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 100), null],
	'ajaxAction' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,			null]
];
check_fields($fields);
$_REQUEST['dynamic'] = getRequest('dynamic', SCREEN_SIMPLE_ITEM);

/*
 * Permissions
 */
$options = [
	'screenids' => $_REQUEST['screenid'],
	'editable' => true,
	'output' => API_OUTPUT_EXTEND,
	'selectScreenItems' => API_OUTPUT_EXTEND
];
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
	$sw_pos = getRequest('sw_pos', []);
	if (count($sw_pos) > 3) {
		DBstart();

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
			DBexecute('UPDATE screens_items'.
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
			DBexecute('UPDATE screens_items '.
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

		DBend(true);
	}
	echo '{"result": true}';
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$screenItem = [
		'screenid' => getRequest('screenid'),
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
		'max_columns' => getRequest('max_columns'),
		'dynamic' => getRequest('dynamic'),
		'elements' => getRequest('elements', 0),
		'sort_triggers' => getRequest('sort_triggers', SCREEN_SORT_TRIGGERS_DATE_DESC),
		'application' => getRequest('application', '')
	];

	if (hasRequest('resourceid')) {
		$screenItem['resourceid'] = getRequest('resourceid');
	}

	DBstart();

	if (hasRequest('update')) {
		$screenItem['screenitemid'] = getRequest('screenitemid');

		$result = API::ScreenItem()->update($screenItem);
	}
	else {
		$screenItem['x'] = getRequest('x');
		$screenItem['y'] = getRequest('y');

		$result = API::ScreenItem()->create($screenItem);
	}

	if ($result) {
		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'Cell changed '.
			(hasRequest('screenitemid') ? 'screen itemid "'.getRequest('screenitemid').'"' : '').
			(hasRequest('x') && hasRequest('y') ? ' coordinates "'.getRequest('x').','.getRequest('y').'"' : '').
			(hasRequest('resourcetype') ? ' resource type "'.getRequest('resourcetype').'"' : '')
		);
		unset($_REQUEST['form']);
	}

	$result = DBend($result);
	show_messages($result, _('Screen updated'), _('Cannot update screen'));
}
elseif (hasRequest('delete')) {
	DBstart();

	$screenitemid = API::ScreenItem()->delete([getRequest('screenitemid')]);

	if ($screenitemid) {
		$screenitemid = reset($screenitemid);
		$screenitemid = reset($screenitemid);
		add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'],
			'Screen itemid "'.$screenitemid.'"'
		);
	}
	unset($_REQUEST['x']);

	$result = DBend($screenitemid);
	show_messages($result, _('Screen updated'), _('Cannot update screen'));
}
elseif (hasRequest('add_row')) {
	addScreenRow($screen, getRequest('add_row'));
}
elseif (hasRequest('add_col')) {
	addScreenColumn($screen, getRequest('add_col'));
}
elseif (hasRequest('rmv_row')) {
	delScreenRow($screen, getRequest('rmv_row'));
}
elseif (hasRequest('rmv_col')) {
	delScreenColumn($screen, getRequest('rmv_col'));
}

show_messages();

/*
 * Display
 */
$data = [
	'screenid' => getRequest('screenid', 0)
];

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
$screenView = new CView('monitoring.screen.constructor.list', $data);
$screenView->render();
$screenView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
