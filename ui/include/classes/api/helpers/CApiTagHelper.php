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

		foreach (self::groupTagsForFiltering($tags) as $tag => $filters) {
			// The tag operator TAG_OPERATOR_EXISTS overrides explicit values of the same tag and NOT EXISTS statements.
			if (array_key_exists('EXISTS', $filters) && array_key_exists('tag', $filters['EXISTS'])
					&& $filters['EXISTS']['tag'] === true) {
				unset($filters['NOT EXISTS'], $filters['EXISTS']['value']);
			}

			$where = [];
			$tag_condition = 'tag.tag='.zbx_dbstr($tag);

			foreach ($filters as $prefix => $filter) {
				foreach ($filter as $type => $conditions) {
					$tag_conditions = $tag_condition;

					if ($type === 'value') {
						$value_conditions = array_unique($conditions);
						$value_conditions = count($value_conditions) == 1
							? ' AND '.$value_conditions[0]
							: ' AND ('.implode(' OR ', $value_conditions).')';

						$tag_conditions .= $value_conditions;
					}

					if ($with_inherited_tags) {
						switch ($table) {
							case 'host_tag':
								$where[] = $prefix.' ('.
									'SELECT NULL'.
									' FROM host_template_cache htc'.
									' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
									' WHERE h.hostid=htc.hostid'.
										' AND '.$tag_conditions.
								')';
								break;

							case 'httptest_tag':
								$where[] = '('.
									$prefix.' ('.
										'SELECT NULL'.
										' FROM httptest_tag tag'.
										' WHERE ht.httptestid=tag.httptestid'.
											' AND '.$tag_conditions.
									')'.
									($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
										'SELECT NULL'.
										' FROM httptest_template_cache htc'.
										' JOIN host_tag tag ON htc.link_hostid=tag.hostid'.
										' WHERE ht.httptestid=htc.httptestid'.
											' AND '.$tag_conditions.
									')'.
								')';
								break;

							case 'item_tag':
								$where[] = '('.
									$prefix.' ('.
										'SELECT NULL'.
										' FROM item_tag tag'.
										' WHERE i.itemid=tag.itemid'.
											' AND '.$tag_conditions.
									')'.
									($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
										'SELECT NULL'.
										' FROM item_template_cache itc'.
										' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
										' WHERE i.itemid=itc.itemid'.
											' AND '.$tag_conditions.
									')'.
								')';
								break;

							case 'trigger_tag':
								$where[] = '('.
									$prefix.' ('.
										'SELECT NULL'.
										' FROM trigger_tag tag'.
										' WHERE t.triggerid=tag.triggerid'.
											' AND '.$tag_conditions.
									')'.
									($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
										'SELECT NULL'.
										' FROM item_tag tag'.
										' WHERE f.itemid=tag.itemid'.
											' AND '.$tag_conditions.
									')'.
									($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
										'SELECT NULL'.
										' FROM item_template_cache itc'.
										' JOIN host_tag tag ON itc.link_hostid=tag.hostid'.
										' WHERE f.itemid=itc.itemid'.
											' AND '.$tag_conditions.
									')'.
								')';
								break;
						}
					}
					else {
						$where[] = $prefix.' ('.
							'SELECT NULL'.
							' FROM '.$table.' tag'.
							' WHERE '.$parent_alias.'.'.$field.'=tag.'.$field.
								' AND '.$tag_conditions.
						')';
					}
				}
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

	private static function groupTagsForFiltering(array $tags): array {
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
