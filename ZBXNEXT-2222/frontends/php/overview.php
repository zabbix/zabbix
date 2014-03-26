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
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/items.inc.php';

$page['title'] = _('Overview');
$page['file'] = 'overview.php';
$page['hist_arg'] = array('groupid', 'type');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_DO_REFRESH', 1);
define('SHOW_TRIGGERS', 0);
define('SHOW_DATA', 1);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid'     => array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,     null),
	'view_style'  => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null),
	'type'        => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null),
	'fullscreen'  => array(T_ZBX_INT, O_OPT, P_SYS, IN('0,1'), null),
	// filter
	'filter_rst' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_set' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'show_triggers' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'ack_status' =>			array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'show_severity' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,		null),
	'show_maintenance' =>	array(T_ZBX_INT, O_OPT, null,	null,		null),
	'status_change_days' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, DAY_IN_YEAR * 2), null),
	'status_change' =>		array(T_ZBX_INT, O_OPT, null,	null,		null),
	'txt_select' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	'application' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'inventory' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>				array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favstate' =>			array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})')
);
check_fields($fields);

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('favobj')) {
	if (getRequest('favobj') === 'filter') {
		CProfile::update('web.overview.filter.state', getRequest('favstate'), PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

// show triggers
// the state of this filter must not be remembered in the profiles because setting it's value to "All" may render the
// whole page inaccessible on large installations.
$showTriggers = getRequest('show_triggers', TRIGGERS_OPTION_ONLYTRUE);

$config = select_config();
if (hasRequest('filter_set')) {
	CProfile::update('web.overview.filter.show_maintenance', getRequest('show_maintenance', 0), PROFILE_TYPE_INT);
	CProfile::update('web.overview.filter.show_severity', getRequest('show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		PROFILE_TYPE_INT
	);
	CProfile::update('web.overview.filter.status_change', getRequest('status_change', 0), PROFILE_TYPE_INT);

	// ack status
	if (($config['event_ack_enable'] == EVENT_ACK_ENABLED) && hasRequest('ack_status')) {
		CProfile::update('web.overview.filter.ack_status', getRequest('ack_status'), PROFILE_TYPE_INT);
	}

	// status change days
	if (hasRequest('status_change_days')) {
		CProfile::update('web.overview.filter.status_change_days', getRequest('status_change_days'), PROFILE_TYPE_INT);
	}

	// name
	if (getRequest('txt_select') !== '') {
		CProfile::update('web.overview.filter.txt_select', getRequest('txt_select'), PROFILE_TYPE_STR);
	}
	else {
		CProfile::delete('web.overview.filter.txt_select');
	}

	// application
	if (getRequest('application') !== '') {
		CProfile::update('web.overview.filter.application', getRequest('application'), PROFILE_TYPE_STR);
	}
	else {
		CProfile::delete('web.overview.filter.application');
	}

	// update host inventory filter
	$i = 0;
	foreach (getRequest('inventory', array()) as $field) {
		if ($field['value'] === '') {
			continue;
		}

		CProfile::update('web.overview.filter.inventory.field', $field['field'], PROFILE_TYPE_STR, $i);
		CProfile::update('web.overview.filter.inventory.value', $field['value'], PROFILE_TYPE_STR, $i);

		$i++;
	}

	// delete remaining old values
	$idx2 = array();
	while (CProfile::get('web.overview.filter.inventory.field', null, $i) !== null) {
		$idx2[] = $i;

		$i++;
	}

	CProfile::delete('web.overview.filter.inventory.field', $idx2);
	CProfile::delete('web.overview.filter.inventory.value', $idx2);
}
elseif (hasRequest('filter_rst')) {
	$showTriggers = TRIGGERS_OPTION_ONLYTRUE;

	CProfile::delete('web.overview.filter.show_maintenance');
	CProfile::delete('web.overview.filter.ack_status');
	CProfile::delete('web.overview.filter.show_severity');
	CProfile::delete('web.overview.filter.txt_select');
	CProfile::delete('web.overview.filter.status_change');
	CProfile::delete('web.overview.filter.status_change_days');
	CProfile::delete('web.overview.filter.application');

	// reset inventory filters
	$i = 0;
	while (CProfile::get('web.overview.filter.inventory.field', null, $i) !== null) {
		CProfile::delete('web.overview.filter.inventory.field', $i);
		CProfile::delete('web.overview.filter.inventory.value', $i);

		$i++;
	}
}

// overview type
if (hasRequest('type')) {
	CProfile::update('web.overview.type', getRequest('type'), PROFILE_TYPE_INT);
}
$type = CProfile::get('web.overview.type', SHOW_TRIGGERS);

/*
 * Display
 */
// filter data
$filter = array(
	'showTriggers' => $showTriggers,
	'ackStatus' => CProfile::get('web.overview.filter.ack_status', 0),
	'showSeverity' => CProfile::get('web.overview.filter.show_severity', TRIGGER_SEVERITY_NOT_CLASSIFIED),
	'statusChange' => CProfile::get('web.overview.filter.status_change', 0),
	'statusChangeDays' => CProfile::get('web.overview.filter.status_change_days', 14),
	'txtSelect' => CProfile::get('web.overview.filter.txt_select', ''),
	'application' => CProfile::get('web.overview.filter.application', ''),
	'showMaintenance' => CProfile::get('web.overview.filter.show_maintenance', 1),
	'inventory' => array()
);
$i = 0;
while (CProfile::get('web.overview.filter.inventory.field', null, $i) !== null) {
	$filter['inventory'][] = array(
		'field' => CProfile::get('web.overview.filter.inventory.field', null, $i),
		'value' => CProfile::get('web.overview.filter.inventory.value', null, $i)
	);

	$i++;
}

$data = array(
	'fullscreen' => $_REQUEST['fullscreen'],
	'type' => $type,
	'filter' => $filter
);

$data['view_style'] = get_request('view_style', CProfile::get('web.overview.view.style', STYLE_TOP));
CProfile::update('web.overview.view.style', $data['view_style'], PROFILE_TYPE_INT);

$data['pageFilter'] = new CPageFilter(array(
	'groups' => array(
		($data['type'] == SHOW_TRIGGERS ? 'with_monitored_triggers' : 'with_monitored_items') => true
	),
	'hosts' => array(
		'monitored_hosts' => true,
		($data['type'] == SHOW_TRIGGERS ? 'with_monitored_triggers' : 'with_monitored_items') => true
	),
	'applications' => array('templated' => false),
	'hostid' => get_request('hostid', null),
	'groupid' => get_request('groupid', null)
));

$data['groupid'] = $data['pageFilter']->groupid;
$data['hostid'] = $data['pageFilter']->hostid;

// render view
$overviewView = new CView('monitoring.overview', $data);
$overviewView->render();
$overviewView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
