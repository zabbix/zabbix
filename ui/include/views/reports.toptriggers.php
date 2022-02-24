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


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('reports.toptriggers.js.php');

$filterForm = (new CFilter())->setResetUrl(new CUrl('toptriggers.php'));

$severities = [];
foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
	$severities[] = [
		'name' => CSeverityHelper::getName($severity),
		'value' => $severity
	];
}

$filter_column = (new CFormList())
	->addRow(new CLabel(_('Host groups'), 'groupids__ms'),
		(new CMultiSelect([
			'name' => 'groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['multiSelectHostGroupData'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'groupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Hosts'), 'hostids__ms'),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => $data['multiSelectHostData'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'groupids_'
				],
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Severity')),
		(new CCheckBoxList('severities'))
			->setOptions(CSeverityHelper::getSeverities())
			->setChecked($data['filter']['severities'])
			->setColumns(3)
			->setVertical(true)
	);

$filterForm
	->setProfile($data['filter']['timeline']['profileIdx'])
	->setActiveTab($data['filter']['active_tab'])
	->addTimeSelector($data['filter']['timeline']['from'], $data['filter']['timeline']['to'], true, ZBX_DATE_TIME)
	->addFilterTab(_('Filter'), [$filter_column]);

// table
$table = (new CTableInfo())->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Number of status changes')]);

foreach ($data['triggers'] as $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];

	$hostName = (new CLinkAction($trigger['hosts'][0]['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));
	if ($data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) {
		$hostName->addClass(ZBX_STYLE_RED);
	}

	$triggerDescription = (new CLinkAction($trigger['description']))
		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], 0));

	$table->addRow([
		$hostName,
		$triggerDescription,
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		$trigger['cnt_event']
	]);
}

$obj_data = [
	'id' => 'timeline_1',
	'domid' => 'toptriggers',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0
];
zbx_add_post_js('timeControl.addObject("toptriggers", '.zbx_jsvalue($data['filter']).', '.zbx_jsvalue($obj_data).');');
zbx_add_post_js('timeControl.processObjects();');

(new CWidget())
	->setTitle(_('100 busiest triggers'))
	->addItem($filterForm)
	->addItem($table)
	->show();
