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


$actionWidget = new CWidget();

// create new action button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('eventsource', $this->data['eventsource']);
$createForm->addItem(new CSubmit('form', _('Create action')));
$actionWidget->addPageHeader(_('CONFIGURATION OF ACTIONS'), $createForm);

// create widget header
$sourceComboBox = new CComboBox('eventsource', $this->data['eventsource'], 'submit()');
$sourceComboBox->addItem(EVENT_SOURCE_TRIGGERS, _('Triggers'));
$sourceComboBox->addItem(EVENT_SOURCE_DISCOVERY, _('Discovery'));
$sourceComboBox->addItem(EVENT_SOURCE_AUTO_REGISTRATION, _('Auto registration'));
$sourceComboBox->addItem(EVENT_SOURCE_INTERNAL, _x('Internal', 'event source'));
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Event source'), SPACE, $sourceComboBox));

$actionWidget->addHeader(_('Actions'), $filterForm);
$actionWidget->addHeaderRowNumber();

// create form
$actionForm = new CForm();
$actionForm->setName('actionForm');

// create table
$actionTable = new CTableInfo(_('No actions found.'));
$actionTable->setHeader(array(
	new CCheckBox('all_items', null, "checkAll('".$actionForm->getName()."', 'all_items', 'g_actionid');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Conditions'),
	_('Operations'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
));

foreach ($this->data['actions'] as $action) {
	$conditions = array();
	order_result($action['filter']['conditions'], 'conditiontype', ZBX_SORT_DOWN);
	foreach ($action['filter']['conditions'] as $condition) {
		$conditions[] = get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']);
		$conditions[] = BR();
	}

	sortOperations($this->data['eventsource'], $action['operations']);
	$operations = array();
	foreach ($action['operations'] as $operation) {
		$operations[] = get_operation_descr(SHORT_DESCRIPTION, $operation);
	}

	if ($action['status'] == ACTION_STATUS_DISABLED) {
		$status = new CLink(_('Disabled'),
			'actionconf.php?action=action.massenable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
			'disabled'
		);
	}
	else {
		$status = new CLink(_('Enabled'),
			'actionconf.php?action=action.massdisable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
			'enabled'
		);
	}

	$actionTable->addRow(array(
		new CCheckBox('g_actionid['.$action['actionid'].']', null, null, $action['actionid']),
		new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']),
		$conditions,
		new CCol($operations, 'wraptext'),
		$status
	));
}

// create go buttons
$goComboBox = new CComboBox('action');

$goOption = new CComboItem('action.massenable', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable selected actions?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('action.massdisable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable selected actions?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('action.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected actions?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "g_actionid";');

// append table to form
$actionForm->addItem(array($this->data['paging'], $actionTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$actionWidget->addItem($actionForm);

return $actionWidget;
