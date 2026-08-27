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


class CClickHouseHelper {

	public static function getQueryParts(string $table, string $table_alias): array {
		return [
			'select'	=> [],
			'from'		=> [$table.' '.$table_alias],
			'join'		=> [],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null,
			'param'		=> []
		];
	}

	public static function getQueryPartsFromOptions(string $table, string $table_alias, array $db_schema,
			array $options): array {
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
		], $options);

		$query_parts = static::getQueryParts($table, $table_alias);

		$query_parts['limit'] = $options['limit'];

		if ($options['filter']) {
			$query_parts = static::addQueryFilterOptions($query_parts, $options, $db_schema, $table, $table_alias);
		}

		if ($options['search']) {
			$query_parts = static::addQuerySearchOptions($query_parts, $options, $db_schema, $table, $table_alias);
		}

		if ($options['countOutput']) {
			$query_parts['select'] = ['rowscount' => 'count()'];

			return $query_parts;
		}

		if ($options['sortfield']) {
			$query_parts = static::addQuerySortOptions($query_parts, $options, $db_schema, $table, $table_alias);
		}

		if ($options['output']) {
			$query_parts = static::addQueryOutputOptions($query_parts, $options, $db_schema, $table, $table_alias);
		}

		return $query_parts;
	}

	private static function addQueryFilterOptions(array $query_parts, array $options, array $db_schema, string $table,
			string $table_alias): array {
		$table_schema = $db_schema[$table];

		$filter = array_intersect_key($table_schema, $options['filter']);

		if (!$filter) {
			return $query_parts;
		}

		$filter_prepared = [];

		foreach ($filter as $field => ['type' => $field_type]) {
			$values = $options['filter'][$field];
			$values_prepared = [];

			switch ($field_type) {
				case 'Int32':
					foreach ($values as $value) {
						if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
							continue;
						}

						if ($value < ZBX_MIN_INT32 || $value > ZBX_MAX_INT32) {
							continue;
						}

						$values_prepared[] = $value;
					}
					break;

				case 'Int64':
					foreach ($values as $value) {
						if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
							continue;
						}

						if ($value < 0 || bccomp((string) $value, ZBX_MAX_INT64) > 0) {
							continue;
						}

						$values_prepared[] = $value;
					}
					break;

				case 'UInt64':
					foreach ($values as $value) {
						if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
							continue;
						}

						if ($value < 0 || bccomp((string) $value, ZBX_MAX_UINT64) > 0) {
							continue;
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
					$values_prepared = $values;
					break;

				default:
					throw new InvalidArgumentException();
			}

			$query_parts['param']['filter_'.$field] = $values_prepared;
			$filter_prepared[$field] = $table_alias.'.'.$field.' IN {filter_'.$field.':Array('.$field_type.')}';
		}

		$where = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter_prepared);

		if ($options['searchByAny'] && count($filter_prepared) > 1) {
			$where = '('.$where.')';
		}

		$query_parts['where'][] = $where;

		return $query_parts;
	}

	private static function addQuerySearchOptions(array $query_parts, array $options, array $db_schema, string $table,
			string $table_alias): array {
		$table_schema = $db_schema[$table];

		$search = array_intersect_key($table_schema, $options['search']);

		if (!$search) {
			return $query_parts;
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

			$query_parts['param']['search_'.$field] = $patterns;
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

		$query_parts['where'][] = $where;

		return $query_parts;
	}

	private static function addQuerySortOptions(array $query_parts, array $options, array $db_schema, string $table,
			string $table_alias): array {
		$table_schema = $db_schema[$table];

		foreach ($options['sortfield'] as $i => $field) {
			if (!array_key_exists($field, $table_schema)) {
				continue;
			}

			$sortorder = $options['sortorder'];
			$sortorder = match(true) {
				is_string($sortorder) => $sortorder,
				is_array($sortorder) && array_key_exists($i, $sortorder) => $sortorder[$i],
				default => ZBX_SORT_UP
			};

			$query_parts['order'][] = $table_alias.'.'.$field.($sortorder === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '');
		}

		return $query_parts;
	}

	private static function addQueryOutputOptions(array $query_parts, array $options, array $db_schema, string $table,
			string $table_alias): array {
		$table_schema = $db_schema[$table];

		$output = array_intersect(array_keys($table_schema), $options['output']);

		foreach ($output as $field) {
			$query_parts['select'][] = $table_alias.'.'.$field;
		}

		return $query_parts;
	}

	public static function buildQueryFromParts(array $query_parts): string {
		$select = array_map(
			static fn ($field, $expression) => $expression.(is_string($field) ? ' AS '.$field : ''),
			array_keys($query_parts['select']),
			$query_parts['select']
		);

		return
			'SELECT '.implode(',', $select).
			' FROM '.implode(',', $query_parts['from']).
			($query_parts['join'] ? ' '.implode(' ', $query_parts['join']) : '').
			($query_parts['where'] ? ' WHERE '.implode(' AND ', $query_parts['where']) : '').
			($query_parts['group'] ? ' GROUP BY '.implode(',', $query_parts['group']) : '').
			($query_parts['order'] ? ' ORDER BY '.implode(',', $query_parts['order']) : '').
			($query_parts['limit'] !== null ? ' LIMIT '.$query_parts['limit'] : '');
	}

	public static function addAttributeFilter(array $query_parts, string $field, array $list, int $eval_type): array {
		$list_grouped = [];

		foreach ($list as $attribute) {
			$list_grouped[$attribute['key']][$attribute['operator']][] = $attribute['value'];
		}

		$where = [];

		$index = 0;

		foreach ($list_grouped as $key => $operators) {
			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_EXISTS, $operators)
					&& array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EXISTS, $operators)) {
				continue;
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_EQUAL, $operators)
					|| array_key_exists(APM_ATTRIBUTE_OPERATOR_LIKE, $operators)) {
				unset($operators[APM_ATTRIBUTE_OPERATOR_EXISTS]);
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, $operators)
					|| array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_LIKE, $operators)) {
				unset($operators[APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]);
			}

			$query_parts['param']['filter_'.$index.'_key'] = $key;

			$field_param = $field.'[{filter_'.$index.'_key:String}]';

			$where_or = [];
			$where_and = [];

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_EQUAL, $operators)) {
				$query_parts['param']['filter_'.$index.'_equal'] = $operators[APM_ATTRIBUTE_OPERATOR_EQUAL];
				$where_or[] = $field_param.' IN {filter_'.$index.'_equal:Array(String)}';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_LIKE, $operators)) {
				foreach ($operators[APM_ATTRIBUTE_OPERATOR_LIKE] as $value_index => $value) {
					$query_parts['param']['filter_'.$index.'_like_'.$value_index] = $value;
					$where_or[] = 'positionCaseInsensitive('.$field_param.','.
						'{filter_'.$index.'_like_'.$value_index.':String})>0';
				}
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EXISTS, $operators)) {
				$where_or[] = 'NOT mapContains('.$field.',{filter_'.$index.'_key:String})';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, $operators)) {
				$query_parts['param']['filter_'.$index.'_not_equal'] = $operators[APM_ATTRIBUTE_OPERATOR_NOT_EQUAL];
				$where_and[] = $field_param.' NOT IN {filter_'.$index.'_not_equal:Array(String)}';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_LIKE, $operators)) {
				foreach ($operators[APM_ATTRIBUTE_OPERATOR_NOT_LIKE] as $value_index => $value) {
					$query_parts['param']['filter_'.$index.'_like_'.$value_index] = $value;
					$where_and[] = 'positionCaseInsensitive('.$field_param.','.
						'{filter_'.$index.'_like_'.$value_index.':String})=0';
				}
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_EXISTS, $operators)) {
				$where_or[] = 'mapContains('.$field.',{filter_'.$index.'_key:String})';
			}

			if ($where_or) {
				$where_or_sql = implode(' OR ', $where_or);
				$where_or_sql = count($where_or) > 1 ? '('.$where_or_sql.')' : $where_or_sql;

				$where_and[] = $where_or_sql;
			}

			$where[] = implode(' AND ', $where_and);
		}

		if ($where) {
			$query_parts['where'][] = implode($eval_type === APM_ATTRIBUTE_EVAL_TYPE_AND_OR ? ' AND ' : ' OR ', $where);
		}

		return $query_parts;
	}
}
