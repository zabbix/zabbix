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


$widget = (new CWidget())
	->setTitle(_('Applications'))
	->addItem(get_header_host_table('applications', $this->data['hostid']));

// create form
$applicationForm = (new CForm())
	->addVar('form', $this->data['form'])
	->addVar('hostid', $this->data['hostid']);
if (!empty($this->data['applicationid'])) {
	$applicationForm->addVar('applicationid', $this->data['applicationid']);
}

// append tabs to form
$applicationTab = (new CTabView())
	->addTab('applicationTab', _('Application'),
		(new CFormList())
			->addRow(_('Name'),
				(new CTextBox('appname', $this->data['appname']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
	);

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
$widget->addItem($applicationForm);

return $widget;
