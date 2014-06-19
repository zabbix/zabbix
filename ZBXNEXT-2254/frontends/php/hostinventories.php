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
	'groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// filter
	'filter_set' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_rst' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'filter_field' =>		array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_field_value' =>	array(T_ZBX_STR, O_OPT, null,	null,		null),
	'filter_exact' =>		array(T_ZBX_INT, O_OPT, null,	'IN(0,1)',	null),
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

validate_sort_and_sortorder('name', ZBX_SORT_UP,
	array('name', 'pr_name', 'pr_type', 'pr_os', 'pr_serialno_a', 'pr_tag', 'pr_macaddress_a')
);

if (hasRequest('filterState')) {
	CProfile::update('web.hostinventories.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$hostId = getRequest('hostid', 0);

/*
 * Display
 */
if ($hostId > 0) {
	$data = array();

	// host scripts
	$data['hostScripts'] = API::Script()->getScriptsByHosts(array($hostId));

	// inventory info
	$data['tableTitles'] = getHostInventories();
	$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
	$inventoryFields = array_keys($data['tableTitles']);

	// overview tab
	$data['host'] = API::Host()->get(array(
		'output' => array('hostid', 'host', 'name', 'status', 'maintenance_status', 'description'),
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => $inventoryFields,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'hostids' => $hostId,
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
	elseif ($userType == USER_TYPE_ZABBIX_ADMIN) {
		$rwHost = API::Host()->get(array(
			'output' => array('hostid'),
			'hostids' => $hostId,
			'editable' => true
		));

		$data['rwHost'] = (bool) $rwHost;
	}
	else {
		$data['rwHost'] = false;
	}

	// view generation
	$hostinventoriesView = new CView('inventory.host.view', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}
else {
	$data = array(
		'config' => select_config(),
		'hosts' => array()
	);

	// filter
	$data['pageFilter'] = new CPageFilter(array(
		'groups' => array(
			'real_hosts' => true
		),
		'groupid' => getRequest('groupid', null)
	));

	/*
	 * Filter
	 */
	if (hasRequest('filter_set')) {
		CProfile::update('web.hostinventories.filter_field', getRequest('filter_field', ''), PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_field_value', getRequest('filter_field_value', ''), PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_exact', getRequest('filter_exact', 0), PROFILE_TYPE_INT);

	}
	elseif (hasRequest('filter_rst')) {
		DBStart();
		CProfile::delete('web.hostinventories.filter_field');
		CProfile::delete('web.hostinventories.filter_field_value');
		CProfile::delete('web.hostinventories.filter_exact');
		DBend();
	}

	$data['filterField'] = CProfile::get('web.hostinventories.filter_field', '');
	$data['filterFieldValue'] = CProfile::get('web.hostinventories.filter_field_value', '');
	$data['filterExact'] = CProfile::get('web.hostinventories.filter_exact', 0);

	if ($data['pageFilter']->groupsSelected) {
		// which inventory fields we will need for displaying
		$requiredInventoryFields = array(
			'name',
			'type',
			'os',
			'serialno_a',
			'tag',
			'macaddress_a'
		);

		// checking if correct inventory field is specified for filter
		$possibleInventoryFields = getHostInventories();
		$possibleInventoryFields = zbx_toHash($possibleInventoryFields, 'db_field');
		if (!empty($data['filterField'])
				&& !empty($data['filterFieldValue'])
				&& !isset($possibleInventoryFields[$data['filterField']])) {
			error(_s('Impossible to filter by inventory field "%s", which does not exist.', $data['filterField']));
		}
		else {
			// if we are filtering by field, this field is also required
			if (!empty($data['filterField']) && !empty($data['filterFieldValue'])) {
				$requiredInventoryFields[] = $data['filterField'];
			}

			$options = array(
				'output' => array('hostid', 'name', 'status'),
				'selectInventory' => $requiredInventoryFields,
				'withInventory' => true,
				'selectGroups' => API_OUTPUT_EXTEND,
				'limit' => ($data['config']['search_limit'] + 1)
			);
			if ($data['pageFilter']->groupid > 0) {
				$options['groupids'] = $data['pageFilter']->groupid;
			}

			$data['hosts'] = API::Host()->get($options);

			// copy some inventory fields to the uppers array level for sorting
			// and filter out hosts if we are using filter
			foreach ($data['hosts'] as $num => $host) {
				$data['hosts'][$num]['pr_name'] = $host['inventory']['name'];
				$data['hosts'][$num]['pr_type'] = $host['inventory']['type'];
				$data['hosts'][$num]['pr_os'] = $host['inventory']['os'];
				$data['hosts'][$num]['pr_serialno_a'] = $host['inventory']['serialno_a'];
				$data['hosts'][$num]['pr_tag'] = $host['inventory']['tag'];
				$data['hosts'][$num]['pr_macaddress_a'] = $host['inventory']['macaddress_a'];

				// if we are filtering by inventory field
				if (!empty($data['filterField']) && !empty($data['filterFieldValue'])) {
					// must we filter exactly or using a substring (both are case insensitive)
					$haystack = mb_strtolower($data['hosts'][$num]['inventory'][$data['filterField']]);
					$needle = mb_strtolower($data['filterFieldValue']);

					$match = $data['filterExact'] ? ($haystack === $needle) : (mb_strpos($haystack, $needle) !== false);
					if (!$match) {
						unset($data['hosts'][$num]);
					}
				}
			}

			order_result($data['hosts'], getPageSortField('name'), getPageSortOrder());
		}
	}

	$data['paging'] = getPagingLine($data['hosts']);

	$hostinventoriesView = new CView('inventory.host.list', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
