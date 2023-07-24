<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$filter = (new CFilter())->setResetUrl((new CUrl())->setArgument('action', $data['action']));
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

$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

// First column.
$filter_column_1
	->addRow(
		new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'), 'filter_groupids__ms'),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
			'data' => $data['filter_data']['ms_hostgroups'],
			'popup' => [
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(
		new CLabel($data['context'] === 'host' ? _('Hosts') : _('Templates'), 'filter_hostids__ms'),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
			'data' => $data['filter_data']['ms_hosts'],
			'popup' => [
				'filter_preselect' => [
					'id' => 'filter_groupids_',
					'submit_as' => 'groupid'
				],
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
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

if ($data['filter_data']['ms_hosts']) {
	$filter_column_1->addRow(new CLabel(_('Value mapping'), 'filter_valuemapids__ms'),
		(new CMultiSelect([
			'name' => 'filter_valuemapids[]',
			'object_name' => 'valuemap_names',
			'data' => $data['filter_data']['ms_valuemaps'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'valuemap_names',
					'srcfld1' => 'valuemapid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_valuemapids_',
					'hostids' => array_column($data['filter_data']['ms_hosts'], 'id'),
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
		ITEM_VALUE_TYPE_TEXT => _('Text'),
		ITEM_VALUE_TYPE_BINARY => _('Binary')
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
$tags = $data['filter_data']['filter_tags'];

if (!$tags) {
	$tags[] = ['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''];
}

$filter_column_3->addRow(_('Tags'),
	CTagFilterFieldHelper::getTagFilterField([
		'evaltype' => $data['filter_data']['filter_evaltype'],
		'tags' => $tags
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

$subfilters_table = (new CTableInfo())
	->addRow([
		new CTag('h4', true, [
			_('Subfilter'), ' ', (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
		])
	], ZBX_STYLE_HOVER_NOBG);

foreach ($data['subfilter'] as $subfilter) {
	$cell = [];

	if (count($subfilter['values']) < 2) {
		continue;
	}

	foreach ($subfilter['values'] as $value => $count) {
		$is_selected = array_key_exists($value, $subfilter['selected']);
		$prefix = ($subfilter['selected'] && !$is_selected && $count > 0) ? '+' : '';
		$value_label = $value;

		if (array_key_exists('labels', $subfilter)) {
			$value_label = $subfilter['labels'][$value];
		}

		$name = $subfilter['key'].'[]';
		$cell[] = (new CSpan([
			$count > 0
				? (new CLinkAction($value_label))
					->setAttribute('data-name', $name)
					->setAttribute('data-value', $value)
				: (new CSpan($value_label))->addClass(ZBX_STYLE_GREY),
			$is_selected ? new CInput('hidden', $name, $value) : null,
			' ',
			new CSup($prefix.$count)
		]))
			->addClass(ZBX_STYLE_SUBFILTER)
			->addClass($is_selected ? ZBX_STYLE_SUBFILTER_ENABLED : null);
	}

	if ($cell) {
		array_unshift($cell, new CTag('h3', true, $subfilter['label']));
		$subfilters_table->addRow([$cell]);
	}
}

$filter
	->setProfile($data['filter_data']['filter_profile'])
	->setActiveTab($data['filter_data']['filter_tab'])
	->setResetUrl(
		(new CUrl('zabbix.php'))
			->setArgument('action', $data['action'])
			->setArgument('context', $data['context'])
	)
	->addVar('action', $data['action'], uniqid('item_'))
	->addVar('context', $data['context'], uniqid('item_'))
	->addFilterTab(_('Filter'),
		[$filter_column_1, $filter_column_2, $filter_column_3],
		$subfilters_table
	);

$filter->show();
