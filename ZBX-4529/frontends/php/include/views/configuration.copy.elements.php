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
?>
<?php
$triggersWidget = new CWidget();

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	$hostTableElement = ($this->data['elements_field'] == 'group_graphid') ? 'graphs' : 'trigger';
	$triggersWidget->addItem(get_header_host_table($hostTableElement, $this->data['hostid']));
}

if (!empty($this->data['title'])) {
	$triggersWidget->addPageHeader($this->data['title']);
}

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar($this->data['elements_field'], $this->data['elements']);
$triggersForm->addVar('hostid', $this->data['hostid']);
$triggersForm->addVar('go', 'copy_to');

// create form list
$triggersFormList = new CFormList('triggersFormList');

// append copy types to form list
$copyTypeComboBox = new CComboBox('copy_type', $this->data['copy_type'], 'submit()');
$copyTypeComboBox->addItem(0, _('Hosts'));
$copyTypeComboBox->addItem(1, _('Host groups'));
$triggersFormList->addRow(_('Target type'), $copyTypeComboBox);

// append groups to form list
if ($this->data['copy_type'] == 0) {
	$groupComboBox = new CComboBox('filter_groupid', $this->data['filter_groupid'], 'submit()');
	foreach ($this->data['groups'] as $group) {
		if (empty($this->data['filter_groupid'])) {
			$this->data['filter_groupid'] = $group['groupid'];
		}
		$groupComboBox->addItem($group['groupid'], $group['name']);
	}
	$triggersFormList->addRow(_('Group'), $groupComboBox);
}

// append targets to form list
$targets = array();
if ($this->data['copy_type'] == 0) {
	foreach ($this->data['hosts'] as $host) {
		array_push(
			$targets,
			array(
				new CCheckBox('copy_targetid['.$host['hostid'].']', uint_in_array($host['hostid'], $this->data['copy_targetid']), null, $host['hostid']),
				SPACE,
				$host['name'],
				BR()
			)
		);
	}
}
else {
	foreach ($this->data['groups'] as $group) {
		array_push(
			$targets,
			array(
				new CCheckBox('copy_targetid['.$group['groupid'].']', uint_in_array($group['groupid'], $this->data['copy_targetid']), null, $group['groupid']),
				SPACE,
				$group['name'],
				BR()
			)
		);
	}
}
if (empty($targets)) {
	array_push($targets, BR());
}
$triggersFormList->addRow(_('Target'), $targets);

// append tabs to form
$triggersTab = new CTabView();
$triggersTab->addTab('triggersTab', count($this->data['elements']).SPACE._('elements copy to ...'), $triggersFormList);
$triggersForm->addItem($triggersTab);

// append buttons to form
$triggersForm->addItem(makeFormFooter(
	array(new CSubmit('copy', _('Copy'))),
	array(new CButtonCancel(url_param('groupid').url_param('hostid').url_param('config')))
));

$triggersWidget->addItem($triggersForm);
return $triggersWidget;
?>
