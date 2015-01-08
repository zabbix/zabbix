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

$divTabs = new CTabView();
if (!isset($_REQUEST['form_refresh'])) {
	$divTabs->setSelected(0);
}

$host_groups = get_request('groups', array());
if (isset($_REQUEST['groupid']) && ($_REQUEST['groupid'] > 0) && empty($host_groups)) {
	array_push($host_groups, $_REQUEST['groupid']);
}

$newgroup = get_request('newgroup', '');
$host = get_request('host', '');
$visiblename = get_request('visiblename', '');
$status = get_request('status', HOST_STATUS_MONITORED);
$proxy_hostid = get_request('proxy_hostid', '');
$ipmi_authtype = get_request('ipmi_authtype', -1);
$ipmi_privilege = get_request('ipmi_privilege', 2);
$ipmi_username = get_request('ipmi_username', '');
$ipmi_password = get_request('ipmi_password', '');
$inventoryMode = getRequest('inventory_mode', HOST_INVENTORY_DISABLED);
$hostInventory = getRequest('host_inventory', array());
$macros = get_request('macros', array());
$interfaces = get_request('interfaces', array());
$templateIds = get_request('templates', array());
$clear_templates = get_request('clear_templates', array());

$_REQUEST['hostid'] = get_request('hostid', 0);

$frm_title = _('Host');
if ($_REQUEST['hostid'] > 0) {
	$dbHost = $this->data['dbHost'];

	$frm_title .= SPACE.' ['.$dbHost['host'].']';
	$original_templates = $dbHost['parentTemplates'];
	$original_templates = zbx_toHash($original_templates, 'templateid');

	if (isset($_REQUEST['mainInterfaces'][INTERFACE_TYPE_AGENT])) {
		$mainAgentId = $_REQUEST['mainInterfaces'][INTERFACE_TYPE_AGENT];
		$interfaces[$mainAgentId]['main'] = '1';
	}
	if (isset($_REQUEST['mainInterfaces'][INTERFACE_TYPE_SNMP])) {
		$snmpAgentId = $_REQUEST['mainInterfaces'][INTERFACE_TYPE_SNMP];
		$interfaces[$snmpAgentId]['main'] = '1';
	}
	if (isset($_REQUEST['mainInterfaces'][INTERFACE_TYPE_JMX])) {
		$ipmiAgentId = $_REQUEST['mainInterfaces'][INTERFACE_TYPE_JMX];
		$interfaces[$ipmiAgentId]['main'] = '1';
	}
	if (isset($_REQUEST['mainInterfaces'][INTERFACE_TYPE_IPMI])) {
		$jmxAgentId = $_REQUEST['mainInterfaces'][INTERFACE_TYPE_IPMI];
		$interfaces[$jmxAgentId]['main'] = '1';
	}

	// get items that populate host inventory fields
	$hostItemsToInventory = API::Item()->get(array(
		'filter' => array('hostid' => $dbHost['hostid']),
		'output' => array('inventory_link', 'itemid', 'hostid', 'name', 'key_'),
		'preserveKeys' => true,
		'nopermissions' => true
	));
	$hostItemsToInventory = zbx_toHash($hostItemsToInventory, 'inventory_link');

	$hostItemsToInventory = CMacrosResolverHelper::resolveItemNames($hostItemsToInventory);
}
else {
	$original_templates = array();
}

// load data from the DB when opening the full clone form for the first time
$cloneFormOpened = (in_array(getRequest('form'), array('clone', 'full_clone')) && getRequest('form_refresh') == 1);
if (getRequest('hostid') && (!hasRequest('form_refresh') || $cloneFormOpened)) {
	$proxy_hostid = $dbHost['proxy_hostid'];
	$host = $dbHost['host'];
	$visiblename = $dbHost['name'];

	// display empty visible name if equal to host name
	if ($visiblename === $host) {
		$visiblename = '';
	}

	$status = $dbHost['status'];

	$ipmi_authtype = $dbHost['ipmi_authtype'];
	$ipmi_privilege = $dbHost['ipmi_privilege'];
	$ipmi_username = $dbHost['ipmi_username'];
	$ipmi_password = $dbHost['ipmi_password'];

	$macros = order_macros($dbHost['macros'], 'macro');
	$host_groups = zbx_objectValues($dbHost['groups'], 'groupid');

	$hostInventory = $dbHost['inventory'];
	$inventoryMode = isset($hostInventory['inventory_mode']) ? $hostInventory['inventory_mode']	: $inventoryMode;

	$templateIds = array();
	foreach ($original_templates as $tpl) {
		$templateIds[$tpl['templateid']] = $tpl['templateid'];
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
}

$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
$clear_templates = array_diff($clear_templates, array_keys($templateIds));
natcasesort($templateIds);

// whether this is a discovered host
$isDiscovered = (get_request('hostid') && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED && get_request('form') == 'update');

$frmHost = new CForm();
$frmHost->setName('web.hosts.host.php.');
$frmHost->addVar('form', get_request('form', 1));

$frmHost->addVar('clear_templates', $clear_templates);

$hostList = new CFormList('hostlist');

if ($_REQUEST['hostid'] > 0 && get_request('form') != 'clone') {
	$frmHost->addVar('hostid', $_REQUEST['hostid']);
}
if ($_REQUEST['groupid'] > 0) {
	$frmHost->addVar('groupid', $_REQUEST['groupid']);
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

$hostTB = new CTextBox('host', $host, ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered);
$hostTB->setAttribute('maxlength', 64);
$hostTB->setAttribute('autofocus', 'autofocus');
$hostList->addRow(_('Host name'), $hostTB);

$visiblenameTB = new CTextBox('visiblename', $visiblename, ZBX_TEXTBOX_STANDARD_SIZE, $isDiscovered);
$visiblenameTB->setAttribute('maxlength', 64);
$hostList->addRow(_('Visible name'), $visiblenameTB);

// groups for normal hosts
if (!$isDiscovered) {
	$grp_tb = new CTweenBox($frmHost, 'groups', $host_groups, 10);
	$all_groups = API::HostGroup()->get(array(
		'editable' => true,
		'output' => API_OUTPUT_EXTEND
	));
	order_result($all_groups, 'name');
	foreach ($all_groups as $group) {
		$grp_tb->addItem($group['groupid'], $group['name']);
	}

	$hostList->addRow(_('Groups'), $grp_tb->get(_('In groups'), _('Other groups')));

	$newgroupTB = new CTextBox('newgroup', $newgroup, ZBX_TEXTBOX_SMALL_SIZE);
	$newgroupTB->setAttribute('maxlength', 64);
	$tmp_label = _('New group');
	if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
		$tmp_label .= SPACE._('(Only super admins can create groups)');
		$newgroupTB->setReadonly(true);
	}
	$hostList->addRow(SPACE, array(new CLabel($tmp_label, 'newgroup'), BR(), $newgroupTB), null, null, 'new');
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
		$json = new CJSON();
		$encodedInterfaces = $json->encode($interfaces);
		$script = 'hostInterfacesManager.add('.$encodedInterfaces.');';
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
	$buttonRow->setAttribute('id', 'agentInterfacesFooter');

	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('Agent interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'), false, null, 'interface-row interface-row-first');

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
	$buttonRow->setAttribute('id', 'SNMPInterfacesFooter');

	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('SNMP interfaces'), new CDiv($ifTab, 'border_dotted inlineblock objectgroup interface-group'), false, null, 'interface-row');

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
	$buttonRow->setAttribute('id', 'JMXInterfacesFooter');
	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('JMX interfaces'), new CDiv($ifTab, 'border_dotted objectgroup inlineblock interface-group'), false, null, 'interface-row');

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
	zbx_add_post_js('hostInterfacesManager.add('.CJs::encodeJson($interfaces).');');
	zbx_add_post_js('hostInterfacesManager.disable()');

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

// Proxy
if (!$isDiscovered) {
	$proxyControl = new CComboBox('proxy_hostid', $proxy_hostid);
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

$cmbStatus = new CComboBox('status', $status);
$cmbStatus->addItem(HOST_STATUS_MONITORED, _('Monitored'));
$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED, _('Not monitored'));

$hostList->addRow(_('Status'), $cmbStatus);

if ($_REQUEST['form'] == 'full_clone') {
	// host applications
	$hostApps = API::Application()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'inherited' => false,
		'output' => array('name'),
		'preservekeys' => true
	));

	if (!empty($hostApps)) {
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
		'hostids' => $_REQUEST['hostid'],
		'inherited' => false,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'output' => array('itemid', 'hostid', 'key_', 'name')
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
		'inherited' => false,
		'hostids' => $_REQUEST['hostid'],
		'output' => array('triggerid', 'description'),
		'selectItems' => array('type'),
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL))
	));
	if (!empty($hostTriggers)) {
		$triggersList = array();

		foreach ($hostTriggers as $hostTrigger) {
			if (httpItemExists($hostTrigger['items'])) {
				continue;
			}
			$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
		}

		if (!empty($triggersList)) {
			order_result($triggersList);

			$listBox = new CListBox('triggers', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($triggersList);
			$hostList->addRow(_('Triggers'), $listBox);
		}
	}

	// host graphs
	$hostGraphs = API::Graph()->get(array(
		'inherited' => false,
		'hostids' => $_REQUEST['hostid'],
		'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
		'selectHosts' => array('hostid'),
		'selectItems' => array('type'),
		'output' => array('graphid', 'name')
	));
	if (!empty($hostGraphs)) {
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

		if (!empty($graphsList)) {
			order_result($graphsList);

			$listBox = new CListBox('graphs', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($graphsList);
			$hostList->addRow(_('Graphs'), $listBox);
		}
	}

	// discovery rules
	$hostDiscoveryRuleids = array();

	$hostDiscoveryRules = API::DiscoveryRule()->get(array(
		'inherited' => false,
		'hostids' => $_REQUEST['hostid'],
		'output' => array('itemid', 'hostid', 'key_', 'name')
	));

	if ($hostDiscoveryRules) {
		$hostDiscoveryRules = CMacrosResolverHelper::resolveItemNames($hostDiscoveryRules);

		$discoveryRuleList = array();
		foreach ($hostDiscoveryRules as $discoveryRule) {
			$discoveryRuleList[$discoveryRule['itemid']] = $discoveryRule['name_expanded'];
		}
		order_result($discoveryRuleList);
		$hostDiscoveryRuleids = array_keys($discoveryRuleList);

		$listBox = new CListBox('discoveryRules', null, 8);
		$listBox->setAttribute('disabled', 'disabled');
		$listBox->addItems($discoveryRuleList);
		$hostList->addRow(_('Discovery rules'), $listBox);
	}

	// item prototypes
	$hostItemPrototypes = API::ItemPrototype()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'discoveryids' => $hostDiscoveryRuleids,
		'inherited' => false,
		'output' => array('itemid', 'hostid', 'key_', 'name')
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
		'hostids' => $_REQUEST['hostid'],
		'discoveryids' => $hostDiscoveryRuleids,
		'inherited' => false,
		'output' => array('triggerid', 'description'),
		'selectItems' => array('type')
	));
	if (!empty($hostTriggerPrototypes)) {
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
		'hostids' => $_REQUEST['hostid'],
		'discoveryids' => $hostDiscoveryRuleids,
		'inherited' => false,
		'selectHosts' => array('hostid'),
		'output' => array('graphid', 'name')
	));
	if (!empty($hostGraphPrototypes)) {
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
		'discoveryids' => $hostDiscoveryRuleids,
		'inherited' => false,
		'output' => array('hostid', 'name')
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
		'hostids' => getRequest('hostid'),
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

		$linkedTemplateTable->addRow(
			array(
				$template['name'],
				array(
					new CSubmit('unlink['.$template['templateid'].']', _('Unlink'), null, 'link_menu'),
					SPACE,
					SPACE,
					isset($original_templates[$template['templateid']])
						? new CSubmit('unlink_and_clear['.$template['templateid'].']', _('Unlink and clear'), null, 'link_menu')
						: SPACE
				)
			),
			null, 'conditions_'.$template['templateid']
		);

		$ignoredTemplates[$template['templateid']] = $template['name'];
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

	// create new linked template table
	$newTemplateTable = new CTable(null, 'formElementTable');
	$newTemplateTable->attr('id', 'newTemplateTable');
	$newTemplateTable->attr('style', 'min-width: 400px;');

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

	$tmplList->addRow(_('Link new templates'), new CDiv($newTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
}
// templates for discovered hosts
else {
	$linkedTemplateTable->setHeader(array(_('Name')));
	foreach ($linkedTemplates as $template) {
		$linkedTemplateTable->addRow(array($template['name']), null, 'conditions_'.$template['templateid']);
	}

	$tmplList->addRow(_('Linked templates'), new CDiv($linkedTemplateTable, 'objectgroup inlineblock border_dotted ui-corner-all'));
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList);

/*
 * IPMI
 */
$ipmiList = new CFormList('ipmilist');

// normal hosts
if (!$isDiscovered) {
	$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
	$cmbIPMIAuthtype->addItems(ipmiAuthTypes());
	$cmbIPMIAuthtype->addClass('openView');
	$cmbIPMIAuthtype->setAttribute('size', 7);
	$cmbIPMIAuthtype->addStyle('width: 170px;');
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
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

$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $ipmi_username, ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $ipmi_password, ZBX_TEXTBOX_SMALL_SIZE, $isDiscovered));
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
$inventoryFormList->addRow(SPACE, new CDiv($inventoryTypeRadioButton, 'jqueryinputset'));

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
$others = array();
// do not display the clone and delete buttons for clone forms and new host forms
if (getRequest('hostid') && !in_array(getRequest('form'), array('clone', 'full_clone'))) {
	$others[] = new CSubmit('clone', _('Clone'));
	$others[] = new CSubmit('full_clone', _('Full clone'));
	$others[] = new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid').url_param('groupid'));
}
$others[] = new CButtonCancel(url_param('groupid'));

$frmHost->addItem(makeFormFooter(new CSubmit('save', _('Save')), $others));

return $frmHost;
