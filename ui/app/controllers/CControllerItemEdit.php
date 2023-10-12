<?php declare(strict_types=0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require 'include/forms.inc.php';

class CControllerItemEdit extends CControllerItem {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context'		=> 'required|in host,template',
			'hostid'		=> 'id',
			'itemid'		=> 'id',
			'master_itemid'	=> 'id',
			'clone'			=> 'in 1'
		];
		$ret = $this->validateInput($fields) && $this->validateRefferedObjects();

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

	public function doAction() {
		$host = $this->getInput('context') === 'host' ? $this->getHost() : $this->getTemplate();
		$item = $this->getItem();
		$inherited_timeouts = getInheritedTimeouts($host['proxyid'])['timeouts'];
		$item['inherited_timeout'] = $inherited_timeouts[$item['type']] ?? '';

		if ($item['timeout'] === DB::getDefault('items', 'timeout')) {
			$item['timeout'] = $item['inherited_timeout'];
		}

		$set_inventory = array_column(API::Item()->get([
			'output' => ['inventory_link'],
			'hostids' => [$item['hostid']],
			'nopermissions' => true
		]), 'inventory_link', 'inventory_link');
		$inventory_fields = [];

		foreach (getHostInventories() as $inventory_field) {
			$inventory_fields[$inventory_field['nr']] = [
				'label' => $inventory_field['title'],
				'disabled' => array_key_exists($inventory_field['nr'], $set_inventory)
			];
		};

		$value_type_keys = [];
		$key_value_type = CItemData::getValueTypeByKey();

		foreach (CItemData::getKeysByItemType() as $type => $keys) {
			foreach ($keys as $key) {
				$value_type = $key_value_type[$key];
				$value_type_keys += [$type => []];
				$value_type_keys[$type][$key] = $value_type;
			}
		}

		if ($this->hasInput('clone')) {
			if ($item['valuemap'] && $item['templateid']) {
				$host_valuemap = API::ValueMap()->get([
					'output' => ['valuemapid'],
					'search' => ['name' => $item['valuemap']['name']],
					'filter' => ['hostid' => $item['hostid']]
				]);

				if ($host_valuemap) {
					$host_valuemap = reset($host_valuemap);
					$item['valuemap']['valuemapid'] = $host_valuemap['valuemapid'];
					$item['valuemap']['hostid'] = $item['hostid'];
				}
				else {
					$item['valuemapid'] = 0;
					$item['valuemap'] = [];
				}
			}

			$item = [
				'itemid' => 0,
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
				'templateid' => 0,
				'parent_items' => [],
				'discovered' => false,
				'templated' => false
			] + $item;
		}

		$data = [
			'item' => $item,
			'host' => $host,
			'types' => array_diff_key(item_type2str(), array_flip([ITEM_TYPE_HTTPTEST])),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($host['hostid']),
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
		[$host] = API::Host()->get([
			'output' => ['hostid', 'proxyid', 'name', 'flags', 'status'],
			'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'useip', 'details', 'type', 'main'],
			'hostids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
			'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
		]);

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
	 * @param string $templateid
	 *
	 * @return array
	 */
	protected function getTemplate(): array {
		[$template] = API::Template()->get([
			'output' => ['templateid', 'name', 'flags'],
			'templateids' => !$this->hasInput('itemid') ? [$this->getInput('hostid')] : null,
			'itemids' => $this->hasInput('itemid') ? [$this->getInput('itemid')] : null
		]);
		$template += [
			'hostid' => $template['templateid'],
			'proxyid' => 0,
			'status' => HOST_STATUS_TEMPLATE,
			'interfaces' => []
		];

		return $template;
	}

	/**
	 * Get form data for item from database.
	 *
	 * @return array
	 */
	protected function getItem(): array {
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
				'selectItemDiscovery' => ['parent_itemid'],
				'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
				'selectTags' => ['tag', 'value'],
				'itemids' => [$this->getInput('itemid')]
			]);
			$item = CItemHelper::convertApiInputForForm($item);
		}

		if (!$item) {
			$item = CItemHelper::getDefaults();
			$item['hostid'] = $this->getInput('hostid');

			if ($this->hasInput('master_itemid')) {
				$item['type'] = ITEM_TYPE_DEPENDENT;
				$master_item = API::Item()->get([
					'output' => ['itemid', 'name'],
					'itemids' => [$this->getInput('master_itemid')],
					'hostids' => [$this->getInput('hostid')]
				]);

				if ($master_item) {
					$item['master_itemid'] = $this->getInput('master_itemid');
					$item['master_item'] =  reset($master_item);
				}
			}
		}

		$item['context'] = $this->getInput('context');

		return $item;
	}
}
