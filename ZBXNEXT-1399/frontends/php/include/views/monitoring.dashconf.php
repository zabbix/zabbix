<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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


$dashconfWidget = new CWidget();
$dashconfWidget->setClass('header');
$dashconfWidget->addPageHeader(_('DASHBOARD CONFIGURATION'));

// create form
$dashconfForm = new CForm();
$dashconfForm->setName('dashconf');
$dashconfForm->setAttribute('id', 'dashform');
$dashconfForm->addVar('filterEnable', $this->data['isFilterEnable']);

// create form list
$dashconfFormList = new CFormList('dashconfFormList');

// append filter status to form list
if ($this->data['isFilterEnable']) {
	$filterStatusSpan = new CSpan(_('Enabled'), 'green underline pointer');
	$filterStatusSpan->setAttribute('onclick', "create_var('".$dashconfForm->getName()."', 'filterEnable', 0, true);");
}
else {
	$filterStatusSpan = new CSpan(_('Disabled'), 'red underline pointer');
	$filterStatusSpan->setAttribute('onclick', "$('dashform').enable(); create_var('".$dashconfForm->getName()."', 'filterEnable', 1, true);");
}
$dashconfFormList->addRow(_('Dashboard filter'), $filterStatusSpan);

// append host groups to form list
$hostGroupsComboBox = new CComboBox('grpswitch', $this->data['grpswitch'], 'submit();');
$hostGroupsComboBox->addItem(0, _('All'));
$hostGroupsComboBox->addItem(1, _('Selected'));
if (!$this->data['isFilterEnable']) {
	$hostGroupsComboBox->setAttribute('disabled', 'disabled');
}
$dashconfFormList->addRow(_('Host groups'), $hostGroupsComboBox);

if (!empty($this->data['grpswitch'])) {
	// show groups
	$groupListBox = new CListBox('groupids[]');
	$groupListBox->makeModern(array('objectName' => 'hostGroup'));

	if (!$this->data['isFilterEnable']) {
		$groupListBox->setAttribute('disabled', 'disabled');
	}

	foreach ($this->data['groups'] as $group) {
		$groupListBox->addItem($group['groupid'], $group['nodename'].$group['name'], true);
	}

	$dashconfFormList->addRow(_('Show selected groups'), $groupListBox);

	// hide groups
	$hideGroupListBox = new CListBox('hgroupids[]');
	$hideGroupListBox->makeModern(array('objectName' => 'hostGroup'));

	if (!$this->data['isFilterEnable']) {
		$hideGroupListBox->setAttribute('disabled', 'disabled');
	}

	foreach ($this->data['hgroups'] as $hgroup) {
		$hideGroupListBox->addItem($hgroup['groupid'], $hgroup['nodename'].$hgroup['name'], true);
	}

	$dashconfFormList->addRow(_('Hide selected groups'), $hideGroupListBox);
}

// append host in maintenance checkbox to form list
$maintenanceCheckBox = new CCheckBox('maintenance', $this->data['maintenance'], null, '1');
if (!$this->data['isFilterEnable']) {
	$maintenanceCheckBox->setAttribute('disabled', 'disabled');
}
$dashconfFormList->addRow(_('Hosts'), array($maintenanceCheckBox, _('Show hosts in maintenance')));

// append trigger severities to form list
$severities = array();
foreach ($this->data['severities'] as $severity) {
	$serverityCheckBox = new CCheckBox('trgSeverity['.$severity.']', isset($this->data['severity'][$severity]), '', 1);
	$serverityCheckBox->setEnabled($this->data['isFilterEnable']);
	$severities[] = array($serverityCheckBox, getSeverityCaption($severity));
	$severities[] = BR();
}
array_pop($severities);

$dashconfFormList->addRow(_('Triggers with severity'), $severities);

// append problem display to form list
$extAckComboBox = new CComboBox('extAck', $this->data['extAck']);
$extAckComboBox->addItems(array(
	EXTACK_OPTION_ALL => _('All'),
	EXTACK_OPTION_BOTH => _('Separated'),
	EXTACK_OPTION_UNACK => _('Unacknowledged only')
));
$extAckComboBox->setEnabled($this->data['isFilterEnable'] && $this->data['config']['event_ack_enable']);
if (!$this->data['config']['event_ack_enable']) {
	$extAckComboBox->setAttribute('title', _('Event acknowledging disabled'));
}
$dashconfFormList->addRow(_('Problem display'), $extAckComboBox);

// create tab
$dashconfTab = new CTabView();
$dashconfTab->addTab('dashconfTab', _('Filter'), $dashconfFormList);

$dashconfForm->addItem($dashconfTab);
$dashconfForm->addItem(makeFormFooter(new CSubmit('save', _('Save'))));

$dashconfWidget->addItem($dashconfForm);

return $dashconfWidget;
