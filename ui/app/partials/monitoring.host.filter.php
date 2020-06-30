<?php declare(strict_types = 1);

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


$filter_tags_table = (new CTable())
	->setId('filter-tags')
	->addRow(
		(new CCol(
			(new CRadioButtonList('filter_evaltype', (int) $data['filter']['evaltype']))
				->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
				->addValue(_('Or'), TAG_EVAL_TYPE_OR)
				->setModern(true)
		))->setColSpan(4)
);

$i = 0;
foreach ($data['filter']['tags'] as $tag) {
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
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('filter_tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$columns = [];
$columns[] = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['multiselect_hostgroup_data'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('IP'),
		(new CTextBox('filter_ip', $data['filter']['ip']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('DNS'),
		(new CTextBox('filter_dns', $data['filter']['dns']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Port'),
		(new CTextBox('filter_port', $data['filter']['port']))
			->setWidth(ZBX_TEXTAREA_INTERFACE_PORT_WIDTH)
	)
	->addRow(_('Severity'),
		(new CSeverityCheckBoxList('filter_severities'))->setChecked($data['filter']['severities'])
	);

$columns[] = (new CFormList())
	->addRow(
		_('Status'),
		(new CHorList())
			->addItem((new CRadioButtonList('filter_status', (int) $data['filter']['status']))
				->addValue(_('Any'), -1)
				->addValue(_('Enabled'), HOST_STATUS_MONITORED)
				->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED)
				->setModern(true)
			)
	)
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Show hosts in maintenance'), [
		(new CCheckBox('filter_maintenance_status'))
			->setChecked($data['filter']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
			->setUncheckedValue(HOST_MAINTENANCE_STATUS_OFF),
		(new CDiv([
			(new CLabel(_('Show suppressed problems'), 'filter_show_suppressed'))
				->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
			(new CCheckBox('filter_show_suppressed'))
				->setChecked($data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
				->setEnabled($data['filter']['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON),
		]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
	]);

$footer[] = (new CDiv())
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem(
		(new CSubmitButton(_('Save as'), 'save_as', 1))
			->addClass(ZBX_STYLE_BTN_ALT)
	)
	->addItem(
		(new CSubmitButton(_('Update'), 'filter_set', 1))
	)
	->addItem(
		(new CSubmitButton(_('Apply'), 'filter_apply', 1))
			->addClass(ZBX_STYLE_BTN_ALT)
	)
	->addItem(
		(new CRedirectButton(_('Reset'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'host.view')
				->setArgument('filter_rst', 1)
				->getUrl()
		))
			->addClass(ZBX_STYLE_BTN_ALT)
	);

(new CFilter($data['view_curl']))
	->hideFilterButtons()
	->setProfile('web.hostsmon.filter')
	->setActiveTab($data['active_tab'])
	->addFormItem((new CVar('action', 'host.view'))->removeId())
	->addFilterTab(_('Filter'), $columns, $footer)
	->addTimeSelector('now-1h', 'now')
	->show();
