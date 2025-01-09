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
	 * Common item prototype field validation rules.
	 *
	 * @return array
	 */
	protected static function getValidationFields(): array {
		return [
			'name'					=> 'db items.name',
			'type'					=> 'in '.implode(',', array_keys(item_type2str())),
			'key'					=> 'db items.key_',
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY]),
			'url'					=> 'db items.url',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'script'				=> 'db items.params',
			'browser_script'		=> 'db items.params',
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
			'passphrase'			=> 'db items.password',
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
			'templateid'			=> 'id'
		];
	}

	/**
	 * Additional validation for user input consumed by create and update actions.
	 *
	 * @return bool
	 */
	protected function validateInputEx(): bool {
		$ret = true;
		$type = $this->getInput('type', -1);
		$fields = array_fill_keys(['name', 'key'], '');
		$this->getInputs($fields, array_keys($fields));

		foreach ($fields as $field => $value) {
			if ($value === '') {
				$ret = false;
				error(_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty')));
			}
		}

		if (isItemExampleKey($type, $this->getInput('key', ''))) {
			$ret = false;
		}

		$delay_flex = $this->getInput('delay_flex', []);

		if ($delay_flex && !isValidCustomIntervals($delay_flex)) {
			$ret = false;
		}

		$simple_interval_parser = new CSimpleIntervalParser([
			'usermacros' => true,
			'lldmacros' => false
		]);

		if (!in_array($type, [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT])
				&& !($type == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($this->getInput('key', ''), 'mqtt.get', 8) == 0)
				&& $simple_interval_parser->parse($this->getInput('delay', '')) != CParser::PARSE_SUCCESS) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'delay', _('a time unit is expected')));
			$ret = false;
		}

		$timeout_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL,
			ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_SNMP, ITEM_TYPE_HTTPAGENT,
			ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
		];

		if (in_array($type, $timeout_types)
				&& $this->getInput('custom_timeout', -1) == ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED
				&& $simple_interval_parser->parse($this->getInput('timeout', '')) != CParser::PARSE_SUCCESS) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout', _('a time unit is expected')));
			$ret = false;
		}

		return $ret && $this->validateReferredObjects();
	}

	/**
	 * Validate for referred objects exists and user have access.
	 *
	 * @return bool
	 */
	protected function validateReferredObjects(): bool {
		$ret = true;

		if ($this->hasInput('itemid')) {
			$ret = (bool) API::ItemPrototype()->get([
				'output' => ['itemid'],
				'itemids' => [$this->getInput('itemid')],
				'editable' => true
			]);
		}

		if ($ret && $this->hasInput('parent_discoveryid')) {
			$lld = API::DiscoveryRule()->get([
				'output' => ['itemid'],
				'selectHosts' => ['status'],
				'itemids' => [$this->getInput('parent_discoveryid')]
			]);
			$ret = (bool) $lld;

			if ($ret) {
				$context = $this->getInput('context');
				$is_template_lld = $lld[0]['hosts'][0]['status'] == HOST_STATUS_TEMPLATE;
				$ret = ($context === 'template' && $is_template_lld) || ($context === 'host' && !$is_template_lld);
			}
		}

		if ($ret && $this->hasInput('hostid')) {
			if ($this->getInput('context') === 'host') {
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
	 * Get form fields value normalized.
	 *
	 * @return array form fields data.
	 */
	protected function getFormValues(): array {
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
		$input = CItemPrototypeHelper::normalizeFormData($input);

		return $input;
	}
}
