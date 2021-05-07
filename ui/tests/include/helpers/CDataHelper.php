<?php

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../../include/hosts.inc.php';

class CDataHelper extends CAPIHelper {

	protected static $data = null;
	protected static $request = [];
	protected static $response = [];

	/**
	 * Prepare request for API call and make API call (@see callRaw).
	 *
	 * @param string $method    API method to be called.
	 * @param mixed $params     API call params.
	 *
	 * @return array
	 *
	 * @throws Exception on API error
	 */
	public static function call($method, $params) {
		global $URL;
		if (!$URL) {
			$URL = PHPUNIT_URL.'api_jsonrpc.php';
		}

		static::$request = [];
		static::$response = [];

		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::createSessionId();
		}

		$response = CAPIHelper::call($method, $params);

		if (array_key_exists('error', $response)) {
			throw new Exception('API call failed: '.json_encode($response['error'], JSON_PRETTY_PRINT));
		}

		if (!array_key_exists('result', $response)) {
			throw new Exception('API call failed: result is not present');
		}

		static::$request = (CTestArrayHelper::isAssociative($params)) ? [$params] : $params;
		static::$response = $response['result'];

		return static::$response;
	}

	/**
	 * Prepare request for API call and make API call (@see callRaw).
	 *
	 * @param string $method    API method to be called.
	 * @param mixed $params     API call params.
	 *
	 * @return array
	 *
	 * @throws Exception on API error
	 */
	public static function getIds($field) {
		$ids = [];
		$result = reset(static::$response);
		if (count(static::$request) !== count($result)) {
			throw new Exception('Failed to get ids: record counts don\'t match');
		}

		foreach (static::$request as $i => $object) {
			if (!array_key_exists($field, $object)) {
				throw new Exception('Failed to get ids: field "'.$field.'" is not present in request.');
			}

			if (!array_key_exists($i, $result)) {
				throw new Exception('Failed to get ids: element ('.$i.') is not present in result.');
			}

			if (array_key_exists($object[$field], $ids)) {
				throw new Exception('Failed to get ids: field "'.$field.'" is not unique.');
			}

			$ids[$object[$field]] = $result[$i];
		}

		return $ids;
	}

	/**
	 * Create host with items.
	 *
	 * @param mixed $params    API call params.
	 *                         In addition to the default host.create params, "items" can be set for any of the host in
	 *                         order to create host items.
	 *
	 * @return array
	 *
	 * @throws Exception on API error
	 */
	public static function createHosts($params) {
		$items = [];
		foreach ($params as &$param) {
			if (array_key_exists('items', $param)) {
				$items[$param['host']] = $param['items'];
				unset($param['items']);
			}
		}
		unset($param);

		static::call('host.create', $params);
		$hostids = static::getIds('host');

		$result = [
			'hostids' => $hostids
		];

		if (!$items) {
			return $result;
		}

		$hosts = static::call('host.get', [
			'output' => ['host'],
			'hostids' => array_values($hostids),
			'selectInterfaces' => ['interfaceid', 'useip', 'ip', 'type', 'dns', 'port', 'main']
		]);

		$default_interfaces = [];
		$interfaceids = [];
		foreach ($hosts as $host) {
			foreach ($host['interfaces'] as $interface) {
				if ($interface['main'] == 1) {
					$default_interfaces[$host['host']][$interface['type']] = $interface['interfaceid'];
				}

				$address = ($interface['useip'] == 1) ? $interface['ip'] : $interface['dns'];
				$interfaceids[$host['host']][$address.':'.$interface['port']] = $interface['interfaceid'];
			}
		}

		$request = [];
		foreach ($items as $host => $host_items) {
			foreach ($host_items as $item) {
				$item['hostid'] = $hostids[$host];

				if (array_key_exists($host, $default_interfaces)) {
					$interface_type = null;
					switch (CTestArrayHelper::get($item, 'type')) {
						case ITEM_TYPE_ZABBIX:
						case ITEM_TYPE_ZABBIX_ACTIVE:
							$interface_type = 1;
							break;

						case ITEM_TYPE_SNMP:
						case ITEM_TYPE_SNMPTRAP:
							$interface_type = 2;
							break;

						case ITEM_TYPE_IPMI:
							$interface_type = 3;
							break;

						case ITEM_TYPE_JMX:
							$interface_type = 4;
							break;
					}

					if ($interface_type !== null && array_key_exists($interface_type, $default_interfaces[$host])) {
						$item['interfaceid'] = $default_interfaces[$host][$interface_type];
					}
				}

				if (array_key_exists($host, $interfaceids)) {
					$interface = CTestArrayHelper::get($item, 'interface');
					unset($item['interface']);
					if ($interface !== null && array_key_exists($interfaceids[$host], $interface)) {
						$item['interfaceid'] = $interfaceids[$host][$interface];
					}
				}

				$request[] = $item;
			}
		}

		// Create items.
		$response = static::call('item.create', $request);
		$i = 0;

		$result['itemids'] = [];
		foreach ($items as $host => $host_items) {
			foreach ($host_items as $item) {
				if (!array_key_exists($i, $response['itemids'])) {
					throw new Exception('Failed to get ids: element ('.$i.') is not present in result.');
				}

				$result['itemids'][$host.':'.$item['key_']] = $response['itemids'][$i++];
			}
		}

		return $result;
	}

	/**
	 * Load the data source data from the file cache.
	 */
	protected static function preload() {
		if (static::$data === null) {
			static::$data = [];

			if (!defined('PHPUNIT_DATA_DIR')) {
				return;
			}

			foreach (new DirectoryIterator(PHPUNIT_DATA_DIR) as $file) {
				if ($file->isDot() || $file->isDir() || strtolower($file->getExtension()) !== 'json') {
					continue;
				}

				$name = $file->getBasename('.'.$file->getExtension());
				static::$data[$name] = json_decode(file_get_contents($file->getPathname()), true);
			}
		}
	}

	/**
	 * Get data from the data sources.
	 *
	 * @param mixed $path       data path to look for
	 * @param mixed $default    default value to be returned if data doesn't exist
	 *
	 * @return mixed
	 */
	public static function get($path, $default = null) {
		return CTestArrayHelper::get(static::$data, $path, $default);
	}

	/**
	 * Load specific data source data.
	 *
	 * @param mixed $source    name of the data source(s)
	 *
	 * @return boolean
	 *
	 * @throws \Exception
	 */
	public static function load($source) {
		if (is_array($source)) {
			$result = true;
			foreach ($source as $name) {
				$result &= static::load($name);
			}

			return $result;
		}

		static::preload();

		if (array_key_exists($source, static::$data)) {
			return true;
		}

		try {
			$path = PHPUNIT_DATA_SOURCES_DIR.$source.'.php';
			if (!file_exists($path)) {
				throw new \Exception('File "'.$path.'" doesn\'t exist.');
			}

			require_once $path;
			static::$data[$source] = forward_static_call([$source, 'load']);

			if (defined('PHPUNIT_DATA_DIR')) {
				$data = json_encode(static::get($source));
				file_put_contents(PHPUNIT_DATA_DIR.$source.'.json', $data);
			}
		}
		catch (\Exception $e) {
			echo 'Failed to load data from data source "'.$source.'".'."\n\n".$e->getMessage()."\n".$e->getTraceAsString();

			return false;
		}

		return true;
	}
}
