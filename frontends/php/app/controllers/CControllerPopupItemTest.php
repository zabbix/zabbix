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
 * Class shares common properties, constants and methods for different controllers used for item tests.
 */
abstract class CControllerPopupItemTest extends CController {
	/**
	 * Types of preprocessing tests, depending on type of item.
	 */
	const ZBX_TEST_TYPE_ITEM = 0;
	const ZBX_TEST_TYPE_ITEM_PROTOTYPE = 1;
	const ZBX_TEST_TYPE_LLD = 2;

	/**
	 * Define a set of item types allowed to test and item properties needed to collect for each item type.
	 *
	 * @var array
	 */
	public static $testable_item_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_SNMPV3, ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
		ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED
	];

	/**
	 * Item value type used if user has not specified one.
	 */
	const ZBX_DEFAULT_VALUE_TYPE = ITEM_VALUE_TYPE_TEXT;

	/**
	 * Item types requiring interface.
	 *
	 * @var array
	 */
	protected $items_require_interface = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C, ITEM_TYPE_SNMPV3,
		ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_SIMPLE
	];

	/**
	 * Item types with proxy support.
	 *
	 * @var array
	 */
	protected $items_support_proxy = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPV1, ITEM_TYPE_SNMPV2C,
		ITEM_TYPE_SNMPV3, ITEM_TYPE_INTERNAL, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT,
		ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX
	];

	/**
	 * Item types with mandatory item key.
	 *
	 * @var array
	 */
	protected $item_types_has_key_mandatory = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI,
		ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED
	];

	/**
	 * Tested item type.
	 *
	 * @var int
	 */
	protected $item_type;

	/**
	 * Tested item's host.
	 *
	 * @var int
	 */
	protected $host;

	/**
	 * Is item testable.
	 *
	 * @var bool
	 */
	protected $is_item_testable;

	/**
	 * @var object
	 */
	protected $preproc_item;

	/**
	 * @var array
	 */
	protected static $preproc_steps_using_prev_value = [ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED,
		ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE
	];

	protected function checkPermissions() {
		$ret = ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);

		/*
		 * Preprocessing test can be done from mass-update section so host is non mandatory but if it is used, it must
		 * be editable.
		 */
		$hostid = $this->getInput('hostid', false);

		if ($ret && $hostid) {
			$host = API::Host()->get([
				'output' => ['hostid', 'host', 'status', 'available', 'flags', 'proxy_hostid', 'tls_subject',
					'ipmi_available', 'jmx_available', 'snmp_available', 'maintenance_status', 'maintenance_type',
					'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_psk_identity', 'tls_psk',
					'tls_issuer', 'tls_connect'
				],
				'hostids' => [$hostid],
				'templated_hosts' => true,
				'editable' => true
			]);

			$this->host = reset($host);
			$ret = (bool) $this->host;
		}

		return $ret;
	}

	/**
	 * Function returns instance of item, item prototype or discovery rule class.
	 *
	 * @param int $test_type
	 *
	 * @return CItem|CItemPrototype|CDiscoveryRule
	 */
	protected function getPreprocessingItemClassInstance($test_type) {
		switch ($test_type) {
			case self::ZBX_TEST_TYPE_ITEM:
				return new CItem;

			case self::ZBX_TEST_TYPE_ITEM_PROTOTYPE:
				return new CItemPrototype;

			case self::ZBX_TEST_TYPE_LLD:
				return new CDiscoveryRule;
		}
	}

	/**
	 * Function returns list of proxies.
	 *
	 * @return array
	 */
	protected function getHostProxies() {
		$proxies = API::Proxy()->get([
			'output' => ['host'],
			'preservekeys' => true
		]);

		CArrayHelper::sort($proxies, [['field' => 'host', 'order' => ZBX_SORT_UP]]);

		foreach ($proxies as &$proxy) {
			$proxy = $proxy['host'];
		}
		unset($proxy);

		return $proxies;
	}

	/**
	 * Function returns array of item specific properties used for item testing.
	 *
	 * @param array $input  Stored user input used to overwrite values retrieved from database.
	 *
	 * @return array
	 */
	protected function getItemTestProperties(array $input) {
		$data = [
			'value_type' => $this->getInput('value_type')
		];

		if (!$this->is_item_testable) {
			return $data;
		}

		$data += [
			'proxy_hostid' => $this->host['proxy_hostid'],
			'type' => $this->item_type
		];

		$interface_input = array_key_exists('interface', $input) ? $input['interface'] : [];

		switch ($this->item_type) {
			case ITEM_TYPE_ZABBIX:
				$data += [
					'key' => $this->getInput('key'),
					'host' => [
						'tls_subject' => $this->host['tls_subject'],
						'tls_psk_identity' => $this->host['tls_psk_identity'],
						'tls_psk' => $this->host['tls_psk'],
						'tls_issuer' => $this->host['tls_issuer'],
						'tls_connect' => $this->host['tls_connect']
					],
					'interface' => $this->getItemTestInterface($interface_input)
				];

				unset($data['interface']['useip'], $data['interface']['interfaceid']);
				break;

			case ITEM_TYPE_SNMPV1:
			case ITEM_TYPE_SNMPV2C:
			case ITEM_TYPE_SNMPV3:
				switch ($this->getInput('test_type')) {
					case self::ZBX_TEST_TYPE_LLD:
						$item_flag = ZBX_FLAG_DISCOVERY_RULE;
						break;

					case self::ZBX_TEST_TYPE_ITEM_PROTOTYPE;
						$item_flag = ZBX_FLAG_DISCOVERY_PROTOTYPE;
						break;

					default:
						$item_flag = ZBX_FLAG_DISCOVERY_NORMAL;
						break;
				}

				$data += [
					'snmp_oid' => $this->getInput('snmp_oid'),
					'snmp_community' => $this->getInput('snmp_community'),
					'flags' => $item_flag,
					'snmpv3_securityname' => $this->getInput('snmpv3_securityname'),
					'snmpv3_contextname' => $this->getInput('snmpv3_contextname'),
					'snmpv3_securitylevel' => $this->getInput('snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV),
					'host' => [
						'host' => $this->host['host']
					],
					'interface' => $this->getItemTestInterface($interface_input)
				];

				if ($data['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$data += [
						'snmpv3_authprotocol' => $this->getInput('snmpv3_authprotocol'),
						'snmpv3_authpassphrase' => $this->getInput('snmpv3_authpassphrase'),
						'snmpv3_privprotocol' => $this->getInput('snmpv3_privprotocol'),
						'snmpv3_privpassphrase' => $this->getInput('snmpv3_privpassphrase')
					];
				}
				break;

			case ITEM_TYPE_INTERNAL:
				$data += [
					'key' => $this->getInput('key'),
					'host' => [
						'hostid' => $this->host['hostid'],
						'available' => $this->host['available'],
						'ipmi_available' => $this->host['ipmi_available'],
						'jmx_available' => $this->host['jmx_available'],
						'snmp_available' => $this->host['snmp_available'],
						'maintenance_status' => $this->host['maintenance_status'],
						'maintenance_type' => $this->host['maintenance_type']
					]
				];
				break;

			case ITEM_TYPE_AGGREGATE:
				$data += [
					'key' => $this->getInput('key'),
					'value_type' => $this->getInput('value_type')
				];
				break;

			case ITEM_TYPE_EXTERNAL:
				$data += [
					'key' => $this->getInput('key')
				];
				break;

			case ITEM_TYPE_DB_MONITOR:
				$data += [
					'key' => $this->getInput('key'),
					'params' => $this->getInput('params'),
					'username' => $this->getInput('username'),
					'password' => $this->getInput('password')
				];
				break;

			case ITEM_TYPE_HTTPAGENT:
				$data += [
					'key' => $this->getInput('key'),
					'authtype' => $this->getInput('authtype', HTTPTEST_AUTH_NONE),
					'follow_redirects' => $this->getInput('follow_redirects', 0),
					'headers' => $this->getInput('headers', []),
					'http_proxy' => $this->getInput('http_proxy'),
					'output_format' => $this->getInput('output_format', 0),
					'posts' => $this->getInput('posts', ''),
					'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_RAW),
					'query_fields' => $this->getInput('query_fields', []),
					'request_method' => $this->getInput('request_method', HTTPCHECK_REQUEST_GET),
					'retrieve_mode' => $this->getInput('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT),
					'ssl_cert_file' => $this->getInput('ssl_cert_file', ''),
					'ssl_key_file' => $this->getInput('ssl_key_file', ''),
					'ssl_key_password' => $this->getInput('ssl_key_password', ''),
					'status_codes' => $this->getInput('status_codes', ''),
					'timeout' => $this->getInput('timeout', ''),
					'url' => $this->getInput('url', ''),
					'verify_host' => $this->getInput('verify_host', 0),
					'verify_peer' => $this->getInput('verify_peer', '')
				];

				if ($data['authtype'] != HTTPTEST_AUTH_NONE) {
					$data += [
						'username' => $this->getInput('http_username', ''),
						'password' => $this->getInput('http_password', '')
					];
				}
				break;

			case ITEM_TYPE_IPMI:
				$data += [
					'key' => $this->getInput('key'),
					'ipmi_sensor' => $this->getInput('ipmi_sensor', ''),
					'interface' => $this->getItemTestInterface($interface_input),
					'host' => [
						'hostid' => $this->host['hostid'],
						'ipmi_authtype' => $this->host['ipmi_authtype'],
						'ipmi_privilege' => $this->host['ipmi_privilege'],
						'ipmi_username' => $this->host['ipmi_username'],
						'ipmi_password' => $this->host['ipmi_password']
					]
				];

				unset($data['interface']['useip'], $data['interface']['interfaceid']);
				break;

			case ITEM_TYPE_SSH:
				$data += [
					'key' => $this->getInput('key'),
					'authtype' => $this->getInput('authtype', ITEM_AUTHTYPE_PASSWORD),
					'params' => $this->getInput('params', ''),
					'interface' => $this->getItemTestInterface($interface_input),
					'username' => $this->getInput('username', ''),
					'password' => $this->getInput('password', '')
				];

				if ($data['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$data += [
						'publickey' => $this->getInput('publickey', ''),
						'privatekey' => $this->getInput('privatekey', '')
					];
				}

				unset($data['interface']['interfaceid'], $data['interface']['useip']);
				break;

			case ITEM_TYPE_TELNET:
				$data += [
					'key' => $this->getInput('key'),
					'params' => $this->getInput('params', ''),
					'publickey' => $this->getInput('publickey', ''),
					'privatekey' => $this->getInput('privatekey', ''),
					'interface' => $this->getItemTestInterface($interface_input)
				];

				unset($data['interface']['interfaceid'], $data['interface']['useip']);
				break;

			case ITEM_TYPE_JMX:
				$data += [
					'key' => $this->getInput('key'),
					'jmx_endpoint' => $this->getInput('jmx_endpoint', ''),
					'username' => $this->getInput('username', ''),
					'password' => $this->getInput('password', '')
				];
				break;

			case ITEM_TYPE_CALCULATED:
				$data += [
					'key' => $this->getInput('key'),
					'params' => $this->getInput('params', ''),
					'value_type' => $this->getInput('value_type'),
					'host' => [
						'host' => $this->host['host']
					]
				];
				break;

			case ITEM_TYPE_SIMPLE:
				$data += [
					'key' => $this->getInput('key'),
					'interface' => $this->getItemTestInterface($interface_input),
					'username' => $this->getInput('username', ''),
					'password' => $this->getInput('password', '')
				];

				unset($data['interface']['useip'], $data['interface']['interfaceid'],  $data['interface']['port']);
				break;
		}

		return $data;
	}

	/**
	 * Check if item belongs to host and select and resolve interface properties. Leave fields empty otherwise.
	 *
	 * @var array $inputs  Stored user input used to overwrite values retrieved from database.
	 *
	 * @param array $interface_data
	 */
	protected function getItemTestInterface(array $inputs) {
		$interface_data = [
			'address' => '',
			'port' => '',
			'interfaceid' => 0,
			'useip' => INTERFACE_USE_DNS
		];

		if (($this->host['status'] == HOST_STATUS_MONITORED || $this->host['status'] == HOST_STATUS_NOT_MONITORED)
				&& in_array($this->item_type, $this->items_require_interface)) {
			$interface = $this->getInput('interfaceid', 0)
				? API::HostInterface()->get([
					'output' => ['hostid', 'type', 'dns', 'ip', 'port', 'main', 'useip'],
					'interfaceids' => $this->getInput('interfaceid'),
					'hostids' => $this->host['hostid']
				])
				: null;

			if ($interface) {
				$interface = CMacrosResolverHelper::resolveHostInterfaces($interface);

				$interface_data = [
					'address' => ($interface[0]['useip'] == INTERFACE_USE_IP)
						? $interface[0]['ip']
						: $interface[0]['dns'],
					'port' => $interface[0]['port'],
					'useip' => $interface[0]['useup'],
					'interfaceid' => $interface[0]['interfaceid']
				];
			}
		}

		foreach ($inputs as $key => $value) {
			$interface_data[$key] = $value;
		}

		return $interface_data;
	}

	/**
	 * Function returns human readable time used for previous time field in item test.
	 *
	 * @return string
	 */
	protected function getPrevTime() {
		$time_change = max($this->getInput('time_change', 1), 1);

		if ($time_change >= SEC_PER_DAY) {
			$n = floor($time_change / SEC_PER_DAY);
			return 'now-'.$n.'d';
		}
		elseif ($time_change >= SEC_PER_HOUR * 5) {
			$n = floor($time_change / SEC_PER_HOUR);
			return 'now-'.$n.'h';
		}
		elseif ($time_change >= SEC_PER_MIN * 5) {
			$n = floor($time_change / SEC_PER_MIN);
			return 'now-'.$n.'m';
		}
		else {
			return 'now-'.$time_change.'s';
		}
	}
}
