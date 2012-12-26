<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
$dashconfForm->addVar('form_refresh', $this->data['form_refresh']);
$dashconfForm->addVar('filterEnable', $this->data['isFilterEnable']);
$dashconfForm->addVar('groupids', $this->data['groupIds']);

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
$groupsComboBox = new CComboBox('grpswitch', $this->data['grpswitch'], 'submit();');
$groupsComboBox->addItem(0, _('All'));
$groupsComboBox->addItem(1, _('Selected'));
if (!$this->data['isFilterEnable']) {
	$groupsComboBox->setAttribute('disabled', 'disabled');
}
$dashconfFormList->addRow(_('Host groups'), $groupsComboBox);

if (!empty($this->data['grpswitch'])) {
	$groupListBox = new CListBox('del_groups[]');
	$groupListBox->makeModern();

	// test data
	/*for ($i = 0; $i < 30; $i++) {
		$groupListBox->addItem($i, 'lajksdhlfkjashldf='.$i);
	}*/

	if (!$this->data['isFilterEnable']) {
		$groupListBox->setAttribute('disabled', 'disabled');
	}

	foreach ($this->data['hostGroups'] as $hostGroup) {
		$groupListBox->addItem($hostGroup['groupid'], $hostGroup['nodename'].$hostGroup['name']);
	}

	$dashconfFormList->addRow(_('Groups'), $groupListBox);
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
