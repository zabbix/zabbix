<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	public function get($options = []) {
		$result = [];

		$default_options = [
			'itemids'					=> null,
			// filter
			'time_from'					=> null,
			'time_till'					=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> null,
			'limit'						=> null
		];

		$options = zbx_array_merge($default_options, $options);

		// Check if items have read permissions.
		$items = API::Item()->get([
			'output' => ['itemid', 'value_type'],
			'itemids' => $options['itemids'],
			'filter' => ['value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]
		]);

		if (!$items) {
			return ($options['countOutput'] === null) ? [] : 0;
		}

		$float_itemids = [];
		$uint_itemids = [];

		foreach ($items as $item) {
			if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) {
				$float_itemids[$item['itemid']] = true;
			}
			else {
				$uint_itemids[$item['itemid']] = true;
			}
		}

		$sql_where = [];

		if ($options['time_from'] !== null) {
			$sql_where['clock_from'] = 't.clock>='.zbx_dbstr($options['time_from']);
		}

		if ($options['time_till'] !== null) {
			$sql_where['clock_till'] = 't.clock<='.zbx_dbstr($options['time_till']);
		}

		if ($options['countOutput'] === null) {
			$sql_limit  = (zbx_ctype_digit($options['limit']) && $options['limit']) ? $options['limit'] : null;

			// Always select at least "itemid" field.
			$sql_fields = 'itemid,';

			if (is_array($options['output'])) {
				foreach ($options['output'] as $field) {
					if ($this->hasField($field, 'trends') && $this->hasField($field, 'trends_uint')
							&& $field !== 'itemid') {
						$sql_fields .= $field.',';
					}
				}
				$sql_fields = substr($sql_fields, 0, -1);
			}
			elseif ($options['output'] == API_OUTPUT_EXTEND) {
				$sql_fields .= '*';
			}

			$cnt = 0;

			// Select data from "trends".
			if ($float_itemids) {
				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($float_itemids));

				$res = DBselect(
					'SELECT t.'.$sql_fields.
					' FROM trends AS t'.
					' WHERE '.implode(' AND ', $sql_where),
					$sql_limit
				);

				while ($data = DBfetch($res)) {
					$result[] = $data;
				}

				$cnt = count($result);
			}

			$sql_limit -= $cnt;

			// Select data from "trends_uint" and merge results.
			if ($uint_itemids && $sql_limit > 0) {
				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($uint_itemids));

				$res = DBselect(
					'SELECT t.'.$sql_fields.
					' FROM trends_uint AS t'.
					' WHERE '.implode(' AND ', $sql_where),
					$sql_limit
				);

				while ($data = DBfetch($res)) {
					$result[] = $data;
				}
			}
		}
		else {
			if ($float_itemids && $uint_itemids) {
				// Select data from both "trends" and "trends_uint" tables.

				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($float_itemids));

				$sql = 'SELECT ('.
					'SELECT COUNT(t.itemid)'.
					' FROM trends AS t'.
					' WHERE '.implode(' AND ', $sql_where);

				$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($uint_itemids));

				$sql .= ') + ('.
						'SELECT COUNT(t.itemid)'.
						' FROM trends_uint AS t'.
						' WHERE '.implode(' AND ', $sql_where).
					') AS rowscount FROM dual';

				$res = DBselect($sql);
			}
			else {
				// Select data from either one of the tables.

				if ($float_itemids) {
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($float_itemids));
					$sql_from = 'trends';
				}
				else {
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($uint_itemids));
					$sql_from = 'trends_uint';

				}

				$res = DBselect(
					'SELECT COUNT(t.itemid) AS rowscount'.
					' FROM '.$sql_from.' AS t'.
					' WHERE '.implode(' AND ', $sql_where)
				);
			}

			while ($data = DBfetch($res)) {
				$result = $data['rowscount'];
			}
		}

		return $result;
	}
}
