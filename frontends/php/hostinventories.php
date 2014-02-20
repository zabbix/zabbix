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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventory');
$page['file'] = 'hostinventories.php';
$page['hist_arg'] = array('groupid', 'hostid');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	// filter
	'filter_set' =>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,		null),
	'inventory'=>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	//ajax
	'filterState' =>		array(T_ZBX_INT, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable(array(getRequest('groupid')))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable(array(getRequest('hostid')))) {
	access_deny();
}

validate_sort_and_sortorder('name', ZBX_SORT_UP);

if (hasRequest('filterState')) {
	CProfile::update('web.hostinventories.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$hostid = getRequest('hostid', 0);

// update filter
if (hasRequest('filter_set')) {
	// update host inventory filter
	$i = 0;
	foreach (getRequest('inventory', array()) as $field) {
		if ($field['value'] === '') {
			continue;
		}

		CProfile::update('web.hostinventories.filter.inventory.field', $field['field'], PROFILE_TYPE_STR, $i);
		CProfile::update('web.hostinventories.filter.inventory.value', $field['value'], PROFILE_TYPE_STR, $i);

		$i++;
	}
	// delete remaining old values
	while (CProfile::get('web.hostinventories.filter.inventory.field', null, $i) !== null) {
		CProfile::delete('web.hostinventories.filter.inventory.field', $i);
		CProfile::delete('web.hostinventories.filter.inventory.value', $i);

		$i++;
	}
}

// fetch filter from profiles
$filter = array();
$i = 0;
while (CProfile::get('web.hostinventories.filter.inventory.field', null, $i) !== null) {
	$filter[] = array(
		'field' => CProfile::get('web.hostinventories.filter.inventory.field', null, $i),
		'value' => CProfile::get('web.hostinventories.filter.inventory.value', null, $i)
	);

	$i++;
}

$data = array();

/*
 * Display
 */
if ($hostid > 0) {
	// host scripts
	$data['hostScripts'] = API::Script()->getScriptsByHosts($hostid);

	// inventory info
	$data['tableTitles'] = getHostInventories();
	$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
	$inventoryFields = array_keys($data['tableTitles']);

	// overview tab
	$data['host'] = API::Host()->get(array(
		'hostids' => $hostid,
		'output' => array('hostid', 'host', 'name', 'maintenance_status'),
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => $inventoryFields,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'preservekeys' => true
	));
	$data['host'] = reset($data['host']);
	unset($data['host']['inventory']['hostid']);

	// resolve macros
	$data['host']['interfaces'] = CMacrosResolverHelper::resolveHostInterfaces($data['host']['interfaces']);

	// get permissions
	$userType = CWebUser::getType();
	if ($userType == USER_TYPE_SUPER_ADMIN) {
		$data['rwHost'] = true;
	}
	else if ($userType == USER_TYPE_ZABBIX_ADMIN) {
		$rwHost = API::Host()->get(array(
			'output' => array('hostid'),
			'hostids' => $hostid,
			'editable' => true
		));

		$data['rwHost'] = $rwHost ? true : false;
	}
	else {
		$data['rwHost'] = false;
	}

	// view generation
	$hostinventoriesView = new CView('inventory.host.view', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}
else{
	$pageFilter = new CPageFilter(array(
		'groups' => array(
			'real_hosts' => 1,
		),
		'groupid' => getRequest('groupid'),
	));

	$data = array(
		'config' => select_config(),
		'pageFilter' => $pageFilter,
		'hosts' => array(),
		'filter' => $filter
	);

	if ($pageFilter->groupsSelected) {
		$inventoryFilter = array();
		foreach ($filter as $inventoryField) {
			$inventoryFilter[$inventoryField['field']][] = $inventoryField['value'];
		}

		$data['hosts'] = API::Host()->get(array(
			'output' => array('hostid', 'name', 'status'),
			'selectInventory' => array('name', 'type', 'os', 'serialno_a', 'tag', 'macaddress_a'),
			'selectGroups' => API_OUTPUT_EXTEND,
			'groupids' => ($pageFilter->groupid) ? $pageFilter->groupid : null,
			'withInventory' => true,
			'searchInventory' => $inventoryFilter,
			'limit' => ($data['config']['search_limit'] + 1)
		));

		// copy some inventory fields to the uppers array level for sorting
		foreach ($data['hosts'] as $num => $host) {
			$data['hosts'][$num]['pr_name'] = $host['inventory']['name'];
			$data['hosts'][$num]['pr_type'] = $host['inventory']['type'];
			$data['hosts'][$num]['pr_os'] = $host['inventory']['os'];
			$data['hosts'][$num]['pr_serialno_a'] = $host['inventory']['serialno_a'];
			$data['hosts'][$num]['pr_tag'] = $host['inventory']['tag'];
			$data['hosts'][$num]['pr_macaddress_a'] = $host['inventory']['macaddress_a'];
		}

		order_result($data['hosts'], getPageSortField('name'), getPageSortOrder());
	}

	$data['paging'] = getPagingLine($data['hosts']);

	$hostinventoriesView = new CView('inventory.host.list', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();

}

require_once dirname(__FILE__).'/include/page_footer.php';
