<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Dashboard configuration');
$page['file'] = 'dashconf.php';
$page['hist_arg'] = array();
$page['scripts'] = array('chosen.jquery.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'filterEnable' =>	array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'grpswitch' =>		array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 1),	null),
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'hidegroupids' =>	array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'trgSeverity' =>	array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'maintenance' =>	array(T_ZBX_INT, O_OPT, P_SYS,	BETWEEN(0, 1),	null),
	'extAck' =>			array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, P_SYS,	null,			null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null)
);
check_fields($fields);

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	// filter
	$filterEnable = get_request('filterEnable', 0);
	CProfile::update('web.dashconf.filter.enable', $filterEnable, PROFILE_TYPE_INT);

	if ($filterEnable == 1) {
		// groups
		CProfile::update('web.dashconf.groups.grpswitch', $_REQUEST['grpswitch'], PROFILE_TYPE_INT);

		if ($_REQUEST['grpswitch'] == 1) {
			// show groups
			$groupIds = get_request('groupids', array());

			$result = CFavorite::remove('web.dashconf.groups.groupids');
			foreach ($groupIds as $groupId) {
				$result &= CFavorite::add('web.dashconf.groups.groupids', $groupId);
			}

			// hide groups
			$hideGroupIds = get_request('hidegroupids', array());

			$result = CFavorite::remove('web.dashconf.groups.hide.groupids');
			foreach ($hideGroupIds as $hideGroupId) {
				$result &= CFavorite::add('web.dashconf.groups.hide.groupids', $hideGroupId);
			}
		}

		// hosts
		$_REQUEST['maintenance'] = get_request('maintenance', 0);
		CProfile::update('web.dashconf.hosts.maintenance', $_REQUEST['maintenance'], PROFILE_TYPE_INT);

		// triggers
		$_REQUEST['trgSeverity'] = get_request('trgSeverity', array());
		$_REQUEST['extAck'] = get_request('extAck', 0);

		CProfile::update('web.dashconf.triggers.severity', implode(';', array_keys($_REQUEST['trgSeverity'])), PROFILE_TYPE_STR);
		CProfile::update('web.dashconf.events.extAck', $_REQUEST['extAck'], PROFILE_TYPE_INT);
	}

	jsRedirect('dashboard.php');
}

/*
 * Display
 */
$data = array(
	'config' => select_config()
);

if (isset($_REQUEST['form_refresh'])) {
	$data['isFilterEnable'] = get_request('filterEnable', 0);
	$data['maintenance'] = get_request('maintenance', 0);
	$data['extAck'] = get_request('extAck', 0);

	$data['severity'] = get_request('trgSeverity', array());
	$data['severity'] = array_keys($data['severity']);

	// groups
	$data['grpswitch'] = get_request('grpswitch', 0);
	$data['groupIds'] = get_request('groupids', array());
	$data['groupIds'] = zbx_toHash($data['groupIds']);
	$data['hideGroupIds'] = get_request('hidegroupids', array());
	$data['hideGroupIds'] = zbx_toHash($data['hideGroupIds']);
}
else {
	$data['isFilterEnable'] = CProfile::get('web.dashconf.filter.enable', 0);
	$data['hgrpswitch'] = CProfile::get('web.dashconf.groups.hgrpswitch', 0);
	$data['maintenance'] = CProfile::get('web.dashconf.hosts.maintenance', 1);
	$data['extAck'] = CProfile::get('web.dashconf.events.extAck', 0);

	$data['severity'] = CProfile::get('web.dashconf.triggers.severity', '0;1;2;3;4;5');
	$data['severity'] = zbx_empty($data['severity']) ? array() : explode(';', $data['severity']);

	// groups
	$data['grpswitch'] = CProfile::get('web.dashconf.groups.grpswitch', 0);
	$data['groupIds'] = CFavorite::get('web.dashconf.groups.groupids');
	$data['groupIds'] = zbx_objectValues($data['groupIds'], 'value');
	$data['groupIds'] = zbx_toHash($data['groupIds']);
	$data['hideGroupIds'] = CFavorite::get('web.dashconf.groups.hide.groupids');
	$data['hideGroupIds'] = zbx_objectValues($data['hideGroupIds'], 'value');
	$data['hideGroupIds'] = zbx_toHash($data['hideGroupIds']);
}

$data['severity'] = zbx_toHash($data['severity']);
$data['severities'] = array(
	TRIGGER_SEVERITY_NOT_CLASSIFIED,
	TRIGGER_SEVERITY_INFORMATION,
	TRIGGER_SEVERITY_WARNING,
	TRIGGER_SEVERITY_AVERAGE,
	TRIGGER_SEVERITY_HIGH,
	TRIGGER_SEVERITY_DISASTER
);

if ($data['grpswitch']) {
	// show groups
	$data['groups'] = API::HostGroup()->get(array(
		'nodeids' => get_current_nodeid(true),
		'groupids' => $data['groupIds'],
		'output' => API_OUTPUT_EXTEND
	));

	foreach ($data['groups'] as &$group) {
		$group['nodename'] = get_node_name_by_elid($group['groupid'], true, ': ');
	}
	unset($group);

	CArrayHelper::sort($data['groups'], array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));

	// hide groups
	$data['hideGroups'] = API::HostGroup()->get(array(
		'nodeids' => get_current_nodeid(true),
		'groupids' => $data['hideGroupIds'],
		'output' => API_OUTPUT_EXTEND
	));
	foreach ($data['hideGroups'] as &$hideGroup) {
		$hideGroup['nodename'] = get_node_name_by_elid($hideGroup['groupid'], true, ': ');
	}
	unset($hideGroup);

	CArrayHelper::sort($data['hideGroups'], array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	));
}

// render view
$dashconfView = new CView('monitoring.dashconf', $data);
$dashconfView->render();
$dashconfView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
