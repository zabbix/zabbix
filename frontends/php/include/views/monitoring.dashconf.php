<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$widget = (new CWidget())->setTitle(_('Dashboard'));

// create form
$form = (new CForm())
	->setName('dashconf')
	->setId('dashform')
	->addVar('filterEnable', $this->data['isFilterEnable']);

// create form list
$form_list = new CFormList('dashconfFormList');

// append filter status to form list
if ($this->data['isFilterEnable']) {
	$filterStatusSpan = (new CSpan(_('Enabled')))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(ZBX_STYLE_GREEN)
		->onClick("create_var('".$form->getName()."', 'filterEnable', 0, true);")
		->setAttribute('tabindex', 0);
}
else {
	$filterStatusSpan = (new CSpan(_('Disabled')))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(ZBX_STYLE_RED)
		->onClick("$('dashform').enable(); create_var('".$form->getName()."', 'filterEnable', 1, true);")
		->setAttribute('tabindex', 0);
}
$form_list->addRow(_('Dashboard filter'), $filterStatusSpan);

// append host groups to form list
$hostGroupsComboBox = new CComboBox('grpswitch', $this->data['grpswitch'], 'submit()', [
	0 => _('All'),
	1 => _('Selected')
]);
if (!$this->data['isFilterEnable']) {
	$hostGroupsComboBox->setAttribute('disabled', 'disabled');
}
$form_list->addRow(_('Host groups'), $hostGroupsComboBox);

if ($this->data['grpswitch']) {
	$form_list->addRow(_('Show selected groups'), (new CMultiSelect([
		'name' => 'groupids[]',
		'objectName' => 'hostGroup',
		'data' => $this->data['groups'],
		'disabled' => !$this->data['isFilterEnable'],
		'popup' => [
			'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1=groupids_'.
				'&srcfld1=groupid&multiselect=1'
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
	$form_list->addRow(_('Hide selected groups'), (new CMultiSelect([
		'name' => 'hidegroupids[]',
		'objectName' => 'hostGroup',
		'data' => $this->data['hideGroups'],
		'disabled' => !$this->data['isFilterEnable'],
		'popup' => [
			'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1=hidegroupids_'.
				'&srcfld1=groupid&multiselect=1'
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
}

// append host in maintenance checkbox to form list
$maintenanceCheckBox = (new CCheckBox('maintenance'))->setChecked($this->data['maintenance'] == 1);
if (!$this->data['isFilterEnable']) {
	$maintenanceCheckBox->setAttribute('disabled', 'disabled');
}
$form_list->addRow(_('Hosts'),
	new CLabel([$maintenanceCheckBox, _('Show hosts in maintenance')], 'maintenance')
);

// append trigger severities to form list
$severities = [];
foreach ($this->data['severities'] as $severity) {
	$serverityCheckBox = (new CCheckBox('trgSeverity['.$severity.']'))
		->setChecked(isset($this->data['severity'][$severity]))
		->setEnabled($this->data['isFilterEnable']);
	$severities[] = new CLabel([$serverityCheckBox, getSeverityName($severity, $this->data['config'])],
		'trgSeverity['.$severity.']'
	);
	$severities[] = BR();
}
array_pop($severities);

$form_list->addRow(_('Triggers with severity'), $severities);

$form_list->addRow(_('Trigger name'),
	(new CTextBox('trigger_name', $data['trigger_name']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setEnabled($data['isFilterEnable'])
);

if ($data['config']['event_ack_enable']) {
	$form_list->addRow(_('Problem display'),
		(new CRadioButtonList('extAck', (int) $data['extAck']))
			->addValue(_('All'), EXTACK_OPTION_ALL)
			->addValue(_('Separated'), EXTACK_OPTION_BOTH)
			->addValue(_('Unacknowledged only'), EXTACK_OPTION_UNACK)
			->setEnabled($data['isFilterEnable'])
			->setModern(true)
	);
}

// create tab
$tab = new CTabView();
$tab->addTab('dashconfTab', _('Filter'), $form_list);

$tab->setFooter(makeFormFooter(
	new CSubmit('update', _('Update')),
	[new CButtonCancel()]
));

$form->addItem($tab);
$widget->addItem($form);

return $widget;
