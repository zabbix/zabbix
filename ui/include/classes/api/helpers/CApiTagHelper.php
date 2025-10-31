<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Helper class containing methods for operations with tags.
 */
class CApiTagHelper {

	private const SUBQUERY_TYPE_EXISTS = 'EXISTS';
	private const SUBQUERY_TYPE_NOT_EXISTS = 'NOT EXISTS';

	public static function getTagCondition(array $tags, int $evaltype, array $parent_aliases, string $table,
			string $field, bool $inherited_tags = false): string {
		if (!$tags) {
			return '1=0';
		}

		$query_options = [
			'evaltype' => $evaltype,
			'parent_aliases' => $parent_aliases,
			'table' => $table,
			'field' => $field,
			'inherited_tags' => $inherited_tags
		];

		$sql_where = [];

		foreach (self::getGroupedTags($tags) as $tag => $operator_values) {
			$subqueries = self::getTagSubqueriesByExistsOperator($tag, $operator_values, $query_options);

			if (!$subqueries) {
				$subqueries = array_merge(
					self::getTagSubqueriesByLikeOrEqualOperator($tag, $operator_values, $query_options),
					self::getTagSubqueriesByNotExistsOperator($tag, $operator_values, $query_options),
					self::getTagSubqueriesByNotLikeOrNotEqualOperator($tag, $operator_values, $query_options)
				);
			}

			if ($evaltype == TAG_EVAL_TYPE_AND_OR) {
				if (!array_diff_key($operator_values, array_flip([TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL]))
						|| !array_diff_key($operator_values, array_flip([TAG_OPERATOR_NOT_EXISTS]))) {
					$sql_where[] = implode(' AND ', $subqueries);
				}
				else {
					$sql_where[] = count($subqueries) == 1 ? $subqueries[0] : '('.implode(' OR ', $subqueries).')';
				}
			}
			else {
				$sql_where = array_merge($sql_where, $subqueries);
			}
		}

		$sql = implode($evaltype == TAG_EVAL_TYPE_AND_OR ? ' AND ' : ' OR ', $sql_where);

		if ($evaltype == TAG_EVAL_TYPE_OR && count($sql_where) > 1) {
			$sql = '('.$sql.')';
		}

		return $sql;
	}

	private static function getGroupedTags(array $tags): array {
		$grouped_tags = [];

		foreach ($tags as $tag) {
			if ($tag['operator'] == TAG_OPERATOR_NOT_LIKE && $tag['value'] === '') {
				$tag['operator'] = TAG_OPERATOR_NOT_EXISTS;
			}
			elseif ($tag['operator'] == TAG_OPERATOR_LIKE && $tag['value'] === '') {
				$tag['operator'] = TAG_OPERATOR_EXISTS;
			}

			if (in_array($tag['operator'], [TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS])) {
				$grouped_tags[$tag['tag']][$tag['operator']] = true;
			}
			elseif (in_array($tag['operator'], [TAG_OPERATOR_LIKE, TAG_OPERATOR_NOT_LIKE])) {
				$grouped_tags[$tag['tag']][$tag['operator']][mb_strtoupper($tag['value'])] = true;
			}
			else {
				$grouped_tags[$tag['tag']][$tag['operator']][$tag['value']] = true;
			}
		}

		// The tag operator TAG_OPERATOR_EXISTS overrides filters with other operators within the tag.
		foreach ($grouped_tags as &$operator_values) {
			if (array_key_exists(TAG_OPERATOR_EXISTS, $operator_values)) {
				$operator_values = [TAG_OPERATOR_EXISTS => true];
			}
		}
		unset($operator_values);

		return $grouped_tags;
	}

	private static function getTagSubqueriesByExistsOperator(string $tag, array $operator_values,
			array $query_options): array {
		$subqueries = [];

		if (array_key_exists(TAG_OPERATOR_EXISTS, $operator_values)) {
			$tag_table_subqueries = self::getTagTableSubqueries($tag, self::SUBQUERY_TYPE_EXISTS, $query_options);

			foreach ($tag_table_subqueries as $subquery_sql) {
				$subqueries[] = self::getTagSubquery(self::SUBQUERY_TYPE_EXISTS, $subquery_sql);
			}
		}

		return $subqueries;
	}

	private static function getTagSubqueriesByLikeOrEqualOperator(string $tag, array $operator_values,
			array $query_options): array {
		if (!array_key_exists(TAG_OPERATOR_LIKE, $operator_values)
				&& !array_key_exists(TAG_OPERATOR_EQUAL, $operator_values)) {
			return [];
		}

		$tag_table_subqueries = self::getTagTableSubqueries($tag, self::SUBQUERY_TYPE_EXISTS, $query_options);

		$value_conditions = [];

		self::supplementWithLikeConditions($value_conditions, $operator_values, TAG_OPERATOR_LIKE,
			$tag_table_subqueries
		);
		self::supplementWithEqualConditions($value_conditions, $operator_values, TAG_OPERATOR_EQUAL,
			$tag_table_subqueries
		);

		$subqueries = [];

		foreach ($value_conditions as $table => $conditions) {
			$subqueries[] =
				self::getTagSubquery(self::SUBQUERY_TYPE_EXISTS, $tag_table_subqueries[$table], $conditions);
		}

		return $subqueries;
	}

	private static function getTagSubqueriesByNotExistsOperator(string $tag, array $operator_values,
			array $query_options): array {
		if (array_key_exists(TAG_OPERATOR_NOT_EXISTS, $operator_values)) {
			$tag_table_subqueries = self::getTagTableSubqueries($tag, self::SUBQUERY_TYPE_NOT_EXISTS, $query_options);

			$subqueries = [];

			foreach ($tag_table_subqueries as $subquery_sql) {
				$subqueries[] = self::getTagSubquery(self::SUBQUERY_TYPE_NOT_EXISTS, $subquery_sql);
			}

			if ($query_options['evaltype'] == TAG_EVAL_TYPE_AND_OR
					&& !array_diff_key($operator_values, array_flip([TAG_OPERATOR_NOT_EXISTS]))) {
				return $subqueries;
			}

			return count($subqueries) == 1 ? $subqueries : ['('.implode(' AND ', $subqueries).')'];
		}

		return [];
	}

	private static function getTagSubqueriesByNotLikeOrNotEqualOperator(string $tag, array $operator_values,
			array $query_options): array {
		if (!array_key_exists(TAG_OPERATOR_NOT_LIKE, $operator_values)
				&& !array_key_exists(TAG_OPERATOR_NOT_EQUAL, $operator_values)) {
			return [];
		}

		$tag_table_subqueries = self::getTagTableSubqueries($tag, self::SUBQUERY_TYPE_NOT_EXISTS, $query_options);

		$value_conditions = [];

		self::supplementWithLikeConditions($value_conditions, $operator_values, TAG_OPERATOR_NOT_LIKE,
			$tag_table_subqueries
		);
		self::supplementWithEqualConditions($value_conditions, $operator_values, TAG_OPERATOR_NOT_EQUAL,
			$tag_table_subqueries
		);

		$subqueries = [];

		foreach ($value_conditions as $table => $conditions) {
			$subqueries[] =
				self::getTagSubquery(self::SUBQUERY_TYPE_NOT_EXISTS, $tag_table_subqueries[$table], $conditions);
		}

		if ($query_options['evaltype'] == TAG_EVAL_TYPE_AND_OR) {
			if (!array_diff_key($operator_values, array_flip([TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL]))) {
				return $subqueries;
			}
		}

		return count($subqueries) > 1 ? ['('.implode(' AND ', $subqueries).')'] : $subqueries;
	}

	private static function getTagTableSubqueries(string $tag, string $subquery_type, array $query_options): array {
		[
			'parent_aliases' => $parent_aliases,
			'table' => $table,
			'field' => $field,
			'inherited_tags' => $inherited_tags
		] = $query_options;

		if ($inherited_tags && $table === 'host_tag') {
			return [
				'host_tag' =>
					'SELECT NULL'.
					' FROM host_template_cache htc'.
					' JOIN host_tag ON htc.link_hostid=host_tag.hostid'.
					' WHERE '.$parent_aliases[0].'.hostid=htc.hostid'.
						' AND host_tag.tag='.zbx_dbstr($tag)
			];
		}

		$default_subquery =
			'SELECT NULL'.
			' FROM '.$table.
			' WHERE '.$parent_aliases[0].'.'.$field.'='.$table.'.'.$field.
				' AND '.$table.'.tag='.zbx_dbstr($tag);

		if ($inherited_tags && $table === 'item_tag') {
			return [
				'item_tag' => $default_subquery,
				'host_tag' =>
					'SELECT NULL'.
					' FROM item_template_cache itc'.
					' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
					' WHERE '.$parent_aliases[0].'.itemid=itc.itemid'.
						' AND host_tag.tag='.zbx_dbstr($tag)
			];
		}

		if ($inherited_tags && $table === 'trigger_tag') {
			return [
				'trigger_tag' => $default_subquery,
				'item_tag' => match ($subquery_type) {
					self::SUBQUERY_TYPE_EXISTS =>
						'SELECT NULL'.
						' FROM item_tag'.
						' WHERE '.$parent_aliases[1].'.itemid=item_tag.itemid'.
							' AND item_tag.tag='.zbx_dbstr($tag),
					self::SUBQUERY_TYPE_NOT_EXISTS =>
						'SELECT NULL'.
						' FROM functions'.
						' JOIN item_tag ON functions.itemid=item_tag.itemid'.
						' WHERE '.$parent_aliases[0].'.triggerid=functions.triggerid'.
							' AND item_tag.tag='.zbx_dbstr($tag)
				},
				'host_tag' => match ($subquery_type) {
					self::SUBQUERY_TYPE_EXISTS =>
						'SELECT NULL'.
						' FROM item_template_cache itc'.
						' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
						' WHERE '.$parent_aliases[1].'.itemid=itc.itemid'.
							' AND host_tag.tag='.zbx_dbstr($tag),
					self::SUBQUERY_TYPE_NOT_EXISTS =>
						'SELECT NULL'.
						' FROM functions'.
						' JOIN item_template_cache itc ON functions.itemid=itc.itemid'.
						' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
						' WHERE '.$parent_aliases[0].'.triggerid=functions.triggerid'.
							' AND host_tag.tag='.zbx_dbstr($tag)
				}
			];
		}

		if ($inherited_tags && $table === 'httptest_tag') {
			return [
				'httptest_tag' => $default_subquery,
				'host_tag' => match ($subquery_type) {
					self::SUBQUERY_TYPE_EXISTS =>
						'SELECT NULL'.
						' FROM item_template_cache itc'.
						' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
						' WHERE '.$parent_aliases[1].'.itemid=itc.itemid'.
							' AND host_tag.tag='.zbx_dbstr($tag),
					self::SUBQUERY_TYPE_NOT_EXISTS =>
						'SELECT NULL'.
						' FROM httptestitem'.
						' JOIN item_template_cache itc ON httptestitem.itemid=itc.itemid'.
						' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
						' WHERE '.$parent_aliases[0].'.httptestid=httptestitem.httptestid'.
							' AND host_tag.tag='.zbx_dbstr($tag),
				}
			];
		}

		return [$table => $default_subquery];
	}

	private static function getTagSubquery(string $subquery_type, string $subquery_sql,
			array $conditions = []): string {
		if ($conditions) {
			$condition_string = count($conditions) == 1
				? ' AND '.$conditions[0]
				: ' AND ('.implode(' OR ', $conditions).')';

			return $subquery_type.' ('.
				$subquery_sql.
				$condition_string.
			')';
		}

		return $subquery_type.' ('.
			$subquery_sql.
		')';
	}

	private static function supplementWithLikeConditions(array &$value_conditions, array $operator_values,
			int $operator, array $tag_table_subqueries): void {
		if (!array_key_exists($operator, $operator_values)) {
			return;
		}

		foreach ($operator_values[$operator] as $value => $true) {
			$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
			$value = '%'.$value.'%';

			foreach ($tag_table_subqueries as $table => $subquery_sql) {
				$value_conditions[$table][] = 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
			}
		}
	}

	private static function supplementWithEqualConditions(array &$value_conditions, array $operator_values,
			int $operator, array $tag_table_subqueries): void {
		if (!array_key_exists($operator, $operator_values)) {
			return;
		}

		foreach ($tag_table_subqueries as $table => $subquery_sql) {
			$value_conditions[$table][] = dbConditionString($table.'.value', array_keys($operator_values[$operator]));
		}
	}
}
