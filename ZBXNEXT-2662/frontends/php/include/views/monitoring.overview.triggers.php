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

zbx_add_post_js('jqBlink.blink();');

$overviewWidget = new CWidget();
$overviewWidget->setTitle(_('Overview'));

$typeComboBox = new CComboBox('type', $this->data['type'], 'submit()');
$typeComboBox->addItem(SHOW_TRIGGERS, _('Triggers'));
$typeComboBox->addItem(SHOW_DATA, _('Data'));

$headerForm = new CForm('get');
$controls = new CList();

$controls->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
$controls->addItem(array(_('Type').SPACE, $typeComboBox));

// hint table
$hintTable = new CTableInfo(null, 'tableinfo tableinfo-overview-hint');
$hintTable->addRow(array(new CCol(SPACE, 'normal'), _('OK')));
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$hintTable->addRow(array(getSeverityCell($severity, $this->data['config']), _('PROBLEM')));
}

// blinking preview in help popup (only if blinking is enabled)
if ($this->data['config']['blink_period'] > 0) {
	$row = new CRow(null);
	$row->addItem(new CCol(SPACE, 'normal'));
	for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
		$row->addItem(new CCol(SPACE, getSeverityStyle($i)));
	}
	$col = new CTable('', 'blink overview-mon-severities');
	$col->addRow($row);

	// double div necassary for FireFox
	$col = new CCol(new CDiv(new CDiv($col), 'overview-mon-severities-container'));

	$hintTable->addRow(array($col, _s('Age less than %s', convertUnitsS($this->data['config']['blink_period']))));
}

$hintTable->addRow(array(new CCol(SPACE), _('No trigger')));

$help = new CIcon(null, 'iconhelp');
$help->setHint($hintTable);

// header right
$controls->addItem(get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])));
$controls->addItem($help);

// header left
$styleComboBox = new CComboBox('view_style', $this->data['view_style'], 'submit()');
$styleComboBox->addItem(STYLE_TOP, _('Top'));
$styleComboBox->addItem(STYLE_LEFT, _('Left'));

$controls->additem(array(_('Hosts location').SPACE, $styleComboBox));

$headerForm->addItem($controls);
$overviewWidget->setControls($headerForm);

// filter
$filter = $this->data['filter'];
$filterFormView = new CView('common.filter.trigger', array(
	'overview' => true,
	'filter' => array(
		'showTriggers' => $filter['showTriggers'],
		'ackStatus' => $filter['ackStatus'],
		'showSeverity' => $filter['showSeverity'],
		'statusChange' => $filter['statusChange'],
		'statusChangeDays' => $filter['statusChangeDays'],
		'txtSelect' => $filter['txtSelect'],
		'application' => $filter['application'],
		'inventory' => $filter['inventory'],
		'showMaintenance' => $filter['showMaintenance'],
		'hostId' => $this->data['hostid'],
		'groupId' => $this->data['groupid'],
		'fullScreen' => $this->data['fullscreen']
	),
	'config' => $this->data['config']
));
$filterForm = $filterFormView->render();

$overviewWidget->addFlicker($filterForm, CProfile::get('web.overview.filter.state', 0));

// data table
if ($this->data['config']['dropdown_first_entry']) {
	global $page;

	$dataTable = getTriggersOverview($this->data['hosts'], $this->data['triggers'], $page['file'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo(_('No triggers found.'));
}

$overviewWidget->addItem($dataTable);

return $overviewWidget;
