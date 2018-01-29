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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Host inventory overview');
$page['file'] = 'hostinventoriesoverview.php';

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'groupid' =>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null],
	'groupby' =>	[T_ZBX_STR, O_OPT,	P_SYS,	null,	null],
	// sort and sortorder
	'sort' =>		[T_ZBX_STR, O_OPT, P_SYS, IN('"host_count","inventory_field"'),		null],
	'sortorder' =>	[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'host_count'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_DOWN));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

/*
 * Permissions
 */
if (getRequest('groupid') && !isReadableHostGroups([getRequest('groupid')])) {
	access_deny();
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$options = [
	'groups' => [
		'real_hosts' => 1,
	],
	'groupid' => getRequest('groupid'),
];
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['groupby'] = getRequest('groupby', '');
$groupFieldTitle = '';

$hostinvent_wdgt = (new CWidget())->setTitle(_('Host inventory overview'));

// getting inventory fields to make a drop down
$inventoryFields = getHostInventories(true); // 'true' means list should be ordered by title
$inventoryFieldsComboBox = new CComboBox('groupby', $_REQUEST['groupby'], 'submit()');
$inventoryFieldsComboBox->addItem('', _('not selected'));
foreach($inventoryFields as $inventoryField){
	$inventoryFieldsComboBox->addItem(
		$inventoryField['db_field'],
		$inventoryField['title'],
		$_REQUEST['groupby'] === $inventoryField['db_field'] ? 'yes' : null // selected?
	);
	if($_REQUEST['groupby'] === $inventoryField['db_field']){
		$groupFieldTitle = $inventoryField['title'];
	}
}

$controls = (new CList())
	->addItem([
		new CLabel(_('Group'), 'groupid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$pageFilter->getGroupsCB()
	])
	->addItem([
		new CLabel(_('Grouping by'), 'groupby'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$inventoryFieldsComboBox
	]);

$hostinvent_wdgt->setControls(
	(new CForm('get'))->addItem($controls)
);

$table = (new CTableInfo())
	->setHeader(
		[
			make_sorting_header($groupFieldTitle === '' ? _('Field') : $groupFieldTitle, 'inventory_field',
				$sortField, $sortOrder
			),
			make_sorting_header(_('Host count'), 'host_count', $sortField, $sortOrder),
		]
	);

// to show a report, we will need a host group and a field to aggregate
if($pageFilter->groupsSelected && $groupFieldTitle !== ''){

	$options = [
		'output' => ['hostid', 'name'],
		'selectInventory' => [$_REQUEST['groupby']], // only one field is required
		'withInventory' => true,
		'groupids' => $pageFilter->groupids
	];

	$hosts = API::Host()->get($options);

	// aggregating data by chosen field value
	$report = [];
	foreach($hosts as $host) {
		if ($host['inventory'][$_REQUEST['groupby']] !== '') {
			// same names with different letter casing are considered the same
			$lowerValue = mb_strtolower($host['inventory'][$_REQUEST['groupby']]);

			if (!isset($report[$lowerValue])) {
				$report[$lowerValue] = [
					'inventory_field' => $host['inventory'][$_REQUEST['groupby']],
					'host_count' => 1
				];
			}
			else {
				$report[$lowerValue]['host_count'] += 1;
			}
		}
	}

	order_result($report, $sortField, $sortOrder);

	foreach ($report as $rep) {
		$table->addRow([
			zbx_str2links($rep['inventory_field']),
			new CLink($rep['host_count'],
				'hostinventories.php?filter_field='.$_REQUEST['groupby'].
				'&filter_field_value='.urlencode($rep['inventory_field']).
				'&filter_set=1&filter_exact=1'.url_param('groupid')
			)
		]);
	}
}

$hostinvent_wdgt->addItem($table);
$hostinvent_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
