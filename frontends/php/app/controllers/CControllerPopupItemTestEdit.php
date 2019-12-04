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
 * Controller to build preprocessing test dialog.
 */
class CControllerPopupItemTestEdit extends CControllerPopupItemTest {

	protected function checkInput() {
		$fields = [
			'authtype' => 'in '.implode(',', [HTTPTEST_AUTH_NONE, HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'data' => 'array',
			'delay' => 'string',
			'get_value' => 'in 0,1',
			'headers' => 'array',
			'hostid' => 'db hosts.hostid',
			'http_proxy' => 'string',
			'follow_redirects' => 'in 0,1',
			'key' => 'string',
			'interfaceid' => 'db interface.interfaceid',
			'ipmi_sensor' => 'string',
			'item_type' => 'in '.implode(',', [ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV2C, ITEM_TYPE_INTERNAL, ITEM_TYPE_SNMPV3, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT]),
			'jmx_endpoint' => 'string',
			'output_format' => 'in '.implode(',', [HTTPCHECK_STORE_RAW, HTTPCHECK_STORE_JSON]),
			'params' => 'string',
			'password' => 'string',
			'post_type' => 'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_JSON, ZBX_POSTTYPE_XML]),
			'posts' => 'string',
			'privatekey' => 'string',
			'publickey' => 'string',
			'query_fields' => 'array',
			'request_method' => 'in '.implode(',', [HTTPCHECK_REQUEST_GET, HTTPCHECK_REQUEST_POST, HTTPCHECK_REQUEST_PUT, HTTPCHECK_REQUEST_HEAD]),
			'retrieve_mode' => 'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'show_final_result' => 'in 0,1',
			'snmp_oid' => 'string',
			'snmp_community' => 'string',
			'snmpv3_securityname' => 'string',
			'snmpv3_contextname' => 'string',
			'snmpv3_securitylevel' => 'string',
			'snmpv3_authprotocol' => 'in '.implode(',', [ITEM_AUTHPROTOCOL_MD5, ITEM_AUTHPROTOCOL_SHA]),
			'snmpv3_authpassphrase' => 'string',
			'snmpv3_privprotocol' => 'string',
			'snmpv3_privpassphrase' => 'string',
			'step_obj' => 'required|int32',
			'steps' => 'array',
			'ssl_cert_file' => 'string',
			'ssl_key_file' => 'string',
			'ssl_key_password' => 'string',
			'status_codes' => 'string',
			'test_type' => 'required|in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'timeout' => 'string',
			'username' => 'string',
			'url' => 'string',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'valuemapid' => 'int32',
			'verify_host' => 'in 0,1',
			'verify_peer' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->item_type = $this->hasInput('item_type') ? $this->getInput('item_type') : -1;
			$this->preproc_item = self::getPreprocessingItemType($this->getInput('test_type'));
			$this->is_item_testable = in_array($this->item_type, self::$testable_item_properties);

			// Check if key is not empty for item types it's mandatory.
			if (in_array($this->item_type, $this->item_types_has_key_mandatory) && $this->getInput('key', '') === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'key_', _('cannot be empty')));
				$ret = false;
			}

			/*
			 * Either the item must be testable or at least one preprocessing test must be passed ("Test" button should
			 * be disabled otherwise).
			 */
			$steps = $this->getInput('steps', []);
			if ($ret && $steps) {
				$steps_validation_response = $this->preproc_item->validateItemPreprocessingSteps($steps);
				if ($steps_validation_response !== true) {
					error($steps_validation_response);
					$ret = false;
				}
			}
			elseif ($ret && !$this->is_item_testable) {
				error(_s('Test of "%1$s" items is not supported.', item_type2str($this->item_type)));
				$ret = false;
			}
		}

		if (($messages = getMessages(false, null, false)) !== null) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => CJs::encodeJson(['errors' => $messages->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function doAction() {
		// Get item and host properties and values from cache.
		$data = $this->getInput('data', []);
		$inputs = $this->getItemTestProperties($data);

		// Work with preprocessing steps.
		$preprocessing_steps = $this->getInput('steps', []);
		$preprocessing_types = zbx_objectValues($preprocessing_steps, 'type');
		$preprocessing_names = get_preprocessing_types(null, false, $preprocessing_types);
		$support_lldmacros = ($this->preproc_item instanceof CItemPrototype);
		$show_prev = (count(array_intersect($preprocessing_types, self::$preproc_steps_using_prev_value)) > 0);

		// Extract macros and get effective values.
		$usermacros = CMacrosResolverHelper::extractMacrosFromPreprocessingSteps([
			'steps' => $preprocessing_steps,
			'hostid' => $this->host ? $this->host['hostid'] : 0,
			'delay' => $show_prev ? $this->getInput('delay', ZBX_ITEM_DELAY_DEFAULT) : ''
		], $support_lldmacros);

		// Set resolved macros to previously specified values.
		if ($usermacros['macros'] && array_key_exists('macros', $data) && is_array($data['macros'])) {
			foreach (array_keys($usermacros['macros']) as $macro_name) {
				if (array_key_exists($macro_name, $data['macros']))
				$usermacros['macros'][$macro_name] = $data['macros'][$macro_name];
			}
		}

		// Get previous value time.
		if ($show_prev && array_key_exists('prev_time', $data)) {
			$prev_time = $data['prev_time'];
		}
		elseif ($show_prev) {
			$delay = timeUnitToSeconds($usermacros['delay']);
			$prev_time = ($delay !== null && $delay > 0)
				? 'now-'.$usermacros['delay']
				: 'now';
		}
		else {
			$prev_time = '';
		}

		// Sort macros.
		ksort($usermacros['macros']);

		// Add step number and name for each preprocessing step.
		$num = 0;
		foreach ($preprocessing_steps as &$step) {
			$step['name'] = $preprocessing_names[$step['type']];
			$step['num'] = ++$num;
		}
		unset($step);

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test item'),
			'steps' => $preprocessing_steps,
			'steps_num' => count($preprocessing_steps),
			'value' => array_key_exists('value', $data) ? $data['value'] : '',
			'eol' => array_key_exists('eol', $data) ? (int) $data['eol'] : ZBX_EOL_LF,
			'prev_value' => ($show_prev && array_key_exists('prev_value', $data)) ? $data['prev_value'] : '',
			'macros' => $usermacros['macros'],
			'show_prev' => $show_prev,
			'prev_time' => $prev_time,
			'hostid' => $this->getInput('hostid'),
			'test_type' => $this->getInput('test_type'),
			'step_obj' => $this->getInput('step_obj'),
			'show_final_result' => $this->getInput('show_final_result'),
			'valuemapid' => $this->getInput('valuemapid', 0),
			'get_value' => array_key_exists('get_value', $data)
				? $data['get_value']
				: $this->getInput('get_value', 0),
			'is_item_testable' => $this->is_item_testable,
			'inputs' => $inputs,
			'proxies' => $this->getHostProxies(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
