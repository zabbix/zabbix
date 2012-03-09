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
if (isset($_REQUEST['nodeid'])) {
	$nodeForm->addVar('nodeid', $this->data['nodeid']);
}

// create form list
$nodeFormList = new CFormList('nodeFormList');
$nodeFormList->addRow(_('Name'), new CTextBox('name', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE));
$nodeFormList->addRow(_('ID'), new CNumericBox('new_nodeid', $this->data['new_nodeid'], 10, isset($_REQUEST['nodeid']) ? 'yes' : 'no'));

if (isset($_REQUEST['nodeid'])) {
	$nodeFormList->addRow(_('Name'), new CTextBox('node_type_name', node_type2str($this->data['node_type']), ZBX_TEXTBOX_STANDARD_SIZE, 'yes'));
}
else {
	$nodeTypeComboBox = new CComboBox('node_type', $this->data['node_type'], 'submit()');
	$nodeTypeComboBox->addItem(ZBX_NODE_CHILD, _('Child'));
	if (!$this->data['has_master']) {
		$nodeTypeComboBox->addItem(ZBX_NODE_MASTER, _('Master'));
	}
	$nodeFormList->addRow(_('Type'), $nodeTypeComboBox);
}

if ($this->data['node_type'] == ZBX_NODE_CHILD) {
	if (isset($_REQUEST['nodeid'])) {
		$nodeFormList->addRow(_('Name'), new CTextBox('master_name', $ZBX_NODES[$ZBX_NODES[$this->data['nodeid']]['masterid']]['name'], ZBX_TEXTBOX_STANDARD_SIZE, 'yes'));
	}
	else {
		$masterComboBox = new CComboBox('masterid', $this->data['masterid']);
		foreach ($ZBX_NODES as $node) {
			if ($node['nodeid'] == $ZBX_LOCMASTERID) {
				continue;
			}
			$masterComboBox->addItem($node['nodeid'], $node['name']);
		}
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
if (isset($_REQUEST['nodeid']) && $this->data['node_type'] != ZBX_NODE_LOCAL) {
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
