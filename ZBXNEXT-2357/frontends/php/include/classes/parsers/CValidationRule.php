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

class CValidationRule {
	const STATE_BEGIN = 0;
	const STATE_END = 1;

	/**
	 * An error message if validation rule is not valid
	 *
	 * @var string
	 */
	private $error = '';

//	public function __construct() {
//	}

	public function parse($buffer) {
		$this->error = '';

		$pos = 0;
		$state = self::STATE_BEGIN;
		$rules = array();
		$is_empty = true;

		while (isset($buffer[$pos])) {
			switch ($state) {
				case self::STATE_BEGIN:
					switch ($buffer[$pos]) {
						case ' ':
							$pos++;
							break;
						default:
							$is_empty = false;
							$rule = array();

							if (!$this->parseFatal($buffer, $pos, $rule)
									&& !$this->parseRequired($buffer, $pos, $rule)
									&& !$this->parseIn($buffer, $pos, $rule)
									&& !$this->parseDB($buffer, $pos, $rule)
									&& !$this->parseRequiredIf($buffer, $pos, $rule)) {

								// incorrect validation rule
								break 3;
							}

							if (array_key_exists(key($rule), $rules)) {
								$this->error = 'Validation rule "'.key($rule).'" already exists.';
								return false;
							}

							$rules = array_merge($rules, $rule);
							$state = self::STATE_END;
							break;
					}
					break;
				case self::STATE_END:
					switch ($buffer[$pos]) {
						case ' ':
							$pos++;
							break;
						case '|':
							$state = self::STATE_BEGIN;
							$pos++;
							break;
						default:
							// incorrect validation rule
							break 3;
					}
					break;
			}
		}

		if ($state == self::STATE_BEGIN && !$is_empty) {
			$this->error = 'Cannot parse validation rules "'.$buffer.'" at position '.$pos.'.';
			return false;
		}

		return $rules;
	}

	/**
	 * Returns the error message if validation rule is invalid.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * fatal
	 *
	 * 'fatal' => true
	 */
	private function parseFatal($buffer, &$pos, &$rule) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $i), 'fatal', 5)) {
			return false;
		}

		$i += 5;

		if (isset($buffer[$i]) && $buffer[$i] != ' ' && $buffer[$i] != '|') {
			return false;
		}

		$pos = $i;
		$rule['fatal'] = true;

		return true;
	}

	/**
	 * required
	 *
	 * 'required' => true
	 */
	private function parseRequired($buffer, &$pos, &$rules) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $pos), 'required', 8)) {
			return false;
		}

		$i += 8;

		if (isset($buffer[$i]) && $buffer[$i] != ' ' && $buffer[$i] != '|') {
			return false;
		}

		$pos = $i;
		$rules['required'] = true;

		return true;
	}

	/**
	 * in <value1>[,...,<valueN>]
	 *
	 * 'in' => array('<value1>', ..., '<valueN>')
	 */
	private function parseIn($buffer, &$pos, &$rules) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $i), 'in ', 3)) {
			return false;
		}

		$i += 3;

		while (isset($buffer[$i]) && $buffer[$i] == ' ') {
			$i++;
		}

		$values = array();

		if (!$this->parseValues($buffer, $i, $values)) {
			return false;
		}

		$pos = $i;
		$rules['in'] = $values;

		return true;
	}

	/**
	 * db <table>.<field>
	 *
	 * 'db' => array(
	 *     'table' => '<table>',
	 *     'field' => '<field>'
	 * )
	 */
	private function parseDB($buffer, &$pos, &$rules) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $i), 'db ', 3)) {
			return false;
		}

		$i += 3;

		while (isset($buffer[$i]) && $buffer[$i] == ' ') {
			$i++;
		}

		$table = '';

		if (!$this->parseField($buffer, $i, $table) || !isset($buffer[$i]) || $buffer[$i++] != '.') {
			return false;
		}

		$field = '';

		if (!$this->parseField($buffer, $i, $field)) {
			return false;
		}

		if (isset($buffer[$i]) && $buffer[$i] != ' ' && $buffer[$i] != '|') {
			return false;
		}

		$pos = $i;
		$rules['db'] = array(
			'table' => $table,
			'field' => $field
		);

		return true;
	}

	/**
	 * required_if:<field>,<value1>[...,<valueN>]
	 *
	 * 'required_if' => array(
	 *     'field' => '<field>',
	 *     'values' => array('<value1>', ..., '<valueN>')
	 * )
	 */
	private function parseRequiredIf($buffer, &$pos, &$rules) {
		$i = $pos;
		$rule = array();

		if (0 != strncmp(substr($buffer, $i), 'required_if ', 12)) {
			return false;
		}

		$i += 12;

		while (isset($buffer[$i]) && $buffer[$i] == ' ') {
			$i++;
		}

		while (true) {
			$field = '';

			if (!$this->parseField($buffer, $i, $field) || !isset($buffer[$i]) || $buffer[$i++] != ':') {
				return false;
			}

			$rule[$field] = array();

			if (!$this->parseValues($buffer, $i, $rule[$field])) {
				return false;
			}

			while (isset($buffer[$i]) && $buffer[$i] == ' ') {
				$i++;
			}

			if (!isset($buffer[$i]) || $buffer[$i] == '|') {
				break;
			}
		}

		if (isset($buffer[$i]) && $buffer[$i] != '|') {
			return false;
		}

		$pos = $i;
		$rules['required_if'] = $rule;

		return true;
	}

	/**
	 * <field>
	 */
	private function parseField($buffer, &$pos, &$field) {
		$matches = array();
		if (1 != preg_match('/^[A-Za-z0-9_]+/', substr($buffer, $pos), $matches))
			return false;

		$pos += strlen($matches[0]);
		$field = $matches[0];

		return true;
	}

	/**
	 * <value1>[,<value2>[...,<valueN>]]
	 */
	private function parseValues($buffer, &$pos, array &$values) {
		$i = $pos;

		while (true) {
			$value = '';

			if (!isset($buffer[$i]) || !$this->parseValue($buffer, $i, $value)) {
				return false;
			}

			$values[] = $value;

			if (!isset($buffer[$i]) || $buffer[$i] == ' ' || $buffer[$i] == '|') {
				break;
			}

			$i++;
		}

		$pos = $i;

		return true;
	}

	/**
	 * <value>
	 */
	private function parseValue($buffer, &$pos, &$value) {
		$i = $pos;

		while (isset($buffer[$i]) && $buffer[$i] != ',' && $buffer[$i] != ' ' && $buffer[$i] != '|') {
			$i++;
		}

		if ($pos == $i) {
			return false;
		}

		$value = substr($buffer, $pos, $i - $pos);
		$pos = $i;

		return true;
	}
}
