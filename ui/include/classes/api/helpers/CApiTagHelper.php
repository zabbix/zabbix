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

	/**
	 * Returns SQL condition for tag filters.
	 *
	 * @param array  $tags
	 *        string $tags[]['tag']
	 *        int    $tags[]['operator']
	 *        string $tags[]['value']
	 * @param int    $evaltype
	 * @param bool   $with_inherited_tags
	 * @param string $table
	 * @param string $parent_alias
	 * @param string $field
	 *
	 * @return string
	 */
	public static function addWhereCondition(array $tags, int $evaltype, bool $with_inherited_tags, string $table,
			string $parent_alias, string $field): string {
		$sql_where = [];

		foreach (self::groupValuesByTags($tags) as $tag => $filters) {
			// The tag operator TAG_OPERATOR_EXISTS overrides explicit values of the same tag and NOT EXISTS statements.
			if (array_key_exists('EXISTS', $filters) && array_key_exists('tag', $filters['EXISTS'])
					&& $filters['EXISTS']['tag'] === true) {
				unset($filters['NOT EXISTS'], $filters['EXISTS']['value']);
			}

			$where = [];

			foreach ($filters as $prefix => $filter) {
				$where_value_conditions = '';

				foreach ($filter as $type => $conditions) {
					if ($type === 'value') {
						$conditions = array_unique($conditions);
						$where_value_conditions .= count($conditions) == 1
							? ' AND '.$conditions[0]
							: ' AND ('.implode(' OR ', $conditions).')';
					}
				}

				$_where = $prefix.' ('.
					'SELECT NULL'.
					' FROM '.$table.' tag'.
					' WHERE '.$parent_alias.'.'.$field.'=tag.'.$field.
						' AND tag.tag='.zbx_dbstr($tag).
						$where_value_conditions.
				')';

				if ($with_inherited_tags) {
					$sql = 'SELECT NULL';

					switch ($table) {
						case 'host_tag':
							$sql .= ' FROM host_template_cache htc'.
									' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
									' WHERE '.$parent_alias.'.'.$field.'=htc.'.$field;
							break;

						case 'httptest_tag':
							$sql .= ' FROM httptest_template_cache htc'.
									' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
									' WHERE '.$parent_alias.'.'.$field.'=htc.'.$field;
							break;

						case 'item_tag':
							$sql .= ' FROM item_template_cache itc'.
									' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
									' WHERE '.$parent_alias.'.'.$field.'=itc.'.$field;
							break;

						case 'trigger_tag':
							$sql .= ' FROM item_template_cache itc'.
									' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
									' WHERE f.itemid=itc.itemid';
							break;
					}

					$sql .= ' AND tag.tag='.zbx_dbstr($tag).
							$where_value_conditions;

					$_where .= ($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
						$sql.
					')';

					$_where = '('.$_where.')';
				}

				$where[] = $_where;
			}

			if ($evaltype == TAG_EVAL_TYPE_AND_OR) {
				$sql_where[] = count($where) == 1 ? $where[0] : '('.implode(' OR ', $where).')';
			}
			else {
				$sql_where = array_merge($sql_where, $where);
			}
		}

		if (!$sql_where) {
			return '(1=0)';
		}

		$evaltype_glue = $evaltype == TAG_EVAL_TYPE_OR ? ' OR ' : ' AND ';

		return count($sql_where) > 1 && $evaltype == TAG_EVAL_TYPE_OR
			? '('.implode($evaltype_glue, $sql_where).')'
			: implode($evaltype_glue, $sql_where);
	}

	private static function groupValuesByTags(array $tags): array {
		$values_by_tags = [];

		foreach ($tags as $tag) {
			$operator = array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE;
			$value = array_key_exists('value', $tag) ? $tag['value'] : '';

			if ($operator == TAG_OPERATOR_NOT_LIKE && $value === '') {
				$operator = TAG_OPERATOR_NOT_EXISTS;
			}
			elseif ($operator == TAG_OPERATOR_LIKE && $value === '') {
				$operator = TAG_OPERATOR_EXISTS;
			}

			$prefix = in_array($operator, [TAG_OPERATOR_EXISTS, TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL])
				? 'EXISTS'
				: 'NOT EXISTS';

			switch ($operator) {
				case TAG_OPERATOR_LIKE:
				case TAG_OPERATOR_NOT_LIKE:
					$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
					$value = '%'.mb_strtoupper($value).'%';

					$values_by_tags[$tag['tag']][$prefix]['value'][] =
						'UPPER(tag.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
					break;

				case TAG_OPERATOR_EXISTS:
				case TAG_OPERATOR_NOT_EXISTS:
					$values_by_tags[$tag['tag']][$prefix]['tag'] = true;
					break;

				case TAG_OPERATOR_EQUAL:
				case TAG_OPERATOR_NOT_EQUAL:
					$values_by_tags[$tag['tag']][$prefix]['value'][] = 'tag.value='.zbx_dbstr($value);
					break;
			}
		}

		return $values_by_tags;
	}
}
