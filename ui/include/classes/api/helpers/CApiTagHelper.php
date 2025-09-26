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

		$sql_where = [];

		foreach (self::getGroupedTags($tags) as $tag => $operator_values) {
			$tag_table_subqueries = self::getTagTableSubqueries($parent_aliases, $table, $field, $tag, $inherited_tags);
			$subqueries = self::getTagSubqueries($operator_values, $tag_table_subqueries);

			if ($evaltype == TAG_EVAL_TYPE_AND_OR) {
				$sql_where[] = count($subqueries) == 1 ? $subqueries[0] : '('.implode(' OR ', $subqueries).')';
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

	private static function getTagTableSubqueries(array $parent_aliases, string $table, string $field, string $tag,
			bool $inherited_tags): array {
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
				'item_tag' =>
					'SELECT NULL'.
					' FROM item_tag'.
					' WHERE '.$parent_aliases[1].'.itemid=item_tag.itemid'.
						' AND item_tag.tag='.zbx_dbstr($tag),
				'host_tag' =>
					'SELECT NULL'.
					' FROM item_template_cache itc'.
					' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
					' WHERE '.$parent_aliases[1].'.itemid=itc.itemid'.
						' AND host_tag.tag='.zbx_dbstr($tag)
			];
		}

		if ($inherited_tags && $table === 'httptest_tag') {
			return [
				'httptest_tag' => $default_subquery,
				'host_tag' =>
					'SELECT NULL'.
					' FROM item_template_cache itc'.
					' JOIN host_tag ON itc.link_hostid=host_tag.hostid'.
					' WHERE '.$parent_aliases[1].'.itemid=itc.itemid'.
						' AND host_tag.tag='.zbx_dbstr($tag)
			];
		}

		return [$table => $default_subquery];
	}

	private static function getTagSubqueries(array $operator_values, array $tag_table_subqueries): array {
		$subqueries = [];

		if (array_key_exists(TAG_OPERATOR_EXISTS, $operator_values)) {
			foreach ($tag_table_subqueries as $subquery_sql) {
				$subqueries[] = self::getTagSubquery(self::SUBQUERY_TYPE_EXISTS, $subquery_sql);
			}

			return $subqueries;
		}

		$value_conditions = [];

		if (array_key_exists(TAG_OPERATOR_LIKE, $operator_values)) {
			self::addLikeConditions($value_conditions, $operator_values[TAG_OPERATOR_LIKE], $tag_table_subqueries);
		}

		if (array_key_exists(TAG_OPERATOR_EQUAL, $operator_values)) {
			self::addEqualConditions($value_conditions, $operator_values[TAG_OPERATOR_EQUAL], $tag_table_subqueries);
		}

		if ($value_conditions) {
			foreach ($tag_table_subqueries as $table => $subquery_sql) {
				$subqueries[] =
					self::getTagSubquery(self::SUBQUERY_TYPE_EXISTS, $subquery_sql, $value_conditions[$table]);
			}
		}

		if (array_key_exists(TAG_OPERATOR_NOT_EXISTS, $operator_values)) {
			$_subqueries = [];

			foreach ($tag_table_subqueries as $subquery_sql) {
				$_subqueries[] = self::getTagSubquery(self::SUBQUERY_TYPE_NOT_EXISTS, $subquery_sql);
			}

			$subqueries[] = count($_subqueries) == 1 ? $_subqueries[0] : '('.implode(' AND ', $_subqueries).')';
		}

		$value_conditions = [];

		if (array_key_exists(TAG_OPERATOR_NOT_LIKE, $operator_values)) {
			self::addLikeConditions($value_conditions, $operator_values[TAG_OPERATOR_NOT_LIKE], $tag_table_subqueries);
		}

		if (array_key_exists(TAG_OPERATOR_NOT_EQUAL, $operator_values)) {
			self::addEqualConditions($value_conditions, $operator_values[TAG_OPERATOR_NOT_EQUAL],
				$tag_table_subqueries
			);
		}

		if ($value_conditions) {
			$_subqueries = [];

			foreach ($tag_table_subqueries as $table => $subquery_sql) {
				$_subqueries[] = self::getTagSubquery(self::SUBQUERY_TYPE_NOT_EXISTS, $subquery_sql,
					$value_conditions[$table]
				);
			}

			$subqueries[] = count($_subqueries) == 1 ? $_subqueries[0] : '('.implode(' AND ', $_subqueries).')';
		}

		return $subqueries;
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

	private static function addLikeConditions(array &$value_conditions, array $values,
			array $tag_table_subqueries): void {
		foreach ($values as $value => $true) {
			$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
			$value = '%'.$value.'%';

			foreach ($tag_table_subqueries as $table => $subquery_sql) {
				$value_conditions[$table][] = 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
			}
		}
	}

	private static function addEqualConditions(array &$value_conditions, array $values,
			array $tag_table_subqueries): void {
		foreach ($tag_table_subqueries as $table => $subquery_sql) {
			$value_conditions[$table][] = dbConditionString($table.'.value', array_keys($values));
		}
	}
}
