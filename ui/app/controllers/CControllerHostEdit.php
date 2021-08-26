<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

	/**
	 * Clone hostid.
	 *
	 * @var ?string
	 */
	protected $clone_hostid;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid'			=> 'db hosts.hostid',
			'groupids'			=> 'array_db hosts_groups.groupid',
			'clone'				=> 'in 1',
			'full_clone'		=> 'in 1',
			'host'				=> 'db hosts.host',
			'visiblename'		=> 'db hosts.name',
			'description'		=> 'db hosts.description',
			'status'			=> 'db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED,
										HOST_STATUS_NOT_MONITORED
									]),
			'proxy_hostid'		=> 'db hosts.proxy_hostid',
			'interfaces'		=> 'array',
			'mainInterfaces'	=> 'array',
			'groups'			=> 'array',
			'tags'				=> 'array',
			'templates'			=> 'array_db hosts.hostid',
			'add_templates'		=> 'array_db hosts.hostid',
			'ipmi_authtype'		=> 'in '.implode(',', [IPMI_AUTHTYPE_DEFAULT, IPMI_AUTHTYPE_NONE, IPMI_AUTHTYPE_MD2,
										IPMI_AUTHTYPE_MD5, IPMI_AUTHTYPE_STRAIGHT, IPMI_AUTHTYPE_OEM,
										IPMI_AUTHTYPE_RMCP_PLUS
									]),
			'ipmi_privilege'	=> 'in '.implode(',', [IPMI_PRIVILEGE_CALLBACK, IPMI_PRIVILEGE_USER,
										IPMI_PRIVILEGE_OPERATOR, IPMI_PRIVILEGE_ADMIN, IPMI_PRIVILEGE_OEM
									]),
			'ipmi_username'		=> 'db hosts.ipmi_username',
			'ipmi_password'		=> 'db hosts.ipmi_password',
			'tls_connect'		=> 'db hosts.tls_connect|in '.implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK,
										HOST_ENCRYPTION_CERTIFICATE
									]),
			'tls_accept'		=> 'db hosts.tls_accept|ge 0|le '.
										(0 | HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE),
			'tls_subject'		=> 'db hosts.tls_subject',
			'tls_issuer'		=> 'db hosts.tls_issuer',
			'inventory_mode'	=> 'db host_inventory.inventory_mode|in '.implode(',', [HOST_INVENTORY_DISABLED,
										HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC
									]),
			'host_inventory'	=> 'array',
			'macros'			=> 'array',
			'valuemaps'			=> 'array'
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
		if ($this->hasInput('clone') || $this->hasInput('full_clone')) {
			return $this->hasInput('hostid');
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('hostid')) {
			if ($this->hasInput('full_clone') || $this->hasInput('clone')) {
				$this->clone_hostid = $this->getInput('hostid');
				$this->host = [['hostid' => null]];
			}
			else {
				$data['tableTitles'] = getHostInventories();
				$data['tableTitles'] = zbx_toHash($data['tableTitles'], 'db_field');
				$inventory_fields = array_keys($data['tableTitles']);

				$this->host = API::Host()->get([
					'output' => ['hostid', 'host', 'name', 'status', 'description', 'proxy_hostid', 'ipmi_authtype',
						'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_connect', 'tls_accept', 'tls_issuer',
						'tls_subject', 'flags', 'inventory_mode'
					],
					'selectDiscoveryRule' => ['itemid', 'name', 'parent_hostid'],
					'selectGroups' => ['groupid'],
					'selectHostDiscovery' => ['parent_hostid'],
					'selectInterfaces' => ['interfaceid', 'type', 'available', 'error', 'details', 'ip', 'dns', 'port',
						'useip'
					],
					'selectInventory' => $inventory_fields,
					'selectMacros' => ['hostmacroid', 'macro', 'value', 'description', 'type'],
					'selectParentTemplates' => ['templateid', 'name'],
					'selectTags' => ['tag', 'value'],
					'selectValueMaps' => ['valuemapid', 'name', 'mappings'],
					'hostids' => $this->getInput('hostid'),
					'editable' => true
				]);
			}

			if (!$this->host) {
				return false;
			}

			$this->host = $this->host[0];
		}

		return true;
	}

	protected function doAction(): void {
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
			'full_clone' => $this->hasInput('full_clone') ? 1 : null,
			'clone_hostid' => $this->clone_hostid,
			'host' => $this->host,
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($this->getAction() === 'popup.host.edit' && ($messages = getMessages()) !== null) {
			$data['warnings'] = $messages
				->addClass(ZBX_STYLE_MSG_WARNING)
				->toString();
		}

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
			$data['host']['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			foreach($data['host']['tags'] as &$tag) {
				if (!array_key_exists('value', $tag)) {
					$tag['value'] = '';
				}
			};
			unset($tag);

			CArrayHelper::sort($data['host']['tags'], ['tag', 'value']);
		}

		// Extend data for view.
		$this->extendHostGroups($data['groups_ms']);
		$this->extendLinkedTemplates($data['editable_templates']);
		$this->extendDiscoveryRule($data['editable_discovery_rules']);
		$this->extendProxies($data['proxies']);
		$this->extendInventory($data['inventory_items'], $data['inventory_fields']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of host'));
		$this->setResponse($response);
	}

	/**
	 * Function to prepare data for host group multiselect.
	 *
	 * @param array $groups_ms
	 *
	 * @return void
	 */
	protected function extendHostGroups(?array &$groups_ms): void {
		$groupids = array_column($this->host['groups'], 'groupid');

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
		foreach ($groupids as $groupid) {
			$groups_ms[] = [
				'id' => $groupid,
				'name' => $groups_all[$groupid]['name'],
				'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) && !array_key_exists($groupid, $groups_rw)
			];
		}

		CArrayHelper::sort($groups_ms, ['name']);
	}

	/**
	 * Function to prepare data for Linked templates list.
	 *
	 * @param array $editable_templates
	 *
	 * @return void
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
	 * Function to select editable discovery rules for 'Discovered by' link.
	 *
	 * @param array $editable_discovery_rule
	 *
	 * @return void
	 */
	protected function extendDiscoveryRule(?array &$editable_discovery_rule): void {
		$editable_discovery_rule = $this->host['discoveryRule']
			? API::DiscoveryRule([
				'output' => [],
				'itemids' => array_column($this->host['discoveryRule'], 'itemid'),
				'editable' => true,
				'preservekeys' => true
			])
			: [];
	}

	/**
	 * Function to select data for 'Monitored by proxy' field.
	 *
	 * @param array $proxies
	 *
	 * @return void
	 */
	protected function extendProxies(?array &$proxies): void {
		if ($this->host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$proxies = ($this->host['proxy_hostid'] != '0')
				? API::Proxy()->get([
					'output' => ['host', 'proxyid'],
					'proxyids' => [$this->host['proxy_hostid']],
					'preservekeys' => true
				])
				: [];
		}
		else {
			$proxies = API::Proxy()->get([
				'output' => ['host', 'proxyid'],
				'preservekeys' => true
			]);
			CArrayHelper::sort($proxies, ['host']);
		}

		$proxies = array_column($proxies, 'host', 'proxyid');
	}

	/**
	 * Function to prepare data of inventory fields and find items selected to populate each of inventory fields.
	 *
	 * @param array $inventory_items
	 * @param array $inventory_fields
	 *
	 * @return void
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
				'output' => ['inventory_link', 'itemid', 'hostid', 'name', 'key_'],
				'hostids' => $this->host['hostid'],
				'filter' => [
					'inventory_link' => array_keys($inventory_fields)
				]
			])
			: [];

		$inventory_items = zbx_toHash($inventory_items, 'inventory_link');
		$inventory_items = CMacrosResolverHelper::resolveItemNames($inventory_items);
	}

	/**
	 * Returns array with post input values.
	 *
	 * @return array
	 */
	protected function getInputValues(): array {
		$inputs = [];

		if ($this->hasInput('clone') || $this->hasInput('full_clone')) {
			$inputs['groups'] = zbx_toObject($this->getInput('groups', []), 'groupid');

			$inputs['name'] = $this->getInput('visiblename', '');
			$inputs['inventory'] = $this->getInput('host_inventory', []);

			$this->getInputs($inputs, [
				'host', 'description', 'status', 'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'tls_connect', 'tls_accept', 'tls_subject', 'tls_issuer', 'tags', 'inventory_mode',
				'host_inventory'
			]);

			$field_add_templates = $this->getInput('add_templates', []);
			$field_templates = $this->getInput('templates', []);
			$linked_templates = API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_merge($field_add_templates, $field_templates),
				'preservekeys' => true
			]);

			$secrets_reset = false;
			$inputs['macros'] = array_map(function ($macro) use (&$secrets_reset) {
				unset($macro['hostmacroid']);

				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET) {
					$secrets_reset = true;
					$macro = ['value' => '', 'type' => ZBX_MACRO_TYPE_TEXT] + $macro;
				}

				$macro = $macro + ['description' => ''];

				return $macro;
			}, $this->getInput('macros', []));

			if ($secrets_reset) {
				CMessageHelper::addError(
					_('The cloned host contains user defined macros with type "Secret text". The value and type of these macros were reset.')
				);
			}

			$inputs['valuemaps'] = array_map(function ($valuemap) {
				unset($valuemap['valuemapid']);

				return $valuemap;
			}, $this->getInput('valuemaps', []));

			$inputs['interfaces'] = array_map(function ($interface) {
				unset($interface['interfaceid'], $interface['items']);

				return $interface;
			}, $this->getInput('interfaces', []));

			$inputs['parentTemplates'] = array_intersect_key($linked_templates, array_flip($field_templates));
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
			'proxy_hostid' => '0',
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
