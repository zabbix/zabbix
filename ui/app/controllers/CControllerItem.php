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


abstract class CControllerItem extends CController {

	protected function checkPermissions(): bool {
		if (!CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('itemids')) {
			$itemids = $this->getInput('itemids', []);

			return count($itemids) == API::Item()->get(['countOutput' => true, 'itemids' => $itemids,
				'editable' => true
			]);
		}

		if ($this->hasInput('itemid')) {
			return (bool) API::Item()->get(['itemids' => [$this->getInput('itemid')], 'editable' => true]);
		}

		if ($this->hasInput('hostid')) {
			return $this->getInput('context') === 'host'
				? (bool) API::Host()->get(['hostids' => [$this->getInput('hostid')], 'editable' => true])
				: (bool) API::Template()->get(['templateids' => [$this->getInput('hostid')], 'editable' => true]);
		}

		return false;
	}

	/**
	 * Validate form input.
	 *
	 * @param array $required_fields  Array of fields to be set as required when validating.
	 *
	 * @return bool  is form input valid.
	 */
	protected function validateFormInput(array $required_fields): bool {
		$fields = [
			'allow_traps'			=> 'in 0,1',
			'authtype'				=> 'db items.authtype',
			'context'				=> 'required|in host,template',
			'delay'					=> 'db items.delay',
			'delay_flex'			=> 'array',
			'description'			=> 'db items.description',
			'follow_redirects'		=> 'in 0,1',
			'form_refresh'			=> 'in 1',
			'headers'				=> 'array',
			'history'				=> 'db items.history',
			'history_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'hostid'				=> 'id',
			'http_authtype'			=> 'db items.authtype',
			'http_password'			=> 'db items.password',
			'http_proxy'			=> 'string',
			'http_username'			=> 'db items.username',
			'interfaceid'			=> 'id',
			'inventory_link'		=> 'db items.inventory_link',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'itemid'				=> 'id',
			'jmx_endpoint'			=> 'db items.jmx_endpoint',
			'key'					=> 'db items.key_',
			'logtimefmt'			=> 'db items.logtimefmt',
			'master_itemid'			=> 'id',
			'name'					=> 'db items.name',
			'output_format'			=> 'in 0,1',
			'parameters'			=> 'array',
			'params_ap'				=> 'db items.params',
			'params_es'				=> 'db items.params',
			'params_f'				=> 'db items.params',
			'password'				=> 'db items.password',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'db items.posts',
			'preprocessing'			=> 'array',
			'privatekey'			=> 'db items.privatekey',
			'publickey'				=> 'db items.publickey',
			'query_fields'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'script'				=> 'db items.params',
			'show_inherited_tags'	=> 'in 0,1',
			'show_inherited_tags'	=> 'in 0,1',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ssl_cert_file'			=> 'db items.ssl_cert_file',
			'ssl_key_file'			=> 'db items.ssl_key_file',
			'ssl_key_password'		=> 'db items.ssl_key_password',
			'status'				=> 'db items.status',
			'status_codes'			=> 'db items.status_codes',
			'tags'					=> 'array',
			'templateid'			=> 'id',
			'timeout'				=> 'db items.timeout',
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'trends'				=> 'db items.trends',
			'trends_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'type'					=> 'db items.type',
			'units'					=> 'db items.units',
			'url'					=> 'db items.url',
			'username'				=> 'db items.username',
			'value_type'			=> 'db items.value_type',
			'valuemapid'			=> 'id',
			'verify_host'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'verify_peer'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON])
		];

		foreach ($required_fields as $field) {
			$fields[$field] = 'required|'.$fields[$field];
		}

		$field = '';
		$ret = $this->validateInput($fields);

		if ($ret) {
			foreach ($this->getInput('tags', []) as $tag) {
				if (!array_key_exists('tag', $tag) || !array_key_exists('value', $tag)) {
					$ret = false;
					$field = 'tags';
					break;
				}
			}
		}

		$parameters = $this->getInput('parameters', []);

		if ($ret && $parameters) {
			$ret = count($parameters) == count(array_column($parameters, 'name'))
				&& count($parameters) == count(array_column($parameters, 'value'));
			$field = 'parameters';
		}

		$query_fields = $this->getInput('query_fields', []);

		if ($ret && $query_fields) {
			$ret = array_key_exists('sortorder', $query_fields)
				&& array_key_exists('name', $query_fields)
				&& array_key_exists('value', $query_fields);
			$field = 'query_fields';
		}

		$headers = $this->getInput('headers', []);

		if ($ret && $headers) {
			$ret = array_key_exists('sortorder', $headers)
				&& array_key_exists('name', $headers)
				&& array_key_exists('value', $headers);
			$field = 'headers';
		}

		$delay_flex = $this->getInput('delay_flex', []);

		if ($ret && $delay_flex) {
			$ret = isValidCustomIntervals($delay_flex);
			$field = 'delay_flex';
		}

		if ($ret) {
			$ret = $this->hasInput('itemid') || $this->hasInput('hostid');
			$field = $this->hasInput('hostid') ? 'itemid' : 'hostid';
		}

		if (!$ret && $field !== '') {
			error(_s('Incorrect value for "%1$s" field.', $field));
		}

		return $ret;
	}

	/**
	 * Get form data for item from input.
	 *
	 * @return array
	 */
	protected function getInputForForm(): array {
		$input = [
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
			'parameters' => [],
			'params_ap' => DB::getDefault('items', 'params'),
			'params_es' => DB::getDefault('items', 'params'),
			'params_f' => DB::getDefault('items', 'params'),
			'password' => DB::getDefault('items', 'password'),
			'post_type' => DB::getDefault('items', 'post_type'),
			'posts' => DB::getDefault('items', 'posts'),
			'preprocessing' => [],
			'privatekey' => DB::getDefault('items', 'privatekey'),
			'publickey' => DB::getDefault('items', 'publickey'),
			'query_fields' => [],
			'request_method' => DB::getDefault('items', 'request_method'),
			'retrieve_mode' => DB::getDefault('items', 'retrieve_mode'),
			'script' => DB::getDefault('items', 'params'),
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
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'valuemapid' => 0,
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'verify_peer' => DB::getDefault('items', 'verify_peer')
		];
		$this->getInputs($input, array_keys($input));

		if ($input['query_fields']) {
			$query_fields = [];

			foreach ($input['query_fields']['sortorder'] as $index) {
				$query_fields[] = [
					'name' => $input['query_fields']['name'][$index],
					'value' => $input['query_fields']['value'][$index]
				];
			}

			$input['query_fields'] = $query_fields;
		}

		if ($input['headers']) {
			$headers = [];

			foreach ($input['headers']['sortorder'] as $index) {
				$headers[] = [
					'name' => $input['headers']['name'][$index],
					'value' => $input['headers']['value'][$index]
				];
			}

			$input['headers'] = $headers;
		}

		if ($input['preprocessing']) {
			$preprocessings = [];

			foreach ($input['preprocessing'] as $preprocessing) {
				$preprocessings[] = $preprocessing + [
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
					'error_handler_params' => ''
				];
			}

			$input['preprocessing'] = $preprocessings;
		}

		if ($input['tags'] && $input['show_inherited_tags'] == 0) {
			// Unset inherited tags.
			$tags = [];

			foreach ($input['tags'] as $tag) {
				if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
					continue;
				}

				$tags[] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
			}

			$input['tags'] = $tags;
		}

		$params_field = [
			ITEM_TYPE_SCRIPT => 'script',
			ITEM_TYPE_SSH => 'params_es',
			ITEM_TYPE_TELNET => 'params_es',
			ITEM_TYPE_DB_MONITOR => 'params_ap',
			ITEM_TYPE_CALCULATED => 'params_f'
		];
		$input['params'] = '';

		if (array_key_exists($input['type'], $params_field)) {
			$field = $params_field[$input['type']];
			$input['params'] = $input[$field];
		}

		return $input;
	}

	/**
	 * Get data to send to API from input.
	 *
	 * @return array
	 */
	protected function getInputForApi(): array {
		$input = $this->getInputForForm();
		$field_map = [];

		if ($this->hasInput('key')) {
			$field_map['key'] = 'key_';
		}

		if ($this->getInput('history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['history'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
			$input['trends'] = ITEM_NO_STORAGE_VALUE;
		}

		if ($this->getInput('type') == ITEM_TYPE_HTTPAGENT) {
			$field_map['http_authtype'] = 'authtype';
			$field_map['http_username'] = 'username';
			$field_map['http_password'] = 'password';
		}

		if ($input['delay_flex']) {
			$custom_intervals = $this->getInput('delay_flex', []);
			isValidCustomIntervals($custom_intervals);
			$input['delay'] = getDelayWithCustomIntervals($input['delay'], $custom_intervals);
		}

		if ($input['query_fields']) {
			$query_fields = [];

			foreach ($input['query_fields'] as $query_field) {
				if ($query_field['name'] === '' && $query_field['value'] === '') {
					continue;
				}

				$query_fields[] = [$query_field['name'] => $query_field['value']];
			}

			$input['query_fields'] = $query_fields;
		}

		if ($input['headers']) {
			$headers = [];

			foreach ($input['headers'] as $header) {
				if ($header['name'] === '' && $header['value'] === '') {
					continue;
				}

				$headers[$header['name']] = $header['value'];
			}

			$input['headers'] = $headers;
		}

		if ($input['preprocessing']) {
			$input['preprocessing'] = normalizeItemPreprocessingSteps($input['preprocessing']);
		}

		if ($input['tags']) {
			$tags = [];

			foreach ($input['tags'] as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				$tags[] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
			}

			$input['tags'] = $tags;
		}

		$parameters = [];

		foreach ($input['parameters'] as $parameter) {
			if ($parameter['name'] === '' || $parameter['value'] === '') {
				continue;
			}

			$parameters[] = $parameter;
		}

		$input['parameters'] = $parameters;

		$input = CArrayHelper::renameKeys($input, $field_map);

		if ($this->getInput('itemid', 0)) {
			// Update operation, set non editable fields from database.
			[$item] = API::Item()->get([
				'output' => ['templateid', 'flags', 'type', 'key_', 'value_type', 'authtype', 'allow_traps'],
				'selectHosts' => ['status'],
				'itemids' => [$this->getInput('itemid')]
			]);
			$item = ['itemid' => $this->getInput('itemid')] + getSanitizedItemFields($input + $item);
		}
		else if ($this->getInput('hostid', 0)) {
			$item = [
				'templateid' => '0',
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
				'hosts' => API::Host()->get([
					'output' => ['hostid', 'status'],
					'hostids' => [$this->getInput('hostid')],
					'templated_hosts' => true,
					'editable' => true
				])
			];
			$item = ['hostid' => $this->getInput('hostid')] + getSanitizedItemFields($input + $item);
		}

		return $item;
	}
}
