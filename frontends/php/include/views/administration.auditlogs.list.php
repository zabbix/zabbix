<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$auditWidget = (new CWidget())->setTitle(_('Audit log'));

// header

$filterColumn = new CFormList();
$filterColumn->addRow(_('User'), [
	(new CTextBox('alias', $this->data['alias']))
		->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		->setAttribute('autofocus', 'autofocus'),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('btn1', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.
			CJs::encodeJson([
				'srctbl' => 'users',
				'srcfld1' => 'alias',
				'dstfrm' => 'zbx_filter',
				'dstfld1' => 'alias'
			]).', null, this);'
		)
]);
$filterColumn->addRow(_('Action'), new CComboBox('action', $this->data['action'], null, [
	-1 => _('All'),
	AUDIT_ACTION_LOGIN => _('Login'),
	AUDIT_ACTION_LOGOUT => _('Logout'),
	AUDIT_ACTION_ADD => _('Add'),
	AUDIT_ACTION_UPDATE => _('Update'),
	AUDIT_ACTION_DELETE => _('Delete'),
	AUDIT_ACTION_ENABLE => _('Enable'),
	AUDIT_ACTION_DISABLE => _('Disable')
]));
$filterColumn->addRow(_('Resource'), new CComboBox('resourcetype', $this->data['resourcetype'], null,
	[-1 => _('All')] + audit_resource2str()
));

$auditWidget->addItem(
	(new CFilter())
		->setProfile('web.auditlogs.filter', 0)
		->addTimeSelector($data['timeline']['from'], $data['timeline']['to'])
		->addFilterTab(_('Filter'), [$filterColumn])
);

// create form
$auditForm = (new CForm('get'))->setName('auditForm');

// create table
$auditTable = (new CTableInfo())
	->setHeader([
		_('Time'),
		_('User'),
		_('IP'),
		_('Resource'),
		_('Action'),
		_('ID'),
		_('Description'),
		_('Details')
	]);
foreach ($this->data['actions'] as $action) {
	$details = [];
	if (is_array($action['details'])) {
		foreach ($action['details'] as $detail) {
			$details[] = [$detail['table_name'].'.'.$detail['field_name'].NAME_DELIMITER.$detail['oldvalue'].' => '.$detail['newvalue'], BR()];
		}
	}
	else {
		$details = $action['details'];
	}

	$auditTable->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $action['clock']),
		$action['alias'],
		$action['ip'],
		$action['resourcetype'],
		$action['action'],
		$action['resourceid'],
		$action['resourcename'],
		$details
	]);
}

// append table to form
$auditForm->addItem([$auditTable, $this->data['paging']]);

// append navigation bar js
$objData = [
	'id' => 'timeline_1',
	'domid' => 'events',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0,
	'mainObject' => 1
];
zbx_add_post_js('timeControl.addObject("events", '.zbx_jsvalue($this->data['timeline']).', '.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// append form to widget
$auditWidget->addItem($auditForm);

return $auditWidget;
