<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

if (isset($_REQUEST['go']) && $_REQUEST['go'] == 'export' && isset($_REQUEST['hosts'])) {
	$page['file'] = 'zbx_export_hosts.xml';
	$page['type'] = detect_page_type(PAGE_TYPE_XML);

	$EXPORT_DATA = true;
}
else {
	$page['title'] = _('Configuration of hosts');
	$page['file'] = 'hosts.php';
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['hist_arg'] = array('groupid');

	$EXPORT_DATA = false;
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hosts' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'groups' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'hostids' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'applications' =>	array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		null),
	'hostid' =>		array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		'isset({form})&&({form}=="update")'),
	'host' =>		array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Host name')),
	'visiblename' =>	array(T_ZBX_STR, O_OPT, null,		null,		'isset({save})'),
	'proxy_hostid' =>	array(T_ZBX_INT, O_OPT, P_SYS,		DB_ID,		'isset({save})'),
	'status' =>		array(T_ZBX_INT, O_OPT, null,		IN('0,1,3'),	'isset({save})'),
	'newgroup' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'interfaces' =>		array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	'isset({save})', _('Agent or SNMP or JMX or IPMI interface')),
	'mainInterfaces' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,		null),
	'templates' =>		array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY,	null),
	'templates_rem' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'clear_templates' =>	array(T_ZBX_INT, O_OPT, null,		DB_ID,		null),
	'ipmi_authtype' =>	array(T_ZBX_INT, O_OPT, null,		BETWEEN(-1, 6), null),
	'ipmi_privilege' =>	array(T_ZBX_INT, O_OPT, null,		BETWEEN(0, 5),	null),
	'ipmi_username' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'ipmi_password' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'mass_replace_tpls' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'mass_clear_tpls' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'inventory_mode' =>	array(T_ZBX_INT, O_OPT, null,
		IN(HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC), null),
	'host_inventory' =>	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,	null,		null),
	'macros_rem' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'macros' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'macro_new' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		'isset({macro_add})'),
	'value_new' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		'isset({macro_add})'),
	'macro_add' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'massupdate' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'visible' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	// actions
	'go' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'add_to_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'delete_from_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'unlink' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'unlink_and_clear' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'save' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'masssave' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'clone' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'full_clone' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'delete' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'cancel' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'form' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,		null),
	'form_refresh' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT, P_ACT,		null,		null),
	'filter_host' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	'filter_ip' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'filter_dns' =>		array(T_ZBX_STR, O_OPT, null,		null,		null),
	'filter_port' =>	array(T_ZBX_STR, O_OPT, null,		null,		null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,		null,		null),
	'favref' =>		array(T_ZBX_STR, O_OPT, P_ACT,		NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,		NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Permissions
 */
if (get_request('groupid', 0) > 0) {
	$groupids = available_groups($_REQUEST['groupid'], 1);
	if (empty($groupids)) {
		access_deny();
	}
}
if (get_request('hostid', 0) > 0) {
	$hostids = available_hosts($_REQUEST['hostid'], 1);
	if (empty($hostids)) {
		access_deny();
	}
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ('filter' == $_REQUEST['favobj']) {
		CProfile::update('web.hosts.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if (PAGE_TYPE_JS == $page['type'] || PAGE_TYPE_HTML_BLOCK == $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$hostids = get_request('hosts', array());

/*
 * Export
 */
if ($EXPORT_DATA) {
	$export = new CConfigurationExport(array('hosts' => $hostids));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();
	if (!no_errors()) {
		show_messages();
	}
	else {
		print($exportData);
	}
	exit();
}

/*
 * Filter
 */
if (isset($_REQUEST['filter_set'])) {
	$_REQUEST['filter_ip'] = get_request('filter_ip');
	$_REQUEST['filter_dns'] = get_request('filter_dns');
	$_REQUEST['filter_host'] = get_request('filter_host');
	$_REQUEST['filter_port'] = get_request('filter_port');

	CProfile::update('web.hosts.filter_ip', $_REQUEST['filter_ip'], PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_dns', $_REQUEST['filter_dns'], PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_host', $_REQUEST['filter_host'], PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_port', $_REQUEST['filter_port'], PROFILE_TYPE_STR);
}
else {
	$_REQUEST['filter_ip'] = CProfile::get('web.hosts.filter_ip');
	$_REQUEST['filter_dns'] = CProfile::get('web.hosts.filter_dns');
	$_REQUEST['filter_host'] = CProfile::get('web.hosts.filter_host');
	$_REQUEST['filter_port'] = CProfile::get('web.hosts.filter_port');
}

/*
 * Actions
 */
if (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear'])) {
	$_REQUEST['clear_templates'] = get_request('clear_templates', array());

	$unlink_templates = array();
	if (isset($_REQUEST['unlink'])) {
		// templates_rem for old style removal in massupdate form
		if (isset($_REQUEST['templates_rem'])) {
			$unlink_templates = array_keys($_REQUEST['templates_rem']);
		}
		elseif (is_array($_REQUEST['unlink'])) {
			$unlink_templates = array_keys($_REQUEST['unlink']);
		}
	}
	else {
		$unlink_templates = array_keys($_REQUEST['unlink_and_clear']);
		$_REQUEST['clear_templates'] = array_merge($_REQUEST['clear_templates'], $unlink_templates);
	}

	foreach ($unlink_templates as $id) {
		unset($_REQUEST['templates'][$id]);
	}
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	unset($_REQUEST['hostid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])) {
	$_REQUEST['form'] = 'full_clone';
}
elseif (isset($_REQUEST['go']) && $_REQUEST['go'] == 'massupdate' && isset($_REQUEST['masssave'])) {
	$hostids = get_request('hosts', array());
	$visible = get_request('visible', array());
	$_REQUEST['newgroup'] = get_request('newgroup', '');
	$_REQUEST['proxy_hostid'] = get_request('proxy_hostid', 0);
	$_REQUEST['templates'] = get_request('templates', array());

	try {
		DBstart();

		$hosts = array('hosts' => zbx_toObject($hostids, 'hostid'));

		$properties = array('proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'status');
		$new_values = array();
		foreach ($properties as $property) {
			if (isset($visible[$property])) {
				$new_values[$property] = $_REQUEST[$property];
			}
		}

		if (isset($visible['inventory_mode'])) {
			$new_values['inventory_mode'] = get_request('inventory_mode', HOST_INVENTORY_DISABLED);
			$new_values['inventory'] = $new_values['inventory_mode'] != HOST_INVENTORY_DISABLED ? get_request('host_inventory', array()) : array();
		}

		$newgroup = array();
		if (isset($visible['newgroup']) && !empty($_REQUEST['newgroup'])) {
			if (!$result = API::HostGroup()->create(array('name' => $_REQUEST['newgroup']))) {
				throw new Exception();
			}

			$newgroup = array('groupid' => reset($result['groupids']), 'name' => $_REQUEST['newgroup']);
		}

		$templates = array();
		if (isset($visible['template_table'])) {
			$tplids = array_keys($_REQUEST['templates']);
			$templates = zbx_toObject($tplids, 'templateid');
		}

		if (isset($visible['groups'])) {
			$hosts['groups'] = API::HostGroup()->get(array(
				'groupids' => get_request('groups', array()),
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN
			));
			if (!empty($newgroup)) {
				$hosts['groups'][] = $newgroup;
			}
		}
		if (isset($_REQUEST['mass_replace_tpls'])) {
			if (isset($_REQUEST['mass_clear_tpls'])) {
				$host_templates = API::Template()->get(array('hostids' => $hostids));
				$host_templateids = zbx_objectValues($host_templates, 'templateid');
				$templates_to_del = array_diff($host_templateids, $tplids);
				$hosts['templates_clear'] = zbx_toObject($templates_to_del, 'templateid');
			}
			$hosts['templates'] = $templates;
		}

		$result = API::Host()->massUpdate(array_merge($hosts, $new_values));
		if ($result === false) {
			throw new Exception();
		}

		$add = array();
		if (!empty($templates) && isset($visible['template_table'])) {
			$add['templates'] = $templates;
		}
		if (!empty($newgroup) && !isset($visible['groups'])) {
			$add['groups'][] = $newgroup;
		}
		if (!empty($add)) {
			$add['hosts'] = $hosts['hosts'];

			$result = API::Host()->massAdd($add);
			if ($result === false) {
				throw new Exception();
			}
		}

		DBend(true);

		show_message(_('Hosts updated'));

		unset($_REQUEST['massupdate'], $_REQUEST['form'], $_REQUEST['hosts']);

		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update hosts'));
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['save'])) {
	if (!count(get_accessible_nodes_by_user(CWebUser::$data, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
		access_deny();
	}

	try {
		DBstart();

		$macros = get_request('macros', array());
		$interfaces = get_request('interfaces', array());
		$templates = get_request('templates', array());
		$groups = get_request('groups', array());

		if (isset($_REQUEST['hostid']) && $_REQUEST['form'] != 'full_clone') {
			$create_new = false;
			$msg_ok = _('Host updated');
			$msg_fail = _('Cannot update host');
		}
		else {
			$create_new = true;
			$msg_ok = _('Host added');
			$msg_fail = _('Cannot add host');
		}

		$templates = array_keys($templates);
		$templates = zbx_toObject($templates, 'templateid');

		foreach ($interfaces as $inum => $interface) {
			if (zbx_empty($interface['ip']) && zbx_empty($interface['dns'])) {
				unset($interface[$inum]);
				continue;
			}

			if ($interface['isNew']) {
				unset($interfaces[$inum]['interfaceid']);
			}
			unset($interfaces[$inum]['isNew']);
			$interfaces[$inum]['main'] = 0;
		}

		$interfaceTypes = array(INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI);
		foreach ($interfaceTypes as $type) {
			if (isset($_REQUEST['mainInterfaces'][$type])) {
				$interfaces[$_REQUEST['mainInterfaces'][$type]]['main'] = '1';
			}
		}

		// ignore empty new macros, i.e., macros rows that have not been filled
		foreach ($macros as $mnum => $macro) {
			if (!isset($macro['hostmacroid']) && zbx_empty($macro['macro']) && zbx_empty($macro['value'])) {
				unset($macros[$mnum]);
			}
		}

		foreach ($macros as $mnum => $macro) {
			// transform macros to uppercase {$aaa} => {$AAA}
			$macros[$mnum]['macro'] = zbx_strtoupper($macro['macro']);
		}

		// create new group
		if (!zbx_empty($_REQUEST['newgroup'])) {
			if (!$newGroup = API::HostGroup()->create(array('name' => $_REQUEST['newgroup']))) {
				throw new Exception();
			}
			$groups[] = reset($newGroup['groupids']);
		}
		$groups = zbx_toObject($groups, 'groupid');

		$host = array(
			'host' => $_REQUEST['host'],
			'name' => $_REQUEST['visiblename'],
			'status' => $_REQUEST['status'],
			'proxy_hostid' => get_request('proxy_hostid', 0),
			'ipmi_authtype' => get_request('ipmi_authtype'),
			'ipmi_privilege' => get_request('ipmi_privilege'),
			'ipmi_username' => get_request('ipmi_username'),
			'ipmi_password' => get_request('ipmi_password'),
			'groups' => $groups,
			'templates' => $templates,
			'interfaces' => $interfaces,
			'macros' => $macros,
			'inventory' => (get_request('inventory_mode') != HOST_INVENTORY_DISABLED) ? get_request('host_inventory', array()) : array(),
			'inventory_mode' => get_request('inventory_mode')
		);

		if ($create_new) {
			$hostids = API::Host()->create($host);
			if ($hostids) {
				$hostid = reset($hostids['hostids']);
			}
			else {
				throw new Exception();
			}

			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST, $hostid, $host['host'], null, null, null);
		}
		else {
			$hostid = $host['hostid'] = $_REQUEST['hostid'];

			$host['templates_clear'] = zbx_toObject(get_request('clear_templates', array()), 'templateid');

			$host_old = API::Host()->get(array(
				'hostids' => $hostid,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND
			));
			$host_old = reset($host_old);

			if (!API::Host()->update($host)) {
				throw new Exception();
			}

			$host_new = API::Host()->get(array(
				'hostids' => $hostid,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND
			));
			$host_new = reset($host_new);

			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts', $host_old, $host_new);
		}

		if ($_REQUEST['form'] == 'full_clone') {
			$srcHostId = get_request('hostid');

			if (!copyApplications($srcHostId, $hostid)) {
				throw new Exception();
			}

			if (!copyItems($srcHostId, $hostid)) {
				throw new Exception();
			}

			// clone triggers
			$triggers = API::Trigger()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'hostids' => $srcHostId,
				'inherited' => false
			));
			if ($triggers) {
				if (!copyTriggersToHosts(zbx_objectValues($triggers, 'triggerid'), $hostid, $srcHostId)) {
					throw new Exception();
				}
			}

			// clone discovery rules
			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'hostids' => $srcHostId,
				'inherited' => false
			));
			if ($discoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy(array(
					'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
					'hostids' => array($hostid)
				));
				if (!$copyDiscoveryRules) {
					throw new Exception();
				}
			}

			$graphs = API::Graph()->get(array(
				'hostids' => $srcHostId,
				'selectItems' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND,
				'inherited' => false,
				'selectHosts' => API_OUTPUT_REFER,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
			));
			foreach ($graphs as $graph) {
				if (count($graph['hosts']) > 1) {
					continue;
				}

				if (httpItemExists($graph['items'])) {
					continue;
				}

				if (!copy_graph_to_host($graph['graphid'], $hostid)) {
					throw new Exception();
				}
			}
		}

		$result = DBend(true);

		show_messages($result, $msg_ok, $msg_fail);

		unset($_REQUEST['form'], $_REQUEST['hostid']);
	}
	catch (Exception $e) {
		DBend(false);
		show_messages(false, $msg_ok, $msg_fail);
	}

	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {
	DBstart();
	$result = API::Host()->delete(array('hostid' => $_REQUEST['hostid']));
	$result = DBend($result);

	show_messages($result, _('Host deleted'), _('Cannot delete host'));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['hostid']);
	}
	unset($_REQUEST['delete']);
}
elseif (isset($_REQUEST['chstatus']) && isset($_REQUEST['hostid'])) {
	DBstart();

	$result = updateHostStatus($_REQUEST['hostid'], $_REQUEST['chstatus']);
	$result = DBend($result);

	show_messages($result, _('Host status updated'), _('Cannot update host status'));

	unset($_REQUEST['chstatus'], $_REQUEST['hostid']);
}
elseif ($_REQUEST['go'] == 'delete') {
	$hostids = get_request('hosts', array());

	DBstart();
	$go_result = API::Host()->delete(zbx_toObject($hostids,'hostid'));
	$go_result = DBend($go_result);

	show_messages($go_result, _('Host deleted'), _('Cannot delete host'));
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable'))) {
	$status = ($_REQUEST['go'] == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$hosts = get_request('hosts', array());
	$act_hosts = available_hosts($hosts, 1);

	DBstart();

	$go_result = updateHostStatus($act_hosts, $status);
	$go_result = DBend($go_result);

	show_messages($go_result, _('Host status updated'), _('Cannot update host status'));
}

if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray("'.$path.'")');
}

/*
 * Display
 */
$hosts_wdgt = new CWidget();

$pageFilter = new CPageFilter(array(
	'groups' => array(
		'real_hosts' => 1,
		'editable' => true
	),
	'groupid' => get_request('groupid', null)
));

$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = get_request('hostid', 0);

if ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['hosts'])) {
	$hosts_wdgt->addPageHeader(_('CONFIGURATION OF HOSTS'));

	$data = array(
		'hosts' => get_request('hosts', array()),
		'visible' => get_request('visible', array()),
		'mass_replace_tpls' => get_request('mass_replace_tpls'),
		'mass_clear_tpls' => get_request('mass_clear_tpls'),
		'groups' => get_request('groups', array()),
		'newgroup' => get_request('newgroup', ''),
		'status' => get_request('status', HOST_STATUS_MONITORED),
		'proxy_hostid' => get_request('proxy_hostid', ''),
		'ipmi_authtype' => get_request('ipmi_authtype', -1),
		'ipmi_privilege' => get_request('ipmi_privilege', 2),
		'ipmi_username' => get_request('ipmi_username', ''),
		'ipmi_password' => get_request('ipmi_password', ''),
		'inventory_mode' => get_request('inventory_mode', HOST_INVENTORY_DISABLED),
		'host_inventory' => get_request('host_inventory', array()),
		'templates' => get_request('templates', array())
	);

	// sort templates
	natsort($data['templates']);

	// get groups
	$data['all_groups'] = API::HostGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));
	order_result($data['all_groups'], 'name');

	// get proxies
	$data['proxies'] = DBfetchArray(DBselect(
		'SELECT h.hostid,h.host'.
		' FROM hosts h'.
		' WHERE h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
			' AND '.DBin_node('h.hostid').
		' ORDER BY h.host'
	));

	// get inventories
	if ($data['inventory_mode'] != HOST_INVENTORY_DISABLED) {
		$data['inventories'] = getHostInventories();
		$data['inventories'] = zbx_toHash($data['inventories'], 'db_field');
	}

	$hostForm = new CView('configuration.host.massupdate', $data);
	$hosts_wdgt->addItem($hostForm->render());
}
elseif (isset($_REQUEST['form'])) {
	$hosts_wdgt->addPageHeader(_('CONFIGURATION OF HOSTS'));

	if ($hostid = get_request('hostid', 0)) {
		$hosts_wdgt->addItem(get_header_host_table('', $_REQUEST['hostid']));
	}

	$hostForm = new CView('configuration.host.edit');
	$hosts_wdgt->addItem($hostForm->render());
	$hosts_wdgt->setRootClass('host-edit');
}
else {
	$frmForm = new CForm();
	$frmForm->cleanItems();
	$buttons = new CDiv(array(
		new CSubmit('form', _('Create host')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=host")')
	));
	$frmForm->addItem($buttons);
	$frmForm->addItem(new CVar('groupid', $_REQUEST['groupid'], 'filter_groupid_id'));

	$hosts_wdgt->addPageHeader(_('CONFIGURATION OF HOSTS'), $frmForm);

	$frmGroup = new CForm('get');
	$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB()));

	$hosts_wdgt->addHeader(_('Hosts'), $frmGroup);
	$hosts_wdgt->addHeaderRowNumber();
	$hosts_wdgt->setRootClass('host-list');

	// filter
	$filter_table = new CTable('', 'filter');
	$filter_table->addRow(array(
		array(array(bold(_('Name')), SPACE._('like').': '), new CTextBox('filter_host', $_REQUEST['filter_host'], 20)),
		array(array(bold(_('DNS')), SPACE._('like').': '), new CTextBox('filter_dns', $_REQUEST['filter_dns'], 20)),
		array(array(bold(_('IP')), SPACE._('like').': '), new CTextBox('filter_ip', $_REQUEST['filter_ip'], 20)),
		array(bold(_('Port').': '), new CTextBox('filter_port', $_REQUEST['filter_port'], 20))
	));

	$filter = new CButton('filter', _('Filter'), "javascript: create_var('zbx_filter', 'filter_set', '1', true);");
	$filter->useJQueryStyle('main');

	$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
	$reset->useJQueryStyle();

	$div_buttons = new CDiv(array($filter, SPACE, $reset));
	$div_buttons->setAttribute('style', 'padding: 4px 0;');

	$filter_table->addRow(new CCol($div_buttons, 'center', 4));

	$filter_form = new CForm('get');
	$filter_form->setAttribute('name', 'zbx_filter');
	$filter_form->setAttribute('id', 'zbx_filter');
	$filter_form->addItem($filter_table);

	$hosts_wdgt->addFlicker($filter_form, CProfile::get('web.hosts.filter.state', 0));

	// table hosts
	$form = new CForm();
	$form->setName('hosts');

	$table = new CTableInfo(_('No hosts defined.'));
	$table->setHeader(array(
		new CCheckBox('all_hosts', null, "checkAll('".$form->getName()."', 'all_hosts', 'hosts');"),
		make_sorting_header(_('Name'), 'name'),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		_('Templates'),
		make_sorting_header(_('Status'), 'status'),
		_('Availability')
	));

	// get Hosts
	$hosts = array();

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	if ($pageFilter->groupsSelected) {
		$options = array(
			'editable' => true,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => $config['search_limit'] + 1,
			'search' => array(
				'name' => (empty($_REQUEST['filter_host']) ? null : $_REQUEST['filter_host']),
				'ip' => (empty($_REQUEST['filter_ip']) ? null : $_REQUEST['filter_ip']),
				'dns' => (empty($_REQUEST['filter_dns']) ? null : $_REQUEST['filter_dns'])
			),
			'filter' => array(
				'port' => (empty($_REQUEST['filter_port']) ? null : $_REQUEST['filter_port'])
			)
		);

		if ($pageFilter->groupid > 0) {
			$options['groupids'] = $pageFilter->groupid;
		}

		$hosts = API::Host()->get($options);
	}
	else {
		$hosts = array();
	}

	// sorting && paging
	order_result($hosts, $sortfield, $sortorder);
	$paging = getPagingLine($hosts);

	$hosts = API::Host()->get(array(
		'hostids' => zbx_objectValues($hosts, 'hostid'),
		'output' => API_OUTPUT_EXTEND,
		'selectParentTemplates' => array('hostid', 'name'),
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT
	));
	order_result($hosts, $sortfield, $sortorder);

	// selecting linked templates to templates linked to hosts
	$templateids = array();
	foreach ($hosts as $host) {
		$templateids = array_merge($templateids, zbx_objectValues($host['parentTemplates'], 'templateid'));
	}
	$templateids = array_unique($templateids);

	$templates = API::Template()->get(array(
		'templateids' => $templateids,
		'selectParentTemplates' => array('hostid', 'name')
	));
	$templates = zbx_toHash($templates, 'templateid');

	foreach ($hosts as $host) {
		$interface = reset($host['interfaces']);

		$applications = array(new CLink(_('Applications'), 'applications.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['applications'].')');
		$items = array(new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$host['hostid']),
			' ('.$host['items'].')');
		$triggers = array(new CLink(_('Triggers'), 'triggers.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['triggers'].')');
		$graphs = array(new CLink(_('Graphs'), 'graphs.php?groupid='.$_REQUEST['groupid'].'&hostid='.$host['hostid']),
			' ('.$host['graphs'].')');
		$discoveries = array(new CLink(_('Discovery'), 'host_discovery.php?&hostid='.$host['hostid']),
			' ('.$host['discoveries'].')');
		$httpTests = array(new CLink(_('Web'), 'httpconf.php?&hostid='.$host['hostid']),
			' ('.$host['httpTests'].')');

		$description = array();
		if ($host['proxy_hostid']) {
			$proxy = API::Proxy()->get(array(
				'proxyids' => $host['proxy_hostid'],
				'output' => API_OUTPUT_EXTEND
			));
			$proxy = reset($proxy);
			$description[] = $proxy['host'].':';
		}

		$description[] = new CLink($host['name'], 'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid'));

		$hostIF = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
		$hostIF .= empty($interface['port']) ? '' : ': '.$interface['port'];

		$status_script = null;
		switch ($host['status']) {
			case HOST_STATUS_MONITORED:
				if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$status_caption = _('In maintenance');
					$status_class = 'orange';
				}
				else {
					$status_caption = _('Monitored');
					$status_class = 'enabled';
				}

				$status_script = 'return Confirm('.zbx_jsvalue(_('Disable host?')).');';
				$status_url = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=disable'.url_param('groupid');
				break;
			case HOST_STATUS_NOT_MONITORED:
				$status_caption = _('Not monitored');
				$status_url = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=activate'.url_param('groupid');
				$status_script = 'return Confirm('.zbx_jsvalue(_('Enable host?')).');';
				$status_class = 'disabled';
				break;
			default:
				$status_caption = _('Unknown');
				$status_script = 'return Confirm('.zbx_jsvalue(_('Disable host?')).');';
				$status_url = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=disable'.url_param('groupid');
				$status_class = 'unknown';
		}

		$status = new CLink($status_caption, $status_url, $status_class, $status_script);

		if (empty($host['parentTemplates'])) {
			$hostTemplates = '-';
		}
		else {
			$hostTemplates = array();
			order_result($host['parentTemplates'], 'name');

			foreach ($host['parentTemplates'] as $template) {
				$caption = array();
				$caption[] = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid'], 'unknown');

				if (!empty($templates[$template['templateid']]['parentTemplates'])) {
					order_result($templates[$template['templateid']]['parentTemplates'], 'name');

					$caption[] = ' (';
					foreach ($templates[$template['templateid']]['parentTemplates'] as $tpl) {
						$caption[] = new CLink($tpl['name'],'templates.php?form=update&templateid='.$tpl['templateid'], 'unknown');
						$caption[] = ', ';
					}
					array_pop($caption);

					$caption[] = ')';
				}

				$hostTemplates[] = $caption;
				$hostTemplates[] = ', ';
			}

			if (!empty($hostTemplates)) {
				array_pop($hostTemplates);
			}
		}

		$table->addRow(array(
			new CCheckBox('hosts['.$host['hostid'].']', null, null, $host['hostid']),
			$description,
			$applications,
			$items,
			$triggers,
			$graphs,
			$discoveries,
			$httpTests,
			$hostIF,
			new CCol($hostTemplates, 'wraptext'),
			$status,
			getAvailabilityTable($host)
		));
	}

	$goBox = new CComboBox('go');
	$goBox->addItem('export', _('Export selected'));
	$goBox->addItem('massupdate', _('Mass update'));

	$goOption = new CComboItem('activate', _('Enable selected'));
	$goOption->setAttribute('confirm', _('Enable selected hosts?'));
	$goBox->addItem($goOption);

	$goOption = new CComboItem('disable', _('Disable selected'));
	$goOption->setAttribute('confirm', _('Disable selected hosts?'));
	$goBox->addItem($goOption);

	$goOption = new CComboItem('delete', _('Delete selected'));
	$goOption->setAttribute('confirm', _('Delete selected hosts?'));
	$goBox->addItem($goOption);

	$goButton = new CSubmit('goButton', _('Go').' (0)');
	$goButton->setAttribute('id', 'goButton');
	zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

	// footer
	$table = array($paging, $table, $paging, get_table_header(array($goBox, $goButton)));

	$form->addItem($table);
	$hosts_wdgt->addItem($form);
}

$hosts_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
