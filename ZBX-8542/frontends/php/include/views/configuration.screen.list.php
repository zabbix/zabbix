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


$screenWidget = new CWidget();

// create new screen button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CSubmit('form', _('Create screen')));
if (!empty($this->data['templateid'])) {
	$createForm->addVar('templateid', $this->data['templateid']);
	$screenWidget->addItem(get_header_host_table('screens', $this->data['templateid']));
}
else {
	$createForm->addItem(new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=screen")'));
}
$screenWidget->addPageHeader(_('CONFIGURATION OF SCREENS'), $createForm);

// header
$screenWidget->addHeader(_('Screens'));
$screenWidget->addHeaderRowNumber();

// create form
$screenForm = new CForm();
$screenForm->setName('screenForm');

$screenForm->addVar('templateid', $this->data['templateid']);

// create table
$screenTable = new CTableInfo(_('No screens found.'));
$screenTable->setHeader(array(
	new CCheckBox('all_screens', null, "checkAll('".$screenForm->getName()."', 'all_screens', 'screens');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Dimension (cols x rows)'),
	_('Screen')
));

foreach ($this->data['screens'] as $screen) {
	$screenTable->addRow(array(
		new CCheckBox('screens['.$screen['screenid'].']', null, null, $screen['screenid']),
		new CLink($screen['name'], 'screenedit.php?screenid='.$screen['screenid'].url_param('templateid')),
		$screen['hsize'].' x '.$screen['vsize'],
		new CLink(_('Edit'), '?form=update&screenid='.$screen['screenid'].url_param('templateid'))
	));
}

// create go button
$goComboBox = new CComboBox('action');
if (empty($this->data['templateid'])) {
	$goComboBox->addItem('screen.export', _('Export selected'));
}
$goOption = new CComboItem('screen.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected screens?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "screens";');

// append table to form
$screenForm->addItem(array($this->data['paging'], $screenTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$screenWidget->addItem($screenForm);

return $screenWidget;
