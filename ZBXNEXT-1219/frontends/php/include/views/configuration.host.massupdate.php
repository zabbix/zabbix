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


require_once dirname(__FILE__).'/js/configuration.host.massupdate.js.php';

$hostWidget = (new CWidget())->setTitle(_('Hosts'));

// create form
$hostView = (new CForm())
	->setName('hostForm')
	->addVar('action', 'host.massupdate');
foreach ($data['hosts'] as $hostid) {
	$hostView->addVar('hosts['.$hostid.']', $hostid);
}

// create form list
$hostFormList = new CFormList('hostFormList');

// replace host groups
$hostGroupsToReplace = null;
if (isset($_REQUEST['groups'])) {
	$getHostGroups = API::HostGroup()->get([
		'groupids' => $_REQUEST['groups'],
		'output' => ['groupid', 'name'],
		'editable' => true
	]);
	foreach ($getHostGroups as $getHostGroup) {
		$hostGroupsToReplace[] = [
			'id' => $getHostGroup['groupid'],
			'name' => $getHostGroup['name']
		];
	}
}

$replaceGroups = (new CDiv(
	(new CMultiSelect([
		'name' => 'groups[]',
		'objectName' => 'hostGroup',
		'objectOptions' => ['editable' => true],
		'data' => $hostGroupsToReplace,
		'popup' => [
			'parameters' => 'srctbl=host_groups&dstfrm='.$hostView->getName().'&dstfld1=groups_&srcfld1=groupid'.
				'&writeonly=1&multiselect=1'
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
))->setId('replaceGroups');

$hostFormList->addRow(
	[
		_('Replace host groups'),
		SPACE,
		(new CVisibilityBox('visible[groups]', 'replaceGroups', _('Original')))
			->setChecked(isset($data['visible']['groups']))
	],
	$replaceGroups
);

// add new or existing host groups
$hostGroupsToAdd = null;
if (isset($_REQUEST['new_groups'])) {
	foreach ($_REQUEST['new_groups'] as $newHostGroup) {
		if (is_array($newHostGroup) && isset($newHostGroup['new'])) {
			$hostGroupsToAdd[] = [
				'id' => $newHostGroup['new'],
				'name' => $newHostGroup['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		else {
			$hostGroupIds[] = $newHostGroup;
		}
	}

	if (isset($hostGroupIds)) {
		$getHostGroups = API::HostGroup()->get([
			'groupids' => $hostGroupIds,
			'output' => ['groupid', 'name']
		]);
		foreach ($getHostGroups as $getHostGroup) {
			$hostGroupsToAdd[] = [
				'id' => $getHostGroup['groupid'],
				'name' => $getHostGroup['name']
			];
		}
	}
}
if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
	$hostFormList->addRow(
		[
			_('Add new or existing host groups'),
			SPACE,
			(new CVisibilityBox('visible[new_groups]', 'newGroups', _('Original')))
				->setChecked(isset($data['visible']['new_groups']))
		],
		(new CDiv(
			(new CMultiSelect([
				'name' => 'new_groups[]',
				'objectName' => 'hostGroup',
				'objectOptions' => ['editable' => true],
				'data' => $hostGroupsToAdd,
				'addNew' => true,
				'popup' => [
					'parameters' => 'srctbl=host_groups&dstfrm='.$hostView->getName().'&dstfld1=new_groups_'.
						'&srcfld1=groupid&writeonly=1&multiselect=1'
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('newGroups')
	);
}
else {
	$hostFormList->addRow(
		[
			_('New host group'),
			SPACE,
			(new CVisibilityBox('visible[new_groups]', 'newGroups', _('Original')))
				->setChecked(isset($data['visible']['new_groups']))
		],
		(new CDiv(
			(new CMultiSelect([
				'name' => 'new_groups[]',
				'objectName' => 'hostGroup',
				'objectOptions' => ['editable' => true],
				'data' => $hostGroupsToAdd,
				'popup' => [
					'parameters' => 'srctbl=host_groups&dstfrm='.$hostView->getName().'&dstfld1=new_groups_'.
						'&srcfld1=groupid&writeonly=1&multiselect=1'
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->setId('newGroups')
	);
}

// append description to form list
$hostFormList->addRow(
	[
		_('Description'),
		SPACE,
		(new CVisibilityBox('visible[description]', 'description', _('Original')))
			->setChecked(isset($data['visible']['description']))
	],
	(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// append proxy to form list
$proxyComboBox = new CComboBox('proxy_hostid', $data['proxy_hostid']);
$proxyComboBox->addItem(0, _('(no proxy)'));
foreach ($data['proxies'] as $proxie) {
	$proxyComboBox->addItem($proxie['hostid'], $proxie['host']);
}
$hostFormList->addRow(
	[
		_('Monitored by proxy'),
		SPACE,
		(new CVisibilityBox('visible[proxy_hostid]', 'proxy_hostid', _('Original')))
			->setChecked(isset($data['visible']['proxy_hostid']))
	],
	$proxyComboBox
);

// append status to form list
$hostFormList->addRow(
	[
		_('Status'),
		SPACE,
		(new CVisibilityBox('visible[status]', 'status', _('Original')))
			->setChecked(isset($data['visible']['status']))
	],
	new CComboBox('status', $data['status'], null, [
		HOST_STATUS_MONITORED => _('Enabled'),
		HOST_STATUS_NOT_MONITORED => _('Disabled')
	])
);

$templatesFormList = new CFormList('templatesFormList');

// append templates table to from list
$templatesTable = (new CTable())
	->addClass('formElementTable')
	->setAttribute('style', 'min-width: 500px;')
	->setId('template_table');

$clearDiv = (new CDiv())->addStyle('clear: both;');

$templatesDiv = (new CDiv(
	[
		(new CMultiSelect([
			'name' => 'templates[]',
			'objectName' => 'templates',
			'data' => $data['linkedTemplates'],
			'popup' => [
				'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$hostView->getName().
					'&dstfld1=templates_&templated_hosts=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		$clearDiv,
		(new CDiv([
			(new CCheckBox('mass_replace_tpls'))->setChecked($data['mass_replace_tpls'] == 1),
			SPACE,
			_('Replace'),
			BR(),
			(new CCheckBox('mass_clear_tpls'))->setChecked($data['mass_clear_tpls'] == 1),
			SPACE,
			_('Clear when unlinking')
		]))->addClass('floatleft')
	]
))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setId('templateDiv');

$templatesFormList->addRow(
	[
		_('Link templates'),
		SPACE,
		(new CVisibilityBox('visible[templates]', 'templateDiv', _('Original')))
			->setChecked(isset($data['visible']['templates']))
	],
	$templatesDiv
);

$ipmiFormList = new CFormList('ipmiFormList');

// append ipmi to form list
$ipmiFormList->addRow(
	[
		_('IPMI authentication algorithm'),
		SPACE,
		(new CVisibilityBox('visible[ipmi_authtype]', 'ipmi_authtype', _('Original')))
			->setChecked(isset($data['visible']['ipmi_authtype']))
	],
	new CComboBox('ipmi_authtype', $data['ipmi_authtype'], null, ipmiAuthTypes())
);

$ipmiFormList->addRow(
	[
		_('IPMI privilege level'),
		SPACE,
		(new CVisibilityBox('visible[ipmi_privilege]', 'ipmi_privilege', _('Original')))
			->setChecked(isset($data['visible']['ipmi_privilege']))
	],
	new CComboBox('ipmi_privilege', $data['ipmi_privilege'], null, ipmiPrivileges())
);

$ipmiFormList->addRow(
	[
		_('IPMI username'),
		SPACE,
		(new CVisibilityBox('visible[ipmi_username]', 'ipmi_username', _('Original')))
			->setChecked(isset($data['visible']['ipmi_username']))
	],
	(new CTextBox('ipmi_username', $data['ipmi_username']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

$ipmiFormList->addRow(
	[
		_('IPMI password'),
		SPACE,
		(new CVisibilityBox('visible[ipmi_password]', 'ipmi_password', _('Original')))
			->setChecked(isset($data['visible']['ipmi_password']))
	],
	(new CTextBox('ipmi_password', $data['ipmi_password']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
);

$inventoryFormList = new CFormList('inventoryFormList');

// append inventories to form list
$inventoryFormList->addRow(
	[
		_('Inventory mode'),
		SPACE,
		(new CVisibilityBox('visible[inventory_mode]', 'inventory_mode', _('Original')))
			->setChecked(isset($data['visible']['inventory_mode']))
	],
	new CComboBox('inventory_mode', $data['inventory_mode'], null, [
		HOST_INVENTORY_DISABLED => _('Disabled'),
		HOST_INVENTORY_MANUAL => _('Manual'),
		HOST_INVENTORY_AUTOMATIC => _('Automatic')
	])
);

$hostInventoryTable = DB::getSchema('host_inventory');
foreach ($data['inventories'] as $field => $fieldInfo) {
	if (!isset($data['host_inventory'][$field])) {
		$data['host_inventory'][$field] = '';
	}

	if ($hostInventoryTable['fields'][$field]['type'] == DB::FIELD_TYPE_TEXT) {
		$fieldInput = (new CTextArea('host_inventory['.$field.']', $data['host_inventory'][$field]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH);
	}
	else {
		$field_length = $hostInventoryTable['fields'][$field]['length'];

		if ($field_length < 39) {
			$width = ZBX_TEXTAREA_SMALL_WIDTH;
		}
		elseif ($field_length < 64) {
			$width = ZBX_TEXTAREA_STANDARD_WIDTH;
		}
		else {
			$width = ZBX_TEXTAREA_BIG_WIDTH;
		}

		$fieldInput = (new CTextBox('host_inventory['.$field.']', $data['host_inventory'][$field]))
			->setWidth($width)
			->setAttribute('maxlength', $field_length);
	}

	$inventoryFormList->addRow(
		[
			$fieldInfo['title'],
			SPACE,
			(new CVisibilityBox(
				'visible['.$field.']',
				'host_inventory['.$field.']',
				_('Original')
			))->setChecked(isset($data['visible'][$field]))
		],
		$fieldInput, false, null, 'formrow-inventory'
	);
}

// append tabs to form
$hostTab = (new CTabView())
	->addTab('hostTab', _('Host'), $hostFormList)
	->addTab('templatesTab', _('Templates'), $templatesFormList)
	->addTab('ipmiTab', _('IPMI'), $ipmiFormList)
	->addTab('inventoryTab', _('Inventory'), $inventoryFormList);
// reset the tab when opening the form for the first time
if (!hasRequest('masssave') && !hasRequest('inventory_mode')) {
	$hostTab->setSelected(0);
}

// append buttons to form
$hostTab->setFooter(makeFormFooter(
	new CSubmit('masssave', _('Update')),
	[new CButtonCancel(url_param('groupid'))]
));

$hostView->addItem($hostTab);

$hostWidget->addItem($hostView);

return $hostWidget;
