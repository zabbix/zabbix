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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

if (hasRequest('action') && getRequest('action') == 'host.export' && hasRequest('hosts')) {
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
	'hostid' =>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		'isset({form}) && {form} == "update"'),
	'host' =>			array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})', _('Host name')),
	'visiblename' =>	array(T_ZBX_STR, O_OPT, null,			null,		'isset({add}) || isset({update})'),
	'description' =>	array(T_ZBX_STR, O_OPT, null,			null,		null),
	'proxy_hostid' =>	array(T_ZBX_INT, O_OPT, P_SYS,		    DB_ID,		null),
	'status' =>			array(T_ZBX_INT, O_OPT, null,			IN(array(HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED)), null),
	'newgroup' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	'interfaces' =>		array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})', _('Agent or SNMP or JMX or IPMI interface')),
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
	'visible' =>		array(T_ZBX_STR, O_OPT, null,			null,		null),
	// actions
	'action' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"host.export","host.massdelete","host.massdisable","host.massenable","host.massupdate"'.
								',"host.massupdateform"'
							),
							null
						),
	'add_to_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'delete_from_group' => array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'unlink' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'unlink_and_clear' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'update' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'masssave' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'full_clone' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,		null,			null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'filter_rst' =>		array(T_ZBX_STR, O_OPT, P_SYS,		null,			null),
	'filter_host' =>	array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_ip' =>		array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_dns' =>		array(T_ZBX_STR, O_OPT, null,		null,			null),
	'filter_port' =>	array(T_ZBX_STR, O_OPT, null,		null,			null),
	// ajax
	'filterState' =>	array(T_ZBX_INT, O_OPT, P_ACT,		null,			null),
	// sort and sortorder
	'sort' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null),
	'sortorder' =>		array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isWritable(array($_REQUEST['hostid']))) {
	access_deny();
}

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.hosts.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}
if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$hostIds = getRequest('hosts', array());

/*
 * Export
 */
if ($exportData) {
	$export = new CConfigurationExport(array('hosts' => $hostIds));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		print($exportData);
	}

	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.hosts.filter_ip', getRequest('filter_ip', ''), PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_dns', getRequest('filter_dns', ''), PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_host', getRequest('filter_host', ''), PROFILE_TYPE_STR);
	CProfile::update('web.hosts.filter_port', getRequest('filter_port', ''), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.hosts.filter_ip');
	CProfile::delete('web.hosts.filter_dns');
	CProfile::delete('web.hosts.filter_host');
	CProfile::delete('web.hosts.filter_port');
	DBend();
}

$filter['ip'] = CProfile::get('web.hosts.filter_ip', '');
$filter['dns'] = CProfile::get('web.hosts.filter_dns', '');
$filter['host'] = CProfile::get('web.hosts.filter_host', '');
$filter['port'] = CProfile::get('web.hosts.filter_port', '');

/*
 * Actions
 */
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = getRequest('templates', array());
	$_REQUEST['templates'] = array_merge($_REQUEST['templates'], $_REQUEST['add_templates']);
}
if (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear'])) {
	$_REQUEST['clear_templates'] = getRequest('clear_templates', array());

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
elseif (hasRequest('action') && getRequest('action') == 'host.massupdate' && hasRequest('masssave')) {
	$hostIds = getRequest('hosts', array());
	$visible = getRequest('visible', array());
	$_REQUEST['proxy_hostid'] = getRequest('proxy_hostid', 0);
	$_REQUEST['templates'] = getRequest('templates', array());

	try {
		DBstart();

		// filter only normal hosts, ignore discovered
		$hosts = API::Host()->get(array(
			'output' => array('hostid'),
			'hostids' => $hostIds,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL)
		));
		$hosts = array('hosts' => $hosts);

		$properties = array(
			'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'description'
		);

		$newValues = array();
		foreach ($properties as $property) {
			if (isset($visible[$property])) {
				$newValues[$property] = $_REQUEST[$property];
			}
		}

		if (isset($visible['status'])) {
			$newValues['status'] = getRequest('status', HOST_STATUS_NOT_MONITORED);
		}

		if (isset($visible['inventory_mode'])) {
			$newValues['inventory_mode'] = getRequest('inventory_mode', HOST_INVENTORY_DISABLED);
			$newValues['inventory'] = ($newValues['inventory_mode'] == HOST_INVENTORY_DISABLED)
				? array() : getRequest('host_inventory', array());
		}

		$templateIds = array();
		if (isset($visible['templates'])) {
			$templateIds = $_REQUEST['templates'];
		}

		// add new or existing host groups
		$newHostGroupIds = array();
		if (isset($visible['new_groups']) && !empty($_REQUEST['new_groups'])) {
			if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				foreach ($_REQUEST['new_groups'] as $newGroup) {
					if (is_array($newGroup) && isset($newGroup['new'])) {
						$newGroups[] = array('name' => $newGroup['new']);
					}
					else {
						$newHostGroupIds[] = $newGroup;
					}
				}

				if (isset($newGroups)) {
					if (!$createdGroups = API::HostGroup()->create($newGroups)) {
						throw new Exception();
					}

					$newHostGroupIds = $newHostGroupIds
						? array_merge($newHostGroupIds, $createdGroups['groupids'])
						: $createdGroups['groupids'];
				}
			}
			else {
				$newHostGroupIds = getRequest('new_groups');
			}
		}

		if (isset($visible['groups'])) {
			if (isset($_REQUEST['groups'])) {
				$replaceHostGroupsIds = $newHostGroupIds
					? array_unique(array_merge(getRequest('groups'), $newHostGroupIds))
					: $_REQUEST['groups'];
			}
			elseif ($newHostGroupIds) {
				$replaceHostGroupsIds = $newHostGroupIds;
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
		elseif ($newHostGroupIds) {
			$newHostGroups = API::HostGroup()->get(array(
				'groupids' => $newHostGroupIds,
				'editable' => true,
				'output' => array('groupid')
			));
		}

		if (isset($_REQUEST['mass_replace_tpls'])) {
			if (isset($_REQUEST['mass_clear_tpls'])) {
				$hostTemplates = API::Template()->get(array(
					'output' => array('templateid'),
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
		if ($templateIds && isset($visible['templates'])) {
			$add['templates'] = $templateIds;
		}

		// add new host groups
		if ($newHostGroupIds && (!isset($visible['groups']) || !isset($replaceHostGroups))) {
			$add['groups'] = zbx_toObject($newHostGroupIds, 'groupid');
		}

		if ($add) {
			$add['hosts'] = $hosts['hosts'];

			$result = API::Host()->massAdd($add);

			if ($result === false) {
				throw new Exception();
			}
		}

		DBend(true);

		uncheckTableRows();
		show_message(_('Hosts updated'));

		unset($_REQUEST['massupdate'], $_REQUEST['form'], $_REQUEST['hosts']);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update hosts'));
	}
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		DBstart();

		$hostId = getRequest('hostid');

		if ($hostId && getRequest('form') !== 'full_clone') {
			$create = false;

			$msgOk = _('Host updated');
			$msgFail = _('Cannot update host');

			$dbHost = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostId,
				'editable' => true
			));
			$dbHost = reset($dbHost);
		}
		else {
			$create = true;

			$msgOk = _('Host added');
			$msgFail = _('Cannot add host');
		}

		// host data
		if (!$create && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$host = array(
				'hostid' => $hostId,
				'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
				'description' => getRequest('description', ''),
				'inventory' => (getRequest('inventory_mode') == HOST_INVENTORY_DISABLED)
					? array()
					: getRequest('host_inventory', array())
			);
		}
		else {
			// templates
			$templates = array();

			foreach (getRequest('templates', array()) as $templateId) {
				$templates[] = array('templateid' => $templateId);
			}

			// interfaces
			$interfaces = getRequest('interfaces', array());

			foreach ($interfaces as $key => $interface) {
				if (zbx_empty($interface['ip']) && zbx_empty($interface['dns'])) {
					unset($interface[$key]);
					continue;
				}

				if ($interface['type'] == INTERFACE_TYPE_SNMP && !isset($interface['bulk'])) {
					$interfaces[$key]['bulk'] = SNMP_BULK_DISABLED;
				}
				else {
					$interfaces[$key]['bulk'] = SNMP_BULK_ENABLED;
				}

				if ($interface['isNew']) {
					unset($interfaces[$key]['interfaceid']);
				}

				unset($interfaces[$key]['isNew']);
				$interfaces[$key]['main'] = 0;
			}

			$interfaceTypes = array(INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI);

			foreach ($interfaceTypes as $interfaceType) {
				if (isset($_REQUEST['mainInterfaces'][$interfaceType])) {
					$interfaces[$_REQUEST['mainInterfaces'][$interfaceType]]['main'] = INTERFACE_PRIMARY;
				}
			}

			// macros
			$macros = getRequest('macros', array());

			foreach ($macros as $key => $macro) {
				if (!isset($macro['hostmacroid']) && zbx_empty($macro['macro']) && zbx_empty($macro['value'])) {
					unset($macros[$key]);
				}
				else {
					// transform macros to uppercase {$aaa} => {$AAA}
					$macros[$key]['macro'] = mb_strtoupper($macro['macro']);
				}
			}

			// new group
			$groups = getRequest('groups', array());
			$newGroup = getRequest('newgroup');

			if (!zbx_empty($newGroup)) {
				$newGroup = API::HostGroup()->create(array('name' => $newGroup));

				if (!$newGroup) {
					throw new Exception();
				}

				$groups[] = reset($newGroup['groupids']);
			}

			$groups = zbx_toObject($groups, 'groupid');

			// host data
			$host = array(
				'host' => getRequest('host'),
				'name' => getRequest('visiblename'),
				'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
				'description' => getRequest('description'),
				'proxy_hostid' => getRequest('proxy_hostid', 0),
				'ipmi_authtype' => getRequest('ipmi_authtype'),
				'ipmi_privilege' => getRequest('ipmi_privilege'),
				'ipmi_username' => getRequest('ipmi_username'),
				'ipmi_password' => getRequest('ipmi_password'),
				'groups' => $groups,
				'templates' => $templates,
				'interfaces' => $interfaces,
				'macros' => $macros,
				'inventory_mode' => getRequest('inventory_mode'),
				'inventory' => (getRequest('inventory_mode') == HOST_INVENTORY_DISABLED)
					? array()
					: getRequest('host_inventory', array())
			);

			if (!$create) {
				$host['templates_clear'] = zbx_toObject(getRequest('clear_templates', array()), 'templateid');
			}
		}

		if ($create) {
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
			$host['hostid'] = $hostId;

			if (!API::Host()->update($host)) {
				throw new Exception();
			}

			$dbHostNew = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostId,
				'editable' => true
			));
			$dbHostNew = reset($dbHostNew);

			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $dbHostNew['hostid'], $dbHostNew['host'], 'hosts',
				$dbHost, $dbHostNew);
		}

		// full clone
		if (getRequest('form') === 'full_clone') {
			$srcHostId = getRequest('hostid');

			// copy applications
			if (!copyApplications($srcHostId, $hostId)) {
				throw new Exception();
			}

			// copy items
			if (!copyItems($srcHostId, $hostId)) {
				throw new Exception();
			}

			// copy web scenarios
			if (!copyHttpTests($srcHostId, $hostId)) {
				throw new Exception();
			}

			// copy triggers
			$dbTriggers = API::Trigger()->get(array(
				'output' => array('triggerid'),
				'hostids' => $srcHostId,
				'inherited' => false
			));

			if ($dbTriggers) {
				if (!copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'), $hostId, $srcHostId)) {
					throw new Exception();
				}
			}

			// copy discovery rules
			$dbDiscoveryRules = API::DiscoveryRule()->get(array(
				'output' => array('itemid'),
				'hostids' => $srcHostId,
				'inherited' => false
			));

			if ($dbDiscoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy(array(
					'discoveryids' => zbx_objectValues($dbDiscoveryRules, 'itemid'),
					'hostids' => array($hostId)
				));

				if (!$copyDiscoveryRules) {
					throw new Exception();
				}
			}

			// copy graphs
			$dbGraphs = API::Graph()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => array('hostid'),
				'selectItems' => array('type'),
				'hostids' => $srcHostId,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
				'inherited' => false
			));

			foreach ($dbGraphs as $dbGraph) {
				if (count($dbGraph['hosts']) > 1) {
					continue;
				}

				if (httpItemExists($dbGraph['items'])) {
					continue;
				}

				if (!copyGraphToHost($dbGraph['graphid'], $hostId)) {
					throw new Exception();
				}
			}
		}

		$result = DBend(true);

		if ($result) {
			uncheckTableRows();
		}
		show_messages($result, $msgOk, $msgFail);

		unset($_REQUEST['form'], $_REQUEST['hostid']);
	}
	catch (Exception $e) {
		DBend(false);
		show_messages(false, $msgOk, $msgFail);
	}
}
elseif (hasRequest('delete') && hasRequest('hostid')) {
	DBstart();

	$result = API::Host()->delete(array(getRequest('hostid')));
	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['hostid']);
		uncheckTableRows();
	}
	show_messages($result, _('Host deleted'), _('Cannot delete host'));

	unset($_REQUEST['delete']);
}
elseif (hasRequest('action') && getRequest('action') == 'host.massdelete' && hasRequest('hosts')) {
	DBstart();

	$result = API::Host()->delete(getRequest('hosts'));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Host deleted'), _('Cannot delete host'));
}
elseif (hasRequest('action') && str_in_array(getRequest('action'), array('host.massenable', 'host.massdisable')) && hasRequest('hosts')) {
	$enable =(getRequest('action') == 'host.massenable');
	$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;

	$actHosts = API::Host()->get(array(
		'hostids' => getRequest('hosts'),
		'editable' => true,
		'templated_hosts' => true,
		'output' => array('hostid')
	));
	$actHosts = zbx_objectValues($actHosts, 'hostid');

	if ($actHosts) {
		DBstart();

		$result = updateHostStatus($actHosts, $status);
		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}

		$updated = count($actHosts);

		$messageSuccess = $enable
			? _n('Host enabled', 'Hosts enabled', $updated)
			: _n('Host disabled', 'Hosts disabled', $updated);
		$messageFailed = $enable
			? _n('Cannot enable host', 'Cannot enable hosts', $updated)
			: _n('Cannot disable host', 'Cannot disable hosts', $updated);

		show_messages($result, $messageSuccess, $messageFailed);
	}
}

/*
 * Display
 */
$pageFilter = new CPageFilter(array(
	'groups' => array(
		'real_hosts' => true,
		'editable' => true
	),
	'groupid' => getRequest('groupid')
));

$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = getRequest('hostid', 0);

if (hasRequest('action') && getRequest('action') === 'host.massupdateform' && hasRequest('hosts')) {
	$data = array(
		'hosts' => getRequest('hosts'),
		'visible' => getRequest('visible', array()),
		'mass_replace_tpls' => getRequest('mass_replace_tpls'),
		'mass_clear_tpls' => getRequest('mass_clear_tpls'),
		'groups' => getRequest('groups', array()),
		'newgroup' => getRequest('newgroup', ''),
		'status' => getRequest('status', HOST_STATUS_MONITORED),
		'description' => getRequest('description'),
		'proxy_hostid' => getRequest('proxy_hostid', ''),
		'ipmi_authtype' => getRequest('ipmi_authtype', IPMI_AUTHTYPE_DEFAULT),
		'ipmi_privilege' => getRequest('ipmi_privilege', IPMI_PRIVILEGE_USER),
		'ipmi_username' => getRequest('ipmi_username', ''),
		'ipmi_password' => getRequest('ipmi_password', ''),
		'inventory_mode' => getRequest('inventory_mode', HOST_INVENTORY_DISABLED),
		'host_inventory' => getRequest('host_inventory', array()),
		'templates' => getRequest('templates', array()),
		'inventories' => zbx_toHash(getHostInventories(), 'db_field')
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
		' WHERE h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'
	));
	order_result($data['proxies'], 'host');

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

	$hostView = new CView('configuration.host.massupdate', $data);
}
elseif (hasRequest('form')) {
	$data = array(
		'form' => getRequest('form'),
		'hostId' => getRequest('hostid', 0),
		'groupId' => getRequest('groupid', 0)
	);

	if ($data['hostId']) {
		$dbHosts = API::Host()->get(array(
			'hostids' => $data['hostId'],
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => array('templateid', 'name'),
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectInventory' => true,
			'selectDiscoveryRule' => array('name', 'itemid'),
			'output' => API_OUTPUT_EXTEND
		));
		$dbHost = reset($dbHosts);

		$dbHost['interfaces'] = API::HostInterface()->get(array(
			'hostids' => $data['hostId'],
			'output' => API_OUTPUT_EXTEND,
			'selectItems' => array('type'),
			'sortfield' => 'interfaceid',
			'preservekeys' => true
		));

		$data['dbHost'] = $dbHost;
	}

	$hostView = new CView('configuration.host.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// get Hosts
	$hosts = array();
	if ($pageFilter->groupsSelected) {
		$hosts = API::Host()->get(array(
			'output' => array('hostid', 'name', 'status'),
			'groupids' => ($pageFilter->groupid > 0) ? $pageFilter->groupid : null,
			'editable' => true,
			'sortfield' => $sortField,
			'sortorder' => $sortOrder,
			'limit' => $config['search_limit'] + 1,
			'search' => array(
				'name' => ($filter['host'] === '') ? null : $filter['host'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			),
			'filter' => array(
				'port' => ($filter['port'] === '') ? null : $filter['port']
			)
		));
	}
	order_result($hosts, $sortField, $sortOrder);

	$pagingLine = getPagingLine($hosts);

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
	order_result($hosts, $sortField, $sortOrder);

	// selecting linked templates to templates linked to hosts
	$templateIds = array();
	foreach ($hosts as $host) {
		$templateIds = array_merge($templateIds, zbx_objectValues($host['parentTemplates'], 'templateid'));
	}
	$templateIds = array_unique($templateIds);

	$templates = API::Template()->get(array(
		'output' => array('templateid', 'name'),
		'templateids' => $templateIds,
		'selectParentTemplates' => array('hostid', 'name'),
		'preservekeys' => true
	));

	// get proxy host IDs that that are not 0
	$proxyHostIds = array();
	foreach ($hosts as $host) {
		if ($host['proxy_hostid']) {
			$proxyHostIds[$host['proxy_hostid']] = $host['proxy_hostid'];
		}
	}
	$proxies = array();
	if ($proxyHostIds) {
		$proxies = API::Proxy()->get(array(
			'proxyids' => $proxyHostIds,
			'output' => array('host'),
			'preservekeys' => true
		));
	}

	$data = array(
		'pageFilter' => $pageFilter,
		'hosts' => $hosts,
		'paging' => $pagingLine,
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'groupId' => $pageFilter->groupid,
		'config' => $config,
		'templates' => $templates,
		'proxies' => $proxies
	);

	$hostView = new CView('configuration.host.list', $data);
}

$hostView->render();
$hostView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
