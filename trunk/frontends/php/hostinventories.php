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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventory');
$page['file'] = 'hostinventories.php';

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>			[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'hostid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_field' =>		[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_field_value' =>	[T_ZBX_STR, O_OPT, null,	null,		null],
	'filter_exact' =>		[T_ZBX_INT, O_OPT, null,	'IN(0,1)',	null],
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
if (getRequest('groupid') && !isReadableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
	access_deny();
}

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

$hostId = getRequest('hostid', 0);

/*
 * Display
 */
if ($hostId > 0) {
	$data = [];

	// host scripts
	$data['hostScripts'] = API::Script()->getScriptsByHosts([$hostId]);

	// inventory info
	$data['tableTitles'] = getHostInventories();
	$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
	$inventoryFields = array_keys($data['tableTitles']);

	// overview tab
	$data['host'] = API::Host()->get([
		'output' => ['hostid', 'host', 'name', 'status', 'maintenance_status', 'maintenanceid', 'maintenance_type', 'description'],
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
	$hostinventoriesView = new CView('inventory.host.view', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}
else {
	$data = [
		'config' => select_config(),
		'hosts' => [],
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// filter
	$data['pageFilter'] = new CPageFilter([
		'groups' => [
			'real_hosts' => true
		],
		'groupid' => getRequest('groupid')
	]);

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
		$requiredInventoryFields = [
			'name',
			'type',
			'os',
			'serialno_a',
			'tag',
			'macaddress_a'
		];

		// checking if correct inventory field is specified for filter
		$possibleInventoryFields = getHostInventories();
		$possibleInventoryFields = zbx_toHash($possibleInventoryFields, 'db_field');
		if ($data['filterField'] !== '' && $data['filterFieldValue'] !== ''
				&& !isset($possibleInventoryFields[$data['filterField']])) {
			error(_s('Impossible to filter by inventory field "%s", which does not exist.', $data['filterField']));
		}
		else {
			// if we are filtering by field, this field is also required
			if ($data['filterField'] !== '' && $data['filterFieldValue'] !== '') {
				$requiredInventoryFields[] = $data['filterField'];
			}

			$options = [
				'output' => ['hostid', 'name', 'status'],
				'selectInventory' => $requiredInventoryFields,
				'withInventory' => true,
				'selectGroups' => API_OUTPUT_EXTEND,
				'groupids' => $data['pageFilter']->groupids
			];

			if ($data['filterField'] !== '' && $data['filterFieldValue'] !== '') {
				$options['searchInventory'] = [
					$data['filterField'] => [$data['filterFieldValue']]
				];
			}

			$data['hosts'] = API::Host()->get($options);

			// filter exact matches
			if ($data['filterField'] !== '' && $data['filterFieldValue'] !== '' && $data['filterExact'] != 0) {
				$needle = mb_strtolower($data['filterFieldValue']);

				foreach ($data['hosts'] as $num => $host) {
					$haystack = mb_strtolower($data['hosts'][$num]['inventory'][$data['filterField']]);

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

			$limit = $data['config']['search_limit'] + 1;

			order_result($data['hosts'], $sortField, $sortOrder);

			if ($sortOrder == ZBX_SORT_UP) {
				$data['hosts'] = array_slice($data['hosts'], 0, $limit);
			}
			else {
				$data['hosts'] = array_slice($data['hosts'], -$limit, $limit);
			}

			order_result($data['hosts'], $sortField, $sortOrder);
		}
	}

	$url = (new CUrl('hostinventories.php'))
		->setArgument('groupid', $data['pageFilter']->groupid);

	$data['paging'] = getPagingLine($data['hosts'], $sortOrder, $url);

	$hostinventoriesView = new CView('inventory.host.list', $data);
	$hostinventoriesView->render();
	$hostinventoriesView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
