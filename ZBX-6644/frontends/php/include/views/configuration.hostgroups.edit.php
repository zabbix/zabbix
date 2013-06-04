<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$hostgroupWidget = new CWidget();
$hostgroupWidget->addPageHeader(_('CONFIGURATION OF HOST GROUPS'));

// create form
$hostgroupForm = new CForm();
$hostgroupForm->setName('hostgroupForm');
$hostgroupForm->addVar('form', $this->data['form']);
if (isset($this->data['groupid'])) {
	$hostgroupForm->addVar('groupid', $this->data['groupid']);
}

// create hostgroup form list
$hostgroupFormList = new CFormList('hostgroupFormList');
$hostgroupFormList->addRow(_('Group name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE, false, 64));

// append groups and hosts to form list
$groupsComboBox = new CComboBox('twb_groupid', $this->data['twb_groupid'], 'submit()');
$groupsComboBox->addItem('0', _('All'));
foreach ($this->data['db_groups'] as $row) {
	$groupsComboBox->addItem($row['groupid'], $row['name']);
}

$hostsComboBox = new CTweenBox($hostgroupForm, 'hosts', $this->data['hosts'], 25);
foreach ($this->data['db_hosts'] as $host) {
	// add all hosts except selected
	if (!isset($this->data['hosts'][$host['hostid']])) {
		$hostsComboBox->addItem($host['hostid'], $host['name']);
	}
}
foreach ($this->data['r_hosts'] as $host) {
	if (isset($this->data['rw_hosts'][$host['hostid']])) {
		$hostsComboBox->addItem($host['hostid'], $host['name']);
	}
	else {
		$hostsComboBox->addItem($host['hostid'], $host['name'], true, false);
	}
}
$hostgroupFormList->addRow(_('Hosts'), $hostsComboBox->get(_('Hosts in'), array(_('Other hosts | Group').SPACE, $groupsComboBox)));

// append tabs to form
$hostgroupTab = new CTabView();
$hostgroupTab->addTab('hostgroupTab', _('Host group'), $hostgroupFormList);
$hostgroupForm->addItem($hostgroupTab);

// append buttons to form
if (empty($this->data['groupid'])) {
	$hostgroupForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(new CButtonCancel())
	));
}
else {
	$deleteButton = new CButtonDelete(_('Delete selected group?'), url_param('form').url_param('groupid'));
	if (empty($this->data['deletableHostGroups'])) {
		$deleteButton->attr('disabled', 'disabled');
	}
	$hostgroupForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CSubmit('clone', _('Clone')),
			$deleteButton,
			new CButtonCancel())
	));
}

$hostgroupWidget->addItem($hostgroupForm);

return $hostgroupWidget;
