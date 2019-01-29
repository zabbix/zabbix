<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Audit log');
$page['file'] = 'auditlogs.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js', 'flickerfreescreen.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

$action = [-1, AUDIT_ACTION_ADD, AUDIT_ACTION_UPDATE, AUDIT_ACTION_DELETE, AUDIT_ACTION_LOGIN, AUDIT_ACTION_LOGOUT,
	AUDIT_ACTION_ENABLE, AUDIT_ACTION_DISABLE
];
$resourcetype = [-1, AUDIT_RESOURCE_USER, AUDIT_RESOURCE_ZABBIX_CONFIG, AUDIT_RESOURCE_MEDIA_TYPE, AUDIT_RESOURCE_HOST,
	AUDIT_RESOURCE_ACTION, AUDIT_RESOURCE_GRAPH, AUDIT_RESOURCE_GRAPH_ELEMENT, AUDIT_RESOURCE_USER_GROUP,
	AUDIT_RESOURCE_APPLICATION, AUDIT_RESOURCE_TRIGGER, AUDIT_RESOURCE_HOST_GROUP, AUDIT_RESOURCE_ITEM,
	AUDIT_RESOURCE_IMAGE, AUDIT_RESOURCE_VALUE_MAP, AUDIT_RESOURCE_IT_SERVICE, AUDIT_RESOURCE_MAP,
	AUDIT_RESOURCE_SCREEN, AUDIT_RESOURCE_SCENARIO, AUDIT_RESOURCE_DISCOVERY_RULE, AUDIT_RESOURCE_SLIDESHOW,
	AUDIT_RESOURCE_SCRIPT, AUDIT_RESOURCE_PROXY, AUDIT_RESOURCE_MAINTENANCE, AUDIT_RESOURCE_REGEXP,
	AUDIT_RESOURCE_MACRO, AUDIT_RESOURCE_TEMPLATE, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, AUDIT_RESOURCE_ICON_MAP,
	AUDIT_RESOURCE_DASHBOARD, AUDIT_RESOURCE_CORRELATION, AUDIT_RESOURCE_GRAPH_PROTOTYPE, AUDIT_RESOURCE_ITEM_PROTOTYPE,
	AUDIT_RESOURCE_HOST_PROTOTYPE
];

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'action' =>			[T_ZBX_INT,			O_OPT, P_SYS,	IN($action), null],
	'resourcetype' =>	[T_ZBX_INT,			O_OPT, P_SYS,	IN($resourcetype), null],
	'filter_rst' =>		[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'filter_set' =>		[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'alias' =>			[T_ZBX_STR,			O_OPT, P_SYS,	null,	null],
	'from' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null],
	'to' =>				[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,	null]
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

/*
 * Ajax
 */
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.auditlogs.filter.alias', getRequest('alias', ''), PROFILE_TYPE_STR);
	CProfile::update('web.auditlogs.filter.action', getRequest('action', -1), PROFILE_TYPE_INT);
	CProfile::update('web.auditlogs.filter.resourcetype', getRequest('resourcetype', -1), PROFILE_TYPE_INT);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.auditlogs.filter.alias');
	CProfile::delete('web.auditlogs.filter.action');
	CProfile::delete('web.auditlogs.filter.resourcetype');
	DBend();
}

/*
 * Display
 */
$timeselector_options = [
	'profileIdx' => 'web.auditlogs.filter',
	'profileIdx2' => 0,
	'from' => getRequest('from'),
	'to' => getRequest('to')
];
updateTimeSelectorPeriod($timeselector_options);

$data = [
	'actions' => [],
	'action' => CProfile::get('web.auditlogs.filter.action', -1),
	'resourcetype' => CProfile::get('web.auditlogs.filter.resourcetype', -1),
	'alias' => CProfile::get('web.auditlogs.filter.alias', ''),
	'timeline' => getTimeSelectorPeriod($timeselector_options),
	'active_tab' => CProfile::get('web.auditlogs.filter.active', 1)
];

// get audit
$config = select_config();

$sqlWhere = [];
if (!empty($data['alias'])) {
	$sqlWhere['alias'] = ' AND u.alias='.zbx_dbstr($data['alias']);
}
if ($data['action'] > -1) {
	$sqlWhere['action'] = ' AND a.action='.zbx_dbstr($data['action']);
}
if ($data['resourcetype'] > -1) {
	$sqlWhere['resourcetype'] = ' AND a.resourcetype='.zbx_dbstr($data['resourcetype']);
}

$sql = 'SELECT a.auditid,a.clock,u.alias,a.ip,a.resourcetype,a.action,a.resourceid,a.resourcename,a.details'.
		' FROM auditlog a,users u'.
		' WHERE a.userid=u.userid'.
			implode('', $sqlWhere).
			' AND a.clock BETWEEN '.zbx_dbstr($data['timeline']['from_ts']).' AND '.zbx_dbstr($data['timeline']['to_ts']).
		' ORDER BY a.clock DESC';
$dbAudit = DBselect($sql, $config['search_limit'] + 1);
while ($audit = DBfetch($dbAudit)) {
	switch ($audit['action']) {
		case AUDIT_ACTION_ADD:
			$action = _('Added');
			break;
		case AUDIT_ACTION_UPDATE:
			$action = _('Updated');
			break;
		case AUDIT_ACTION_DELETE:
			$action = _('Deleted');
			break;
		case AUDIT_ACTION_LOGIN:
			$action = _('Login');
			break;
		case AUDIT_ACTION_LOGOUT:
			$action = _('Logout');
			break;
		case AUDIT_ACTION_ENABLE:
			$action = _('Enabled');
			break;
		case AUDIT_ACTION_DISABLE:
			$action = _('Disabled');
			break;
		default:
			$action = _('Unknown action');
	}
	$audit['action'] = $action;
	$audit['resourcetype'] = audit_resource2str($audit['resourcetype']);

	if (empty($audit['details'])) {
		$audit['details'] = DBfetchArray(DBselect(
			'SELECT ad.table_name,ad.field_name,ad.oldvalue,ad.newvalue'.
			' FROM auditlog_details ad'.
			' WHERE ad.auditid='.zbx_dbstr($audit['auditid'])
		));
	}
	$data['actions'][$audit['auditid']] = $audit;
}
if (!empty($data['actions'])) {
	order_result($data['actions'], 'clock', ZBX_SORT_DOWN);
}

// get paging
$data['paging'] = getPagingLine($data['actions'], ZBX_SORT_UP, new CUrl('auditlogs.php'));

// render view
$auditView = new CView('administration.auditlogs.list', $data);
$auditView->render();
$auditView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
