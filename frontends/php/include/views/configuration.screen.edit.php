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


$screenWidget = (new CWidget())->setTitle(_('Screens'));
if (!empty($this->data['templateid'])) {
	$screenWidget->addItem(get_header_host_table('screens', $this->data['templateid']));
}

// create form
$screenForm = (new CForm())
	->setName('screenForm')
	->addVar('form', $this->data['form'])
	->addVar('templateid', $this->data['templateid']);
if (!empty($this->data['screenid'])) {
	$screenForm->addVar('screenid', $this->data['screenid']);
}

// create screen form list
$screenFormList = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $this->data['name']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Columns'),
		(new CNumericBox('hsize', $this->data['hsize'], 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Rows'),
		(new CNumericBox('vsize', $this->data['vsize'], 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	);

// append tabs to form
$screenTab = (new CTabView())->addTab('screenTab', _('Screen'), $screenFormList);

// append buttons to form
if (isset($this->data['screenid']))
{
	$screenTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CButtonDelete(_('Delete screen?'), url_param('form').url_param('screenid').url_param('templateid')),
			new CButtonCancel(url_param('templateid'))
		]
	));
}
else {
	$screenTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('templateid'))]
	));
}

$screenForm->addItem($screenTab);

$screenWidget->addItem($screenForm);
return $screenWidget;
