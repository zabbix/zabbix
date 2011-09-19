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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates
	include('include/views/js/configuration.host.edit.js.php');
	include('include/views/js/configuration.host.edit.macros.js.php');
?>
<?php
	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh']))
		$divTabs->setSelected(0);

	$host_groups = get_request('groups', array());
	if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && empty($host_groups)){
		array_push($host_groups, $_REQUEST['groupid']);
	}

	$newgroup	= get_request('newgroup','');

	$host 		= get_request('host',	'');
	$visiblename= get_request('visiblename',	'');
	$status		= get_request('status',	HOST_STATUS_MONITORED);
	$proxy_hostid	= get_request('proxy_hostid','');

	$ipmi_authtype	= get_request('ipmi_authtype',-1);
	$ipmi_privilege	= get_request('ipmi_privilege',2);
	$ipmi_username	= get_request('ipmi_username','');
	$ipmi_password	= get_request('ipmi_password','');

	$_REQUEST['hostid'] = get_request('hostid', 0);

	$inventory_mode	= get_request('inventory_mode', HOST_INVENTORY_DISABLED);
	$host_inventory	= get_request('host_inventory',array());

	$macros = get_request('macros',array());
	$interfaces = get_request('interfaces',array());
	$templates = get_request('templates',array());
	$clear_templates = get_request('clear_templates', array());

	$frm_title = _('Host');
	if($_REQUEST['hostid']>0){
		$dbHosts = API::Host()->get(array(
			'hostids' => $_REQUEST['hostid'],
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectInventory' => true,
			'output' => API_OUTPUT_EXTEND
		));
		$dbHost = reset($dbHosts);

		$dbHost['interfaces'] = API::HostInterface()->get(array(
			'hostids' => $dbHost['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_COUNT,
			'preserveKeys' => true,
		));

		morder_result($dbHost['interfaces'], array('type', 'interfaceid'));

		$frm_title	.= SPACE.' ['.$dbHost['host'].']';
		$original_templates = $dbHost['parentTemplates'];
		$original_templates = zbx_toHash($original_templates, 'templateid');

		if(!empty($interfaces)){
			foreach($interfaces as $hinum => $interface){
				$interfaces[$hinum]['items'] = 0;

				if($interface['new'] == 'create') continue;
				if(!isset($dbHost['interfaces'][$interface['interfaceid']])) continue;

				$interfaces[$hinum]['items'] = $dbHost['interfaces'][$interface['interfaceid']]['items'];
			}
		}

		// getting items that populate host inventory fields
		$hostItemsToInventory = API::Item()->get(array(
			'filter' => array('hostid'=>$dbHost['hostid']),
			'output' => array('inventory_link', 'name', 'key_'),
			'preserveKeys' => true,
			'nopermissions' => true
		));
		$hostItemsToInventory = zbx_toHash($hostItemsToInventory, 'inventory_link');
	}
	else{
		$original_templates = array();
	}

	if(($_REQUEST['hostid']>0) && !isset($_REQUEST['form_refresh'])){
		$proxy_hostid	= $dbHost['proxy_hostid'];
		$host			= $dbHost['host'];
		$visiblename	= $dbHost['name'];
// display empty visible name if equal to host name
		if($visiblename == $host)
			$visiblename='';
		$status			= $dbHost['status'];

		$ipmi_authtype		= $dbHost['ipmi_authtype'];
		$ipmi_privilege		= $dbHost['ipmi_privilege'];
		$ipmi_username		= $dbHost['ipmi_username'];
		$ipmi_password		= $dbHost['ipmi_password'];

		$macros = $dbHost['macros'];
		$interfaces = $dbHost['interfaces'];
		$host_groups = zbx_objectValues($dbHost['groups'], 'groupid');

// BEGIN: HOSTS INVENTORY Section
		$host_inventory = $dbHost['inventory'];
		$inventory_mode = empty($host_inventory) ? HOST_INVENTORY_DISABLED : $dbHost['inventory']['inventory_mode'];
// END:   HOSTS INVENTORY Section

		$templates = array();
		foreach($original_templates as $tnum => $tpl){
			$templates[$tpl['templateid']] = $tpl['name'];
		}
	}

	$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
	$clear_templates = array_diff($clear_templates,array_keys($templates));
	natcasesort($templates);

	$frmHost = new CForm();
	$frmHost->setName('web.hosts.host.php.');
	$frmHost->addVar('form', get_request('form', 1));

	$from_rfr = get_request('form_refresh',0);
	$frmHost->addVar('form_refresh', $from_rfr+1);
	$frmHost->addVar('clear_templates', $clear_templates);

// HOST WIDGET {

	$hostList = new CFormList('hostlist');

	if($_REQUEST['hostid']>0) $frmHost->addVar('hostid', $_REQUEST['hostid']);
	if($_REQUEST['groupid']>0) $frmHost->addVar('groupid', $_REQUEST['groupid']);

	$hostTB = new CTextBox('host',$host,54);
	$hostTB->setAttribute('maxlength', 64);
	$hostList->addRow(_('Host name'), $hostTB);

	$visiblenameTB = new CTextBox('visiblename',$visiblename,54);
	$visiblenameTB->setAttribute('maxlength', 64);
	$hostList->addRow(_('Visible name'), $visiblenameTB);

	$grp_tb = new CTweenBox($frmHost, 'groups', $host_groups, 10);
	$all_groups = API::HostGroup()->get(array(
		'editable' => 1,
		'output' => API_OUTPUT_EXTEND
	));
	order_result($all_groups, 'name');
	foreach($all_groups as $group){
		$grp_tb->addItem($group['groupid'], $group['name']);
	}

	$hostList->addRow(_('Groups'),$grp_tb->get(_('In groups'), _('Other groups')));

	$newgroupTB = new CTextBox('newgroup', $newgroup);
	$newgroupTB->setAttribute('maxlength', 64);
	$hostList->addRow(array(new CLabel(_('New group'), 'newgroup'), BR(), $newgroupTB));

// interfaces
	if(empty($interfaces)){
		$interfaces = array(array(
			'ip' => '127.0.0.1',
			'dns' => '',
			'port' => 10050,
			'useip' => 1,
			'type' => 1,
			'items' => 0
		));
	}

	$ifTab = new CTable(null, 'formElementTable');
	$ifTab->addRow(array(_('IP address'),_('DNS name'),_('Connect to'),_('Port'),_('Type')));
	$ifTab->setAttribute('id', 'hostInterfaces');

	$jsInsert = '';
	foreach($interfaces as $inum => $interface){
		$jsInsert.= 'addInterfaceRow('.zbx_jsvalue($interface).');';
	}
	zbx_add_post_js('setTimeout(function(){'.$jsInsert.'}, 1);');

	$addButton = new CButton('add', _('Add'), 'javascript: addInterfaceRow({});');
	$addButton->setAttribute('class', 'link_menu');

	$col = new CCol(array($addButton));
	$col->setAttribute('colspan', 5);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'hostIterfacesFooter');

	$ifTab->addRow($buttonRow);

	$hostList->addRow(_('Interfaces'), new CDiv($ifTab, 'objectgroup inlineblock border_dotted ui-corner-all'));

//Proxy
	$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);
	$cmbProxy->addItem(0, S_NO_PROXY);

	$options = array('output' => API_OUTPUT_EXTEND);
	$db_proxies = API::Proxy()->get($options);
	order_result($db_proxies, 'host');

	foreach($db_proxies as $proxy){
		$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
	}

	$hostList->addRow(_('Monitored by proxy'), $cmbProxy);
//----------

	$cmbStatus = new CComboBox('status',$status);
	$cmbStatus->addItem(HOST_STATUS_MONITORED,	_('Monitored'));
	$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	_('Not monitored'));

	$hostList->addRow(_('Status'),$cmbStatus);

	if($_REQUEST['form'] == 'full_clone'){
		// host items
		$hostItems = API::Item()->get(array(
			'hostids' => $_REQUEST['hostid'],
			'inherited' => false,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($hostItems)){
			$itemsList = array();
			foreach($hostItems as $hostItem){
				$itemsList[$hostItem['itemid']] = itemName($hostItem);
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
			'selectItems' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
			'expandDescription' => true,
		));

		if(!empty($hostTriggers)){
			$triggersList = array();

			foreach($hostTriggers as $hostTrigger){
				if (httpitemExists($hostTrigger['items']))
					continue;

				$triggersList[$hostTrigger['triggerid']] = $hostTrigger['description'];
			}

			if(!empty($triggersList)){
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
			'selectHosts' => API_OUTPUT_REFER,
			'selectItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($hostGraphs)){
			$graphsList = array();
			foreach($hostGraphs as $hostGraph){
				if(count($hostGraph['hosts']) > 1)
					continue;

				if (httpitemExists($hostGraph['items']))
					continue;

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

// Discovery rules
		$hostDiscoveryRuleids = array();

		$hostDiscoveryRules = API::DiscoveryRule()->get(array(
			'inherited' => false,
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($hostDiscoveryRules)){
			$discoveryRuleList = array();
			foreach($hostDiscoveryRules as $discoveryRule){
				$discoveryRuleList[$discoveryRule['itemid']] = itemName($discoveryRule);
			}
			order_result($discoveryRuleList);
			$hostDiscoveryRuleids = array_keys($discoveryRuleList);

			$listBox = new CListBox('discoveryRules', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($discoveryRuleList);

			$hostList->addRow(_('Discovery rules'), $listBox);
		}

// Item prototypes
		$hostItemPrototypes = API::Itemprototype()->get(array(
			'hostids' => $_REQUEST['hostid'],
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($hostItemPrototypes)){
			$prototypeList = array();
			foreach($hostItemPrototypes as $itemPrototype){
				$prototypeList[$itemPrototype['itemid']] = itemName($itemPrototype);
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
			'output' => API_OUTPUT_EXTEND,
			'expandDescription' => true,
		));
		if(!empty($hostTriggerPrototypes)){
			$prototypeList = array();
			foreach($hostTriggerPrototypes as $triggerPrototype){
				$prototypeList[$triggerPrototype['triggerid']] = $triggerPrototype['description'];
			}
			order_result($prototypeList);

			$listBox = new CListBox('triggerprototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$hostList->addRow(_('Trigger prototypes'), $listBox);
		}

// Graph prototypes
		$hostGraphPrototypes = API::GraphPrototype()->get(array(
			'hostids' => $_REQUEST['hostid'],
			'discoveryids' => $hostDiscoveryRuleids,
			'inherited' => false,
			'selectHosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
		));
		if(!empty($hostGraphPrototypes)){
			$prototypeList = array();
			foreach($hostGraphPrototypes as $graphPrototype){
				if(count($graphPrototype['hosts']) == 1){
					$prototypeList[$graphPrototype['graphid']] = $graphPrototype['name'];
				}
			}
			order_result($prototypeList);

			$listBox = new CListBox('graphPrototypes', null, 8);
			$listBox->setAttribute('disabled', 'disabled');
			$listBox->addItems($prototypeList);

			$hostList->addRow(_('Graph prototypes'), $listBox);
		}
	}

	$divTabs->addTab('hostTab', _('Host'), $hostList);
// } HOST WIDGET

// TEMPLATES{
	$tmplList = new CFormList('tmpllist');

	foreach($templates as $tid => $temp_name){
		$frmHost->addVar('templates['.$tid.']', $temp_name);
		$tmplList->addRow($temp_name, array(
			new CSubmit('unlink['.$tid.']', _('Unlink'), null, 'link_menu'),
			SPACE, SPACE,
			isset($original_templates[$tid]) ? new CSubmit('unlink_and_clear['.$tid.']', _('Unlink and clear'), null, 'link_menu') : SPACE
		));
	}

	$tmplAdd = new CButton('add', _('Add'), "return PopUp('popup.php?dstfrm=".$frmHost->getName().
			"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
			url_param($templates,false,'existed_templates')."',450,450)",
			'link_menu');

	$tmplList->addRow($tmplAdd, SPACE);

	$divTabs->addTab('templateTab', _('Templates'), $tmplList);
// } TEMPLATES

// IPMI TAB {
	$ipmiList = new CFormList('ipmilist');

	$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
	$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
	$cmbIPMIAuthtype->setAttribute('size', 7);
	$cmbIPMIAuthtype->addStyle('width: 170px;');
	$ipmiList->addRow(_('Authentication algorithm'), $cmbIPMIAuthtype);

	$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
	$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
	$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
	$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
	$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
	$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);
	$cmbIPMIPrivilege->setAttribute('size', 5);
	$cmbIPMIPrivilege->addStyle('width: 170px;');
	$ipmiList->addRow(_('Privilege level'), $cmbIPMIPrivilege);

	$ipmiList->addRow(_('Username'), new CTextBox('ipmi_username', $ipmi_username, 20));
	$ipmiList->addRow(_('Password'), new CTextBox('ipmi_password', $ipmi_password, 20));

	$divTabs->addTab('ipmiTab', _('IPMI'), $ipmiList);

// } IPMI TAB


// MACROS WIDGET {
// macros

	if(empty($macros)){
		$macros = array(array(
			'macro' => '',
			'value' => ''
		));
	}

	$macroTab = new CTable(null,'formElementTable');
	$macroTab->addRow(array(S_MACRO, SPACE, S_VALUE));
	$macroTab->setAttribute('id', 'userMacros');

	$jsInsert = '';
	foreach($macros as $inum => $macro){
		if(!empty($jsInsert) && zbx_empty($macro['macro']) && zbx_empty($macro['value'])) continue;

		$jsInsert.= 'addMacroRow('.zbx_jsvalue($macro).');';
	}
	zbx_add_post_js($jsInsert);

	$addButton = new CButton('add', _('Add'), 'javascript: addMacroRow({});');
	$addButton->setAttribute('class', 'link_menu');

	$col = new CCol(array($addButton));
	$col->setAttribute('colspan', 4);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'userMacroFooter');

	$macroTab->addRow($buttonRow);

	$macrolist = new CFormList('macrolist');
	$macrolist->addRow($macroTab);

	$divTabs->addTab('macroTab', _('Macros'), $macrolist);
// } MACROS WIDGET

	$inventoryFormList = new CFormList('inventorylist');

	// radio buttons for inventory type choice
	$inventoryTypeRadioButton = array(
		new CRadioButton(
			'inventory_mode',
			HOST_INVENTORY_DISABLED,
			null, // class
			'host_inventory_radio_'.HOST_INVENTORY_DISABLED,
			$inventory_mode == HOST_INVENTORY_DISABLED // checked?
		),
		new CLabel(_('Disabled'), 'host_inventory_radio_'.HOST_INVENTORY_DISABLED),

		new CRadioButton(
			'inventory_mode',
			HOST_INVENTORY_MANUAL,
			null,
			'host_inventory_radio_'.HOST_INVENTORY_MANUAL,
			$inventory_mode == HOST_INVENTORY_MANUAL
		),
		new CLabel(_('Manual'), 'host_inventory_radio_'.HOST_INVENTORY_MANUAL),

		new CRadioButton(
			'inventory_mode',
			HOST_INVENTORY_AUTOMATIC,
			null,
			'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC,
			$inventory_mode == HOST_INVENTORY_AUTOMATIC
		),
		new CLabel(_('Automatic'), 'host_inventory_radio_'.HOST_INVENTORY_AUTOMATIC),
	);
	$inventoryFormList->addRow(new CDiv($inventoryTypeRadioButton, 'jqueryinputset'));

	$hostInventoryTable = DB::getSchema('host_inventory');
	$hostInventoryFields = getHostInventories();

	foreach($hostInventoryFields as $inventoryNo => $inventoryInfo){
		if(!isset($host_inventory[$inventoryInfo['db_field']])){
			$host_inventory[$inventoryInfo['db_field']] = '';
		}

		if($hostInventoryTable['fields'][$inventoryInfo['db_field']]['type'] == DB::FIELD_TYPE_TEXT){
			$input = new CTextArea('host_inventory['.$inventoryInfo['db_field'].']', $host_inventory[$inventoryInfo['db_field']]);
			$input->addStyle('width: 64em;');
		}
		else{
			$fieldLength = $hostInventoryTable['fields'][$inventoryInfo['db_field']]['length'];
			$input = new CTextBox('host_inventory['.$inventoryInfo['db_field'].']', $host_inventory[$inventoryInfo['db_field']]);
			$input->setAttribute('maxlength', $fieldLength);
			$input->addStyle('width: '.($fieldLength > 64 ? 64 : $fieldLength).'em;');
		}
		if($inventory_mode == HOST_INVENTORY_DISABLED){
			$input->setAttribute('disabled', 'disabled');
		}

		// link to populating item at the right side (if any)
		if(isset($hostItemsToInventory[$inventoryNo])){
			$itemName = itemName($hostItemsToInventory[$inventoryNo]);
			$populatingLink = new CLink($itemName, 'items.php?form=update&itemid='.$hostItemsToInventory[$inventoryNo]['itemid']);
			$populatingLink->setAttribute('title', _s('This field is automatically populated by item "%s".', $itemName));
			$populatingItemCell = array(' &larr; ', $populatingLink);
			$input->addClass('linked_to_item'); // this will be used for disabling fields via jquery
			if($inventory_mode == HOST_INVENTORY_AUTOMATIC){
				$input->setAttribute('disabled', 'disabled');
			}
		}
		else{
			$populatingItemCell = '';
		}
		$populatingItem = new CSpan(
			$populatingItemCell,
			'populating_item'
		);
		$input->addStyle('float: left;');
		// those links are visible only in automatic mode
		if($inventory_mode != HOST_INVENTORY_AUTOMATIC){
			$populatingItem->addStyle("display:none");
		}

		$inventoryFormList->addRow($inventoryInfo['title'], array($input, $populatingItem));
	}

	// clearing the float
	$clearFixDiv = new CDiv();
	$clearFixDiv->addStyle("clear: both;");
	$inventoryFormList->addRow('', $clearFixDiv);

	$divTabs->addTab('inventoryTab', _('Host inventory'), $inventoryFormList);
// } INVENTORY WIDGET

	$frmHost->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(($_REQUEST['hostid']>0) && ($_REQUEST['form'] != 'full_clone')){
		$others[] = new CSubmit('clone', _('Clone'));
		$others[] = new CSubmit('full_clone', _('Full clone'));
		$others[] = new CButtonDelete(S_DELETE_SELECTED_HOST_Q, url_param('form').url_param('hostid').url_param('groupid'));
	}
	$others[] = new CButtonCancel(url_param('groupid'));

	$frmHost->addItem(makeFormFooter($main, $others));

return $frmHost;
?>
