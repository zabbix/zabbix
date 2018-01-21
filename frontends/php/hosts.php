<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/include/hostgroups.inc.php';
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
	$page['scripts'] = ['multiselect.js'];

	$exportData = false;
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hosts' =>					[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null],
	'groups' =>					[T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
	'new_groups' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'remove_groups' =>			[T_ZBX_STR, O_OPT, P_SYS,			DB_ID,		null],
	'hostids' =>				[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null],
	'groupids' =>				[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null],
	'applications' =>			[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null],
	'groupid' =>				[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		null],
	'hostid' =>					[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,		'isset({form}) && {form} == "update"'],
	'clone_hostid' =>			[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,
									'isset({form}) && {form} == "full_clone"'
								],
	'host' =>					[T_ZBX_STR, O_OPT, null,			NOT_EMPTY,	'isset({add}) || isset({update})',
									_('Host name')
								],
	'visiblename' =>			[T_ZBX_STR, O_OPT, null,			null,		'isset({add}) || isset({update})'],
	'description' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'proxy_hostid' =>			[T_ZBX_INT, O_OPT, P_SYS,		    DB_ID,		null],
	'status' =>					[T_ZBX_INT, O_OPT, null,
									IN([HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]), null
								],
	'interfaces' =>				[T_ZBX_STR, O_OPT, null,			NOT_EMPTY,
									'isset({add}) || isset({update})', _('Agent or SNMP or JMX or IPMI interface')
								],
	'mainInterfaces' =>			[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
	'templates' =>				[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
	'add_template' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'add_templates' =>			[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
	'templates_rem' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'clear_templates' =>		[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
	'ipmi_authtype' =>			[T_ZBX_INT, O_OPT, null,			BETWEEN(-1, 6), null],
	'ipmi_privilege' =>			[T_ZBX_INT, O_OPT, null,			BETWEEN(0, 5), null],
	'ipmi_username' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'ipmi_password' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'tls_connect' =>			[T_ZBX_INT, O_OPT, null,
									IN([HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]),
									null
								],
	'tls_accept' =>				[T_ZBX_INT, O_OPT, null,
									BETWEEN(0,
										(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)
									),
									null
								],
	'tls_subject' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'tls_issuer' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
	'tls_psk_identity' =>		[T_ZBX_STR, O_OPT, null,			null,		null],
	'tls_psk' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
	'flags' =>					[T_ZBX_INT, O_OPT, null,
									IN([ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]), null
								],
	'mass_replace_tpls' =>		[T_ZBX_STR, O_OPT, null,			null,		null],
	'mass_clear_tpls' =>		[T_ZBX_STR, O_OPT, null,			null,		null],
	'inventory_mode' =>			[T_ZBX_INT, O_OPT, null,
									IN(HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC),
									null
								],
	'host_inventory' =>			[T_ZBX_STR, O_OPT, P_UNSET_EMPTY,	null,		null],
	'macros' =>					[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'visible' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
	'show_inherited_macros' =>	[T_ZBX_INT, O_OPT, null, IN([0,1]), null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
									IN('"host.export","host.massdelete","host.massdisable",'.
										'"host.massenable","host.massupdate","host.massupdateform"'
									),
									null
								],
	'add_to_group' =>			[T_ZBX_INT, O_OPT, P_SYS|P_ACT,		DB_ID,		null],
	'delete_from_group' =>		[T_ZBX_INT, O_OPT, P_SYS|P_ACT,		DB_ID,		null],
	'unlink' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'unlink_and_clear' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'masssave' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'clone' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'full_clone' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT,		null,		null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,			null,		null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,			null,		null],
	'filter_host' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'filter_ip' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
	'filter_dns' =>				[T_ZBX_STR, O_OPT, null,			null,		null],
	'filter_port' =>			[T_ZBX_STR, O_OPT, null,			null,		null],
	'filter_monitored_by' =>	[T_ZBX_INT, O_OPT, null,
									IN([ZBX_MONITORED_BY_ALL, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_SERVER]),
									null
								],
	'filter_proxyids' =>		[T_ZBX_INT, O_OPT, null,			DB_ID,		null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"name","status"'),						null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !isWritableHostGroups([getRequest('groupid')])) {
	access_deny();
}
if (getRequest('hostid')) {
	$hosts = API::Host()->get([
		'output' => [],
		'hostids' => getRequest('hostid'),
		'editable' => true
	]);

	if (!$hosts) {
		access_deny();
	}
}

$hostIds = getRequest('hosts', []);

/*
 * Export
 */
if ($exportData) {
	$export = new CConfigurationExport(['hosts' => $hostIds]);
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
	CProfile::update('web.hosts.filter.monitored_by', getRequest('filter_monitored_by', ZBX_MONITORED_BY_ALL),
		PROFILE_TYPE_INT
	);
	CProfile::updateArray('web.hosts.filter.proxyids', getRequest('filter_proxyids', []), PROFILE_TYPE_ID);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.hosts.filter_ip');
	CProfile::delete('web.hosts.filter_dns');
	CProfile::delete('web.hosts.filter_host');
	CProfile::delete('web.hosts.filter_port');
	CProfile::delete('web.hosts.filter.monitored_by');
	CProfile::deleteIdx('web.hosts.filter.proxyids');
	DBend();
}

$filter['ip'] = CProfile::get('web.hosts.filter_ip', '');
$filter['dns'] = CProfile::get('web.hosts.filter_dns', '');
$filter['host'] = CProfile::get('web.hosts.filter_host', '');
$filter['port'] = CProfile::get('web.hosts.filter_port', '');
$filter['monitored_by'] = CProfile::get('web.hosts.filter.monitored_by', ZBX_MONITORED_BY_ALL);
$filter['proxyids'] = CProfile::getArray('web.hosts.filter.proxyids', []);

// remove inherited macros data (actions: 'add', 'update' and 'form')
$macros = cleanInheritedMacros(getRequest('macros', []));

// remove empty new macro lines
foreach ($macros as $idx => $macro) {
	if (!array_key_exists('hostmacroid', $macro) && $macro['macro'] === '' && $macro['value'] === '') {
		unset($macros[$idx]);
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['add_template']) && isset($_REQUEST['add_templates'])) {
	$_REQUEST['templates'] = getRequest('templates', []);
	$_REQUEST['templates'] = array_merge($_REQUEST['templates'], $_REQUEST['add_templates']);
}
if (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear'])) {
	$_REQUEST['clear_templates'] = getRequest('clear_templates', []);

	$unlinkTemplates = [];

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
elseif ((hasRequest('clone') || hasRequest('full_clone')) && hasRequest('hostid')) {
	$_REQUEST['form'] = hasRequest('clone') ? 'clone' : 'full_clone';

	$groups = getRequest('groups', []);
	$groupids = [];

	// Remove inaccessible groups from request, but leave "new".
	if ($groups) {
		foreach ($groups as $group) {
			if (!is_array($group)) {
				$groupids[] = $group;
			}
		}

		$groups_allowed = API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($groups as $idx => $group) {
			if (!is_array($group) && !array_key_exists($group, $groups_allowed)) {
				unset($groups[$idx]);
			}
		}

		$_REQUEST['groups'] = $groups;
	}

	if (hasRequest('interfaces')) {
		$interfaceid = 1;
		foreach ($_REQUEST['interfaces'] as &$interface) {
			$interface['interfaceid'] = (string) $interfaceid++;
			unset($interface['locked'], $interface['items']);
		}
		unset($interface);
	}

	if (hasRequest('full_clone')) {
		$_REQUEST['clone_hostid'] = $_REQUEST['hostid'];
	}

	unset($_REQUEST['hostid'], $_REQUEST['flags']);
}
elseif (hasRequest('action') && getRequest('action') == 'host.massupdate' && hasRequest('masssave')) {
	$hostIds = getRequest('hosts', []);
	$visible = getRequest('visible', []);
	$_REQUEST['proxy_hostid'] = getRequest('proxy_hostid', 0);
	$_REQUEST['templates'] = getRequest('templates', []);

	try {
		DBstart();

		// filter only normal and discovery created hosts
		$options = [
			'output' => ['hostid'],
			'hostids' => $hostIds,
			'selectInventory' => ['inventory_mode'],
			'selectGroups' => ['groupid'],
			'filter' => ['flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]]
		];

		if (array_key_exists('templates', $visible) && !hasRequest('mass_replace_tpls')) {
			$options['selectParentTemplates'] = ['templateid'];
		}

		$hosts = API::Host()->get($options);

		$properties = [
			'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'description'
		];

		$newValues = [];
		foreach ($properties as $property) {
			if (isset($visible[$property])) {
				$newValues[$property] = $_REQUEST[$property];
			}
		}

		if (isset($visible['status'])) {
			$newValues['status'] = getRequest('status', HOST_STATUS_NOT_MONITORED);
		}

		if (array_key_exists('encryption', $visible)) {
			$newValues['tls_connect'] = getRequest('tls_connect', HOST_ENCRYPTION_NONE);
			$newValues['tls_accept'] = getRequest('tls_accept', HOST_ENCRYPTION_NONE);

			if ($newValues['tls_connect'] == HOST_ENCRYPTION_PSK || ($newValues['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				$newValues['tls_psk_identity'] = getRequest('tls_psk_identity', '');
				$newValues['tls_psk'] = getRequest('tls_psk', '');
			}

			if ($newValues['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE
					|| ($newValues['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				$newValues['tls_issuer'] = getRequest('tls_issuer', '');
				$newValues['tls_subject'] = getRequest('tls_subject', '');
			}
		}

		$templateids = [];
		if (isset($visible['templates'])) {
			$templateids = $_REQUEST['templates'];
		}

		/*
		 * Step 2. Add new host groups. This is actually done later, but before we can do that we need to check what
		 * groups will be added and first of all actually create them and get the new IDs.
		 */
		$newHostGroupIds = [];
		if (isset($visible['new_groups']) && !empty($_REQUEST['new_groups'])) {
			if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				foreach ($_REQUEST['new_groups'] as $newGroup) {
					if (is_array($newGroup) && isset($newGroup['new'])) {
						$newGroups[] = ['name' => $newGroup['new']];
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

		// Step 1. Replace existing groups.
		if (isset($visible['groups'])) {
			if (isset($_REQUEST['groups'])) {
				// First (step 1.) we try to replace existing groups and add new groups in the process (step 2.).
				$replaceHostGroupsIds = $newHostGroupIds
					? array_unique(array_merge(getRequest('groups'), $newHostGroupIds))
					: $_REQUEST['groups'];
			}
			elseif ($newHostGroupIds) {
				/*
				 * If no groups need to be replaced, use same variable as if new groups are added. This is used in
				 * step 3. The only difference is that we try to remove all existing groups by replacing with nothing
				 * since we left it empty.
				 */
				$replaceHostGroupsIds = $newHostGroupIds;
			}

			if (isset($replaceHostGroupsIds)) {
				$newValues['groups'] = API::HostGroup()->get([
					'groupids' => $replaceHostGroupsIds,
					'editable' => true,
					'output' => ['groupid']
				]);
			}
			else {
				$newValues['groups'] = [];
			}
		}

		if (isset($_REQUEST['mass_replace_tpls'])) {
			if (isset($_REQUEST['mass_clear_tpls'])) {
				$hostTemplates = API::Template()->get([
					'output' => ['templateid'],
					'hostids' => $hostIds
				]);

				$hostTemplateIds = zbx_objectValues($hostTemplates, 'templateid');
				$templatesToDelete = array_diff($hostTemplateIds, $templateids);

				$newValues['templates_clear'] = zbx_toObject($templatesToDelete, 'templateid');
			}

			$newValues['templates'] = $templateids;
		}

		$host_inventory = array_intersect_key(getRequest('host_inventory', []), $visible);

		if (hasRequest('inventory_mode') && array_key_exists('inventory_mode', $visible)) {
			$newValues['inventory_mode'] = getRequest('inventory_mode', HOST_INVENTORY_DISABLED);

			if ($newValues['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$host_inventory = [];
			}
		}

		foreach ($hosts as &$host) {
			if (array_key_exists('inventory_mode', $newValues)) {
				$host['inventory'] = $host_inventory;
			}
			elseif (array_key_exists('inventory_mode', $host['inventory'])
					&& $host['inventory']['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				$host['inventory'] = $host_inventory;
			}
			else {
				$host['inventory'] = [];
			}

			/*
			 * Step 3. Case when groups need to be removed. This is done inside the loop, since each host may have
			 * different existing groups. So we need to know what can we remove.
			 */
			$remove_groups = getRequest('remove_groups', []);

			if (array_key_exists('remove_groups', $visible) && $remove_groups) {
				if (isset($replaceHostGroupsIds)) {
					/*
						* Previously we determined what groups fro ALL hosts will be replaced.
						* The $replaceHostGroupsIds holds both - groups to replace and new groups to add.
						* But $replace_host_groupids is the difference between the replaceable groups and removable
						* groups.
						*/
					$replace_host_groupids = array_diff($replaceHostGroupsIds, $remove_groups);
				}
				else {
					/*
						* The $newHostGroupIds holds only groups that need to be added. So $replace_host_groupsids is
						* the difference between the groups that already exist + groups that need to be added and
						* removable groups.
						*/
					$current_groupids = zbx_objectValues($host['groups'], 'groupid');

					$replace_host_groupids = $newHostGroupIds
						? array_diff(array_unique(array_merge($current_groupids, $newHostGroupIds)), $remove_groups)
						: array_diff($current_groupids, $remove_groups);
				}

				$newValues['groups'] = API::HostGroup()->get([
					'groupids' => $replace_host_groupids,
					'editable' => true,
					'output' => ['groupid']
				]);
			}

			// Case when we only need to add new groups to host.
			if ($newHostGroupIds && !array_key_exists('groups', $visible)
					&& !array_key_exists('remove_groups', $visible)) {
				$add_groups = [];

				foreach ($newHostGroupIds as $groupid) {
					$add_groups[] = ['groupid' => $groupid];
				}

				$host['groups'] = array_merge($host['groups'], $add_groups);
			}
			else {
				// In all other cases we first clear out the old values. And simply replace with $newValues later.
				unset($host['groups']);
			}

			if ($templateids && array_key_exists('parentTemplates', $host)) {
				$host['templates'] = array_unique(
					array_merge($templateids, zbx_objectValues($host['parentTemplates'], 'templateid'))
				);
			}

			unset($host['parentTemplates']);

			$host = array_merge($host, $newValues);
		}
		unset($host);

		$result = (bool) API::Host()->update($hosts);

		if ($result === false) {
			throw new Exception();
		}

		DBend(true);

		uncheckTableRows();
		show_message(_('Hosts updated'));

		unset($_REQUEST['masssave'], $_REQUEST['form'], $_REQUEST['hosts']);
	}
	catch (Exception $e) {
		DBend(false);
		show_error_message(_('Cannot update hosts'));
	}
}
elseif (hasRequest('add') || hasRequest('update')) {
	try {
		DBstart();

		$hostId = getRequest('hostid', 0);

		if ($hostId != 0) {
			$create = false;

			$msgOk = _('Host updated');
			$msgFail = _('Cannot update host');

			$options = [
				'output' => ['flags'],
				'hostids' => $hostId,
				'editable' => true
			];

			if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
				$options['selectGroups'] = ['groupid'];
			}

			$dbHost = API::Host()->get($options);
			$dbHost = reset($dbHost);
		}
		else {
			$create = true;

			$msgOk = _('Host added');
			$msgFail = _('Cannot add host');
		}

		// host data
		if (!$create && $dbHost['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$host = [
				'hostid' => $hostId,
				'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
				'description' => getRequest('description', ''),
				'inventory' => (getRequest('inventory_mode') == HOST_INVENTORY_DISABLED)
					? []
					: getRequest('host_inventory', [])
			];
		}
		else {
			// templates
			$templates = [];

			foreach (getRequest('templates', []) as $templateId) {
				$templates[] = ['templateid' => $templateId];
			}

			// interfaces
			$interfaces = getRequest('interfaces', []);

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

			$mainInterfaces = getRequest('mainInterfaces', []);
			foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $type) {
				if (array_key_exists($type, $mainInterfaces)) {
					$interfaces[$mainInterfaces[$type]]['main'] = INTERFACE_PRIMARY;
				}
			}

			// Add new group.
			$groups = getRequest('groups', []);
			$new_groups = [];

			foreach ($groups as $idx => $group) {
				if (is_array($group) && array_key_exists('new', $group)) {
					$new_groups[] = ['name' => $group['new']];
					unset($groups[$idx]);
				}
			}

			if ($new_groups) {
				$new_groupid = API::HostGroup()->create($new_groups);

				if (!$new_groupid) {
					throw new Exception();
				}

				$groups = array_merge($groups, $new_groupid['groupids']);
			}

			// Host data.
			$host = [
				'host' => getRequest('host'),
				'name' => getRequest('visiblename'),
				'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),
				'description' => getRequest('description'),
				'proxy_hostid' => getRequest('proxy_hostid', 0),
				'ipmi_authtype' => getRequest('ipmi_authtype'),
				'ipmi_privilege' => getRequest('ipmi_privilege'),
				'ipmi_username' => getRequest('ipmi_username'),
				'ipmi_password' => getRequest('ipmi_password'),
				'tls_connect' => getRequest('tls_connect', HOST_ENCRYPTION_NONE),
				'tls_accept' => getRequest('tls_accept', HOST_ENCRYPTION_NONE),
				'groups' => $groups,
				'templates' => $templates,
				'interfaces' => $interfaces,
				'macros' => $macros,
				'inventory_mode' => getRequest('inventory_mode'),
				'inventory' => (getRequest('inventory_mode') == HOST_INVENTORY_DISABLED)
					? []
					: getRequest('host_inventory', [])
			];

			if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || ($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				$host['tls_psk_identity'] = getRequest('tls_psk_identity', '');
				$host['tls_psk'] = getRequest('tls_psk', '');
			}

			if ($host['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE
					|| ($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				$host['tls_issuer'] = getRequest('tls_issuer', '');
				$host['tls_subject'] = getRequest('tls_subject', '');
			}

			if (!$create) {
				$host['templates_clear'] = zbx_toObject(getRequest('clear_templates', []), 'templateid');
			}
		}

		if ($create) {
			$host['groups'] = zbx_toObject($groups, 'groupid');

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

			/*
			 * For non-super admins who may not have permissions to some groups, merge inaccessible groups together with
			 * submitted ones, so that there are no API errors on submit, in case the inaccessible group has been removed.
			 */
			if (CWebUser::$data['type'] != USER_TYPE_SUPER_ADMIN) {
				$groups_allowed = API::HostGroup()->get([
					'output' => [],
					'editable' => true,
					'preservekeys' => true
				]);

				foreach ($dbHost['groups'] as $group) {
					if (!array_key_exists($group['groupid'], $groups_allowed)) {
						$groups[] = $group['groupid'];
					}
				}
			}

			$host['groups'] = zbx_toObject($groups, 'groupid');

			if (!API::Host()->update($host)) {
				throw new Exception();
			}

			$dbHostNew = API::Host()->get([
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostId,
				'editable' => true
			]);
			$dbHostNew = reset($dbHostNew);

			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $dbHostNew['hostid'], $dbHostNew['host'], 'hosts',
				$dbHost, $dbHostNew);
		}

		// full clone
		if (getRequest('form', '') === 'full_clone' && getRequest('clone_hostid', 0) != 0) {
			$srcHostId = getRequest('clone_hostid');

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
			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'hostids' => $srcHostId,
				'inherited' => false,
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
			]);

			if ($dbTriggers) {
				if (!copyTriggersToHosts(zbx_objectValues($dbTriggers, 'triggerid'), $hostId, $srcHostId)) {
					throw new Exception();
				}
			}

			// copy discovery rules
			$dbDiscoveryRules = API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'hostids' => $srcHostId,
				'inherited' => false
			]);

			if ($dbDiscoveryRules) {
				$copyDiscoveryRules = API::DiscoveryRule()->copy([
					'discoveryids' => zbx_objectValues($dbDiscoveryRules, 'itemid'),
					'hostids' => [$hostId]
				]);

				if (!$copyDiscoveryRules) {
					throw new Exception();
				}
			}

			// copy graphs
			$dbGraphs = API::Graph()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => ['hostid'],
				'selectItems' => ['type'],
				'hostids' => $srcHostId,
				'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
				'inherited' => false
			]);

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

	$result = API::Host()->delete([getRequest('hostid')]);
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
elseif (hasRequest('action') && str_in_array(getRequest('action'), ['host.massenable', 'host.massdisable']) && hasRequest('hosts')) {
	$enable =(getRequest('action') == 'host.massenable');
	$status = $enable ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;

	$actHosts = API::Host()->get([
		'hostids' => getRequest('hosts'),
		'editable' => true,
		'templated_hosts' => true,
		'output' => ['hostid']
	]);
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
$pageFilter = new CPageFilter([
	'groups' => [
		'real_hosts' => true,
		'editable' => true
	],
	'groupid' => getRequest('groupid')
]);

$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = getRequest('hostid', 0);

$config = select_config();

if ((getRequest('action') === 'host.massupdateform' || hasRequest('masssave')) && hasRequest('hosts')) {
	$data = [
		'hosts' => getRequest('hosts'),
		'visible' => getRequest('visible', []),
		'mass_replace_tpls' => getRequest('mass_replace_tpls'),
		'mass_clear_tpls' => getRequest('mass_clear_tpls'),
		'groups' => getRequest('groups', []),
		'status' => getRequest('status', HOST_STATUS_MONITORED),
		'description' => getRequest('description'),
		'proxy_hostid' => getRequest('proxy_hostid', ''),
		'ipmi_authtype' => getRequest('ipmi_authtype', IPMI_AUTHTYPE_DEFAULT),
		'ipmi_privilege' => getRequest('ipmi_privilege', IPMI_PRIVILEGE_USER),
		'ipmi_username' => getRequest('ipmi_username', ''),
		'ipmi_password' => getRequest('ipmi_password', ''),
		'inventory_mode' => getRequest('inventory_mode', HOST_INVENTORY_DISABLED),
		'host_inventory' => getRequest('host_inventory', []),
		'templates' => getRequest('templates', []),
		'inventories' => zbx_toHash(getHostInventories(), 'db_field'),
		'tls_connect' => getRequest('tls_connect', HOST_ENCRYPTION_NONE),
		'tls_accept' => getRequest('tls_accept', HOST_ENCRYPTION_NONE),
		'tls_issuer' => getRequest('tls_issuer', ''),
		'tls_subject' => getRequest('tls_subject', ''),
		'tls_psk_identity' => getRequest('tls_psk_identity', ''),
		'tls_psk' => getRequest('tls_psk', '')
	];

	// sort templates
	natsort($data['templates']);

	// get groups
	$data['all_groups'] = API::HostGroup()->get([
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	]);
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
		$getLinkedTemplates = API::Template()->get([
			'templateids' => $data['templates'],
			'output' => ['templateid', 'name']
		]);

		foreach ($getLinkedTemplates as $getLinkedTemplate) {
			$data['linkedTemplates'][] = [
				'id' => $getLinkedTemplate['templateid'],
				'name' => $getLinkedTemplate['name']
			];
		}
	}

	$hostView = new CView('configuration.host.massupdate', $data);
}
elseif (hasRequest('form')) {
	$data = [
		// Common & auxiliary
		'form' => getRequest('form', ''),
		'hostid' => getRequest('hostid', 0),
		'clone_hostid' => getRequest('clone_hostid', 0),
		'flags' => getRequest('flags', ZBX_FLAG_DISCOVERY_NORMAL),

		// Host
		'host' => getRequest('host', ''),
		'visiblename' => getRequest('visiblename', ''),
		'interfaces' => getRequest('interfaces', []),
		'mainInterfaces' => getRequest('mainInterfaces', []),
		'description' => getRequest('description', ''),
		'proxy_hostid' => getRequest('proxy_hostid', 0),
		'status' => getRequest('status', HOST_STATUS_NOT_MONITORED),

		// Templates
		'templates' => getRequest('templates', []),
		'clear_templates' => getRequest('clear_templates', []),
		'original_templates' => [],
		'linked_templates' => [],

		// IPMI
		'ipmi_authtype' => getRequest('ipmi_authtype', IPMI_AUTHTYPE_DEFAULT),
		'ipmi_privilege' => getRequest('ipmi_privilege', IPMI_PRIVILEGE_USER),
		'ipmi_username' => getRequest('ipmi_username', ''),
		'ipmi_password' => getRequest('ipmi_password', ''),

		// Macros
		'macros' => $macros,
		'show_inherited_macros' => getRequest('show_inherited_macros', 0),

		// Host inventory
		'inventory_mode' => getRequest('inventory_mode', $config['default_inventory_mode']),
		'host_inventory' => getRequest('host_inventory', []),
		'inventory_items' => [],

		// Encryption
		'tls_connect' => getRequest('tls_connect', HOST_ENCRYPTION_NONE),
		'tls_accept' => getRequest('tls_accept', HOST_ENCRYPTION_NONE),
		'tls_issuer' => getRequest('tls_issuer', ''),
		'tls_subject' => getRequest('tls_subject', ''),
		'tls_psk_identity' => getRequest('tls_psk_identity', ''),
		'tls_psk' => getRequest('tls_psk', '')
	];

	$groups = [];

	if (!hasRequest('form_refresh')) {
		if ($data['hostid'] != 0) {
			$dbHosts = API::Host()->get([
				'output' => ['hostid', 'proxy_hostid', 'host', 'name', 'status', 'ipmi_authtype', 'ipmi_privilege',
					'ipmi_username', 'ipmi_password', 'flags', 'description', 'tls_connect', 'tls_accept', 'tls_issuer',
					'tls_subject', 'tls_psk_identity', 'tls_psk'
				],
				'selectGroups' => ['groupid'],
				'selectParentTemplates' => ['templateid'],
				'selectMacros' => ['hostmacroid', 'macro', 'value'],
				'selectDiscoveryRule' => ['itemid', 'name'],
				'selectInventory' => true,
				'hostids' => [$data['hostid']]
			]);
			$dbHost = reset($dbHosts);

			$data['flags'] = $dbHost['flags'];
			if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$data['discoveryRule'] = $dbHost['discoveryRule'];
			}

			// Host
			$data['host'] = $dbHost['host'];
			$data['visiblename'] = $dbHost['name'];
			$data['interfaces'] = API::HostInterface()->get([
				'output' => ['interfaceid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'bulk'],
				'selectItems' => ['type'],
				'hostids' => [$data['hostid']],
				'sortfield' => 'interfaceid'
			]);
			$data['description'] = $dbHost['description'];
			$data['proxy_hostid'] = $dbHost['proxy_hostid'];
			$data['status'] = $dbHost['status'];

			// Templates
			$data['templates'] = zbx_objectValues($dbHost['parentTemplates'], 'templateid');
			$data['original_templates'] = array_combine($data['templates'], $data['templates']);

			// IPMI
			$data['ipmi_authtype'] = $dbHost['ipmi_authtype'];
			$data['ipmi_privilege'] = $dbHost['ipmi_privilege'];
			$data['ipmi_username'] = $dbHost['ipmi_username'];
			$data['ipmi_password'] = $dbHost['ipmi_password'];

			// Macros
			$data['macros'] = $dbHost['macros'];

			// Interfaces
			foreach ($data['interfaces'] as &$interface) {
				if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
					$interface['locked'] = true;
				}
				else {
					// check if interface has items that require specific interface type, if so type cannot be changed
					$interface['locked'] = false;
					foreach ($interface['items'] as $item) {
						$type = itemTypeInterface($item['type']);
						if ($type !== false && $type != INTERFACE_TYPE_ANY) {
							$interface['locked'] = true;
							break;
						}
					}
				}

				$interface['items'] = (bool) $interface['items'];
			}
			unset($interface);

			// Host inventory
			$data['inventory_mode'] = array_key_exists('inventory_mode', $dbHost['inventory'])
				? $dbHost['inventory']['inventory_mode']
				: HOST_INVENTORY_DISABLED;
			$data['host_inventory'] = $dbHost['inventory'];
			unset($data['host_inventory']['inventory_mode']);

			// Encryption
			$data['tls_connect'] = $dbHost['tls_connect'];
			$data['tls_accept'] = $dbHost['tls_accept'];
			$data['tls_issuer'] = $dbHost['tls_issuer'];
			$data['tls_subject'] = $dbHost['tls_subject'];
			$data['tls_psk_identity'] = $dbHost['tls_psk_identity'];
			$data['tls_psk'] = $dbHost['tls_psk'];

			// display empty visible name if equal to host name
			if ($data['host'] === $data['visiblename']) {
				$data['visiblename'] = '';
			}

			$groups = zbx_objectValues($dbHost['groups'], 'groupid');
		}
		else {
			if (getRequest('groupid', 0) != 0) {
				$groups[] = getRequest('groupid');
			}

			$data['status'] = HOST_STATUS_MONITORED;
		}
	}
	else {
		if ($data['hostid'] != 0) {
			$dbHosts = API::Host()->get([
				'output' => ['flags'],
				'selectParentTemplates' => ['templateid'],
				'selectDiscoveryRule' => ['itemid', 'name'],
				'hostids' => [$data['hostid']]
			]);
			$dbHost = reset($dbHosts);

			$data['flags'] = $dbHost['flags'];
			if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$data['discoveryRule'] = $dbHost['discoveryRule'];
			}

			$templateids = zbx_objectValues($dbHost['parentTemplates'], 'templateid');
			$data['original_templates'] = array_combine($templateids, $templateids);
		}

		foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $type) {
			if (array_key_exists($type, $data['mainInterfaces'])) {
				$interfaceid = $data['mainInterfaces'][$type];
				$data['interfaces'][$interfaceid]['main'] = '1';
			}
		}
		$data['interfaces'] = array_values($data['interfaces']);

		$groups = getRequest('groups', []);
	}

	if ($data['hostid'] != 0) {
		// get items that populate host inventory fields
		$data['inventory_items'] = API::Item()->get([
			'output' => ['inventory_link', 'itemid', 'hostid', 'name', 'key_'],
			'hostids' => [$dbHost['hostid']],
			'filter' => ['inventory_link' => array_keys(getHostInventories())]
		]);
		$data['inventory_items'] = zbx_toHash($data['inventory_items'], 'inventory_link');
		$data['inventory_items'] = CMacrosResolverHelper::resolveItemNames($data['inventory_items']);
	}

	if ($data['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		if ($data['proxy_hostid'] != 0) {
			$data['proxies'] = API::Proxy()->get([
				'output' => ['host'],
				'proxyids' => [$data['proxy_hostid']],
				'preservekeys' => true
			]);
		}
		else {
			$data['proxies'] = [];
		}
	}
	else {
		$data['proxies'] = API::Proxy()->get([
			'output' => ['host'],
			'preservekeys' => true
		]);
		order_result($data['proxies'], 'host');
	}

	foreach ($data['proxies'] as &$proxy) {
		$proxy = $proxy['host'];
	}
	unset($proxy);

	if ($data['show_inherited_macros']) {
		$data['macros'] = mergeInheritedMacros($data['macros'], getInheritedMacros($data['templates']));
	}
	$data['macros'] = array_values(order_macros($data['macros'], 'macro'));

	if (!$data['macros'] && $data['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
		$macro = ['macro' => '', 'value' => ''];
		if ($data['show_inherited_macros']) {
			$macro['type'] = MACRO_TYPE_HOSTMACRO;
		}
		$data['macros'][] = $macro;
	}

	// Groups with RW permissions.
	$groups_allowed = API::HostGroup()->get([
		'output' => ['name'],
		'editable' => true,
		'preservekeys' => true
	]);

	$data['groups_ms'] = [];
	$n = 0;

	// Prepare data for multiselect. Remove inaccessible groups.
	foreach ($groups as $group) {
		if (is_array($group) && array_key_exists('new', $group)) {
			$data['groups_ms'][] = [
				'id' => $group['new'],
				'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		elseif (array_key_exists($group, $groups_allowed)) {
			$data['groups_ms'][] = [
				'id' => $group,
				'name' => $groups_allowed[$group]['name']
			];
		}
		else {
			$postfix = (++$n > 1) ? ' ('.$n.')' : '';
			$data['groups_ms'][] = [
				'id' => $group,
				'name' => _('Inaccessible group').$postfix,
				'inaccessible' => true
			];
		}
	}

	if ($data['templates']) {
		$data['linked_templates'] = API::Template()->get([
			'output' => ['templateid', 'name'],
			'templateids' => $data['templates']
		]);
		CArrayHelper::sort($data['linked_templates'], ['name']);

		$data['writable_templates'] = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => $data['templates'],
			'editable' => true,
			'preservekeys' => true
		]);
	}

	$hostView = new CView('configuration.host.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// get Hosts
	$hosts = [];
	if ($pageFilter->groupsSelected) {
		$hosts = API::Host()->get([
			'output' => ['hostid', $sortField],
			'groupids' => $pageFilter->groupids,
			'editable' => true,
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1,
			'search' => [
				'name' => ($filter['host'] === '') ? null : $filter['host'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			],
			'filter' => [
				'port' => ($filter['port'] === '') ? null : $filter['port'],
			],
			'proxyids' => ($filter['monitored_by'] == ZBX_MONITORED_BY_PROXY && $filter['proxyids'])
								? $filter['proxyids']
								: null
		]);
	}
	order_result($hosts, $sortField, $sortOrder);

	$url = (new CUrl('hosts.php'))
		->setArgument('groupid', $pageFilter->groupid);

	$pagingLine = getPagingLine($hosts, $sortOrder, $url);

	$hosts = API::Host()->get([
		'hostids' => zbx_objectValues($hosts, 'hostid'),
		'output' => API_OUTPUT_EXTEND,
		'selectParentTemplates' => ['hostid', 'name'],
		'selectInterfaces' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_COUNT,
		'selectDiscoveries' => API_OUTPUT_COUNT,
		'selectTriggers' => API_OUTPUT_COUNT,
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectApplications' => API_OUTPUT_COUNT,
		'selectHttpTests' => API_OUTPUT_COUNT,
		'selectDiscoveryRule' => ['itemid', 'name'],
		'selectHostDiscovery' => ['ts_delete']
	]);
	order_result($hosts, $sortField, $sortOrder);

	// selecting linked templates to templates linked to hosts
	$templateids = [];

	foreach ($hosts as $host) {
		$templateids = array_merge($templateids, zbx_objectValues($host['parentTemplates'], 'templateid'));
	}

	$templateids = array_keys(array_flip($templateids));

	$templates = API::Template()->get([
		'output' => ['templateid', 'name'],
		'templateids' => $templateids,
		'selectParentTemplates' => ['hostid', 'name'],
		'preservekeys' => true
	]);

	// selecting writable templates IDs
	$writable_templates = [];
	if ($templateids) {
		foreach ($templates as $template) {
			$templateids = array_merge($templateids, zbx_objectValues($template['parentTemplates'], 'templateid'));
		}

		$writable_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => array_keys(array_flip($templateids)),
			'editable' => true,
			'preservekeys' => true
		]);
	}

	// get proxy host IDs that that are not 0
	$proxyHostIds = [];
	foreach ($hosts as &$host) {
		// Sort interfaces to be listed starting with one selected as 'main'.
		CArrayHelper::sort($host['interfaces'], [
			['field' => 'main', 'order' => ZBX_SORT_DOWN]
		]);

		if ($host['proxy_hostid']) {
			$proxyHostIds[$host['proxy_hostid']] = $host['proxy_hostid'];
		}
	}
	unset($host);

	$proxies = [];
	if ($proxyHostIds) {
		$proxies = API::Proxy()->get([
			'proxyids' => $proxyHostIds,
			'output' => ['host'],
			'preservekeys' => true
		]);
	}

	// Prepare data for multiselect and remove unexisting proxies.
	$proxies_ms = [];
	if ($filter['proxyids']) {
		$filter_proxies = API::Proxy()->get([
			'output' => ['proxyid', 'host'],
			'proxyids' => $filter['proxyids'],
			'preservekeys' => true
		]);

		$proxies_ms = CArrayHelper::renameObjectsKeys($filter_proxies, ['proxyid' => 'id', 'host' => 'name']);
	}

	$data = [
		'pageFilter' => $pageFilter,
		'hosts' => $hosts,
		'paging' => $pagingLine,
		'filter' => $filter,
		'sortField' => $sortField,
		'sortOrder' => $sortOrder,
		'groupId' => $pageFilter->groupid,
		'config' => $config,
		'templates' => $templates,
		'writable_templates' => $writable_templates,
		'proxies' => $proxies,
		'proxies_ms' => $proxies_ms
	];

	$hostView = new CView('configuration.host.list', $data);
}

$hostView->render();
$hostView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
