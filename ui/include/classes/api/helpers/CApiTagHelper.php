<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
		/*
		 * Tag grouping based on operator:
		 * '0' for NOT EXISTS sub-queries;
		 * '1' for EXISTS sub-queries.
		 */
		$values_by_tag = [
			0 => [],
			1 => []
		];
		foreach ($tags as $tag) {
			$operator = array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE;
			$value = array_key_exists('value', $tag) ? $tag['value'] : '';

			if ($operator == TAG_OPERATOR_NOT_EXISTS
					|| ($operator == TAG_OPERATOR_NOT_LIKE && $value === '')) {
				$values_by_tag[0][$tag['tag']] = false;
			}
			elseif ($operator == TAG_OPERATOR_NOT_EQUAL && $value !== '') {
				$values_by_tag[0][$tag['tag']][] = $table.'.value='.zbx_dbstr($value);
			}
			elseif ($operator == TAG_OPERATOR_NOT_LIKE) {
				$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
				$value = '%'.mb_strtoupper($value).'%';

				$values_by_tag[0][$tag['tag']][] = 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
			}
			elseif ($operator == TAG_OPERATOR_NOT_EQUAL) {
				$values_by_tag[1][$tag['tag']][] = $table.'.value<>'.zbx_dbstr($value);
			}
			elseif ($operator == TAG_OPERATOR_EXISTS
					|| ($operator == TAG_OPERATOR_LIKE && $value === '')) {
				$values_by_tag[1][$tag['tag']] = true;
			}
			elseif ($operator == TAG_OPERATOR_LIKE) {
				$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
				$value = '%'.mb_strtoupper($value).'%';

				$values_by_tag[1][$tag['tag']][] = 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
			}
			elseif ($operator == TAG_OPERATOR_EQUAL) {
				$values_by_tag[1][$tag['tag']][] = $table.'.value='.zbx_dbstr($value);
			}
		}

		$evaltype_glue = ($evaltype == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ';
		$sql_where = [];
		foreach ($values_by_tag as $key => $filters) {
			$statements = [];
			foreach ($filters as $tag => $values) {
				if (!is_array($values) || count($values) == 0) {
					$values = '';
				}
				elseif (count($values) == 1) {
					$values = ' AND '.$values[0];
				}
				else {
					$values = $values ? ' AND ('.implode(' OR ', $values).')' : '';
				}

				if ($values === '') {
					$statements[] = $table.'.tag='.zbx_dbstr($tag).$values;
				}
				else {
					$statements[] = '('.$table.'.tag='.zbx_dbstr($tag).$values.')';
				}
			}

			if ($statements) {
				$prefix = ($key == 0) ? 'NOT ' : '';
				$sql_where[] = $prefix.'EXISTS ('.
					'SELECT NULL'.
					' FROM '.$table.
					' WHERE '.$parent_alias.'.'.$field.'='.$table.'.'.$field.
						' AND ('.implode($evaltype_glue, $statements).')'.
				')';
			}
		}

		if (!$sql_where) {
			return '(1=0)';
		}

		$sql_where = implode($evaltype_glue, $sql_where);

		return (count($values_by_tag) > 1 && $evaltype == TAG_EVAL_TYPE_OR) ? '('.$sql_where.')' : $sql_where;
	}

	/**
	 * Function returns template tags matching filter tags based on given operator filter.
	 *
	 * @param array  $filter_tag
	 * @param string $filter_tag['tag']
	 * @param int    $filter_tag['operator']
	 * @param string $filter_tag['value']
	 * @param array  $host_tag
	 * @param string $host_tag['tag']
	 * @param string $host_tag['value']
	 *
	 * @return array
	 */
	public static function getMatchingTemplateTag(array $filter_tag, array $template_tags): array {
		if ($filter_tag['operator'] == TAG_OPERATOR_NOT_EXISTS
				|| $filter_tag['operator'] == TAG_OPERATOR_NOT_LIKE
				|| ($filter_tag['operator'] == TAG_OPERATOR_NOT_EQUAL && $filter_tag['value'] !== '')) {
			return [];
		}

		$_template_tags = [];
		foreach ($template_tags as $tag) {
			$_template_tags[$tag['tag']][] = $tag;
		}

		$return = [];
		switch ($filter_tag['operator']) {
			case TAG_OPERATOR_EXISTS:
			case TAG_OPERATOR_NOT_EXISTS:
				if (array_key_exists($filter_tag['tag'], $_template_tags)) {
					$return = $_template_tags[$filter_tag['tag']];
				}
				break;

			case TAG_OPERATOR_LIKE:
			case TAG_OPERATOR_NOT_LIKE:
				if (array_key_exists($filter_tag['tag'], $_template_tags)) {
					$return = array_filter($_template_tags[$filter_tag['tag']], function($template_tag) use($filter_tag) {
						$template_tag_value = mb_strtolower($template_tag['value']);
						$filter_tag_value = mb_strtolower($filter_tag['value']);

						return ($filter_tag_value === '' || mb_strpos($template_tag_value, $filter_tag_value) !== false);
					});
				}
				break;

			case TAG_OPERATOR_EQUAL:
			case TAG_OPERATOR_NOT_EQUAL:
				if (array_key_exists($filter_tag['tag'], $_template_tags)) {
					$return = array_filter($_template_tags[$filter_tag['tag']], function($template_tag) use($filter_tag) {
						return ($filter_tag['value'] === $template_tag['value']);
					});
				}
				break;
		}

		return zbx_toHash($return, 'hostid');
	}
}
