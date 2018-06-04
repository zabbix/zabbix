<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
 * Class containing methods for operations with trends.
 */
class CTrend extends CApiService {

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
		$default_options = [
			'itemids'		=> null,
			// filter
			'time_from'		=> null,
			'time_till'		=> null,
			// output
			'output'		=> API_OUTPUT_EXTEND,
			'countOutput'	=> false,
			'limit'			=> null
		];

		$options = zbx_array_merge($default_options, $options);

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
				$history_source = CHistoryManager::getDataSourceType($item['value_type']);
				$storage_items[$history_source][$item['value_type']][$item['itemid']] = true;
			}
		}

		foreach ([ZBX_HISTORY_SOURCE_ELASTIC, ZBX_HISTORY_SOURCE_SQL] as $source) {
			if (array_key_exists($source, $storage_items)) {
				$options['itemids'] = $storage_items[$source];

				switch ($source) {
					case ZBX_HISTORY_SOURCE_ELASTIC:
						$data = $this->getFromElasticsearch($options);
						break;

					default:
						$data = $this->getFromSql($options);
				}

				if (is_array($result)) {
					$result = array_merge($result, $data);
				}
				else {
					$result += $data;
				}
			}
		}

		return $result;
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

			foreach ([ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] as $value_type) {
				if ($sql_limit !== null && $sql_limit <= 0) {
					break;
				}

				$sql_from = ($value_type == ITEM_VALUE_TYPE_FLOAT) ? 'trends' : 'trends_uint';

				if ($options['itemids'][$value_type]) {
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($options['itemids'][$value_type]));

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
			}

			$result = $this->unsetExtraFields($result, ['itemid'], $options['output']);
		}
		else {
			$result = 0;

			foreach ([ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] as $value_type) {
				if ($options['itemids'][$value_type]) {
					$sql_from = ($value_type == ITEM_VALUE_TYPE_FLOAT) ? 'trends' : 'trends_uint';
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($options['itemids'][$value_type]));

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
								'min_doc_count' => 1,
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

		foreach (CHistoryManager::getElasticsearchEndpoints($value_types) as $type => $endpoint) {
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
}
