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

$overviewWidget = (new CWidget())->setTitle(_('Overview'));

$typeComboBox = new CComboBox('type', $this->data['type'], 'submit()', [
	SHOW_TRIGGERS => _('Triggers'),
	SHOW_DATA => _('Data')
]);

$headerForm = new CForm('get');
$controls = new CList();

$controls->addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()]);
$controls->addItem([_('Type').SPACE, $typeComboBox]);

// hint table
$hintTable = new CTableInfo();
$hintTable->addRow([(new CCol(SPACE))->addClass('normal'), _('OK')]);
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$hintTable->addRow([getSeverityCell($severity, $this->data['config']), _('PROBLEM')]);
}

// blinking preview in help popup (only if blinking is enabled)
if ($this->data['config']['blink_period'] > 0) {
	$row = new CRow();
	$row->addItem((new CCol(SPACE))->addClass('normal'));
	for ($i = 0; $i < TRIGGER_SEVERITY_COUNT; $i++) {
		$row->addItem((new CCol(SPACE))->addClass(getSeverityStyle($i)));
	}
	$col = (new CTable(''))->
		addClass('blink')->
		addClass('overview-mon-severities')->
		addRow($row);

	// double div necassary for FireFox
	$col = new CCol(new CDiv(new CDiv($col), 'overview-mon-severities-container'));

	$hintTable->addRow([$col, _s('Age less than %s', convertUnitsS($this->data['config']['blink_period']))]);
}

$hintTable->addRow([new CCol(SPACE), _('No trigger')]);

// header left
$styleComboBox = new CComboBox('view_style', $this->data['view_style'], 'submit()', [
	STYLE_TOP => _('Top'),
	STYLE_LEFT => _('Left')
]);

$controls->additem([_('Hosts location').SPACE, $styleComboBox]);

// header right
$help = get_icon('overviewhelp');
$help->setHint($hintTable);
$controls->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));
$controls->addItem($help);

$headerForm->addItem($controls);
$overviewWidget->setControls($headerForm);

// filter
$filter = $this->data['filter'];
$filterFormView = new CView('common.filter.trigger', [
	'overview' => true,
	'filter' => [
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
	],
	'config' => $this->data['config']
]);
$filterForm = $filterFormView->render();

$overviewWidget->addItem($filterForm);

// data table
if ($data['pageFilter']->groupsSelected) {
	global $page;

	$dataTable = getTriggersOverview($this->data['hosts'], $this->data['triggers'], $page['file'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo();
}

$overviewWidget->addItem($dataTable);

return $overviewWidget;
