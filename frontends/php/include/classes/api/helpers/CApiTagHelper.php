<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * @param array  $tags
	 * @param string $tags[]['tag']
	 * @param int    $tags[]['operator']
	 * @param string $tags[]['value']
	 * @param int    $evaltype
	 * @param string $parent_alias
	 * @param string $table
	 * @param string $field
	 *
	 * @return string
	 */
	public static function addWhereCondition(array $tags, $evaltype, $parent_alias, $table, $field) {
		$values_by_tag = [];

		foreach ($tags as $tag) {
			$operator = array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE;
			$value = array_key_exists('value', $tag) ? $tag['value'] : '';

			if (!array_key_exists($tag['tag'], $values_by_tag) || is_array($values_by_tag[$tag['tag']])) {
				if ($operator == TAG_OPERATOR_EQUAL) {
					$values_by_tag[$tag['tag']][] = $table.'.value='.zbx_dbstr($value);
				}
				elseif ($value !== '') {
					$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
					$value = '%'.mb_strtoupper($value).'%';

					$values_by_tag[$tag['tag']][] = 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
				}
				// ($value === '') - all other conditions can be omitted
				else {
					$values_by_tag[$tag['tag']] = false;
				}
			}
		}

		$sql_where = [];

		foreach ($values_by_tag as $tag => $values) {
			if (!is_array($values) || count($values) == 0) {
				$values = '';
			}
			elseif (count($values) == 1) {
				$values = ' AND '.$values[0];
			}
			else {
				$values = $values ? ' AND ('.implode(' OR ', $values).')' : '';
			}

			$sql_where[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM '.$table.
				' WHERE '.$parent_alias.'.'.$field.'='.$table.'.'.$field.
					' AND '.$table.'.tag='.zbx_dbstr($tag).$values.
			')';
		}

		$sql_where = implode(($evaltype == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ', $sql_where);

		return (count($values_by_tag) > 1 && $evaltype == TAG_EVAL_TYPE_OR) ? '('.$sql_where.')' : $sql_where;
	}
}
