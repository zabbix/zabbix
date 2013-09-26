<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

if (isset($_REQUEST['go']) && $_REQUEST['go'] == 'export' && isset($_REQUEST['hosts'])) {
	$page['file'] = 'zbx_export_hosts.xml';
	$page['type'] = detect_page_type(PAGE_TYPE_XML);

	$exportData = true;
}
else {
	$page['title'] = _('Configuration of hosts');
	$page['file'] = 'hosts.php';
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['hist_arg'] = array('groupid');
	$page['scripts'] = array('multiselect.js');

	$exportData = false;
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hosts' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'groups' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'new_groups' =>		array(T_ZBX_STR, O_OPT, P_SYS,			null,		null),
	'hostids' =>		array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'applications' =>	array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null),
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		'isset({form})&&{form}=="update"'),
	'host' =>			array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({save})', _('Host name')),
	'visiblename' =>	array(T_ZBX_STR, O_OPT, null,			null,		'isset({save})'),
	'proxy_hostid' =>	array(T_ZBX_INT, O_OPT, P_SYS,		    DB_ID,		null),
	'status' =>			array(T_ZBX_INT, O_OPT, null,			IN('0,1,3'), 'isset({save})'),
	'newgroup' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	'interfaces' =>		array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({save})', _('Agent or SNMP or JMX or IPMI interface')),
	'mainInterfaces' =>	array(T_ZBX_INT, O_OPT, null,			DB_ID,		null),
	'templates' =>		array(T_ZBX_INT, O_OPT, null,			DB_ID,		null),
	'add_template' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	'add_templates' => array(T_ZBX_INT, O_OPT, null,			DB_ID,		null),
	'templates_rem' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'clear_templates' => array(T_ZBX_INT, O_OPT, null,			DB_ID,		null),
	'ipmi_authtype' =>	array(T_ZBX_INT, O_OPT, null,			BETWEEN(-1, 6), null),
	'ipmi_privilege' =>	array(T_ZBX_INT, O_OPT, null,			BETWEEN(0, 5), null),
	'ipmi_username' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	'ipmi_password' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	'mass_replace_tpls' => array(T_ZBX_STR, O_OPT, null,		null,		null),
	'mass_clear_tpls' => array(T_ZBX_STR, O_OPT, null,			null,		null),
	'inventory_mode' => array(T_ZBX_INT, O_OPT, null,
		IN(HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC), null),
	'host_inventory' =>	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,	null,		null),
	'macros_rem' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'macros' =>			array(T_ZBX_STR, O_OPT, P_SYS,			null,		null),
	'macro_new' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		'isset({macro_add})'),
	'value_new' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		'isset({macro_add})'),
	'macro_add' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'massupdate' =>		array(T_ZBX_STR, O_OPT, P_SYS,			null,		null),
	'visible' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'add_to_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'delete_from_group' => array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'unlink' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'unlink_and_clear' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'masssave' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'full_clone' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'form_refresh' =>	array(T_ZBX_STR, O_OPT, null,		null,			null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT, P_ACT,		null,			null),
	'filter_host' =>	array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_ip' =>		array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_dns' =>		array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_port' =>	array(T_ZBX_STR, O_OPT, null,		null,			null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,		null,			null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,		NOT_EMPTY,		'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,		NOT_EMPTY,		'isset({favobj})&&"filter"=={favobj}')
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Permissions
 */
if (get_request('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (get_request('hostid') && !API::Host()->isWritable(array($_REQUEST['hostid']))) {
	access_deny();
}

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if ('filter' == $_REQUEST['favobj']) {
		CProfile::update('web.hosts.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$hostIds = get_request('hosts', array());

/*
 * Export
 */
if ($exportData) {
	$export = new CConfigurationExport(array('hosts' => $hostIds));
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
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = get_request('templates', array());
	$_REQUEST['templates'] = array_merge($_REQUEST['templates'], $_REQUEST['add_templates']);
}
if (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear'])) {
	$_REQUEST['clear_templates'] = get_request('clear_templates', array());

	$unlinkTemplates = array();

	if (isset($_REQUEST['unlink'])) {
		// templates_rem for old style removal in massupdate form
		if (isset($_REQUEST['templates_rem'])) {
			$unlinkTemplates = array_keys($_REQUEST['templates_rem']);
		}
		elseif (is_array($_REQUEST['unlink'])) {
			$unlinkTemplates = array_keys($_REQUEST['unlink']);
		}
	}
	else {
		$unlinkTemplates = array_keys($_REQUEST['unlink_and_clear']);

		$_REQUEST['clear_templates'] = array_merge($_REQUEST['clear_templates'], $unlinkTemplates);
	}

	foreach ($unlinkTemplates as $templateId) {
		unset($_REQUEST['templates'][array_search($templateId, $_REQUEST['templates'])]);
	}
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])) {
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])) {
	$_REQUEST['form'] = 'full_clone';
}
elseif (isset($_REQUEST['go']) && $_REQUEST['go'] == 'massupdate' && isset($_REQUEST['masssave'])) {
	$hostIds = get_request('hosts', array());
	$visible = get_request('visible', array());
	$_REQUEST['proxy_hostid'] = get_request('proxy_hostid', 0);
	$_REQUEST['templates'] = get_request('templates', array());

	try {
		DBstart();

		// filter only normal hosts, ignore discovered
		$hosts = API::Host()->get(array(
			'output' => array('hostid'),
			'hostids' => $hostIds,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		));
		$hosts = array('hosts' => $hosts);

		$properties = array('proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'status');
		$newValues = array();
		foreach ($properties as $property) {
			if (isset($visible[$property])) {
				$newValues[$property] = $_REQUEST[$property];
			}
		}

		if (isset($visible['inventory_mode'])) {
			$newValues['inventory_mode'] = get_request('inventory_mode', HOST_INVENTORY_DISABLED);
			$newValues['inventory'] = ($newValues['inventory_mode'] == HOST_INVENTORY_DISABLED)
				? array() : get_request('host_inventory', array());
		}

		$templateIds = array();
		if (isset($visible['template_table'])) {
			$templateIds = $_REQUEST['templates'];
		}

		// add new or existing host groups
		if (isset($visible['new_groups']) && !empty($_REQUEST['new_groups'])) {
			if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				foreach ($_REQUEST['new_groups'] as $newGroup) {
					if (is_array($newGroup) && isset($newGroup['new'])) {
						$newGroups[] = array('name' => $newGroup['new']);
					}
					else {
						$existGroups[] = $newGroup;
					}
				}

				if (isset($newGroups)) {
					if (!$createdGroups = API::HostGroup()->create($newGroups)) {
						throw new Exception();
					}

					$existGroups = isset($existGroups)
						? array_merge($existGroups, $createdGroups['groupids']) : $createdGroups['groupids'];
				}
			}
			else {
				$existGroups = $_REQUEST['new_groups'];
			}
		}

		if (isset($visible['groups'])) {
			if (isset($_REQUEST['groups'])) {
				$replaceHostGroupsIds = isset($existGroups)
					? array_unique(array_merge($_REQUEST['groups'], $existGroups)) : $_REQUEST['groups'];
			}
			elseif (isset($existGroups)) {
				$replaceHostGroupsIds = $existGroups;
			}

			if (isset($replaceHostGroupsIds)) {
				$hosts['groups'] = API::HostGroup()->get(array(
					'groupids' => $replaceHostGroupsIds,
					'editable' => true,
					'output' => array('groupid')
				));
			}
			else {
				$hosts['groups'] = array();
			}
		}
		elseif (isset($existGroups)) {
			$existGroups = API::HostGroup()->get(array(
				'groupids' => $existGroups,
				'editable' => true,
				'output' => array('groupid')
			));
		}

		if (isset($_REQUEST['mass_replace_tpls'])) {
			if (isset($_REQUEST['mass_clear_tpls'])) {
				$hostTemplates = API::Template()->get(array(
					'hostids' => $hostIds
				));

				$hostTemplateIds = zbx_objectValues($hostTemplates, 'templateid');
				$templatesToDelete = array_diff($hostTemplateIds, $templateIds);

				$hosts['templates_clear'] = zbx_toObject($templatesToDelete, 'templateid');
			}

			$hosts['templates'] = $templateIds;
		}

		$result = API::Host()->massUpdate(array_merge($hosts, $newValues));
		if ($result === false) {
			throw new Exception();
		}

		$add = array();
		if ($templateIds && isset($visible['template_table'])) {
			$add['templates'] = $templateIds;
		}

		// add new host groups
		if (isset($existGroups) && (!isset($visible['groups']) || !isset($replaceHostGroups))) {
			$add['groups'] = $existGroups;
		}

		if ($add) {
			$add['hosts'] = $hosts['hosts'];

			$result = API::Host()->massAdd($add);

			if ($result === false) {
				throw new Exception();
			}
		}

		DBend(true);

		show_message(_('Hosts updated'));
		clearCookies(true);

		unset($_REQUEST['massupdate'], $_REQUEST['form'], $_REQUEST['hosts']);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update hosts'));
	}
	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['save'])) {
	try {
		DBstart();

		if (isset($_REQUEST['hostid']) && $_REQUEST['form'] != 'full_clone') {
			$createNew = false;
			$msgOk = _('Host updated');
			$msgFail = _('Cannot update host');

			$hostOld = API::Host()->get(array(
				'hostids' => get_request('hostid'),
				'editable' => true,
				'output' => API_OUTPUT_EXTEND
			));
			$hostOld = reset($hostOld);
		}
		else {
			$createNew = true;
			$msgOk = _('Host added');
			$msgFail = _('Cannot add host');
		}

		// updating an existing discovered host
		if (!$createNew && $hostOld['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$host = array(
				'hostid' => get_request('hostid'),
				'status' => get_request('status'),
				'inventory' => (get_request('inventory_mode') != HOST_INVENTORY_DISABLED) ? get_request('host_inventory', array()) : array()
			);
		}
		// creating or updating a normal host
		else {
			$macros = get_request('macros', array());
			$interfaces = get_request('interfaces', array());
			$templates = get_request('templates', array());
			$groups = get_request('groups', array());

			$linkedTemplates = $templates;
			$templates = array();
			foreach ($linkedTemplates as $templateId) {
				$templates[] = array('templateid' => $templateId);
			}

			foreach ($interfaces as $key => $interface) {
				if (zbx_empty($interface['ip']) && zbx_empty($interface['dns'])) {
					unset($interface[$key]);
					continue;
				}

				if ($interface['isNew']) {
					unset($interfaces[$key]['interfaceid']);
				}
				unset($interfaces[$key]['isNew']);
				$interfaces[$key]['main'] = 0;
			}

			$interfaceTypes = array(INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI);
			foreach ($interfaceTypes as $type) {
				if (isset($_REQUEST['mainInterfaces'][$type])) {
					$interfaces[$_REQUEST['mainInterfaces'][$type]]['main'] = '1';
				}
			}

			// ignore empty new macros, i.e., macros rows that have not been filled
			foreach ($macros as $key => $macro) {
				if (!isset($macro['hostmacroid']) && zbx_empty($macro['macro']) && zbx_empty($macro['value'])) {
					unset($macros[$key]);
				}
			}

			foreach ($macros as $key => $macro) {
				// transform macros to uppercase {$aaa} => {$AAA}
				$macros[$key]['macro'] = zbx_strtoupper($macro['macro']);
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
				'inventory' => (get_request('inventory_mode') != HOST_INVENTORY_DISABLED) ? get_request('host_inventory', array()) : null,
				'inventory_mode' => get_request('inventory_mode')
			);

			if (!$createNew) {
				$host['templates_clear'] = zbx_toObject(get_request('clear_templates', array()), 'templateid');
			}
		}

		if ($createNew) {
			$hostIds = API::Host()->create($host);

			if ($hostIds) {
				$hostId = reset($hostIds['hostids']);
			}
			else {
				throw new Exception();
			}

			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST, $hostId, $host['host'], null, null, null);
		}
		else {
			$hostId = $host['hostid'] = $_REQUEST['hostid'];

			if (!API::Host()->update($host)) {
				throw new Exception();
			}

			$hostNew = API::Host()->get(array(
				'hostids' => $hostId,
				'editable' => true,
				'output' => API_OUTPUT_EXTEND
			));
			$hostNew = reset($hostNew);

			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $hostNew['hostid'], $hostNew['host'], 'hosts', $hostOld, $hostNew);
		}

		if ($_REQUEST['form'] == 'full_clone') {
			$srcHostId = get_request('hostid');

			if (!copyApplications($srcHostId, $hostId)) {
				throw new Exception();
			}

			if (!copyItems($srcHostId, $hostId)) {
				throw new Exception();
			}

			// clone triggers
			$triggers = API::Trigger()->get(array(
				'output' => array('triggerid'),
				'hostids' => $srcHostId,
				'inherited' => false
			));
			if ($triggers) {
				if (!copyTriggersToHosts(zbx_objectValues($triggers, 'triggerid'), $hostId, $srcHostId)) {
					throw new Exception();
				}
			}

			// clone discovery rules
			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => array('itemid'),
				'hostids' => $srcHostId,
				'inherited' => false
			));
			if ($discoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy(array(
					'discoveryids' => zbx_objectValues($discoveryRules, 'itemid'),
					'hostids' => array($hostId)
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

				if (!copy_graph_to_host($graph['graphid'], $hostId)) {
					throw new Exception();
				}
			}
		}

		$result = DBend(true);

		show_messages($result, $msgOk, $msgFail);
		clearCookies($result);

		unset($_REQUEST['form'], $_REQUEST['hostid']);
	}
	catch (Exception $e) {
		DBend(false);
		show_messages(false, $msgOk, $msgFail);
	}

	unset($_REQUEST['save']);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])) {
	DBstart();

	$result = API::Host()->delete(array($_REQUEST['hostid']));
	$result = DBend($result);

	show_messages($result, _('Host deleted'), _('Cannot delete host'));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['hostid']);
	}

	unset($_REQUEST['delete']);
	clearCookies($result);
}
elseif ($_REQUEST['go'] == 'delete') {
	$hostIds = get_request('hosts', array());

	DBstart();

	$goResult = API::Host()->delete($hostIds);
	$goResult = DBend($goResult);

	show_messages($goResult, _('Host deleted'), _('Cannot delete host'));
	clearCookies($goResult);
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable'))) {
	$status = ($_REQUEST['go'] == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
	$hosts = get_request('hosts', array());
	$actHosts = API::Host()->get(array(
		'hostids' => $hosts,
		'editable' => true,
		'templated_hosts' => 1,
		'output' => array('hostid')
	));
	$actHosts = zbx_objectValues($actHosts, 'hostid');

	if ($actHosts) {
		DBstart();

		$goResult = updateHostStatus($actHosts, $status);
		$goResult = DBend($goResult);

		show_messages($goResult, _('Host status updated'), _('Cannot update host status'));
		clearCookies($goResult);
	}
}

/*
 * Display
 */
$hostsWidget = new CWidget();

$pageFilter = new CPageFilter(array(
	'groups' => array(
		'real_hosts' => true,
		'editable' => true
	),
	'groupid' => get_request('groupid', null)
));

$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = get_request('hostid', 0);

if ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['hosts'])) {
	$hostsWidget->addPageHeader(_('CONFIGURATION OF HOSTS'));

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
			andDbNode('h.hostid').
		' ORDER BY h.host'
	));

	// get inventories
	if ($data['inventory_mode'] != HOST_INVENTORY_DISABLED) {
		$data['inventories'] = getHostInventories();
		$data['inventories'] = zbx_toHash($data['inventories'], 'db_field');
	}

	// get templates data
	$data['linkedTemplates'] = null;
	if (!empty($data['templates'])) {
		$getLinkedTemplates = API::Template()->get(array(
			'templateids' => $data['templates'],
			'output' => array('templateid', 'name')
		));

		foreach ($getLinkedTemplates as $getLinkedTemplate) {
			$data['linkedTemplates'][] = array(
				'id' => $getLinkedTemplate['templateid'],
				'name' => $getLinkedTemplate['name']
			);
		}
	}

	$hostForm = new CView('configuration.host.massupdate', $data);
	$hostsWidget->addItem($hostForm->render());
}
elseif (isset($_REQUEST['form'])) {
	$hostsWidget->addPageHeader(_('CONFIGURATION OF HOSTS'));

	$data = array();
	if ($hostId = get_request('hostid', 0)) {
		$hostsWidget->addItem(get_header_host_table('', $_REQUEST['hostid']));

		$dbHosts = API::Host()->get(array(
			'hostids' => $hostId,
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => array('templateid', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectInventory' => true,
			'selectDiscoveryRule' => array('name', 'itemid'),
			'output' => API_OUTPUT_EXTEND
		));
		$dbHost = reset($dbHosts);

		$dbHost['interfaces'] = API::HostInterface()->get(array(
			'hostids' => $hostId,
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => array('type'),
			'sortfield' => 'interfaceid',
			'preservekeys' => true
		));

		$data['dbHost'] = $dbHost;
	}

	$hostForm = new CView('configuration.host.edit', $data);
	$hostsWidget->addItem($hostForm->render());

	$rootClass = 'host-edit';
	if (get_request('hostid') && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$rootClass .= ' host-edit-discovered';
	}
	$hostsWidget->setRootClass($rootClass);
}
else {
	$displayNodes = (is_array(get_current_nodeid()) && $pageFilter->groupid == 0);

	$frmForm = new CForm();
	$frmForm->cleanItems();
	$frmForm->addItem(new CDiv(array(
		new CSubmit('form', _('Create host')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=host")')
	)));
	$frmForm->addItem(new CVar('groupid', $_REQUEST['groupid'], 'filter_groupid_id'));

	$hostsWidget->addPageHeader(_('CONFIGURATION OF HOSTS'), $frmForm);

	$frmGroup = new CForm('get');
	$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB(true)));

	$hostsWidget->addHeader(_('Hosts'), $frmGroup);
	$hostsWidget->addHeaderRowNumber();
	$hostsWidget->setRootClass('host-list');

	// filter
	$filterTable = new CTable('', 'filter');
	$filterTable->addRow(array(
		array(array(bold(_('Name')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_host', $_REQUEST['filter_host'], 20)),
		array(array(bold(_('DNS')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_dns', $_REQUEST['filter_dns'], 20)),
		array(array(bold(_('IP')), SPACE._('like').NAME_DELIMITER), new CTextBox('filter_ip', $_REQUEST['filter_ip'], 20)),
		array(bold(_('Port').NAME_DELIMITER), new CTextBox('filter_port', $_REQUEST['filter_port'], 20))
	));

	$filter = new CButton('filter', _('Filter'),
		"javascript: create_var('zbx_filter', 'filter_set', '1', true); chkbxRange.clearSelectedOnFilterChange();"
	);
	$filter->useJQueryStyle('main');

	$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
	$reset->useJQueryStyle();

	$divButtons = new CDiv(array($filter, SPACE, $reset));
	$divButtons->setAttribute('style', 'padding: 4px 0;');

	$filterTable->addRow(new CCol($divButtons, 'center', 4));

	$filterForm = new CForm('get');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');
	$filterForm->addItem($filterTable);

	$hostsWidget->addFlicker($filterForm, CProfile::get('web.hosts.filter.state', 0));

	// table hosts
	$form = new CForm();
	$form->setName('hosts');

	$table = new CTableInfo(_('No hosts defined.'));
	$table->setHeader(array(
		new CCheckBox('all_hosts', null, "checkAll('".$form->getName()."', 'all_hosts', 'hosts');"),
		$displayNodes ? _('Node') : null,
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
		$hosts = API::Host()->get(array(
			'groupids' => ($pageFilter->groupid > 0) ? $pageFilter->groupid : null,
			'editable' => true,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => $config['search_limit'] + 1,
			'search' => array(
				'name' => empty($_REQUEST['filter_host']) ? null : $_REQUEST['filter_host'],
				'ip' => empty($_REQUEST['filter_ip']) ? null : $_REQUEST['filter_ip'],
				'dns' => empty($_REQUEST['filter_dns']) ? null : $_REQUEST['filter_dns']
			),
			'filter' => array(
				'port' => empty($_REQUEST['filter_port']) ? null : $_REQUEST['filter_port']
			)
		));
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
		'selectHttpTests' => API_OUTPUT_COUNT,
		'selectDiscoveryRule' => array('itemid', 'name'),
		'selectHostDiscovery' => array('ts_delete')
	));
	order_result($hosts, $sortfield, $sortorder);

	// selecting linked templates to templates linked to hosts
	$templateIds = array();
	foreach ($hosts as $host) {
		$templateIds = array_merge($templateIds, zbx_objectValues($host['parentTemplates'], 'templateid'));
	}
	$templateIds = array_unique($templateIds);

	$templates = API::Template()->get(array(
		'templateids' => $templateIds,
		'selectParentTemplates' => array('hostid', 'name')
	));
	$templates = zbx_toHash($templates, 'templateid');

	// get proxy host IDs that that are not 0
	$proxyHostIds = array();
	foreach ($hosts as $host) {
		if ($host['proxy_hostid']) {
			$proxyHostIds[$host['proxy_hostid']] = $host['proxy_hostid'];
		}
	}
	if ($proxyHostIds) {
		$proxies = API::Proxy()->get(array(
			'proxyids' => $proxyHostIds,
			'output' => array('host'),
			'preservekeys' => true
		));
	}

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

		if (isset($proxies[$host['proxy_hostid']])) {
			$description[] = $proxies[$host['proxy_hostid']]['host'].NAME_DELIMITER;
		}
		if ($host['discoveryRule']) {
			$description[] = new CLink($host['discoveryRule']['name'], 'host_prototypes.php?parent_discoveryid='.$host['discoveryRule']['itemid'], 'gold');
			$description[] = NAME_DELIMITER;
		}

		$description[] = new CLink(CHtml::encode($host['name']), 'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid'));

		$hostInterface = ($interface['useip'] == INTERFACE_USE_IP) ? $interface['ip'] : $interface['dns'];
		$hostInterface .= empty($interface['port']) ? '' : NAME_DELIMITER.$interface['port'];

		$statusScript = null;

		switch ($host['status']) {
			case HOST_STATUS_MONITORED:
				if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
					$statusCaption = _('In maintenance');
					$statusClass = 'orange';
				}
				else {
					$statusCaption = _('Monitored');
					$statusClass = 'enabled';
				}

				$statusScript = 'return Confirm('.zbx_jsvalue(_('Disable host?')).');';
				$statusUrl = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=disable'.url_param('groupid');
				break;

			case HOST_STATUS_NOT_MONITORED:
				$statusCaption = _('Not monitored');
				$statusUrl = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=activate'.url_param('groupid');
				$statusScript = 'return Confirm('.zbx_jsvalue(_('Enable host?')).');';
				$statusClass = 'disabled';
				break;

			default:
				$statusCaption = _('Unknown');
				$statusScript = 'return Confirm('.zbx_jsvalue(_('Disable host?')).');';
				$statusUrl = 'hosts.php?hosts'.SQUAREBRACKETS.'='.$host['hostid'].'&go=disable'.url_param('groupid');
				$statusClass = 'unknown';
		}

		$status = new CLink($statusCaption, $statusUrl, $statusClass, $statusScript);

		if (empty($host['parentTemplates'])) {
			$hostTemplates = '-';
		}
		else {
			$hostTemplates = array();
			order_result($host['parentTemplates'], 'name');

			foreach ($host['parentTemplates'] as $template) {
				$caption = array();
				$caption[] = new CLink(CHtml::encode($template['name']), 'templates.php?form=update&templateid='.$template['templateid'], 'unknown');

				if (!empty($templates[$template['templateid']]['parentTemplates'])) {
					order_result($templates[$template['templateid']]['parentTemplates'], 'name');

					$caption[] = ' (';
					foreach ($templates[$template['templateid']]['parentTemplates'] as $tpl) {
						$caption[] = new CLink(CHtml::encode($tpl['name']),'templates.php?form=update&templateid='.$tpl['templateid'], 'unknown');
						$caption[] = ', ';
					}
					array_pop($caption);

					$caption[] = ')';
				}

				$hostTemplates[] = $caption;
				$hostTemplates[] = ', ';
			}

			if ($hostTemplates) {
				array_pop($hostTemplates);
			}
		}

		$table->addRow(array(
			new CCheckBox('hosts['.$host['hostid'].']', null, null, $host['hostid']),
			$displayNodes ? get_node_name_by_elid($host['hostid'], true) : null,
			$description,
			$applications,
			$items,
			$triggers,
			$graphs,
			$discoveries,
			$httpTests,
			$hostInterface,
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

	$form->addItem(array($paging, $table, $paging, get_table_header(array($goBox, $goButton))));
	$hostsWidget->addItem($form);
}

$hostsWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
