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


$hostGroupWidget = new CWidget();
$hostGroupWidget->addPageHeader(_('CONFIGURATION OF HOST GROUPS'));

// create form
$hostGroupForm = new CForm();
$hostGroupForm->setName('hostgroupForm');
$hostGroupForm->addVar('form', $this->data['form']);
if (isset($this->data['groupid'])) {
	$hostGroupForm->addVar('groupid', $this->data['groupid']);
}

// create hostgroup form list
$hostGroupFormList = new CFormList('hostgroupFormList');
$nameTextBox = new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE,
	($this->data['groupid'] && $this->data['group']['flags'] == ZBX_FLAG_DISCOVERY_CREATED),
	64
);
$nameTextBox->attr('autofocus', 'autofocus');
$hostGroupFormList->addRow(_('Group name'), $nameTextBox);

// append groups and hosts to form list
$groupsComboBox = new CComboBox('twb_groupid', $this->data['twb_groupid'], 'submit()');
$groupsComboBox->addItem('0', _('All'));
foreach ($this->data['db_groups'] as $group) {
	$groupsComboBox->addItem($group['groupid'], $group['name']);
}

$hostsComboBox = new CTweenBox($hostGroupForm, 'hosts', $this->data['hosts'], 25);
foreach ($this->data['db_hosts'] as $host) {
	if (!isset($this->data['hosts'][$host['hostid']])) {
		$hostsComboBox->addItem($host['hostid'], $host['name']);
	}
}
foreach ($this->data['r_hosts'] as $host) {
	if (isset($this->data['r_hosts'][$host['hostid']]) && $host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$hostsComboBox->addItem($host['hostid'], $host['name']);
	}
	else {
		$hostsComboBox->addItem($host['hostid'], $host['name'], true, false);
	}
}
$hostGroupFormList->addRow(_('Hosts'), $hostsComboBox->get(_('Hosts in'), array(_('Other hosts | Group').SPACE, $groupsComboBox)));

// append tabs to form
$hostGroupTab = new CTabView();
$hostGroupTab->addTab('hostgroupTab', _('Host group'), $hostGroupFormList);
$hostGroupForm->addItem($hostGroupTab);

// append buttons to form
if (empty($this->data['groupid'])) {
	$hostGroupForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		new CButtonCancel()
	));
}
else {
	$deleteButton = new CButtonDelete(_('Delete selected group?'), url_param('form').url_param('groupid'));
	if (empty($this->data['deletableHostGroups'])) {
		$deleteButton->attr('disabled', 'disabled');
	}

	$hostGroupForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		array(
			new CSubmit('clone', _('Clone')),
			$deleteButton,
			new CButtonCancel())
	));
}

$hostGroupWidget->addItem($hostGroupForm);

return $hostGroupWidget;
