<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		$groupedConditions = array();
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

		$groupFormulas = array();
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
		$matches = array();
		preg_match_all('/\d+/', $formula, $matches);

		$ids = array_keys(array_flip($matches[0]));

		$i = 0;
		$formulaIds = array();
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
	 * @param string 	$formula
	 * @param array 	$ids		array of formula ID - numeric ID pairs
	 *
	 * @return string
	 */
	public static function replaceLetterIds($formula, array $ids) {
		foreach ($ids as $formulaId => $id) {
			$formula = str_replace($formulaId, '{'.$id.'}', $formula);
		}

		return $formula;
	}
}
