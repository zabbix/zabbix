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


require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';

// create form
$hostForm = new CForm();
$hostForm->setName('hostForm');
$hostForm->addVar('go', 'massupdate');
foreach ($this->data['hosts'] as $hostid) {
	$hostForm->addVar('hosts['.$hostid.']', $hostid);
}

// create form list
$hostFormList = new CFormList('hostFormList');

// replace host groups
$hostGroupsToReplace = null;
if (isset($_REQUEST['groups'])) {
	$getHostGroups = API::HostGroup()->get(array(
		'groupids' => $_REQUEST['groups'],
		'output' => array('groupid', 'name'),
		'editable' => true
	));
	foreach ($getHostGroups as $getHostGroup) {
		$hostGroupsToReplace[] = array(
			'id' => $getHostGroup['groupid'],
			'name' => $getHostGroup['name']
		);
	}
}

$replaceGroups = new CMultiSelect(array(
	'name' => 'groups[]',
	'objectName' => 'hostGroup',
	'objectOptions' => array('editable' => true),
	'data' => $hostGroupsToReplace
));

$hostFormList->addRow(
	array(
		_('Replace host groups'),
		SPACE,
		new CVisibilityBox('visible[groups]', isset($this->data['visible']['groups']), 'groups_', _('Original'))
	),
	$replaceGroups
);

// add new or existing host groups
$hostGroupsToAdd = null;
if (isset($_REQUEST['new_groups'])) {
	foreach ($_REQUEST['new_groups'] as $newHostGroup) {
		if (is_array($newHostGroup) && isset($newHostGroup['new'])) {
			$hostGroupsToAdd[] = array(
				'id' => $newHostGroup['new'],
				'name' => $newHostGroup['new'] . ' (new)',
				'isNew' => true
			);
		}
		else {
			$hostGroupIds[] = $newHostGroup;
		}
	}

	if (isset($hostGroupIds)) {
		$getHostGroups = API::HostGroup()->get(array(
			'groupids' => $hostGroupIds,
			'output' => array('groupid', 'name')
		));
		foreach ($getHostGroups as $getHostGroup) {
			$hostGroupsToAdd[] = array(
				'id' => $getHostGroup['groupid'],
				'name' => $getHostGroup['name']
			);
		}
	}
}
if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$newGroups = new CMultiSelect(array(
		'name' => 'new_groups[]',
		'objectName' => 'hostGroup',
		'objectOptions' => array('editable' => true),
		'data' => $hostGroupsToAdd,
		'addNew' => true
	));

	$hostFormList->addRow(
		array(
			_('Add new or existing host groups'),
			SPACE,
			new CVisibilityBox('visible[new_groups]', isset($this->data['visible']['new_groups']), 'new_groups_', _('Original'))
		),
		$newGroups
	);
}
else {
	$newGroups = new CMultiSelect(array(
		'name' => 'new_groups[]',
		'objectName' => 'hostGroup',
		'objectOptions' => array('editable' => true),
		'data' => $hostGroupsToAdd
	));

	$hostFormList->addRow(
		array(
			_('New host group'),
			SPACE,
			new CVisibilityBox('visible[new_groups]', isset($this->data['visible']['new_groups']), 'new_groups_', _('Original'))
		),
		$newGroups
	);
}

// append proxy to form list
$proxyComboBox = new CComboBox('proxy_hostid', $this->data['proxy_hostid']);
$proxyComboBox->addItem(0, _('(no proxy)'));
foreach ($this->data['proxies'] as $proxie) {
	$proxyComboBox->addItem($proxie['hostid'], $proxie['host']);
}
$hostFormList->addRow(
	array(
		_('Monitored by proxy'),
		SPACE,
		new CVisibilityBox('visible[proxy_hostid]', isset($this->data['visible']['proxy_hostid']), 'proxy_hostid', _('Original'))
	),
	$proxyComboBox
);

// append status to form list
$statusComboBox = new CComboBox('status', $this->data['status']);
$statusComboBox->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$statusComboBox->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));
$hostFormList->addRow(
	array(
		_('Status'),
		SPACE,
		new CVisibilityBox('visible[status]', isset($this->data['visible']['status']), 'status', _('Original'))
	),
	$statusComboBox
);

$templatesFormList = new CFormList('templatesFormList');
// append templates table to from list
$templatesTable = new CTable(null, 'formElementTable');
$templatesTable->setAttribute('style', 'min-width: 500px;');
$templatesTable->setAttribute('id', 'template_table');

$templatesDiv = new CDiv(
	array(
		$templatesTable,
		new CMultiSelect(array(
			'name' => 'templates[]',
			'objectName' => 'templates',
			'data' => $this->data['linkedTemplates']
		)),
		new CCheckBox('mass_replace_tpls', $this->data['mass_replace_tpls']),
		SPACE,
		_('Replace'),
		BR(),
		new CCheckBox('mass_clear_tpls', $this->data['mass_clear_tpls']),
		SPACE,
		_('Clear when unlinking')
	),
	'objectgroup inlineblock border_dotted ui-corner-all'
);
$templatesDiv->setAttribute('id', 'templates_div');

$templatesFormList->addRow(
	array(
		_('Link templates'),
		SPACE,
		new CVisibilityBox('visible[template_table]', !empty($this->data['visible']['template_table']) ? 'yes' : 'no', 'templates_div', _('Original'))
	),
	$templatesDiv
);

$ipmiFormList = new CFormList('ipmiFormList');
// append ipmi to form list
$ipmiAuthtypeComboBox = new CComboBox('ipmi_authtype', $this->data['ipmi_authtype']);
$ipmiAuthtypeComboBox->addItems(ipmiAuthTypes());
$ipmiFormList->addRow(
	array(
		_('IPMI authentication algorithm'),
		SPACE,
		new CVisibilityBox('visible[ipmi_authtype]', isset($this->data['visible']['ipmi_authtype']), 'ipmi_authtype', _('Original'))
	),
	$ipmiAuthtypeComboBox
);

$ipmiPrivilegeComboBox = new CComboBox('ipmi_privilege', $this->data['ipmi_privilege']);
$ipmiPrivilegeComboBox->addItems(ipmiPrivileges());
$ipmiFormList->addRow(
	array(
		_('IPMI privilege level'),
		SPACE,
		new CVisibilityBox('visible[ipmi_privilege]', isset($this->data['visible']['ipmi_privilege']), 'ipmi_privilege', _('Original'))
	),
	$ipmiPrivilegeComboBox
);

$ipmiFormList->addRow(
	array(
		_('IPMI username'),
		SPACE,
		new CVisibilityBox('visible[ipmi_username]', isset($this->data['visible']['ipmi_username']), 'ipmi_username', _('Original'))
	),
	new CTextBox('ipmi_username', $this->data['ipmi_username'], ZBX_TEXTBOX_SMALL_SIZE)
);

$ipmiFormList->addRow(
	array(
		_('IPMI password'),
		SPACE,
		new CVisibilityBox('visible[ipmi_password]', isset($this->data['visible']['ipmi_password']), 'ipmi_password', _('Original'))
	),
	new CTextBox('ipmi_password', $this->data['ipmi_password'], ZBX_TEXTBOX_SMALL_SIZE)
);

$inventoryFormList = new CFormList('inventoryFormList');
// append inventories to form list
$inventoryModesComboBox = new CComboBox('inventory_mode', $this->data['inventory_mode'], 'submit()');
$inventoryModesComboBox->addItem(HOST_INVENTORY_DISABLED, _('Disabled'));
$inventoryModesComboBox->addItem(HOST_INVENTORY_MANUAL, _('Manual'));
$inventoryModesComboBox->addItem(HOST_INVENTORY_AUTOMATIC, _('Automatic'));
$inventoryFormList->addRow(
	array(
		_('Inventory mode'),
		SPACE,
		new CVisibilityBox('visible[inventory_mode]', isset($this->data['visible']['inventory_mode']), 'inventory_mode', _('Original'))
	),
	$inventoryModesComboBox
);

$hostInventoryTable = DB::getSchema('host_inventory');
if ($this->data['inventory_mode'] != HOST_INVENTORY_DISABLED) {
	foreach ($this->data['inventories'] as $field => $fieldInfo) {
		if (!isset($this->data['host_inventory'][$field])) {
			$this->data['host_inventory'][$field] = '';
		}

		if ($hostInventoryTable['fields'][$field]['type'] == DB::FIELD_TYPE_TEXT) {
			$fieldInput = new CTextArea('host_inventory['.$field.']', $this->data['host_inventory'][$field]);
			$fieldInput->addStyle('width: 64em;');
		}
		else {
			$fieldLength = $hostInventoryTable['fields'][$field]['length'];
			$fieldInput = new CTextBox('host_inventory['.$field.']', $this->data['host_inventory'][$field]);
			$fieldInput->setAttribute('maxlength', $fieldLength);
			$fieldInput->addStyle('width: '.($fieldLength > 64 ? 64 : $fieldLength).'em;');
		}

		$inventoryFormList->addRow(
			array(
				$fieldInfo['title'],
				SPACE,
				new CVisibilityBox(
					'visible['.$field.']',
					isset($this->data['visible'][$field]),
					'host_inventory['.$field.']',
					_('Original')
				)
			),
			$fieldInput
		);
	}
}

// append tabs to form
$hostTab = new CTabView();
// reset the tab when opening the form for the first time
if (!hasRequest('masssave')) {
	$hostTab->setSelected(0);
}
$hostTab->addTab('hostTab', _('Host'), $hostFormList);
$hostTab->addTab('templatesTab', _('Templates'), $templatesFormList);
$hostTab->addTab('ipmiTab', _('IPMI'), $ipmiFormList);
$hostTab->addTab('inventoryTab', _('Inventory'), $inventoryFormList);
$hostForm->addItem($hostTab);

// append buttons to form
$hostForm->addItem(makeFormFooter(new CSubmit('masssave', _('Update')), new CButtonCancel(url_param('groupid'))));

return $hostForm;
