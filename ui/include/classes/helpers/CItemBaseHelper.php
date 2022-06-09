<?php declare(strict_types = 0);
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
 * A helper class for Items and their derivatives.
 */
class CItemBaseHelper {

	// Fields that primarily play a role in determining whether a property is relevant to specific item.
	public const CONDITION_FIELDS = ['itemid', 'key_', 'type', 'flags', 'authtype', 'templateid', 'value_type',
		'request_method', 'post_type', 'allow_traps'
	];

	public static function getFieldDefaults() {
		static $defaults;

		if ($defaults === null) {
			$defaults = DB::getDefaults('items')
				+ array_fill_keys(['valuemapid', 'interfaceid', 'master_itemid', 'ruleid'], 0)
				+ array_fill_keys(['tags', 'preprocessing', 'parameters'], []);
		}

		return $defaults;
	}

	/**
	 * Pick out relevant input for various item/prototype types, as expected by API, leaving out general (form) fields.
	 *
	 * @param array $items          A set of item inputs as received via create/update form, mass update etc.
	 * @param int	$items[<n>][<host_status>]  Relevant if items are to be created.
	 * @param int	$items[<n>][<flags>]
	 * @param array|null $db_items  Relevant for updates.
	 * @param array	$db_items[<n>][<hosts>]  Expected to contain host status.
	 * @param array	$db_items[<n>][<tags>]   In case of mass_update_tags operations.
	 *
	 * @return array A set of 'cleaned up' items. Empty in case a fatal input (parsing) error has been encountered.
	 */
	public static function sanitizeItems(array $items, array $db_items = null): array {
		$item_defaults = self::getFieldDefaults();

		foreach ($items as $i => $item) {
			$item = CArrayHelper::renameKeys($item, [
				'key' => 'key_',
				'parent_discoveryid' => 'ruleid'
			]);

			if (CArrayHelper::getByPath($item, 'trends_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
				$item['trends'] = ITEM_NO_STORAGE_VALUE;
			}

			if (CArrayHelper::getByPath($item, 'history_mode', ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
				$item['history'] = ITEM_NO_STORAGE_VALUE;
			}

			$items[$i] = $item;
		}

		return $db_items
			? self::sanitizeUpdateItems($items, $db_items, $item_defaults)
			: self::sanitizeCreateItems($items, $item_defaults);
	}

	protected static function sanitizeCreateItems(array $items, array $item_defaults): array {
		$create_items = [];

		foreach ($items as $item) {
			$item += $item_defaults;

			if ($item['type'] == ITEM_TYPE_SCRIPT) {
				$item = prepareScriptItemFormData($item);
			}
			elseif ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				$item = CArrayHelper::renameKeys($item, [
					'http_authtype' => 'authtype',
					'http_username' => 'username',
					'http_password' => 'password'
				]);

				$item = prepareItemHttpAgentFormData($item);
			}

			$field_map = ['hostid', 'name', 'type', 'key_', 'value_type', 'history', 'description', 'status', 'tags',
				'preprocessing'
			];

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$field_map[] = 'ruleid';
				$field_map[] = 'discover';
			}
			elseif ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL && in_array($item['value_type'],
					[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])) {
				$field_map[] = 'inventory_link';
			}

			if ($item['host_status'] == HOST_STATUS_TEMPLATE && CArrayHelper::getByPath($item, 'uuid', '') !== '') {
				$field_map[] = 'uuid';
			}

			if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$field_map[] = 'units';
				$field_map[] = 'trends';
			}

			if (in_array($item['value_type'],  [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])) {
				$field_map[] = 'valuemapid';
			}

			if ($item['value_type'] == ITEM_VALUE_TYPE_LOG) {
				$field_map[] = 'logtimefmt';
			}

			$field_map = array_flip($field_map);
			$field_map['tags'] = self::getTagMap($item);
			$field_map['preprocessing'] = self::getPreprocessingMap($item);
			$field_map += self::addFieldMapByType($item, null);

			$create_items[] = self::combineFromFieldMap($field_map, $item);
		}

		return $create_items;
	}

	protected static function sanitizeUpdateItems(array $items, array $db_items, array $item_defaults): array {
		$update_items = [];

		foreach ($db_items as $i => $db_item) {
			$db_item['hosts'][0] = CArrayHelper::renameKeys($db_item['hosts'][0], ['status' => 'host_status']);
			$item = $db_item['hosts'][0];
			unset($db_item['hosts']);

			$item += $items[$i] + $db_item + $item_defaults;

			if ($item['type'] == ITEM_TYPE_SCRIPT) {
				$item = prepareScriptItemFormData($item);
			}
			elseif ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				$item = CArrayHelper::renameKeys($item, [
					'http_authtype' => 'authtype',
					'http_username' => 'username',
					'http_password' => 'password'
				]);

				$item = prepareItemHttpAgentFormData($item);
			}

			switch (CArrayHelper::getByPath($item, 'mass_update_tags', -1)) {
				case ZBX_ACTION_ADD:
					$unique_tags = [];

					foreach (array_merge($db_item['tags'], $item['tags']) as $tag) {
						$unique_tags[$tag['tag']][$tag['value']] = $tag;
					}

					$item['tags'] = [];

					foreach ($unique_tags as $tags_by_name) {
						foreach ($tags_by_name as $tag) {
							$item['tags'][] = $tag;
						}
					}
					break;

				case ZBX_ACTION_REMOVE:
					$diff_tags = [];

					foreach ($db_item['tags'] as $a) {
						foreach ($item['tags'] as $b) {
							if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
								continue 2;
							}
						}

						$diff_tags[] = $a;
					}

					$item['tags'] = $diff_tags;
					break;
			}

			$field_map = ['itemid'];

			if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
				$field_map[] = 'trends';
			}

			if ($db_item['templateid'] != 0) {
				$field_map = array_merge($field_map, ['value_type', 'history', 'history', 'status', 'tags']);

				if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
						&& in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR,
							ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])) {
					$field_map[] = 'inventory_link';
				}
			}
			elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$field_map[] = 'status';
			}
			else {
				$field_map = array_merge($field_map, ['hostid', 'name', 'type', 'key_', 'value_type', 'history',
					'description', 'status', 'tags', 'preprocessing'
				]);

				if (in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
					$field_map[] = 'units';
				}

				if (in_array($item['value_type'],
						[ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])) {
					$field_map[] = 'valuemapid';
				}

				if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL
						&& in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR,
							ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])) {
					$field_map[] = 'inventory_link';
				}

				if ($item['value_type'] == ITEM_VALUE_TYPE_LOG) {
					$field_map[] = 'logtimefmt';
				}
			}

			$field_map = array_flip($field_map);
			$field_map['tags'] = self::getTagMap($item);
			$field_map['preprocessing'] = self::getPreprocessingMap($item);
			$field_map += self::addFieldMapByType($item, $db_item);

			$update_items[] = self::combineFromFieldMap($field_map, $item);
		}

		return $update_items;
	}

	protected static function getTagMap(array &$item): array {
		$field_map = [];
		$item['tags'] = prepareItemTags(CArrayHelper::getByPath($item, 'tags', []));

		foreach ($item['tags'] as $foo) {
			$field_map[] = array_flip(['tag', 'value']);
		}

		return $field_map;
	}

	protected static function getPreprocessingMap(array &$item): array {
		$field_map = [];
		$item['preprocessing'] = normalizeItemPreprocessingSteps(CArrayHelper::getByPath($item, 'preprocessing', []));
		$types_with_params = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE
			? CItemPrototype::PREPROC_TYPES_WITH_PARAMS
			: CItem::PREPROC_TYPES_WITH_PARAMS;

		foreach ($item['preprocessing'] as $preproc) {
			$preproc_map = ['type'];

			if (in_array($preproc['type'], $types_with_params)) {
				$preproc_map[] = 'params';
			}

			if ($preproc['type'] == ZBX_PREPROC_VALIDATE_NOT_SUPPORTED
					|| in_array($preproc['type'], CItem::PREPROC_TYPES_WITH_ERR_HANDLING)) {
				$preproc_map[] = 'error_handler';
			}

			if (array_key_exists('error_handler', $preproc) && in_array($preproc['error_handler'],
					[ZBX_PREPROC_FAIL_SET_VALUE, ZBX_PREPROC_FAIL_SET_ERROR])) {
				$preproc_map[] = 'error_handler_params';
			}

			$field_map[] = array_flip($preproc_map);
		}

		return $field_map;
	}

	protected static function getInterfaceidExpected(array $item, array $fields): array {
		if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
			$fields[] = 'interfaceid';
		}

		return $fields;
	}

	protected static function getCreateFields(array $item): array {
		$fields = [];

		switch ($item['type']) {
			case ITEM_TYPE_CALCULATED:
				return ['params', 'delay'];

			case ITEM_TYPE_DB_MONITOR:
				return ['username', 'password', 'params', 'delay'];

			case ITEM_TYPE_DEPENDENT:
				return ['master_itemid'];

			case ITEM_TYPE_EXTERNAL:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_HTTPAGENT:
				$fields = ['url', 'query_fields', 'request_method', 'timeout', 'post_type', 'posts', 'headers',
					'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'authtype',
					'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'delay',
					'allow_traps'
				];
				$fields = self::getInterfaceidExpected($item, $fields);

				if (in_array($item['authtype'],
						[HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])) {
					$fields[] = 'username';
					$fields[] = 'password';
				}

				if ($item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON) {
					$fields[] = 'trapper_hosts';
				}
				break;

			case ITEM_TYPE_INTERNAL:
				return ['delay'];

			case ITEM_TYPE_IPMI:
				$fields = ['delay', 'ipmi_sensor'];
				$fields = self::getInterfaceidExpected($item, $fields);
				break;

			case ITEM_TYPE_JMX:
				$fields =  ['jmx_endpoint', 'username', 'password', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SCRIPT:
				return ['params', 'timeout', 'delay', 'parameters'];

			case ITEM_TYPE_SIMPLE:
				$fields = ['username', 'password', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMP:
				$fields = ['snmp_oid', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMPTRAP:
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SSH:
				$fields = ['authtype', 'username', 'password', 'params', 'delay'];
				$fields = self::getInterfaceidExpected($item, $fields);

				if ($item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$fields[] = 'publickey';
					$fields[] = 'privatekey';
				}
				break;

			case ITEM_TYPE_TELNET:
				$fields = ['username', 'password', 'params', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_TRAPPER:
				return ['trapper_hosts'];

			case ITEM_TYPE_ZABBIX:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_ZABBIX_ACTIVE:
				if (strncmp($item['key_'], 'mqtt.get', 8) !== 0) {
					$fields[] = 'delay';
				}
				break;
		}

		return $fields;
	}

	protected static function getUpdateInheritedFields(array $item): array {
		$fields = [];

		switch ($item['type']) {
			case ITEM_TYPE_CALCULATED:
				return ['params', 'delay'];

			case ITEM_TYPE_DB_MONITOR:
				return ['username', 'password', 'params', 'delay'];

			case ITEM_TYPE_EXTERNAL:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_HTTPAGENT:
				$fields = ['delay', 'allow_traps'];
				$fields = self::getInterfaceidExpected($item, $fields);

				if ($item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON) {
					$fields[] = 'trapper_hosts';
				}
				break;

			case ITEM_TYPE_INTERNAL:
				return ['delay' ];

			case ITEM_TYPE_IPMI:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_JMX:
				$fields =  ['jmx_endpoint', 'username', 'password', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SCRIPT:
				return $fields = ['delay'];

			case ITEM_TYPE_SIMPLE:
				$fields = ['username', 'password', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMP:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMPTRAP:
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SSH:
				$fields = ['authtype', 'username', 'password', 'params', 'delay'];
				$fields = self::getInterfaceidExpected($item, $fields);

				if ($item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$fields[] = 'publickey';
					$fields[] = 'privatekey';
				}
				break;

			case ITEM_TYPE_TELNET:
				$fields = ['username', 'password', 'params', 'delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_TRAPPER:
				return ['trapper_hosts'];

			case ITEM_TYPE_ZABBIX:
				$fields = ['delay'];
				return self::getInterfaceidExpected($item, $fields);

			case ITEM_TYPE_ZABBIX_ACTIVE:
				if (strncmp($item['key_'], 'mqtt.get', 8) !== 0) {
					$fields[] = 'delay';
				}
				break;
		}

		return $fields;
	}

	protected static function getUpdateInterfaceidExpected(array $item, array $fields): array {
		$fields = self::getInterfaceidExpected($item, $fields);

		if (!in_array('intefaceid', $fields) && in_array($item['type'], [
				ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_DB_MONITOR,
				ITEM_TYPE_CALCULATED, ITEM_TYPE_DEPENDENT, ITEM_TYPE_SCRIPT
		])) {
			$fields[] = 'interfaceid';
		}

		return $fields;
	}

	protected static function getUpdateFields(array $item): array {
		$fields = [];

		switch ($item['type']) {
			case ITEM_TYPE_CALCULATED:
				return ['params', 'delay'];

			case ITEM_TYPE_DB_MONITOR:
				return ['username', 'password', 'params', 'delay'];

			case ITEM_TYPE_DEPENDENT:
				return ['master_itemid'];

			case ITEM_TYPE_EXTERNAL:
				$fields = ['delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_HTTPAGENT:
				$fields = ['url', 'query_fields', 'request_method', 'timeout', 'post_type', 'posts', 'headers',
					'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'authtype',
					'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'delay',
					'allow_traps'
				];
				$fields = self::getInterfaceidExpected($item, $fields);

				if (in_array($item['authtype'],
						[HTTPTEST_AUTH_BASIC, HTTPTEST_AUTH_NTLM, HTTPTEST_AUTH_KERBEROS, HTTPTEST_AUTH_DIGEST])) {
					$fields[] = 'username';
					$fields[] = 'password';
				}

				if ($item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_ON) {
					$fields[] = 'trapper_hosts';
				}
				break;

			case ITEM_TYPE_INTERNAL:
				return ['delay' ];

			case ITEM_TYPE_IPMI:
				$fields = ['delay', 'ipmi_sensor'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_JMX:
				$fields =  ['jmx_endpoint', 'username', 'password', 'delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SCRIPT:
				return $fields = ['parameters', 'params', 'timeout', 'delay'];

			case ITEM_TYPE_SIMPLE:
				$fields = ['username', 'password', 'delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMP:
				$fields = ['snmp_oid', 'delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SNMPTRAP:
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_SSH:
				$fields = ['authtype', 'username', 'password', 'params', 'delay'];
				$fields = self::getUpdateInterfaceidExpected($item, $fields);

				if ($item['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$fields[] = 'publickey';
					$fields[] = 'privatekey';
				}
				break;

			case ITEM_TYPE_TELNET:
				$fields = ['username', 'password', 'params', 'delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_TRAPPER:
				return ['trapper_hosts'];

			case ITEM_TYPE_ZABBIX:
				$fields = ['delay'];
				return self::getUpdateInterfaceidExpected($item, $fields);

			case ITEM_TYPE_ZABBIX_ACTIVE:
				if (strncmp($item['key_'], 'mqtt.get', 8) !== 0) {
					$fields[] = 'delay';
				}
				break;
		}

		return $fields;
	}

	protected static function addFieldMapByType(array &$item, ?array $db_item): array {
		if ($db_item === null) {
			$field_map = array_flip(self::getCreateFields($item));

			if ($item['type'] == ITEM_TYPE_SCRIPT && array_key_exists('parameters', $item)) {
				$field_map['parameters'] = [];

				foreach ($item['parameters'] as $foo) {
					$field_map['parameters'][] =  array_flip(['name', 'value']);
				}
			}
		}
		elseif ($db_item['templateid'] != 0) {
			$field_map = array_flip(self::getUpdateInheritedFields($item));
		}
		elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$field_map = [];
		}
		else {
			$field_map = array_flip(self::getUpdateFields($item));
		}

		if (array_key_exists('delay', $field_map)) {
			$delay_error = null;
			$item['delay'] = self::processItemDelay($item, $delay_error);

			if ($delay_error !== null) {
				error($delay_error);
				return [];
			}
		}

		return $field_map;
	}

	/**
	 * Extract relevant fields and values from item input according to the "scheme" provided.
	 *
	 * @param array $field_map  The structure to match, outlined by the present array keys.
	 * @param array $item
	 *
	 * @return array Output corresponding to the mapped out structure.
	 */
	public static function combineFromFieldMap(array $field_map, array $item): array {
		$fields = array_flip(array_keys($field_map));
		$result = array_intersect_key($item, $fields);

		foreach ($field_map as $field => $structures) {
			if (!is_array($structures) || !array_key_exists($field, $item)) {
				continue;
			}

			$result[$field] = [];

			foreach ($structures as $i => $structure) {
				$result[$field][] = self::combineFromFieldMap($structure ,$item[$field][$i]);
			}
		}

		return $result;
	}

	/**
	 * Converts 'delay' and optionally 'delay_flex' to API input format.
	 *
	 * @param string $item[<delay>]
	 * @param string $item[<delay_flex>] (optional)
	 * @param int    $item[<type>]
	 * @param string $item[<key_>]
	 *
	 * @param string<out> $error  Non-null if delay_flex could not be parsed.
	 *
	 * @return string  Empty and $error set if failed to parse.
	 */
	protected static function processItemDelay(array $item, &$error = null): string {
		$delay = $item['delay'];

		/*
		* The 'delay_flex' is a temporary field that contains flexible and scheduling intervals,
		* separated by a semicolon.
		* These custom intervals together with the 'delay' part itself are combined to a single string.
		*/
		if (!in_array($item['type'], [ITEM_TYPE_TRAPPER, ITEM_TYPE_SNMPTRAP])
				&& array_key_exists('delay_flex', $item)
				&& ($item['type'] != ITEM_TYPE_ZABBIX_ACTIVE || strncmp($item['key_'], 'mqtt.get', 8) !== 0)) {
			$intervals = [];
			$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
			$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
			$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

			foreach ($item['delay_flex'] as $interval) {
				if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
					if ($interval['delay'] === '' && $interval['period'] === '') {
						continue;
					}

					if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
						$error = _s('Invalid interval "%1$s".', $interval['delay']);

						return '';
					}
					elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
						$error = _s('Invalid interval "%1$s".', $interval['period']);
						return '';
					}

					$intervals[] = $interval['delay'].'/'.$interval['period'];
				}
				else {
					if ($interval['schedule'] === '') {
						continue;
					}

					if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
						$error = _s('Invalid interval "%1$s".', $interval['schedule']);

						return '';
					}

					$intervals[] = $interval['schedule'];
				}
			}

			if ($intervals) {
				$delay .= ';'.implode(';', $intervals);
			}
		}

		return $delay;
	}
}
