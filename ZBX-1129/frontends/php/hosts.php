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
require_once('include/config.inc.php');
require_once('include/maintenances.inc.php');
require_once('include/forms.inc.php');
require_once('include/ident.inc.php');

if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'export') && isset($_REQUEST['hosts'])){
	$EXPORT_DATA = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_hosts_export.xml';

	require_once('include/export.inc.php');
}
else{
	$EXPORT_DATA = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = 'S_HOSTS';
	$page['file'] = 'hosts.php';
	$page['hist_arg'] = array('groupid');
}

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
//ARRAYS
		'hosts'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
// host
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS, 	DB_ID,				NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			'isset({form})&&({form}=="update")'),
		'host'=>			array(T_ZBX_STR, O_OPT,	null,   NOT_EMPTY,		'isset({save})'),
		'proxy_hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({save})'),
		'dns'=>				array(T_ZBX_STR, O_OPT,	null,	null,			'isset({save})'),
		'useip'=>			array(T_ZBX_STR, O_OPT, null,	IN('0,1'),		'isset({save})'),
		'ip'=>				array(T_ZBX_IP,  O_OPT, null,	null,			'isset({save})'),
		'port'=>			array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535),	'isset({save})'),
		'status'=>			array(T_ZBX_INT, O_OPT,	null,	IN('0,1,3'),		'isset({save})'),

		'newgroup'=>		array(T_ZBX_STR, O_OPT, null,   null,	null),
		'templates'=>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'templates_rem'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	null),
		'clear_templates'=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,	null),

		'useipmi'=>			array(T_ZBX_STR, O_OPT,	NULL, NULL,				NULL),
		'ipmi_ip'=>			array(T_ZBX_STR, O_OPT,	NULL, NOT_EMPTY,				'isset({useipmi})&&isset({save})'),
		'ipmi_port'=>		array(T_ZBX_INT, O_OPT,	NULL, BETWEEN(0,65535),	NULL),
		'ipmi_authtype'=>	array(T_ZBX_INT, O_OPT,	NULL, BETWEEN(-1,6),	NULL),
		'ipmi_privilege'=>	array(T_ZBX_INT, O_OPT,	NULL, BETWEEN(0,5),		NULL),
		'ipmi_username'=>	array(T_ZBX_STR, O_OPT,	NULL, NULL,				NULL),
		'ipmi_password'=>	array(T_ZBX_STR, O_OPT,	NULL, NULL,				NULL),

		'mass_clear_tpls'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),

		'useprofile'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'devicetype'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'name'=>			array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'os'=>				array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'serialno'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'tag'=>				array(T_ZBX_STR, O_OPT, NULL,			NULL,	NULL),
		'macaddress'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'hardware'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'software'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'contact'=>			array(T_ZBX_STR, O_OPT, NULL,			NULL,	NULL),
		'location'=>		array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'notes'=>			array(T_ZBX_STR, O_OPT, NULL, 			NULL,	NULL),
		'host_profile'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,	NULL,   NULL),

		'useprofile_ext'=>		array(T_ZBX_STR, O_OPT, null,   null,	null),
		'ext_host_profiles'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,   null,   null),

		'macros_rem'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	null),
		'macros'=>				array(T_ZBX_STR, O_OPT, P_SYS,   null,	null),
		'macro_new'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	'isset({macro_add})'),
		'value_new'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	'isset({macro_add})'),
		'macro_add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	null),
		'macros_del' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   null,	null),
// mass update
		'massupdate'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'visible'=>			array(T_ZBX_STR, O_OPT,	NULL, 	NULL,	NULL),
// actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// form
		'add_to_group'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,	null),
		'delete_from_group'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,	null),
		'unlink'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'unlink_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'masssave'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'full_clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
// other
		'form'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>		array(T_ZBX_STR, O_OPT, null,	null,	null),
// Import
		'rules' =>				array(T_ZBX_STR, O_OPT,	null,			DB_ID,	null),
		'import' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
// Filter
		'filter_set' =>			array(T_ZBX_STR, O_OPT,	P_ACT,	null,	null),

		'filter_host'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_ip'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_dns'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
		'filter_port'=>		array(T_ZBX_STR, O_OPT,  null,	null,	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})')
	);

// OUTER DATA
	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');

// PERMISSIONS
	if(get_request('groupid', 0) > 0){
		$groupids = available_groups($_REQUEST['groupid'], 1);
		if(empty($groupids)) access_deny();
	}

	if(get_request('hostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['hostid'], 1);
		if(empty($hostids)) access_deny();
	}
?>
<?php
	/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.hosts.filter.state', $_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------

	$hostids = get_request('hosts', array());

	if($EXPORT_DATA){
// SELECT HOSTS
		$params = array(
			'hostids' => $hostids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'select_profile' => 1
		);
		$hosts = CHost::get($params);
		order_result($hosts, 'host');

// SELECT HOST GROUPS
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$groups = CHostGroup::get($params);

// SELECT GRAPHS
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$graphs = CGraph::get($params);

// SELECT GRAPH ITEMS
		$graphids = zbx_objectValues($graphs, 'graphid');
		$params = array(
			'graphids' => $graphids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'expandData' => 1
		);
		$gitems = CGraphItem::get($params);

		$params = array(
			'itemids' => zbx_objectValues($gitems, 'itemid'),
			'preservekeys' => 1,
			'webitems' => true,
			'output' => API_OUTPUT_EXTEND
		);
		$items = CItem::get($params);

		foreach($gitems as $gnum => $gitem){
			if ($items[$gitem['itemid']]['type'] == ITEM_TYPE_HTTPTEST) {
				unset($graphs[$gitem['graphid']]);
				unset($gitems[$gitem['gitemid']]);
				continue;
			}
			$gitems[$gitem['gitemid']]['host_key_'] = $gitem['host'].':'.$gitem['key_'];
		}
// SELECT TEMPLATES
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$templates = CTemplate::get($params);

// SELECT MACROS
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$macros = CUserMacro::get($params);

// SELECT ITEMS
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$items = CItem::get($params);

// SELECT APPLICATIONS
		$itemids = zbx_objectValues($items, 'itemid');
//sdii($itemids);
		$params = array(
			'itemids' => $itemids,
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$applications = Capplication::get($params);
//sdii($applications);

// SELECT TRIGGERS
		$params = array(
			'hostids' => $hostids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1,
			'select_items' => API_OUTPUT_EXTEND,
			'select_dependencies' => API_OUTPUT_EXTEND,
			'expandData' => 1
		);
		$triggers = CTrigger::get($params);
		foreach($triggers as $tnum => $trigger){
			foreach ($trigger['items'] as $item) {
				if ($item['type'] == ITEM_TYPE_HTTPTEST) {
					unset($triggers[$tnum]);
					continue 2;
				}
			}
			$triggers[$trigger['triggerid']]['expression'] = explode_exp($trigger['expression']);
		}

// SELECT TRIGGER DEPENDENCIES
		$dependencies = array();
		foreach($triggers as $tnum => $trigger){
			if(!empty($trigger['dependencies'])){
				if(!isset($dependencies[$trigger['triggerid']])) $dependencies[$trigger['triggerid']] = array();

				$dependencies[$trigger['triggerid']]['trigger'] = $trigger;
				$dependencies[$trigger['triggerid']]['depends_on'] = $trigger['dependencies'];
			}
		}

// we do custom fields for export
		foreach($dependencies as $triggerid => $dep_data){
			$dependencies[$triggerid]['trigger']['host_description'] = $triggers[$triggerid]['host'].':'.$triggers[$triggerid]['description'];
			foreach($dep_data['depends_on'] as $dep_triggerid => $dep_trigger){
				$dependencies[$triggerid]['depends_on'][$dep_triggerid]['host_description'] = $dep_trigger['host'].':'.$dep_trigger['description'];
			}
		}


		$data = array(
			'hosts' => $hosts,
			'items' => $items,
			'items_applications' => $applications,
			'graphs' => $graphs,
			'graphs_items' => $gitems,
			'templates' => $templates,
			'macros' => $macros,
			'hosts_groups' => $groups,
			'triggers' => $triggers,
			'dependencies' => $dependencies
		);

		$xml = zbxXML::export($data);

		print($xml);
		exit();
	}

// IMPORT ///////////////////////////////////
	$rules = get_request('rules', array());
	if(!isset($_REQUEST['form_refresh'])){
		foreach(array('host', 'template', 'item', 'trigger', 'graph') as $key){
			$rules[$key]['exist'] = 1;
			$rules[$key]['missed'] = 1;
		}
	}

	if(isset($_FILES['import_file']) && is_file($_FILES['import_file']['tmp_name'])){
		require_once('include/export.inc.php');
		DBstart();
		$result = zbxXML::import($_FILES['import_file']['tmp_name']);
		if($result) $result = zbxXML::parseMain($rules);
		$result = DBend($result);
		show_messages($result, S_IMPORTED.SPACE.S_SUCCESSEFULLY_SMALL, S_IMPORT.SPACE.S_FAILED_SMALL);
	}

/* FILTER */
	if(isset($_REQUEST['filter_set'])){
		$_REQUEST['filter_ip'] = get_request('filter_ip');
		$_REQUEST['filter_dns'] = get_request('filter_dns');
		$_REQUEST['filter_host'] = get_request('filter_host');
		$_REQUEST['filter_port'] = get_request('filter_port');

		CProfile::update('web.hosts.filter_ip', $_REQUEST['filter_ip'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts.filter_dns', $_REQUEST['filter_dns'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts.filter_host', $_REQUEST['filter_host'], PROFILE_TYPE_STR);
		CProfile::update('web.hosts.filter_port', $_REQUEST['filter_port'], PROFILE_TYPE_STR);
	}
	else{
		$_REQUEST['filter_ip'] = CProfile::get('web.hosts.filter_ip');
		$_REQUEST['filter_dns'] = CProfile::get('web.hosts.filter_dns');
		$_REQUEST['filter_host'] = CProfile::get('web.hosts.filter_host');
		$_REQUEST['filter_port'] = CProfile::get('web.hosts.filter_port');
	}
?>
<?php
/************ ACTIONS FOR HOSTS ****************/
// UNLINK HOST
	if(isset($_REQUEST['templates_rem']) && (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear']))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		$unlink_templates = array_keys($_REQUEST['templates_rem']);
		if(isset($_REQUEST['unlink_and_clear'])){
			$_REQUEST['clear_templates'] = zbx_array_merge($_REQUEST['clear_templates'], $unlink_templates);
		}

		foreach($unlink_templates as $id)
			unset($_REQUEST['templates'][$id]);
	}
// CLONE HOST
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
// FULL CLONE HOST
	else if(isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])){
		$_REQUEST['form'] = 'full_clone';
	}
// HOST MASS UPDATE
	else if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['masssave'])){
		$hostids = get_request('hosts', array());
		$visible = get_request('visible', array());
		$_REQUEST['newgroup'] = get_request('newgroup', '');
		$_REQUEST['proxy_hostid'] = get_request('proxy_hostid', 0);
		$_REQUEST['templates'] = get_request('templates', array());

		try{
			DBstart();

			$hosts = array('hosts' => zbx_toObject($hostids, 'hostid'));

			$properties = array('port', 'useip', 'dns',	'ip', 'proxy_hostid', 'useipmi', 'ipmi_ip', 'ipmi_port', 'ipmi_authtype',
				'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'status');
			$new_values = array();
			foreach($properties as $property){
				if(isset($visible[$property])){
					if($property == 'useipmi')
						$new_values[$property] = isset($_REQUEST['useipmi']) ? 1 : 0;
					else
						$new_values[$property] = $_REQUEST[$property];
				}
			}

// PROFILES {{{
			if(isset($visible['useprofile'])){
				$new_values['profile'] = get_request('useprofile', false) ? get_request('host_profile', array()) : array();
			}

			if(isset($visible['useprofile_ext'])){
				$new_values['extendedProfile'] = get_request('useprofile_ext', false) ? get_request('ext_host_profiles', array()) : array();
			}
// }}} PROFILES

			$newgroup = array();
			if(isset($visible['newgroup']) && !empty($_REQUEST['newgroup'])){
				$result = CHostGroup::create(array('name' => $_REQUEST['newgroup']));
				if($result === false) throw new Exception();

				$newgroup = array('groupid' => reset($result['groupids']), 'name' => $_REQUEST['newgroup']);
			}

			$templates = array();
			if(isset($visible['template_table']) || isset($visible['template_table_r'])){
				$tplids = array_keys($_REQUEST['templates']);
				$templates = zbx_toObject($tplids, 'templateid');
			}

			if(isset($visible['groups'])){
				$hosts['groups'] = CHostGroup::get(array(
					'groupids' => get_request('groups', array()),
					'editable' => 1,
					'output' => API_OUTPUT_SHORTEN,
				));
				if(!empty($newgroup)){
					$hosts['groups'][] = $newgroup;
				}
			}
			if(isset($visible['template_table_r'])){
				if(isset($_REQUEST['mass_clear_tpls'])){
					$host_templates = CTemplate::get(array('hostids' => $hostids));
					$host_templateids = zbx_objectValues($host_templates, 'templateid');
					$templates_to_del = array_diff($host_templateids, $tplids);
					$hosts['templates_clear'] = zbx_toObject($templates_to_del, 'templateid');
				}
				$hosts['templates'] = $templates;
			}

			$result = CHost::massUpdate(array_merge($hosts, $new_values));
			if ($result === false) {
				throw new Exception();
			}

			$add = array();
			if(!empty($templates) && isset($visible['template_table'])){
				$add['templates'] = $templates;
			}
			if(!empty($newgroup) && !isset($visible['groups'])){
				$add['groups'][] = $newgroup;
			}
			if(!empty($add)){
				$add['hosts'] = $hosts['hosts'];

				$result = CHost::massAdd($add);
				if($result === false) throw new Exception();
			}

			DBend(true);

			show_messages(true, S_HOSTS.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HOSTS);

			unset($_REQUEST['massupdate']);
			unset($_REQUEST['form']);
			unset($_REQUEST['hosts']);

			$url = new CUrl();
			$path = $url->getPath();
			insert_js('cookie.eraseArray("'.$path.'")');
		}
		catch(Exception $e){
			DBend(false);
			show_messages(false, S_HOSTS.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HOSTS);
		}

		unset($_REQUEST['save']);
	}
// SAVE HOST
	elseif (isset($_REQUEST['save'])) {
		try {
			$templates = get_request('templates', array());
			$templates_clear = get_request('clear_templates', array());
			$groups = get_request('groups', array());

			if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
				access_deny();
			}

			if (isset($_REQUEST['hostid']) && $_REQUEST['form'] != 'full_clone') {
				$create_new = false;
				$msg_ok = S_HOST_UPDATED;
				$msg_fail = S_CANNOT_UPDATE_HOST;
			}
			else {
				$create_new = true;
				$msg_ok = S_HOST_ADDED;
				$msg_fail = S_CANNOT_ADD_HOST;
			}

			$clone_hostid = false;
			if ($_REQUEST['form'] == 'full_clone') {
				$create_new = true;
				$clone_hostid = $_REQUEST['hostid'];
			}

			$templates = array_keys($templates);
			$templates = zbx_toObject($templates, 'templateid');
			$templates_clear = zbx_toObject($templates_clear, 'templateid');

			// START SAVE TRANSACTION {{{
			DBstart();

			// create new group
			if (!zbx_empty($_REQUEST['newgroup'])) {
				$newGroup = CHostGroup::create(array('name' => $_REQUEST['newgroup']));
				if (!$newGroup) {
					throw new Exception();
				}
				$groups[] = reset($newGroup['groupids']);
			}
			$groups = zbx_toObject($groups, 'groupid');

			$macros = get_request('macros', array());
			foreach ($macros as $mnum => $macro) {
				if (zbx_empty($macro['value']) && zbx_empty($macro['macro'])) {
					unset($macros[$mnum]);
				}
			}

			$duplicatedMacros = array();
			foreach ($macros as $mnum => $macro) {
				// transform macros to uppercase {$aaa} => {$AAA}
				$macros[$mnum]['macro'] = zbx_strtoupper($macro['macro']);

				// search for duplicates items in new macros array
				foreach ($macros as $duplicateNumber => $duplicateNewMacro) {
					if ($mnum != $duplicateNumber && $macro['macro'] == $duplicateNewMacro['macro']) {
						$duplicatedMacros[] = '"'.$duplicateNewMacro['macro'].'"';
					}
				}
			}

			// validate duplicates macros
			if (!empty($duplicatedMacros)) {
				error(S_DUPLICATED_MACRO_FOUND.SPACE.implode(', ', array_unique($duplicatedMacros)));
				throw new Exception();
			}

			$host = array(
				'host' => $_REQUEST['host'],
				'port' => $_REQUEST['port'],
				'status' => $_REQUEST['status'],
				'useip' => $_REQUEST['useip'],
				'dns' => $_REQUEST['dns'],
				'ip' => $_REQUEST['ip'],
				'proxy_hostid' => get_request('proxy_hostid', 0),
				'useipmi' => isset($_REQUEST['useipmi']) ? 1 : 0,
				'ipmi_ip' => $_REQUEST['ipmi_ip'],
				'ipmi_port' => $_REQUEST['ipmi_port'],
				'ipmi_authtype' => $_REQUEST['ipmi_authtype'],
				'ipmi_privilege' => $_REQUEST['ipmi_privilege'],
				'ipmi_username' => $_REQUEST['ipmi_username'],
				'ipmi_password' => $_REQUEST['ipmi_password'],
				'groups' => $groups,
				'templates' => $templates,
				'macros' => $macros,
				'extendedProfile' => (get_request('useprofile_ext', 'no') == 'yes') ? get_request('ext_host_profiles', array()) : array(),
			);

			if($create_new){
				$hostids = CHost::create($host);
				if($hostids){
					$hostid = reset($hostids['hostids']);
				}
				else throw new Exception();

				add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST,
					$hostid,
					$host['host'],
					null,null,null);
			}
			else{
				$hostid = $host['hostid'] = $_REQUEST['hostid'];
				$host['templates_clear'] = $templates_clear;

				$host_old = CHost::get(array('hostids' => $hostid, 'editable' => 1, 'output' => API_OUTPUT_EXTEND));
				$host_old = reset($host_old);

				if(!CHost::update($host)) throw new Exception();

				$host_new = CHost::get(array('hostids' => $hostid, 'editable' => 1, 'output' => API_OUTPUT_EXTEND));
				$host_new = reset($host_new);

				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST,
					$host['hostid'],
					$host['host'],
					'hosts',
					$host_old,
					$host_new);
			}

// FULL CLONE {{{
			if($clone_hostid && ($_REQUEST['form'] == 'full_clone')){
// Host applications
				$sql = 'SELECT * FROM applications WHERE hostid='.$clone_hostid.' AND templateid=0';
				$res = DBselect($sql);
				while($db_app = DBfetch($res)){
					add_application($db_app['name'], $hostid, 0);
				}

// Host items
				$sql = 'SELECT DISTINCT i.itemid, i.description '.
						' FROM items i '.
						' WHERE i.hostid='.$clone_hostid.
							' AND i.type<>'.ITEM_TYPE_HTTPTEST.
							' AND i.templateid=0 '.
						' ORDER BY i.description';

				$res = DBselect($sql);
				while($db_item = DBfetch($res)){
						if(!copy_item_to_host($db_item['itemid'], $hostid, true)) throw new Exception();
				}

// Host triggers
				if(!copy_triggers($clone_hostid, $hostid)) throw new Exception();

// Host graphs
				$options = array(
					'inherited' => 0,
					'hostids' => $clone_hostid,
					'select_hosts' => API_OUTPUT_REFER,
					'select_items' => API_OUTPUT_EXTEND,
					'output' => API_OUTPUT_EXTEND,
				);
				$graphs = CGraph::get($options);
				foreach($graphs as $gnum => $graph){
					if(count($graph['hosts']) > 1)
						continue;

					if (httpitemExists($graph['items']))
						continue;

					if(!copy_graph_to_host($graph['graphid'], $hostid, true))
						throw new Exception();
				}
			}

// }}} FULL CLONE

//HOSTS PROFILE Section
			CProfile::update('HOST_PORT', $_REQUEST['port'], PROFILE_TYPE_INT);

			if(!$create_new){
				delete_host_profile($hostid);
			}

			if(get_request('useprofile', 'no') == 'yes'){
				if(!add_host_profile($hostid,
					$_REQUEST['devicetype'],$_REQUEST['name'],$_REQUEST['os'],
					$_REQUEST['serialno'],$_REQUEST['tag'],$_REQUEST['macaddress'],
					$_REQUEST['hardware'],$_REQUEST['software'],$_REQUEST['contact'],
					$_REQUEST['location'],$_REQUEST['notes'])) throw new Exception();
			}

// }}} SAVE TRANSACTION

			$result = DBend(true);

			show_messages($result, $msg_ok, $msg_fail);

			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		catch(Exception $e){
			DBend(false);
			show_messages(false, $msg_ok, $msg_fail);
		}

		unset($_REQUEST['save']);
	}
// DELETE HOST
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])){
		$result = CHost::delete(array('hostid' => $_REQUEST['hostid']));
		show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		unset($_REQUEST['delete']);
	}
	else if(isset($_REQUEST['chstatus']) && isset($_REQUEST['hostid'])){

		DBstart();
			$result = update_host_status($_REQUEST['hostid'], $_REQUEST['chstatus']);
		$result = DBend($result);

		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);

		unset($_REQUEST['chstatus']);
		unset($_REQUEST['hostid']);
	}

// -------- GO ---------------
// DELETE HOST
	else if($_REQUEST['go'] == 'delete'){
		$hostids = get_request('hosts', array());
		$hosts = zbx_toObject($hostids, 'hostid');

		$go_result = CHost::delete($hosts);
		show_messages($go_result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
	}
// ACTIVATE/DISABLE HOSTS
	else if(str_in_array($_REQUEST['go'], array('activate', 'disable'))){

		$status = ($_REQUEST['go'] == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request('hosts', array());

		$act_hosts = available_hosts($hosts, 1);

		DBstart();
		$go_result = update_host_status($act_hosts, $status);
		$go_result = DBend($go_result);

		show_messages($go_result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php

	$frmForm = new CForm();
	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form',S_CREATE_HOST));
		$frmForm->addItem(new CButton('form', S_IMPORT_HOST));
	}

	$hosts_wdgt = new CWidget();
	$hosts_wdgt->addPageHeader(S_CONFIGURATION_OF_HOSTS, $frmForm);

	$options = array(
		'groups' => array(
			'real_hosts' => 1,
			'editable' => 1,
		),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);

	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = get_request('hostid', 0);

?>
<?php
	// echo SBR;

	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['hosts'])){
		$hosts_wdgt->addItem(insert_mass_update_host_form());
	}
	else if(isset($_REQUEST['form'])){
		if($_REQUEST['form'] == S_IMPORT_HOST)
			$hosts_wdgt->addItem(import_host_form());
		else
			$hosts_wdgt->addItem(insert_host_form());
	}
	else{

		$frmForm = new CForm();
		$frmForm->setMethod('get');

		$frmForm->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB()));

		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$hosts_wdgt->addHeader(S_HOSTS_BIG, $frmForm);
		$hosts_wdgt->addHeader($numrows);

// HOSTS FILTER {{{
		$filter_table = new CTable('', 'filter_config');
		$filter_table->addRow(array(
			array(array(bold(S_HOST), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_host', $_REQUEST['filter_host'], 20)),
			array(array(bold(S_DNS), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_dns', $_REQUEST['filter_dns'], 20)),
			array(array(bold(S_IP), SPACE.S_LIKE_SMALL.': '), new CTextBox('filter_ip', $_REQUEST['filter_ip'], 20)),
			array(bold(S_PORT.': '), new CTextBox('filter_port', $_REQUEST['filter_port'], 20))
		));

		$reset = new CSpan( S_RESET,'biglink');
		$reset->onClick("javascript: clearAllForm('zbx_filter');");
		$filter = new CSpan(S_FILTER,'biglink');
		$filter->onClick("javascript: create_var('zbx_filter', 'filter_set', '1', true);");

		$footer_col = new CCol(array($filter, SPACE, SPACE, SPACE, $reset), 'center');
		$footer_col->setColSpan(4);

		$filter_table->addRow($footer_col);

		$filter_form = new CForm(null, 'get');
		$filter_form->setAttribute('name','zbx_filter');
		$filter_form->setAttribute('id','zbx_filter');
		$filter_form->addItem($filter_table);
// }}} HOSTS FILTER
		$hosts_wdgt->addFlicker($filter_form, CProfile::get('web.hosts.filter.state', 0));


// table HOSTS
		$form = new CForm();
		$form->setName('hosts');

		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_hosts', null, "checkAll('" . $form->getName() . "','all_hosts','hosts');"),
			make_sorting_header(S_NAME, 'host'),
			S_APPLICATIONS,
			S_ITEMS,
			S_TRIGGERS,
			S_GRAPHS,
			make_sorting_header(S_DNS, 'dns'),
			make_sorting_header(S_IP, 'ip'),
			S_PORT,
			S_TEMPLATES,
			make_sorting_header(S_STATUS, 'status'),
			S_AVAILABILITY
		));

// get Hosts
		$hosts = array();

		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();

		if($pageFilter->groupsSelected){
			$options = array(
				'editable' => 1,
				'sortfield' => $sortfield,
				'sortorder' => $sortorder,
				'limit' => ($config['search_limit']+1),
				'search' => array(
					'host' => (empty($_REQUEST['filter_host']) ? null : $_REQUEST['filter_host']),
					'ip' => (empty($_REQUEST['filter_ip']) ? null : $_REQUEST['filter_ip']),
					'dns' => (empty($_REQUEST['filter_dns']) ? null : $_REQUEST['filter_dns']),
				),
				'filter' => array(
					'port' => (empty($_REQUEST['filter_port']) ? null : $_REQUEST['filter_port']),
				)
			);

			if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;

			$hosts = CHost::get($options);
		}

// sorting && paging
		order_result($hosts, $sortfield, $sortorder);
		$paging = getPagingLine($hosts);
//---------

		$options = array(
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'output' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => array('hostid','host'),
			'select_items' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
			'select_applications' => API_OUTPUT_COUNT,
			'nopermissions' => 1,
		);
		$hosts = CHost::get($options);

// sorting && paging
		order_result($hosts, $sortfield, $sortorder);
//---------

// Selecting linked templates to templates linked to hosts
		$templateids = array();
		foreach($hosts as $num => $host){
			$templateids = array_merge($templateids, zbx_objectValues($host['parentTemplates'], 'templateid'));
		}
		$templateids = array_unique($templateids);

		$options = array(
			'templateids' => $templateids,
			'selectParentTemplates' => array('hostid', 'host')
		);
		$templates = CTemplate::get($options);
		$templates = zbx_toHash($templates, 'templateid');
//---------
		foreach($hosts as $num => $host){
			$applications = array(new CLink(S_APPLICATIONS, 'applications.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
				' ('.$host['applications'].')');
			$items = array(new CLink(S_ITEMS, 'items.php?filter_set=1&hostid='.$host['hostid']),
				' ('.$host['items'].')');
			$triggers = array(new CLink(S_TRIGGERS, 'triggers.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
				' ('.$host['triggers'].')');
			$graphs = array(new CLink(S_GRAPHS, 'graphs.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
				' ('.$host['graphs'].')');

			$description = array();
			if($host['proxy_hostid']){
				$proxy = CProxy::get(array('proxyids' => $host['proxy_hostid'], 'extendoutput' => 1));
				$proxy = reset($proxy);
				$description[] = $proxy['host'] . ':';
			}

			$description[] = new CLink($host['host'], 'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid'));

			$dns = empty($host['dns']) ? '-' : $host['dns'];
			$ip = empty($host['ip']) ? '-' : $host['ip'];
			$use = (1 == $host['useip']) ? 'ip' : 'dns';
			$$use = bold($$use);

			$status_script = null;
			switch($host['status']){
				case HOST_STATUS_MONITORED:
					if($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON){
						$status_caption = S_IN_MAINTENANCE;
						$status_class = 'orange';
					}
					else{
						$status_caption = S_MONITORED;
						$status_class = 'enabled';
					}

					$status_script = 'return Confirm('.zbx_jsvalue(S_DISABLE_HOST.'?').');';
					$status_url = 'hosts.php?hosts%5B%5D='.$host['hostid'].'&go=disable'.url_param('groupid');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status_caption = S_NOT_MONITORED;
					$status_url = 'hosts.php?hosts%5B%5D='.$host['hostid'].'&go=activate'.url_param('groupid');
					$status_script = 'return Confirm('.zbx_jsvalue(S_ENABLE_HOST.'?').');';
					$status_class = 'disabled';
					break;
				default:
					$status_caption = S_UNKNOWN;
					$status_script = 'return Confirm('.zbx_jsvalue(S_DISABLE_HOST.'?').');';
					$status_url = 'hosts.php?hosts%5B%5D='.$host['hostid'].'&go=disable'.url_param('groupid');
					$status_class = 'unknown';
			}

			$status = new CLink($status_caption, $status_url, $status_class, $status_script);

			switch($host['available']){
				case HOST_AVAILABLE_TRUE:
					$zbx_available = new CDiv(SPACE, 'iconzbxavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$zbx_available = new CDiv(SPACE, 'iconzbxunavailable');
					$zbx_available->setHint($host['error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$zbx_available = new CDiv(SPACE, 'iconzbxunknown');
					break;
			}

			switch($host['snmp_available']){
				case HOST_AVAILABLE_TRUE:
					$snmp_available = new CDiv(SPACE, 'iconsnmpavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$snmp_available = new CDiv(SPACE, 'iconsnmpunavailable');
					$snmp_available->setHint($host['snmp_error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$snmp_available = new CDiv(SPACE, 'iconsnmpunknown');
					break;
			}

			switch($host['ipmi_available']){
				case HOST_AVAILABLE_TRUE:
					$ipmi_available = new CDiv(SPACE, 'iconipmiavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$ipmi_available = new CDiv(SPACE, 'iconipmiunavailable');
					$ipmi_available->setHint($host['ipmi_error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$ipmi_available = new CDiv(SPACE, 'iconipmiunknown');
					break;
			}

			$av_table = new CTable(null, 'invisible');
			$av_table->addRow(array($zbx_available, $snmp_available, $ipmi_available));

			if(empty($host['parentTemplates'])){
				$hostTemplates = '-';
			}
			else{
				$hostTemplates = array();
				order_result($host['parentTemplates'], 'host');
				foreach($host['parentTemplates'] as $htnum => $template){
					$caption = array();
					$caption[] = new CLink($template['host'],'templates.php?form=update&templateid='.$template['templateid'],'unknown');

					if(!empty($templates[$template['templateid']]['parentTemplates'])){
						order_result($templates[$template['templateid']]['parentTemplates'], 'host');

						$caption[] = ' (';
						foreach($templates[$template['templateid']]['parentTemplates'] as $tnum => $tpl){
							$caption[] = new CLink($tpl['host'],'templates.php?form=update&templateid='.$tpl['templateid'], 'unknown');
							$caption[] = ', ';
						}
						array_pop($caption);

						$caption[] = ')';
					}

					$hostTemplates[] = $caption;
					$hostTemplates[] = ', ';
				}

				if(!empty($hostTemplates)) array_pop($hostTemplates);
			}

			$table->addRow(array(
				new CCheckBox('hosts['.$host['hostid'].']',null,null,$host['hostid']),
				$description,
				$applications,
				$items,
				$triggers,
				$graphs,
				$dns,
				$ip,
				empty($host['port']) ? '-' : $host['port'],
				new CCol($hostTemplates, 'wraptext'),
				$status,
				$av_table
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('export', S_EXPORT_SELECTED);
		$goBox->addItem('massupdate',S_MASS_UPDATE);

		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_HOSTS);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_HOSTS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_HOSTS_Q);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO);
		$goButton->setAttribute('id', 'goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

		$footer = get_table_header(array($goBox, $goButton));
//----

// PAGING FOOTER
		$table = array($paging, $table, $paging, $footer);
//---------
		$form->addItem($table);
		$hosts_wdgt->addItem($form);
	}

	$hosts_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
