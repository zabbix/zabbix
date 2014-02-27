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


$auditWidget = new CWidget();

// header
$configForm = new CForm('get');
$configComboBox = new CComboBox('config', 'auditlogs.php');
$configComboBox->setAttribute('onchange', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('auditlogs.php', _('Audit log'));
$configComboBox->addItem('auditacts.php', _('Action log'));
$configForm->addItem($configComboBox);
$auditWidget->addPageHeader(_('AUDIT LOG'), $configForm);
$auditWidget->addHeader(_('Audit log'));
$auditWidget->addHeaderRowNumber();

// create filter
$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterTable = new CTable('', 'filter');

$actionComboBox = new CComboBox('action', $this->data['action']);
$actionComboBox->addItem(-1, _('All'));
$actionComboBox->addItem(AUDIT_ACTION_LOGIN, _('Login'));
$actionComboBox->addItem(AUDIT_ACTION_LOGOUT, _('Logout'));
$actionComboBox->addItem(AUDIT_ACTION_ADD, _('Add'));
$actionComboBox->addItem(AUDIT_ACTION_UPDATE, _('Update'));
$actionComboBox->addItem(AUDIT_ACTION_DELETE, _('Delete'));
$actionComboBox->addItem(AUDIT_ACTION_ENABLE, _('Enable'));
$actionComboBox->addItem(AUDIT_ACTION_DISABLE, _('Disable'));

$resourceComboBox = new CComboBox('resourcetype', $this->data['resourcetype']);
$resourceComboBox->addItems(array(-1 => _('All')) + audit_resource2str());

$filterTable->addRow(array(
	array(
		bold(_('User')),
		SPACE,
		new CTextBox('alias', $this->data['alias'], 20),
		new CButton('btn1', _('Select'), 'return PopUp("popup.php?dstfrm='.$filterForm->getName().
			'&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1");', 'filter-select-button')
	),
	array(bold(_('Action')), SPACE, $actionComboBox),
	array(bold(_('Resource')), SPACE, $resourceComboBox)
));
$filterButton = new CButton('filter', _('Filter'), "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
$filterButton->useJQueryStyle('main');
$resetButton = new CButton('filter_rst', _('Reset'), 'javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst", 1); location.href = uri.getUrl();');
$resetButton->useJQueryStyle();
$buttonsDiv = new CDiv(array($filterButton, SPACE, $resetButton));
$buttonsDiv->setAttribute('style', 'padding: 4px 0;');

$filterTable->addRow(new CCol($buttonsDiv, 'controls', 3));
$filterForm->addItem($filterTable);

$auditWidget->addFlicker($filterForm, CProfile::get('web.auditlogs.filter.state', 1));
$auditWidget->addFlicker(new CDiv(null, null, 'scrollbar_cntr'), CProfile::get('web.auditlogs.filter.state', 1));

// create form
$auditForm = new CForm('get');
$auditForm->setName('auditForm');

// create table
$auditTable = new CTableInfo(_('No audit log entries found.'));
$auditTable->setHeader(array(
	_('Time'),
	_('User'),
	_('IP'),
	_('Resource'),
	_('Action'),
	_('ID'),
	_('Description'),
	_('Details')
));
foreach ($this->data['actions'] as $action) {
	$details = array();
	if (is_array($action['details'])) {
		foreach ($action['details'] as $detail) {
			$details[] = array($detail['table_name'].'.'.$detail['field_name'].NAME_DELIMITER.$detail['oldvalue'].' => '.$detail['newvalue'], BR());
		}
	}
	else {
		$details = $action['details'];
	}

	$auditTable->addRow(array(
		zbx_date2str(_('d M Y H:i:s'), $action['clock']),
		$action['alias'],
		$action['ip'],
		$action['resourcetype'],
		$action['action'],
		$action['resourceid'],
		$action['resourcename'],
		new CCol($details, 'wraptext')
	));
}

// append table to form
$auditForm->addItem(array($this->data['paging'], $auditTable, $this->data['paging']));

// append navigation bar js
$objData = array(
	'id' => 'timeline_1',
	'domid' => 'events',
	'loadSBox' => 0,
	'loadImage' => 0,
	'loadScroll' => 1,
	'dynamic' => 0,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.auditlogs.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);
zbx_add_post_js('timeControl.addObject("events", '.zbx_jsvalue($this->data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

return $auditWidget;
