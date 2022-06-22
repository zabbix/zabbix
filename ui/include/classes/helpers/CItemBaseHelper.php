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
	public const CONDITION_FIELDS = ['itemid', 'key_', 'type', 'flags', 'authtype', 'templateid', 'master_itemid',
		'allow_traps'
	];

	public static function getFieldDefaults(bool $with_related_objects = true) {
		static $defaults;

		if ($defaults === null) {
			$defaults = DB::getDefaults('items')
				+ array_fill_keys(['valuemapid', 'interfaceid', 'master_itemid', 'ruleid'], ZEROID);
		}

		return $with_related_objects
			? $defaults + array_fill_keys(['tags', 'preprocessing', 'parameters'], [])
			: $defaults;
	}

	/**
	 * Combine input for item/prototypes as expected by API, leaving out general (form) and unexpected fields.
	 *
	 * @param array $items                      A set of item inputs as received via create/update form, mass update...
	 * @param int	$items[<n>][<host_status>]  Relevant if items are to be created.
	 * @param int	$items[<n>][<flags>]
	 * @param array|null $db_items              Relevant for updates.
	 * @param array	$db_items[<n>][<hosts>]     Expected to contain host status.
	 * @param array	$db_items[<n>][<tags>]      In case of mass_update_tags operations.
	 *
	 * @return array                            A set of item inputs ready for passing to API.
	 *                                          Empty in case a fatal input (parsing) error encountered.
	 */
	public static function extractItems(array $items, array $db_items = null): array {
		$item_defaults = ['itemid' => ZEROID] + self::getFieldDefaults();

		foreach ($items as $i => $item) {
			$item = CArrayHelper::renameKeys($item, [
				'key' => 'key_',
				'parent_discoveryid' => 'ruleid'
			]);

			if (self::getInput($item, ['trends_mode'], ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
				$item['trends'] = ITEM_NO_STORAGE_VALUE;
			}

			if (self::getInput($item, ['history_mode'], ITEM_STORAGE_CUSTOM) == ITEM_STORAGE_OFF) {
				$item['history'] = ITEM_NO_STORAGE_VALUE;
			}

			$items[$i] = $item;
		}

		return $db_items
			? self::extractUpdateItems($items, $db_items, $item_defaults)
			: self::extractCreateItems($items, $item_defaults);
	}

	protected static function extractCreateItems(array $items, array $item_defaults): array {
		$create_items = [];

		foreach ($items as $item) {
			$item += $item_defaults;
			$item = self::convertInput($item);

			if (!$item) {
				return [];
			}

			$create_items[] = array_intersect_key($item, $item_defaults);
		}

		return $create_items;
	}

	protected static function extractUpdateItems(array $items, array $db_items, array $item_defaults): array {
		$update_items = [];

		foreach ($db_items as $i => $db_item) {
			$db_item['hosts'][0] = CArrayHelper::renameKeys($db_item['hosts'][0], ['status' => 'host_status']);
			$item = $db_item['hosts'][0];
			unset($db_item['hosts']);

			$item += $items[$i] + $db_item + $item_defaults;
			$item = self::convertInput($item);

			if (!$item) {
				return [];
			}

			switch (self::getInput($item, ['mass_update_tags'], -1)) {
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

			$item = array_intersect_key($item, $item_defaults);
			$update_items[] = self::sanitizeItem($item, $db_item);
		}

		return $update_items;
	}

	protected static function convertInput(array $item): array {
		if (array_key_exists('delay', $item)) {
			$delay_error = null;
			$item['delay'] = self::processItemDelay($item, $delay_error);

			if ($delay_error !== null) {
				error($delay_error);
				return [];
			}
		}

		$item = prepareScriptItemFormData($item);

		if (array_key_exists('http_authtype', $item)) {
			$item = CArrayHelper::renameKeys($item, [
				'http_authtype' => 'authtype',
				'http_username' => 'username',
				'http_password' => 'password'
			]);
		}

		$item = prepareItemHttpAgentFormData($item);

		$item['tags'] = prepareItemTags(self::getInput($item, ['tags'], []));
		$item['preprocessing'] = normalizeItemPreprocessingSteps(self::getInput($item, ['preprocessing'], []));

		return $item;
	}

	protected static function sanitizeItem(array &$item, ?array $db_item = null): array {
		$excluded_fields = [];

		if ($db_item !== null) {
			if ($db_item['templateid'] != 0) {
				$excluded_fields = array_merge(['value_type', 'name', 'type', 'key_', 'units', 'valuemapid',
					'logtimefmt','preprocessing'
				], self::excludedInheritedTypeFields($item));
			}
			elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$excluded_fields = array_merge(['name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
					'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'tags', 'preprocessing'
				], self::excludedDiscoveredTypeFields($item));
			}
		}

		return $excluded_fields
			? array_diff_key($item, array_flip($excluded_fields))
			: $item;
	}

	protected static function excludedInheritedTypeFields(array $item): array {
		switch ($item['type']) {
			case ITEM_TYPE_DEPENDENT:
				return ['master_itemid'];

			case ITEM_TYPE_HTTPAGENT:
				return ['url', 'query_fields', 'request_method', 'timeout', 'post_type', 'posts', 'headers',
					'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'authtype',
					'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password'
				];

			case ITEM_TYPE_IPMI:
				return ['ipmi_sensor'];

			case ITEM_TYPE_SCRIPT:
				return ['parameters', 'params', 'timeout'];

			case ITEM_TYPE_SNMP:
				return ['snmp_oid'];
		}

		return [];
	}

	protected static function excludedDiscoveredTypeFields(array $item): array {
		switch ($item['type']) {
			case ITEM_TYPE_CALCULATED:
				return ['params', 'delay'];

			case ITEM_TYPE_DB_MONITOR:
				return ['username', 'password', 'params', 'delay'];

			case ITEM_TYPE_DEPENDENT:
				return ['master_itemid'];

			case ITEM_TYPE_EXTERNAL:
				return ['interfaceid', 'delay'];

			case ITEM_TYPE_HTTPAGENT:
				return ['url', 'query_fields', 'request_method', 'timeout', 'post_type', 'posts', 'headers',
					'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy', 'authtype',
					'username', 'password', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
					'ssl_key_password', 'interfaceid', 'delay', 'allow_traps', 'trapper_hosts'
				];

			case ITEM_TYPE_INTERNAL:
				return ['delay'];

			case ITEM_TYPE_IPMI:
				return ['interfaceid', 'ipmi_sensor', 'delay'];

			case ITEM_TYPE_JMX:
				return ['interfaceid', 'jmx_endpoint', 'username', 'password', 'delay'];

			case ITEM_TYPE_SCRIPT:
				return ['parameters', 'params', 'timeout', 'delay'];

			case ITEM_TYPE_SIMPLE:
				return ['interfaceid', 'username', 'password', 'delay'];

			case ITEM_TYPE_SNMP:
				return ['interfaceid', 'snmp_oid', 'delay'];

			case ITEM_TYPE_SNMPTRAP:
				return ['interfaceid'];

			case ITEM_TYPE_SSH:
				return ['interfaceid', 'authtype', 'username', 'publickey', 'privatekey', 'password', 'paams', 'delay'];

			case ITEM_TYPE_TELNET:
				return ['interfaceid', 'username', 'password', 'params', 'delay'];

			case ITEM_TYPE_TRAPPER:
				return ['trapper_hosts'];

			case ITEM_TYPE_ZABBIX:
				return ['interfaceid', 'delay'];

			case ITEM_TYPE_ZABBIX_ACTIVE:
				return ['delay'];
		}

		return [];
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

		if (!array_key_exists('delay_flex', $item)) {
			return $delay;
		}

		/*
		* The 'delay_flex' is a temporary field that contains flexible and scheduling intervals,
		* separated by a semicolon.
		* These custom intervals together with the 'delay' part itself are combined to a single string.
		*/
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

		return $delay;
	}

	/**
	 * Attempt to return an input value by key(s).
	 *
	 * @param array $array
	 * @param array $path     String/index or array thereof for nested access.
	 * @param mixed $default  Fallback value eturned if path was not matched.
	 *
	 * @return mixed
	 */
	public static function getInput(array $array, array $path, $default = null) {
		$key = array_shift($path);

		if (!array_key_exists($key, $array)) {
			return $default;
		}

		if (!$path) {
			return $array[$key];
		}
		elseif (!is_array($array[$key])) {
			return $default;
		}

		return self::getInput($array[$key], $path, $default);
	}
}
