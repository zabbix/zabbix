<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

$topTriggers= new CWidget(null, 'top-triggers');

$topTriggers->addHeader(_('Most busy triggers top 100'));

$filterForm = new CForm('get');
$filterForm->setAttribute('name',' zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('filter_from', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_from']));
$filterForm->addVar('filter_till', date(TIMESTAMP_FORMAT, $this->data['filter']['filter_till']));

$filterTable = new CTable(null, 'filter');
$filterTable->setCellPadding(0);
$filterTable->setCellSpacing(0);

$filterTable->addRow(
	array(
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
					'height' => 450,
					'buttonClass' => 'input filter-multiselect-select-button'
				)
			)),
			'inputcol'
		),
		new CCol(bold(_('From')), 'label'),
		new CCol(array(createDateSelector('filter_from', $this->data['filter']['filter_from'])))
	)
);

$filterTable->addRow(
	array(
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
					'height' => 450,
					'buttonClass' => 'input filter-multiselect-select-button'
				)
			)),
			'inputcol'
		),
		new CCol(bold(_('Till')), 'label'),
		new CCol(array(createDateSelector('filter_till', $this->data['filter']['filter_till'])))
	)
);

// get all severities
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$serverityCheckBox = new CCheckBox('severities['.$severity.']',
		in_array($severity, $this->data['filter']['severities']), '', 1
	);

	$severities[] = array($serverityCheckBox, getSeverityName($severity, $this->data['config']));
	$severities[] = BR();
}
array_pop($severities);

$filterTable->addRow(array(
	new CCol(bold(_('Severity')), 'label top'),
	new CCol($severities, 'inputcol'),
	new CCol(null, 'label'),
	new CCol(array(
		new CButton('quickTimeInput', _('Today'), 'javascript: setPeriod('.REPORT_PERIOD_TODAY.');', 'link_menu'),
		new CButton('quickTimeInput', _('Yesterday'), 'javascript: setPeriod('.REPORT_PERIOD_YESTERDAY.');',
			'link_menu link'
		),
		new CButton('quickTimeInput', _('Current week'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_WEEK.');',
			'link_menu link'
		),
		new CButton('quickTimeInput', _('Current month'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_MONTH.');',
			'link_menu link'
		),
		new CButton('quickTimeInput', _('Current year'), 'javascript: setPeriod('.REPORT_PERIOD_CURRENT_YEAR.');',
			'link_menu link'
		),
		BR(),
		new CButton('quickTimeInput', _('Last week'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_WEEK.');',
			'link_menu'
		),
		new CButton('quickTimeInput', _('Last month'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_MONTH.');',
			'link_menu link'
		),
		new CButton('quickTimeInput', _('Last year'), 'javascript: setPeriod('.REPORT_PERIOD_LAST_YEAR.');',
			'link_menu link'
		)
	), 'top')
), 'top');

$filterButton = new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();');
$filterButton->useJQueryStyle('main');

$resetButton = new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();');
$resetButton->useJQueryStyle();

$divButtons = new CDiv(array($filterButton, SPACE, $resetButton));
$divButtons->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($divButtons, 'controls', 4));

$filterForm->addItem($filterTable);

$topTriggers->addFlicker($filterForm, CProfile::get('web.toptriggers.filter.state', 0));
$topTriggers->addPageHeader(_('Triggers top 100'));

// table
$table = new CTableInfo(_('No triggers found.'));
$table->setHeader(array(
	_('Host'),
	_('Trigger'),
	_('Severity'),
	_('Number of status changes')
));

foreach ($this->data['triggers'] as $trigger) {
	$triggerDescription = new CSpan($trigger['description'], 'link_menu');
	$triggerDescription->setMenuPopup(CMenuPopupHelper::getTrigger($trigger, $trigger['items']));

	$table->addRow(array(
		$trigger['hostName'],
		$triggerDescription,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$trigger['cnt_event']
	));
}

$topTriggers->addItem($table);

require_once dirname(__FILE__).'/js/reports.toptriggers.js.php';

return $topTriggers;
