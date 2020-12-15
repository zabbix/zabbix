<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$filter = new CFilter(new CUrl('items.php'));
$filter_column_1 = new CFormList();
$filter_column_2 = new CFormList();
$filter_column_3 = new CFormList();

// type select
$filter_type_visibility = [];
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay_row');
zbx_subarray_push($filter_type_visibility, -1, 'filter_delay');

$item_types = item_type2str();
unset($item_types[ITEM_TYPE_HTTPTEST]); // httptest items are only for internal zabbix logic

$filter_type = new CComboBox('filter_type', $data['filter_data']['filter_type'], null, [-1 => _('all')]);
$filter_type->addItems($item_types);

foreach (array_keys($item_types) as $type) {
	if ($type != ITEM_TYPE_TRAPPER && $type != ITEM_TYPE_SNMPTRAP) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay_row');
		zbx_subarray_push($filter_type_visibility, $type, 'filter_delay');
	}
	if ($type == ITEM_TYPE_SNMP) {
		zbx_subarray_push($filter_type_visibility, $type, 'filter_snmp_oid_row');
	}
}

zbx_add_post_js("var filterTypeSwitcher = new CViewSwitcher('filter_type', 'change', ".zbx_jsvalue($filter_type_visibility, true).');');

$filter_column_1
	->addRow((new CLabel(_('Host groups'), 'filter_groupid_ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter_data']['groupids'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $filter->getName(),
					'dstfld1' => 'filter_groupids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow((new CLabel(_('Hosts'), 'filter_hostid_ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => 'host_templates',
			'data' => $data['filter_data']['hostids'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => 'host_templates',
					'srcfld1' => 'hostid',
					'dstfrm' => $filter->getName(),
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

$filter_column_2
	->addRow(_('Type'), $filter_type)
	->addRow(_('Type of information'),
		new CComboBox('filter_value_type', $data['filter_data']['filter_value_type'], null, [
			-1 => _('all'),
			ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
			ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
			ITEM_VALUE_TYPE_STR => _('Character'),
			ITEM_VALUE_TYPE_LOG => _('Log'),
			ITEM_VALUE_TYPE_TEXT => _('Text')
		])
	)
	->addRow(_('SNMP OID'),
		(new CTextBox('filter_snmp_oid', $data['filter_data']['filter_snmp_oid'], '', 255))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_snmp_oid_row'
	)
	->addRow(_('History'),
		(new CTextBox('filter_history', $data['filter_data']['filter_history']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Trends'),
		(new CTextBox('filter_trends', $data['filter_data']['filter_trends']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(_('Update interval'),
		(new CTextBox('filter_delay', $data['filter_data']['filter_delay']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'filter_delay_row'
	);

$filter_tags_table = (new CTable())
	->setId('filter-tags')
	->addRow(
		(new CCol(
			(new CRadioButtonList('filter_evaltype', (int) $data['filter_data']['filter_evaltype']))
				->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), TAG_EVAL_TYPE_OR)
				->setModern(true)
				->setId('filter_evaltype')
		))->setColSpan(4)
	);

foreach ($data['filter_data']['filter_tags'] as $i => $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('filter_tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CRadioButtonList('filter_tags['.$i.'][operator]', (int) $tag['operator']))
			->addValue(_('Contains'), TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
			->setModern(true),
		(new CTextBox('filter_tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('filter_tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
				->removeId()
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');
}

$filter_tags_table->addRow(
	(new CCol(
		(new CButton('tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
			->removeId()
	))->setColSpan(3)
);

$filter_column_3
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('State'),
		new CComboBox('filter_state', $data['filter_data']['filter_state'], null, [
			-1 => _('all'),
			ITEM_STATE_NORMAL => itemState(ITEM_STATE_NORMAL),
			ITEM_STATE_NOTSUPPORTED => itemState(ITEM_STATE_NOTSUPPORTED)
		])
	)
	->addRow(_('Status'),
		new CComboBox('filter_status', $data['filter_data']['filter_status'], null, [
			-1 => _('all'),
			ITEM_STATUS_ACTIVE => item_status2str(ITEM_STATUS_ACTIVE),
			ITEM_STATUS_DISABLED => item_status2str(ITEM_STATUS_DISABLED)
		])
	)
	->addRow(_('Triggers'),
		new CComboBox('filter_with_triggers', $data['filter_data']['filter_with_triggers'], null, [
			-1 => _('all'),
			1 => _('With triggers'),
			0 => _('Without triggers')
		])
	)
	->addRow(_('Template'),
		new CComboBox('filter_templated_items', $data['filter_data']['filter_templated_items'], null, [
			-1 => _('all'),
			1 => _('Inherited items'),
			0 => _('Not inherited items')
		])
	)
	->addRow(_('Discovery'),
		new CComboBox('filter_discovery', $data['filter_data']['filter_discovery'], null, [
			-1 => _('all'),
			ZBX_FLAG_DISCOVERY_CREATED => _('Discovered items'),
			ZBX_FLAG_DISCOVERY_NORMAL => _('Regular items')
		])
	);

$filter
	->setProfile('web.items.filter')
	->setActiveTab(CProfile::get('web.items.filter.active', 1))
	->addVar('subfilter_hosts', $data['filter_data']['subfilter_hosts'])
	->addVar('subfilter_types', $data['filter_data']['subfilter_types'])
	->addVar('subfilter_value_types', $data['filter_data']['subfilter_value_types'])
	->addVar('subfilter_status', $data['filter_data']['subfilter_status'])
	->addVar('subfilter_state', $data['filter_data']['subfilter_state'])
	->addVar('subfilter_templated_items', $data['filter_data']['subfilter_templated_items'])
	->addVar('subfilter_with_triggers', $data['filter_data']['subfilter_with_triggers'])
	->addVar('subfilter_discovery', $data['filter_data']['subfilter_discovery'])
	->addVar('subfilter_history', $data['filter_data']['subfilter_history'])
	->addVar('subfilter_trends', $data['filter_data']['subfilter_trends'])
	->addVar('subfilter_interval', $data['filter_data']['subfilter_interval'])
	->addFilterTab(_('Filter'),
		[$filter_column_1, $filter_column_2, $filter_column_3],
		makeItemSubfilter($data['filter_data'], $data['items'])
	);

$filter->show();
