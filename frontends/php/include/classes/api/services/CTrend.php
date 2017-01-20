<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$default_options = [
			'itemids'		=> null,
			// filter
			'time_from'		=> null,
			'time_till'		=> null,
			// output
			'output'		=> API_OUTPUT_EXTEND,
			'countOutput'	=> null,
			'limit'			=> null
		];

		$options = zbx_array_merge($default_options, $options);

		$itemids = ['trends' => [], 'trends_uint' => []];

		if ($options['itemids'] === null || $options['itemids']) {
			// Check if items have read permissions.
			$items = API::Item()->get([
				'output' => ['itemid', 'value_type'],
				'itemids' => $options['itemids'],
				'webitems' => true,
				'filter' => ['value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]]
			]);

			foreach ($items as $item) {
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT) ? 'trends' : 'trends_uint';
				$itemids[$sql_from][$item['itemid']] = true;
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

			foreach (['trends', 'trends_uint'] as $sql_from) {
				if ($sql_limit !== null && $sql_limit <= 0) {
					break;
				}

				if ($itemids[$sql_from]) {
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($itemids[$sql_from]));

					$res = DBselect(
						'SELECT '.implode(',', $sql_fields).
						' FROM '.$sql_from.' AS t'.
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

			foreach (['trends', 'trends_uint'] as $sql_from) {
				if ($itemids[$sql_from]) {
					$sql_where['itemid'] = dbConditionInt('t.itemid', array_keys($itemids[$sql_from]));

					$res = DBselect(
						'SELECT COUNT(*) AS rowscount'.
						' FROM '.$sql_from.' AS t'.
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
}
