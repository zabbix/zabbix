<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
	include('include/templates/hosts.js.php');
	include('include/templates/macros.js.php');
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
	$status		= get_request('status',	HOST_STATUS_MONITORED);
	$proxy_hostid	= get_request('proxy_hostid','');

	$useipmi	= get_request('useipmi','no');
	$ipmi_authtype	= get_request('ipmi_authtype',-1);
	$ipmi_privilege	= get_request('ipmi_privilege',2);
	$ipmi_username	= get_request('ipmi_username','');
	$ipmi_password	= get_request('ipmi_password','');

	$useprofile = get_request('useprofile','no');

	$devicetype	= get_request('devicetype','');
	$name		= get_request('name','');
	$os			= get_request('os','');
	$serialno	= get_request('serialno','');
	$tag		= get_request('tag','');
	$macaddress	= get_request('macaddress','');
	$hardware	= get_request('hardware','');
	$software	= get_request('software','');
	$contact	= get_request('contact','');
	$location	= get_request('location','');
	$notes		= get_request('notes','');

	$_REQUEST['hostid'] = get_request('hostid', 0);
// BEGIN: HOSTS PROFILE EXTENDED Section
	$useprofile_ext		= get_request('useprofile_ext','no');
	$ext_host_profiles	= get_request('ext_host_profiles',array());
// END:   HOSTS PROFILE EXTENDED Section

	$macros = get_request('macros',array());
	$interfaces = get_request('interfaces',array());
	$templates = get_request('templates',array());
	$clear_templates = get_request('clear_templates',array());

	$frm_title = S_HOST;
	if($_REQUEST['hostid']>0){
		$dbHosts = CHost::get(array(
			'hostids' => $_REQUEST['hostid'],
			'selectParentTemplates' => API_OUTPUT_EXTEND,
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'select_profile' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		));
		$dbHost = reset($dbHosts);

		$frm_title	.= SPACE.' ['.$dbHost['host'].']';
		$original_templates = $dbHost['parentTemplates'];
	}
	else{
		$original_templates = array();
	}

	if(($_REQUEST['hostid']>0) && !isset($_REQUEST['form_refresh'])){
		

		$proxy_hostid	= $dbHost['proxy_hostid'];
		$host			= $dbHost['host'];
		$status			= $dbHost['status'];

		$useipmi		= $dbHost['useipmi'] ? 'yes' : 'no';
		$ipmi_authtype		= $dbHost['ipmi_authtype'];
		$ipmi_privilege		= $dbHost['ipmi_privilege'];
		$ipmi_username		= $dbHost['ipmi_username'];
		$ipmi_password		= $dbHost['ipmi_password'];

		$macros = $dbHost['macros'];
		$interfaces = $dbHost['interfaces'];

// add groups
		$options = array('hostids' => $_REQUEST['hostid']);
		$host_groups = CHostGroup::get($options);
		$host_groups = zbx_objectValues($host_groups, 'groupid');

// read profile
		$useprofile = 'no';
		$db_profile = $dbHost['profile'];
		if(!empty($db_profile)){
			$useprofile = 'yes';

			$devicetype	= $db_profile['devicetype'];
			$name		= $db_profile['name'];
			$os			= $db_profile['os'];
			$serialno	= $db_profile['serialno'];
			$tag		= $db_profile['tag'];
			$macaddress	= $db_profile['macaddress'];
			$hardware	= $db_profile['hardware'];
			$software	= $db_profile['software'];
			$contact	= $db_profile['contact'];
			$location	= $db_profile['location'];
			$notes		= $db_profile['notes'];
		}

// BEGIN: HOSTS PROFILE EXTENDED Section
		$ext_host_profiles = $dbHost['profile_ext'];
		$useprofile_ext = empty($ext_host_profiles) ? 'no' : 'yes';
// END:   HOSTS PROFILE EXTENDED Section

		$templates = array();
		foreach($original_templates as $tnum => $tpl){
			$templates[$tpl['templateid']] = $tpl['host'];
		}
	}

	$ext_profiles_fields = array(
		'device_alias'=>S_DEVICE_ALIAS,
		'device_type'=>S_DEVICE_TYPE,
		'device_chassis'=>S_DEVICE_CHASSIS,
		'device_os'=>S_DEVICE_OS,
		'device_os_short'=>S_DEVICE_OS_SHORT,
		'device_hw_arch'=>S_DEVICE_HW_ARCH,
		'device_serial'=>S_DEVICE_SERIAL,
		'device_model'=>S_DEVICE_MODEL,
		'device_tag'=>S_DEVICE_TAG,
		'device_vendor'=>S_DEVICE_VENDOR,
		'device_contract'=>S_DEVICE_CONTRACT,
		'device_who'=>S_DEVICE_WHO,
		'device_status'=>S_DEVICE_STATUS,
		'device_app_01'=>S_DEVICE_APP_01,
		'device_app_02'=>S_DEVICE_APP_02,
		'device_app_03'=>S_DEVICE_APP_03,
		'device_app_04'=>S_DEVICE_APP_04,
		'device_app_05'=>S_DEVICE_APP_05,
		'device_url_1'=>S_DEVICE_URL_1,
		'device_url_2'=>S_DEVICE_URL_2,
		'device_url_3'=>S_DEVICE_URL_3,
		'device_networks'=>S_DEVICE_NETWORKS,
		'device_notes'=>S_DEVICE_NOTES,
		'device_hardware'=>S_DEVICE_HARDWARE,
		'device_software'=>S_DEVICE_SOFTWARE,
		'ip_subnet_mask'=>S_IP_SUBNET_MASK,
		'ip_router'=>S_IP_ROUTER,
		'ip_macaddress'=>S_IP_MACADDRESS,
		'oob_ip'=>S_OOB_IP,
		'oob_subnet_mask'=>S_OOB_SUBNET_MASK,
		'oob_router'=>S_OOB_ROUTER,
		'date_hw_buy'=>S_DATE_HW_BUY,
		'date_hw_install'=>S_DATE_HW_INSTALL,
		'date_hw_expiry'=>S_DATE_HW_EXPIRY,
		'date_hw_decomm'=>S_DATE_HW_DECOMM,
		'site_street_1'=>S_SITE_STREET_1,
		'site_street_2'=>S_SITE_STREET_2,
		'site_street_3'=>S_SITE_STREET_3,
		'site_city'=>S_SITE_CITY,
		'site_state'=>S_SITE_STATE,
		'site_country'=>S_SITE_COUNTRY,
		'site_zip'=>S_SITE_ZIP,
		'site_rack'=>S_SITE_RACK,
		'site_notes'=>S_SITE_NOTES,
		'poc_1_name'=>S_POC_1_NAME,
		'poc_1_email'=>S_POC_1_EMAIL,
		'poc_1_phone_1'=>S_POC_1_PHONE_1,
		'poc_1_phone_2'=>S_POC_1_PHONE_2,
		'poc_1_cell'=>S_POC_1_CELL,
		'poc_1_screen'=>S_POC_1_SCREEN,
		'poc_1_notes'=>S_POC_1_NOTES,
		'poc_2_name'=>S_POC_2_NAME,
		'poc_2_email'=>S_POC_2_EMAIL,
		'poc_2_phone_1'=>S_POC_2_PHONE_1,
		'poc_2_phone_2'=>S_POC_2_PHONE_2,
		'poc_2_cell'=>S_POC_2_CELL,
		'poc_2_screen'=>S_POC_2_SCREEN,
		'poc_2_notes'=>S_POC_2_NOTES
	);


	foreach($ext_profiles_fields as $field => $caption){
		if(!isset($ext_host_profiles[$field])) $ext_host_profiles[$field] = '';
	}

	$clear_templates = array_intersect($clear_templates, array_keys($original_templates));
	$clear_templates = array_diff($clear_templates,array_keys($templates));
	natcasesort($templates);

	$frmHost = new CForm('hosts.php', 'post');
	$frmHost->setName('web.hosts.host.php.');
//		$frmHost->setHelp('web.hosts.host.php');
	$frmHost->addVar('form', get_request('form', 1));
	$from_rfr = get_request('form_refresh',0);
	$frmHost->addVar('form_refresh', $from_rfr+1);
	$frmHost->addVar('clear_templates', $clear_templates);

// HOST WIDGET {

	$hostList = new CFormList('hostlist');

	if($_REQUEST['hostid']>0) $frmHost->addVar('hostid', $_REQUEST['hostid']);
	if($_REQUEST['groupid']>0) $frmHost->addVar('groupid', $_REQUEST['groupid']);

	$hostList->addRow(S_NAME, new CTextBox('host',$host,54));

	$grp_tb = new CTweenBox($frmHost, 'groups', $host_groups, 10);
	$all_groups = CHostGroup::get(array(
		'editable' => 1,
		'output' => API_OUTPUT_EXTEND
	));
	order_result($all_groups, 'name');
	foreach($all_groups as $group){
		$grp_tb->addItem($group['groupid'], $group['name']);
	}

	$hostList->addRow(S_GROUPS,$grp_tb->get(S_IN_GROUPS, S_OTHER_GROUPS));
	$hostList->addRow(S_NEW_GROUP, new CTextBox('newgroup',$newgroup));

// interfaces
	if(empty($interfaces)){
		$interfaces = array(array(
			'ip' => '127.0.0.1',
			'dns' => '',
			'port' => 10050,
			'useip' => 1
		));
	}

	$ifTab = new CTable();
	$ifTab->addRow(array(S_IP_ADDRESS,S_DNS_NAME,S_PORT,S_CONNECT_TO));
	$ifTab->setAttribute('id', 'hostInterfaces');

	$jsInsert = '';
	foreach($interfaces as $inum => $interface){
		$jsInsert.= 'addInterfaceRow('.zbx_jsvalue($interface).');';
	}
	zbx_add_post_js($jsInsert);

	$addButton = new CButton('add', S_ADD, 'javascript: addInterfaceRow({});',false);
	$addButton->setAttribute('class', 'link_menu');

	$col = new CCol(array($addButton));
	$col->setAttribute('colspan', 5);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'hostIterfacesFooter');

	$ifTab->addRow($buttonRow);
	
	$hostList->addRow(S_INTERFACES, $ifTab);

//Proxy
	$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);
	$cmbProxy->addItem(0, S_NO_PROXY);

	$options = array('output' => API_OUTPUT_EXTEND);
	$db_proxies = CProxy::get($options);
	order_result($db_proxies, 'host');

	foreach($db_proxies as $proxy){
		$cmbProxy->addItem($proxy['proxyid'], $proxy['host']);
	}

	$hostList->addRow(S_MONITORED_BY_PROXY, $cmbProxy);
//----------

	$cmbStatus = new CComboBox('status',$status);
	$cmbStatus->addItem(HOST_STATUS_MONITORED,	S_MONITORED);
	$cmbStatus->addItem(HOST_STATUS_NOT_MONITORED,	S_NOT_MONITORED);

	$hostList->addRow(S_STATUS,$cmbStatus);

	$hostList->addRow(S_USEIPMI, new CCheckBox('useipmi', $useipmi, 'submit()'));

	if($useipmi == 'yes'){
		$cmbIPMIAuthtype = new CComboBox('ipmi_authtype', $ipmi_authtype);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_DEFAULT,	S_AUTHTYPE_DEFAULT);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_NONE,		S_AUTHTYPE_NONE);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD2,		S_AUTHTYPE_MD2);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_MD5,		S_AUTHTYPE_MD5);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_STRAIGHT,	S_AUTHTYPE_STRAIGHT);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_OEM,		S_AUTHTYPE_OEM);
		$cmbIPMIAuthtype->addItem(IPMI_AUTHTYPE_RMCP_PLUS,	S_AUTHTYPE_RMCP_PLUS);
		$hostList->addRow(S_IPMI_AUTHTYPE, $cmbIPMIAuthtype);

		$cmbIPMIPrivilege = new CComboBox('ipmi_privilege', $ipmi_privilege);
		$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_CALLBACK,	S_PRIVILEGE_CALLBACK);
		$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_USER,		S_PRIVILEGE_USER);
		$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OPERATOR,	S_PRIVILEGE_OPERATOR);
		$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_ADMIN,	S_PRIVILEGE_ADMIN);
		$cmbIPMIPrivilege->addItem(IPMI_PRIVILEGE_OEM,		S_PRIVILEGE_OEM);

		$hostList->addRow(S_IPMI_PRIVILEGE, $cmbIPMIPrivilege);
		$hostList->addRow(S_IPMI_USERNAME, new CTextBox('ipmi_username', $ipmi_username, 16));
		$hostList->addRow(S_IPMI_PASSWORD, new CTextBox('ipmi_password', $ipmi_password, 20));
	}
	else{
		$frmHost->addVar('ipmi_authtype', $ipmi_authtype);
		$frmHost->addVar('ipmi_privilege', $ipmi_privilege);
		$frmHost->addVar('ipmi_username', $ipmi_username);
		$frmHost->addVar('ipmi_password', $ipmi_password);
	}

	if($_REQUEST['form'] == 'full_clone'){
// Host items
		$host_items = CItem::get(array(
			'hostids' => $_REQUEST['hostid'],
			'inherited' => 0,
			'webitems' => 1,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
			'output' => API_OUTPUT_EXTEND,
		));

		if(!empty($host_items)){
			$items_lbx = new CListBox('items', null, 8);
			$items_lbx->setAttribute('disabled', 'disabled');

			order_result($host_items, 'description');
			foreach($host_items as $hitem){
				$items_lbx->addItem($hitem['itemid'], item_description($hitem));
			}
			
			$hostList->addRow(S_ITEMS, $items_lbx);
		}

// Host triggers
		$options = array(
			'inherited' => 0,
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
			'expandDescription' => true,
		);
		$host_triggers = CTrigger::get($options);

		if(!empty($host_triggers)){
			$trig_lbx = new CListBox('triggers' ,null, 8);
			$trig_lbx->setAttribute('disabled', 'disabled');

			order_result($host_triggers, 'description');
			foreach($host_triggers as $htrigger){
				$trig_lbx->addItem($htrigger['triggerid'], $htrigger['description']);
			}
			$hostList->addRow(S_TRIGGERS, $trig_lbx);
		}
// Host graphs
		$options = array(
			'inherited' => 0,
			'hostids' => $_REQUEST['hostid'],
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CHILD)),
			'selectHosts' => API_OUTPUT_REFER,
			'output' => API_OUTPUT_EXTEND,
		);
		$host_graphs = CGraph::get($options);

		if(!empty($host_graphs)){
			$graphs_lbx = new CListBox('graphs', null, 8);
			$graphs_lbx->setAttribute('disabled', 'disabled');

			order_result($host_graphs, 'name');
			foreach($host_graphs as $hgraph){
				if(count($hgraph['hosts']) > 1) continue;
				$graphs_lbx->addItem($hgraph['graphid'], $hgraph['name']);
			}

			$hostList->addRow(S_GRAPHS, $graphs_lbx);
		}

// discovery rules
		$options = array(
			'inherited' => 0,
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY),
			'webitems' => 1,
		);
		$host_items = CItem::get($options);

		if(!empty($host_items)){
			$items_lbx = new CListBox('items', null, 8);
			$items_lbx->setAttribute('disabled', 'disabled');

			order_result($host_items, 'description');
			foreach($host_items as $hitem){
				$items_lbx->addItem($hitem['itemid'], item_description($hitem));
			}
			$hostList->addRow(S_DISCOVERY_RULES, $items_lbx);
		}
	}

	$divTabs->addTab('hostTab', S_HOST, $hostList);
// } HOST WIDGET

// TEMPLATES{

	$tmplList = new CFormList('tmpllist');

	foreach($templates as $id => $temp_name){
		$frmHost->addVar('templates['.$id.']', $temp_name);
		$tmplList->addRow($temp_name, new CCheckBox('templates_rem['.$id.']', 'no', null, $id));
	}

	$tmplAdd = new CButton('add', S_ADD, "return PopUp('popup.php?dstfrm=".$frmHost->getName().
			"&dstfld1=new_template&srctbl=templates&srcfld1=hostid&srcfld2=host".
			url_param($templates,false,'existed_templates')."',450,450)",
			'T');
	$tmplAdd->setAttribute('class', 'link_menu');

	$tmplUnlink = new CButton('unlink', S_UNLINK);
	$tmplUnlink->setAttribute('class', 'link_menu');

	$tmplUnlinkClear = new CButton('unlink_and_clear', S_UNLINK_AND_CLEAR);
	$tmplUnlinkClear->setAttribute('class', 'link_menu');

	$footer = new CDiv(array($tmplAdd,$tmplUnlink,$tmplUnlinkClear));

	$tmplList->addRow($footer);

	$divTabs->addTab('templateTab', S_TEMPLATES, $tmplList);
// } TEMPLATES


// MACROS WIDGET {
// macros
	if(empty($macros)){
		$macros = array(array(
			'macro' => '',
			'value' => ''
		));
	}

	$macroTab = new CTable();
	$macroTab->addRow(array(S_MACRO, SPACE, S_VALUE));
	$macroTab->setAttribute('id', 'userMacros');
//SDII($macros);
	$jsInsert = '';
	foreach($macros as $inum => $macro){
		$jsInsert.= 'addMacroRow('.zbx_jsvalue($macro).');';
	}
	zbx_add_post_js($jsInsert);

	$addButton = new CButton('add', S_ADD, 'javascript: addMacroRow({});',false);
	$addButton->setAttribute('class', 'link_menu');

	$col = new CCol(array($addButton));
	$col->setAttribute('colspan', 4);

	$buttonRow = new CRow($col);
	$buttonRow->setAttribute('id', 'userMacroFooter');

	$macroTab->addRow($buttonRow);

	$macrolist = new CFormList('macrolist');
	$macrolist->addRow($macroTab);

	$divTabs->addTab('macroTab', S_MACROS, $macrolist);
// } MACROS WIDGET


// PROFILE WIDGET {
	$profileList = new CFormList('profilelist');

	$profileList->addRow(S_USE_PROFILE,new CCheckBox('useprofile',$useprofile,'submit()'));

	if($useprofile == 'yes'){
		$profileList->addRow(S_DEVICE_TYPE,new CTextBox('devicetype',$devicetype,61));
		$profileList->addRow(S_NAME,new CTextBox('name',$name,61));
		$profileList->addRow(S_OS,new CTextBox('os',$os,61));
		$profileList->addRow(S_SERIALNO,new CTextBox('serialno',$serialno,61));
		$profileList->addRow(S_TAG,new CTextBox('tag',$tag,61));
		$profileList->addRow(S_MACADDRESS,new CTextBox('macaddress',$macaddress,61));
		$profileList->addRow(S_HARDWARE,new CTextArea('hardware',$hardware,60,4));
		$profileList->addRow(S_SOFTWARE,new CTextArea('software',$software,60,4));
		$profileList->addRow(S_CONTACT,new CTextArea('contact',$contact,60,4));
		$profileList->addRow(S_LOCATION,new CTextArea('location',$location,60,4));
		$profileList->addRow(S_NOTES,new CTextArea('notes',$notes,60,4));
	}
	else{
		$frmHost->addVar('devicetype', $devicetype);
		$frmHost->addVar('name',$name);
		$frmHost->addVar('os',$os);
		$frmHost->addVar('serialno',$serialno);
		$frmHost->addVar('tag',	$tag);
		$frmHost->addVar('macaddress',$macaddress);
		$frmHost->addVar('hardware',$hardware);
		$frmHost->addVar('software',$software);
		$frmHost->addVar('contact',$contact);
		$frmHost->addVar('location',$location);
		$frmHost->addVar('notes',$notes);
	}

	$divTabs->addTab('profileTab', S_HOST_PROFILE, $profileList);
// } PROFILE WIDGET

// EXT PROFILE WIDGET {
	$profileexlist =  new CFormList('profileexlist');
	$profileexlist->addRow(S_USE_EXTENDED_PROFILE,new CCheckBox('useprofile_ext',$useprofile_ext,'submit()','yes'));

	foreach($ext_profiles_fields as $prof_field => $caption){
		if($useprofile_ext == 'yes'){
			$profileexlist->addRow($caption,new CTextBox('ext_host_profiles['.$prof_field.']',$ext_host_profiles[$prof_field],80));
		}
		else{
			$frmHost->addVar('ext_host_profiles['.$prof_field.']',	$ext_host_profiles[$prof_field]);
		}
	}

	$divTabs->addTab('profileExTab', S_EXTENDED_HOST_PROFILE, $profileexlist);
// } EXT PROFILE WIDGET

$frmHost->addItem($divTabs);

// Footer
	$host_footer = array();
	$host_footer[] = new CButton('save', S_SAVE);
	if(($_REQUEST['hostid']>0) && ($_REQUEST['form'] != 'full_clone')){
		array_push($host_footer, SPACE, new CButton('clone', S_CLONE), SPACE, new CButton('full_clone', S_FULL_CLONE), SPACE,
			new CButtonDelete(S_DELETE_SELECTED_HOST_Q, url_param('form').url_param('hostid').url_param('groupid')));
	}
	array_push($host_footer, SPACE, new CButtonCancel(url_param('groupid')));

	$frmHost->addItem(new CDiv($host_footer, 'center'));

return $frmHost;
?>