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

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'hostid' => 'db hosts.hostid',
			'groupids' => 'array_db hosts_groups.groupid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('hostid')) {
			$this->host = API::Host()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectDiscoveryRule' => ['itemid', 'name', 'parent_hostid'],
				'selectGroups' => ['groupid'],
				'selectHostDiscovery' => ['parent_hostid'],
				'selectInterfaces' => API_OUTPUT_EXTEND,
				'selectInventory' => API_OUTPUT_EXTEND,
				'selectMacros' => ['hostmacroid', 'macro', 'value', 'description', 'type'],
				'selectParentTemplates' => ['templateid', 'name'],
				'selectTags' => API_OUTPUT_EXTEND,
				'selectValueMaps' => API_OUTPUT_EXTEND,
				'hostids' => $this->getInput('hostid'),
				'editable' => true
			]);

			if (!$this->host) {
				return false;
			}

			$this->host = $this->host[0];
		}

		return true;
	}

	protected function doAction(): void {

		$this->host = (array) $this->host + $this->getInputValues() + $this->getHostDefaultValues();

		$data = [
			'form_action' => $this->host['hostid'] ? 'host.create' : 'host.update',
			'hostid' => $this->host['hostid'],
			'host' => $this->host,
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES),
			'user' => [
				'debug_mode' => $this->getDebugMode()
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
			$data['host']['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			CArrayHelper::sort($data['host']['tags'], ['tag', 'value']);
		}

		// TODO: remove this once fix of ZBX-19555 is delivered into dev-branch.
		$data['host'] += [
			'inventory_mode' => CSettingsHelper::get(CSettingsHelper::DEFAULT_INVENTORY_MODE),
			'tls_psk' => 'tiri-piri!',
			'tls_psk_identity' => 'tiri-piri!'
		];
		// end TODO.

		// Extend data for view.
		$this->extendHostGroups($data['groups_ms']);
		$this->extendLinkedTemplates($data['editable_templates']);
		$this->extendDiscoveryRule($data['editable_discovery_rules']);
		$this->extendProxies($data['proxies']);
		$this->extendInventory($data['inventory_items'], $data['inventory_fields']);

		// Set response.
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
			$proxies = ($this->host['proxy_hostid'] != 0)
				? API::Proxy()->get([
					'output' => ['host', 'proxyid'],
					'proxyids' => [$data['proxy_hostid']],
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
	 * Returns array with host input values.
	 *
	 * @return array
	 */
	protected function getInputValues(): array {
		$values = [];

		if (!$this->host && $this->hasInput('groupids')) {
			$values['groups'] = array_map(function ($groupid) {
				return ['groupid' => $groupid];
			}, $this->getInput('groupids'));
		}

		return $values;
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
			'proxy_hostid' => 0,
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
			'valuemaps' => []
		];
	}
}
