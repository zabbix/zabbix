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
	public $constants = array();

	/**
	 * Array of supported operators.
	 *
	 * @var array
	 */
	protected $allowedOperators = array('and', 'or');

	/**
	 * Current position on a parsed element.
	 *
	 * @var integer
	 */
	private $pos;

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
		$this->constants = array();

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

		$this->constants = array_unique($this->constants);

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
		$this->constants[] = $constant;

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
}
