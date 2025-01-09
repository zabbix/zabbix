<?php
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


/**
 * Controller to perform 'get value from host' action in item test dialog.
 */
class CControllerPopupItemTestGetValue extends CControllerPopupItemTest {

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'headers'				=> 'array',
			'hostid'				=> 'db hosts.hostid',
			'test_with'				=> 'in '.implode(',', [self::TEST_WITH_SERVER, self::TEST_WITH_PROXY]),
			'proxyid'				=> 'id',
			'http_authtype'			=> 'in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'http_password'			=> 'string',
			'http_proxy'			=> 'string',
			'http_username'			=> 'string',
			'flags'					=> 'in '. implode(',', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE, ZBX_FLAG_DISCOVERY_CREATED]),
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interface'				=> 'array',
			'ipmi_sensor'			=> 'string',
			'item_type'				=> 'required|int32',
			'jmx_endpoint'			=> 'string',
			'macros'				=> 'array',
			'output_format'			=> 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params_ap'				=> 'string',
			'params_es'				=> 'string',
			'params_f'				=> 'string',
			'script'				=> 'string',
			'browser_script'		=> 'string',
			'password'				=> 'string',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'string',
			'privatekey'			=> 'string',
			'publickey'				=> 'string',
			'query_fields'			=> 'array',
			'parameters'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'snmp_oid'				=> 'string',
			'ssl_cert_file'			=> 'string',
			'ssl_key_file'			=> 'string',
			'ssl_key_password'		=> 'string',
			'status_codes'			=> 'string',
			'test_type'				=> 'required|in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'time_change'			=> 'int32',
			'timeout'				=> 'string',
			'username'				=> 'string',
			'url'					=> 'string',
			'value'					=> 'string',
			'value_type'			=> 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'verify_host'			=> 'in 0,1',
			'verify_peer'			=> 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$testable_item_types = self::getTestableItemTypes($this->getInput('hostid', '0'));
			$this->item_type = $this->getInput('item_type');
			$this->test_type = $this->getInput('test_type');
			$this->is_item_testable = in_array($this->item_type, $testable_item_types);

			if (!$this->is_item_testable) {
				error(_s('Test of "%1$s" items is not supported.', item_type2str($this->item_type)));
				$ret = false;
			}

			// Check if key is valid for item types it's mandatory.
			if ($ret && in_array($this->item_type, $this->item_types_has_key_mandatory)) {
				$key = $this->getInput('key', '');

				/*
				 * VMware and icmpping simple checks are not supported.
				 * This normally cannot be achieved from UI so no need for error message.
				 */
				if ($this->item_type == ITEM_TYPE_SIMPLE
						&& (substr($key, 0, 7) === 'vmware.' || substr($key, 0, 8) === 'icmpping')) {
					$ret = false;
				}
				else {
					$item_key_parser = new CItemKey();

					if ($item_key_parser->parse($key) != CParser::PARSE_SUCCESS) {
						error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', $item_key_parser->getError()));
						$ret = false;
					}
				}
			}

			// Test interface options.
			$interface = $this->getInput('interface', []);

			if (array_key_exists($this->item_type, $this->items_require_interface)) {
				if (!$this->validateInterface($interface)) {
					$ret = false;
				}
			}

			if ($this->item_type == ITEM_TYPE_CALCULATED) {
				$expression_parser = new CExpressionParser([
					'usermacros' => true,
					'lldmacros' => ($this->getInput('test_type') == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
					'calculated' => true,
					'host_macro' => true,
					'empty_host' => true
				]);

				if ($expression_parser->parse($this->getInput('params_f')) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Formula'),
						$expression_parser->getError()
					));
				}
				else {
					$expression_validator = new CExpressionValidator([
						'usermacros' => true,
						'lldmacros' => ($this->getInput('test_type') == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
						'calculated' => true
					]);

					if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Formula'),
							$expression_validator->getError()
						));
					}
				}
			}

			if ($this->hasInput('test_with') && $this->getInput('test_with') == self::TEST_WITH_PROXY
					&& $this->getInput('proxyid', 0) == 0) {
				error(_s('Incorrect value for field "%1$s": %2$s.',
					_s('%1$s: %2$s', _('Test with'), _('Proxy')), _('cannot be empty')
				));

				$ret = false;
			}
		}

		if ($messages = array_column(get_and_clear_messages(), 'message')) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => $messages
					]
				])])
			);

			$ret = false;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$output = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Send test to be executed on Zabbix server.
		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::ITEM_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);
		$result = $server->testItem($this->prepareTestData(), CSessionHelper::getId());

		// Handle the response.
		if ($result === false) {
			error($server->getError());
		}
		elseif (array_key_exists('error', $result)) {
			error($result['error']);
		}
		elseif (array_key_exists('error', $result['item'])) {
			error($result['item']['error']);
		}
		else {
			$output['prev_value'] = $this->getInput('value', '');
			$output['prev_time'] = $this->getPrevTime();
			$output['value'] = $result['item']['result'];
			$output['eol'] = $result['item']['eol'] === 'CRLF' ? ZBX_EOL_CRLF : ZBX_EOL_LF;

			if (array_key_exists('truncated', $result['item']) && $result['item']['truncated']) {
				$output['value_warning'] = _s('Result is truncated due to its size (%1$s).',
					convertUnits(['value' => $result['item']['original_size'], 'units' => 'B'])
				);
			}
		}

		$messages = get_and_clear_messages();

		if ($messages) {
			foreach ($messages as &$message) {
				if ($message['message'] === '') {
					$message['message'] = _('<empty string>');
				}
			}
			unset($message);

			$output['error']['messages'] = array_column($messages, 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
