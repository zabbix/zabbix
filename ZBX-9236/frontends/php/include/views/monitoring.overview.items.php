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


$overviewWidget = (new CWidget())->setTitle(_('Overview'));

$headerForm = new CForm('get');

$controls = new CList();
$controls->addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()]);
$controls->addItem([_('Type').SPACE, new CComboBox('type', $this->data['type'], 'submit()', [
	SHOW_TRIGGERS => _('Triggers'),
	SHOW_DATA => _('Data')
])]);

// hint table
$hintTable = new CTableInfo();
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$hintTable->addRow([getSeverityCell($severity, $this->data['config']), _('PROBLEM')]);
}
$hintTable->addRow([new CCol(SPACE), _('OK or no trigger')]);

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
$filter = new CFilter('web.overview.filter.state');
$filter->addVar('fullscreen', $this->data['fullscreen']);
$filter->addVar('groupid', $this->data['groupid']);
$filter->addVar('hostid', $this->data['hostid']);

$column = new CFormList();

// application
$column->addRow(_('Filter by application'), [
	new CTextBox('application', $this->data['filter']['application'], 40),
	new CButton('application_name', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1'.
		'&dstfrm=zbx_filter");',
		'button-form'
	)
]);

$filter->addColumn($column);

$overviewWidget->addItem($filter);

// data table
if ($data['pageFilter']->groupsSelected) {
	$dataTable = getItemsDataOverview(array_keys($this->data['pageFilter']->hosts), $this->data['applicationIds'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo();
}

$overviewWidget->addItem($dataTable);

return $overviewWidget;
