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
		ITEM_TYPE_CALCULATED, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
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
		ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
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
			'host' => ['{HOSTNAME}', '{HOST.HOST}', '{HOST.NAME}'],
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}', '{HOST.PORT}'],
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
			'interface' => ['{HOST.IP}', '{IPADDRESS}', '{HOST.DNS}', '{HOST.CONN}'],
			'item' => ['{ITEM.ID}', '{ITEM.KEY.ORIG}', '{ITEM.KEY}'],
			'support_user_macros' => true,
			'support_lld_macros' => true
		],
		'params_f' => [],
		'script' => [
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
	 * @var object
	 */
	protected $preproc_item;

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
				'output' => ['hostid', 'host', 'name', 'status', 'proxy_hostid', 'tls_subject', 'maintenance_status',
					'maintenance_type', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password',
					'tls_issuer', 'tls_connect'
				],
				'hostids' => [$hostid],
				'editable' => true
			]);

			if (!$hosts) {
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
	 * Function returns instance of item, item prototype or discovery rule class.
	 *
	 * @param int $test_type
	 *
	 * @return CItem|CItemPrototype|CDiscoveryRule
	 */
	protected static function getPreprocessingItemClassInstance($test_type) {
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
	 * @param array $input       Stored user input used to overwrite values retrieved from database.
	 * @param bool  $for_server  Whether need to add to result additional properties used only for connection with
	 *                           Zabbix server.
	 *
	 * @return array
	 */
	protected function getItemTestProperties(array $input, bool $for_server = false) {
		$data = [
			'value_type' => $input['value_type']
		];

		if (!$this->is_item_testable) {
			return $data;
		}

		$data['type'] = $this->item_type;

		if (array_key_exists('itemid', $input)) {
			$data['itemid'] = $input['itemid'];
		}

		if (array_key_exists('interface', $input) && array_key_exists('interfaceid', $input['interface'])) {
			$interface_input['interfaceid'] = $input['interface']['interfaceid'];
		}
		elseif (array_key_exists('interfaceid', $input)) {
			$interface_input['interfaceid'] = $input['interfaceid'];
		}
		else {
			$interface_input['interfaceid'] = 0;
		}

		if (array_key_exists('interface', $input) && array_key_exists('useip', $input['interface'])) {
			$interface_input['useip'] = $input['interface']['useip'];
		}

		if (array_key_exists('data', $input) && array_key_exists('port', $input['data'])) {
			$interface_input['port'] = $input['data']['port'];
		}
		elseif (array_key_exists('interface', $input) && array_key_exists('port', $input['interface'])) {
			$interface_input['port'] = $input['interface']['port'];
		}

		if (array_key_exists('data', $input) && array_key_exists('address', $input['data'])) {
			$interface_input['address'] = $input['data']['address'];
		}
		elseif (array_key_exists('interface', $input) && array_key_exists('address', $input['interface'])) {
			$interface_input['address'] = $input['interface']['address'];
		}
		elseif (array_key_exists('address', $input)) {
			$interface_input['address'] = $input['address'];
		}

		if (array_key_exists('data', $input) && array_key_exists('interface_details', $input['data'])
				&& is_array($input['data']['interface_details'])) {
			$interface_input['details'] = $input['data']['interface_details'];
		}
		elseif (array_key_exists('interface', $input) && array_key_exists('details', $input['interface'])) {
			$interface_input['details'] = $input['interface']['details'];
		}

		// Set proxy.
		if (in_array($this->item_type, $this->items_support_proxy)) {
			if (array_key_exists('data', $input) && array_key_exists('proxy_hostid', $input['data'])) {
				$data['proxy_hostid'] = $input['data']['proxy_hostid'];
			}
			elseif (array_key_exists('proxy_hostid', $input)) {
				$data['proxy_hostid'] = $input['proxy_hostid'];
			}
			elseif (array_key_exists('proxy_hostid', $this->host)) {
				$data['proxy_hostid'] = $this->host['proxy_hostid'];
			}
			else {
				$data['proxy_hostid'] = 0;
			}
		}

		switch ($this->item_type) {
			case ITEM_TYPE_ZABBIX:
				$data += [
					'key' => array_key_exists('key', $input) ? $input['key'] : null,
					'interface' => $this->getHostInterface($interface_input)
				];

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data['host'] = [
						'tls_issuer' => $this->host['tls_issuer'],
						'tls_connect' => $this->host['tls_connect'],
						'tls_subject' => $this->host['tls_subject']
					];

					if ($for_server && $this->host['tls_connect'] == HOST_ENCRYPTION_PSK) {
						$hosts = API::Host()->get([
							'output' => ['tls_psk_identity', 'tls_psk'],
							'hostids' => $this->host['hostid'],
							'editable' => true
						]);
						$host = reset($hosts);

						$data['host']['tls_psk_identity'] = $host['tls_psk_identity'];
						$data['host']['tls_psk'] = $host['tls_psk'];
					}
				}

				unset($data['interface']['useip'], $data['interface']['interfaceid'], $data['interface']['ip'],
					$data['interface']['dns']
				);
				break;

			case ITEM_TYPE_SNMP:
				if (!array_key_exists('flags', $input)) {
					$items = (array_key_exists('itemid', $input))
						? API::Item()->get([
							'output' => ['flags'],
							'itemids' => $input['itemid']
						])
						: [];

					if ($items) {
						$item_flag = $items[0]['flags'];
					}
					else {
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
					}
				}
				else {
					$item_flag = $input['flags'];
				}

				$data += [
					'snmp_oid' => array_key_exists('snmp_oid', $input) ? $input['snmp_oid'] : null,
					'flags' => $item_flag,
					'host' => [
						'host' => $this->host['host']
					],
					'interface' => $this->getHostInterface($interface_input)
				];

				unset($data['interface']['ip'], $data['interface']['dns']);
				break;

			case ITEM_TYPE_INTERNAL:
				$data += [
					'key' => $input['key'],
					'host' => [
						'hostid' => $this->host['hostid']
					]
				];

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data['host'] += [
						'maintenance_status' => $this->host['maintenance_status'],
						'maintenance_type' => $this->host['maintenance_type']
					];
				}
				break;

			case ITEM_TYPE_EXTERNAL:
				$data += [
					'key' => $input['key']
				];
				break;

			case ITEM_TYPE_DB_MONITOR:
				$data += [
					'key' => $input['key'],
					'params_ap' => array_key_exists('params_ap', $input) ? $input['params_ap'] : null,
					'username' => array_key_exists('username', $input) ? $input['username'] : null,
					'password' => array_key_exists('password', $input) ? $input['password'] : null
				];
				break;

			case ITEM_TYPE_HTTPAGENT:
				$data += [
					'key' => $input['key'],
					'http_authtype' => array_key_exists('http_authtype', $input)
						? $input['http_authtype']
						: HTTPTEST_AUTH_NONE,
					'follow_redirects' => array_key_exists('follow_redirects', $input) ? $input['follow_redirects'] : 0,
					'headers' => array_key_exists('headers', $input) ? $input['headers'] : [],
					'http_proxy' => array_key_exists('http_proxy', $input) ? $input['http_proxy'] : null,
					'output_format' => array_key_exists('output_format', $input) ? $input['output_format'] : 0,
					'posts' => array_key_exists('posts', $input) ? $input['posts'] : null,
					'post_type' => array_key_exists('post_type', $input) ? $input['post_type'] : ZBX_POSTTYPE_RAW,
					'query_fields' => array_key_exists('query_fields', $input) ? $input['query_fields'] : [],
					'request_method' => array_key_exists('request_method', $input)
						? $input['request_method']
						: HTTPCHECK_REQUEST_GET,
					'retrieve_mode' => array_key_exists('retrieve_mode', $input)
						? $input['retrieve_mode']
						: HTTPTEST_STEP_RETRIEVE_MODE_CONTENT,
					'ssl_cert_file' => array_key_exists('ssl_cert_file', $input) ? $input['ssl_cert_file'] : null,
					'ssl_key_file' => array_key_exists('ssl_key_file', $input) ? $input['ssl_key_file'] : null,
					'ssl_key_password' => array_key_exists('ssl_key_password', $input)
						? $input['ssl_key_password']
						: null,
					'status_codes' => array_key_exists('status_codes', $input) ? $input['status_codes'] : null,
					'timeout' => array_key_exists('timeout', $input) ? $input['timeout'] : null,
					'url' => array_key_exists('url', $input) ? $input['url'] : null,
					'verify_host' => array_key_exists('verify_host', $input) ? $input['verify_host'] : 0,
					'verify_peer' => array_key_exists('verify_peer', $input) ? $input['verify_peer'] : 0
				];

				if ($data['http_authtype'] != HTTPTEST_AUTH_NONE) {
					$data += [
						'http_username' => array_key_exists('http_username', $input) ? $input['http_username'] : null,
						'http_password' => array_key_exists('http_password', $input) ? $input['http_password'] : null
					];
				}
				break;

			case ITEM_TYPE_IPMI:
				$data += [
					'key' => $input['key'],
					'ipmi_sensor' => array_key_exists('ipmi_sensor', $input) ? $input['ipmi_sensor'] : null,
					'interface' => $this->getHostInterface($interface_input),
					'host' => [
						'hostid' => $this->host['hostid']
					]
				];

				if ($this->host['status'] != HOST_STATUS_TEMPLATE) {
					$data['host'] += [
						'ipmi_authtype' => $this->host['ipmi_authtype'],
						'ipmi_privilege' => $this->host['ipmi_privilege'],
						'ipmi_username' => $this->host['ipmi_username'],
						'ipmi_password' => $this->host['ipmi_password']
					];
				}

				unset($data['interface']['useip'], $data['interface']['interfaceid'], $data['interface']['ip'],
					$data['interface']['dns']
				);
				break;

			case ITEM_TYPE_SSH:
				$data += [
					'key' => $input['key'],
					'authtype' => array_key_exists('authtype', $input) ? $input['authtype'] : ITEM_AUTHTYPE_PASSWORD,
					'params_es' => array_key_exists('params_es', $input) ? $input['params_es'] : ITEM_AUTHTYPE_PASSWORD,
					'username' => array_key_exists('username', $input) ? $input['username'] : null,
					'password' => array_key_exists('password', $input) ? $input['password'] : null,
					'interface' => $this->getHostInterface($interface_input)
				];

				if ($data['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$data += [
						'publickey' => array_key_exists('publickey', $input) ? $input['publickey'] : null,
						'privatekey' => array_key_exists('privatekey', $input) ? $input['privatekey'] : null
					];
				}
				break;

			case ITEM_TYPE_TELNET:
				$data += [
					'key' => $input['key'],
					'params_es' => array_key_exists('params_es', $input) ? $input['params_es'] : null,
					'username' => array_key_exists('username', $input) ? $input['username'] : null,
					'password' => array_key_exists('password', $input) ? $input['password'] : null,
					'interface' => $this->getHostInterface($interface_input)
				];
				break;

			case ITEM_TYPE_JMX:
				$data += [
					'key' => $input['key'],
					'jmx_endpoint' => array_key_exists('jmx_endpoint', $input) ? $input['jmx_endpoint'] : null,
					'username' => array_key_exists('username', $input) ? $input['username'] : null,
					'password' => array_key_exists('password', $input) ? $input['password'] : null
				];
				break;

			case ITEM_TYPE_CALCULATED:
				$data += [
					'key' => $input['key'],
					'params_f' => array_key_exists('params_f', $input) ? $input['params_f'] : null,
					'host' => [
						'host' => $this->host['host']
					]
				];
				break;

			case ITEM_TYPE_SIMPLE:
				$data += [
					'key' => $input['key'],
					'interface' => $this->getHostInterface($interface_input),
					'username' => array_key_exists('username', $input) ? $input['username'] : null,
					'password' => array_key_exists('password', $input) ? $input['password'] : null
				];

				unset($data['interface']['useip'], $data['interface']['interfaceid'], $data['interface']['ip'],
					$data['interface']['dns'],  $data['interface']['port']
				);
				break;

			case ITEM_TYPE_SCRIPT:
				$data += [
					'key' => $input['key'],
					'parameters' => array_key_exists('parameters', $input) ? $input['parameters'] : [],
					'script' => array_key_exists('script', $input) ? $input['script'] : null,
					'timeout' => array_key_exists('timeout', $input) ? $input['timeout'] : null
				];
				break;
		}

		return $data;
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
				'contextname' => ''
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

		if ($this->item_type == ITEM_TYPE_SCRIPT) {
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
			if ($key === 'host' && is_array($value)) {
				$data[$key] = $this->unsetEmptyValues($value);

				if (!$data[$key]) {
					unset($data[$key]);
				}
			}
			elseif ($key === 'interface' && $this->item_type == ITEM_TYPE_SNMP) {
				if ($data['interface']['details']['version'] == SNMP_V3) {
					unset($data['interface']['details']['community']);

					if ($data['interface']['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV) {
						unset($data['interface']['details']['authprotocol'],
							$data['interface']['details']['authpassphrase'],
							$data['interface']['details']['privprotocol'],
							$data['interface']['details']['privpassphrase']
						);
					}
					elseif ($data['interface']['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
						unset($data['interface']['details']['privprotocol'],
							$data['interface']['details']['privpassphrase']
						);
					}
				}
				else {
					unset($data['interface']['details']['contextname'],
						$data['interface']['details']['securityname'],
						$data['interface']['details']['securitylevel'],
						$data['interface']['details']['authprotocol'],
						$data['interface']['details']['authpassphrase'],
						$data['interface']['details']['privprotocol'],
						$data['interface']['details']['privpassphrase']
					);
				}

				unset($data['interface']['type']);
			}
			elseif ($key === 'query_fields') {
				if ($value === '[]') {
					unset($data[$key]);
				}
			}
			elseif ($key === 'parameters') {
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
				'support_lldmacros' => ($this->preproc_item instanceof CItemPrototype),
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
	 * Transform front-end familiar array of http query fields to the form server is capable to handle.
	 *
	 * @param array $data
	 * @param array $data[name]   Indexed array of names.
	 * @param array $data[value]  Indexed array of values.
	 *
	 * @return string
	 */
	protected function transformQueryFields(array $data) {
		$result = [];

		if (array_key_exists('name', $data) && array_key_exists('value', $data)) {
			foreach (array_keys($data['name']) as $num) {
				if (array_key_exists($num, $data['value']) && $data['name'][$num] !== '') {
					$result[] = [$data['name'][$num] => $data['value'][$num]];
				}
			}
		}

		return json_encode($result);
	}

	/**
	 * Transform front-end familiar array of parameters fields to the form server is capable to handle. Server expects
	 * one object where parameter names are keys and parameter values are values. Note that parameter names are unique.
	 *
	 * @param array $data
	 * @param array $data[name]   Indexed array of names.
	 * @param array $data[value]  Indexed array of values.
	 *
	 * @return array
	 */
	protected function transformParametersFields(array $data): array {
		$result = [];

		if (array_key_exists('name', $data) && array_key_exists('value', $data)) {
			foreach (array_keys($data['name']) as $num) {
				if (array_key_exists($num, $data['value']) && $data['name'][$num] !== '') {
					$result += [$data['name'][$num] => $data['value'][$num]];
				}
			}
		}

		return $result;
	}

	/**
	 * Transform front-end familiar array of http header fields to the form server is capable to handle.
	 *
	 * @param array $data
	 * @param array $data[name]   Indexed array of names.
	 * @param array $data[value]  Indexed array of values.
	 *
	 * @return string
	 */
	protected function transformHeaderFields(array $data) {
		$result = [];

		if (array_key_exists('name', $data) && array_key_exists('value', $data)) {
			foreach (array_keys($data['name']) as $num) {
				if (array_key_exists($num, $data['value']) && $data['name'][$num] !== '') {
					$result[] = $data['name'][$num].': '.$data['value'][$num];
				}
			}
		}

		return implode("\r\n", $result);
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
		$macros_types = ($this->preproc_item instanceof CItemPrototype)
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
				$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($step[$field], $macros_types);

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
			'lldmacros' => ($this->preproc_item instanceof CItemPrototype),
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

			if ($this->preproc_item instanceof CItemPrototype) {
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

				foreach (['name', 'value'] as $key) {
					foreach (array_keys($inputs[$field][$key]) as $nr) {
						$str = &$inputs[$field][$key][$nr];
						if (strstr($str, '{') !== false) {
							$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($str, $types);

							foreach (array_reverse($matched_macros, true) as $pos => $macro) {
								$macro_value = array_key_exists($macro, $macros_posted)
									? $macros_posted[$macro]
									: '';

								$str = substr_replace($str, $macro_value, $pos, strlen($macro));
							}
						}

						unset($str);
					}
				}
			}
			elseif (strstr($inputs[$field], '{') !== false) {
				if ($field === 'key') {
					$inputs[$field] = CMacrosResolverGeneral::resolveItemKeyMacros($inputs[$field], $macros_posted, $types);
				}
				else {
					$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($inputs[$field], $types);

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
					$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($field, ['usermacros' => true]);

					foreach (array_reverse($matched_macros, true) as $pos => $macro) {
						$macro_value = array_key_exists($macro, $macros_posted)
							? $macros_posted[$macro]
							: '';

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
}
