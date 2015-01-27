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

$topTriggers = new CWidget(null, 'top-triggers');
$topTriggers->addHeader(_('Report'));

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('filter_from', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_from']));
$filterForm->addVar('filter_till', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_till']));

$filterTable = new CTable(null, 'filter');

$periodTable = new CTable(null, 'period-table');

$periodTable->addRow(array(
	new CCol(bold(_('From')), 'label'),
	new CCol(array(createDateSelector('filter_from', $this->data['filter']['filter_from'])))
));

$periodTable->addRow(array(
	new CCol(bold(_('Till')), 'label'),
	new CCol(array(createDateSelector('filter_till', $this->data['filter']['filter_till'])))
));

$periodTable->addRow(array(
	new CCol(null, 'label'),
	new CCol(array(
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
	), 'quick-input')
));

$periods = new CCol($periodTable, 'top');
$periods->setRowSpan(3);

$filterTable->addRow(array(
	new CCol(bold(_('Host groups')), 'label'),
	new CCol(new CMultiSelect(
		array(
			'name' => 'groupids[]',
			'objectName' => 'hostGroup',
			'data' => $this->data['multiSelectHostGroupData'],
			'popup' => array(
				'parameters' => 'srctbl=host_groups&dstfrm='.$filterForm->getName().'&dstfld1=groupids_'.
					'&srcfld1=groupid&multiselect=1',
				'width' => 450,
				'height' => 450
			)
		)),
		'inputcol'
	),
	$periods
));

$filterTable->addRow(array(
	new CCol(bold(_('Hosts')), 'label'),
	new CCol(new CMultiSelect(
		array(
			'name' => 'hostids[]',
			'objectName' => 'hosts',
			'data' => $this->data['multiSelectHostData'],
			'popup' => array(
				'parameters' => 'srctbl=hosts&dstfrm='.$filterForm->getName().'&dstfld1=hostids_&srcfld1=hostid'.
					'&real_hosts=1&multiselect=1',
				'width' => 450,
				'height' => 450
			)
		)),
		'inputcol'
	)
));

// severities
$severitiesTable = new CTable(null, 'severities-table');

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$serverityCheckBox = new CCheckBox('severities['.$severity.']',
		in_array($severity, $this->data['filter']['severities']), null, 1
	);

	if ($severity % 2) {
		$severitiesCol2[] = new CCol(array($serverityCheckBox, getSeverityName($severity, $this->data['config'])));
	}
	else {
		$severitiesCol1[] = new CCol(array($serverityCheckBox, getSeverityName($severity, $this->data['config'])));
	}
}

$severitiesTable->addRow($severitiesCol1);
$severitiesTable->addRow($severitiesCol2);

$filterTable->addRow(array(
	new CCol(bold(_('Severity')), 'label top'),
	new CCol($severitiesTable)
));

$filterButton = new CSubmit('filter_set', _('Filter'), null, 'jqueryinput shadow');
$filterButton->main();

$resetButton = new CSubmit('filter_rst', _('Reset'), null, 'jqueryinput shadow');

$divButtons = new CDiv(array($filterButton, $resetButton));
$divButtons->addStyle('padding: 4px 0px;');

$filterTable->addRow(new CCol($divButtons, 'controls', 4));

$filterForm->addItem($filterTable);

$topTriggers->addFlicker($filterForm, CProfile::get('web.toptriggers.filter.state', 0));
$topTriggers->addPageHeader(_('MOST BUSY TRIGGERS TOP 100'));

// table
$table = new CTableInfo(_('No triggers found.'));
$table->setHeader(array(
	_('Host'),
	_('Trigger'),
	_('Severity'),
	_('Number of status changes')
));

foreach ($this->data['triggers'] as $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];

	$hostName = new CSpan($trigger['hosts'][0]['name'],
		'link_menu menu-host'.(($this->data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) ? ' not-monitored' : '')
	);
	$hostName->setMenuPopup(CMenuPopupHelper::getHost($this->data['hosts'][$hostId], $this->data['scripts'][$hostId]));

	$triggerDescription = new CSpan($trigger['description'], 'link_menu');
	$triggerDescription->setMenuPopup(CMenuPopupHelper::getTrigger($trigger, $trigger['items']));

	$table->addRow(array(
		$hostName,
		$triggerDescription,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$trigger['cnt_event']
	));
}

$topTriggers->addItem($table);

return $topTriggers;
