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
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Audit log');
$page['file'] = 'auditlogs.php';
$page['hist_arg'] = array();
$page['scripts'] = array('class.calendar.js', 'gtlc.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'action' =>			array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(-1, 6), null),
	'resourcetype' =>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(-1, 31), null),
	'filter_rst' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'filter_set' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'alias' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'period' =>			array(T_ZBX_INT, O_OPT, null,	null,	null),
	'stime' =>			array(T_ZBX_STR, O_OPT, null,	null,	null),
	// ajax
	'filterState' =>	array(T_ZBX_INT, O_OPT, P_ACT,	null,	null),
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,	null),
	'favid' =>			array(T_ZBX_INT, O_OPT, P_ACT,	null,	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.auditlogs.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}
if (isset($_REQUEST['favobj'])) {
	// saving fixed/dynamic setting to profile
	if ($_REQUEST['favobj'] == 'timelinefixedperiod') {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.auditlogs.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}
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
$effectivePeriod = navigation_bar_calc('web.auditlogs.timeline', 0, true);
$data = array(
	'stime' => getRequest('stime'),
	'actions' => array(),
	'action' => CProfile::get('web.auditlogs.filter.action', -1),
	'resourcetype' => CProfile::get('web.auditlogs.filter.resourcetype', -1),
	'alias' => CProfile::get('web.auditlogs.filter.alias', '')
);

$from = zbxDateToTime($data['stime']);
$till = $from + $effectivePeriod;

// get audit
$sqlWhere = array();
if (!empty($data['alias'])) {
	$sqlWhere['alias'] = ' AND u.alias='.zbx_dbstr($data['alias']);
}
if ($data['action'] > -1) {
	$sqlWhere['action'] = ' AND a.action='.zbx_dbstr($data['action']);
}
if ($data['resourcetype'] > -1) {
	$sqlWhere['resourcetype'] = ' AND a.resourcetype='.zbx_dbstr($data['resourcetype']);
}
$sqlWhere['from'] = ' AND a.clock>'.zbx_dbstr($from);
$sqlWhere['till'] = ' AND a.clock<'.zbx_dbstr($till);

$sql = 'SELECT a.auditid,a.clock,u.alias,a.ip,a.resourcetype,a.action,a.resourceid,a.resourcename,a.details'.
		' FROM auditlog a,users u'.
		' WHERE a.userid=u.userid'.
			implode('', $sqlWhere).
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
$data['paging'] = getPagingLine($data['actions']);

// get timeline
unset($sqlWhere['from'], $sqlWhere['till']);

$sql = 'SELECT MIN(a.clock) AS clock'.
		' FROM auditlog a,users u'.
		' WHERE a.userid=u.userid'.
			implode('', $sqlWhere);
$firstAudit = DBfetch(DBselect($sql, $config['search_limit'] + 1));

$data['timeline'] = array(
	'period' => $effectivePeriod,
	'starttime' => date(TIMESTAMP_FORMAT, $firstAudit ? $firstAudit['clock'] : null),
	'usertime' => isset($_REQUEST['stime']) ? date(TIMESTAMP_FORMAT, zbxDateToTime($data['stime']) + $effectivePeriod) : null
);

// render view
$auditView = new CView('administration.auditlogs.list', $data);
$auditView->render();
$auditView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
