<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


global $ZBX_NODES, $ZBX_LOCMASTERID;

$nodeWidget = new CWidget();
$nodeWidget->addPageHeader(_('CONFIGURATION OF NODES'));

// create form
$nodeForm = new CForm();
$nodeForm->addVar('form', 1);
if (!empty($this->data['nodeid'])) {
	$nodeForm->addVar('nodeid', $this->data['nodeid']);
}

// create form list
$nodeFormList = new CFormList('nodeFormList');

// Name
$nodeFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));

// ID
$nodeFormList->addRow(_('ID'), new CNumericBox('new_nodeid', $this->data['new_nodeid'], 10, isset($this->data['nodeid'])));

// Type
if (empty($this->data['masterNode']) && empty($this->data['nodeid'])) {
	$nodeTypeField = new CComboBox('nodetype', $this->data['nodetype'], 'submit()');
	$nodeTypeField->addItem(ZBX_NODE_CHILD, node_type2str(ZBX_NODE_CHILD));
	$nodeTypeField->addItem(ZBX_NODE_MASTER, node_type2str(ZBX_NODE_MASTER));
}
else {
	$nodeTypeField = node_type2str($this->data['nodetype']);
	$nodeForm->addVar('nodetype', $this->data['nodetype']);
}
$nodeFormList->addRow(_('Type'), $nodeTypeField);

// Master node
if ($this->data['masterid'] != 0 && $this->data['nodetype'] != ZBX_NODE_MASTER) {
	if (empty($this->data['nodeid'])) {
		$masterField = new CComboBox('masterid', $this->data['masterid']);
		foreach ($ZBX_NODES as $node) {
			if ($node['nodeid'] == $ZBX_LOCMASTERID) {
				continue;
			}
			$masterField->addItem($node['nodeid'], $node['name']);
		}
	}
	else {
		$masterField = $ZBX_NODES[$this->data['masterid']]['name'];
		$nodeForm->addVar('masterid', $this->data['masterid']);
	}

	$nodeFormList->addRow(_('Master node'), $masterField);
}

// IP
$nodeFormList->addRow(_('IP'), new CTextBox('ip', $this->data['ip'], ZBX_TEXTBOX_SMALL_SIZE));

// Port
$nodeFormList->addRow(_('Port'), new CNumericBox('port', $this->data['port'], 5));


// append tabs to form
$nodeTab = new CTabView();
$nodeTab->addTab('nodeTab', _('Node'), $nodeFormList);
$nodeForm->addItem($nodeTab);

// append buttons to form
$secButtons = array(new CButtonCancel());
if (isset($this->data['nodeid']) && $this->data['nodetype'] != ZBX_NODE_LOCAL) {
	array_unshift($secButtons, new CButtonDelete(_('Delete selected node?'), url_param('form').url_param('nodeid')));
}
$nodeForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), $secButtons));

// append form to widget
$nodeWidget->addItem($nodeForm);

return $nodeWidget;
