<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CItemHelper extends CItemGeneralHelper {

	/**
	 * Get item fields default values.
	 */
	public static function getDefaults(): array {
		$general_fields = parent::getDefaults();

		return [
			'flags'				=> ZBX_FLAG_DISCOVERY_NORMAL,
			'inventory_link'	=> 0
		] + $general_fields;
	}

	/**
	 * @param string $src_templateid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	public static function cloneTemplateItems(string $src_templateid, array $dst_host): bool {
		$src_items = self::getSourceItems([
			'templateids' => $src_templateid,
			'inherited' => false
		]);

		$dst_hosts = [$dst_host['templateid'] => $dst_host + ['status' => HOST_STATUS_TEMPLATE]];

		return !$src_items || self::copy($src_items, $dst_hosts);
	}

	/**
	 * @param string $src_hostid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	public static function cloneHostItems(string $src_hostid, array $dst_host): bool {
		$src_items = self::getSourceItems([
			'hostids' => $src_hostid,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		$dst_hosts = [$dst_host['hostid'] => $dst_host];

		return !$src_items || self::copy($src_items, $dst_hosts);
	}

	/**
	 * Convert API data to be ready to use for edit or create form.
	 *
	 * @param array $item  Array of API fields data.
	 */
	public static function convertApiInputForForm(array $item): array {
		$item = parent::convertApiInputForForm($item);
		$item['parent_items'] = makeItemTemplatesHtml(
			$item['itemid'],
			getItemParentTemplates([$item], ZBX_FLAG_DISCOVERY_NORMAL),
			ZBX_FLAG_DISCOVERY_NORMAL,
			CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		);
		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => false
		]);

		if ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
			$item = static::addDelayWithFlexibleIntervals($update_interval_parser, $item);
		}
		else {
			$item['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			$item['delay_flex'] = [];
		}

		if ($item['master_itemid']) {
			$master_item = API::Item()->get([
				'output' => ['itemid', 'name'],
				'itemids' => $item['master_itemid'],
				'webitems' => true
			]);
			$item['master_item'] = $master_item ? reset($master_item) : [];
		}

		return $item;
	}

	/**
	 * @param array $src_items
	 * @param array $dst_hosts
	 *
	 * @return bool
	 */
	public static function copy(array $src_items, array $dst_hosts): bool {
		$dst_valuemapids = self::getDestinationValueMaps($src_items, $dst_hosts);

		try {
			$dst_interfaceids = self::getDestinationHostInterfaces($src_items, $dst_hosts);
		}
		catch (Exception $e) {
			return false;
		}

		$src_itemids = array_fill_keys(array_keys($src_items), true);
		$src_dep_items = [];

		foreach ($src_items as $src_item) {
			if (array_key_exists($src_item['master_itemid'], $src_itemids)) {
				$src_dep_items[$src_item['master_itemid']][] = $src_item;

				unset($src_items[$src_item['itemid']]);
			}
		}

		try {
			$dst_master_itemids = self::getDestinationMasterItems($src_items, $dst_hosts, ZBX_FLAG_DISCOVERY_NORMAL);
		}
		catch (Exception $e) {
			return false;
		}

		do {
			$dst_items = [];

			foreach ($dst_hosts as $dst_hostid => $dst_host) {
				foreach ($src_items as $src_item) {
					$dst_item = array_diff_key($src_item, array_flip(['itemid', 'hosts']));

					if (array_key_exists($src_item['itemid'], $dst_valuemapids)) {
						$dst_item['valuemapid'] = $dst_valuemapids[$src_item['itemid']][$dst_hostid];
					}

					if (array_key_exists($src_item['itemid'], $dst_interfaceids)) {
						$dst_item['interfaceid'] = $dst_interfaceids[$src_item['itemid']][$dst_hostid];
					}

					if (array_key_exists($src_item['itemid'], $dst_master_itemids)) {
						$dst_item['master_itemid'] = $dst_master_itemids[$src_item['itemid']][$dst_hostid];
					}

					$dst_items[] = ['hostid' => $dst_hostid] + getSanitizedItemFields([
						'templateid' => 0,
						'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
						'hosts' => [$dst_host]
					] + $dst_item);

				}
			}

			$response = API::Item()->create($dst_items);

			if ($response === false) {
				return false;
			}

			$_src_items = [];

			if ($src_dep_items) {
				foreach ($dst_hosts as $dst_hostid => $foo) {
					foreach ($src_items as $src_item) {
						$dst_itemid = array_shift($response['itemids']);

						if (array_key_exists($src_item['itemid'], $src_dep_items)) {
							foreach ($src_dep_items[$src_item['itemid']] as $src_dep_item) {
								$dst_master_itemids[$src_dep_item['itemid']][$dst_hostid] = $dst_itemid;
							}
						}
					}
				}

				foreach ($src_items as $src_item) {
					if (array_key_exists($src_item['itemid'], $src_dep_items)) {
						$_src_items = array_merge($_src_items, $src_dep_items[$src_item['itemid']]);
						unset($src_dep_items[$src_item['itemid']]);
					}
				}
			}

			$src_items = $_src_items;
		} while ($src_items);

		return true;
	}

	/**
	 * @param array  $src_options
	 *
	 * @return array
	 */
	public static function getSourceItems(array $src_options): array {
		return API::Item()->get([
			'output' => ['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status',

				// Type fields.
				// The fields used for multiple item types.
				'interfaceid', 'authtype', 'username', 'password', 'params', 'timeout', 'delay', 'trapper_hosts',

				// Dependent item type specific fields.
				'master_itemid',

				// HTTP Agent item type specific fields.
				'url', 'query_fields', 'request_method', 'post_type', 'posts',
				'headers', 'status_codes', 'follow_redirects', 'retrieve_mode', 'output_format', 'http_proxy',
				'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'allow_traps',

				// IPMI item type specific fields.
				'ipmi_sensor',

				// JMX item type specific fields.
				'jmx_endpoint',

				// Script item type specific fields.
				'parameters',

				// SNMP item type specific fields.
				'snmp_oid',

				// SSH item type specific fields.
				'publickey', 'privatekey'
			],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'selectHosts' => ['status'],
			'preservekeys' => true
		] + $src_options);
	}

	/**
	 * Get translated name of aggregate function.
	 *
	 * @param int $function
	 *
	 * @return string
	 */
	public static function getAggregateFunctionName(int $function): string {
		static $names;

		if ($names === null) {
			$names = [
				AGGREGATE_NONE => _('not used'),
				AGGREGATE_MIN => _('min'),
				AGGREGATE_MAX => _('max'),
				AGGREGATE_AVG => _('avg'),
				AGGREGATE_COUNT => _('count'),
				AGGREGATE_SUM => _('sum'),
				AGGREGATE_FIRST => _('first'),
				AGGREGATE_LAST => _('last')
			];
		}

		return $names[$function];
	}

	/**
	 * Resolve string representation of the aggregate function into AGGREGATE_* constant.
	 *
	 * @param string $name
	 *
	 * @return int
	 */
	public static function resolveAggregateFunction(string $name): int {
		static $functions = [
			'min' => AGGREGATE_MIN,
			'max' => AGGREGATE_MAX,
			'avg' => AGGREGATE_AVG,
			'count' => AGGREGATE_COUNT,
			'sum' => AGGREGATE_SUM,
			'first' => AGGREGATE_FIRST,
			'last' => AGGREGATE_LAST
		];

		return $functions[$name];
	}

	/**
	 * Add 'source' property to items ('history' or 'trends'), for the specified time stamp, based on item configuration
	 * and housekeeping settings.
	 *
	 * Items must have 'history' and 'trends' properties set.
	 *
	 * @param array $items  Array of items.
	 * @param int   $time   Unix time stamp to calculate data source for.
	 *
	 * @return array  Items with updated 'history' and 'trends' properties, as well as 'source' property set.
	 */
	public static function addDataSource(array $items, int $time): array {
		$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['history', 'trends']);

		foreach ($items as &$item) {
			[
				'keep_history' => $item['history'],
				'history_has_errors' => $history_has_errors,
				'keep_trends' => $item['trends'],
				'trends_has_errors' => $trends_has_errors
			] = CItemHelper::getStoragePeriods((int) $item['value_type'], $item['history'], $item['trends']);

			if ($history_has_errors) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'history',
					_('invalid history storage period')
				));
			}

			if ($trends_has_errors) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'trends',
					_('invalid trend storage period')
				));
			}

			if ($item['history'] === null) {
				$item['history'] = 25 * SEC_PER_YEAR;
			}

			if ($item['trends'] === null) {
				$item['trends'] = 25 * SEC_PER_YEAR;
			}

			$item['source'] = $item['trends'] == 0 || time() - $item['history'] <= $time ? 'history' : 'trends';
		}
		unset($item);

		return $items;
	}

	/**
	 * Returns the actual history and trend retention period, taking into account global settings,
	 * external storage settings, as well as item settings.
	 *
	 * The returned array contains the following keys:
	 *   string   history, trends                       - Human-readable history/trends retention period.
	 *   int|null keep_history, keep_trends             - History/trends retention period in seconds. In case of a
	 *                                                    parsing error or if the retention period cannot be retrieved
	 *                                                    from the external storage, null is returned.
	 *   bool     history_has_errors, trends_has_errors - true in case of a parsing error.
	 *
	 * @param int    $value_type
	 * @param string $history
	 * @param string $trends
	 *
	 * @return array
	 */
	public static function getStoragePeriods(int $value_type, string $history, string $trends): array {
		// History retention period in external storages such as ClickHouse or Elasticsearch.
		static $value_type_ttl = Manager::History()->getValueTypesStorageTtls();
		static $value_types_with_trends = CHousekeepingHelper::getValueTypesWithTrends();
		static $hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);

		$simple_interval_parser = new CSimpleIntervalParser();
		$keep_history = $simple_interval_parser->parse($history) == CParser::PARSE_SUCCESS
			? timeUnitToSeconds($history)
			: null;
		$history_has_errors = false;
		$trends_has_errors = false;

		if ($keep_history !== 0 && array_key_exists($value_type, $value_type_ttl)) {
			$keep_history = $value_type_ttl[$value_type]['value_ttl'];
			$history = $keep_history !== null ? convertSecondsToTimeUnits($keep_history) : '';
		}
		elseif ($keep_history !== 0 && $hk_history_global == 1) {
			static $hk_history = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY);

			$history = $hk_history;
			$keep_history = timeUnitToSeconds($history);
		}
		elseif ($keep_history === null) {
			$history_has_errors = true;
		}

		if (in_array($value_type, $value_types_with_trends)) {
			static $hk_trends_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL);

			$keep_trends = $simple_interval_parser->parse($trends) == CParser::PARSE_SUCCESS
				? timeUnitToSeconds($trends)
				: null;

			if ($keep_trends !== 0 && $hk_trends_global == 1) {
				static $hk_trends = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS);

				$trends = $hk_trends;
				$keep_trends = timeUnitToSeconds($trends);
			}
			elseif ($keep_trends === null) {
				$trends_has_errors = true;
			}
		}
		else {
			$trends = '';
			$keep_trends = 0;
		}

		return [
			'history' => $history,
			'keep_history' => $keep_history,
			'history_has_errors' => $history_has_errors,
			'trends' => $trends,
			'keep_trends' => $keep_trends,
			'trends_has_errors' => $trends_has_errors
		];
	}
}
