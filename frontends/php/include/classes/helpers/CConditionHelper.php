<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	 * var_dump(CFormulaHelper::getFormulaIds('1 or (2 and 3) or 2'));
	 *
	 * // array(1 => 'A', 2 => 'B', 3 => 'C')
	 *
	 * @param string $formula	a formula with numeric IDs
	 *
	 * @return array
	 */
	public static function getFormulaIds($formula) {
		$matches = [];
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
	 * Sort conditions by formula id as if they were numbers.
	 *
	 * @param array		$conditions		conditions
	 * @return array
	 */
	public static function sortConditionsByFormulaId($conditions) {
		uasort($conditions, function ($condition1, $condition2) {
			return CConditionHelper::compareFormulaIds($condition1['formulaid'], $condition2['formulaid']);
		});

		return $conditions;
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
}
