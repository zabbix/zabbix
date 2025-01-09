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

					$values_by_tag[$tag['tag']][$prefix]['value'][]
						= 'UPPER('.$table.'.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
					break;

				case TAG_OPERATOR_EXISTS:
				case TAG_OPERATOR_NOT_EXISTS:
					$values_by_tag[$tag['tag']][$prefix]['tag'] = true;
					break;

				case TAG_OPERATOR_EQUAL:
				case TAG_OPERATOR_NOT_EQUAL:
					$values_by_tag[$tag['tag']][$prefix]['value'][] = $table.'.value='.zbx_dbstr($value);
					break;
			}
		}

		$sql_where = [];

		foreach ($values_by_tag as $tag => $filters) {
			// The tag operator TAG_OPERATOR_EXISTS overrides explicit values of the same tag and NOT EXISTS statements.
			if (array_key_exists('EXISTS', $filters) && array_key_exists('tag', $filters['EXISTS'])
					&& $filters['EXISTS']['tag'] === true) {
				unset($filters['NOT EXISTS'], $filters['EXISTS']['value']);
			}

			$_where = [];

			foreach ($filters as $prefix => $filter) {
				$statement_start = $prefix.' ('.
					'SELECT NULL'.
					' FROM '.$table.
					' WHERE '.$parent_alias.'.'.$field.'='.$table.'.'.$field.
						' AND '.$table.'.tag='.zbx_dbstr($tag);

				foreach ($filter as $type => $values) {
					if ($type === 'tag') {
						$_where[] = $statement_start.')';
					}
					else {
						$values = array_unique($values);
						$conditions = count($values) == 1
							? ' AND '.implode(' OR ', $values)
							: ' AND ('.implode(' OR ', $values).')';
						$_where[] = $statement_start.$conditions.')';
					}
				}
			}

			if ($evaltype == TAG_EVAL_TYPE_AND_OR) {
				$sql_where[] = count($_where) == 1 ? $_where[0] : '('.implode(' OR ', $_where).')';
			}
			else {
				$sql_where = array_merge($sql_where, $_where);
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

	/**
	 * Return SQL query conditions to filter host tags including inherited template tags.
	 *
	 * @param array  $tags
	 * @param string $tags[]['tag']
	 * @param int    $tags[]['operator']
	 * @param string $tags[]['value']
	 * @param int    $evaltype
	 *
	 * @return string
	 */
	public static function addInheritedHostTagsWhereCondition(array $tags, int $evaltype): string {
		// Swap tag operators to select templates normally should be excluded.
		$swapped_filter = array_map(function ($tag) {
			$swapping_map = [
				TAG_OPERATOR_LIKE => TAG_OPERATOR_LIKE,
				TAG_OPERATOR_EQUAL => TAG_OPERATOR_EQUAL,
				TAG_OPERATOR_NOT_LIKE => TAG_OPERATOR_LIKE,
				TAG_OPERATOR_NOT_EQUAL => TAG_OPERATOR_EQUAL,
				TAG_OPERATOR_EXISTS => TAG_OPERATOR_EXISTS,
				TAG_OPERATOR_NOT_EXISTS => TAG_OPERATOR_EXISTS
			];
			return ['operator' => $swapping_map[$tag['operator']]] + $tag;
		}, $tags);

		$db_template_tags = DBfetchArray(DBselect(
			'SELECT h.hostid,ht.tag,ht.value'.
			' FROM hosts h, host_tag ht'.
			' WHERE ht.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_TEMPLATE.
				' AND '.self::addWhereCondition($swapped_filter, TAG_EVAL_TYPE_OR, 'h','host_tag', 'hostid')
		));

		// Group filter tags by operator and tag name.
		$negated_tags = [];
		$inclusive_tags = [];

		foreach ($tags as $tag) {
			if (!array_key_exists('operator', $tag)) {
				$tag['operator'] = TAG_OPERATOR_LIKE;
			}
			if (!array_key_exists('value', $tag)) {
				$tag['value'] = '';
			}
			if ($tag['operator'] == TAG_OPERATOR_NOT_LIKE && $tag['value'] === '') {
				$tag['operator'] = TAG_OPERATOR_NOT_EXISTS;
			}
			elseif ($tag['operator'] == TAG_OPERATOR_LIKE && $tag['value'] === '') {
				$tag['operator'] = TAG_OPERATOR_EXISTS;
			}

			if (in_array($tag['operator'], [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_EXISTS])
					&& !array_key_exists($tag['tag'], $inclusive_tags)) {
				$inclusive_tags[$tag['tag']] = [];
			}
			elseif (in_array($tag['operator'], [TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_NOT_EXISTS])
					&& !array_key_exists($tag['tag'], $negated_tags)) {
				$negated_tags[$tag['tag']] = [];
			}

			switch ($tag['operator']) {
				case TAG_OPERATOR_LIKE:
				case TAG_OPERATOR_EQUAL:
					if (is_array($inclusive_tags[$tag['tag']])) {
						$inclusive_tags[$tag['tag']][] = $tag;
					}
					break;

				case TAG_OPERATOR_NOT_LIKE:
				case TAG_OPERATOR_NOT_EQUAL:
					if (is_array($negated_tags[$tag['tag']])) {
						$negated_tags[$tag['tag']][] = $tag;
					}
					break;

				case TAG_OPERATOR_EXISTS:
					$inclusive_tags[$tag['tag']] = false;
					break;

				case TAG_OPERATOR_NOT_EXISTS:
					$negated_tags[$tag['tag']] = false;
					break;
			}
		}

		// Make 'where' condition from negated filter tags.
		$negated_conditions = array_fill_keys(array_keys($negated_tags), ['values' => [], 'templateids' => []]);
		array_walk($negated_conditions, function (&$where, $tag_name) use ($negated_tags, $db_template_tags) {
			if ($negated_tags[$tag_name] === false) {
				$tag = ['tag' => $tag_name, 'operator' => TAG_OPERATOR_NOT_EXISTS];
				$where['templateids'] += self::getMatchingTemplateids($tag, $db_template_tags);
			}
			else {
				foreach ($negated_tags[$tag_name] as $tag) {
					$where['templateids'] += self::getMatchingTemplateids($tag, $db_template_tags);

					if ($tag['operator'] == TAG_OPERATOR_NOT_EXISTS) {
						$where['values'] = false;
					}
					elseif (is_array($where['values'])) {
						$where['values'][] = self::makeTagWhereCondition($tag);
					}
				}
			}
		});

		$negated_where_conditions = [];
		foreach ($negated_conditions as $tag => $tag_where) {

			$templateids_in = [];
			while ($tag_where['templateids']) {
				$templateids_in += $tag_where['templateids'];

				$tag_where['templateids'] = API::Template()->get([
					'output' => [],
					'parentTemplateids' => array_keys($tag_where['templateids']),
					'preservekeys' => true,
					'nopermissions' => true
				]);
			}

			$negated_where_conditions[] = '(NOT EXISTS ('.
				'SELECT NULL'.
				' FROM host_tag'.
				' WHERE (h.hostid=host_tag.hostid'.
					' AND host_tag.tag='.zbx_dbstr($tag).
						($tag_where['values'] ? ' AND ('.implode(' OR ', $tag_where['values']).')' : '').
					')'.
					($templateids_in
						? ' OR '.dbConditionInt('ht2.templateid', array_keys($templateids_in)).''
						: ''
					).
				')'.
			')';
		}

		$where_conditions = [];

		if ($negated_where_conditions) {
			if ($evaltype == TAG_EVAL_TYPE_AND_OR) {
				$where_conditions[] = implode(' AND ', $negated_where_conditions);
			}
			else {
				$where_conditions = array_map(function ($condition) {
					return $condition;
				}, $negated_where_conditions);
			}
		}

		// Make 'where' conditions for inclusive filter tags.
		foreach ($inclusive_tags as $tag_name => $tag_values) {
			$templateids = [];
			$values = [];

			if ($tag_values === false) {
				$templateids += self::getMatchingTemplateids(['tag' => $tag_name, 'operator' => TAG_OPERATOR_EXISTS],
					$db_template_tags
				);
			}
			else {
				foreach ($tag_values as $tag) {
					$templateids += self::getMatchingTemplateids($tag, $db_template_tags);

					if ($tag['operator'] == TAG_OPERATOR_EXISTS) {
						$values = false;
					}
					elseif (is_array($values)) {
						$values[] = self::makeTagWhereCondition($tag);
					}
				}
			}

			$templateids_in = [];
			while ($templateids) {
				$templateids_in += $templateids;

				$templateids = API::Template()->get([
					'output' => [],
					'parentTemplateids' => array_keys($templateids),
					'preservekeys' => true,
					'nopermissions' => true
				]);
			}

			$where_conditions[] = '(EXISTS ('.
				'SELECT NULL'.
				' FROM host_tag'.
				' WHERE h.hostid=host_tag.hostid'.
					' AND host_tag.tag='.zbx_dbstr($tag_name).
					($values ? ' AND ('.implode(' OR ', $values).')' : '').
				')'.
				($templateids_in ? ' OR '.dbConditionInt('ht2.templateid', array_keys($templateids_in)) : '').
			')';
		}

		$operator = ($evaltype == TAG_EVAL_TYPE_OR) ? ' OR ' : ' AND ';
		return '('.implode($operator, $where_conditions).')';
	}

	/**
	 * Function returns SQL WHERE statement for given tag value based on operator.
	 *
	 * @param array  $tag
	 * @param string $tag['value']
	 * @param int    $tag['operator']
	 *
	 * @return string
	 */
	private static function makeTagWhereCondition(array $tag): string {
		if ($tag['operator'] == TAG_OPERATOR_EQUAL || $tag['operator'] == TAG_OPERATOR_NOT_EQUAL) {
			return 'host_tag.value='.zbx_dbstr($tag['value']);
		}
		else {
			$value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $tag['value']);
			$value = '%'.mb_strtoupper($value).'%';
			return 'UPPER(host_tag.value) LIKE '.zbx_dbstr($value)." ESCAPE '!'";
		}
	}

	/**
	 * Function to collect templateids having tags matching the filter tag.
	 *
	 * @param array   $filter_tag
	 * @param string  $filter_tag['tag']
	 * @param int     $filter_tag['operator']
	 * @param string  $filter_tag['value']
	 * @param array   $template_tags
	 * @param string  $template_tags[]['tag']
	 * @param string  $template_tags[]['value']
	 * @param string  $template_tags[]['hostid']
	 *
	 * @return array
	 */
	private static function getMatchingTemplateids(array $filter_tag, array $template_tags): array {
		$templateids = [];

		switch ($filter_tag['operator']) {
			case TAG_OPERATOR_LIKE:
			case TAG_OPERATOR_NOT_LIKE:
				foreach ($template_tags as $tag) {
					if ($filter_tag['tag'] === $tag['tag']
							&& mb_stripos($tag['value'], $filter_tag['value']) !== false) {
						$templateids[$tag['hostid']] = true;
					}
				}
				break;

			case TAG_OPERATOR_EQUAL:
			case TAG_OPERATOR_NOT_EQUAL:
				foreach ($template_tags as $tag) {
					if ($filter_tag['tag'] === $tag['tag'] && $filter_tag['value'] === $tag['value']) {
						$templateids[$tag['hostid']] = true;
					}
				}
				break;

			case TAG_OPERATOR_NOT_EXISTS:
			case TAG_OPERATOR_EXISTS:
				foreach ($template_tags as $tag) {
					if ($filter_tag['tag'] === $tag['tag']) {
						$templateids[$tag['hostid']] = true;
					}
				}
				break;
		}

		return $templateids;
	}
}
