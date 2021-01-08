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
		$values_by_tag = [
			'NOT EXISTS' => [],
			'EXISTS' => []
		];
		foreach ($tags as $tag) {
			$operator = array_key_exists('operator', $tag) ? $tag['operator'] : TAG_OPERATOR_LIKE;
			$value = array_key_exists('value', $tag) ? $tag['value'] : '';

			if ($operator == TAG_OPERATOR_NOT_LIKE && $value === '') {
				$operator = TAG_OPERATOR_NOT_EXISTS;
			}
			elseif ($operator == TAG_OPERATOR_LIKE && $value === '') {
				$operator = TAG_OPERATOR_EXISTS;
			}

			$slot = in_array($operator, [TAG_OPERATOR_EXISTS, TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL])
				? 'EXISTS'
				: 'NOT EXISTS';

			if (array_key_exists($tag['tag'], $values_by_tag[$slot]) && !is_array($values_by_tag[$slot][$tag['tag']])) {
				continue;
			}

			switch ($operator) {
				case TAG_OPERATOR_LIKE:
				case TAG_OPERATOR_NOT_LIKE:
					$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
					$value = '%'.mb_strtoupper($value).'%';

					$values_by_tag[$slot][$tag['tag']][]
						= 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
					break;

				case TAG_OPERATOR_EXISTS:
				case TAG_OPERATOR_NOT_EXISTS:
					$values_by_tag[$slot][$tag['tag']] = false;
					break;

				case TAG_OPERATOR_EQUAL:
				case TAG_OPERATOR_NOT_EQUAL:
					$values_by_tag[$slot][$tag['tag']][] = $table.'.value='.zbx_dbstr($value);
					break;
			}
		}

		$evaltype_glue = ($evaltype == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ';
		$sql_where = [];
		foreach ($values_by_tag as $prefix => $filters) {
			foreach ($filters as $tag => $values) {
				$values = $values ? array_filter($values) : false;

				$statement = $table.'.tag='.zbx_dbstr($tag);
				if ($values) {
					$statement .= (count($values) == 1)
						? ' AND '.implode(' OR ', $values)
						: ' AND ('.implode(' OR ', $values).')';
				}

				$sql_where[] = $prefix.' ('.
					'SELECT NULL'.
					' FROM '.$table.
					' WHERE '.$parent_alias.'.'.$field.'='.$table.'.'.$field.' AND '.$statement.
				')';
			}
		}

		if (!$sql_where) {
			return '(1=0)';
		}

		$sql_where_cnt = count($sql_where);
		$sql_where = implode($evaltype_glue, $sql_where);

		return ($sql_where_cnt > 1 && $evaltype == TAG_EVAL_TYPE_OR) ? '('.$sql_where.')' : $sql_where;
	}

	/**
	 * Function prepares two lists:
	 * - templateids matching $filter_tag;
	 * - as well as list of templateids which MUST be excluded from results.
	 *
	 * @param array  $filter_tag
	 * @param int    $filter_tag['operator']
	 * @param string $filter_tag['tag']
	 * @param string $filter_tag['value']
	 * @param array  $template_tags
	 * @param string $template_tags[]['tag']
	 * @param string $template_tags[]['value']
	 * @param ?int   $template_tags[]['hostid']
	 *
	 * @return array
	 */
	public static function checkMatchingTemplates(array $filter_tag, array $template_tags): array {
		$include = [];
		$exclude = [];

		switch ($filter_tag['operator']) {
			case TAG_OPERATOR_LIKE:
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0
							&& $filter_tag['tag'] === $tag['tag']
							&& ($filter_tag['value'] === ''
								|| mb_stripos($tag['value'], $filter_tag['value']) !== false)) {
						$include[$tag['hostid']] = true;
					}
				}
				break;

			case TAG_OPERATOR_EQUAL:
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0
							&& $filter_tag['tag'] === $tag['tag']
							&& $filter_tag['value'] === $tag['value']) {
						$include[$tag['hostid']] = true;
					}
				}
				break;

			case TAG_OPERATOR_NOT_LIKE:
				$include = array_flip(array_column($template_tags, 'hostid'));
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0
							&& $filter_tag['tag'] === $tag['tag']
							&& ($filter_tag['value'] === ''
								|| mb_stripos($tag['value'], $filter_tag['value']) !== false)) {
						$exclude[$tag['hostid']] = true;
						unset($include[$tag['hostid']]);
					}
				}
				break;

			case TAG_OPERATOR_NOT_EQUAL:
				$include = array_flip(array_column($template_tags, 'hostid'));
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0
							&& $filter_tag['tag'] === $tag['tag']
							&& $filter_tag['value'] === $tag['value']) {
						$exclude[$tag['hostid']] = true;
						unset($include[$tag['hostid']]);
					}
				}
				break;

			case TAG_OPERATOR_EXISTS:
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0 && $filter_tag['tag'] === $tag['tag']) {
						$include[$tag['hostid']] = true;
					}
				}
				break;

			case TAG_OPERATOR_NOT_EXISTS:
				$include = array_flip(array_column($template_tags, 'hostid'));
				foreach ($template_tags as $tag) {
					if ($tag['hosttagid'] != 0 && $filter_tag['tag'] === $tag['tag']) {
						$exclude[$tag['hostid']] = true;
						unset($include[$tag['hostid']]);
					}
				}
				break;
		}

		return [$include, $exclude];
	}
}
