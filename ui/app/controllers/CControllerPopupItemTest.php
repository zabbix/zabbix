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
	 * Max-length of input fields that can contain resolved macro values. Used in views for input fields.
	 *
	 * @var int
	 */
	public const INPUT_MAX_LENGTH = 2048;

	/**
	 * Define a set of item types allowed to test and item properties needed to collect for each item type.
	 *
	 * @var array
	 */
	private static $testable_item_types = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_EXTERNAL,
		ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
		ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
	];

	/**
	 * Item value type used if user has not specified one.
	 */
	const ZBX_DEFAULT_VALUE_TYPE = ITEM_VALUE_TYPE_TEXT;

	public const TEST_WITH_SERVER = 0;
	public const TEST_WITH_PROXY = 1;

	/**
	 * Item types requiring interface.
	 *
	 * @var array
	 */
	protected $items_require_interface = [
		ITEM_TYPE_ZABBIX => [
			'address' => true,
			'port' => true
		],
		ITEM_TYPE_IPMI => [
			'address' => true,
			'port' => true
		],
		ITEM_TYPE_SIMPLE => [
			'address' => true,
			'port' => false
		],
		ITEM_TYPE_SNMP => [
			'address' => true,
			'port' => true
		],
		ITEM_TYPE_SSH => [
			'address' => true,
			'port' => false
		],
		ITEM_TYPE_TELNET => [
			'address' => true,
			'port' => false
		]
	];

	/**
	 * Item types with proxy support.
	 *
	 * @var array
	 */
	protected $items_support_proxy = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_EXTERNAL,
		ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX,
		ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER
	];

	/**
	 * Item types with mandatory item key.
	 *
	 * @var array
	 */
	protected $item_types_has_key_mandatory = [ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_IPMI,
		ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_CALCULATED
	];

	/**
	 * Item properties where macros are supported.
	 *
	 * @var array
	 */
	protected $macros_by_item_props = [
		'key' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'params_es' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'params_ap' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'jmx_endpoint' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'url' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'posts' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'http_proxy' => [
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'ssl_cert_file' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'ssl_key_file' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'query_fields' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'headers' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'parameters' => [
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'params_f' => [],
		'script' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'browser_script' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'timeout' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'ipmi_sensor' => [
			'support_user_macros' => false,
			'support_lld_macros' => true
		],
		'snmp_oid' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'username' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'password' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'http_username' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'http_password' => [
			'support_user_macros' => true,
			'support_lld_macros' => true
		]
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
	 * @var array
	 */
	protected $host;

	/**
	 * Is item testable.
	 *
	 * @var bool
	 */
	protected $is_item_testable;

	/**
	 * @var string
	 */
	protected $test_type;

	/**
	 * @var array
	 */
	protected static $preproc_steps_using_prev_value = [ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED,
		ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE
	];

	/**
	 * @var int
	 */
	protected $eol;

	/**
	 * Get testable item types based on host type.
	 *
	 * @param string $hostid
	 *
	 * @return array
	 */
	public static function getTestableItemTypes(string $hostid = '0'): array {
		if ($hostid != 0 && self::isItemTypeTestable($hostid)) {
			self::$testable_item_types[] = ITEM_TYPE_IPMI;
		}

		return self::$testable_item_types;
	}

	/**
	 * Function checks if item type can be tested depending on what type of host it belongs to.
	 *
	 * @param string $hostid
	 *
	 * @return bool
	 */
	protected static function isItemTypeTestable(string $hostid): bool {
		$ret = (bool) API::Template()->get([
			'countOutput' => true,
			'templateids' => [$hostid]
		]);

		return !$ret;
	}

	protected function checkPermissions() {
		$ret = ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);

		/*
		 * Preprocessing test can be done from mass-update section so host is non mandatory but if it is used, it must
		 * be editable.
		 */
		$hostid = $this->getInput('hostid', 0);

		if ($ret && $hostid != 0) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'host', 'name', 'monitored_by', 'proxyid', 'assigned_proxyid', 'status',
					'maintenance_status', 'maintenance_type', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
					'ipmi_password', 'tls_subject', 'tls_issuer', 'tls_connect'
				],
				'hostids' => [$hostid],
				'editable' => true
			]);

			if ($hosts) {
				if ($hosts[0]['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
					$hosts[0]['proxyid'] = $hosts[0]['assigned_proxyid'];
				}

				unset($hosts[0]['monitored_by'], $hosts[0]['assigned_proxyid']);
			}
			else {
				$hosts = API::Template()->get([
					'output' => ['templateid', 'host', 'name', 'status'],
					'templateids' => [$hostid],
					'editable' => true
				]);

				$hosts[0] = CArrayHelper::renameKeys($hosts[0], ['templateid' => 'hostid']);
			}

			$this->host = reset($hosts);

			return (bool) $this->host;
		}

		return $ret;
	}

	/**
	 * Function returns array of item specific properties used for item testing.
	 *
	 * @param array $input       Stored user input used to overwrite values retrieved from database.
	 * @param bool  $for_server  Whether need to add to result additional properties used only for connection with
	 *                           Zabbix server.
	 *
	 * @return array
	 */
	protected function getItemTestProperties(array $input, bool $for_server = false): array {
		$data_host = [];
		$data_item = [
			'value_type' => (int) $input['value_type']
		];

		if (!$this->is_item_testable) {
			return ['item' => $data_item, 'host' => []];
		}

		$data_item['type'] = $this->item_type;

		if (array_key_exists('itemid', $input)) {
			$data_item['itemid'] = $input['itemid'];
		}

		switch ($this->item_type) {
			case ITEM_TYPE_ZABBIX:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'timeout']);
				$data_host += $this->getInterface($input, ['useip', 'interfaceid', 'ip', 'dns']);

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data_host += CArrayHelper::getByKeysStrict($this->host,
						['tls_issuer', 'tls_connect', 'tls_subject']
					);

					if ($for_server && $this->host['tls_connect'] == HOST_ENCRYPTION_PSK) {
						$hosts = API::Host()->get([
							'output' => ['tls_psk_identity', 'tls_psk'],
							'hostids' => $this->host['hostid'],
							'editable' => true
						]);

						if ($hosts) {
							$data_host['tls_psk_identity'] = $hosts[0]['tls_psk_identity'];
							$data_host['tls_psk'] = $hosts[0]['tls_psk'];
						}
					}
				}
				break;

			case ITEM_TYPE_SNMP:
				if (array_key_exists('flags', $input)) {
					$item_flag = $input['flags'];
				}
				else {
					$items = array_key_exists('itemid', $input)
						? API::Item()->get([
							'output' => ['flags'],
							'itemids' => $input['itemid']
						])
						: [];

					if ($items) {
						$item_flag = $items[0]['flags'];
					}
					else {
						$item_flags = [
							self::ZBX_TEST_TYPE_ITEM => ZBX_FLAG_DISCOVERY_NORMAL,
							self::ZBX_TEST_TYPE_ITEM_PROTOTYPE => ZBX_FLAG_DISCOVERY_PROTOTYPE,
							self::ZBX_TEST_TYPE_LLD => ZBX_FLAG_DISCOVERY_RULE
						];
						$item_flag = $item_flags[$this->getInput('test_type')];
					}
				}

				$data_item += ['flags' => $item_flag] + CArrayHelper::getByKeys($input, ['snmp_oid', 'timeout']);
				$data_host += $this->getInterface($input, ['ip', 'dns']);
				$data_host['host'] = $this->host['host'];
				break;

			case ITEM_TYPE_INTERNAL:
				$data_item += ['key' => $input['key']];
				$data_host['hostid'] = $this->host['hostid'];

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data_host += CArrayHelper::getByKeysStrict($this->host,
						['maintenance_status', 'maintenance_type']
					);
				}
				break;

			case ITEM_TYPE_EXTERNAL:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'timeout']);
				break;

			case ITEM_TYPE_DB_MONITOR:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'params_ap', 'username', 'password', 'timeout']);
				break;

			case ITEM_TYPE_HTTPAGENT:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'http_authtype', 'follow_redirects', 'headers',
					'http_proxy', 'output_format', 'posts', 'post_type', 'query_fields', 'request_method',
					'retrieve_mode', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'status_codes', 'timeout',
					'url', 'verify_host', 'verify_peer'
				]) + [
					'http_authtype' => ZBX_HTTP_AUTH_NONE,
					'follow_redirects' => HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF,
					'output_format' => HTTPCHECK_STORE_RAW,
					'post_type' => ZBX_POSTTYPE_RAW,
					'request_method' => HTTPCHECK_REQUEST_GET,
					'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT,
					'verify_host' => ZBX_HTTP_VERIFY_HOST_OFF,
					'verify_peer' => ZBX_HTTP_VERIFY_PEER_OFF
				];

				if ($data_item['http_authtype'] != ZBX_HTTP_AUTH_NONE) {
					$data_item += CArrayHelper::getByKeys($input, ['http_username', 'http_password']);
				}
				break;

			case ITEM_TYPE_IPMI:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'ipmi_sensor']);
				$data_host += $this->getInterface($input, ['useip', 'interfaceid', 'ip', 'dns']);
				$data_host['hostid'] = $this->host['hostid'];

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data_host += CArrayHelper::getByKeysStrict($this->host, ['ipmi_authtype', 'ipmi_privilege',
						'ipmi_username', 'ipmi_password'
					]);
				}
				break;

			case ITEM_TYPE_SSH:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'authtype', 'params_es', 'username', 'password',
					'timeout'
				]) + [
					'authtype' => ITEM_AUTHTYPE_PASSWORD,
					'params_es' => ITEM_AUTHTYPE_PASSWORD
				];
				$data_host += $this->getInterface($input);

				if ($data_item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$data_item += CArrayHelper::getByKeys($input, ['publickey', 'privatekey']);
				}
				break;

			case ITEM_TYPE_TELNET:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'params_es', 'username', 'password', 'timeout']);
				$data_host += $this->getInterface($input);
				break;

			case ITEM_TYPE_JMX:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'jmx_endpoint', 'username', 'password']);
				break;

			case ITEM_TYPE_CALCULATED:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'params_f']);
				$data_host['host'] = $this->host['host'];
				break;

			case ITEM_TYPE_SIMPLE:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'username', 'password', 'timeout']);
				$data_host += $this->getInterface($input, ['useip', 'interfaceid', 'ip', 'dns', 'port']);
				break;

			case ITEM_TYPE_SCRIPT:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'parameters', 'script', 'timeout']);
				break;

			case ITEM_TYPE_BROWSER:
				$data_item += CArrayHelper::getByKeys($input, ['key', 'parameters', 'browser_script', 'timeout']);
				break;
		}

		if (in_array($this->item_type, $this->items_support_proxy)) {
			if (array_key_exists('data', $input) && array_key_exists('test_with', $input['data'])) {
				$test_with = $input['data']['test_with'];
			}
			elseif (array_key_exists('test_with', $input)) {
				$test_with = $input['test_with'];
			}
			else {
				$test_with = self::TEST_WITH_SERVER;
			}

			$data_host['proxyid'] = 0;

			if ($test_with == self::TEST_WITH_PROXY) {
				if (array_key_exists('data', $input) && array_key_exists('proxyid', $input['data'])) {
					$data_host['proxyid'] = $input['data']['proxyid'];
				}
				elseif (array_key_exists('proxyid', $input)) {
					$data_host['proxyid'] = $input['proxyid'];
				}
				elseif ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data_host['proxyid'] = $this->host['proxyid'];
				}
			}
		}

		return ['item' => $data_item, 'host' => $data_host];
	}

	private function getInterface(array $input, array $exclude_keys = []): array {
		$interface_input = [];

		if (array_key_exists('data', $input)) {
			$input['data'] = CArrayHelper::renameKeys($input['data'], ['interface_details' => 'details']);
			$interface_input += CArrayHelper::getByKeys($input['data'], ['port', 'address', 'details']);
		}

		if (array_key_exists('interface', $input)) {
			$interface_input += CArrayHelper::getByKeys($input['interface'],
				['interfaceid', 'useip', 'port', 'address', 'details']
			);
		}

		$interface_input += CArrayHelper::getByKeys($input, ['interfaceid', 'address']) + ['interfaceid' => 0];

		return ['interface' => array_diff_key($this->getHostInterface($interface_input), array_flip($exclude_keys))];
	}

	/**
	 * Check if item belongs to host and select and resolve interface properties. Leave fields empty otherwise.
	 *
	 * @param array $inputs  Stored user input used to overwrite values retrieved from database.
	 *
	 * @return array $interface_data
	 */
	protected function getHostInterface(array $inputs) {
		$interface_data = [
			'address' => '',
			'port' => '',
			'interfaceid' => 0,
			'type' => INTERFACE_TYPE_UNKNOWN,
			'ip' => '',
			'dns' => '',
			'useip' => INTERFACE_USE_DNS,
			'details' => [
				'community' => '',
				'version' => SNMP_V2C,
				'securityname' => '',
				'securitylevel' => ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
				'authpassphrase' => '',
				'privpassphrase' => '',
				'authprotocol' => ITEM_SNMPV3_AUTHPROTOCOL_MD5,
				'privprotocol' => ITEM_SNMPV3_PRIVPROTOCOL_DES,
				'contextname' => '',
				'max_repetitions' => '10'
			]
		];

		if ($this->item_type != ITEM_TYPE_SNMP) {
			unset($interface_data['details'], $inputs['details']);
		}

		// Get values from database; resolve macros.
		if (($this->host['status'] == HOST_STATUS_MONITORED || $this->host['status'] == HOST_STATUS_NOT_MONITORED)
				&& array_key_exists('interfaceid', $inputs)) {
			$output = ['hostid', 'type', 'dns', 'ip', 'port', 'main', 'useip'];
			$interfaces = [];

			if ($this->item_type == ITEM_TYPE_SNMP) {
				$output[] = 'details';
			}

			$item_type_interface = itemTypeInterface($this->item_type);

			if ($item_type_interface == INTERFACE_TYPE_OPT && $inputs['interfaceid'] == 0) {
				$item_type_interface = false;
			}

			if ($item_type_interface === false) {
				$host_interfaces = API::HostInterface()->get([
					'output' => $output,
					'hostids' => $this->host['hostid'],
					'filter' => ['main' => INTERFACE_PRIMARY]
				]);
				$host_interfaces = zbx_toHash($host_interfaces, 'type');

				foreach (CItem::INTERFACE_TYPES_BY_PRIORITY as $interface_type) {
					if (array_key_exists($interface_type, $host_interfaces)) {
						$interfaces[] = $host_interfaces[$interface_type];
						break;
					}
				}
			}
			else {
				$interfaces = API::HostInterface()->get([
					'output' => $output,
					'interfaceids' => $inputs['interfaceid'],
					'hostids' => $this->host['hostid']
				]);
			}

			if (count($interfaces) != 0) {
				$interfaces = CMacrosResolverHelper::resolveHostInterfaces($interfaces);
				$interface_data = ($this->item_type == ITEM_TYPE_SNMP)
					? ['details' => $interfaces[0]['details'] + $interface_data['details']]
					: [];

				$interface_data += [
					'address' => ($interfaces[0]['useip'] == INTERFACE_USE_IP)
						? $interfaces[0]['ip']
						: $interfaces[0]['dns'],
					'port' => $interfaces[0]['port'],
					'useip' => $interfaces[0]['useip'],
					'type' => $interfaces[0]['type'],
					'ip' => $interfaces[0]['ip'],
					'dns' => $interfaces[0]['dns'],
					'interfaceid' => $interfaces[0]['interfaceid']
				];
			}
		}

		if (in_array($this->item_type, [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
			return $interface_data;
		}

		// Apply client side cache.
		foreach ($inputs as $key => $value) {
			if (is_array($value)) {
				$interface_data[$key] = $value + $interface_data[$key];
			}
			else {
				$interface_data[$key] = $value;
			}
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

	/**
	 * Function to unset unspecified values before sending 'get value' request to server.
	 *
	 * @param array $data  Data array containing all parameters prepared to be sent to server.
	 *
	 * @return array
	 */
	protected function unsetEmptyValues(array $data) {
		foreach ($data as $key => $value) {
			if (in_array($key, ['host', 'item', 'options']) && is_array($value)) {
				$data[$key] = $this->unsetEmptyValues($value);

				if (!$data[$key]) {
					unset($data[$key]);
				}
			}
			elseif ($key === 'interface' && $this->item_type == ITEM_TYPE_SNMP) {
				if ($data['interface']['details']['version'] == SNMP_V3) {
					$unrelated_details = [];

					if ($data['interface']['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
						$unrelated_details = ['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase'];
					}
					elseif ($data['interface']['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
						$unrelated_details = ['privprotocol', 'privpassphrase'];
					}

					array_unshift($unrelated_details, 'community');
				}
				else {
					$unrelated_details = ['contextname', 'securityname', 'securitylevel', 'authprotocol',
						'authpassphrase', 'privprotocol', 'privpassphrase'
					];
				}

				unset($data['interface']['type']);
				$data['interface']['details'] = array_diff_key($data['interface']['details'],
					array_flip($unrelated_details)
				);
			}
			elseif (in_array($key, ['query_fields', 'headers', 'parameters'])) {
				if (!$value) {
					unset($data[$key]);
				}
			}
			elseif ($value === '' || $value === null) {
				unset($data[$key]);
			}
		}

		return $data;
	}

	/**
	 * Function returns array containing values for each of supported macros.
	 *
	 * @return array
	 */
	protected function getSupportedMacros(array $inputs) {
		$interface = $this->getHostInterface(['interfaceid' => $inputs['interfaceid']]);

		$macros = [
			'host' => [
				'{HOSTNAME}' => $this->host['host'],
				'{HOST.HOST}' => $this->host['host'],
				'{HOST.NAME}' => $this->host['name']
			],
			'interface' => [
				'{HOST.IP}' => $interface['ip'],
				'{IPADDRESS}' => $interface['ip'],
				'{HOST.DNS}' => $interface['dns'],
				'{HOST.CONN}' => $interface['address'],
				'{HOST.PORT}' => $interface['port']
			],
			'item' => [
				'{ITEM.ID}' => (array_key_exists('itemid', $inputs) && $inputs['itemid'])
					? $inputs['itemid']
					: UNRESOLVED_MACRO_STRING,
				'{ITEM.KEY}' => array_key_exists('key', $inputs) ? $inputs['key'] : UNRESOLVED_MACRO_STRING,
				'{ITEM.KEY.ORIG}' => array_key_exists('key', $inputs) ? $inputs['key'] : UNRESOLVED_MACRO_STRING
			]
		];

		if (array_key_exists('key', $inputs) && strstr($inputs['key'], '{') !== false) {
			$usermacros = CMacrosResolverHelper::extractItemTestMacros([
				'steps' => [],
				'delay' => '',
				'supported_macros' => array_diff_key($this->macros_by_item_props['key'],
					['support_user_macros' => true, 'support_lld_macros' => true]
				),
				'support_lldmacros' => ($this->test_type == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
				'texts_support_macros' => [$inputs['key']],
				'texts_support_lld_macros' => [$inputs['key']],
				'texts_support_user_macros' => [$inputs['key']],
				'hostid' => $this->host ? $this->host['hostid'] : 0,
				'macros_values' => array_intersect_key($macros, $this->macros_by_item_props['key'])
			]);

			foreach ($usermacros['macros'] as $macro => $value) {
				$macros['item']['{ITEM.KEY}'] = str_replace($macro, $value, $macros['item']['{ITEM.KEY}']);
			}
		}

		return $macros;
	}

	/**
	 * Resolve macros used in preprocessing step parameter fields.
	 *
	 * @param array $steps  Steps from item test input form.
	 *
	 * @return array
	 */
	protected function resolvePreprocessingStepMacros(array $steps) {
		// Resolve macros used in parameter fields.
		$macros_posted = $this->getInput('macros', []);
		$macros_types = ($this->test_type == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE)
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];

		foreach ($steps as &$step) {
			/*
			 * Values received from user input form may be transformed so we must remove redundant "\r" before
			 * sending data to Zabbix server.
			 */
			$step['params'] = str_replace("\r\n", "\n", $step['params']);

			// Resolve macros in parameter fields before send data to Zabbix server.
			foreach (['params', 'error_handler_params'] as $field) {
				$matched_macros = CMacrosResolverGeneral::getMacroPositions($step[$field], $macros_types);

				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					$macro_value = array_key_exists($macro, $macros_posted)
						? $macros_posted[$macro]
						: '';

					$step[$field] = substr_replace($step[$field], $macro_value, $pos, strlen($macro));
				}
			}
		}
		unset($step);

		return $steps;
	}

	/**
	 * Resolve macros used in the calculates item formula.
	 *
	 * @param string $formula        Calculated item formula.
	 * @param array  $macros_posted  Macros.
	 *
	 * @return string
	 */
	private function resolveCalcFormulaMacros(string $formula, array $macros_posted): string {
		if (!$macros_posted) {
			return $formula;
		}

		$expression_parser = new CExpressionParser([
			'usermacros' => true,
			'lldmacros' => ($this->test_type == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE),
			'calculated' => true,
			'host_macro' => true,
			'empty_host' => true
		]);

		if ($expression_parser->parse($formula) != CParser::PARSE_SUCCESS) {
			// Cannot parse a calculated item formula. Return as is.
			return $formula;
		}

		$expression = [];
		$pos_left = 0;

		$tokens = $expression_parser->getResult()->getTokensOfTypes([
			CExpressionParserResult::TOKEN_TYPE_USER_MACRO,
			CExpressionParserResult::TOKEN_TYPE_LLD_MACRO,
			CExpressionParserResult::TOKEN_TYPE_STRING,
			CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION
		]);

		foreach ($tokens as $token) {
			if ($pos_left != $token['pos']) {
				$expression[] = substr($formula, $pos_left, $token['pos'] - $pos_left);
			}
			$pos_left = $token['pos'] + $token['length'];

			switch ($token['type']) {
				case CExpressionParserResult::TOKEN_TYPE_USER_MACRO:
				case CExpressionParserResult::TOKEN_TYPE_LLD_MACRO:
					$expression[] = array_key_exists($token['match'], $macros_posted)
						? CExpressionParser::quoteString($macros_posted[$token['match']], false)
						: $token['match'];
					break;

				case CExpressionParserResult::TOKEN_TYPE_STRING:
					$string = strtr(CExpressionParser::unquoteString($token['match']), $macros_posted);
					$expression[] = CExpressionParser::quoteString($string, false, true);
					break;

				case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
					$pos_left = $token['pos'];

					foreach ($token['data']['parameters'] as $parameter) {
						if ($pos_left != $parameter['pos']) {
							$expression[] = substr($formula, $pos_left, $parameter['pos'] - $pos_left);
						}
						$pos_left = $parameter['pos'] + $parameter['length'];

						switch ($parameter['type']) {
							case CHistFunctionParser::PARAM_TYPE_QUERY:
								$pos_left = $parameter['pos'];

								foreach ($parameter['data']['filter']['tokens'] as $filter_token) {
									if ($pos_left != $filter_token['pos']) {
										$expression[] = substr($formula, $pos_left, $filter_token['pos'] - $pos_left);
									}
									$pos_left = $filter_token['pos'] + $filter_token['length'];

									switch ($filter_token['type']) {
										case CFilterParser::TOKEN_TYPE_USER_MACRO:
										case CFilterParser::TOKEN_TYPE_LLD_MACRO:
											$string = strtr($filter_token['match'], $macros_posted);
											$expression[] = CFilterParser::quoteString($string);
											break;

										case CFilterParser::TOKEN_TYPE_STRING:
											$string = strtr(CFilterParser::unquoteString($filter_token['match']),
												$macros_posted
											);
											$expression[] = CFilterParser::quoteString($string);
											break;

										case CFilterParser::TOKEN_TYPE_KEYWORD:
										case CFilterParser::TOKEN_TYPE_OPERATOR:
											$expression[] = $filter_token['match'];
											break;
									}
								}
								break;

							case CHistFunctionParser::PARAM_TYPE_PERIOD:
								$string = strtr($parameter['match'], $macros_posted);
								$expression[] = $string;
								break;

							case CHistFunctionParser::PARAM_TYPE_QUOTED:
								$string = strtr(CFilterParser::unquoteString($parameter['match']), $macros_posted);
								$expression[] = CFilterParser::quoteString($string);
								break;

							case CHistFunctionParser::PARAM_TYPE_UNQUOTED:
								$string = strtr($parameter['match'], $macros_posted);
								$expression[] = CFilterParser::quoteString($string);
								break;
						}
					}
					break;
			}
		}

		if ($pos_left != strlen($formula)) {
			$expression[] = substr($formula, $pos_left);
		}

		return implode('', $expression);
	}

	/**
	 * Resolve macros used in item property fields.
	 *
	 * @param array $inputs  Item fields potentially having supported macros.
	 *
	 * @return array
	 */
	protected function resolveItemPropertyMacros(array $inputs) {
		// Resolve macros used in parameter fields.
		$macros_posted = $this->getInput('macros', []);

		foreach (array_keys($this->macros_by_item_props) as $field) {
			if (!array_key_exists($field, $inputs)) {
				continue;
			}

			// Special processing for calculated item formula.
			if ($field === 'params_f') {
				$inputs[$field] = $this->resolveCalcFormulaMacros($inputs[$field], $macros_posted);
				continue;
			}

			// Construct array of supported macros.
			$types = [
				'usermacros' => true,
				'macros_n' => []
			];

			if ($this->test_type == self::ZBX_TEST_TYPE_ITEM_PROTOTYPE) {
				$types += ['lldmacros' => true];
			}

			foreach (['host', 'interface', 'item'] as $type) {
				if (array_key_exists($type, $this->macros_by_item_props[$field])) {
					$types['macros_n'] = array_merge($types['macros_n'], $this->macros_by_item_props[$field][$type]);
				}
			}

			// Get strings to resolve and types of supported macros.
			if ($field === 'query_fields' || $field === 'headers' || $field === 'parameters') {
				if (!array_key_exists($field, $inputs) || !$inputs[$field]) {
					continue;
				}

				foreach ($inputs[$field] as &$entry) {
					if (strpos($entry['name'], '{') !== false) {
						$matched_macros = CMacrosResolverGeneral::getMacroPositions($entry['name'], $types);

						foreach (array_reverse($matched_macros, true) as $pos => $macro) {
							$macro_value = array_key_exists($macro, $macros_posted)
								? $macros_posted[$macro]
								: '';

							$entry['name'] = substr_replace($entry['name'], $macro_value, $pos, strlen($macro));
						}
					}

					if (strpos($entry['value'], '{') !== false) {
						$matched_macros = CMacrosResolverGeneral::getMacroPositions($entry['value'], $types);

						foreach (array_reverse($matched_macros, true) as $pos => $macro) {
							$macro_value = array_key_exists($macro, $macros_posted)
								? $macros_posted[$macro]
								: '';

							$entry['value'] = substr_replace($entry['value'], $macro_value, $pos, strlen($macro));
						}
					}
				}
				unset($entry);
			}
			elseif (strstr($inputs[$field], '{') !== false) {
				if ($field === 'key') {
					$inputs[$field] = CMacrosResolverGeneral::resolveItemKeyMacros($inputs[$field], $macros_posted, $types);
				}
				else {
					$matched_macros = CMacrosResolverGeneral::getMacroPositions($inputs[$field], $types);

					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						$macro_value = array_key_exists($macro, $macros_posted)
							? $macros_posted[$macro]
							: '';

						if ($inputs['type'] == ITEM_TYPE_HTTPAGENT && $field === 'posts') {
							if ($inputs['post_type'] == ZBX_POSTTYPE_JSON && !is_numeric($macro_value)) {
								$macro_value = json_encode($macro_value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
								// Remove " wrapping.
								$macro_value = substr($macro_value, 1, -1);
							}
							elseif ($inputs['post_type'] == ZBX_POSTTYPE_XML) {
								$macro_value = htmlentities($macro_value);
							}
						}

						$inputs[$field] = substr_replace($inputs[$field], $macro_value, $pos, strlen($macro));
					}
				}
			}
		}

		// Resolve interface details (SNMP) macros separately.
		if (array_key_exists('interface', $inputs) && array_key_exists('details', $inputs['interface'])) {
			foreach ($inputs['interface']['details'] as &$field) {
				if (strstr($field, '{') !== false) {
					$matched_macros = CMacrosResolverGeneral::getMacroPositions($field, ['usermacros' => true]);

					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						// If matching macro is not found, return unresolved macro string.
						$macro_value = array_key_exists($macro, $macros_posted)
							? $macros_posted[$macro]
							: $macro;

						$field = substr_replace($field, $macro_value, $pos, strlen($macro));
					}
				}
			}
			unset($field);
		}

		return $inputs;
	}

	/**
	 * Get single input parameter. Converts value fields to specified newline.
	 *
	 * @return var
	 */
	public function getInput($var, $default = null) {
		$value = parent::getInput($var, $default);
		if ($var === 'value' || $var === 'prev_value') {
			$value = str_replace("\r\n", "\n", $value);

			if ($this->eol == ZBX_EOL_CRLF) {
				$value = str_replace("\n", "\r\n", $value);
			}
		}

		return $value;
	}

	/**
	 * Validates interface object in context of current item type.
	 *
	 * @param array  $interface
	 * @param string $interface['address']               (optional)
	 * @param string $interface['port']                  (optional)
	 * @param array  $interface['details']               (optional)
	 * @param int    $interface['details']['version']
	 * @param string $interface['details']['community']  (optional)
	 *
	 * @return bool
	 */
	final protected function validateInterface(array $interface): bool {
		if ($this->item_type == ITEM_TYPE_SNMP) {
			if (($interface['details']['version'] == SNMP_V1 || $interface['details']['version'] == SNMP_V2C)
					&& (!array_key_exists('community', $interface['details'])
						|| $interface['details']['community'] === '')) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('SNMP community'), _('cannot be empty')));

				return false;
			}

			if ($interface['details']['version'] == SNMP_V2C || $interface['details']['version'] == SNMP_V3) {
				if (!array_key_exists('max_repetitions', $interface['details'])
						|| $interface['details']['max_repetitions'] === '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Max repetition count'), _('cannot be empty')));

					return false;
				}

				if (!is_numeric($interface['details']['max_repetitions'])) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Max repetition count'), _('a numeric value is expected')));

					return false;
				}

				if ($interface['details']['max_repetitions'] < 1) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Max repetition count'), _s('value must be no less than "%1$s"', 1)));

					return false;
				}

				if ($interface['details']['max_repetitions'] > ZBX_MAX_INT32) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Max repetition count'), _s('value must be no greater than "%1$s"', ZBX_MAX_INT32)));

					return false;
				}
			}
		}

		if ($this->items_require_interface[$this->item_type]['address']
				&& (!array_key_exists('address', $interface) || $interface['address'] === '')) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Host address'), _('cannot be empty')));

			return false;
		}

		if ($this->items_require_interface[$this->item_type]['port']
				&& (!array_key_exists('port', $interface) || $interface['port'] === '')) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Port'), _('cannot be empty')));

			return false;
		}

		return true;
	}

	/**
	 * Gathers (non-empty) properties needed for server to collect item value.
	 *
	 * @return array $data
	 * @return array $data['item]
	 * @return array $data['host]
	 */
	protected function prepareTestData(): array {
		$data = $this->getItemTestProperties($this->getInputAll(), true);
		$data['item'] = $this->resolveItemPropertyMacros($data['item']);

		if ($data['item']['type'] == ITEM_TYPE_CALCULATED) {
			$data['host']['hostid'] = $this->getInput('hostid');
		}

		// Rename form fields according to API conventions.
		$data['item'] = CArrayHelper::renameKeys($data['item'], [
			'params_ap' => 'params',
			'params_es' => 'params',
			'params_f' => 'params',
			'script' => 'params',
			'browser_script' => 'params',
			'http_username' => 'username',
			'http_password' => 'password',
			'http_authtype' => 'authtype',
			'item_type' => 'type'
		]);

		// Only non-empty fields are sent to server, except item status_codes.
		$data = $this->unsetEmptyValues($data);

		// Server defaults to checking status code is 200 if field not present. Turn off code check if unspecified.
		if ($this->item_type == ITEM_TYPE_HTTPAGENT) {
			$data['item'] += ['status_codes' => ''];

			self::transformHttpFields($data['item']);
		}

		if (array_key_exists('parameters', $data['item'])) {
			$data['item']['parameters'] = array_column($data['item']['parameters'], 'value', 'name');
		}

		return $data;
	}

	/**
	 * @param array $item
	 */
	protected static function transformHttpFields(array &$item): void {
		if (array_key_exists('query_fields', $item)) {
			foreach ($item['query_fields'] as &$query_field) {
				$query_field = [$query_field['name'] => $query_field['value']];
			}
			unset($query_field);

			$item['query_fields'] = json_encode(array_values($item['query_fields']), JSON_UNESCAPED_UNICODE);
		}

		if (array_key_exists('headers', $item)) {
			foreach ($item['headers'] as &$header) {
				$header = $header['name'].': '.$header['value'];
			}
			unset($header);

			$item['headers'] = implode("\r\n", $item['headers']);
		}
	}
}
