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

class CControllerItemPrototypeEdit extends CControllerItemPrototype {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateFormInput([]);

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
		$form_refresh = $this->hasInput('form_refresh');
		$form = $form_refresh || !$this->hasInput('itemid') ? $this->getInputForForm() : $this->getItem();
		$itemid = $this->hasInput('itemid') ? $this->getInput('itemid') : $this->getInput('parent_discoveryid');
		$host = $this->getInput('context') === 'host' ? $this->getHost($itemid) : $this->getTemplate($itemid);
		$form['hostid'] = $host['hostid'];
		$data = [
			'readonly' => false,
			'host' => $host,
			'valuemap' => [],
			'inventory_fields' => [],
			'form' => $form,
			'form_refresh' => $form_refresh,
			'parent_items' => [],
			'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
			'discovery_rule' => [],
			'discovery_itemid' => 0,
			'master_item' => [],
			'types' => item_type2str(),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($host['hostid']),
			'interface_types' => itemTypeInterface(),
			'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM,
			'preprocessing_types' => CItem::SUPPORTED_PREPROCESSING_TYPES,
			'inherited_timeouts' => [],
			'can_edit_source_timeouts' => false,
			'config' => [
				'compression_status' => CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS),
				'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL),
				'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
				'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
				'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)
			],
			'user' => ['debug_mode' => $this->getDebugMode()]
		];
		unset($data['types'][ITEM_TYPE_HTTPTEST]);

		if ($data['form']['valuemapid']) {
			$valuemap = API::ValueMap()->get([
				'output' => ['valuemapid', 'name', 'hostid'],
				'valuemapids' => [$data['form']['valuemapid']]
			]);

			if ($valuemap) {
				$valuemap = reset($valuemap);

				if (!$data['form']['templateid'] && bccomp($valuemap['hostid'], $host['hostid']) != 0) {
					$valuemap = API::ValueMap()->get([
						'output' => ['valuemapid', 'name'],
						'hostids' => [$host['hostid']],
						'filter' => ['name' => $valuemap['name']]
					]);
					$valuemap = $valuemap ? reset($valuemap) : [];
				}

				$data['valuemap'] = CArrayHelper::renameKeys($valuemap, ['valuemapid' => 'id']);
			}
			else {
				$data['valuemapid'] = DB::getDefault('items', 'valuemapid');
			}
		}

		if ($data['form']['master_itemid']) {
			$master_items = API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => [$data['form']['master_itemid']],
				'webitems' => true
			]);

			if (!$master_items) {
				$master_items = API::ItemPrototype()->get([
					'output' => ['itemid', 'name'],
					'itemids' => [$data['form']['master_itemid']]
				]);
			}

			$data['master_item'] = $master_items ? reset($master_items) : [];
		}

		if ($data['form']['itemid']) {
			$item = [
				'itemid' => $data['form']['itemid'],
				'templateid' => $data['form']['templateid']
			];
			$data['parent_items'] = makeItemTemplatesHtml(
				$item['itemid'],
				getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_PROTOTYPE),
				ZBX_FLAG_DISCOVERY_PROTOTYPE,
				$this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);
			[$db_item] = API::ItemPrototype()->get([
				'output' => ['flags'],
				'selectDiscoveryRule' => ['name', 'templateid'],
				'itemids' => [$data['form']['itemid']]
			]);

			if ($db_item) {
				$data['flags'] = $db_item['flags'];
				$data['discovery_rule'] = $db_item['discoveryRule'];
			}
		}

		if ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED) {
			$data['inherited_timeouts'] = getInheritedTimeouts($host['proxyid'])['timeouts'];
			$data['inherited_timeout'] = $timeout_config['timeouts'][$data['form']['type']] ?? '';
			$data['can_edit_source_timeouts'] = $host['proxyid']
				? CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_PROXIES)
				: CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);

			if (!$form_refresh && $data['form']['timeout'] === DB::getDefault('items', 'timeout')) {
				$data['form']['timeout'] = $data['inherited_timeout'];
			}
		}

		$data['value_type_keys'] = [];
		$key_value_type = CItemData::getValueTypeByKey();
		foreach (CItemData::getKeysByItemType() as $type => $keys) {
			foreach ($keys as $key) {
				$value_type = $key_value_type[$key];
				$data['value_type_keys'] += [$type => []];
				$data['value_type_keys'][$type][$key] = $value_type;
			}
		}

		if ($data['form']['templateid']) {
			$data['readonly'] = true;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item prototype'));
		$this->setResponse($response);
	}

	/**
	 * Get host data.
	 *
	 * @param int $itemid  Item prototype or it discovery rule id.
	 */
	protected function getHost($itemid): array {
		[$host] = API::Host()->get([
			'output' => ['hostid', 'proxyid', 'name', 'flags', 'status'],
			'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'useip', 'details', 'type', 'main'],
			'itemids' => [$itemid]
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
	 * @param int $itemid  Item prototype or it discovery rule id.
	 */
	protected function getTemplate($itemid): array {
		[$template] = API::Template()->get([
			'output' => ['templateid', 'name', 'flags'],
			'itemids' => [$itemid]
		]);
		$template += [
			'hostid' => $template['templateid'],
			'proxyid' => 0,
			'status' => HOST_STATUS_TEMPLATE,
			'interfaces' => []
		];

		return $template;
	}

	protected function getItem(): array {
		[$item] = API::ItemPrototype()->get([
			'itemids' => $this->getInput('itemid'),
			'output' => [
				'itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'interfaceid',
				'description', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'parameters', 'posts',
				'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode',
				'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer',
				'verify_host', 'allow_traps', 'discover'
			],
			'selectDiscoveryRule' => ['itemid', 'templateid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value']
		]);
		$item = CItemPrototypeHelper::convertApiInputForForm($item);
		$item['context'] = $this->getInput('context');

		return $item;
	}
}
