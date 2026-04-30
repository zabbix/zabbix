<?php
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


/**
 * Class containing methods for operations with trends.
 */
class CTrend extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	public const OUTPUT_FIELDS = ['itemid', 'clock', 'num', 'value_min', 'value_avg', 'value_max'];

	public function __construct() {
		// the parent::__construct() method should not be called.
	}

	/**
	 * Get trend data.
	 *
	 * @param array $options
	 * @param int $options['time_from']
	 * @param int $options['time_till']
	 * @param int $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int trend data as array or false if error
	 */
	public function get($options = []) {
		self::validateGet($options);

		$storage_items = [];
		$result = ($options['countOutput']) ? 0 : [];

		if ($options['itemids'] === null || $options['itemids']) {
			// Check if items have read permissions.
			$items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'itemids' => $options['itemids'],
				'webitems' => true,
				'filter' => ['value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]
			]);

			foreach ($items as $item) {
				$history_source = Manager::History()->getDataSourceType($item['value_type']);
				$storage_items[$history_source][$item['value_type']][$item['itemid']] = true;
			}
		}

		foreach ([ZBX_HISTORY_SOURCE_ELASTIC, ZBX_HISTORY_SOURCE_CLICKHOUSE, ZBX_HISTORY_SOURCE_SQL] as $source) {
			if (array_key_exists($source, $storage_items)) {
				$options['itemids'] = $storage_items[$source];

				switch ($source) {
					case ZBX_HISTORY_SOURCE_ELASTIC:
						$data = $this->getFromElasticsearch($options);
						break;

					case ZBX_HISTORY_SOURCE_CLICKHOUSE:
						$data = $this->getFromClickHouse($options);
						break;

					case ZBX_HISTORY_SOURCE_SQL:
						if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 1) {
							$hk_trends = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));
							$options['time_from'] = max($options['time_from'], time() - $hk_trends + 1);
						}

						$data = $this->getFromSql($options);
						break;
				}

				if (is_array($result)) {
					$result = array_merge($result, $data);
				}
				else {
					$result += $data;
				}
			}
		}

		return is_array($result) ? $result : (string) $result;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filters.
			'itemids' =>		['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'time_from' =>		['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>		['type' => API_TIMESTAMP, 'flags' => API_ALLOW_NULL, 'default' => null],
			// Output.
			'output' =>			['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>	['type' => API_BOOLEAN, 'default' => false],
			'limit' =>			['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = self::OUTPUT_FIELDS;
		}
	}

	/**
	 * SQL specific implementation of get.
	 *
	 * @see CTrend::get
	 */
	private function getFromSql($options) {
		$sql_where = [];

		if ($options['time_from'] !== null) {
			$sql_where['clock_from'] = 't.clock>='.zbx_dbstr($options['time_from']);
		}

		if ($options['time_till'] !== null) {
			$sql_where['clock_till'] = 't.clock<='.zbx_dbstr($options['time_till']);
		}

		if (!$options['countOutput']) {
			$sql_limit = ($options['limit'] && zbx_ctype_digit($options['limit'])) ? $options['limit'] : null;

			$sql_fields = [];

			if (is_array($options['output'])) {
				foreach ($options['output'] as $field) {
					if ($this->hasField($field, 'trends') && $this->hasField($field, 'trends_uint')) {
						$sql_fields[] = 't.'.$field;
					}
				}
			}
			elseif ($options['output'] == API_OUTPUT_EXTEND) {
				$sql_fields[] = 't.*';
			}

			// An empty field set or invalid output method (string). Select only "itemid" instead of everything.
			if (!$sql_fields) {
				$sql_fields[] = 't.itemid';
			}

			$result = [];

			foreach ($options['itemids'] as $value_type => $items) {
				if ($sql_limit !== null && $sql_limit <= 0) {
					break;
				}

				$sql_from = ($value_type == ITEM_VALUE_TYPE_FLOAT) ? 'trends' : 'trends_uint';
				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($items));

				$res = DBselect(
					'SELECT '.implode(',', $sql_fields).
					' FROM '.$sql_from.' t'.
					' WHERE '.implode(' AND ', $sql_where),
					$sql_limit
				);

				while ($row = DBfetch($res)) {
					$result[] = $row;
				}

				if ($sql_limit !== null) {
					$sql_limit -= count($result);
				}
			}

			$result = $this->unsetExtraFields($result, ['itemid'], $options['output']);
		}
		else {
			$result = 0;

			foreach ($options['itemids'] as $value_type => $items) {
				$sql_from = ($value_type == ITEM_VALUE_TYPE_FLOAT) ? 'trends' : 'trends_uint';
				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($items));

				$res = DBselect(
					'SELECT COUNT(*) AS rowscount'.
					' FROM '.$sql_from.' t'.
					' WHERE '.implode(' AND ', $sql_where)
				);

				if ($row = DBfetch($res)) {
					$result += $row['rowscount'];
				}
			}
		}

		return $result;
	}

	/**
	 * Elasticsearch specific implementation of get.
	 *
	 * @see CTrend::get
	 */
	private function getFromElasticsearch($options) {
		$query_must = [];
		$value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];

		$query = [
			'aggs' => [
				'group_by_itemid' => [
					'terms' => [
						'field' => 'itemid'
					],
					'aggs' => [
						'group_by_clock' => [
							'date_histogram' => [
								'field' => 'clock',
								'interval' => '1h',
								'min_doc_count' => 1
							],
							'aggs' => [
								'max_value' => [
									'max' => [
										'field' => 'value'
									]
								],
								'avg_value' => [
									'avg' => [
										'field' => 'value'
									]
								],
								'min_value' => [
									'min' => [
										'field' => 'value'
									]
								]
							]
						]
					]
				]
			],
			'size' => 0
		];

		if ($options['time_from'] !== null) {
			$query_must[] = [
				'range' => [
					'clock' => [
						'gte' => $options['time_from']
					]
				]
			];
		}

		if ($options['time_till'] !== null) {
			$query_must[] = [
				'range' => [
					'clock' => [
						'lte' => $options['time_till']
					]
				]
			];
		}

		$limit = ($options['limit'] && zbx_ctype_digit($options['limit'])) ? $options['limit'] : null;
		$result = [];

		if ($options['countOutput']) {
			$result = 0;
		}

		foreach (Manager::History()->getElasticsearchEndpoints($value_types) as $type => $endpoint) {
			if (!array_key_exists($type, $options['itemids'])) {
				continue;
			}

			$itemids = array_keys($options['itemids'][$type]);

			if (!$itemids) {
				continue;
			}

			$query['query']['bool']['must'] = [
				'terms' => [
					'itemid' => $itemids
				]
			] + $query_must;

			$query['aggs']['group_by_itemid']['terms']['size'] = count($itemids);

			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			foreach ($data['group_by_itemid']['buckets'] as $item) {
				if (!$options['countOutput']) {
					foreach ($item['group_by_clock']['buckets'] as $histogram) {
						if ($limit !== null) {
							// Limit is reached, no need to continue.
							if ($limit <= 0) {
								break 3;
							}

							$limit--;
						}

						$result[] = [
							'itemid' => $item['key'],
							// Field key_as_string is used to get seconds instead of milliseconds.
							'clock' => $histogram['key_as_string'],
							'num' => $histogram['doc_count'],
							'min_value' => $histogram['min_value']['value'],
							'avg_value' => $histogram['avg_value']['value'],
							'max_value' => $histogram['max_value']['value']
						];
					}
				}
				else {
					$result += count($item['group_by_clock']['buckets']);
				}
			}
		}

		return $result;
	}

	/**
	 * ClickHouse specific implementation of get.
	 *
	 * @see CTrend::get
	 */
	private function getFromClickHouse(array $options) {
		$result = [];
		$rowcount = 0;

		foreach ($options['itemids'] as $value_type => $itemids) {
			/** @var CClickHouseStorage $storage */
			$storage = Manager::History()->getStorageProviderInstance($value_type);
			$values = $storage->selectTrends([
				'output' => $options['output'],
				'history' => $value_type,
				'itemids' => array_keys($itemids),
				'time_from' => $options['time_from'],
				'time_till' => $options['time_till'],
				'countOutput' => $options['countOutput'],
				'limit' => $options['limit']
			]);

			if ($storage->getErrorCode() !== null) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $storage->getErrorMessage());
			}

			if ($options['countOutput']) {
				$rowcount += $values[0]['rowscount'];
			}
			else {
				$result = array_merge($result, $values);
			}
		}

		return $options['countOutput'] ? $rowcount : $result;
	}
}
