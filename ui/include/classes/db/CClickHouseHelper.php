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
		], $options);

		$query = (new CClickHouseQuery())
			->from($table, $table_alias)
			->limit($options['limit']);

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

		foreach ($filter as $field => ['type' => $type]) {
			$values = $options['filter'][$field];
			$values_prepared = [];

			switch ($type) {
				case 'Int8':
				case 'Int16':
				case 'Int32':
				case 'Int64':
				case 'Int128':
				case 'Int256':
				case 'UInt8':
				case 'UInt16':
				case 'UInt32':
				case 'UInt64':
				case 'UInt128':
				case 'UInt256':
					foreach ($values as $value) {
						if (!is_int($value) && (!is_string($value) || !preg_match('/^'.ZBX_PREG_INT.'$/', $value))) {
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

			$query->param('filter_'.$field, $values_prepared);
			$filter_prepared[$field] = $table_alias.'.'.$field.' IN {filter_'.$field.':Array('.$type.')}';
		}

		$where = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter_prepared);

		if ($options['searchByAny'] && count($filter_prepared) > 1) {
			$where = '('.$where.')';
		}

		$query->where($where);
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

		$output = array_intersect(array_keys($table_schema), $options['output']);

		foreach ($output as $field) {
			$query->select($table_alias.'.'.$field);
		}
	}

	public static function addAttributeFilter(CClickHouseQuery $query, string $field, array $list, int $eval_type)
			: void {
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

			$query->param('filter_'.$index.'_key', $key);

			$field_param = $field.'[{filter_'.$index.'_key:String}]';

			$where_or = [];
			$where_and = [];

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_EQUAL, $operators)) {
				$query->param('filter_'.$index.'_equal', $operators[APM_ATTRIBUTE_OPERATOR_EQUAL]);
				$where_or[] = $field_param.' IN {filter_'.$index.'_equal:Array(String)}';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_LIKE, $operators)) {
				foreach ($operators[APM_ATTRIBUTE_OPERATOR_LIKE] as $value_index => $value) {
					$query->param('filter_'.$index.'_like_'.$value_index, $value);
					$where_or[] = 'positionCaseInsensitive('.$field_param.','.
						'{filter_'.$index.'_like_'.$value_index.':String})>0';
				}
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EXISTS, $operators)) {
				$where_or[] = 'NOT mapContains('.$field.',{filter_'.$index.'_key:String})';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, $operators)) {
				$query->param('filter_'.$index.'_not_equal', $operators[APM_ATTRIBUTE_OPERATOR_NOT_EQUAL]);
				$where_and[] = $field_param.' NOT IN {filter_'.$index.'_not_equal:Array(String)}';
			}

			if (array_key_exists(APM_ATTRIBUTE_OPERATOR_NOT_LIKE, $operators)) {
				foreach ($operators[APM_ATTRIBUTE_OPERATOR_NOT_LIKE] as $value_index => $value) {
					$query->param('filter_'.$index.'_like_'.$value_index, $value);
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
			$query->where(implode($eval_type === APM_ATTRIBUTE_EVAL_TYPE_AND_OR ? ' AND ' : ' OR ', $where));
		}
	}
}
