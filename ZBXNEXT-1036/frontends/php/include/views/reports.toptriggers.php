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
		new CCol(array(createDateSelector('filter_from', 0, 'filter_timetill')))
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
		new CCol(array(createDateSelector('filter_till', 0, 'filter_timetill')))
	)
);

$severtyComboBox = new CComboBox('severity', $this->data['filter']['severity']);
$severtyComboBox->addItem(-1, _('All'));

// get all severities
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$severtyComboBox->addItem($severity, getSeverityName($severity, $this->data['config']));
}

$filterTable->addRow(array(
	new CCol(bold(_('Severity')), 'label'),
	new CCol($severtyComboBox, 'inputcol'),
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
	))
));

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
$table = new CTableInfo(($this->data['filterSet']) ? _('No values found.') : _('Specify some filter condition to see the values.'));

$topTriggers->addItem($table);

require_once dirname(__FILE__).'/js/reports.toptriggers.js.php';

return $topTriggers;
