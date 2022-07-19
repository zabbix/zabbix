<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventory overview');
$page['file'] = 'hostinventoriesoverview.php';

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// sort and sortorder
	'sort' =>		[T_ZBX_STR, O_OPT, P_SYS, IN('"host_count","inventory_field"'),		null],
	'sortorder' =>	[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null],
	// filter
	'filter_set' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_rst' =>			[T_ZBX_STR, O_OPT, P_SYS,		null,	null],
	'filter_groups' =>		[T_ZBX_INT, O_OPT, null,		DB_ID,	null],
	'filter_groupby' =>		[T_ZBX_STR, O_OPT, P_SYS,		null,	null]
];
check_fields($fields);

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'host_count'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_DOWN));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

if (hasRequest('filter_set')) {
	CProfile::updateArray('web.hostinventoriesoverview.filter_groups', getRequest('filter_groups', []), PROFILE_TYPE_ID);
	CProfile::update('web.hostinventoriesoverview.filter_groupby', getRequest('filter_groupby', ''), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	CProfile::deleteIdx('web.hostinventoriesoverview.filter_groups');
	CProfile::deleteIdx('web.hostinventoriesoverview.filter_groupby');
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$filter = [
	'groups' => CProfile::getArray('web.hostinventoriesoverview.filter_groups', []),
	'groupby' => CProfile::get('web.hostinventoriesoverview.filter_groupby', '')
];

$ms_groups = [];
$filter_groupids = $filter['groups'] ? getSubGroups($filter['groups'], $ms_groups) : null;

$inventories = [];
foreach (getHostInventories() as $inventory) {
	$inventories[$inventory['db_field']] = $inventory['title'];
}

if (!array_key_exists($filter['groupby'], $inventories)) {
	$filter['groupby'] = '';
}

$grouping_column = ($filter['groupby'] === '') ? _('Field') : $inventories[$filter['groupby']];

$table = (new CTableInfo())->setHeader([
	make_sorting_header($grouping_column, 'inventory_field', $sortField, $sortOrder),
	make_sorting_header(_('Host count'), 'host_count', $sortField, $sortOrder)
]);

// To show a report, we will need a host group and a field to aggregate.
if ($filter['groupby'] !== '') {
	$hosts = API::Host()->get([
		'output' => ['hostid', 'name'],
		'selectInventory' => [$filter['groupby']], // only one field is required
		'groupids' => $filter_groupids,
		'filter' => [
			'inventory_mode' => [HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
		]
	]);

	// aggregating data by chosen field value
	$report = [];
	foreach ($hosts as $host) {
		if ($host['inventory'][$filter['groupby']] !== '') {
			// same names with different letter casing are considered the same
			$lowerValue = mb_strtolower($host['inventory'][$filter['groupby']]);

			if (!isset($report[$lowerValue])) {
				$report[$lowerValue] = [
					'inventory_field' => $host['inventory'][$filter['groupby']],
					'host_count' => 1
				];
			}
			else {
				$report[$lowerValue]['host_count'] += 1;
			}
		}
	}

	order_result($report, $sortField, $sortOrder);

	$allowed_ui_inventory = CWebUser::checkAccess(CRoleHelper::UI_INVENTORY_HOSTS);
	foreach ($report as $rep) {
		$table->addRow([
			zbx_str2links($rep['inventory_field']),
			$allowed_ui_inventory
				? new CLink($rep['host_count'],
					(new CUrl('hostinventories.php'))
						->setArgument('filter_set', '1')
						->setArgument('filter_exact', '1')
						->setArgument('filter_groups', array_keys($ms_groups))
						->setArgument('filter_field', $filter['groupby'])
						->setArgument('filter_field_value', $rep['inventory_field'])
				)
				: $rep['host_count']
		]);
	}
}

$select_groupby = (new CSelect('filter_groupby'))
	->setValue($filter['groupby'])
	->setFocusableElementId('groupby')
	->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
	->addOption(new CSelectOption('', _('not selected')))
	->addOptions(CSelect::createOptionsFromArray($inventories));

(new CWidget())
	->setTitle(_('Host inventory overview'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::INVENTORY_HOST_OVERVIEW))
	->addItem(
		(new CFilter())
			->setResetUrl(new CUrl('hostinventoriesoverview.php'))
			->setProfile('web.hostinventoriesoverview.filter')
			->setActiveTab(CProfile::get('web.hostinventoriesoverview.filter.active', 1))
			->addFilterTab(_('Filter'), [
				(new CFormList())
					->addRow(
						(new CLabel(_('Host groups'), 'filter_groups__ms')),
						(new CMultiSelect([
							'name' => 'filter_groups[]',
							'object_name' => 'hostGroup',
							'data' => $ms_groups,
							'popup' => [
								'parameters' => [
									'srctbl' => 'host_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_groups_',
									'with_hosts' => true,
									'enrich_parent_groups' => true
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
					->addRow(new CLabel(_('Grouping by'), $select_groupby->getFocusableElementId()), $select_groupby)
			])
	)
	->addItem($table)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
