<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A class for creating conditions.
 */
class CConditionHelper {

	/**
	 * Generate a numeric formula from conditions $conditions with respect to evaluation type $evalType.
	 * Each condition must have a condition type, that will be used for grouping.
	 *
	 * Supported $evalType values:
	 * - CONDITION_EVAL_TYPE_AND_OR
	 * - CONDITION_EVAL_TYPE_AND
	 * - CONDITION_EVAL_TYPE_OR
	 *
	 * Example:
	 * echo CFormulaHelper::getFormula(array(
	 * 	1 => 'condition1',
	 *	2 => 'condition1',
	 *	5 => 'condition2'
	 * ), CONDITION_EVAL_TYPE_AND_OR);
	 *
	 * // ({1} or {2}) and {5}
	 *
	 * Keep in sync with JS getConditionFormula().
	 *
	 * @param array $conditions		conditions with IDs as keys and condition type with values
	 * @param int	$evalType
	 *
	 * @return string
	 */
	public static function getFormula(array $conditions, $evalType) {
		$groupedConditions = [];
		foreach ($conditions as $id => $condition) {
			$groupedConditions[$condition][] = '{'.$id.'}';
		}

		// operators
		switch ($evalType) {
			case CONDITION_EVAL_TYPE_AND:
				$conditionOperator = 'and';
				$groupOperator = $conditionOperator;
				break;
			case CONDITION_EVAL_TYPE_OR:
				$conditionOperator = 'or';
				$groupOperator = $conditionOperator;
				break;
			default:
				$conditionOperator = 'or';
				$groupOperator = 'and';
				break;
		}

		$groupFormulas = [];
		foreach ($groupedConditions as $conditionIds) {
			if (count($conditionIds) > 1) {
				$groupFormulas[] = '('.implode(' '.$conditionOperator.' ', $conditionIds).')';
			}
			else {
				$groupFormulas[] = $conditionIds[0];
			}
		}

		$formula = implode(' '.$groupOperator.' ', $groupFormulas);

		// strip parentheses if there's only one condition group
		if (count($groupedConditions) == 1) {
			$formula = trim($formula, '()');
		}

		return $formula;
	}

	/**
	 * Extract the numeric IDs used in the given formula and generate a set of letter aliases for them.
	 * Aliases will be generated in the order they appear in the formula.
	 *
	 * Example:
	 * CFormulaHelper::getFormulaIds('1 or (2 and 3) or 2');
	 *
	 * // array(1 => 'A', 2 => 'B', 3 => 'C')
	 *
	 * @param string $formula	a formula with numeric IDs
	 *
	 * @return array
	 */
	public static function getFormulaIds($formula) {
		preg_match_all('/\d+/', $formula, $matches);

		$ids = array_keys(array_flip($matches[0]));

		$i = 0;
		$formulaIds = [];
		foreach ($ids as $id) {
			$formulaIds[$id] = num2letter($i);

			$i++;
		}

		return $formulaIds;
	}

	/**
	 * Replace numeric IDs with formula IDs using the pairs given in $ids.
	 *
	 * @param string 	$formula
	 * @param array 	$ids		array of numeric ID - formula ID pairs
	 *
	 * @return string
	 */
	public static function replaceNumericIds($formula, array $ids) {
		foreach ($ids as $id => $formulaId) {
			$formula = str_replace('{'.$id.'}', $formulaId, $formula);
		}

		return $formula;
	}

	/**
	 * Replace formula IDs with numeric IDs using the pairs given in $ids.
	 *
	 * Notes:
	 *     - $formula must be valid before the function call
	 *     - $ids must contain all constants used in the $formula
	 *
	 * @param string 	$formula
	 * @param array 	$ids		array of formula ID - numeric ID pairs
	 *
	 * @return string
	 */
	public static function replaceLetterIds($formula, array $ids) {
		$parser = new CConditionFormula();
		$parser->parse($formula);

		foreach (array_reverse($parser->constants) as $constant) {
			$formula = substr_replace($formula, '{'.$ids[$constant['value']].'}', $constant['pos'],
				strlen($constant['value'])
			);
		}

		return $formula;
	}

	/**
	 * Compare formula IDs.
	 *
	 * @param string $formulaId1
	 * @param string $formulaId2
	 *
	 * @return int
	 */
	public static function compareFormulaIds($formulaId1, $formulaId2) {
		$len1 = strlen($formulaId1);
		$len2 = strlen($formulaId2);

		if ($len1 == $len2) {
			return strcmp($formulaId1, $formulaId2);
		}
		else {
			return ($len1 < $len2) ? -1 : 1;
		}
	}

	/**
	 * Returns next formula ID - A => B, B => C, ..., Z => AA, ..., ZZ => AAA, ...
	 *
	 * @param array $formulaIds
	 *
	 * @return string
	 */
	public static function getNextFormulaId(array $formulaIds) {
		if (!$formulaIds) {
			$nextFormulaId = 'A';
		}
		else {
			usort($formulaIds, ['CConditionHelper', 'compareFormulaIds']);

			$lastFormulaId = array_pop($formulaIds);

			$calculateNextFormulaId = function($formulaId) use (&$calculateNextFormulaId) {
				$head = substr($formulaId, 0, -1);
				$tail = substr($formulaId, -1);

				if ($tail == 'Z') {
					$nextFormulaId = $head ? $calculateNextFormulaId($head).'A' : 'AA';
				}
				else {
					$nextFormulaId = $head.chr(ord($tail) + 1);
				}

				return $nextFormulaId;
			};

			$nextFormulaId = $calculateNextFormulaId($lastFormulaId);
		}

		return $nextFormulaId;
	}

	/**
	 * Sorts the conditions based on the given formula.
	 *
	 * @param array  $conditions
	 * @param string $formula
	 * @param string $pk_field_name
	 *
	 * @return array
	 */
	public static function sortConditionsByFormula(array $conditions, string $formula, string $pk_field_name): array {
		preg_match_all('/\d+/', $formula, $matches);
		$order = [];
		foreach ($matches[0] as $key => $conditionid) {
			$order += [$conditionid => $key];
		}

		usort($conditions, static function (array $a, array $b) use ($order, $pk_field_name) {
			return $order[$a[$pk_field_name]] <=> $order[$b[$pk_field_name]];
		});

		return $conditions;
	}

	/**
	 * Sorts the action conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 * @param int   $eventsource
	 *
	 * @return array
	 */
	public static function sortActionConditions(array $conditions, int $eventsource): array {
		$ct_order = array_flip(get_conditions_by_eventsource($eventsource));

		usort($conditions, static function (array $row1, array $row2) use ($ct_order) {
			if ($cmp = $ct_order[$row1['conditiontype']] <=> $ct_order[$row2['conditiontype']]) {
				return $cmp;
			}

			foreach (['operator', 'value2', 'value'] as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});

		return $conditions;
	}

	/**
	 * Sorts the LLD rule filter conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array  $conditions
	 * @param string $pk_field_name
	 *
	 * @return array
	 */
	public static function sortLldRuleFilterConditions(array $conditions, string $pk_field_name): array {
		usort($conditions, static function (array $row1, array $row2) use ($pk_field_name) {
			// To correctly sort macros, only the internal part of the macro needs to be sorted.
			// See order_macros() for details.
			if ($cmp = strnatcmp(substr($row1['macro'], 2, -1), substr($row2['macro'], 2, -1))) {
				return $cmp;
			}

			foreach (['operator', 'value', $pk_field_name] as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});

		return $conditions;
	}

	/**
	 * Sorts the correlation conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 *
	 * @return array
	 */
	public static function sortCorrelationConditions(array $conditions): array {
		$type_order = array_flip(array_keys(CCorrelationHelper::getConditionTypes()));

		usort($conditions, static function (array $row1, array $row2) use ($type_order) {
			if ($cmp = $type_order[$row1['type']] <=> $type_order[$row2['type']]) {
				return $cmp;
			}

			switch ($row1['type']) {
				case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
					$field_names = ['tag'];
					break;

				case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
					$field_names = ['operator', 'groupid'];
					break;

				case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
					$field_names = ['oldtag', 'newtag'];
					break;

				case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
					$field_names = ['tag', 'operator', 'value'];
					break;
			}

			foreach ($field_names as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});

		return $conditions;
	}
}
