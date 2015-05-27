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


$applicationWidget = (new CWidget())->setTitle(_('Applications'))->
	addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$applicationForm = new CForm();
$applicationForm->setName('applicationForm');
$applicationForm->addVar('form', $this->data['form']);
$applicationForm->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['applicationid'])) {
	$applicationForm->addVar('applicationid', $this->data['applicationid']);
}

// create form list
$applicationFormList = new CFormList('applicationFormList');
$nameTextBox = new CTextBox('appname', $this->data['appname'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->setAttribute('autofocus', 'autofocus');
$applicationFormList->addRow(_('Name'), $nameTextBox);

// append tabs to form
$applicationTab = new CTabView();
$applicationTab->addTab('applicationTab', _('Application'), $applicationFormList);

// append buttons to form
if (!empty($this->data['applicationid'])) {
	$applicationTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete application?'), url_params(['hostid', 'form', 'applicationid'])),
			new CButtonCancel(url_param('hostid'))
		]
	));
}
else {
	$applicationTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('hostid'))]
	));
}

$applicationForm->addItem($applicationTab);

// append form to widget
$applicationWidget->addItem($applicationForm);

return $applicationWidget;
