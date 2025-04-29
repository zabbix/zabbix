<?php declare(strict_types=0);
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


require 'include/forms.inc.php';

class CControllerItemEdit extends CControllerItem {

	/**
	 * @var array
	 */
	private $host;

	/**
	 * @var array
	 */
	private $template;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'clone' => 'in 1'
		] + static::getValidationFields();

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->hasInput('clone') && !$this->hasInput('itemid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'itemid'));
			}
			elseif (!$this->hasInput('itemid') && !$this->hasInput('hostid')) {
				$ret = false;
				error(_s('Incorrect value for "%1$s" field.', 'hostid'));
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!CWebUser::isLoggedIn() || !$this->validateReferredObjects()) {
			return false;
		}

		if ($this->getInput('context') === 'host') {
			$host = API::Host()->get([
				'output' => ['hostid', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'flags', 'status'],
				'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'useip', 'details', 'type', 'main'],
				'hostids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
				'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
			]);

			if (!$host) {
				return false;
			}

			$this->host = reset($host);
		}
		else {
			$template = API::Template()->get([
				'output' => ['templateid', 'name', 'flags'],
				'templateids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
				'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
			]);

			if (!$template) {
				return false;
			}

			$this->template = reset($template);
		}

		return parent::checkPermissions();
	}

	public function doAction() {
		$host = $this->getInput('context') === 'host' ? $this->getHost() : $this->getTemplate();
		$item = $this->hasInput('clone') ? $this->getClone($host) : $this->getItem($host);
		$item['context'] = $this->getInput('context');
		$inherited_timeouts = getInheritedTimeouts($host['proxyid'])['timeouts'];
		$item['inherited_timeout'] = array_key_exists($item['type'], $inherited_timeouts)
			? $inherited_timeouts[$item['type']] : '';

		if ($item['timeout'] === DB::getDefault('items', 'timeout')) {
			$item['timeout'] = $item['inherited_timeout'];
		}

		$inventory_fields = [];

		if (!$item['discovered']) {
			$set_inventory = array_column(API::Item()->get([
				'output' => ['inventory_link'],
				'hostids' => [$item['hostid']],
				'nopermissions' => true
			]), 'inventory_link', 'inventory_link');

			foreach (getHostInventories() as $inventory_field) {
				$inventory_fields[$inventory_field['nr']] = [
					'label' => $inventory_field['title'],
					'disabled' => array_key_exists($inventory_field['nr'], $set_inventory)
				];
			};
		}

		$value_type_keys = [];
		$key_value_type = CItemData::getValueTypeByKey();

		foreach (CItemData::getKeysByItemType() as $type => $keys) {
			foreach ($keys as $key) {
				$value_type = $key_value_type[$key];
				$value_type_keys += [$type => []];
				$value_type_keys[$type][$key] = $value_type;
			}
		}

		$data = [
			'item' => $item,
			'host' => $host,
			'types' => array_diff_key(item_type2str(), array_flip([ITEM_TYPE_HTTPTEST])),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($host['hostid']),
			'executable_item_types' => checkNowAllowedTypes(),
			'inherited_timeouts' => $inherited_timeouts,
			'interface_types' => itemTypeInterface(),
			'inventory_fields' => $inventory_fields,
			'value_type_keys' => $value_type_keys,
			'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM,
			'preprocessing_types' => CItem::SUPPORTED_PREPROCESSING_TYPES,
			'can_edit_source_timeouts' => $host['proxyid']
				? CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				: CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL),
			'config' => [
				'compression_status' => CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS),
				'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL),
				'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
				'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
				'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)
			],
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item'));
		$this->setResponse($response);
	}

	/**
	 * Get host data.
	 *
	 * @return array
	 */
	protected function getHost(): array {
		$host = $this->host;

		if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
			$host['proxyid'] = $host['assigned_proxyid'];
		}
		unset($host['monitored_by'], $host['assigned_proxyid']);

		$host['interfaces'] = array_column($host['interfaces'], null, 'interfaceid');
		// Sort interfaces to be listed starting with one selected as 'main'.
		CArrayHelper::sort($host['interfaces'], [
			['field' => 'main', 'order' => ZBX_SORT_DOWN],
			['field' => 'interfaceid','order' => ZBX_SORT_UP]
		]);

		return $host;
	}

	/**
	 * Get template data.
	 *
	 * @return array
	 */
	protected function getTemplate(): array {
		$template = $this->template;
		$template += [
			'hostid' => $template['templateid'],
			'proxyid' => 0,
			'status' => HOST_STATUS_TEMPLATE,
			'interfaces' => []
		];

		return $template;
	}

	/**
	 * Get item form input for clone action.
	 *
	 * @param array $host  Item host data.
	 *
	 * @return array item clone form data.
	 */
	protected function getClone(array $host): array {
		$item = [
			'itemid' => 0,
			'templateid' => 0,
			'hostid' => $host['hostid'],
			'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
			'parent_items' => [],
			'discovered' => false,
			'templated' => false,
			'inventory_link' => 0
		] + $this->getFormValues();

		if ($item['valuemapid']) {
			$valuemap = API::ValueMap()->get([
				'output' => ['valuemapid', 'name', 'hostid'],
				'valuemapids' => [$item['valuemapid']]
			]);
			$valuemap = $valuemap ? reset($valuemap) : [];

			if ($valuemap && $valuemap['hostid'] != $host['hostid']) {
				$valuemap = API::ValueMap()->get([
					'output' => ['valuemapid', 'name', 'hostid'],
					'search' => ['name' => $valuemap['name']],
					'filter' => ['hostid' => $host['hostid']]
				]);
				$valuemap = $valuemap ? reset($valuemap) : [];
			}

			$item['valuemap'] = $valuemap;
			$item['valuemapid'] = $valuemap ? $valuemap['valuemapid'] : 0;
		}

		if ($item['master_itemid']) {
			$master_item = API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $item['master_itemid'],
				'webitems' => true
			]);
			$item['master_item'] = $master_item ? reset($master_item) : [];
		}

		return $item;
	}

	/**
	 * Get form data for item from database.
	 *
	 * @param array $host  Item host data.
	 *
	 * @return array item form data.
	 */
	protected function getItem(array $host): array {
		$item = [];

		if ($this->hasInput('itemid')) {
			[$item] = API::Item()->get([
				'output' => [
					'itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
					'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
					'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'interfaceid',
					'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'master_itemid', 'url', 'query_fields',
					'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
					'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps'
				],
				'selectDiscoveryRule' => ['name', 'templateid'],
				'selectInterfaces' => ['interfaceid', 'type', 'ip', 'dns', 'port', 'useip', 'main'],
				'selectItemDiscovery' => ['parent_itemid', 'disable_source'],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'selectTags' => ['tag', 'value'],
				'itemids' => [$this->getInput('itemid')]
			]);
			$item = CItemHelper::convertApiInputForForm($item);
		}

		if (!$item) {
			$item = CItemHelper::getDefaults();
			$item['hostid'] = $host['hostid'];

			if ($this->hasInput('master_itemid')) {
				$item['type'] = ITEM_TYPE_DEPENDENT;
				$master_item = API::Item()->get([
					'output' => ['itemid', 'name'],
					'itemids' => [$this->getInput('master_itemid')],
					'hostids' => [$host['hostid']]
				]);

				if ($master_item) {
					$item['master_itemid'] = $this->getInput('master_itemid');
					$item['master_item'] =  reset($master_item);
				}
			}
		}

		return $item;
	}
}
