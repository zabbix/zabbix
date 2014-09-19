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

require_once dirname(__FILE__) . '/include/page_header.php';

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
									',"host.massupdateform","host.start_add","host.add","host.start_update","host.update"'
								),
								null
	),
	'add_to_group' =>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'delete_from_group' => array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	DB_ID,		null),
	'unlink' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'unlink_and_clear' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'start_clone' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'start_full_clone' => array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'start_add' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
	'start_update' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,		null),
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
if (getRequest('groupid') && !API::HostGroup()->isWritable(array(getRequest('groupid')))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isWritable(array(getRequest('hostid')))) {
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

/*
 * Export
 */
if ($exportData) {
	$export = new CConfigurationExport(array('hosts' => getRequest('hosts', array())));
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
/* Form actions */
if (hasRequest('start_add')) {
	$host = fetchHostFromRequest();

	showEditHostForm($host, 'add');
}
elseif (hasRequest('add')) {
	$host = fetchHostFromRequest();

	if (doAddHost($host)) {
		showList();
	}
	else {
		showEditHostForm($host, 'add');
	}
}
elseif (hasRequest('start_update')) {
	$host = fetchHostFromDb(getRequest('hostid'));
	showEditHostForm($host, 'update');
}
elseif (hasRequest('update')) {
	$host = fetchHostFromRequest();

	if (doUpdateHost($host)) {
		showList();
	}
	else {
		showEditHostForm($host, 'update');
	}
}
elseif (hasRequest('start_clone')) {
	$host = fetchHostFromRequest();

	showEditHostForm($host, 'clone');
}
elseif (hasRequest('clone')) {
	$host = fetchHostFromRequest();

	if (doCloneHost($host)) {
		showList();
	}
	else {
		showEditHostForm($host, 'clone');
	}
}
elseif (hasRequest('start_full_clone')) {
	$host = fetchHostFromRequest();

	showEditHostForm($host, 'full_clone');
}
elseif (hasRequest('full_clone')) {
	$host = fetchHostFromRequest();

	if (doFullCloneHost($host)) {
		showList();
	}
	else {
		showEditHostForm($host, 'full_clone');
	}
}
elseif (hasRequest('delete')) {
	doDeleteHost(getRequest('hostid'));

	showList();
}
/* Template linkage actions */
elseif (hasRequest('add_template')) {
	$host = fetchHostFromRequest();

	$host = doLinkTemplatesToHost($host, getRequest('add_templates', array()));

	showEditHostForm($host, getRequest('form'));
}
elseif (hasRequest('unlink')) {
	$host = fetchHostFromRequest();

	$templateIdsToUnlink = array_keys(getRequest('unlink', array()));

	$host = doUnlinkTemplatesFromHost($host, $templateIdsToUnlink);

	showEditHostForm($host, getRequest('form'));
}
elseif (hasRequest('unlink_and_clear')) {
	$host = fetchHostFromRequest();

	$templateIdsToUnlinkAndClear = getRequest('unlink_and_clear', array());

	$host = doUnlinkAndClearTemplatesFromHost($host, $templateIdsToUnlinkAndClear);

	showEditHostForm($host, getRequest('form'));
}
/* Mass actions from hosts list */
elseif (hasRequest('action')) {
	$action = getRequest('action');
	$hostIds = getRequest('hosts', array());
	if ($action == 'host.massupdate') {
		doHostsMassUpdate($hostIds);
	}
	elseif ($action == 'host.massenable') {
		doMassSetStatus($hostIds, HOST_STATUS_MONITORED);
	}
	elseif ($action == 'host.massdisable') {
		doMassSetStatus($hostIds, HOST_STATUS_NOT_MONITORED);
	}
	elseif ($action == 'host.massdelete') {
		doHostsMassDelete($hostIds);
	}
	showList();
}
/* List action is default */
else {
	showList();
}

require_once dirname(__FILE__) . '/include/page_footer.php';

function doMassSetStatus(array $hostIds, $status) {
	$hosts = API::Host()->get(array(
			'hostids' => $hostIds,
			'editable' => true,
			'templated_hosts' => true,
			'output' => array('hostid')
		));
	$hosts = zbx_objectValues($hosts, 'hostid');

	if ($hosts) {
		DBstart();

		$result = updateHostStatus($hosts, $status);

		$result = DBend($result);

		if ($result) {
			uncheckTableRows();
		}

		$updatedHostCount = count($hosts);

		$messageSuccess = ($status == HOST_STATUS_MONITORED)
			? _n('Host enabled', 'Hosts enabled', $updatedHostCount)
			: _n('Host disabled', 'Hosts disabled', $updatedHostCount);
		$messageFailed = ($status == HOST_STATUS_MONITORED)
			? _n('Cannot enable host', 'Cannot enable hosts', $updatedHostCount)
			: _n('Cannot disable host', 'Cannot disable hosts', $updatedHostCount);

		show_messages($result, $messageSuccess, $messageFailed);
	}
}

function doHostsMassDelete(array $hostIds) {
	DBstart();

	$result = API::Host()->delete($hostIds);
	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}

	show_messages($result, _('Host deleted'), _('Cannot delete host'));
}

function doHostsMassUpdate(
	/** @noinspection PhpUnusedParameterInspection */
	array $hostIds) {
	die('TBD');
}

function showList() {
	global $page, $config, $filter;

	$hostsWidget = new CWidget();

	$pageFilter = new CPageFilter(array(
		'groups' => array(
			'real_hosts' => true,
			'editable' => true
		),
		'groupid' => getRequest('groupid')
	));

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
			'selectParentTemplates' => array('hostid', 'name')
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
		'hostsWidget' => $hostsWidget,
		'pageFilter' => $pageFilter,
		'hosts' => $hosts,
		'paging' => getPagingLine($hosts),
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'groupId' => $pageFilter->groupid,
		'config' => $config,
		'templates' => zbx_toHash($templates, 'templateid'),
		'proxies' => $proxies
	);

	$view = new CView('configuration.host.list', $data);
	$hostsWidget->addItem($view->render());
	$hostsWidget->show();
}

function showEditHostForm(array $host, $formMode) {
	$pageFilter = new CPageFilter(array(
		'groups' => array(
			'real_hosts' => true,
			'editable' => true
		),
		'groupid' => getRequest('groupid')
	));

	$data = array(
		'host' => $host,
		'form' => $formMode,
		'hostProxy' => array(),
		'currentUserType' => CWebUser::$data['type'],
		'allGroups' => API::HostGroup()->get(array('editable' => true, 'output' => API_OUTPUT_EXTEND))
	);
	order_result($data['allGroups'], 'name');

	$hostsWidget = new CWidget();
	$rootClass = 'host-edit';

	if ($host['hostid']) {
		$dbHost = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => array($host['hostid'])
			));
		$dbHost = reset($dbHost);

		if ($dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$rootClass .= ' host-edit-discovered';
		}

		if ($formMode == 'full_clone') {
			$proxy = API::Proxy()->get(array(
					'output' => array('host', 'proxyid'),
					'proxyids' => $dbHost['proxy_hostid'],
					'limit' => 1
				));
			$data['hostProxy'] = reset($proxy);

			$applications = API::Application()->get(array(
					'output' => array('name'),
					'hostids' => $host['hostid'],
					'inherited' => false,
					'preservekeys' => true
				));
			$data['hostApplications'] = $applications;

			$items = API::Item()->get(array(
					'output' => array('itemid', 'hostid', 'key_', 'name'),
					'hostids' => $host['hostid'],
					'inherited' => false,
					'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
					'preservekeys' => true
				));
			$data['hostItems'] = CMacrosResolverHelper::resolveItemNames($items);

			$allTriggers = API::Trigger()->get(array(
					'output' => array('triggerid', 'description'),
					'inherited' => false,
					'hostids' => $host['hostid'],
					'selectItems' => array('type'),
					'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
					'preservekeys' => true
				));
			$triggers = array();
			foreach ($allTriggers as $triggerId => $trigger) {
				// do not take triggers with web scenario items
				foreach($trigger['items'] as $item) {
					if ($item['type'] == ITEM_TYPE_HTTPTEST) {
						continue 2;
					}
				}
				$triggers[$triggerId] = $trigger;
			}
			$data['hostTriggers'] = $triggers;

			$allGraphs = API::Graph()->get(array(
					'output' => array('graphid', 'name'),
					'inherited' => false,
					'hostids' => $host['hostid'],
					'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL)),
					'selectHosts' => array('hostid'),
					'selectItems' => array('type'),
					'preservekeys' => true
				));
			$graphs = array();
			if ($allGraphs) {
				foreach ($allGraphs as $graphId => $graph) {
					if (count($graph['hosts']) > 1) {
						continue;
					}
					foreach($graph['items'] as $item) {
						if ($item['type'] == ITEM_TYPE_HTTPTEST) {
							continue 2;
						}
					}
					$graphs[$graphId] = $graph;
				}
			}
			$data['hostGraphs'] = $graphs;

			$discoveryRules = API::DiscoveryRule()->get(array(
					'output' => array('itemid', 'hostid', 'key_', 'name'),
					'inherited' => false,
					'hostids' => $host['hostid'],
					'preservekeys' => true
				));
			$data['hostDiscoveryRules'] = CMacrosResolverHelper::resolveItemNames($discoveryRules);
			$discoveryRuleIds = array_keys($discoveryRules);

			$itemPrototypes = API::ItemPrototype()->get(array(
					'output' => array('itemid', 'hostid', 'key_', 'name'),
					'hostids' => $host['hostid'],
					'discoveryids' => $discoveryRuleIds,
					'inherited' => false,
					'preservekeys' => true
				));
			$data['hostItemPrototypes'] = CMacrosResolverHelper::resolveItemNames($itemPrototypes);

			$allTriggerPrototypes = API::TriggerPrototype()->get(array(
					'output' => array('triggerid', 'description'),
					'hostids' => $host['hostid'],
					'discoveryids' => $discoveryRuleIds,
					'inherited' => false,
					'selectItems' => array('type'),
					'preservekeys' => true
				));
			$triggerPrototypes = array();
			if ($allTriggerPrototypes) {
				foreach ($triggerPrototypes as $triggerPrototypeId => $triggerPrototype) {
					foreach($triggerPrototype['items'] as $item) {
						if ($item['type'] == ITEM_TYPE_HTTPTEST) {
							continue 2;
						}
					}
					$triggerPrototypes[$triggerPrototypeId] = $triggerPrototype;
				}
			}
			$data['hostTriggerPrototypes'] = $triggerPrototypes;

			$allGraphPrototypes = API::GraphPrototype()->get(array(
					'output' => array('graphid', 'name'),
					'hostids' => $host['hostid'],
					'discoveryids' => $discoveryRuleIds,
					'inherited' => false,
					'selectHosts' => array('hostid'),
					'preservekeys' => true
				));
			$graphPrototypes = array();
			if ($allGraphPrototypes) {
				foreach ($allGraphPrototypes as $graphPrototypeId => $graphPrototype) {
					if (count($graphPrototype['hosts']) == 1) {
						$graphPrototypes[$graphPrototypeId] = $graphPrototype;
					}
				}
			}
			$data['hostGraphPrototypes'] = $graphPrototypes;

			$hostPrototypes = API::HostPrototype()->get(array(
					'output' => array('hostid', 'name'),
					'discoveryids' => $discoveryRuleIds,
					'inherited' => false,
					'preservekeys' => true
				));
			$data['hostHostPrototypes'] = $hostPrototypes;

			$httpTests = API::HttpTest()->get(array(
					'output' => array('httptestid', 'name'),
					'hostids' => $host['hostid'],
					'inherited' => false,
					'preservekeys' => true
				));
			$data['hostHttpTests'] = $httpTests;
		}

		$data['hostLinkedTemplates'] = API::Template()->get(array(
				'output' => array('templateid', 'name'),
				'templateids' => $host['templates'],
				'preservekeys' => true
			));
		CArrayHelper::sort($data['hostLinkedTemplates'], array('name'));

		$inventoryItems = API::Item()->get(array(
				'output' => array('inventory_link', 'itemid', 'hostid', 'name', 'key_'),
				'filter' => array('hostid' => $host['hostid']),
				'preserveKeys' => true,
				'nopermissions' => true
			));
		$inventoryItems = zbx_toHash($inventoryItems, 'inventory_link');

		$data['hostInventoryItems'] = CMacrosResolverHelper::resolveItemNames($inventoryItems);
	}

	$data['groupid'] = $pageFilter->groupid;
	$data['newgroup'] = getRequest('newgroup');
	$data['clear_templates'] = getRequest('clear_templates');
	if (!isset($data['hostLinkedTemplates'])) {
		$data['hostLinkedTemplates'] = array();
	}

	$hostsWidget->setRootClass($rootClass);

	showResponse('configuration.host.edit', $data, $hostsWidget);
}

function showResponse($viewName, $data, CWidget $hostsWidget) {
	$view = new CView($viewName, $data);
	$hostsWidget->addItem($view->render());
	$hostsWidget->show();
}

function fetchHostFromRequest() {
	$host = array(
		'hostid' => getRequest('hostid'),
		'host' => getRequest('host'),
		'name' => getRequest('visiblename'),
		'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
		'description' => getRequest('description'),
		'proxy_hostid' => getRequest('proxy_hostid', 0),
		'ipmi_authtype' => getRequest('ipmi_authtype'),
		'ipmi_privilege' => getRequest('ipmi_privilege'),
		'ipmi_username' => getRequest('ipmi_username'),
		'ipmi_password' => getRequest('ipmi_password'),
		'groups' => getRequest('groups', array()),
		'templates' => getRequest('templates', array()),
		'interfaces' => getRequest('interfaces', array()),
		'macros' => getRequest('macros', array()),
		'inventory_mode' => getRequest('inventory_mode'),
		'inventory' => (getRequest('inventory_mode') == HOST_INVENTORY_DISABLED)
				? array()
				: getRequest('host_inventory', array()),
		'mainInterfaces' => getRequest('mainInterfaces', array())
	);

	return $host;
}

function fetchHostFromDb($hostId) {
	$dbHost = API::Host()->get(array(
			'hostids' => $hostId,
			'output' => array('host', 'name', 'status', 'description', 'proxy_hostid', 'ipmi_authtype',
								'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'inventory_mode', 'flags'),
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectMacros' => API_OUTPUT_EXTEND,
			'selectInventory' => API_OUTPUT_EXTEND,
			'selectParentTemplates' => array('templateid'),
			'selectGroups' => API_OUTPUT_EXTEND
		));
	$dbHost = reset($dbHost);

	$host = $dbHost;

	$host['templates'] = array_keys(zbx_toHash($dbHost['parentTemplates'], 'templateid'));
	unset($host['parentTemplates']);

	$host['inventory_mode'] = isset($dbHost['inventory']['inventory_mode'])
		? $dbHost['inventory']['inventory_mode']
		: HOST_INVENTORY_DISABLED;

	$host['groups'] = array_keys(zbx_toHash($dbHost['groups'], 'groupid'));

	return $host;
}

function doAddHost(array $host) {
	$msgOk = _('Host added');
	$msgFail = _('Cannot add host');

	try {
		DBstart();

		$hostCreateData = processHostFromRequest($host);

		$hostIds = API::Host()->create($hostCreateData);

		$hostId = reset($hostIds['hostids']);

		add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST, $hostId, $host['host'], null, null, null);

		$result = DBend(true);
	}
	catch (Exception $e) {
		$result = DBend(false);
	}

	show_messages($result, $msgOk, $msgFail);

	return $result;
}

function doUpdateHost(array $host) {
	$msgOk = _('Host updated');
	$msgFail = _('Cannot update host');

	try {
		DBstart();

		$hostId = $host['hostid'];

		$dbHost = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostId
			));
		$dbHost = reset($dbHost);

		if ($dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$hostUpdateData = array(
				'hostid' => $host['hostid'],
				'status' => $host['status'],
				'description' => $host['description'],
				'inventory' => $host['inventory']
			);
		}
		else {
			$hostUpdateData = processHostFromRequest($host);
			$hostUpdateData['templates_clear'] = zbx_toObject(getRequest('clear_templates', array()), 'templateid');

		}

		API::Host()->update($hostUpdateData);

		$updatedHost = API::Host()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostId,
				'editable' => true
			));
		$updatedHost = reset($updatedHost);

		add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $updatedHost['hostid'], $updatedHost['host'], 'hosts',
			$dbHost, $updatedHost);

		$result = DBend(true);
	}
	catch (Exception $e) {
		$result = DBend(false);
	}

	show_messages($result, $msgOk, $msgFail);

	return $result;
}

function doCloneHost(array $host) {
	$msgOk = _('Host added');
	$msgFail = _('Cannot add host');

	try {
		DBstart();

		$hostCreateData = processHostFromRequest($host);

		$hostIds = API::Host()->create($hostCreateData);

		if (!$hostIds) {
			throw new Exception();
		}

		$hostId = reset($hostIds['hostids']);

		add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST, $hostId, $host['host'], null, null, null);

		$result = DBend(true);
	}
	catch (Exception $e) {
		$result = DBend(false);
	}

	show_messages($result, $msgOk, $msgFail);

	return $result;
}

function doFullCloneHost(array $host) {

	$msgOk = _('Host added');
	$msgFail = _('Cannot add host');

	try {
		DBstart();

		$sourceHostId = $host['hostid'];

		$hostCreateData = processHostFromRequest($host);

		$hostIds = API::Host()->create($hostCreateData);

		$hostId = reset($hostIds['hostids']);

		// copy applications
		copyApplications($sourceHostId, $hostId);

		// copy items
		copyItems($sourceHostId, $hostId);

		// copy web scenarios
		copyHttpTests($sourceHostId, $hostId);

		// copy triggers
		$dbTriggers = API::Trigger()->get(array(
				'output' => array('triggerid'),
				'hostids' => $sourceHostId,
				'inherited' => false
			));
		if ($dbTriggers) {
			if (!copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'), $hostId, $sourceHostId)) {
				throw new Exception();
			}
		}

		// copy discovery rules
		$dbDiscoveryRules = API::DiscoveryRule()->get(array(
				'output' => array('itemid'),
				'hostids' => $sourceHostId,
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
				'hostids' => $sourceHostId,
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

		add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_HOST, $hostId, $host['host'], null, null, null);

		$result = DBend(true);
	}
	catch (Exception $e) {
		$result = DBend(false);
	}

	show_messages($result, $msgOk, $msgFail);

	return $result;
}

function processHostFromRequest(array $hostFromRequest) {
	$interfaces = $hostFromRequest['interfaces'];
	foreach ($interfaces as $key => &$interface) {
		if (!$interface['ip'] && !$interface['dns']) {
			unset($interfaces[$key]);
			continue;
		}

		if ($interface['type'] == INTERFACE_TYPE_SNMP && !isset($interface['bulk'])) {
			$interface['bulk'] = SNMP_BULK_DISABLED;
		}
		else {
			$interface['bulk'] = SNMP_BULK_ENABLED;
		}

		if ($interface['isNew']) {
			unset($interfaces[$key]['interfaceid']);
		}

		unset($interfaces[$key]['isNew']);
		$interface['main'] = 0;
	}
	unset($interface);

	$interfaceTypes = array(INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI);
	$mainInterfaces = $hostFromRequest['mainInterfaces'];
	foreach ($interfaceTypes as $interfaceType) {
		if (isset($mainInterfaces[$interfaceType])) {
			$interfaces[$mainInterfaces[$interfaceType]]['main'] = INTERFACE_PRIMARY;
		}
	}

	$macros = $hostFromRequest['macros'];
	foreach ($macros as $key => &$macro) {
		if (!isset($macro['hostmacroid']) && !$macro['macro'] && !$macro['value']) {
			unset($macros[$key]);
		}
		else {
			$macro['macro'] = mb_strtoupper($macro['macro']);
		}
	}
	unset($macro);

	$groups = $hostFromRequest['groups'];

	$newGroup = getRequest('newgroup');
	if ($newGroup) {
		$newGroup = API::HostGroup()->create(array('name' => $newGroup));

		$groups[] = reset($newGroup['groupids']);
	}

	// host data
	$processedHost = array(
		'hostid' => $hostFromRequest['hostid'],
		'host' => $hostFromRequest['host'],
		'name' => $hostFromRequest['name'] ? $hostFromRequest['name'] : $hostFromRequest['host'],
		'status' => $hostFromRequest['status'],
		'description' => $hostFromRequest['description'],
		'proxy_hostid' => $hostFromRequest['proxy_hostid'],
		'ipmi_authtype' => $hostFromRequest['ipmi_authtype'],
		'ipmi_privilege' => $hostFromRequest['ipmi_privilege'],
		'ipmi_username' => $hostFromRequest['ipmi_username'],
		'ipmi_password' => $hostFromRequest['ipmi_password'],
		'groups' => zbx_toObject($groups, 'groupid'),
		'templates' => zbx_toObject($hostFromRequest['templates'], 'templateid'),
		'interfaces' => $interfaces,
		'macros' => $macros,
		'inventory_mode' => $hostFromRequest['inventory_mode'],
		'inventory' => $hostFromRequest['inventory']
	);

	return $processedHost;
}

function doDeleteHost($hostId) {
	DBstart();
	$result = API::Host()->delete(array($hostId));
	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}

	show_messages($result, _('Host deleted'), _('Cannot delete host'));
}

function doLinkTemplatesToHost(array $host, array $templateIds) {
	$host['templates'] = array_merge($host['templates'], $templateIds);

	return $host;
}

function doUnlinkTemplatesFromHost(array $host, array $templateIds) {
	foreach ($templateIds as $templateId) {
		unset($host['templates'][array_search($templateId, $host['templates'])]);
	}

	return $host;
}

function doUnlinkAndClearTemplatesFromHost(array $host, array $templateIdsToUnlinkAndClear) {
	$host['clear_templates'] = $templateIdsToUnlinkAndClear;

	$frmForm = new CForm();
	$frmForm->cleanItems();
	$frmForm->addItem(new CDiv(array(
		new CSubmit('form', _('Create host')),
		new CButton('form', _('Import'), 'redirect("conf.import.php?rules_preset=host")')
	)));
	$frmForm->addItem(new CVar('groupid', $_REQUEST['groupid'], 'filter_groupid_id'));

	$hostsWidget->addPageHeader(_('CONFIGURATION OF HOSTS'), $frmForm);

	$frmGroup = new CForm('get');
	$frmGroup->addItem(array(_('Group').SPACE, $pageFilter->getGroupsCB()));

	$hostsWidget->addHeader(_('Hosts'), $frmGroup);
	$hostsWidget->addHeaderRowNumber();
	$hostsWidget->setRootClass('host-list');

	// filter
	$filterTable = new CTable('', 'filter filter-center');
	$filterTable->addRow(array(
		array(array(bold(_('Name')), ' '._('like').' '), new CTextBox('filter_host', $filter['host'], 20)),
		array(array(bold(_('DNS')), ' '._('like').' '), new CTextBox('filter_dns', $filter['dns'], 20)),
		array(array(bold(_('IP')), ' '._('like').' '), new CTextBox('filter_ip', $filter['ip'], 20)),
		array(bold(_('Port')), ' ', new CTextBox('filter_port', $filter['port'], 20))
	));

	$filterButton = new CSubmit('filter_set', _('Filter'), 'chkbxRange.clearSelectedOnFilterChange();');
	$filterButton->useJQueryStyle('main');

	$resetButton = new CSubmit('filter_rst', _('Reset'), 'chkbxRange.clearSelectedOnFilterChange();');
	$resetButton->useJQueryStyle();

	$divButtons = new CDiv(array($filterButton, SPACE, $resetButton));
	$divButtons->setAttribute('style', 'padding: 4px 0;');

	$filterTable->addRow(new CCol($divButtons, 'controls', 4));

	$filterForm = new CForm('get');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');
	$filterForm->addItem($filterTable);

	$hostsWidget->addFlicker($filterForm, CProfile::get('web.hosts.filter.state', 0));

	// table hosts
	$form = new CForm();
	$form->setName('hosts');

	$table = new CTableInfo(_('No hosts found.'));
	$table->setHeader(array(
		new CCheckBox('all_hosts', null, "checkAll('".$form->getName()."', 'all_hosts', 'hosts');"),
		make_sorting_header(_('Name'), 'name', $sortField, $sortOrder),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Discovery'),
		_('Web'),
		_('Interface'),
		_('Templates'),
		make_sorting_header(_('Status'), 'status', $sortField, $sortOrder),
		_('Availability')
	));

	foreach ($host['clear_templates'] as $templateId) {
		unset($host['templates'][array_search($templateId, $host['templates'])]);
	}

	return $host;
}
