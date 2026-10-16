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


final class CClickHouseHelper {

	public const INT8_MIN = -128;
	public const INT8_MAX = 127;
	public const INT16_MIN = -32768;
	public const INT16_MAX = 32767;
	public const INT32_MIN = -2147483648;
	public const INT32_MAX = 2147483647;
	public const INT64_MIN = -9223372036854775808;
	public const INT64_MAX = 9223372036854775807;

	public const UINT8_MAX = 255;
	public const UINT16_MAX = 65535;
	public const UINT32_MAX = 4294967295;
	public const UINT64_MAX = '18446744073709551615';

	public static function getIntMin(string $type): int {
		return match ($type) {
			'Int8' => self::INT8_MIN,
			'Int16' => self::INT16_MIN,
			'Int32' => self::INT32_MIN,
			'Int64' => self::INT64_MIN,
			'UInt8', 'UInt16', 'UInt32', 'UInt64' => 0
		};
	}

	public static function getIntMax(string $type): int|string {
		return match ($type) {
			'Int8' => self::INT8_MAX,
			'Int16' => self::INT16_MAX,
			'Int32' => self::INT32_MAX,
			'Int64' => self::INT64_MAX,
			'UInt8' => self::UINT8_MAX,
			'UInt16' => self::UINT16_MAX,
			'UInt32' => self::UINT32_MAX,
			'UInt64' => self::UINT64_MAX
		};
	}

	public static function createQueryFromOptions(string $table, string $table_alias, array $db_schema, array $options)
			: CClickHouseQuery {
		$options = array_replace([
			'output' => null,
			'countOutput' => false,
			'filter' => null,
			'search' => null,
			'searchByAny' => false,
			'startSearch' => false,
			'excludeSearch' => false,
			'searchWildcardsEnabled' => false,
			'sortfield' => null,
			'sortorder' => null,
			'limit' => null,
			'offset' => null
		], $options);

		$query = (new CClickHouseQuery())->from($table, $table_alias);

		if ($options['filter']) {
			self::addQueryFilterOptions($query, $options, $db_schema, $table, $table_alias);
		}

		if ($options['search']) {
			self::addQuerySearchOptions($query, $options, $db_schema, $table, $table_alias);
		}

		if ($options['countOutput']) {
			return $query->select('count()', 'rowscount');
		}

		if ($options['sortfield']) {
			self::addQuerySortOptions($query, $options, $db_schema, $table, $table_alias);
		}

		if ($options['output']) {
			self::addQueryOutputOptions($query, $options, $db_schema, $table, $table_alias);
		}

		$query
			->limit($options['limit'])
			->offset($options['offset']);

		return $query;
	}

	private static function addQueryFilterOptions(CClickHouseQuery $query, array $options, array $db_schema,
			string $table, string $table_alias): void {
		$table_schema = $db_schema[$table];

		$filter = array_intersect_key($table_schema, $options['filter']);

		if (!$filter) {
			return;
		}

		$filter_prepared = [];
		$filter_prepared_params = [];

		$has_never_matched_fields = false;

		foreach ($filter as $field => ['type' => $type]) {
			$values = $options['filter'][$field];
			$values_prepared = [];

			$field_never_matches = false;

			switch ($type) {
				case 'Int8':
				case 'Int16':
				case 'Int32':
				case 'Int64':
				case 'UInt8':
				case 'UInt16':
				case 'UInt32':
				case 'UInt64':
					foreach ($values as $value) {
						if (!is_int($value) && (!is_string($value) || !preg_match('/^'.ZBX_PREG_INT.'$/', $value))) {
							$field_never_matches = true;
							break 2;
						}

						$value_min = self::getIntMin($type);
						$value_max = self::getIntMax($type);

						if ((is_int($value) && $value < $value_min)
								|| (is_int($value) && is_int($value_max) && $value > $value_max)
								|| bccomp((string) $value, (string) $value_min) === -1
								|| bccomp((string) $value, (string) $value_max) === 1) {
							$field_never_matches = true;
							break 2;
						}

						$values_prepared[] = $value;
					}
					break;

				case 'Float64':
					foreach ($values as $value) {
						if (!is_numeric($value)) {
							continue;
						}

						$values_prepared[] = $value;
					}
					break;

				case 'String':
					foreach ($values as $value) {
						$values_prepared[] = (string) $value;
					}

					break;

				default:
					throw new InvalidArgumentException();
			}

			if ($field_never_matches) {
				$has_never_matched_fields = true;
			}
			else {
				$filter_prepared[$field] = $table_alias.'.'.$field.' IN {filter_'.$field.':Array('.$type.')}';
				$filter_prepared_params['filter_'.$field] = $values_prepared;
			}
		}

		if ($has_never_matched_fields && (!$filter_prepared || !$options['searchByAny'])) {
			$query->where('0');

			return;
		}

		$where = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter_prepared);

		if ($options['searchByAny'] && count($filter_prepared) > 1) {
			$where = '('.$where.')';
		}

		$query
			->where($where)
			->params($filter_prepared_params);
	}

	private static function addQuerySearchOptions(CClickHouseQuery $query, array $options, array $db_schema,
			string $table, string $table_alias): void {
		$table_schema = $db_schema[$table];

		$search = array_intersect_key($table_schema, $options['search']);

		if (!$search) {
			return;
		}

		$prefix = $options['startSearch'] || $options['searchWildcardsEnabled'] ? '' : '%';
		$suffix = $options['searchWildcardsEnabled'] ? '' : '%';
		$replacements = ['%' => '\\%', '_' => '\\_', '\\' => '\\\\']
			+ ($options['searchWildcardsEnabled'] ? ['*' => '%'] : []);

		$search_prepared = [];

		foreach (array_keys($search) as $field) {
			$patterns = $options['search'][$field];

			foreach ($patterns as &$pattern) {
				$pattern = $prefix.strtr($pattern, $replacements).$suffix;
			}
			unset($pattern);

			$query->param('search_'.$field, $patterns);
			$search_prepared[$field] = $options['searchByAny']
				? 'arrayExists(p -> '.$table_alias.'.'.$field.' ILIKE p, {search_'.$field.':Array(String)})'
				: 'arrayAll(p -> '.$table_alias.'.'.$field.' ILIKE p, {search_'.$field.':Array(String)})';
		}

		$where = implode($options['searchByAny'] ? ' OR ' : ' AND ', $search_prepared);

		if (($options['searchByAny'] || $options['excludeSearch']) && count($search_prepared) > 1) {
			$where = '('.$where.')';
		}

		if ($options['excludeSearch']) {
			$where = 'NOT '.$where;
		}

		$query->where($where);
	}

	private static function addQuerySortOptions(CClickHouseQuery $query, array $options, array $db_schema,
			string $table, string $table_alias): void {
		$table_schema = $db_schema[$table];

		foreach ($options['sortfield'] as $i => $field) {
			if (!array_key_exists($field, $table_schema)) {
				continue;
			}

			$sortorder = $options['sortorder'];
			$sortorder = match (true) {
				is_string($sortorder) => $sortorder,
				is_array($sortorder) && array_key_exists($i, $sortorder) => $sortorder[$i],
				default => ZBX_SORT_UP
			};

			$query->order($table_alias.'.'.$field, $sortorder);
		}
	}

	private static function addQueryOutputOptions(CClickHouseQuery $query, array $options, array $db_schema,
			string $table, string $table_alias): void {
		$table_schema = $db_schema[$table];

		foreach (array_intersect_key($table_schema, array_flip($options['output'])) as $field => ['type' => $type]) {
			$custom_select = self::getCustomTypeSelect($type, $field, $table_alias);

			if ($custom_select !== null) {
				$query->select($custom_select, $field);
			}
			else {
				$query->select($table_alias.'.'.$field);
			}
		}
	}

	public static function getCustomTypeSelect(string $type, string $field, string $table_alias): ?string {
		return match ($type) {
			'DateTime64(9)' => 'toUnixTimestamp64Nano(toDateTime64('.$table_alias.'.'.$field.',9))',
			'Array(DateTime64(9))' =>
				'arrayMap(p -> toUnixTimestamp64Nano(toDateTime64(p,9)),'.$table_alias.'.'.$field.')',
			default => null
		};
	}

	public static function addAttributeFilter(CClickHouseQuery $query, string $field, array $list, int $eval_type)
			: void {
		$list_grouped = [];

		foreach ($list as $attribute) {
			if ($attribute['operator'] == TAG_OPERATOR_LIKE && $attribute['value'] === '') {
				$attribute['operator'] = TAG_OPERATOR_EXISTS;
			}

			$list_grouped[$attribute['key']][$attribute['operator']][] = $attribute['value'];
		}

		$where = [];

		$index = 0;

		foreach ($list_grouped as $key => $operators) {
			if (array_key_exists(TAG_OPERATOR_EXISTS, $operators)
					&& array_key_exists(TAG_OPERATOR_NOT_EXISTS, $operators)) {
				continue;
			}

			if (array_key_exists(TAG_OPERATOR_EQUAL, $operators)
					|| array_key_exists(TAG_OPERATOR_LIKE, $operators)) {
				unset($operators[TAG_OPERATOR_EXISTS]);
			}

			if (array_key_exists(TAG_OPERATOR_NOT_EQUAL, $operators)
					|| array_key_exists(TAG_OPERATOR_NOT_LIKE, $operators)) {
				unset($operators[TAG_OPERATOR_NOT_EXISTS]);
			}

			$param_prefix = 'filter_'.str_replace('.', '_', $field).'_'.$index;

			$query->param($param_prefix.'_key', $key);

			$field_param = $field.'[{'.$param_prefix.'_key:String}]';

			$where_or = [];
			$where_and = [];

			if (array_key_exists(TAG_OPERATOR_EQUAL, $operators)) {
				$query->param($param_prefix.'_equal', $operators[TAG_OPERATOR_EQUAL]);
				$where_or[] = $field_param.' IN {'.$param_prefix.'_equal:Array(String)}';
			}

			if (array_key_exists(TAG_OPERATOR_LIKE, $operators)) {
				foreach ($operators[TAG_OPERATOR_LIKE] as $value_index => $value) {
					$query->param($param_prefix.'_like_'.$value_index, $value);
					$where_or[] = 'positionCaseInsensitive('.$field_param.','.
						'{'.$param_prefix.'_like_'.$value_index.':String})>0';
				}
			}

			if (array_key_exists(TAG_OPERATOR_NOT_EXISTS, $operators)) {
				$where_or[] = 'NOT mapContains('.$field.',{'.$param_prefix.'_key:String})';
			}

			if (array_key_exists(TAG_OPERATOR_NOT_EQUAL, $operators)) {
				$query->param($param_prefix.'_not_equal', $operators[TAG_OPERATOR_NOT_EQUAL]);
				$where_and[] = $field_param.' NOT IN {'.$param_prefix.'_not_equal:Array(String)}';
			}

			if (array_key_exists(TAG_OPERATOR_NOT_LIKE, $operators)) {
				foreach ($operators[TAG_OPERATOR_NOT_LIKE] as $value_index => $value) {
					$query->param($param_prefix.'_not_like_'.$value_index, $value);
					$where_and[] = 'positionCaseInsensitive('.$field_param.','.
						'{'.$param_prefix.'_not_like_'.$value_index.':String})=0';
				}
			}

			if (array_key_exists(TAG_OPERATOR_EXISTS, $operators)) {
				$where_or[] = 'mapContains('.$field.',{'.$param_prefix.'_key:String})';
			}

			if ($where_or) {
				$where_or_sql = implode(' OR ', $where_or);
				$where_or_sql = count($where_or) > 1 ? '('.$where_or_sql.')' : $where_or_sql;

				$where_and[] = $where_or_sql;
			}

			$where[] = implode(' AND ', $where_and);

			$index++;
		}

		if ($where) {
			$where_sql = implode($eval_type === TAG_EVAL_TYPE_AND_OR ? ' AND ' : ' OR ', $where);
			$where_sql = count($where) > 1 ? '('.$where_sql.')' : $where_sql;

			$query->where($where_sql);
		}
	}
}
