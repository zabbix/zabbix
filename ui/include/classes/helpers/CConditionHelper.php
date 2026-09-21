<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * A class to perform operations with conditions.
 */
class CConditionHelper {

	/**
	 * Get formula with condition IDs of the given conditions using the given evaluation method and field to group
	 * conditions by.
	 *
	 * Supported $evalType values:
	 * - CONDITION_EVAL_TYPE_AND_OR
	 * - CONDITION_EVAL_TYPE_AND
	 * - CONDITION_EVAL_TYPE_OR
	 *
	 * Example:
	 * echo CConditionHelper::getFormula(array(
	 *     1 => ['type' => '1'],
	 *     2 => ['type' => '1'],
	 *     5 => ['type' => '2']
	 * ), CONDITION_EVAL_TYPE_AND_OR);
	 *
	 * // ({1} or {2}) and {5}
	 *
	 * Keep in sync with JS getConditionFormula().
	 *
	 * @param array  $conditions
	 * @param string $group_field_name
	 * @param int    $evalType
	 *
	 * @return string
	 */
	public static function getEvalFormula(array $conditions, string $group_field_name, int $evalType): string {
		$groupedConditions = [];
		foreach ($conditions as $conditionid => $condition) {
			$groupedConditions[$condition[$group_field_name]][] = '{'.$conditionid.'}';
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
	 * Replace the user-defined formula IDs in the given formula and conditions with system-generated ones.
	 *
	 * Example:
	 * $formula = '(X or Y) and Z';
	 * $conditions = [
	 *     1 => ['formulaid' => 'X', 'type' => 1],
	 *     2 => ['formulaid' => 'Y', 'type' => 1],
	 *     5 => ['formulaid' => 'Z', 'type' => 2]
	 * ];
	 * CConditionHelper::resetFormulaIds($formula, $conditions);
	 *
	 * // $formula = '(A or B) and C';
	 * // $conditions = [
	 * //     1 => ['formulaid' => 'A', 'type' => 1],
	 * //     2 => ['formulaid' => 'B', 'type' => 1],
	 * //     5 => ['formulaid' => 'C', 'type' => 2]
	 * // ];
	 *
	 * @param string $formula
	 * @param array  $conditions
	 */
	public static function resetFormulaIds(string &$formula, array &$conditions): void {
		self::replaceFormulaIds($formula, $conditions);
		self::addFormulaIds($conditions, $formula);
		self::replaceConditionIds($formula, $conditions);
	}

	/**
	 * Add a formula ID to each of the given conditions according to the condition ID contained in the given formula.
	 *
	 * Example:
	 * $formula = '({1} or {2}) and {5}';
	 * $conditions = [
	 *     1 => ['type' => 1],
	 *     2 => ['type' => 1],
	 *     5 => ['type' => 2]
	 * ];
	 * CConditionHelper::addFormulaIds($conditions, $formula);
	 *
	 * // $conditions = [
	 * //     1 => ['formulaid' => 'A', 'type' => 1],
	 * //     2 => ['formulaid' => 'B', 'type' => 1],
	 * //     5 => ['formulaid' => 'C', 'type' => 2]
	 * // ];
	 *
	 * @param array  $conditions
	 * @param string $formula
	 */
	public static function addFormulaIds(array &$conditions, string $formula): void {
		preg_match_all('/\d+/', $formula, $matches);

		$conditionids = array_keys(array_flip($matches[0]));

		$i = 0;
		foreach ($conditionids as $conditionid) {
			// Custom formula may contain deleted condition IDs.
			if (!array_key_exists($conditionid, $conditions)) {
				continue;
			}

			$conditions[$conditionid]['formulaid'] = num2letter($i);

			$i++;
		}
	}

	/**
	 * Replace condition IDs in the given formula with the appropriate formula IDs of the given conditions.
	 *
	 * Example:
	 * $formula = '({1} or {2}) and {5}';
	 * $conditions = [
	 *     1 => ['formulaid' => 'A', 'type' => 1],
	 *     2 => ['formulaid' => 'B', 'type' => 1],
	 *     5 => ['formulaid' => 'C', 'type' => 2]
	 * ];
	 * CConditionHelper::replaceConditionIds($formula, $conditions);
	 *
	 * // $formula = '(A or B) and C';
	 *
	 * @param string $formula
	 * @param array  $conditions
	 */
	public static function replaceConditionIds(string &$formula, array $conditions): void {
		foreach ($conditions as $conditionid => $condition) {
			$formula = str_replace('{'.$conditionid.'}', $condition['formulaid'], $formula);
		}

		// Replace each deleted condition ID with the next letter.

		preg_match_all('/\d+/', $formula, $matches);

		$i = count($conditions);

		foreach (array_keys(array_flip($matches[0])) as $conditionid) {
			$formula = str_replace('{'.$conditionid.'}', num2letter($i++), $formula);
		}
	}

	/**
	 * Replace formula IDs of the given formula with the appropriate condition IDs of the given conditions.
	 *
	 * Example:
	 * $formula = '(A or B) and C';
	 * $conditions = [
	 *     1 => ['formulaid' => 'A', 'type' => 1],
	 *     2 => ['formulaid' => 'B', 'type' => 1],
	 *     5 => ['formulaid' => 'C', 'type' => 2]
	 * ];
	 * CConditionHelper::replaceFormulaIds($formula, $conditions);
	 *
	 * // $formula = '({1} or {2}) and {5}';
	 *
	 * @param string $formula
	 * @param array  $conditions
	 */
	public static function replaceFormulaIds(string &$formula, array $conditions): void {
		$parser = new CConditionFormulaParser();
		$parser->parse($formula);

		$conditionids = [];

		foreach ($conditions as $conditionid => $condition) {
			$conditionids[$condition['formulaid']] = $conditionid;
		}

		foreach (array_reverse($parser->getConstants()) as $constant) {
			$formula = substr_replace($formula, '{'.$conditionids[$constant['value']].'}', $constant['pos'],
				strlen($constant['value'])
			);
		}
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
	 * @param array  $conditions[<conditionid>]
	 * @param string $formula
	 */
	public static function sortConditionsByFormula(array &$conditions, string $formula): void {
		preg_match_all('/\d+/', $formula, $matches);
		$order = [];
		foreach ($matches[0] as $key => $conditionid) {
			$order += [$conditionid => $key];
		}

		uksort($conditions, static function (string $a, string $b) use ($order) {
			return bccomp($order[$a], $order[$b]);
		});
	}

	/**
	 * Sorts the action conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 * @param int   $eventsource
	 */
	public static function sortActionConditions(array &$conditions, int $eventsource): void {
		$ct_order = array_flip(get_conditions_by_eventsource($eventsource));

		uasort($conditions, static function (array $row1, array $row2) use ($ct_order) {
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
	}

	/**
	 * Sorts the LLD rule filter conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 */
	public static function sortLldRuleConditions(array &$conditions): void {
		uasort($conditions, static function (array $row1, array $row2) {
			// To correctly sort macros, only the internal part of the macro needs to be sorted.
			// See order_macros() for details.
			if ($cmp = strnatcmp(substr($row1['macro'], 2, -1), substr($row2['macro'], 2, -1))) {
				return $cmp;
			}

			foreach (['operator', 'value'] as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});
	}

	/**
	 * Sorts the correlation conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 */
	public static function sortCorrelationConditions(array &$conditions): void {
		$type_order = array_flip(array_keys(CCorrelationHelper::getConditionTypes()));

		uasort($conditions, static function (array $row1, array $row2) use ($type_order) {
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
	}

	/**
	 * Sorts the Telemetry query item filter conditions based on the calculation types And/Or, Or.
	 *
	 * @param array $conditions  Filter conditions by reference.
	 */
	public static function sortTelemetryQueryFilterConditions(array &$conditions): void {
		uasort($conditions, static function (array $row1, array $row2) {
			foreach (['column', 'attribute_key'] as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});
	}

	/**
	 * Sorts the CEP rule conditions based on the calculation types And/Or, And, Or.
	 *
	 * @param array $conditions
	 */
	public static function sortCepRuleConditions(array &$conditions): void {
		$type_order = array_flip(CCepRuleHelper::CONDITION_TYPES);

		uasort($conditions, static function (array $row1, array $row2) use ($type_order): int {
			if ($cmp = $type_order[$row1['type']] <=> $type_order[$row2['type']]) {
				return $cmp;
			}

			$field_names = match ((int) $row1['type']) {
				CCepRuleHelper::CONDITION_EVENT_NAME => ['operator', 'event_name'],
				CCepRuleHelper::CONDITION_TAG => ['operator', 'tag'],
				CCepRuleHelper::CONDITION_TAG_VALUE => ['tag', 'operator', 'tag_value'],
				CCepRuleHelper::CONDITION_SEVERITY => ['operator', 'severity'],
				CCepRuleHelper::CONDITION_HOST_VISIBLE_NAME => ['operator', 'host_name'],
				CCepRuleHelper::CONDITION_HOST_GROUP_NAME => ['operator', 'host_group_name'],
				CCepRuleHelper::CONDITION_TIME_PERIOD => ['operator', 'time_period']
			};

			foreach ($field_names as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});
	}

	/**
	 * Sorts the CEP rule operation conditions based on the calculation types And/Or, Or.
	 *
	 * @param array $conditions
	 */
	public static function sortCepRuleOperationConditions(array &$conditions): void {
		$type_order = array_flip(CCepRuleHelper::OPERATION_CONDITION_TYPES);

		uasort($conditions, static function (array $row1, array $row2) use ($type_order): int {
			if ($cmp = $type_order[$row1['type']] <=> $type_order[$row2['type']]) {
				return $cmp;
			}

			$field_names = match ((int) $row1['type']) {
				ZBX_CONDITION_TYPE_EVENT_TAG => ['operator', 'tag'],
				ZBX_CONDITION_TYPE_EVENT_TAG_VALUE => ['tag', 'operator', 'tag_value'],
				ZBX_CONDITION_TYPE_EVENT_OPEN,
				ZBX_CONDITION_TYPE_EVENT_FIRST,
				ZBX_CONDITION_TYPE_EVENT_LAST,
				ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
				ZBX_CONDITION_TYPE_EVENT_COPIED,
				ZBX_CONDITION_TYPE_EVENT_SUPPRESSED => ['operator']
			};

			foreach ($field_names as $field_name) {
				if ($cmp = strnatcasecmp($row1[$field_name], $row2[$field_name])) {
					return $cmp;
				}
			}

			return 0;
		});
	}

	/**
	 * Check that all constants of formula are specified in the filter conditions of the given LLD rules or overrides.
	 *
	 * @param array  $objects
	 * @param string $path
	 *
	 * @throws APIException
	 */
	public static function checkFilterFormula(array $objects, string $path = '/'): void {
		$condition_formula_parser = new CConditionFormulaParser();

		foreach ($objects as $i => $object) {
			if (!array_key_exists('filter', $object)
					|| $object['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				continue;
			}

			$condition_formula_parser->parse($object['filter']['formula']);

			$constants = array_unique(array_column($condition_formula_parser->getConstants(), 'value'));
			$subpath = ($path === '/' ? $path : $path.'/').($i + 1).'/filter';

			$condition_formulaids = array_column($object['filter']['conditions'], 'formulaid');

			foreach ($constants as $constant) {
				if (!in_array($constant, $condition_formulaids)) {
					throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$subpath.'/formula', _s('missing filter condition "%1$s"', $constant)
					));
				}
			}

			foreach ($object['filter']['conditions'] as $j => $condition) {
				if (!in_array($condition['formulaid'], $constants)) {
					throw new APIException(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$subpath.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
					));
				}
			}
		}
	}
}
