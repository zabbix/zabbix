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


require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';

$widgetClass = 'host-edit';
if (isset($data['dbHost']) && $data['dbHost']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$widgetClass .= ' host-edit-discovered';
}
$hostWidget = new CWidget(null, $widgetClass);
$hostWidget->addPageHeader(_('CONFIGURATION OF HOSTS'));
$hostWidget->addItem(get_header_host_table('', $data['hostId']));

if ($data['hostId']) {
	$dbHost = $data['dbHost'];

	$originalTemplates = $dbHost['parentTemplates'];
	$originalTemplates = zbx_toHash($originalTemplates, 'templateid');

	// get items that populate host inventory fields
	$hostItemsToInventory = API::Item()->get(array(
		'output' => array('inventory_link', 'itemid', 'hostid', 'name', 'key_'),
		'filter' => array('hostid' => $dbHost['hostid']),
		'preserveKeys' => true,
		'nopermissions' => true
	));
	$hostItemsToInventory = zbx_toHash($hostItemsToInventory, 'inventory_link');

	$hostItemsToInventory = CMacrosResolverHelper::resolveItemNames($hostItemsToInventory);
}
else {
	$dbHost = array();
	$originalTemplates = array();
}

$cloneOrFullClone = ($data['form'] === 'clone' || $data['form'] === 'full_clone');

$cloningDiscoveredHost = (
	$cloneOrFullClone
	&& getRequest('form_refresh') == 1
	&& $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED
);

// Use data from database when host is opened and form is shown for first time or discovered host is being cloned.
if ($data['hostId'] && (!hasRequest('form_refresh') || $cloningDiscoveredHost)) {
	$proxyHostId = $dbHost['proxy_hostid'];
	$host = $dbHost['host'];
	$visibleName = $dbHost['name'];

	// display empty visible name if equal to host name
	if ($visibleName === $host) {
		$visibleName = '';
	}

	$ipmiAuthtype = $dbHost['ipmi_authtype'];
	$ipmiPrivilege = $dbHost['ipmi_privilege'];
	$ipmiUsername = $dbHost['ipmi_username'];
	$ipmiPassword = $dbHost['ipmi_password'];

	$macros = order_macros($dbHost['macros'], 'macro');
	$hostGroups = zbx_objectValues($dbHost['groups'], 'groupid');

	if ($cloningDiscoveredHost) {
		$status = getRequest('status', HOST_STATUS_NOT_MONITORED);
		$description = getRequest('description', '');
		$hostInventory = getRequest('host_inventory', array());
	}
	else {
		$status = $dbHost['status'];
		$description = $dbHost['description'];
		$hostInventory = $dbHost['inventory'];
	}

	$inventoryMode = isset($dbHost['inventory']['inventory_mode'])
		? $dbHost['inventory']['inventory_mode']
		: HOST_INVENTORY_DISABLED;

	$templateIds = array();
	foreach ($originalTemplates as $originalTemplate) {
		$templateIds[$originalTemplate['templateid']] = $originalTemplate['templateid'];
	}

	$interfaces = $dbHost['interfaces'];
	foreach ($interfaces as $hinum => $interface) {
		$interfaces[$hinum]['items'] = 0;
		$interfaces[$hinum]['items'] = count($dbHost['interfaces'][$interface['interfaceid']]['items']);

		// check if interface has items that require specific interface type, if so type cannot be changed
		$locked = 0;
		foreach ($dbHost['interfaces'][$interface['interfaceid']]['items'] as $item) {
			$itemInterfaceType = itemTypeInterface($item['type']);
			if (!($itemInterfaceType === false || $itemInterfaceType === INTERFACE_TYPE_ANY)) {
				$locked = 1;
				break;
			}
		}
		$interfaces[$hinum]['locked'] = $locked;
	}

	$clearTemplates = array();

	$newGroupName = '';
}
else {
	$hostGroups = getRequest('groups', array());
	if ($data['groupId'] != 0 && !$hostGroups) {
		$hostGroups[] = $data['groupId'];
	}

	$newGroupName = getRequest('newgroup', '');

	$host = getRequest('host', '');
	$visibleName = getRequest('visiblename', '');
	$proxyHostId = getRequest('proxy_hostid', '');
	$ipmiAuthtype = getRequest('ipmi_authtype', -1);
	$ipmiPrivilege = getRequest('ipmi_privilege', 2);
	$ipmiUsername = getRequest('ipmi_username', '');
	$ipmiPassword = getRequest('ipmi_password', '');
	$inventoryMode = getRequest('inventory_mode', HOST_INVENTORY_DISABLED);
	$hostInventory = getRequest('host_inventory', array());
	$macros = getRequest('macros', array());
	$interfaces = getRequest('interfaces', array());
	$templateIds = getRequest('templates', array());
	$clearTemplates = getRequest('clear_templates', array());
	$description = getRequest('description', '');

	if ($data['hostId'] == 0 && !hasRequest('form_refresh')) {
		$status = HOST_STATUS_MONITORED;
	}
	else {
		$status = getRequest('status', HOST_STATUS_NOT_MONITORED);
	}
}

$mainInterfaces = getRequest('mainInterfaces', array());
foreach (array(INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI) as $interfaceType) {
	if (isset($mainInterfaces[$interfaceType])) {
		$interfaceId = $mainInterfaces[$interfaceType];
		$interfaces[$interfaceId]['main'] = '1';
	}
}

$clearTemplates = array_intersect($clearTemplates, array_keys($originalTemplates));
$clearTemplates = array_diff($clearTemplates, array_keys($templateIds));
natcasesort($templateIds);

// whether this is a discovered host
$isDiscovered = (
	$data['hostId']
	&& $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED
	&& $data['form'] === 'update'
);

$divTabs = new CTabView();
if (!hasRequest('form_refresh')) {
	$divTabs->setSelected(0);
}

$frmHost = new CForm();
$frmHost->setName('web.hosts.host.php.');
$frmHost->addVar('form', $data['form']);

$frmHost->addVar('clear_templates', $clearTemplates);

$hostList = new CFormList('hostlist');

if ($data['hostId'] && $data['form'] !== 'clone') {
	$frmHost->addVar('hostid', $data['hostId']);
}

if ($data['groupId']) {
	$frmHost->addVar('groupid', $data['groupId']);
}

// LLD rule link
if ($isDiscovered) {
	$hostList->addRow(
		_('Discovered by'),
		new CLink($dbHost['discoveryRule']['name'],
			'host_prototypes.php?parent_discoveryid='.$dbHost['discoveryRule']['itemid'],
			'highlight underline weight_normal'
		)
	);
}

$hostTB = new CTextBox('host', $host, ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered, 128);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$visiblenameTB = new CTextBox('visiblename', $visibleName, ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered, 128);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// groups for normal hosts
if (!$isDiscovered) {
	$grp_tb = new CTweenBox($frmHost, 'groups', $hostGroups, 10);
	$all_groups = API::HostGroup()->get(array(
		'editable' => true,
		'output' => API_OUTPUT_EXTEND
	));
	order_result($all_groups, 'name');
	foreach ($all_groups as $group) {
		$grp_tb->addItem($group['groupid'], $group['name']);
	}

	$hostList->addRow(_('Groups'), $grp_tb->get(_('In groups'), _('Other groups')));

	$newgroupTB = new CTextBox('newgroup', $newGroupName, ZBX_TEXTBOX_SMALL_SIZE);
	$newgroupTB->setAttribute('maxlength', 64);
	$tmp_label = _('New group');
	if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
		$tmp_label .= ' '._('(Only super admins can create groups)');
		$newgroupTB->setReadonly(true);
	}
	$hostList->addRow(null, array(new CLabel($tmp_label, 'newgroup'), BR(), $newgroupTB), null, null, 'new');
}
// groups for discovered hosts
else {
	$groupBox = new CComboBox('groups');
	$groupBox->setAttribute('readonly', true);
	$groupBox->setAttribute('size', 10);
	foreach ($dbHost['groups'] as $group) {
		$groupBox->addItem($group['groupid'], $group['name']);
	}
	$hostList->addRow(_('Groups'), $groupBox);
}

// interfaces for normal hosts
if (!$isDiscovered) {
	if (empty($interfaces)) {
		$script = 'hostInterfacesManager.addNew("agent");';
	}
	else {
		$script = 'hostInterfacesManager.add('.CJs::encodeJson(array_values($interfaces)).');';
	}
	zbx_add_post_js($script);

	// table for agent interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'agentInterfaces');
	$ifTab->setAttribute('data-type', 'agent');

	// headers with sizes
	$iconLabel = new CCol(' ', 'interface-drag-control');
	$ipLabel = new CCol(_('IP address'), 'interface-ip');
	$dnsLabel = new CCol(_('DNS name'), 'interface-dns');
	$connectToLabel = new CCol(_('Connect to'), 'interface-connect-to');
	$portLabel = new CCol(_('Port'), 'interface-port');
	$defaultLabel = new CCol(_('Default'), 'interface-default', 2);
	$ifTab->addRow(array($iconLabel, $ipLabel, $dnsLabel, $connectToLabel, $portLabel, $defaultLabel));

	$helpTextWhenDragInterfaceAgent = new CSpan(_('Drag here to change the type of the interface to "agent" type.'));
	$helpTextWhenDragInterfaceAgent->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addAgentInterface', _('Add'), null, 'link_menu'), 'interface-add-control', 7);
	$buttonCol->addItem($helpTextWhenDragInterfaceAgent);

	$buttonRow = new CRow(array($buttonCol));
	$buttonRow->setAttribute('id', 'agentInterfacesFooter');

	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('Agent interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'), false, null, 'interface-row interface-row-first');

	// table for SNMP interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'SNMPInterfaces');
	$ifTab->setAttribute('data-type', 'snmp');

	$helpTextWhenDragInterfaceSNMP = new CSpan(_('Drag here to change the type of the interface to "SNMP" type.'));
	$helpTextWhenDragInterfaceSNMP->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addSNMPInterface', _('Add'), null, 'link_menu'), 'interface-add-control', 7);
	$buttonCol->addItem($helpTextWhenDragInterfaceSNMP);

	$buttonRow = new CRow(array($buttonCol));
	$buttonRow->setAttribute('id', 'SNMPInterfacesFooter');

	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted inlineblock objectgroup interface-group'), false, null, 'interface-row');

	// table for JMX interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'JMXInterfaces');
	$ifTab->setAttribute('data-type', 'jmx');
	$helpTextWhenDragInterfaceJMX = new CSpan(_('Drag here to change the type of the interface to "JMX" type.'));
	$helpTextWhenDragInterfaceJMX->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addJMXInterface', _('Add'), null, 'link_menu'), 'interface-add-control', 7);
	$buttonCol->addItem($helpTextWhenDragInterfaceJMX);

	$buttonRow = new CRow(array($buttonCol));
	$buttonRow->setAttribute('id', 'JMXInterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('JMX interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'), false, null, 'interface-row');

	// table for IPMI interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'IPMIInterfaces');
	$ifTab->setAttribute('data-type', 'ipmi');
	$helpTextWhenDragInterfaceIPMI = new CSpan(_('Drag here to change the type of the interface to "IPMI" type.'));
	$helpTextWhenDragInterfaceIPMI->addClass('dragHelpText');
	$buttonCol = new CCol(new CButton('addIPMIInterface', _('Add'), null, 'link_menu'), 'interface-add-control', 7);
	$buttonCol->addItem($helpTextWhenDragInterfaceIPMI);

	$buttonRow = new CRow(array($buttonCol));
	$buttonRow->setAttribute('id', 'IPMIInterfacesFooter');

	$ifTab->addRow($buttonRow);
	$hostList->addRow(_('IPMI interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'), false, null, 'interface-row');
}
// interfaces for discovered hosts
else {
	$interfaces = array();
	$existingInterfaceTypes = array();
	foreach ($dbHost['interfaces'] as $interface) {
		$interface['locked'] = true;
		$existingInterfaceTypes[$interface['type']] = true;
		$interfaces[$interface['interfaceid']] = $interface;
	}
	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson(array_values($interfaces)).');');
	zbx_add_post_js('hostInterfacesManager.disable();');

	// table for agent interfaces with footer
	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->setAttribute('id', 'agentInterfaces');
	$ifTab->setAttribute('data-type', 'agent');

	// header
	$ifTab->addRow(array(
		new CCol(null, 'interface-drag-control'),
		new CCol(_('IP address'), 'interface-ip'),
		new CCol(_('DNS name'), 'interface-dns'),
		new CCol(_('Connect to'), 'interface-connect-to'),
		new CCol(_('Port'), 'interface-port'),
		new CCol(_('Default'), 'interface-default'),
		new CCol(null, 'interface-control')
	));

	$row = new CRow(null, null, 'agentInterfacesFooter');
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

	$row = new CRow(null, null, 'SNMPInterfacesFooter');
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

	$row = new CRow(null, null, 'JMXInterfacesFooter');
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

	$row = new CRow(null, null, 'IPMIInterfacesFooter');
	if (!isset($existingInterfaceTypes[INTERFACE_TYPE_IPMI])) {
		$row->addItem(new CCol(null, 'interface-drag-control'));
		$row->addItem(new CCol(_('No IPMI interfaces found.'), null, 5));
	}
	$ifTab->addRow($row);
	$hostList->addRow(_('IPMI interfaces'), new CDiv($ifTab, 'border_dotted objectgroup interface-group'), false, null, 'interface-row interface-row-last');
}

$hostList->addRow(_('Description'), new CTextArea('description', $description));

// Proxy
if (!$isDiscovered) {
	$proxyControl = new CComboBox('proxy_hostid', $proxyHostId);
	$proxyControl->addItem(0, _('(no proxy)'));

	$db_proxies = API::Proxy()->get(array('output' => API_OUTPUT_EXTEND));
	order_result($db_proxies, 'host');

	foreach ($db_proxies as $proxy) {
		$proxyControl->addItem($proxy['proxyid'], $proxy['host']);
	}
}
else {
	if ($dbHost['proxy_hostid']) {
		$proxy = API::Proxy()->get(array(
			'output' => array('host', 'proxyid'),
			'proxyids' => $dbHost['proxy_hostid'],
			'limit' => 1
		));
		$proxy = reset($proxy);

		$proxyControl = new CTextBox('proxy_host', $proxy['host'], null, true);
	}
	else {
		$proxyControl = new CTextBox('proxy_host', _('(no proxy)'), null, true);
	}
}
$hostList->addRow(_('Monitored by proxy'), $proxyControl);

$hostList->addRow(_('Enabled'), new CCheckBox('status', ($status == HOST_STATUS_MONITORED), null, HOST_STATUS_MONITORED));

if ($data['form'] === 'full_clone') {
	// host applications
	$hostApps = API::Application()->get(array(
		'output' => array('name'),
		'hostids' => array($data['hostId']),
		'inherited' => false,
		'preservekeys' => true
	));

	if ($hostApps) {
		$applicationsList = array();
		foreach ($hostApps as $hostAppId => $hostApp) {
			$applicationsList[$hostAppId] = $hostApp['name'];
		}
		order_result($applicationsList);

		$listBox = new CListBox('applications', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($applicationsList);
		$hostList->addRow(_('Applications'), $listBox);
	}

	// host items
	$hostItems = API::Item()->get(array(
		'output' => array('itemid', 'hostid', 'key_', 'name'),
		'hostids' => array($data['hostId']),
		'inherited' => false,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
	));

	if ($hostItems) {
		$hostItems = CMacrosResolverHelper::resolveItemNames($hostItems);

		$itemsList = array();
		foreach ($hostItems as $hostItem) {
			$itemsList[$hostItem['itemid']] = $hostItem['name_expanded'];
		}
		order_result($itemsList);

		$listBox = new CListBox('items', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($itemsList);
		$hostList->addRow(_('Items'), $listBox);
	}

	// host triggers
	$hostTriggers = API::Trigger()->get(array(
		'output' => array('triggerid', 'description'),
		'selectItems' => array('type'),
		'hostids' => array($data['hostId']),
		'inherited' => false,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL))
	));

	if ($hostTriggers) {
		$triggersList = array();

		foreach ($hostTriggers as $hostTrigger) {
			if (httpItemExists($hostTrigger['items'])) {
				continue;
			}
			$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
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
	$hostGraphs = API::Graph()->get(array(
		'output' => array('graphid', 'name'),
		'selectHosts' => array('hostid'),
		'selectItems' => array('type'),
		'hostids' => array($data['hostId']),
		'inherited' => false,
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL))
	));

	if ($hostGraphs) {
		$graphsList = array();
		foreach ($hostGraphs as $hostGraph) {
			if (count($hostGraph['hosts']) > 1) {
				continue;
			}
			if (httpItemExists($hostGraph['items'])) {
				continue;
			}
			$graphsList[$hostGraph['graphid']] = $hostGraph['name'];
		}

		if ($graphsList) {
			order_result($graphsList);

			$listBox = new CListBox('graphs', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($graphsList);
			$hostList->addRow(_('Graphs'), $listBox);
		}
	}

	// discovery rules
	$hostDiscoveryRuleIds = array();

	$hostDiscoveryRules = API::DiscoveryRule()->get(array(
		'output' => array('itemid', 'hostid', 'key_', 'name'),
		'hostids' => array($data['hostId']),
		'inherited' => false
	));

	if ($hostDiscoveryRules) {
		$hostDiscoveryRules = CMacrosResolverHelper::resolveItemNames($hostDiscoveryRules);

		$discoveryRuleList = array();
		foreach ($hostDiscoveryRules as $discoveryRule) {
			$discoveryRuleList[$discoveryRule['itemid']] = $discoveryRule['name_expanded'];
		}
		order_result($discoveryRuleList);
		$hostDiscoveryRuleIds = array_keys($discoveryRuleList);

		$listBox = new CListBox('discoveryRules', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($discoveryRuleList);
		$hostList->addRow(_('Discovery rules'), $listBox);
	}

	// item prototypes
	$hostItemPrototypes = API::ItemPrototype()->get(array(
		'output' => array('itemid', 'hostid', 'key_', 'name'),
		'hostids' => array($data['hostId']),
		'discoveryids' => $hostDiscoveryRuleIds,
		'inherited' => false
	));

	if ($hostItemPrototypes) {
		$hostItemPrototypes = CMacrosResolverHelper::resolveItemNames($hostItemPrototypes);

		$prototypeList = array();
		foreach ($hostItemPrototypes as $itemPrototype) {
			$prototypeList[$itemPrototype['itemid']] = $itemPrototype['name_expanded'];
		}
		order_result($prototypeList);

		$listBox = new CListBox('itemsPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($prototypeList);
		$hostList->addRow(_('Item prototypes'), $listBox);
	}

	// Trigger prototypes
	$hostTriggerPrototypes = API::TriggerPrototype()->get(array(
		'output' => array('triggerid', 'description'),
		'selectItems' => array('type'),
		'hostids' => array($data['hostId']),
		'discoveryids' => $hostDiscoveryRuleIds,
		'inherited' => false
	));

	if ($hostTriggerPrototypes) {
		$prototypeList = array();
		foreach ($hostTriggerPrototypes as $triggerPrototype) {
			// skip trigger prototypes with web items
			if (httpItemExists($triggerPrototype['items'])) {
				continue;
			}
			$prototypeList[$triggerPrototype['triggerid']] = $triggerPrototype['description'];
		}

		if ($prototypeList) {
			order_result($prototypeList);

			$listBox = new CListBox('triggerprototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);
			$hostList->addRow(_('Trigger prototypes'), $listBox);
		}
	}

	// Graph prototypes
	$hostGraphPrototypes = API::GraphPrototype()->get(array(
		'output' => array('graphid', 'name'),
		'selectHosts' => array('hostid'),
		'hostids' => array($data['hostId']),
		'discoveryids' => $hostDiscoveryRuleIds,
		'inherited' => false
	));

	if ($hostGraphPrototypes) {
		$prototypeList = array();
		foreach ($hostGraphPrototypes as $graphPrototype) {
			if (count($graphPrototype['hosts']) == 1) {
				$prototypeList[$graphPrototype['graphid']] = $graphPrototype['name'];
			}
		}
		order_result($prototypeList);

		$listBox = new CListBox('graphPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($prototypeList);
		$hostList->addRow(_('Graph prototypes'), $listBox);
	}

	// host prototypes
	$hostPrototypes = API::HostPrototype()->get(array(
		'output' => array('hostid', 'name'),
		'discoveryids' => $hostDiscoveryRuleIds,
		'inherited' => false
	));

	if ($hostPrototypes) {
		$prototypeList = array();
		foreach ($hostPrototypes as $hostPrototype) {
			$prototypeList[$hostPrototype['hostid']] = $hostPrototype['name'];
		}
		order_result($prototypeList);

		$listBox = new CListBox('hostPrototypes', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($prototypeList);
		$hostList->addRow(_('Host prototypes'), $listBox);
	}

	// web scenarios
	$httpTests = API::HttpTest()->get(array(
		'output' => array('httptestid', 'name'),
		'hostids' => array($data['hostId']),
		'inherited' => false
	));

	if ($httpTests) {
		$httpTestList = array();

		foreach ($httpTests as $httpTest) {
			$httpTestList[$httpTest['httptestid']] = $httpTest['name'];
		}

		order_result($httpTestList);

		$listBox = new CListBox('httpTests', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($httpTestList);
		$hostList->addRow(_('Web scenarios'), $listBox);
	}
}

$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList('tmpllist');

// create linked template table
$linkedTemplateTable = new CTable(_('No templates linked.'), 'formElementTable');
$linkedTemplateTable->attr('id', 'linkedTemplateTable');

$linkedTemplates = API::Template()->get(array(
	'templateids' => $templateIds,
	'output' => array('templateid', 'name')
));
CArrayHelper::sort($linkedTemplates, array('name'));

// templates for normal hosts
if (!$isDiscovered) {
	$linkedTemplateTable->setHeader(array(_('Name'), _('Action')));
	$ignoredTemplates = array();

	foreach ($linkedTemplates as $template) {
		$tmplList->addVar('templates[]', $template['templateid']);
		$templateLink = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);
		$templateLink->setTarget('_blank');

		$unlinkButton = new CSubmit('unlink['.$template['templateid'].']', _('Unlink'), null, 'link_menu');
		$unlinkAndClearButton = new CSubmit('unlink_and_clear['.$template['templateid'].']', _('Unlink and clear'),
			null, 'link_menu'
		);
		$unlinkAndClearButton->addStyle('margin-left: 8px');

		$linkedTemplateTable->addRow(
			array(
				$templateLink,
				array(
					$unlinkButton,
					(isset($originalTemplates[$template['templateid']]) && !$cloneOrFullClone)
						? $unlinkAndClearButton
						: null
				)
			),
			null,
			'conditions_'.$template['templateid']
		);
		$ignoredTemplates[$template['templateid']] = $template['name'];
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable,
		'template-link-block objectgroup inlineblock border_dotted ui-corner-all')
	);

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

	$tmplList->addRow(_('Link new templates'), new CDiv($newTemplateTable,
		'template-link-block objectgroup inlineblock border_dotted ui-corner-all')
	);
}
// templates for discovered hosts
else {
	$linkedTemplateTable->setHeader(array(_('Name')));
	foreach ($linkedTemplates as $template) {
		$templateLink = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);
		$templateLink->setTarget('_blank');

		$linkedTemplateTable->addRow($templateLink, null, 'conditions_'.$template['templateid']);
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable,
		'template-link-block objectgroup inlineblock border_dotted ui-corner-all')
	);
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

/*
 * IPMI
 */
$ipmiList = new CFormList('ipmilist');

// normal hosts
if (!$isDiscovered) {
	$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmiAuthtype);
	$cmbIPMIAuthtype->addItems(ipmiAuthTypes());
	$cmbIPMIAuthtype->addClass('openView');
	$cmbIPMIAuthtype->setAttribute('size', 7);
	$cmbIPMIAuthtype->addStyle('width: 170px;');
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmiPrivilege);
	$cmbIPMIPrivilege->addItems(ipmiPrivileges());
	$cmbIPMIPrivilege->addClass('openView');
	$cmbIPMIPrivilege->setAttribute('size', 5);
	$cmbIPMIPrivilege->addStyle('width: 170px;');
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);
}
// discovered hosts
else {
	$cmbIPMIAuthtype = new CTextBox('ipmi_authtype_name', ipmiAuthTypes($dbHost['ipmi_authtype']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CTextBox('ipmi_privilege_name', ipmiPrivileges($dbHost['ipmi_privilege']), ZBX_TEXTBOX_SMALL_SIZE, true);
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);
}

$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $ipmiUsername, ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $ipmiPassword, ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

/*
 * Macros
 */
if (empty($macros)) {
	$macros = array(array('macro' => '', 'value' => ''));
}

$macrosView = new CView('common.macros', array(
	'macros' => $macros,
	'readonly' => $isDiscovered
));
$divTabs->addTab('macroTab', _('Macros'), $macrosView->render());

$inventoryFormList = new CFormList('inventorylist');

// radio buttons for inventory type choice
$inventoryDisabledBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_DISABLED, null, 'host_inventory_radio_'.HOST_INVENTORY_DISABLED,
	$inventoryMode == HOST_INVENTORY_DISABLED
);
$inventoryDisabledBtn->setEnabled(!$isDiscovered);

$inventoryManualBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_MANUAL, null, 'host_inventory_radio_'.HOST_INVENTORY_MANUAL,
	$inventoryMode == HOST_INVENTORY_MANUAL
);
$inventoryManualBtn->setEnabled(!$isDiscovered);

$inventoryAutomaticBtn = new CRadioButton('inventory_mode', HOST_INVENTORY_AUTOMATIC, null, 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC,
	$inventoryMode == HOST_INVENTORY_AUTOMATIC
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
$inventoryFormList->addRow(null, new CDiv($inventoryTypeRadioButton, 'jqueryinputset radioset'));

$hostInventoryTable = DB::getSchema('host_inventory');
$hostInventoryFields = getHostInventories();

foreach ($hostInventoryFields as $inventoryNo => $inventoryInfo) {
	if (!isset($hostInventory[$inventoryInfo['db_field']])) {
		$hostInventory[$inventoryInfo['db_field']] = '';
	}

	if ($hostInventoryTable['fields'][$inventoryInfo['db_field']]['type'] == DB::FIELD_TYPE_TEXT) {
		$input = new CTextArea('host_inventory['.$inventoryInfo['db_field'].']', $hostInventory[$inventoryInfo['db_field']]);
		$input->addStyle('width: 64em;');
	}
	else {
		$fieldLength = $hostInventoryTable['fields'][$inventoryInfo['db_field']]['length'];
		$input = new CTextBox('host_inventory['.$inventoryInfo['db_field'].']', $hostInventory[$inventoryInfo['db_field']]);
		$input->setAttribute('maxlength', $fieldLength);
		$input->addStyle('width: '.($fieldLength > 64 ? 64 : $fieldLength).'em;');
	}
	if ($inventoryMode == HOST_INVENTORY_DISABLED) {
		$input->setAttribute('disabled', 'disabled');
	}

	// link to populating item at the right side (if any)
	if (isset($hostItemsToInventory[$inventoryNo])) {
		$itemName = $hostItemsToInventory[$inventoryNo]['name_expanded'];

		$populatingLink = new CLink($itemName, 'items.php?form=update&itemid='.$hostItemsToInventory[$inventoryNo]['itemid']);
		$populatingLink->setAttribute('title', _s('This field is automatically populated by item "%s".', $itemName));
		$populatingItemCell = array(' &larr; ', $populatingLink);

		$input->addClass('linked_to_item'); // this will be used for disabling fields via jquery
		if ($inventoryMode == HOST_INVENTORY_AUTOMATIC) {
			$input->setAttribute('disabled', 'disabled');
		}
	}
	else {
		$populatingItemCell = '';
	}
	$input->addStyle('float: left;');

	$populatingItem = new CSpan($populatingItemCell, 'populating_item');
	if ($inventoryMode != HOST_INVENTORY_AUTOMATIC) { // those links are visible only in automatic mode
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
// Do not display the clone and delete buttons for clone forms and new host forms.
if ($data['hostId'] && !$cloneOrFullClone) {
	$frmHost->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid').url_param('groupid')),
			new CButtonCancel(url_param('groupid'))
		)
	));
}
else {
	$frmHost->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		array(new CButtonCancel(url_param('groupid')))
	));
}

$hostWidget->addItem($frmHost);

return $hostWidget;
