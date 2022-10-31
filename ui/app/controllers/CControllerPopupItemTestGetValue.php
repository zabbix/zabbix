<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Controller to perform 'get value from host' action in item test dialog.
 */
class CControllerPopupItemTestGetValue extends CControllerPopupItemTest {

	protected function checkInput() {
		$fields = [
			'authtype'				=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'headers'				=> 'array',
			'hostid'				=> 'db hosts.hostid',
			'proxy_hostid'			=> 'id',
			'http_authtype'			=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
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
			$this->preproc_item = self::getPreprocessingItemClassInstance($this->getInput('test_type'));
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

		// Get post data for particular item type.
		$data = $this->getItemTestProperties($this->getInputAll(), true);

		// Apply effective macros values to properties.
		$data = $this->resolveItemPropertyMacros($data);

		if ($this->item_type != ITEM_TYPE_CALCULATED) {
			unset($data['value_type']);
		}
		else {
			$data['host']['hostid'] = $this->getInput('hostid');
		}

		// Rename fields according protocol.
		$data = CArrayHelper::renameKeys($data, [
			'params_ap' => 'params',
			'params_es' => 'params',
			'params_f' => 'params',
			'script' => 'params',
			'http_username' => 'username',
			'http_password' => 'password',
			'http_authtype' => 'authtype',
			'item_type' => 'type'
		]);

		if (array_key_exists('headers', $data)) {
			$data['headers'] = $this->transformHeaderFields($data['headers']);
		}

		if (array_key_exists('query_fields', $data)) {
			$data['query_fields'] = $this->transformQueryFields($data['query_fields']);
		}

		if (array_key_exists('parameters', $data)) {
			$data['parameters'] = $this->transformParametersFields($data['parameters']);
		}

		// Only non-empty fields need to be sent to server.
		$data = $this->unsetEmptyValues($data);

		/*
		 * Server will turn off status code check if field value is empty. If field is not present, then server will
		 * default to check if status code is 200.
		 */
		if ($this->item_type == ITEM_TYPE_HTTPAGENT && !array_key_exists('status_codes', $data)) {
			$data['status_codes'] = '';
		}

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
		$result = $server->testItem($data, CSessionHelper::getId());

		// Handle the response.
		if ($result === false) {
			error($server->getError());
		}
		elseif (is_array($result)) {
			if (array_key_exists('result', $result)) {
				$output['prev_value'] = $this->getInput('value', '');
				$output['prev_time'] = $this->getPrevTime();
				$output['value'] = $result['result'];
				$output['eol'] = (strstr($result['result'], "\r\n") === false) ? ZBX_EOL_LF : ZBX_EOL_CRLF;
			}

			if (array_key_exists('error', $result) && $result['error'] !== '') {
				error($result['error']);
			}
		}

		if ($messages = get_and_clear_messages()) {
			$output['error']['messages'] = array_column($messages, 'message');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
