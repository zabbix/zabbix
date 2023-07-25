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

	protected function init() {
		$this->disableCsrfValidation();
	}

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
			'templateid'			=> 'id',
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
			'show_inherited_tags'	=> 'in 0,1',
			'discovered'			=> 'in 0,1',
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
		return $this->getUserType() == USER_TYPE_ZABBIX_ADMIN
			|| $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	public function doAction() {
		$hostid = $this->getInput('hostid');
		$form_refresh = $this->hasInput('form_refresh');
		$host = $this->getInput('context') === 'host' ? $this->getHost($hostid) : $this->getTemplate($hostid);
		$data = [
			'action' => $this->getAction(),
			'readonly' => false,
			'host' => $host,
			'valuemap' => [],
			'inventory_fields' => [],
			'form' => $form_refresh || !$this->hasInput('itemid') ? $this->getFormData() : $this->getItemData(),
			'form_refresh' => $form_refresh,
			'display_interfaces' => false,
			'parent_items' => [],
			'discovery_rule' => [],
			'master_item' => [],
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

		if ($this->getInput('show_inherited_tags', 0)) {
			$item = $data['form'];
			$item['discoveryRule'] = [];

			if ($item['itemid']) {
				$db_items = API::Item()->get([
					'output' => [],
					'selectDiscoveryRule' => ['itemid', 'name', 'templateid'],
					'itemids' => [$item['itemid']]
				]);

				if ($db_items) {
					$item += reset($db_items);
				}
			}

			$data['form']['tags'] = $this->getInheritedTags([
				'item' => $item,
				'itemid' => $item['itemid'],
				'tags' => $item['tags'],
				'hostid' => $this->getInput('hostid')
			]);
		}

		if ($this->hasInput('itemid')) {
			$item = [
				'itemid' => $data['form']['itemid'],
				'templateid' => $data['form']['templateid']
			];
			$data['parent_items'] = makeItemTemplatesHtml(
				$item['itemid'],
				getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL),
				ZBX_FLAG_DISCOVERY_NORMAL,
				CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);
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

		if ($data['form']['templateid']) {
			$data['readonly'] = true;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Item'));
		$this->setResponse($response);
	}

	/**
	 * Get host data.
	 *
	 * @param string $hostid
	 */
	protected function getHost($hostid): array {
		[$host] = API::Host()->get([
			'output' => ['name', 'flags', 'status'],
			'selectInterfaces' => ['interfaceid', 'ip', 'port', 'dns', 'useip', 'details', 'type', 'main'],
			'hostids' => [$hostid]
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
	 */
	protected function getTemplate($templateid): array {
		$options = [
			'output' => ['name', 'flags'],
			'templateids' => [$templateid]
		];

		[$template] = API::Template()->get($options);
		$template['interfaces'] = [];

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
			'discovered' => $item['flags'] == ZBX_FLAG_DISCOVERY_CREATED ? 1 : 0,
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

		return $item;
	}

	/**
	 * Get form data for item from input.
	 *
	 * @return array
	 */
	protected function getFormData(): array {
		$form = [
			'allow_traps' => DB::getDefault('items', 'allow_traps'),
			'authtype' => DB::getDefault('items', 'authtype'),
			'context' => '',
			'delay' => ZBX_ITEM_DELAY_DEFAULT,
			'delay_flex' => [],
			'description' => DB::getDefault('items', 'description'),
			'discovered' => 0,
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'headers' => [],
			'history' => DB::getDefault('items', 'history'),
			'history_mode' => ITEM_STORAGE_CUSTOM,
			'hostid' => 0,
			'http_authtype' => ZBX_HTTP_AUTH_NONE,
			'http_password' => '',
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'http_username' => '',
			'interfaceid' => 0,
			'inventory_link' => 0,
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),
			'itemid' => 0,
			'jmx_endpoint' => DB::getDefault('items', 'jmx_endpoint'),
			'key' => '',
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'master_itemid' => 0,
			'name' => '',
			'output_format' => DB::getDefault('items', 'output_format'),
			'parameters' => [['name' => '', 'value' => '']],
			'params' => DB::getDefault('items', 'params'),
			'password' => DB::getDefault('items', 'password'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'preprocessing' => [],
			'privatekey' => DB::getDefault('items', 'privatekey'),
			'publickey' => DB::getDefault('items', 'publickey'),
			'query_fields' => [],
			'request_method' => DB::getDefault('items', 'request_method'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'script' => '',
			'show_inherited_tags' => 0,
			'snmp_oid' => DB::getDefault('items', 'snmp_oid'),
			'ssl_cert_file' => DB::getDefault('items', 'ssl_cert_file'),
			'ssl_key_file' => DB::getDefault('items', 'ssl_key_file'),
			'ssl_key_password' => DB::getDefault('items', 'ssl_key_password'),
			'status' => DB::getDefault('items', 'status'),
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'tags' => [],
			'templateid' => 0,
			'timeout' => DB::getDefault('items', 'timeout'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),
			'trends' => DB::getDefault('items', 'trends'),
			'trends_mode' => ITEM_STORAGE_CUSTOM,
			'type' => DB::getDefault('items', 'type'),
			'units' => DB::getDefault('items', 'units'),
			'url' => '',
			'username' => DB::getDefault('items', 'username'),
			'value_type' => DB::getDefault('items', 'value_type'),
			'valuemapid' => 0,
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'verify_peer' => DB::getDefault('items', 'verify_peer')
		];
		$this->getInputs($form, array_keys($form));

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

		if ($form['preprocessing']) {
			foreach ($form['preprocessing'] as &$preprocessing) {
				$preprocessing += [
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				];
			}
			unset($preprocessing);
		}

		// Unset inherited tags.
		if ($this->getInput('show_inherited_tags', 0) == 0) {
			$tags = [];

			foreach ($form['tags'] as $tag) {
				if (!array_key_exists('type', $tag) || ($tag['type'] & ZBX_PROPERTY_OWN)) {
					$tags[] = [
						'tag' => $tag['tag'],
						'value' => $tag['value']
					];
				}

				$form['tags'] = $tags;
			}
		}

		return $form;
	}

	/**
	 * Add item inherited tags to $data['tags'] array of item tags.
	 * Copy of function getItemFormData, file forms.inc.php, part to get inherited tags.
	 *
	 * @param array $data
	 */
	protected function getInheritedTags(array $data): array {
		if ($data['item']['discoveryRule']) {
			$items = [$data['item']['discoveryRule']];
			$parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_RULE)['templates'];
		}
		else {
			$items = [[
				'templateid' => $data['item']['templateid'],
				'itemid' => $data['itemid']
			]];
			$parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL)['templates'];
		}
		unset($parent_templates[0]);

		$db_templates = $parent_templates
			? API::Template()->get([
				'output' => ['templateid'],
				'selectTags' => ['tag', 'value'],
				'templateids' => array_keys($parent_templates),
				'preservekeys' => true
			])
			: [];

		$inherited_tags = [];

		// Make list of template tags.
		foreach ($parent_templates as $templateid => $template) {
			if (array_key_exists($templateid, $db_templates)) {
				foreach ($db_templates[$templateid]['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $inherited_tags)
							&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
						$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
							$templateid => $template
						];
					}
					else {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
							'parent_templates' => [$templateid => $template],
							'type' => ZBX_PROPERTY_INHERITED
						];
					}
				}
			}
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['hostid'],
			'templated_hosts' => true
		]);

		// Overwrite and attach host level tags.
		if ($db_hosts) {
			foreach ($db_hosts[0]['tags'] as $tag) {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag;
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
			}
		}

		// Overwrite and attach item's own tags.
		foreach ($data['tags'] as $tag) {
			if (array_key_exists($tag['tag'], $inherited_tags)
					&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
				$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
			}
			else {
				$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
			}
		}

		$data['tags'] = [];

		foreach ($inherited_tags as $tag) {
			foreach ($tag as $value) {
				$data['tags'][] = $value;
			}
		}

		if (!$data['tags']) {
			$data['tags'] = [['tag' => '', 'value' => '']];
		}
		else {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
		}

		return $data['tags'];
	}
}
