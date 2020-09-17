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

$this->addJsFile('multiselect.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.host.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

$filter_tags = $data['filter']['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

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
foreach ($filter_tags as $tag) {
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

$severities = [];
foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
	$severities[] = [
		'name' => getSeverityName($severity, $data['config']),
		'value' => $severity
	];
}

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$widget->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'host.view')))
		->setProfile('web.hostsmon.filter')
		->setActiveTab($data['active_tab'])
		->addFormItem((new CVar('action', 'host.view'))->removeId())
		->addFilterTab(_('Filter'), [
			(new CFormList())
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
				),
			(new CFormList())
				->addRow(_('Status'),
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), HOST_STATUS_MONITORED)
						->addValue(_('Disabled'), HOST_STATUS_NOT_MONITORED)
						->setModern(true)
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
				])
		])
	);
}

$widget->addItem(new CPartial('monitoring.host.view.html', array_intersect_key($data, array_flip([
	'filter', 'sort', 'sortorder', 'view_curl', 'hosts', 'config', 'maintenances', 'paging'
]))));

$widget->show();

(new CScriptTag('host_page.start();'))
	->setOnDocumentReady()
	->show();
