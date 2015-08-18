<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CConditionValidator extends CValidator {

	/**
	 * Error message if the formula is invalid.
	 *
	 * @var string
	 */
	public $messageInvalidFormula;

	/**
	 * Error message if the formula contains a condition that is not defined in the "conditions" array.
	 *
	 * @var string
	 */
	public $messageMissingCondition;

	/**
	 * Error message if the "conditions" array contains a condition that is not used in the formula.
	 *
	 * @var string
	 */
	public $messageUnusedCondition;

	/**
	 * Validates the given condition formula and checks if the given conditions match the formula.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function validate($object)
	{
		// validate only custom expressions
		if ($object['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return true;
		}

		// check if the formula is valid
		$parser = new CConditionFormula();
		if (!$parser->parse($object['formula'])) {
			$this->error($this->messageInvalidFormula, $object['formula'], $parser->error);

			return false;
		}

		// check that all conditions used in the formula are defined in the "conditions" array
		$conditions = zbx_toHash($object['conditions'], 'formulaid');
		$constants = array_unique(zbx_objectValues($parser->constants, 'value'));
		foreach ($constants as $constant) {
			if (!array_key_exists($constant, $conditions)) {
				$this->error($this->messageMissingCondition, $constant, $object['formula']);

				return false;
			}

			unset($conditions[$constant]);
		}

		// check that the "conditions" array has no unused conditions
		if ($conditions) {
			$condition = reset($conditions);
			$this->error($this->messageUnusedCondition, $condition['formulaid'], $object['formula']);

			return false;
		}

		return true;
	}
}
