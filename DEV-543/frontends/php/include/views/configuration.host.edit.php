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

$host = $this->data['host'];

$isDiscovered = $host['hostid'] && isset($host['flags']) && $host['flags'] == ZBX_FLAG_DISCOVERY_CREATED;

$divTabs = new CTabView();

$frmHost = new CForm();
$frmHost->setName('web.hosts.host.php.');
$frmHost->addVar('form', $this->data['form']);

$frmHost->addVar('clear_templates', $this->data['clear_templates']);

$hostList = new CFormList('hostlist');

if ($host['hostid'] && $this->data['form'] != 'clone') {
	$frmHost->addVar('hostid', $host['hostid']);
}

if ($this->data['groupid']) {
	$frmHost->addVar('groupid', $this->data['groupid']);
}

// LLD rule link
if ($isDiscovered) {
	$hostList->addRow(_('Discovered by'), new CLink($host['discoveryRule']['name'],
		'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid'],
		'highlight underline weight_normal'
	));
}

$hostTB = new CTextBox('host', $host['host'], ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered, 128);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$visibleNameTB = new CTextBox('visiblename', ($host['name'] !== $host['host']) ? $host['name'] : '',
		ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered, 128
);
$hostList->addRow(_('Visible name'), $visibleNameTB);

// groups for discovered hosts
if ($isDiscovered) {
	$groupBox = new CComboBox('groups');
	$groupBox->setAttribute('readonly', true);
	$groupBox->setAttribute('size', 10);
	foreach ($host['groups'] as $group) {
		$groupBox->addItem($group['groupid'], $group['name']);
	}
	$hostList->addRow(_('Groups'), $groupBox);
}
// groups for normal hosts
else {
	$groupTwB = new CTweenBox($frmHost, 'groups', $host['groups'], 10);
	foreach ($this->data['allGroups'] as $group) {
		$groupTwB->addItem($group['groupid'], $group['name']);
	}

	$hostList->addRow(_('Groups'), $groupTwB->get(_('In groups'), _('Other groups')));

	$newGroupTB = new CTextBox('newgroup', $this->data['newgroup'], ZBX_TEXTBOX_SMALL_SIZE);
	$newGroupTB->setAttribute('maxlength', 64);
	$label = _('New group');
	if ($this->data['currentUserType'] != USER_TYPE_SUPER_ADMIN) {
		$label .= SPACE._('(Only super admins can create groups)');
		$newGroupTB->setReadonly(true);
	}
	$hostList->addRow(SPACE, array(new CLabel($label, 'newgroup'), BR(), $newGroupTB), null, null, 'new');
}

// interfaces for normal hosts
if (!$isDiscovered) {
	if ($host['interfaces']) {
		$script = 'hostInterfacesManager.add('.CJs::encodeJson(array_values($host['interfaces'])).');';
	}
	else {
		$script = 'hostInterfacesManager.addNew("agent");';
	}
	zbx_add_post_js($script);

	// table for agent interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'agentInterfaces');
	$ifTab->setAttribute('data-type', 'agent');

	// headers with sizes
	$iconLabel = new CCol(SPACE, 'interface-drag-control');
	$ipLabel = new CCol(_('IP address'), 'interface-ip');
	$dnsLabel = new CCol(_('DNS name'), 'interface-dns');
	$connectToLabel = new CCol(_('Connect to'), 'interface-connect-to');
	$portLabel = new CCol(_('Port'), 'interface-port');
	$defaultLabel = new CCol(_('Default'), 'interface-default');
	$removeLabel = new CCol(SPACE, 'interface-control');

	$ifTab->addRow(array($iconLabel, $ipLabel, $dnsLabel, $connectToLabel, $portLabel, $defaultLabel, $removeLabel));

	$helpTextWhenDragInterfaceAgent = new CSpan(_('Drag here to change the type of the interface to "agent" type.'));
	$helpTextWhenDragInterfaceAgent->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addAgentInterface', _('Add'), null, 'link_menu'), 'interface-add-control');
	$col = new CCol($helpTextWhenDragInterfaceAgent);
	$col->setAttribute('colspan', 6);

	$buttonRow = new CRow(array($buttonCol, $col));
	$buttonRow->setAttribute('id', 'agentIterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('Agent interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'),
		false, null, 'interface-row interface-row-first'
	);

	// table for SNMP interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'SNMPInterfaces');
	$ifTab->setAttribute('data-type', 'snmp');

	$helpTextWhenDragInterfaceSNMP = new CSpan(_('Drag here to change the type of the interface to "SNMP" type.'));
	$helpTextWhenDragInterfaceSNMP->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addSNMPInterface', _('Add'), null, 'link_menu'), 'interface-add-control');
	$col = new CCol($helpTextWhenDragInterfaceSNMP);
	$col->setAttribute('colspan', 6);

	$buttonRow = new CRow(array($buttonCol, $col));
	$buttonRow->setAttribute('id', 'SNMPIterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted inlineblock objectgroup interface-group'),
		false, null, 'interface-row'
	);

	// table for JMX interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'JMXInterfaces');
	$ifTab->setAttribute('data-type', 'jmx');
	$helpTextWhenDragInterfaceJMX = new CSpan(_('Drag here to change the type of the interface to "JMX" type.'));
	$helpTextWhenDragInterfaceJMX->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addJMXInterface', _('Add'), null, 'link_menu'), 'interface-add-control');
	$col = new CCol($helpTextWhenDragInterfaceJMX);
	$col->setAttribute('colspan', 6);

	$buttonRow = new CRow(array($buttonCol, $col));
	$buttonRow->setAttribute('id', 'JMXIterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('JMX interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'),
		false, null, 'interface-row'
	);

	// table for IPMI interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'IPMIInterfaces');
	$ifTab->setAttribute('data-type', 'ipmi');
	$helpTextWhenDragInterfaceIPMI = new CSpan(_('Drag here to change the type of the interface to "IPMI" type.'));
	$helpTextWhenDragInterfaceIPMI->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addIPMIInterface', _('Add'), null, 'link_menu'), 'interface-add-control');
	$col = new CCol($helpTextWhenDragInterfaceIPMI);
	$col->setAttribute('colspan', 6);

	$buttonRow = new CRow(array($buttonCol, $col));
	$buttonRow->setAttribute('id', 'IPMIIterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('IPMI interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'),
		false, null, 'interface-row'
	);
}
// interfaces for discovered hosts
else {
	$interfaces = array();
	$existingInterfaceTypes = array();
	foreach ($host['interfaces'] as $interface) {
		$interface['locked'] = true;
		$existingInterfaceTypes[$interface['type']] = true;
		$interfaces[$interface['interfaceid']] = $interface;
	}
	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson($interfaces).');');
	zbx_add_post_js('hostInterfacesManager.disable();');

	// table for agent interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'agentInterfaces');
	$ifTab->setAttribute('data-type', 'agent');

	// header
	$ifTab->addRow(array(
		new CCol(SPACE, 'interface-drag-control'),
		new CCol(_('IP address'), 'interface-ip'),
		new CCol(_('DNS name'), 'interface-dns'),
		new CCol(_('Connect to'), 'interface-connect-to'),
		new CCol(_('Port'), 'interface-port'),
		new CCol(_('Default'), 'interface-default'),
		new CCol(SPACE, 'interface-control')
	));

	$row = new CRow(null, null, 'agentIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_AGENT])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No agent interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);

	$hostList->addRow(_('Agent interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row interface-row-first');

	// table for SNMP interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'SNMPInterfaces');
	$ifTab->setAttribute('data-type', 'snmp');

	$row = new CRow(null, null, 'SNMPIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_SNMP])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No SNMP interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row');

	// table for JMX interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'JMXInterfaces');
	$ifTab->setAttribute('data-type', 'jmx');

	$row = new CRow(null, null, 'JMXIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_JMX])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No JMX interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('JMX interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row');

	// table for IPMI interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'IPMIInterfaces');
	$ifTab->setAttribute('data-type', 'ipmi');

	$row = new CRow(null, null, 'IPMIIterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_IPMI])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No IPMI interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('IPMI interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row interface-row-last');
}

$hostList->addRow(_('Description'), new CTextArea('description', $host['description']));

// Proxy
if (!$isDiscovered) {
	$proxyControl = new CComboBox('proxy_hostid', $host['proxy_hostid']);
	$proxyControl->addItem(0, _('(no proxy)'));

	$db_proxies = API::Proxy()->get(array('output' => API_OUTPUT_EXTEND));
	order_result($db_proxies, 'host');

	foreach ($db_proxies as $proxy) {
		$proxyControl->addItem($proxy['proxyid'], $proxy['host']);
	}
}
else {
	if ($this->data['hostProxy']) {
		$proxyControl = new CTextBox('proxy_host', $this->data['hostProxy']['host'], null, true);
	}
	else {
		$proxyControl = new CTextBox('proxy_host', _('(no proxy)'), null, true);
	}
}
$hostList->addRow(_('Monitored by proxy'), $proxyControl);

$hostList->addRow(_('Enabled'), new CCheckBox('status', ($host['status'] == HOST_STATUS_MONITORED), null, HOST_STATUS_MONITORED));

if ($this->data['form'] == 'full_clone') {
	// host applications
	if ($this->data['hostApplications']) {
		$applicationsList = array();
		foreach ($this->data['hostApplications'] as $applicationId => $application) {
			$applicationsList[$applicationId] = $application['name'];
		}
		order_result($applicationsList);

		$listBox = new CListBox('applications', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($applicationsList);
		$hostList->addRow(_('Applications'), $listBox);
	}

	// host items
	if ($this->data['hostItems']) {
		$itemsList = array();
		foreach ($hostItems as $itemId => $item) {
			$itemsList[$itemId] = $item['name_expanded'];
		}
		order_result($itemsList);

		$listBox = new CListBox('items', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($itemsList);
		$hostList->addRow(_('Items'), $listBox);
	}

	// host triggers
	if ($this->data['hostTriggers']) {
		$triggersList = array();
		foreach ($this->data['hostTriggers'] as $triggerId => $trigger) {
			$triggersList[$triggerId] = $trigger['description'];
		}

		if ($triggersList) {
			order_result($triggersList);

			$listBox = new CListBox('triggers', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($triggersList);
			$hostList->addRow(_('Triggers'), $listBox);
		}
	}

	// host graphs
	if ($this->data['hostGraphs']) {
		foreach ($this->data['hostGraphs'] as $graphId => $graph) {
			$graphsList[$graphId] = $graph['name'];
		}

		if (!empty($graphsList)) {
			order_result($graphsList);

			$listBox = new CListBox('graphs', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($graphsList);
			$hostList->addRow(_('Graphs'), $listBox);
		}
	}

	// discovery rules
	if ($this->data['hostDiscoveryRules']) {
		$discoveryRulesList = array();
		foreach ($this->data['hostDiscoveryRules'] as $discoveryRuleId => $discoveryRule) {
			$discoveryRulesList[$discoveryRuleId] = $discoveryRule['name_expanded'];
		}
		order_result($discoveryRulesList);

		$listBox = new CListBox('discoveryRules', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($discoveryRulesList);
		$hostList->addRow(_('Discovery rules'), $listBox);
	}

	// item prototypes
	if ($this->data['hostItemPrototypes']) {
		$itemPrototypesList = array();
		foreach ($this->data['hostItemPrototypes'] as $itemPrototypeId => $itemPrototype) {
			$itemPrototypesList[$itemPrototypeId] = $itemPrototype['name_expanded'];
		}
		order_result($itemPrototypesList);

		$listBox = new CListBox('itemsPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($itemPrototypesList);
		$hostList->addRow(_('Item prototypes'), $listBox);
	}

	// trigger prototypes
	if ($this->data['hostTriggerPrototypes']) {
		$itemPrototypesList = array();
		foreach ($this->data['hostTriggerPrototypes'] as $triggerPrototypeId => $triggerPrototype) {
			$itemPrototypesList[$triggerPrototypeId] = $triggerPrototype['description'];
		}

		if ($itemPrototypesList) {
			order_result($itemPrototypesList);

			$listBox = new CListBox('triggerprototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($itemPrototypesList);
			$hostList->addRow(_('Trigger prototypes'), $listBox);
		}
	}

	// graph prototypes
	if ($this->data['hostGraphPrototypes']) {
		foreach ($this->data['hostGraphPrototypes'] as $graphPrototypeId => $graphPrototype) {
			$hostGraphPrototypesList[$graphPrototypeId] = $graphPrototype['name'];
		}
		order_result($hostGraphPrototypesList);

		$listBox = new CListBox('graphPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($hostGraphPrototypesList);
		$hostList->addRow(_('Graph prototypes'), $listBox);
	}

	// host prototypes
	if ($this->data['hostHostPrototypes']) {
		$hostHostPrototypesList = array();
		foreach ($this->data['hostHostPrototypes'] as $hostPrototypeId => $hostPrototype) {
			$hostHostPrototypesList[$hostPrototypeId] = $hostPrototype['name'];
		}
		order_result($hostHostPrototypesList);

		$listBox = new CListBox('hostPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($hostHostPrototypesList);
		$hostList->addRow(_('Host prototypes'), $listBox);
	}

	// web scenarios
	if ($this->data['hostHttpTests']) {
		$httpTestsList = array();
		foreach ($this->data['hostHttpTests'] as $httpTestId => $httpTest) {
			$httpTestsList[$httpTestId] = $httpTest['name'];
		}
		order_result($httpTestsList);

		$listBox = new CListBox('httpTests', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($httpTestsList);
		$hostList->addRow(_('Web scenarios'), $listBox);
	}
}
$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList('tmpllist');

// create linked template table
$linkedTemplateTable = new CTable(_('No templates linked.'), 'formElementTable');
$linkedTemplateTable->attr('id', 'linkedTemplateTable');

// templates for normal hosts
if (!$isDiscovered) {
	$linkedTemplateTable->setHeader(array(_('Name'), _('Action')));
	$ignoredTemplates = array();
	foreach ($this->data['hostLinkedTemplates'] as $template) {
		$tmplList->addVar('templates[]', $template['templateid']);
		$templateLink = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);
		$templateLink->setTarget('_blank');

		$linkedTemplateTable->addRow(
			array(
				$templateLink,
				array(
					new CSubmit('unlink['.$template['templateid'].']', _('Unlink'), null, 'link_menu'),
					SPACE,
					SPACE,
					isset($host['templates'][$template['templateid']]) && ($this->data['form'] != 'full_clone')
						? new CSubmit('unlink_and_clear['.$template['templateid'].']', _('Unlink and clear'), null, 'link_menu')
						: SPACE
				)
			),
			null, 'conditions_'.$template['templateid']
		);
		$ignoredTemplates[$template['templateid']] = $template['name'];
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'template-link-block objectgroup inlineblock border_dotted ui-corner-all'));

	// create new linked template table
	$newTemplateTable = new CTable(null, 'formElementTable');
	$newTemplateTable->attr('id', 'newTemplateTable');

	$newTemplateTable->addRow(array(new CMultiSelect(array(
		'name' => 'add_templates[]',
		'objectName' => 'templates',
		'ignored' => $ignoredTemplates,
		'popup' => array(
			'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$frmHost->getName().
				'&dstfld1=add_templates_&templated_hosts=1&multiselect=1',
			'width' => 450,
			'height' => 450
		)
	))));

	$newTemplateTable->addRow(array(new CSubmit('add_template', _('Add'), null, 'link_menu')));

	$tmplList->addRow(_('Link new templates'), new CDiv($newTemplateTable, 'template-link-block objectgroup inlineblock border_dotted ui-corner-all'));
}
// templates for discovered hosts
else {
	$linkedTemplateTable->setHeader(array(_('Name')));
	foreach ($this->data['hostLinkedTemplates'] as $template) {
		$templateLink = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);
		$templateLink->setTarget('_blank');

		$linkedTemplateTable->addRow($templateLink, null, 'conditions_'.$template['templateid']);
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'template-link-block objectgroup inlineblock border_dotted ui-corner-all'));
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

/*
 * IPMI
 */
$ipmiList = new CFormList('ipmilist');

// normal hosts
if (!$isDiscovered) {
	$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $host['ipmi_authtype']);
	$cmbIPMIAuthtype->addItems(ipmiAuthTypes());
	$cmbIPMIAuthtype->addClass('openView');
	$cmbIPMIAuthtype->setAttribute('size', 7);
	$cmbIPMIAuthtype->addStyle('width: 170px;');
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $host['ipmi_privilege']);
	$cmbIPMIPrivilege->addItems(ipmiPrivileges());
	$cmbIPMIPrivilege->addClass('openView');
	$cmbIPMIPrivilege->setAttribute('size', 5);
	$cmbIPMIPrivilege->addStyle('width: 170px;');
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);
}
// discovered hosts
else {
	$cmbIPMIAuthtype = new CTextBox('ipmi_authtype_name', ipmiAuthTypes($host['ipmi_authtype']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CTextBox('ipmi_privilege_name', ipmiPrivileges($host['ipmi_privilege']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);
}

$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $host['ipmi_username'], ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $host['ipmi_password'], ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

/*
 * Macros
 */
$macrosView = new CView('common.macros', array(
	'macros' => $host['macros'] ? $host['macros'] : array(array('macro' => '', 'value' => '')),
	'readonly' => $isDiscovered
));
$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());

$inventoryFormList = new CFormList('inventorylist');

// radio buttons for inventory type choice
$inventoryDisabledBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_DISABLED, null, 'host_inventory_radio_'.HOST_INVENTORY_DISABLED,
	$host['inventory_mode'] == HOST_INVENTORY_DISABLED
);
$inventoryDisabledBtn->setEnabled(!$isDiscovered);

$inventoryManualBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_MANUAL, null, 'host_inventory_radio_'.HOST_INVENTORY_MANUAL,
	$host['inventory_mode'] == HOST_INVENTORY_MANUAL
);
$inventoryManualBtn->setEnabled(!$isDiscovered);

$inventoryAutomaticBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_AUTOMATIC, null, 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC,
	$host['inventory_mode'] == HOST_INVENTORY_AUTOMATIC
);
$inventoryAutomaticBtn->setEnabled(!$isDiscovered);

$inventoryTypeRadioButton = array(
	$inventoryDisabledBtn,
	new CLabel(_('Disabled'), 'host_inventory_radio_'.HOST_INVENTORY_DISABLED),
	$inventoryManualBtn,
	new CLabel(_('Manual'), 'host_inventory_radio_'.HOST_INVENTORY_MANUAL),
	$inventoryAutomaticBtn,
	new CLabel(_('Automatic'), 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC),
);
$inventoryFormList->addRow(SPACE, new CDiv($inventoryTypeRadioButton, 'jqueryinputset'));

/* TODO: FIX ME */
$hostInventoryTable = DB::getSchema('host_inventory');
$hostInventoryFields = getHostInventories();

$hostInventory = $host['inventory'];
foreach ($hostInventoryFields as $inventoryNo => $inventoryInfo) {
	if (!isset($hostInventory[$inventoryInfo['db_field']])) {
		$hostInventory[$inventoryInfo['db_field']] = '';
	}

	if ($hostInventoryTable['fields'][$inventoryInfo['db_field']]['type'] == DB::FIELD_TYPE_TEXT) {
		$input = new CTextArea('inventory['.$inventoryInfo['db_field'].']', $hostInventory[$inventoryInfo['db_field']]);
		$input->addStyle('width: 64em;');
	}
	else {
		$fieldLength = $hostInventoryTable['fields'][$inventoryInfo['db_field']]['length'];
		$input = new CTextBox('inventory['.$inventoryInfo['db_field'].']', $hostInventory[$inventoryInfo['db_field']]);
		$input->setAttribute('maxlength', $fieldLength);
		$input->addStyle('width: '.($fieldLength > 64 ? 64 : $fieldLength).'em;');
	}

	if ($host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
		$input->setAttribute('disabled', 'disabled');
	}

	// link to populating item at the right side (if any)
	if (isset($hostItemsToInventory[$inventoryNo])) {
		$itemName = $hostItemsToInventory[$inventoryNo]['name_expanded'];

		$populatingLink = new CLink($itemName, 'items.php?form=update&itemid='.$hostItemsToInventory[$inventoryNo]['itemid']);
		$populatingLink->setAttribute('title', _s('This field is automatically populated by item "%s".', $itemName));
		$populatingItemCell = array(' &larr; ', $populatingLink);

		$input->addClass('linked_to_item'); // this will be used for disabling fields via jquery
		if ($host['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
			$input->setAttribute('disabled', 'disabled');
		}
	}
	else {
		$populatingItemCell = '';
	}
	$input->addStyle('float: left;');

	$populatingItem = new CSpan($populatingItemCell, 'populating_item');
	if ($host['inventory_mode'] != HOST_INVENTORY_AUTOMATIC) { // those links are visible only in automatic mode
		$populatingItem->addStyle('display: none');
	}

	$inventoryFormList->addRow($inventoryInfo['title'], array($input, $populatingItem));
}

// clearing the float
$clearFixDiv = new CDiv();
$clearFixDiv->addStyle('clear: both;');
$inventoryFormList->addRow('', $clearFixDiv);

$divTabs->addTab('inventoryTab', _('Host inventory'), $inventoryFormList);
$frmHost->addItem($divTabs);

/*
 * footer
 */
// do not display the clone and delete buttons for clone forms and new host forms
if (getRequest('hostid') && !in_array($this->data['form'], array('clone', 'full_clone'))) {
	$frmHost->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('start_clone', _('Clone')),
			new CSubmit('start_full_clone', _('Full clone')),
			new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid').url_param('groupid')),
			new CButtonCancel(url_param('groupid'))
		)
	));
}
else {
	$submit = new CSubmit($this->data['form'], _('Add'));
	$frmHost->addItem(makeFormFooter(
		$submit,
		new CButtonCancel(url_param('groupid'))
	));
}


return $frmHost;
