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

class CControllerItemEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'hostid'				=> 'required|id',
			'context'				=> 'required|in host,template',
			'interfaceid'			=> 'id',
			'itemid'				=> 'id',
			'name'					=> 'db items.name',
			'description'			=> 'db items.description',
			'key'					=> 'db items.key_',
			'master_itemid'			=> 'id',
			'delay'					=> 'db items.delay',
			'history_mode'			=> 'int32',
			'history'				=> 'db items.history',
			'status'				=> 'db items.status',
			'type'					=> 'db items.type',
			'trends_mode'			=> 'int32',
			'trends'				=> 'db items.trends',
			'value_type'			=> 'db items.value_type',
			'valuemapid'			=> 'id',
			'authtype'				=> 'db items.authtype',
			'username'				=> 'db items.username',
			'password'				=> 'db items.password',
			'http_authtype'			=> 'db items.authtype',
			'http_username'			=> 'db items.username',
			'http_password'			=> 'db items.password',
			'publickey'				=> 'db items.publickey',
			'privatekey'			=> 'db items.privatekey',
			'script'				=> 'db items.params',
			'inventory_link'		=> 'db items.inventory_link',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'units'					=> 'db items.units',
			'logtimefmt'			=> 'db items.logtimefmt',
			'show_inherited_tags'	=> 'int32',
			'preprocessing'			=> 'array',
			'tags'					=> 'array',
			'delay_flex'			=> 'array',
			'preprocessing'			=> 'array',
			'parameters'			=> 'array',
			'query_fields'			=> 'array',
			'headers'				=> 'array',
			'delay_flex'			=> 'array',
			'form_refresh'			=> 'in 1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			foreach ($this->getInput('tags', []) as $tag) {
				if (!array_key_exists('tag', $tag) || !array_key_exists('value', $tag)) {
					$ret = false;
					break;
				}
			}
		}

		$parameters = $this->getInput('parameters', []);

		if ($ret && $parameters) {
			$ret = count($parameters) == count(array_column($parameters, 'name'))
				&& count($parameters) == count(array_column($parameters, 'value'));
		}

		$query_fields = $this->getInput('query_fields', []);

		if ($ret && $query_fields) {
			$ret = array_key_exists('sortorder', $query_fields)
				&& array_key_exists('name', $query_fields)
				&& array_key_exists('value', $query_fields);
		}

		$headers = $this->getInput('headers', []);

		if ($ret && $headers) {
			$ret = array_key_exists('sortorder', $headers)
				&& array_key_exists('name', $headers)
				&& array_key_exists('value', $headers);
		}

		$delay_flex = $this->getInput('delay_flex', []);

		if ($ret && $delay_flex) {
			foreach ($delay_flex as $interval) {
				if (!array_key_exists('type', $interval)
						|| ($interval['type'] != ITEM_DELAY_FLEXIBLE && $interval['type'] != ITEM_DELAY_SCHEDULING)) {
					$ret = false;
					break;
				};
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	public function doAction() {
		$hostid = $this->getInput('hostid');
		$form_refresh = $this->hasInput('form_refresh');
		$data = [
			'action' => $this->getAction(),
			'readonly' => false,
			'host' => $this->getHostOrTemplate($this->getInput('context')),
			'valuemap' => [],
			'inventory_fields' => [],
			'form' => $form_refresh ? $this->getFormData() : $this->getItemData(),
			'form_refresh' => $form_refresh,
			'display_interfaces' => false,
			'parent_templates' => [],
			'discovery_rule' => [],
			'master_item' => [],
			'host_interfaces' => [],
			'types' => item_type2str(),
			'testable_item_types' => CControllerPopupItemTest::getTestableItemTypes($hostid),
			'interface_types' => itemTypeInterface(),
			'preprocessing_test_type' => CControllerPopupItemTestEdit::ZBX_TEST_TYPE_ITEM,
			'preprocessing_types' => CItem::SUPPORTED_PREPROCESSING_TYPES,
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
			$valuemaps = CArrayHelper::renameObjectsKeys(API::ValueMap()->get([
				'output' => ['valuemapid', 'name'],
				'valuemapids' => $data['form']['valuemapid']
			]), ['valuemapid' => 'id']);
			$data['valuemap'] = $valuemaps ? reset($valuemaps) : [];
		}

		if ($data['form']['master_itemid']) {
			$master_items = API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $data['form']['master_itemid']
			]);
			$data['master_item'] = $master_items ? reset($master_items) : [];
		}

		if ($this->getInput('context') === 'host') {
			[$host] = API::Host()->get([
				'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'details', 'type'],
				'hostids' => [$hostid]
			]);
			$data['host_interfaces'] = array_column($host['interfaces'], null, 'interfaceid');
		}

		$set_inventory = array_column(API::Item()->get([
			'output' => ['inventory_link'],
			'hostids' => [$hostid],
			'nopermissions' => true
		]), 'inventory_link', 'inventory_link');

		foreach (getHostInventories() as $inventory_field) {
			$data['inventory_fields'][$inventory_field['nr']] = [
				'label' => $inventory_field['title'],
				'disabled' => array_key_exists($inventory_field['nr'], $set_inventory)
			];
		};

		$data['value_type_keys'] = [];
		$key_value_type = CItemData::getValueTypeByKey();
		foreach (CItemData::getKeysByItemType() as $type => $keys) {
			foreach ($keys as $key) {
				$value_type = $key_value_type[$key];

				if ($value_type === null) {
					continue;
				}

				$data['value_type_keys'] += [$type => []];
				$data['value_type_keys'][$type][$key] = $value_type;
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item'));
		$this->setResponse($response);
	}

	/**
	 * Get host or template, according to context, 'name' and 'flags' fields.
	 *
	 * @param string $context  Available values: 'host', 'template'.
	 * @return array
	 */
	protected function getHostOrTemplate(string $context): array {
		if ($context === 'host') {
			[$host] = API::Host()->get([
				'output' => ['name', 'flags'],
				'hostids' => $this->getInput('hostid')
			]);

			return $host;
		}

		[$template] = API::Template()->get([
			'output' => ['name', 'flags'],
			'templateids' => $this->getInput('hostid')
		]);

		return $template;
	}

	/**
	 * Get form data for item from database.
	 *
	 * @return array
	 */
	protected function getItemData(): array {
		[$item] = API::Item()->get([
			'ouput' => ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'interfaceid',
				'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'master_itemid', 'url', 'query_fields',
				'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
				'headers', 'retrieve_mode', 'request_method', 'output_format', 'ssl_cert_file', 'ssl_key_file',
				'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps'
			],
			'selectInterfaces' => ['interfaceid', 'type', 'ip', 'dns', 'port', 'useip', 'main'],
			'selectItemDiscovery' => ['itemdiscoveryid ', 'itemid', 'parent_itemid'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'itemids' => $this->getInput('itemid')
		]);

		$i = 0;
		foreach ($item['preprocessing'] as &$step) {
			$step['params'] = $step['type'] == ZBX_PREPROC_SCRIPT
				? [$step['params'], ''] : explode("\n", $step['params']);
			$step['sortorder'] = $i++;
		}
		unset($step);

		$item += [
			'script' => '',
			'http_authtype' => '',
			'http_username' => '',
			'http_password' => '',
			'history_mode' => $item['history'] == ITEM_NO_STORAGE_VALUE ? ITEM_STORAGE_OFF : ITEM_STORAGE_CUSTOM,
			'trends_mode' => $item['trends'] == ITEM_NO_STORAGE_VALUE ? ITEM_STORAGE_OFF : ITEM_STORAGE_CUSTOM,
			'context' => $this->getInput('context'),
			'show_inherited_tags' => 0,
			'key' => $item['key_']
		];
		unset($item['key_']);

		switch ($item['type']) {
			case ITEM_TYPE_SCRIPT:
				$item['script'] = $item['params'];

				break;

			case ITEM_TYPE_HTTPAGENT:
				$item['http_authtype'] = $item['authtype'];
				$item['http_username'] = $item['username'];
				$item['http_password'] = $item['password'];
				$query_fields = [];

				foreach ($item['query_fields'] as $query_field) {
					$query_fields[] = [
						'name' => key($query_field),
						'value' => reset($query_field)
					];
				}

				$item['query_fields'] = $query_fields;
				$headers = [];

				foreach ($item['headers'] as $header => $value) {
					$headers[] = [
						'name' => $header,
						'value' => $value
					];
				}

				$item['headers'] = $headers;

				break;
		}

		$item['delay_flex'] = [];
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => false // TODO: for prototypes should be true
		]);

		if ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item['delay'] = $update_interval_parser->getDelay();

			if ($item['delay'][0] !== '{') {
				$delay = timeUnitToSeconds($item['delay']);

				if ($delay == 0 && ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
						|| $item['type'] == ITEM_TYPE_DEPENDENT || ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE
							&& strncmp($item['key'], 'mqtt.get', 8) === 0))) {
					$item['delay'] = ZBX_ITEM_DELAY_DEFAULT;
				}
			}

			foreach ($update_interval_parser->getIntervals() as $interval) {
				if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
					$item['delay_flex'][] = [
						'delay' => $interval['update_interval'],
						'period' => $interval['time_period'],
						'type' => ITEM_DELAY_FLEXIBLE
					];
				}
				else {
					$item['delay_flex'][] = [
						'schedule' => $interval['interval'],
						'type' => ITEM_DELAY_SCHEDULING
					];
				}
			}
		}
		else {
			$item['delay'] = ZBX_ITEM_DELAY_DEFAULT;
		}

		if (!$item['delay_flex']) {
			$item['delay_flex'] = [['delay' => '', 'period' => '', 'type' => ITEM_DELAY_FLEXIBLE]];
		}

		if (!$item['parameters']) {
			$item['parameters'] = [['name' => '', 'value' => '']];
		}

		if (!$item['query_fields']) {
			$item['query_fields'] = [['name' => '', 'value' => '']];
		}

		if (!$item['headers']) {
			$item['headers'] = [['name' => '', 'value' => '']];
		}

		return $item;
	}

	/**
	 * Get form data for item from input.
	 *
	 * @return array
	 */
	protected function getFormData(): array {
		$form = [
			'itemid' => 0,
			'hostid' => 0,
			'context' => '',
			'name' => '',
			'type' => DB::getDefault('items', 'type'),
			'key' => '',
			'value_type' => DB::getDefault('items', 'value_type'),
			'url' => '',
			'query_fields' => [],
			'parameters' => [['name' => '', 'value' => '']],
			'script' => '',
			'request_method' => DB::getDefault('items', 'request_method'),
			'timeout' => DB::getDefault('items', 'timeout'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'output_format' => DB::getDefault('items', 'output_format'),
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'http_authtype' => ZBX_HTTP_AUTH_NONE,// do not have default in db (no field?)
			'http_username' => '',// do not have default in db (no field?)
			'http_password' => '',// do not have default in db (no field?)
			'verify_peer' => DB::getDefault('items', 'verify_peer'),
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
			'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
			'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
			'master_itemid' => 0,
			'snmp_oid' => DB::getDefault('items', 'snmp_oid'),
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),
			'authtype' => DB::getDefault('items', 'authtype'),
			'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),
			'username' => DB::getDefault('items', 'username'),
			'password' => DB::getDefault('items', 'password'),
			'publickey' => DB::getDefault('items', 'publickey'),
			'privatekey' => DB::getDefault('items', 'privatekey'),
			'params' => DB::getDefault('items', 'params'),
			'units' => DB::getDefault('items', 'units'),
			'delay' => DB::getDefault('items', 'delay'),
			'history_mode' => ITEM_STORAGE_OFF,
			'history' => DB::getDefault('items', 'history'),
			'trends_mode' => ITEM_STORAGE_OFF,
			'trends' => DB::getDefault('items', 'trends'),
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'valuemapid' => 0,
			'allow_traps' => DB::getDefault('items', 'allow_traps'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),
			'inventory_link' => 0,
			'description' => DB::getDefault('items', 'description'),
			'status' => DB::getDefault('items', 'status'),
			'show_inherited_tags' => 0,
			'tags' => [],
			'preprocessing' => [],
			'headers' => [],
			'delay_flex' => []
		];
		$this->getInputs($form, array_keys($form));
		// TODO: item with preprocessing trigger undefined index for error_handler, error_handler_params

		if ($form['query_fields']) {
			$query_fields = [];

			foreach ($form['query_fields']['sortorder'] as $index) {
				$query_fields[] = [
					'name' => $form['query_fields']['name'][$index],
					'value' => $form['query_fields']['value'][$index]
				];
			}

			$form['query_fields'] = $query_fields;
		}

		if ($form['headers']) {
			$headers = [];

			foreach ($form['headers']['sortorder'] as $index) {
				$headers[] = [
					'name' => $form['headers']['name'][$index],
					'value' => $form['headers']['value'][$index]
				];
			}

			$form['headers'] = $headers;
		}

		return $form;
	}
}
