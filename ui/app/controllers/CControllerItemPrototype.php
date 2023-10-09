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

	/**
	 * Validate form input.
	 *
	 * @param array $required_fields  Array of fields to be set as required when validating.
	 *
	 * @return bool  is form input valid.
	 */
	protected function validateFormInput(array $required_fields): bool {
		$fields = [
			'name'					=> 'db items.name',
			'type'					=> 'in '.implode(',', array_keys(item_type2str())),
			'key'					=> 'db items.key_',
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'url'					=> 'db items.url',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'script'				=> 'db items.params',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'custom_timeout'		=> 'in '.implode(',', [ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED, ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED]),
			'timeout'				=> 'db items.timeout',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'db items.posts',
			'headers'				=> 'array',
			'status_codes'			=> 'db items.status_codes',
			'follow_redirects'		=> 'in '.implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'http_proxy'			=> 'db items.http_proxy',
			'http_authtype'			=> 'in '.implode(',', array_keys(httptest_authentications())),
			'http_username'			=> 'db items.username',
			'http_password'			=> 'db items.password',
			'verify_peer'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]),
			'verify_host'			=> 'in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'ssl_cert_file'			=> 'db items.ssl_cert_file',
			'ssl_key_file'			=> 'db items.ssl_key_file',
			'ssl_key_password'		=> 'db items.ssl_key_password',
			'master_itemid'			=> 'id',
			'interfaceid'			=> 'id',
			'snmp_oid'				=> 'db items.snmp_oid',
			'ipmi_sensor'			=> 'db items.ipmi_sensor',
			'authtype'				=> 'in '.implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
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
			'allow_traps'			=> 'in '.implode(',', [HTTPCHECK_ALLOW_TRAPS_OFF, HTTPCHECK_ALLOW_TRAPS_ON]),
			'trapper_hosts'			=> 'db items.trapper_hosts',
			'description'			=> 'db items.description',
			'status'				=> 'in '.implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED]),
			'discover'				=> 'in '.implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]),
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

		if ($ret && $this->hasInput('custom_timeout')
				&& $this->getInput('custom_timeout') == ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED) {
			$field = 'timeout';
			$ret = trim($this->getInput('timeout', '')) !== '';
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
					'output' => ['templateid'],
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
	 * Get form input data to be sent to API.
	 *
	 * @return array
	 */
	protected function getInputForApi(): array {
		$unchecked_values = [
			'allow_traps' => HTTPCHECK_ALLOW_TRAPS_OFF,
			'discover' => ZBX_PROTOTYPE_NO_DISCOVER,
			'follow_redirects' => HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF,
			'output_format' => HTTPCHECK_STORE_RAW,
			'status' => ITEM_STATUS_DISABLED,
			'verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
			'verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF
		];
		$input = $unchecked_values + CItemPrototypeHelper::getDefaults();
		$this->getInputs($input, array_keys($input));

		return CItemPrototypeHelper::convertFormInputForApi($input);
	}
}
