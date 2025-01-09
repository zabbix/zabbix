<?php declare(strict_types = 0);
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


/**
 * @var CPartial $this
 * @var array    $data
 */

$filter = (new CFilter())->setResetUrl((new CUrl())->setArgument('action', $data['action']));
$filter_columns = [new CFormList(), new CFormList(), new CFormList()];

// First column.
$filter_columns[0]
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
				] + ($data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true])
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
					'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
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
	->addRow(
		new CLabel(_('Name'), 'filter_name'),
		(new CTextBox('filter_name', $data['filter_data']['filter_name']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(
		new CLabel(_('Key'), 'filter_key'),
		(new CTextBox('filter_key', $data['filter_data']['filter_key']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	);

if ($data['filter_data']['ms_hosts']) {
	$filter_columns[0]->addRow(
		new CLabel(_('Value mapping'), 'filter_valuemapids__ms'),
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
	->addOption(new CSelectOption(-1, _('All')))
	->addOptions(CSelect::createOptionsFromArray($data['types']));

$info_type_select = (new CSelect('filter_value_type'))
	->setFocusableElementId('label-filter-value-type')
	->setValue($data['filter_data']['filter_value_type'])
	->addOptions(CSelect::createOptionsFromArray([
		-1 => _('All'),
		ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
		ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
		ITEM_VALUE_TYPE_STR => _('Character'),
		ITEM_VALUE_TYPE_LOG => _('Log'),
		ITEM_VALUE_TYPE_TEXT => _('Text'),
		ITEM_VALUE_TYPE_BINARY => _('Binary')
	]));

$filter_columns[1]
	->addRow(
		new CLabel(_('Type'), $type_select->getFocusableElementId()),
		$type_select
	)
	->addRow(
		new CLabel(_('Type of information'), $info_type_select->getFocusableElementId()),
		$info_type_select
	)
	->addRow(
		(new CLabel(_('SNMP OID'), 'filter_snmp_oid'))->setId('js-filter-snmp-oid-label'),
		(new CTextBox('filter_snmp_oid', $data['filter_data']['filter_snmp_oid'], '', 255))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'js-filter-snmp-oid-field'
	)
	->addRow(
		new CLabel(_('History'), 'filter_history'),
		(new CTextBox('filter_history', $data['filter_data']['filter_history']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(
		new CLabel(_('Trends'), 'filter_trends'),
		(new CTextBox('filter_trends', $data['filter_data']['filter_trends']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
	->addRow(
		(new CLabel(_('Update interval'), 'filter_delay'))->setId('js-filter-delay-label'),
		(new CTextBox('filter_delay', $data['filter_data']['filter_delay']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		'js-filter-delay-field'
	);

// Third column.
$tags = $data['filter_data']['filter_tags'];

if (!$tags) {
	$tags[] = ['tag' => '', 'operator' => TAG_OPERATOR_LIKE, 'value' => ''];
}

$filter_columns[2]->addRow(
	new CLabel(_('Tags')),
	CTagFilterFieldHelper::getTagFilterField([
		'evaltype' => $data['filter_data']['filter_evaltype'],
		'tags' => $tags
	])
);

if ($data['context'] === 'host') {
	$filter_columns[2]->addRow(
		new CLabel(_('State'), 'filter_state'),
		(new CRadioButtonList('filter_state', (int) $data['filter_data']['filter_state']))
			->addValue(_('All'), -1)
			->addValue(_('Normal'), ITEM_STATE_NORMAL)
			->addValue(_('Not supported'), ITEM_STATE_NOTSUPPORTED)
			->setModern()
	);
}

$filter_columns[2]
	->addRow(
		new CLabel(_('Status'), 'filter_status'),
		(new CRadioButtonList('filter_status', (int) $data['filter_data']['filter_status']))
			->addValue(_('All'), -1)
			->addValue(_('Enabled'), ITEM_STATUS_ACTIVE)
			->addValue(_('Disabled'), ITEM_STATUS_DISABLED)
			->setModern()
			->setEnabled($data['filter_data']['filter_state'] == -1)
	)
	->addRow(
		new CLabel(_('Triggers'), 'filter_with_triggers'),
		(new CRadioButtonList('filter_with_triggers', (int) $data['filter_data']['filter_with_triggers']))
			->addValue(_('All'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern()
	)
	->addRow(
		new CLabel(_('Inherited'), 'filter_inherited'),
		(new CRadioButtonList('filter_inherited', (int) $data['filter_data']['filter_inherited']))
			->addValue(_('All'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern()
	);

if ($data['context'] === 'host') {
	$filter_columns[2]->addRow(
		new CLabel(_('Discovered'), 'filter_discovered'),
		(new CRadioButtonList('filter_discovered', (int) $data['filter_data']['filter_discovered']))
			->addValue(_('All'), -1)
			->addValue(_('Yes'), ZBX_FLAG_DISCOVERY_CREATED)
			->addValue(_('No'), ZBX_FLAG_DISCOVERY_NORMAL)
			->setModern()
	);
}

$subfilters_table = (new CTableInfo())
	->addRow([
		new CTag('h4', true, [
			_('Subfilter'), ' ', (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
		])
	], ZBX_STYLE_HOVER_NOBG);

if ($data['filtered_count'] > 1) {
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
				($count > 0 || $is_selected)
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
	->addFilterTab(_('Filter'), $filter_columns, $subfilters_table);

$filter->show();
