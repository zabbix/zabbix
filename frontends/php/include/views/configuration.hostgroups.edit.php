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


$widget = (new CWidget())->setTitle(_('Host groups'));

// create form
$hostGroupForm = (new CForm())
	->setName('hostgroupForm')
	->addVar('form', $this->data['form']);
if (isset($this->data['groupid'])) {
	$hostGroupForm->addVar('groupid', $this->data['groupid']);
}

// create hostgroup form list
$hostGroupFormList = new CFormList('hostgroupFormList');
$nameTextBox = (new CTextBox('name', $this->data['name'],
	($this->data['groupid'] && $this->data['group']['flags'] == ZBX_FLAG_DISCOVERY_CREATED)
))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired();
$nameTextBox->setAttribute('autofocus', 'autofocus');
$hostGroupFormList->addRow((new CLabel(_('Group name'), 'name'))->setAsteriskMark(), $nameTextBox);

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
	if ($host['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$hostsComboBox->addItem($host['hostid'], $host['name']);
	}
	else {
		$hostsComboBox->addItem($host['hostid'], $host['name'], true, false);
	}
}
$hostGroupFormList->addRow(_('Hosts'), $hostsComboBox->get(_('Hosts in'), [_('Other hosts | Group').SPACE, $groupsComboBox]));

if ($data['groupid'] != 0 && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$hostGroupFormList->addRow(null,
		(new CCheckBox('subgroups'))
			->setLabel(_('Apply permissions and tag filters to all subgroups'))
			->setChecked($data['subgroups'])
	);
}

// append tabs to form
$hostGroupTab = new CTabView();
$hostGroupTab->addTab('hostgroupTab', _('Host group'), $hostGroupFormList);

// append buttons to form
if ($this->data['groupid'] == 0) {
	$hostGroupTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}
else {
	$clone_button = new CSubmit('clone', _('Clone'));
	if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
		$clone_button->setAttribute('disabled', 'disabled');
	}

	$delete_button = new CButtonDelete(_('Delete selected group?'), url_param('form').url_param('groupid'));
	if (!isset($this->data['deletableHostGroups'][$this->data['groupid']])) {
		$delete_button->setAttribute('disabled', 'disabled');
	}

	$hostGroupTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			$clone_button,
			$delete_button,
			new CButtonCancel()
		]
	));
}

$hostGroupForm->addItem($hostGroupTab);

$widget->addItem($hostGroupForm);

return $widget;
