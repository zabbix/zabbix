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


$triggersWidget = new CWidget();

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$hostTableElement = ($this->data['elements_field'] == 'group_graphid') ? 'graphs' : 'trigger';
	$triggersWidget->addItem(get_header_host_table($hostTableElement, $this->data['hostid']));
}

if (!empty($this->data['title'])) {
	$triggersWidget->setTitle($this->data['title']);
}

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar($this->data['elements_field'], $this->data['elements'])
	->addVar('hostid', $this->data['hostid'])
	->addVar('action', $this->data['action']);

// create form list
$triggersFormList = new CFormList('triggersFormList');

// append copy types to form list

$triggersFormList->addRow(_('Target type'), new CComboBox('copy_type', $this->data['copy_type'], 'submit()', [
	COPY_TYPE_TO_HOST => _('Hosts'),
	COPY_TYPE_TO_TEMPLATE => _('Templates'),
	COPY_TYPE_TO_HOST_GROUP => _('Host groups')
]));

// append groups to form list
if ($this->data['copy_type'] == COPY_TYPE_TO_HOST || $this->data['copy_type'] == COPY_TYPE_TO_TEMPLATE) {
	$groupComboBox = new CComboBox('copy_groupid', $this->data['copy_groupid'], 'submit()');
	foreach ($this->data['groups'] as $group) {
		if (empty($this->data['copy_groupid'])) {
			$this->data['copy_groupid'] = $group['groupid'];
		}
		$groupComboBox->addItem($group['groupid'], $group['name']);
	}
	$triggersFormList->addRow(_('Group'), $groupComboBox);
}

// append targets to form list
$targets = [];
if ($this->data['copy_type'] == COPY_TYPE_TO_HOST) {
	foreach ($this->data['hosts'] as $host) {
		$targets[] = new CLabel([
			(new CCheckBox('copy_targetid['.$host['hostid'].']', $host['hostid']))
				->setChecked(uint_in_array($host['hostid'], $this->data['copy_targetid'])), $host['name']],
			'copy_targetid['.$host['hostid'].']'
		);
		$targets[] = BR();
	}
} elseif ($this->data['copy_type'] == COPY_TYPE_TO_TEMPLATE) {
	foreach ($this->data['templates'] as $template) {
		$targets[] = new CLabel([
			(new CCheckBox('copy_targetid['.$template['templateid'].']', $template['templateid']))
				->setChecked(uint_in_array($template['templateid'], $this->data['copy_targetid'])), $template['name']],
			'copy_targetid['.$template['templateid'].']'
		);
		$targets[] = BR();
	}
} else {
	foreach ($this->data['groups'] as $group) {
		$targets[] = new CLabel([
			(new CCheckBox('copy_targetid['.$group['groupid'].']', $group['groupid']))
				->setChecked(uint_in_array($group['groupid'], $this->data['copy_targetid'])), $group['name']],
			'copy_targetid['.$group['groupid'].']'
		);
		$targets[] = BR();
	}
}
if ($targets) {
	array_pop($targets);
}
$triggersFormList->addRow(_('Target'), $targets);

// append tabs to form
$triggersTab = (new CTabView())
	->addTab('triggersTab',
		_n('Copy %1$s element to...', 'Copy %1$s elements to...', count($this->data['elements'])),
		$triggersFormList
	);

// append buttons to form
$triggersTab->setFooter(makeFormFooter(
	new CSubmit('copy', _('Copy')),
	[new CButtonCancel(url_param('groupid').url_param('hostid'))]
));

$triggersForm->addItem($triggersTab);
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
