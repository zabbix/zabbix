<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			'authtype'				=> 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'headers'				=> 'array',
			'host_proxy'			=> 'db hosts.proxy_hostid',
			'http_proxy'			=> 'string',
			'follow_redirects'		=> 'in 0,1',
			'key'					=> 'string',
			'interface'				=> 'array',
			'ipmi_sensor'			=> 'string',
			'item_type'				=> 'required|in '.implode(',', self::$testable_item_types),
			'jmx_endpoint'			=> 'string',
			'output_format'			=>	'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params'				=> 'string',
			'password'				=> 'string',
			'post_type'				=> 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts'					=> 'string',
			'privatekey'			=> 'string',
			'publickey'				=> 'string',
			'query_fields'			=> 'array',
			'request_method'		=> 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode'			=> 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'snmp_oid'				=> 'string',
			'snmp_community'		=> 'string',
			'snmpv3_securityname'	=> 'string',
			'snmpv3_contextname'	=> 'string',
			'snmpv3_securitylevel'	=> 'string',
			'snmpv3_authprotocol'	=> 'in '.implode(',', [ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA]),
			'snmpv3_authpassphrase'	=> 'string',
			'snmpv3_privprotocol'	=> 'string',
			'snmpv3_privpassphrase' => 'string',
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
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;

			/*
			 * Check if key is not empty if 'get value from host' is checked and test is made for item with mandatory
			 * key.
			 */
			if ($this->getInput('key', '') === '' && in_array($this->item_type, $this->item_types_has_key_mandatory)) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', _('cannot be empty')));
				$ret = false;
			}

			// Test interface options.
			$interface = $this->getInput('interface', []);

			if (in_array($this->item_type, $this->items_require_interface)) {
				if (!array_key_exists('address', $interface) || $interface['address'] === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Host address'), _('cannot be empty')));
					$ret = false;
				}

				if (!array_key_exists('port', $interface) || $interface['port'] === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Port'), _('cannot be empty')));
					$ret = false;
				}
			}
		}

		if (($messages = getMessages()) !== null) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => CJs::encodeJson([
						'messages' => $messages->toString(),
						'user' => [
							'debug_mode' => $this->getDebugMode()
						]
					])
				]))->disableView()
			);
			$ret = false;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$this->item_type = $this->getInput('item_type');
		$this->is_item_testable = in_array($this->item_type, self::$testable_item_types);

		$data = $this->getItemTestProperties($this->getInputAll());

		$output = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Send test to be executed on Zabbix server.
		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
		$result = $server->testItem($data, CWebUser::getSessionCookie());

		if ($result === false) {
			error($server->getError());
		}
		elseif (is_array($result)) {
			if (array_key_exists('value', $result)) {
				$output['prev_value'] = $this->getInput('value', '');
				$output['prev_time'] = $this->getPrevTime();
				$output['value'] = $result['value'];
			}

			if (array_key_exists('error', $result) && $result['error'] !== '') {
				error($result['error']);
			}
		}

		if (($messages = getMessages(false)) !== null) {
			$output['messages'] = $messages->toString();
		}

		$this->setResponse((new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView());
	}
}
