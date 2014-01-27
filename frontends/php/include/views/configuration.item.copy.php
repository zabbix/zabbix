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


$itemWidget = new CWidget();

if (!empty($this->data['hostid'])) {
	$itemWidget->addItem(get_header_host_table('items', $this->data['hostid']));
}

$itemWidget->addPageHeader(_('CONFIGURATION OF ITEMS'));

// create form
$itemForm = new CForm();
$itemForm->setName('itemForm');
$itemForm->addVar('group_itemid', $this->data['group_itemid']);
$itemForm->addVar('hostid', $this->data['hostid']);
$itemForm->addVar('go', 'copy_to');

// create form list
$itemFormList = new CFormList('itemFormList');

// append type to form list
$copyTypeComboBox = new CComboBox('copy_type', $this->data['copy_type'], 'submit()');
$copyTypeComboBox->addItem(0, _('Hosts'));
$copyTypeComboBox->addItem(1, _('Host groups'));
$itemFormList->addRow(_('Target type'), $copyTypeComboBox);

// append targets to form list
$targetList = array();
if ($this->data['copy_type'] == 0) {
	$groupComboBox = new CComboBox('copy_groupid', $this->data['copy_groupid'], 'submit()');
	foreach ($this->data['groups'] as $group) {
		$groupComboBox->addItem($group['groupid'],$group['name']);
	}
	$itemFormList->addRow(_('Group'), $groupComboBox);

	foreach ($this->data['hosts'] as $host) {
		array_push($targetList, array(
			new CCheckBox('copy_targetid['.$host['hostid'].']', uint_in_array($host['hostid'], $this->data['copy_targetid']), null, $host['hostid']),
			SPACE,
			$host['name'],
			BR()
		));
	}
}
else {
	foreach ($this->data['groups'] as $group) {
		array_push($targetList, array(
			new CCheckBox('copy_targetid['.$group['groupid'].']', uint_in_array($group['groupid'], $this->data['copy_targetid']), null, $group['groupid']),
			SPACE,
			$group['name'],
			BR()
		));
	}
}
$itemFormList->addRow(_('Target'), !empty($targetList) ? $targetList : SPACE);

// append tabs to form
$itemTab = new CTabView();
$itemTab->addTab('itemTab', count($this->data['group_itemid']).' '._('elements copy to ...'), $itemFormList);
$itemForm->addItem($itemTab);

// append buttons to form
$itemForm->addItem(makeFormFooter(new CSubmit('copy', _('Copy')), new CButtonCancel(url_param('groupid').url_param('hostid').url_param('config'))));
$itemWidget->addItem($itemForm);

return $itemWidget;
