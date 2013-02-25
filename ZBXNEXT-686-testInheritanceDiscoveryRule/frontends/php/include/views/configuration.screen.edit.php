<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$screenWidget = new CWidget();
$screenWidget->addPageHeader(_('CONFIGURATION OF SCREENS'));
if (!empty($this->data['templateid'])) {
	$screenWidget->addItem(get_header_host_table('screens', $this->data['templateid']));
}

// create form
$screenForm = new CForm();
$screenForm->setName('screenForm');
$screenForm->addVar('form', $this->data['form']);
if (!empty($this->data['screenid'])) {
	$screenForm->addVar('screenid', $this->data['screenid']);
}
$screenForm->addVar('templateid', $this->data['templateid']);

// create screen form list
$screenFormList = new CFormList('screenFormList');
$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$screenFormList->addRow(_('Name'), $nameTextBox);
$screenFormList->addRow(_('Columns'), new CNumericBox('hsize', $this->data['hsize'], 3));
$screenFormList->addRow(_('Rows'), new CNumericBox('vsize', $this->data['vsize'], 3));

// append tabs to form
$screenTab = new CTabView();
$screenTab->addTab('screenTab', _('Screen'), $screenFormList);
$screenForm->addItem($screenTab);

// append buttons to form
$screenForm->addItem(makeFormFooter(
	new CSubmit('save', _('Save')),
	array(
		!empty($this->data['screenid']) ? new CSubmit('clone', _('Clone')) : null,
		!empty($this->data['screenid']) ? new CButtonDelete(_('Delete screen?'), url_param('form').url_param('screenid').url_param('templateid')) : null,
		new CButtonCancel(url_param('templateid'))
	)
));

$screenWidget->addItem($screenForm);
return $screenWidget;
