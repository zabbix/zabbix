<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 */

$filter = (new CFilter())->setResetUrl((new CUrl('items.php'))->setArgument('context', $data['context']));
$filter_column_1 = new CFormList();
$filter_column_2 = new CFormList();
$filter_column_3 = new CFormList();

// Type select.
$filter_type_visibility = [];
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay_row');
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay');

$item_types = item_type2str();
unset($item_types[ITEM_TYPE_HTTPTEST]); // Httptest items are only for internal Zabbix logic.

foreach (array_keys($item_types) as $type) {
	if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay_row');
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay');
	}
	if ($type == ITEM_TYPE_SNMP) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_snmp_oid_row');
	}
}

zbx_add_post_js("var filterTypeSwitcher".
	" = new CViewSwitcher('filter_type', 'change', ".json_encode($filter_type_visibility).");"
);

// First column.
$filter_column_1
	->addRow((new CLabel(_('Host groups'), 'filter_groupid_ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter_data']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow((new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostid_ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => ($data['context'] === 'host') ? 'hosts' : 'templates',
			'data' => $data['filter_data']['hosts'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => ($data['context'] === 'host') ? 'hosts' : 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Name'),
		(new CTextBox('filter_name', $data['filter_data']['filter_name']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Key'),
		(new CTextBox('filter_key', $data['filter_data']['filter_key']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

if ($data['filter_data']['hosts']) {
	$filter_column_1->addRow(_('Value mapping'),
		(new CMultiSelect([
			'name' => 'filter_valuemapids[]',
			'object_name' => 'valuemap_names',
			'data' => array_values(array_column($data['filter_data']['filter_valuemapids'], null, 'name')),
			'popup' => [
				'parameters' => [
					'srctbl' => 'valuemap_names',
					'srcfld1' => 'valuemapid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_valuemapids_',
					'hostids' => array_column($data['filter_data']['hosts'], 'id'),
					'with_inherited' => true,
					'context' => $data['context']
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);
}
else {
	foreach ($data['filter_data']['filter_valuemapids'] as $filter_valuemapid) {
		$filter->addVar('filter_valuemapids[]', $filter_valuemapid);
	}
}

// Second column.
$type_select = (new CSelect('filter_type'))
	->setId('filter_type')
	->setValue((int) $data['filter_data']['filter_type'])
	->setFocusableElementId('label-filter-type')
	->addOption(new CSelectOption(-1, _('all')))
	->addOptions(CSelect::createOptionsFromArray($item_types));

$info_type_select = (new CSelect('filter_value_type'))
	->setFocusableElementId('label-filter-value-type')
	->setValue($data['filter_data']['filter_value_type'])
	->addOptions(CSelect::createOptionsFromArray([
		-1 => _('all'),
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text')
	]));

$filter_column_2
	->addRow(new CLabel(_('Type'), $type_select->getFocusableElementId()), $type_select)
	->addRow(new CLabel(_('Type of information'), $info_type_select->getFocusableElementId()), $info_type_select)
	->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $data['filter_data']['filter_snmp_oid'], '', 255))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	)
	->addRow(_('History'),
		(new CTextBox('filter_history', $data['filter_data']['filter_history']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Trends'),
		(new CTextBox('filter_trends', $data['filter_data']['filter_trends']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Update interval'),
		(new CTextBox('filter_delay', $data['filter_data']['filter_delay']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_delay_row'
	);

// Third column.
$filter_column_3->addRow(_('Tags'),
	CTagFilterFieldHelper::getTagFilterField([
		'evaltype' => $data['filter_data']['filter_evaltype'],
		'tags' => $data['filter_data']['filter_tags']
	])
);

if ($data['context'] === 'host') {
	$filter_column_3->addRow(_('State'),
		(new CRadioButtonList('filter_state', (int) $data['filter_data']['filter_state']))
			->addValue(_('all'), -1)
			->addValue(_('Normal'), ITEM_STATE_NORMAL)
			->addValue(_('Not supported'), ITEM_STATE_NOTSUPPORTED)
			->setModern(true)
	);
}

$filter_column_3
	->addRow(_('Status'),
		(new CRadioButtonList('filter_status', (int) $data['filter_data']['filter_status']))
			->addValue(_('all'), -1)
			->addValue(_('Enabled'), ITEM_STATUS_ACTIVE)
			->addValue(_('Disabled'), ITEM_STATUS_DISABLED)
			->setModern(true)
	)
	->addRow(_('Triggers'),
		(new CRadioButtonList('filter_with_triggers', (int) $data['filter_data']['filter_with_triggers']))
			->addValue(_('all'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern(true)
	)
	->addRow(_('Inherited'),
		(new CRadioButtonList('filter_inherited', (int) $data['filter_data']['filter_inherited']))
			->addValue(_('all'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern(true)
	);

if ($data['context'] === 'host') {
	$filter_column_3->addRow(_('Discovered'),
		(new CRadioButtonList('filter_discovered', (int) $data['filter_data']['filter_discovered']))
			->addValue(_('all'), -1)
			->addValue(_('Yes'), ZBX_FLAG_DISCOVERY_CREATED)
			->addValue(_('No'), ZBX_FLAG_DISCOVERY_NORMAL)
			->setModern(true)
	);
}

$filter
	->setProfile('web.items.filter')
	->setActiveTab(CProfile::get('web.items.filter.active', 1))
	->addVar('context', $data['context'])
	->addVar('subfilter_hosts', $data['filter_data']['subfilter_hosts'])
	->addVar('subfilter_types', $data['filter_data']['subfilter_types'])
	->addVar('subfilter_value_types', $data['filter_data']['subfilter_value_types'])
	->addVar('subfilter_status', $data['filter_data']['subfilter_status'])
	->addVar('subfilter_inherited', $data['filter_data']['subfilter_inherited'])
	->addVar('subfilter_with_triggers', $data['filter_data']['subfilter_with_triggers'])
	->addVar('subfilter_history', $data['filter_data']['subfilter_history'])
	->addVar('subfilter_trends', $data['filter_data']['subfilter_trends'])
	->addVar('subfilter_interval', $data['filter_data']['subfilter_interval'])
	->addVar('subfilter_tags', $data['filter_data']['subfilter_tags'])
	->addFilterTab(_('Filter'),
		[$filter_column_1, $filter_column_2, $filter_column_3],
		$data['subfilter']
	);

if ($data['context'] === 'host') {
	$filter
		->addVar('subfilter_state', $data['filter_data']['subfilter_state'])
		->addVar('subfilter_discovered', $data['filter_data']['subfilter_discovered']);
}

$filter->show();
