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


$overviewWidget = new CWidget();

$typeComboBox = new CComboBox('type', $this->data['type'], 'submit()');
$typeComboBox->addItem(SHOW_TRIGGERS, _('Triggers'));
$typeComboBox->addItem(SHOW_DATA, _('Data'));

$headerForm = new CForm('get');
$headerForm->addItem(array(_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()));
$headerForm->addItem(array(SPACE, _('Type'), SPACE, $typeComboBox));

$overviewWidget->addHeader(_('Overview'), $headerForm);

// hint table
$hintTable = new CTableInfo(null, 'tableinfo tableinfo-overview-hint');
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$hintTable->addRow(array(getSeverityCell($severity, $this->data['config']), _('PROBLEM')));
}
$hintTable->addRow(array(new CCol(SPACE), _('OK or no trigger')));

$help = new CHelp();
$help->setHint($hintTable);

// header right
$overviewWidget->addPageHeader(_('OVERVIEW'), array(
	get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])),
	SPACE,
	$help
));

// header left
$styleComboBox = new CComboBox('view_style', $this->data['view_style'], 'submit()');
$styleComboBox->addItem(STYLE_TOP, _('Top'));
$styleComboBox->addItem(STYLE_LEFT, _('Left'));

$hostLocationForm = new CForm('get');
$hostLocationForm->addVar('groupid', $this->data['groupid']);
$hostLocationForm->additem(array(_('Hosts location'), SPACE, $styleComboBox));

$overviewWidget->addHeader($hostLocationForm);

// filter
$filterForm = new CFormTable(null, null, 'get');
$filterForm->setTableClass('formtable old-filter');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('fullscreen', $this->data['fullscreen']);
$filterForm->addVar('groupid', $this->data['groupid']);
$filterForm->addVar('hostid', $this->data['hostid']);

// application
$filterForm->addRow(_('Filter by application'), array(
	new CTextBox('application', $this->data['filter']['application'], 40),
	new CButton('application_name', _('Select'),
		'return PopUp("popup.php?srctbl=applications&srcfld1=name&real_hosts=1&dstfld1=application&with_applications=1'.
		'&dstfrm='.$filterForm->getName().'");',
		'button-form'
	)
));

// filter buttons
$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();'));
$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();'));

$overviewWidget->addFlicker($filterForm, CProfile::get('web.overview.filter.state', 0));

// data table
if ($this->data['config']['dropdown_first_entry']) {
	$dataTable = getItemsDataOverview(array_keys($this->data['pageFilter']->hosts), $this->data['applicationIds'],
		$this->data['view_style']
	);
}
else {
	$dataTable = new CTableInfo(_('No items found.'));
}

$overviewWidget->addItem($dataTable);

return $overviewWidget;
