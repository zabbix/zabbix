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
?>
<?php
global $ZBX_NODES, $ZBX_LOCMASTERID;

$nodeWidget = new CWidget();
$nodeWidget->addPageHeader(_('CONFIGURATION OF NODES'));

// create form
$nodeForm = new CForm();
$nodeForm->setName('nodeForm');
$nodeForm->addVar('form', $this->data['form']);
$nodeForm->addVar('form_refresh', $this->data['form_refresh']);
if (!empty($this->data['nodeid'])) {
	$nodeForm->addVar('nodeid', $this->data['nodeid']);
}

// create form list
$nodeFormList = new CFormList('nodeFormList');
$nodeFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
$nodeFormList->addRow(_('ID'), new CNumericBox('new_nodeid', $this->data['new_nodeid'], 10, isset($_REQUEST['nodeid']) ? 'yes' : 'no'));

// append nodetype to form list, type always will be child!
$nodeTypeComboBox = new CComboBox('nodetype', $this->data['nodetype'], 'submit()');
$nodeTypeComboBox->addItem(ZBX_NODE_CHILD, _('Child'));
$nodeTypeComboBox->addItem(ZBX_NODE_MASTER, _('Master'));
if ($this->data['nodetype'] == ZBX_NODE_LOCAL) {
	$nodeTypeComboBox->addItem(ZBX_NODE_LOCAL, _('Local'));
}
if (!empty($this->data['masterNode']) || !empty($this->data['nodeid'])) {
	$nodeTypeComboBox->setEnabled('disabled');
	$nodeForm->addVar('nodetype', $this->data['nodetype']);
}
$nodeFormList->addRow(_('Type'), $nodeTypeComboBox);

// append master node to form list
if ($this->data['nodetype'] != ZBX_NODE_MASTER) {
	$masterComboBox = new CComboBox('masterid', $this->data['masterid']);
	foreach ($ZBX_NODES as $node) {
		if ($node['nodeid'] == $ZBX_LOCMASTERID) {
			continue;
		}
		$masterComboBox->addItem($node['nodeid'], $node['name']);
	}
	if (!empty($this->data['nodeid'])) {
		$masterComboBox->setEnabled('disabled');
		$nodeForm->addVar('masterid', $this->data['masterid']);
	}
	$nodeFormList->addRow(_('Master node'), $masterComboBox);
}
$nodeFormList->addRow(_('IP'), new CTextBox('ip', $this->data['ip'], ZBX_TEXTBOX_SMALL_SIZE));
$nodeFormList->addRow(_('Port'), new CNumericBox('port', $this->data['port'], 5));

// append tabs to form
$nodeTab = new CTabView();
$nodeTab->addTab('nodeTab', _('Node'), $nodeFormList);
$nodeForm->addItem($nodeTab);

// append buttons to form
if (isset($_REQUEST['nodeid']) && $this->data['nodetype'] != ZBX_NODE_LOCAL) {
	$nodeForm->addItem(makeFormFooter(
		new CSubmit('save', _('Save')),
		array(
			new CButtonDelete(_('Delete selected node?'), url_param('form').url_param('nodeid')),
			new CButtonCancel()
		)
	));
}
else {
	$nodeForm->addItem(makeFormFooter(new CSubmit('save', _('Save')), new CButtonCancel()));
}

// append form to widget
$nodeWidget->addItem($nodeForm);
return $nodeWidget;
?>
