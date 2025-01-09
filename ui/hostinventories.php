<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventory');
$page['file'] = 'hostinventories.php';
$page['scripts'] = ['multilineinput.js', 'items.js'];

$hostId = getRequest('hostid', 0);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_field' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_field_value' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_exact' =>		[T_ZBX_INT, O_OPT, null,	'IN(0,1)',	null],
	'filter_groups' =>		[T_ZBX_INT, O_OPT, P_ONLY_ARRAY,	DB_ID,	null],
	// actions
	'cancel' =>				[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS,
								IN('"name","pr_macaddress_a","pr_name","pr_os","pr_serialno_a","pr_tag","pr_type"'),
								null
							],
	'sortorder' =>			[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
	access_deny();
}

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

/*
 * Display
 */
if ($hostId > 0) {
	$data = [
		'allowed_ui_hosts' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_HOSTS),
		'allowed_ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
		'allowed_ui_latest_data' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA),
		'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
	];

	// inventory info
	$data['tableTitles'] = getHostInventories();
	$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
	$inventoryFields = array_keys($data['tableTitles']);

	// overview tab
	$data['host'] = API::Host()->get([
		'output' => ['hostid', 'host', 'name', 'maintenance_status', 'maintenanceid', 'maintenance_type', 'description'],
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectInventory' => $inventoryFields,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'hostids' => $hostId,
		'preservekeys' => true
	]);
	$data['host'] = reset($data['host']);
	unset($data['host']['inventory']['hostid']);

	// resolve macros
	$data['host']['interfaces'] = CMacrosResolverHelper::resolveHostInterfaces($data['host']['interfaces']);

	if ($data['host']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
		$data['maintenances'] = API::Maintenance()->get([
			'maintenanceids' => [$data['host']['maintenanceid']],
			'output' => ['name', 'description'],
			'preservekeys' => true
		]);
	}

	// get permissions
	$userType = CWebUser::getType();
	if ($userType == USER_TYPE_SUPER_ADMIN) {
		$data['rwHost'] = true;
	}
	elseif ($userType == USER_TYPE_ZABBIX_ADMIN) {
		$rwHost = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => $hostId,
			'editable' => true
		]);

		$data['rwHost'] = (bool) $rwHost;
	}
	else {
		$data['rwHost'] = false;
	}

	// view generation
	echo (new CView('inventory.host.view', $data))->getOutput();
}
else {
	$data = [
		'hosts' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'profileIdx' => 'web.hostinventories.filter',
		'active_tab' => CProfile::get('web.hostinventories.filter.active', 1)
	];

	/*
	 * Filter
	 */
	if (hasRequest('filter_set')) {
		CProfile::update('web.hostinventories.filter_field', getRequest('filter_field', ''), PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_field_value', getRequest('filter_field_value', ''), PROFILE_TYPE_STR);
		CProfile::update('web.hostinventories.filter_exact', getRequest('filter_exact', 0), PROFILE_TYPE_INT);
		CProfile::updateArray('web.hostinventories.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
	}
	elseif (hasRequest('filter_rst')) {
		DBStart();
		CProfile::delete('web.hostinventories.filter_field');
		CProfile::delete('web.hostinventories.filter_field_value');
		CProfile::delete('web.hostinventories.filter_exact');
		CProfile::deleteIdx('web.hostinventories.filter_groups');
		DBend();
	}

	$data['filter'] = [
		'field' => CProfile::get('web.hostinventories.filter_field', ''),
		'fieldValue' => CProfile::get('web.hostinventories.filter_field_value', ''),
		'exact' => CProfile::get('web.hostinventories.filter_exact', 0),
		'groups' => CProfile::getArray('web.hostinventories.filter_groups', [])
	];

	// Select filter host groups.
	$data['filter']['groups'] = $data['filter']['groups']
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $data['filter']['groups'],
			'preservekeys' => true
		]), ['groupid' => 'id'])
		: [];

	$filter_groupids = $data['filter']['groups'] ? array_keys($data['filter']['groups']) : null;
	if ($filter_groupids) {
		$filter_groupids = getSubGroups($filter_groupids);
	}

	$data['host_inventories'] = zbx_toHash(getHostInventories(true), 'db_field');

	if ($data['filter']['field'] === '') {
		$data['filter']['field'] = key($data['host_inventories']);
	}

	// Checking if correct inventory field is specified for filter.
	if ($data['filter']['fieldValue'] !== ''
			&& !array_key_exists($data['filter']['field'], $data['host_inventories'])) {
		error(_s('Impossible to filter by inventory field "%1$s", which does not exist.', $data['filter']['field']));
		$filter_set = false;
	}
	else {
		$filter_set = true;
	}

	/*
	 * Select data
	 */
	if ($filter_set) {
		$options = [
			'output' => ['hostid', 'name', 'status'],
			'selectInventory' => ['name', 'type', 'os', 'serialno_a', 'tag', 'macaddress_a', $data['filter']['field']],
			'selectHostGroups' => ['name'],
			'groupids' => $filter_groupids,
			'filter' => ['inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]]
		];

		if ($data['filter']['fieldValue'] !== '') {
			$options['searchInventory'] = [
				$data['filter']['field'] => [$data['filter']['fieldValue']]
			];
		}

		$data['hosts'] = API::Host()->get($options);

		// filter exact matches
		if ($data['filter']['fieldValue'] !== '' && $data['filter']['exact'] != 0) {
			$needle = mb_strtolower($data['filter']['fieldValue']);

			foreach ($data['hosts'] as $num => $host) {
				$haystack = mb_strtolower($data['hosts'][$num]['inventory'][$data['filter']['field']]);

				if ($haystack !== $needle) {
					unset($data['hosts'][$num]);
				}
			}
		}

		$sort_fields = [
			'pr_name' => 'name',
			'pr_type' => 'type',
			'pr_os' => 'os',
			'pr_serialno_a' => 'serialno_a',
			'pr_tag' => 'tag',
			'pr_macaddress_a' => 'macaddress_a'
		];

		if (array_key_exists($sortField, $sort_fields)) {
			// copying an inventory field into the upper array level for sorting
			foreach ($data['hosts'] as &$host) {
				$host[$sortField] = $host['inventory'][$sort_fields[$sortField]];
			}
			unset($host);
		}

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		order_result($data['hosts'], $sortField, $sortOrder);

		if ($sortOrder == ZBX_SORT_UP) {
			$data['hosts'] = array_slice($data['hosts'], 0, $limit);
		}
		else {
			$data['hosts'] = array_slice($data['hosts'], -$limit, $limit);
		}

		order_result($data['hosts'], $sortField, $sortOrder);
	}

	// pager
	if (hasRequest('page')) {
		$page_num = getRequest('page');
	}
	elseif (isRequestMethod('get') && !hasRequest('cancel')) {
		$page_num = 1;
	}
	else {
		$page_num = CPagerHelper::loadPage($page['file']);
	}

	CPagerHelper::savePage($page['file'], $page_num);

	$data['paging'] = CPagerHelper::paginate($page_num, $data['hosts'], $sortOrder, new CUrl('hostinventories.php'));

	echo (new CView('inventory.host.list', $data))->getOutput();
}

require_once dirname(__FILE__).'/include/page_footer.php';
