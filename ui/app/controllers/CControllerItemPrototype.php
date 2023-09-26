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


abstract class CControllerItemPrototype extends CController {

	protected function checkPermissions(): bool {
		if (!$this->hasInput('context')) {
			return false;
		}

		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function validateFormInput(array $required_fields): bool {
		$fields = [
			'name'					=> 'db items.name',
			'type'					=> 'db items.type',
			'key'					=> 'db items.key_',
			'value_type'			=> 'db items.value_type',
			'url'					=> 'db items.url',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'script'				=> 'db items.params',
			'request_method'		=> 'db items.request_method',
			'timeout'				=> 'db items.timeout',
			'post_type'				=> 'db items.post_type',
			'posts'					=> 'db items.posts',
			'headers'				=> 'array',
			'status_codes'			=> 'db items.status_codes',
			'follow_redirects'		=> 'db items.follow_redirects',
			'retrieve_mode'			=> 'db items.retrieve_mode',
			'output_format'			=> 'db items.output_format',
			'http_proxy'			=> 'db items.http_proxy',
			'http_authtype'			=> 'db items.authtype',
			'http_username'			=> 'db items.username',
			'http_password'			=> 'db items.password',
			'verify_peer'			=> 'db items.verify_peer',
			'verify_host'			=> 'db items.verify_host',
			'ssl_cert_file'			=> 'db items.ssl_cert_file',
			'ssl_key_file'			=> 'db items.ssl_key_file',
			'ssl_key_password'		=> 'db items.ssl_key_password',
			'master_itemid'			=> 'id',
			'interfaceid'			=> 'id',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'authtype'				=> 'db items.authtype',
			'jmx_endpoint'			=> 'db items.jmx_endpoint',
			'username'				=> 'db items.username',
			'publickey'				=> 'db items.publickey',
			'privatekey'			=> 'db items.privatekey',
			'password'				=> 'db items.password',
			'params_es'				=> 'db items.params',
			'params_ap'				=> 'db items.params',
			'params_f'				=> 'db items.params',
			'units'					=> 'db items.units',
			'delay'					=> 'db items.delay',
			'delay_flex'			=> 'array',
			'history_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'history'				=> 'db items.history',
			'trends_mode'			=> 'in '.implode(',', [ITEM_STORAGE_OFF, ITEM_STORAGE_CUSTOM]),
			'trends'				=> 'db items.trends',
			'logtimefmt'			=> 'db items.logtimefmt',
			'valuemapid'			=> 'id',
			'allow_traps'			=> 'db items.allow_traps',
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'description'			=> 'db items.description',
			'status'				=> 'db items.status',
			'discover'				=> 'db items.discover',
			'show_inherited_tags'	=> 'in 0,1',
			'tags'					=> 'array',
			'preprocessing'			=> 'array',
			'context'				=> 'required|in host,template',
			'hostid'				=> 'id',
			'itemid'				=> 'id',
			'parent_discoveryid'	=> 'id',
			'templateid'			=> 'id',
			'form_refresh'			=> 'in 1'
		];

		foreach ($required_fields as $field) {
			$fields[$field] = 'required|'.$fields[$field];
		}

		$field = '';
		$ret = $this->validateInput($fields);
		$tags = $this->getInput('tags', []);

		if ($ret && $tags) {
			$ret = count(array_column($tags, 'tag')) == count(array_column($tags, 'value'));
			$field = 'tags';
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

		if ($ret) {
			$ret = $this->hasInput('parent_discoveryid') || $this->hasInput('itemid');
			$field = $this->hasInput('parent_discoveryid') ? 'itemid' : 'parent_discoveryid';
		}

		if (!$ret && $field !== '') {
			error(_s('Incorrect value for "%1$s" field.', $field));

			return false;
		}

		if ($this->hasInput('itemid')) {
			$ret = (bool) API::ItemPrototype()->get([
				'output' => ['itemid'],
				'itemids' => [$this->getInput('itemid')]
			]);
		}

		if ($ret && $this->hasInput('parent_discoveryid')) {
			$ret = (bool) API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'itemids' => [$this->getInput('parent_discoveryid')]
			]);
		}

		if ($ret && $this->hasInput('hostid')) {
			$check_host = $this->getInput('context', 'host') === 'host';

			if ($check_host) {
				$ret = (bool) API::Host()->get([
					'output' => ['hostid'],
					'hostids' => [$this->getInput('hostid')]
				]);
			}
			else {
				$ret = (bool) API::Template()->get([
					'output' => ['hostid'],
					'templateids' => [$this->getInput('hostid')]
				]);
			}
		}

		if (!$ret) {
			error(_('No permissions to referred object or it does not exist!'));
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
			'delay_flex' => [],
			'delay' => ZBX_ITEM_DELAY_DEFAULT,
			'description' => DB::getDefault('items', 'description'),
			'discover' => DB::getDefault('items', 'discover'),
			'follow_redirects' => DB::getDefault('items', 'follow_redirects'),
			'headers' => [],
			'history_mode' => ITEM_STORAGE_CUSTOM,
			'history' => DB::getDefault('items', 'history'),
			'hostid' => 0,
			'http_authtype' => ZBX_HTTP_AUTH_NONE,
			'http_password' => '',
			'http_proxy' => DB::getDefault('items', 'http_proxy'),
			'http_username' => '',
			'interfaceid' => 0,
			'ipmi_sensor' => DB::getDefault('items', 'ipmi_sensor'),
			'itemid' => 0,
			'jmx_endpoint' => ZBX_DEFAULT_JMX_ENDPOINT,
			'key' => '',
			'logtimefmt' => DB::getDefault('items', 'logtimefmt'),
			'master_itemid' => 0,
			'name' => '',
			'output_format' => DB::getDefault('items', 'output_format'),
			'parameters' => [],
			'params_ap' => DB::getDefault('items', 'params'),
			'params_es' => DB::getDefault('items', 'params'),
			'params_f' => DB::getDefault('items', 'params'),
			'parent_discoveryid' => 0,
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
			'status_codes' => DB::getDefault('items', 'status_codes'),
			'status' => DB::getDefault('items', 'status'),
			'tags' => [],
			'templateid' => 0,
			'timeout' => DB::getDefault('items', 'timeout'),
			'trapper_hosts' => DB::getDefault('items', 'trapper_hosts'),
			'trends_mode' => ITEM_STORAGE_CUSTOM,
			'trends' => DB::getDefault('items', 'trends'),
			'type' => DB::getDefault('items', 'type'),
			'units' => DB::getDefault('items', 'units'),
			'url' => '',
			'username' => DB::getDefault('items', 'username'),
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'valuemapid' => 0,
			'verify_host' => DB::getDefault('items', 'verify_host'),
			'verify_peer' => DB::getDefault('items', 'verify_peer')
		];

		if ($this->hasInput('form_refresh')) {
			// Set unchecked values.
			$input = [
				'allow_traps' => HTTPCHECK_ALLOW_TRAPS_OFF,
				'discover' => ZBX_PROTOTYPE_NO_DISCOVER,
				'follow_redirects' => HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF,
				'output_format' => HTTPCHECK_STORE_RAW,
				'status' => ITEM_STATUS_DISABLED,
				'verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
				'verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF
			] + $input;
		}

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

			$input['preprocessing'] = CItemPrototypeHelper::sortPreprocessingSteps($preprocessings);
		}

		if ($input['tags']) {
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

		if ($input['type'] != ITEM_TYPE_JMX) {
			$input['jmx_endpoint'] = ZBX_DEFAULT_JMX_ENDPOINT;
		}

		if ($input['request_method'] == HTTPCHECK_REQUEST_HEAD) {
			$input['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
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

		return CItemPrototypeHelper::convertFormInputForApi($input);
	}
}
