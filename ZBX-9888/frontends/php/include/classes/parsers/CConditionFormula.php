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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

class CConditionFormula {

	// possible parsing states
	const STATE_AFTER_OPEN_BRACE = 0;
	const STATE_AFTER_OPERATOR = 1;
	const STATE_AFTER_CLOSE_BRACE = 2;
	const STATE_AFTER_CONSTANT = 3;

	/**
	 * Set to true of the formula is valid.
	 *
	 * @var bool
	 */
	public $isValid;

	/**
	 * Error message if the formula is invalid.
	 *
	 * @var string
	 */
	public $error;

	/**
	 * The parsed formula.
	 *
	 * @var string
	 */
	public $formula;

	/**
	 * Array of unique constants used in the formula.
	 *
	 * @var array
	 */
	public $constants = [];

	/**
	 * Array of supported operators.
	 *
	 * @var array
	 */
	protected $allowedOperators = ['and', 'or'];

	/**
	 * Current position on a parsed element.
	 *
	 * @var integer
	 */
	private $pos;

	/**
	 * Array of formula conditions.
	 *
	 * @var array
	 */
	private $conditions_map = [];

	/**
	 * Path to constant in conditions map.
	 *
	 * @var array
	 */
	private $formula_path = [0 => 'L1'];

	/**
	 * Last discovered operator.
	 *
	 * @var string
	 */
	private $last_operator = '';

	/**
	 * Count of entrances for current level in the conditions map.
	 *
	 * @var array
	 */
	private $level_entrances = [0 => 1];

	/**
	 * Parses the given condition formula.
	 *
	 * @param string $formula
	 *
	 * @return bool		true if the formula is valid
	 */
	public function parse($formula) {
		$this->isValid = true;
		$this->error = '';
		$this->constants = [];

		$this->pos = 0;
		$this->formula = $formula;

		$state = self::STATE_AFTER_OPEN_BRACE;
		$afterSpace = false;
		$level = 0;

		while (isset($this->formula[$this->pos])) {
			if ($this->formula[$this->pos] === ' ') {
				$afterSpace = true;
				$this->pos++;

				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($this->formula[$this->pos]) {
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_OPERATOR:
					switch ($this->formula[$this->pos]) {
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CLOSE_BRACE:
					switch ($this->formula[$this->pos]) {
						case ')':
							$state = self::STATE_AFTER_CLOSE_BRACE;
							if ($level == 0) {
								break 3;
							}
							$level--;
							break;
						default:
							if ($this->parseOperator()) {
								$state = self::STATE_AFTER_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CONSTANT:
					switch ($this->formula[$this->pos]) {
						case ')':
							$state = self::STATE_AFTER_CLOSE_BRACE;
							if ($level == 0) {
								break 3;
							}
							$level--;
							break;
						default:
							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseOperator()) {
								$state = self::STATE_AFTER_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;
			}

			$afterSpace = false;
			$this->pos++;
			$this->fillConditionsMap($state, $level);
		}

		if ($this->pos == 0) {
			$this->error = _('expression is empty');
			$this->isValid = false;
		}

		if ($level != 0 || isset($this->formula[$this->pos]) || $state == self::STATE_AFTER_OPERATOR) {
			$this->error = _s('check expression starting from "%1$s"',
				substr($this->formula, $this->pos == 0 ? 0 : $this->pos - 1)
			);
			$this->isValid = false;
		}

		return $this->isValid;
	}

	/**
	 * Parses a constant and advances the position to its last character.
	 *
	 * @return bool
	 */
	protected function parseConstant() {
		$start = $this->pos;

		while (isset($this->formula[$this->pos]) && $this->isConstantChar($this->formula[$this->pos])) {
			$this->pos++;
		}

		// empty constant
		if ($start == $this->pos) {
			return false;
		}

		$constant = substr($this->formula, $start, $this->pos - $start);
		$this->constants[] = [
			'value' => $constant,
			'pos' => $start
		];

		$this->pos--;

		return true;
	}

	/**
	 * Parses an operator and advances the position to its last character.
	 *
	 * @return bool
	 */
	protected function parseOperator() {
		$start = $this->pos;

		while (isset($this->formula[$this->pos]) && $this->isOperatorChar($this->formula[$this->pos])) {
			$this->pos++;
		}

		// empty operator
		if ($start == $this->pos) {
			return false;
		}

		$operator = substr($this->formula, $start, $this->pos - $start);

		$this->pos--;

		// check if this is a valid operator
		if (!in_array($operator, $this->allowedOperators)) {
			return false;
		}

		$this->last_operator = $operator;

		return true;
	}

	/**
	 * Returns true if the given character is a valid constant character.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isConstantChar($c) {
		return ($c >= 'A' && $c <= 'Z');
	}

	/**
	 * Returns true if the given character is a valid operator character.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isOperatorChar($c) {
		return ($c >= 'a' && $c <= 'z');
	}

	/**
	 * Fill conditions map with values.
	 *
	 * @param int $state
	 * @param int $level
	 */
	protected function fillConditionsMap($state, $level) {
		switch ($state) {
			case self::STATE_AFTER_OPEN_BRACE :
				if (!array_key_exists($level, $this->level_entrances)) {
					$this->level_entrances[$level] = 0;
				}
				else {
					$this->level_entrances[$level]++;
				}
				array_push($this->formula_path, 'L'.$this->level_entrances[$level]);
				break;

			case self::STATE_AFTER_OPERATOR :
				break;

			case self::STATE_AFTER_CLOSE_BRACE :
				array_pop($this->formula_path);
				break;

			case self::STATE_AFTER_CONSTANT :
				$this->updateConditionsMap($this->conditions_map, end($this->constants)['value'], $this->formula_path);
				break;
		}
	}

	/**
	 * Update conditions map values.
	 *
	 * @param array $target_element
	 * @param string $constant
	 * @param array $path
	 */
	protected function updateConditionsMap(&$target_element, $constant, $path) {
		$step = array_shift($path);

		if (!array_key_exists($step, $target_element)) {
			$target_element[$step] = ['operator' => $this->last_operator, 'constant' => []];
			$this->last_operator = '';
		}

		if(count($path) > 0) {
			$this->updateConditionsMap($target_element[$step]['constant'],$constant,$path);
		}
		else {
			array_push($target_element[$step]['constant'], ['operator' => $this->last_operator, 'constant' => $constant]);
			$this->last_operator = '';
		}
	}

	/**
	 * Get array with constants that are compared by and operator
	 *
	 * @param array $conditions
	 *
	 * @return array $constants
	 */
	protected function getAndConditions($conditions) {
		$isand = false;
		$previous_is_first = false;
		$previous_constant = [];
		$andcounter = 0;
		$constants = ['all' => [], 'and' => []];

		foreach($conditions as $level_key => $condition) {
			$isand = false;
			if ($condition['operator'] === ''){
				// empty operator is only for firt constant in formula / scope
				$previous_is_first = true;
			}
			else if($condition['operator'] === 'and') {
				$isand = true;
			}
			else {
				// increase andcounter for next and operator
				$andcounter++;
			}

			if (is_array($condition['constant'])) {
				// get conditions from sub-scope
				$constant = $this->getAndConditions($condition['constant']);

				if ($isand) {
					if ($previous_constant) {
						// add previous constant to and comparables
						$constants['and'][$andcounter][] = array_key_exists('all', $previous_constant)
							? $previous_constant['all']
							: $previous_constant;
						$constants['and'][$andcounter] = array_unique($constants['and'][$andcounter], SORT_REGULAR);
					}

					// add sub-scope constants to current level "and" comparables
					$constants['and'][$andcounter][] = $constant['all'];
					$previous_constant = [];
				}
				else {
					$previous_constant = $constant;
				}

				// add sub-scope constants to result array
				$constants[] = $constant;

				// add sub-scope constants to current level "all" items, to pass those to parent level
				$constants['all'] = array_merge($constants['all'], $constant['all']);
			}
			else {
				$constants['all'][] = $condition['constant'];
				if ($isand) {
					if ($previous_constant) {
						// add previous constant to and comparables
						$constants['and'][$andcounter][] = $previous_constant;
					}

					$constants['and'][$andcounter][] = [0 => $condition['constant']];
					$constants['and'][$andcounter] = array_unique($constants['and'][$andcounter], SORT_REGULAR);
				}
				$previous_constant = [0 => $condition['constant']];
			}
		}

		return $constants;
	}

	/**
	 * Check if triggers comparison in formula is valid.
	 *
	 * Comparing several triggers with "and" operator is not allowed.
	 *
	 * @param unknown $triggers
	 *
	 * return bool
	 */
	public function validTriggersComparison(&$triggers) {
		$and_comparables = $this->getAndConditions($this->conditions_map);
		$triggers_and_count = $this->getCountTriggersAnd($triggers, $and_comparables);

		return ($triggers_and_count > 1) ? false : true;
	}

	/**
	 * Get count of triggers compared with "and".
	 *
	 * @param unknown $triggers
	 * @param unknown $and_comparables
	 *
	 * return int $triggers_and_count
	 */
	protected function getCountTriggersAnd(&$triggers, $and_comparables) {
		if (array_key_exists('all', $and_comparables)) {
			unset($and_comparables['all']);
		}

		$triggers_and_count = 0;
		foreach ($and_comparables as $key => $conditions) {
			if($key === 'and') {
				// and conditions
				foreach ($conditions as $and_condition) {
					$triggers_and_count = 0;

					foreach ($and_condition as $constant_key => $constant) {
						if (array_intersect($constant, $triggers)) {
							$triggers_and_count++;
						}
					}

					if ($triggers_and_count > 1) {
						return $triggers_and_count;
					}
				}
			}
			else {
				// subquery scope
				$triggers_and_count = $this->getCountTriggersAnd($triggers, $conditions);
				if ($triggers_and_count > 1) {
					return $triggers_and_count;
				}
			}
		}

		return $triggers_and_count;
	}
}
