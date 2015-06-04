<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


require_once dirname(__FILE__).'/js/reports.toptriggers.js.php';

$topTriggers = (new CWidget())->setTitle(_('100 busiest triggers'));

$filterForm = new CFilter('web.toptriggers.filter.state');
$filterForm->addVar('filter_from', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_from']));
$filterForm->addVar('filter_till', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_till']));

$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();

$filterColumn2->addRow(_('From'), createDateSelector('filter_from', $this->data['filter']['filter_from']));
$filterColumn2->addRow(_('Till'), createDateSelector('filter_till', $this->data['filter']['filter_till']));
$filterColumn2->addRow(null,
	[
		new CButton(null, _('Today'), 'javascript: setPeriod('.REPORT_PERIOD_TODAY.');', 'link_menu'),
		new CButton(null, _('Yesterday'), 'javascript: setPeriod('.REPORT_PERIOD_YESTERDAY.');',
			'link_menu period-link'
		),
		new CButton(null, _('Current week'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_WEEK.');',
			'link_menu period-link'
		),
		new CButton(null, _('Current month'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_MONTH.');',
			'link_menu period-link'
		),
		new CButton(null, _('Current year'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_YEAR.');',
			'link_menu period-link'
		),
		BR(),
		new CButton(null, _('Last week'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_WEEK.');',
			'link_menu'
		),
		new CButton(null, _('Last month'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_MONTH.');',
			'link_menu period-link'
		),
		new CButton(null, _('Last year'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_YEAR.');',
			'link_menu period-link'
		)
	]
);

$filterColumn1->addRow(
	'Host groups',
	new CMultiSelect(
		[
			'name' => 'groupids[]',
			'objectName' => 'hostGroup',
			'data' => $this->data['multiSelectHostGroupData'],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$filterForm->getName().'&dstfld1=groupids_'.
					'&srcfld1=groupid&multiselect=1'
			]
		]
	)
);

$filterColumn1->addRow(
	'Hosts',
	new CMultiSelect(
		[
			'name' => 'hostids[]',
			'objectName' => 'hosts',
			'data' => $this->data['multiSelectHostData'],
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm='.$filterForm->getName().'&dstfld1=hostids_&srcfld1=hostid'.
					'&real_hosts=1&multiselect=1'
			]
		]
	)
);

// severities
$severitiesTable = (new CTable())->
	addClass('severities-table');

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$serverityCheckBox = new CCheckBox('severities['.$severity.']',
		in_array($severity, $this->data['filter']['severities']), null, 1
	);

	if ($severity % 2) {
		$severitiesCol2[] = new CCol([$serverityCheckBox, getSeverityName($severity, $this->data['config'])]);
	}
	else {
		$severitiesCol1[] = new CCol([$serverityCheckBox, getSeverityName($severity, $this->data['config'])]);
	}
}

$severitiesTable->addRow($severitiesCol1);
$severitiesTable->addRow($severitiesCol2);

$filterColumn1->addRow(_('Severity'), $severitiesTable);

$filterForm->addColumn($filterColumn1);
$filterForm->addColumn($filterColumn2);

$topTriggers->addItem($filterForm);

// table
$table = new CTableInfo();
$table->setHeader([
	_('Host'),
	_('Trigger'),
	_('Severity'),
	_('Number of status changes')
]);

foreach ($this->data['triggers'] as $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];

	$hostName = new CSpan($trigger['hosts'][0]['name'],
		ZBX_STYLE_LINK_ACTION.' link_menu'.(($this->data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) ? ' '.ZBX_STYLE_RED : '')
	);
	$hostName->setMenuPopup(CMenuPopupHelper::getHost($this->data['hosts'][$hostId], $this->data['scripts'][$hostId]));

	$triggerDescription = new CSpan($trigger['description'], ZBX_STYLE_LINK_ACTION.' link_menu');
	$triggerDescription->setMenuPopup(CMenuPopupHelper::getTrigger($trigger));

	$table->addRow([
		$hostName,
		$triggerDescription,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$trigger['cnt_event']
	]);
}

$topTriggers->addItem($table);

return $topTriggers;
