<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Helper class containing methods for operations with tags.
 */
class CApiTagHelper {

	/**
	 * Returns SQL condition for tag filters.
	 *
	 * @param array       $tags
	 * @param string      $tags[]['tag']
	 * @param int         $tags[]['operator']
	 * @param string      $tags[]['value']
	 * @param int         $evaltype
	 * @param string      $parent_alias
	 * @param string      $table
	 * @param string      $field
	 * @param string|null $template_field
	 *
	 * @return string
	 */
	public static function addWhereCondition(array $tags, $evaltype, string $parent_alias, string $table,
			string $field, string $template_field = null): string {
		$values_by_tag = [];

		foreach ($tags as $tag) {
			$operator = array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE;
			$value = array_key_exists('value', $tag) ? $tag['value'] : '';

			if ($operator == TAG_OPERATOR_NOT_LIKE && $value === '') {
				$operator = TAG_OPERATOR_NOT_EXISTS;
			}
			elseif ($operator == TAG_OPERATOR_LIKE && $value === '') {
				$operator = TAG_OPERATOR_EXISTS;
			}

			if (!array_key_exists($tag['tag'], $values_by_tag)) {
				$values_by_tag[$tag['tag']] = [
					'NOT EXISTS' => [],
					'EXISTS' => []
				];
			}

			$slot = in_array($operator, [TAG_OPERATOR_EXISTS, TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL])
				? 'EXISTS'
				: 'NOT EXISTS';

			if (!is_array($values_by_tag[$tag['tag']][$slot])) {
				/*
				 * If previously there was the same tag name with operators TAG_OPERATOR_EXISTS/TAG_OPERATOR_NOT_EXISTS,
				 * we don't collect more values anymore because TAG_OPERATOR_EXISTS/TAG_OPERATOR_NOT_EXISTS has higher
				 * priority.
				 *
				 * `continue` is necessary to accidentally not overwrite boolean with array. Tag values collected before
				 * will be later removed.
				 */
				continue;
			}

			switch ($operator) {
				case TAG_OPERATOR_LIKE:
				case TAG_OPERATOR_NOT_LIKE:
					$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
					$value = '%'.mb_strtoupper($value).'%';

					$values_by_tag[$tag['tag']][$slot][]
						= 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
					break;

				case TAG_OPERATOR_EXISTS:
				case TAG_OPERATOR_NOT_EXISTS:
					$values_by_tag[$tag['tag']][$slot] = false;
					break;

				case TAG_OPERATOR_EQUAL:
				case TAG_OPERATOR_NOT_EQUAL:
					$values_by_tag[$tag['tag']][$slot][] = $table.'.value='.zbx_dbstr($value);
					break;
			}
		}

		$sql_where = [];

		foreach ($values_by_tag as $tag => $filters) {
			// Tag operators TAG_OPERATOR_EXISTS/TAG_OPERATOR_NOT_EXISTS are both canceling explicit values of same tag.
			if ($filters['EXISTS'] === false) {
				unset($filters['NOT EXISTS']);
			}
			elseif ($filters['NOT EXISTS'] === false) {
				unset($filters['EXISTS']);
			}

			$_where = [];

			foreach ($filters as $prefix => $values) {
				if ($values === []) {
					continue;
				}

				$statement = $table.'.tag='.zbx_dbstr($tag);

				if ($values) {
					$statement .= count($values) == 1 ? ' AND '.$values[0] : ' AND ('.implode(' OR ', $values).')';
				}

				$condition = $prefix.' ('.
					'SELECT NULL'.
					' FROM '.$table.
					' WHERE '.$parent_alias.'.'.$field.'='.$table.'.'.$field.' AND '.$statement.
				')';

				if ($template_field !== null) {
					$condition .= ($prefix === 'EXISTS' ? ' OR ' : ' AND ').$prefix.' ('.
						'SELECT NULL'.
						' FROM '.$table.
						' WHERE '.$template_field.'='.$table.'.'.$field.' AND '.$statement.
					')';
					$condition = '('.$condition.')';
				}

				$_where[] = $condition;
			}

			$sql_where[] = count($_where) == 1 ? $_where[0] : '('.$_where[0].' OR '.$_where[1].')';
		}

		if (!$sql_where) {
			return '(1=0)';
		}

		$sql_where_cnt = count($sql_where);

		$evaltype_glue = $evaltype == TAG_EVAL_TYPE_OR ? ' OR ' : ' AND ';
		$sql_where = implode($evaltype_glue, $sql_where);

		return ($sql_where_cnt > 1 && $evaltype == TAG_EVAL_TYPE_OR) ? '('.$sql_where.')' : $sql_where;
	}
}
