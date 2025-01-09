<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/forms.inc.php';

/**
 * Configuration host edit controller for full-page form.
 */
class CControllerHostEdit extends CController {

	/**
	 * Edited host.
	 *
	 * @var ?array
	 */
	protected $host;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid'				=> 'db hosts.hostid',
			'groupids'				=> 'array_db hosts_groups.groupid',
			'clone'					=> 'in 1',
			'host'					=> 'db hosts.host',
			'visiblename'			=> 'db hosts.name',
			'description'			=> 'db hosts.description',
			'status'				=> 'db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED,
											HOST_STATUS_NOT_MONITORED
										]),
			'monitored_by'			=> 'db hosts.monitored_by|in '.implode(',', [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP]),
			'proxyid'				=> 'db hosts.proxyid',
			'proxy_groupid'			=> 'db hosts.proxy_groupid',
			'interfaces'			=> 'array',
			'mainInterfaces'		=> 'array',
			'groups'				=> 'array',
			'tags'					=> 'array',
			'templates'				=> 'array_db hosts.hostid',
			'add_templates'			=> 'array_db hosts.hostid',
			'ipmi_authtype'			=> 'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2,
											IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM,
											IPMI_AUTHTYPE_RMCP_PLUS
										]),
			'ipmi_privilege'		=> 'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER,
											IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
										]),
			'ipmi_username'			=> 'db hosts.ipmi_username',
			'ipmi_password'			=> 'db hosts.ipmi_password',
			'show_inherited_macros' => 'in 0,1',
			'tls_connect'			=> 'db hosts.tls_connect|in '.implode(',', [HOST_ENCRYPTION_NONE,
											HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE
										]),
			'tls_accept'			=> 'db hosts.tls_accept|ge 0|le '.
										(0 | HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_subject'			=> 'db hosts.tls_subject',
			'tls_issuer'			=> 'db hosts.tls_issuer',
			'tls_psk_identity'		=> 'db hosts.tls_psk_identity',
			'tls_psk'				=> 'db hosts.tls_psk',
			'inventory_mode'		=> 'db host_inventory.inventory_mode|in '.implode(',', [HOST_INVENTORY_DISABLED,
											HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
										]),
			'host_inventory'		=> 'array',
			'macros'				=> 'array',
			'valuemaps'				=> 'array'
		];

		$ret = ($this->validateInput($fields) && $this->checkCloneSourceHostId());

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * Check if source hostid is given to clone host.
	 *
	 * @return bool
	 */
	protected function checkCloneSourceHostId(): bool {
		if ($this->hasInput('clone')) {
			return $this->hasInput('hostid');
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('hostid')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('hostid'),
				'editable' => true,
				'limit' => 1
			]);

			if (!$hosts) {
				return false;
			}
		}

		return true;
	}

	protected function doAction(): void {
		$clone_hostid = null;

		if ($this->hasInput('hostid')) {
			if ($this->hasInput('clone')) {
				$clone_hostid = $this->getInput('hostid');
				$this->host = ['hostid' => null];
			}
			else {
				$hosts = API::Host()->get([
					'output' => ['hostid', 'host', 'name', 'monitored_by', 'proxyid', 'proxy_groupid',
						'assigned_proxyid', 'status', 'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
						'ipmi_password', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'flags',
						'inventory_mode'
					],
					'selectDiscoveryRule' => ['itemid', 'name', 'parent_hostid'],
					'selectHostGroups' => ['groupid'],
					'selectHostDiscovery' => ['parent_hostid', 'disable_source'],
					'selectInterfaces' => ['interfaceid', 'type', 'main', 'available', 'error', 'details', 'ip', 'dns',
						'port', 'useip'
					],
					'selectInventory' => array_column(getHostInventories(), 'db_field'),
					'selectMacros' => ['hostmacroid', 'macro', 'value', 'description', 'type', 'automatic'],
					'selectParentTemplates' => ['templateid', 'name', 'link_type'],
					'selectTags' => ['tag', 'value', 'automatic'],
					'selectValueMaps' => ['valuemapid', 'name', 'mappings'],
					'hostids' => $this->getInput('hostid')
				]);

				$this->host = $hosts[0];
				$this->host['groups'] = $this->host['hostgroups'];
				unset($this->host['hostgroups']);
			}
		}

		if (array_key_exists('interfaces', (array) $this->host) && $this->host['interfaces']) {
			$interface_items = API::HostInterface()->get([
				'output' => [],
				'selectItems' => API_OUTPUT_COUNT,
				'hostids' => [$this->host['hostid']],
				'preservekeys' => true
			]);

			foreach ($this->host['interfaces'] as &$interface) {
				if (!array_key_exists($interface['interfaceid'], $interface_items)) {
					continue;
				}

				$interface['items'] = $interface_items[$interface['interfaceid']]['items'];
			}
			unset($interface);
		}

		$this->host = (array) $this->host + $this->getInputValues() + $this->getHostDefaultValues();

		$data = [
			'form_action' => $this->host['hostid'] ? 'host.update' : 'host.create',
			'hostid' => $this->host['hostid'],
			'clone' => $this->hasInput('clone') ? 1 : null,
			'clone_hostid' => $clone_hostid,
			'host' => $this->host,
			'is_psk_edit' => $this->hasInput('tls_psk_identity') && $this->hasInput('tls_psk'),
			'show_inherited_macros' => $this->getInput('show_inherited_macros', 0),
			'warnings' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode(),
				'can_edit_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
				'can_edit_proxy_groups' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXY_GROUPS),
				'can_edit_proxies' => CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
			]
		];

		// Rename fields according names of host edit form.
		$data['host'] = CArrayHelper::renameKeys($data['host'], [
			'name' => 'visiblename'
		]);

		// Display empty visible name if equal to host name.
		if ($data['host']['host'] === $data['host']['visiblename']) {
			$data['host']['visiblename'] = '';
		}

		// Prepare tags for edit form.
		if (!$data['host']['tags']) {
			$data['host']['tags'][] = ['tag' => '', 'value' => '', 'automatic' => ZBX_TAG_MANUAL];
		}
		else {
			foreach ($data['host']['tags'] as &$tag) {
				$tag += ['automatic' => ZBX_TAG_MANUAL];
			}
			unset($tag);

			CArrayHelper::sort($data['host']['tags'],
				[['field' => 'automatic', 'order' => ZBX_SORT_DOWN], 'tag', 'value']
			);
		}

		$data['host']['macros'] = array_values(order_macros($data['host']['macros'], 'macro'));

		if (!$data['host']['macros']) {
			$data['host']['macros'][] = [
				'type' => ZBX_MACRO_TYPE_TEXT,
				'macro' => '',
				'value' => '',
				'description' => '',
				'automatic' => ZBX_USERMACRO_MANUAL
			];
		}

		foreach ($data['host']['macros'] as &$macro) {
			if (array_key_exists('automatic', $macro) && $macro['automatic'] == ZBX_USERMACRO_AUTOMATIC) {
				$macro['discovery_state'] = CControllerHostMacrosList::DISCOVERY_STATE_AUTOMATIC;

				$macro['original'] = [
					'value' => getMacroConfigValue($macro),
					'description' => $macro['description'],
					'type' => $macro['type']
				];
			}
			else {
				$macro['discovery_state'] = CControllerHostMacrosList::DISCOVERY_STATE_MANUAL;
			}

			unset($macro['automatic']);
		}
		unset($macro);

		// Reset Secret text macros and set warning for cloned host.
		if ($this->hasInput('clone')) {
			foreach ($data['host']['macros'] as &$macro) {
				if (array_key_exists('allow_revert', $macro) && array_key_exists('value', $macro)) {
					$macro['deny_revert'] = true;

					unset($macro['allow_revert']);
				}
			}
			unset($macro);
		}

		if ($data['host']['hostid'] === null) {
			$secret_macro_reset = false;

			foreach ($data['host']['macros'] as &$macro) {
				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET && !array_key_exists('value', $macro)) {
					$macro = [
						'type' => ZBX_MACRO_TYPE_TEXT,
						'value' => ''
					] + $macro;

					unset($macro['allow_revert']);

					$secret_macro_reset = true;
				}
			}
			unset($macro);

			if ($secret_macro_reset) {
				$data['warnings'][] = _('The cloned host contains user defined macros with type "Secret text". The value and type of these macros were reset.');
			}
		}

		foreach ($data['host']['macros'] as &$macro) {
			if ($macro['type'] == ZBX_MACRO_TYPE_SECRET
					&& !array_key_exists('deny_revert', $macro) && !array_key_exists('value', $macro)) {
				$macro['allow_revert'] = true;
			}
		}
		unset($macro);

		order_result($data['host']['valuemaps'], 'name');
		$data['host']['valuemaps'] = array_values($data['host']['valuemaps']);

		if ($this->hasInput('groupids')) {
			$data['groupids'] = $this->getInput('groupids', []);
		}

		// Extend data for view.
		$data['groups_ms'] = $this->hostGroupsForMultiselect($data['host']['groups'], $clone_hostid !== null);
		unset($data['groups']);

		if ($clone_hostid !== null && count($data['host']['groups']) != count($data['groups_ms'])) {
			$data['warnings'][] = _("The host being cloned belongs to a host group you don't have write permissions to. Non-writable group has been removed from the new host.");
		}

		CArrayHelper::sort($data['host']['parentTemplates'], ['name']);
		$this->extendLinkedTemplates($data['editable_templates']);
		$this->extendInventory($data['inventory_items'], $data['inventory_fields']);

		$data['ms_proxy'] = [];
		$data['ms_proxy_group'] = [];
		$data['host']['assigned_proxy_name'] = '';

		if ($data['host']['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
			$data['ms_proxy'] = CArrayHelper::renameObjectsKeys(API::Proxy()->get([
				'output' => ['proxyid', 'name'],
				'proxyids' => $data['host']['proxyid']
			]), ['proxyid' => 'id']);
		}
		elseif ($data['host']['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
			$data['ms_proxy_group'] = CArrayHelper::renameObjectsKeys(API::ProxyGroup()->get([
				'output' => ['proxy_groupid', 'name'],
				'proxy_groupids' => $data['host']['proxy_groupid']
			]), ['proxy_groupid' => 'id']);

			if ($data['host']['assigned_proxyid'] != 0) {
				$db_proxies = API::Proxy()->get([
					'output' => ['name'],
					'proxyids' => $data['host']['assigned_proxyid']
				]);

				$data['host']['assigned_proxy_name'] = $db_proxies[0]['name'];
			}
		}

		$data['is_discovery_rule_editable'] = $this->host['discoveryRule']
			&& API::DiscoveryRule()->get([
				'output' => [],
				'itemids' => $this->host['discoveryRule']['itemid'],
				'editable' => true
			]);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of host'));
		$this->setResponse($response);
	}

	/**
	 * Function to prepare data for host group multiselect.
	 *
	 * @param array $groups
	 * @param bool  $skip_non_editable  Whether to include non-editable host groups into response.
	 *
	 * @return array
	 */
	protected function hostGroupsForMultiselect(array $groups, $skip_non_editable = false): array {
		$groupids = [];
		foreach ($groups as $group) {
			if (array_key_exists('new', $group)) {
				continue;
			}

			$groupids[] = $group['groupid'];
		}

		// Select all accessible host groups.
		$groups_all = $groupids
			? API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => $groupids,
				'preservekeys' => true
			])
			: [];

		// Editable host groups.
		$groups_rw = ($groups_all && CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
			? API::HostGroup()->get([
				'output' => [],
				'groupids' => array_keys($groups_all),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		$groups_ms = [];
		foreach ($groups as $group) {
			if (array_key_exists('new', $group)) {
				$groups_ms[] = [
					'id' => $group['new'],
					'name' => $group['new'].' ('._x('new', 'new element in multiselect').')',
					'isNew' => true
				];
			}
			elseif (array_key_exists($group['groupid'], $groups_all)) {
				$is_editable = array_key_exists($group['groupid'], $groups_rw);

				if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && $skip_non_editable && !$is_editable) {
					continue;
				}

				$groups_ms[] = [
					'id' => $group['groupid'],
					'name' => $groups_all[$group['groupid']]['name'],
					'disabled' => CWebUser::getType() != USER_TYPE_SUPER_ADMIN && !$is_editable
				];
			}
		}

		CArrayHelper::sort($groups_ms, ['name']);

		return $groups_ms;
	}

	/**
	 * Function to prepare data for Linked templates list.
	 *
	 * @param array $editable_templates
	 */
	protected function extendLinkedTemplates(?array &$editable_templates): void {
		$editable_templates = $this->host['parentTemplates']
			? API::Template()->get([
				'output' => ['templateid'],
				'templateids' => array_column($this->host['parentTemplates'], 'templateid'),
				'editable' => true,
				'preservekeys' => true
			])
			: [];
	}

	/**
	 * Function to prepare data of inventory fields and find items selected to populate each of inventory fields.
	 *
	 * @param array $inventory_items
	 * @param array $inventory_fields
	 */
	protected function extendInventory(?array &$inventory_items, ?array &$inventory_fields): void {
		// Select inventory fields and extend each field with details of database schema.
		$db_fields = DB::getSchema('host_inventory');
		$inventory_fields = array_map(function ($field) use ($db_fields) {
			return $field += array_intersect_key($db_fields['fields'][$field['db_field']], [
				'type' => null,
				'length' => null
			]);
		}, getHostInventories());

		// Select inventory items.
		$inventory_items = $this->host['hostid']
			? API::Item()->get([
				'output' => ['inventory_link', 'itemid', 'name'],
				'hostids' => $this->host['hostid'],
				'filter' => [
					'inventory_link' => array_keys($inventory_fields)
				]
			])
			: [];

		$inventory_items = zbx_toHash($inventory_items, 'inventory_link');
	}

	/**
	 * Returns array with post input values.
	 *
	 * @return array
	 */
	protected function getInputValues(): array {
		$inputs = [];

		if ($this->hasInput('clone')) {
			$inputs['groups'] = [];
			foreach ($this->getInput('groups', []) as $group) {
				if (is_array($group) && array_key_exists('new', $group)) {
					$inputs['groups'][$group['new']] = $group;
				}
				else {
					$inputs['groups'][$group] = ['groupid' => $group];
				}
			}

			$inputs['name'] = $this->getInput('visiblename', '');
			$inputs['inventory'] = $this->getInput('host_inventory', []);

			$this->getInputs($inputs, ['host', 'monitored_by', 'proxyid', 'proxy_groupid', 'description', 'status',
				'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_connect', 'tls_accept',
				'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk', 'tags', 'inventory_mode', 'host_inventory'
			]);

			$field_add_templates = $this->getInput('add_templates', []);
			$field_templates = $this->getInput('templates', []);
			$linked_templates = API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_merge($field_add_templates, $field_templates),
				'preservekeys' => true
			]);

			// Remove inherited macros data.
			$macros = cleanInheritedMacros($this->getInput('macros', []));

			// Remove empty new macro lines.
			$macros = array_filter($macros, function ($macro) {
				$keys = array_flip(['hostmacroid', 'macro', 'value', 'description']);

				return (bool) array_filter(array_intersect_key($macro, $keys));
			});

			$inputs['macros'] = array_map(function ($macro) {
				unset($macro['hostmacroid']);

				return $macro + ['description' => ''];
			}, $macros);

			$inputs['valuemaps'] = array_map(function ($valuemap) {
				unset($valuemap['valuemapid']);

				return $valuemap;
			}, $this->getInput('valuemaps', []));

			$main_interfaces = $this->getInput('mainInterfaces', []);
			$inputs['interfaces'] = $this->getInput('interfaces', []);

			foreach ($inputs['interfaces'] as &$interface) {
				$interface['main'] = (in_array($interface['interfaceid'], $main_interfaces))
					? INTERFACE_PRIMARY
					: INTERFACE_SECONDARY;
				unset($interface['interfaceid'], $interface['items']);
			}
			unset($interface);

			$inputs['parentTemplates'] = array_intersect_key($linked_templates, array_flip($field_templates));

			// When cloning host, templates should be manually linked.
			foreach ($inputs['parentTemplates'] as &$template) {
				$template['link_type'] = TEMPLATE_LINK_MANUAL;
			}
			unset($template);

			$inputs['add_templates'] = array_map(function ($tmpl) {
				return CArrayHelper::renameKeys($tmpl, ['templateid' => 'id']);
			}, array_intersect_key($linked_templates, array_flip($field_add_templates)));
		}
		elseif (!$this->host) {
			// Prefill host groups when creating a new host.
			$inputs['groups'] = $this->hasInput('groupids')
				? zbx_toObject($this->getInput('groupids'), 'groupid')
				: [];
		}

		return $inputs;
	}

	/**
	 * Returns array containing default values of all host edit form fields.
	 *
	 * @return array
	 */
	protected function getHostDefaultValues(): array {
		return [
			'hostid' => null,
			'name' => '',
			'host' => '',
			'monitored_by' => ZBX_MONITORED_BY_SERVER,
			'proxyid' => '0',
			'proxy_groupid' => '0',
			'assigned_proxyid' => '0',
			'status' => HOST_STATUS_MONITORED,
			'ipmi_authtype' => IPMI_AUTHTYPE_DEFAULT,
			'ipmi_privilege' => IPMI_PRIVILEGE_USER,
			'ipmi_username' => '',
			'ipmi_password' => '',
			'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
			'description' => '',
			'tls_connect' => HOST_ENCRYPTION_NONE,
			'tls_accept' => HOST_ENCRYPTION_NONE,
			'tls_issuer' => '',
			'tls_subject' => '',
			'tls_psk_identity' => '',
			'tls_psk' => '',
			'tags' => [],
			'groups' => [],
			'parentTemplates' => [],
			'discoveryRule' => [],
			'interfaces' => [],
			'macros' => [],
			'inventory' => [],
			'valuemaps' => [],
			'inventory_mode' => CSettingsHelper::get(CSettingsHelper::DEFAULT_INVENTORY_MODE)
		];
	}
}
