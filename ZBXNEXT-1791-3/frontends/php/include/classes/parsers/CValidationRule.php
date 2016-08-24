<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * An error message if validation rule is not valid.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Parse validation rule. Returns array of rules or fail if $buffer is not valid.
	 *
	 * @param string $buffer
	 *
	 * @return array|bool
	 */
	public function parse($buffer) {
		$this->error = '';

		$pos = 0;
		$state = self::STATE_BEGIN;
		$rules = [];
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
							$rule = [];

							if (!$this->parseTime($buffer, $pos, $rule)					// time
									&& !$this->parseString($buffer, $pos, $rule)		// string
									&& !$this->parseRequired($buffer, $pos, $rule)		// required
									&& !$this->parseNotEmpty($buffer, $pos, $rule)		// not_empty
									&& !$this->parseLE($buffer, $pos, $rule)			// le
									&& !$this->parseJson($buffer, $pos, $rule)			// json
									&& !$this->parseInt32($buffer, $pos, $rule)			// int32
									&& !$this->parseIn($buffer, $pos, $rule)			// in
									&& !$this->parseId($buffer, $pos, $rule)			// id
									&& !$this->parseGE($buffer, $pos, $rule)			// ge
									&& !$this->parseFatal($buffer, $pos, $rule)			// fatal
									&& !$this->parseDB($buffer, $pos, $rule)			// db
									&& !$this->parseArrayId($buffer, $pos, $rule)		// array_id
									&& !$this->parseArrayDB($buffer, $pos, $rule)		// array_db
									&& !$this->parseArray($buffer, $pos, $rule)) {		// array
								// incorrect validation rule
								break 3;
							}

							if (array_key_exists(key($rule), $rules)) {
								// the message can be not translated because it is an internal error
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

		if (isset($buffer[$pos])) {
			// the message can be not translated because it is an internal error
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
		if (strncmp(substr($buffer, $pos), 'fatal', 5) != 0) {
			return false;
		}

		$pos += 5;
		$rule['fatal'] = true;

		return true;
	}

	/**
	 * time
	 *
	 * 'time' => true
	 */
	private function parseTime($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'time', 4) != 0) {
			return false;
		}

		$pos += 4;
		$rules['time'] = true;

		return true;
	}

	/**
	 * string
	 *
	 * 'string' => true
	 */
	private function parseString($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'string', 6) != 0) {
			return false;
		}

		$pos += 6;
		$rules['string'] = true;

		return true;
	}

	/**
	 * required
	 *
	 * 'required' => true
	 */
	private function parseRequired($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'required', 8) != 0) {
			return false;
		}

		$pos += 8;
		$rules['required'] = true;

		return true;
	}

	/**
	 * not_empty
	 *
	 * 'not_empty' => true
	 */
	private function parseNotEmpty($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'not_empty', 9) != 0) {
			return false;
		}

		$pos += 9;
		$rules['not_empty'] = true;

		return true;
	}

	/**
	 * le <value>
	 *
	 * 'le' => '<value>'
	 */
	private function parseLE($buffer, &$pos, &$rules) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $i), 'le ', 3)) {
			return false;
		}

		$i += 3;
		$value = '';

		while (isset($buffer[$i]) && $buffer[$i] != '|') {
			$value .= $buffer[$i++];
		}

		if (!CNewValidator::is_int32($value)) {
			return false;
		}

		$pos = $i;
		$rules['le'] = $value;

		return true;
	}

	/**
	 * json
	 *
	 * 'json' => true
	 */
	private function parseJson($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'json', 4) != 0) {
			return false;
		}

		$pos += 4;
		$rules['json'] = true;

		return true;
	}

	/**
	 * int32
	 *
	 * 'int32' => true
	 */
	private function parseInt32($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'int32', 5) != 0) {
			return false;
		}

		$pos += 5;
		$rules['int32'] = true;

		return true;
	}

	/**
	 * in <value1>[,...,<valueN>]
	 *
	 * 'in' => array('<value1>', ..., '<valueN>')
	 */
	private function parseIn($buffer, &$pos, &$rules) {
		$i = $pos;

		if (strncmp(substr($buffer, $i), 'in ', 3) != 0) {
			return false;
		}

		$i += 3;

		while (isset($buffer[$i]) && $buffer[$i] == ' ') {
			$i++;
		}

		$values = [];

		if (!$this->parseValues($buffer, $i, $values)) {
			return false;
		}

		$pos = $i;
		$rules['in'] = $values;

		return true;
	}

	/**
	 * id
	 *
	 * 'id' => true
	 */
	private function parseId($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'id', 2) != 0) {
			return false;
		}

		$pos += 2;
		$rules['id'] = true;

		return true;
	}

	/**
	 * ge <value>
	 *
	 * 'ge' => '<value>'
	 */
	private function parseGE($buffer, &$pos, &$rules) {
		$i = $pos;

		if (0 != strncmp(substr($buffer, $i), 'ge ', 3)) {
			return false;
		}

		$i += 3;
		$value = '';

		while (isset($buffer[$i]) && $buffer[$i] != '|') {
			$value .= $buffer[$i++];
		}

		if (!CNewValidator::is_int32($value)) {
			return false;
		}

		$pos = $i;
		$rules['ge'] = $value;

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

		if (strncmp(substr($buffer, $i), 'db ', 3) != 0) {
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

		$pos = $i;
		$rules['db'] = [
			'table' => $table,
			'field' => $field
		];

		return true;
	}

	/**
	 * array
	 *
	 * 'array' => true
	 */
	private function parseArray($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'array', 5) != 0) {
			return false;
		}

		$pos += 5;
		$rules['array'] = true;

		return true;
	}

	/**
	 * array_id
	 *
	 * 'array_id' => true
	 */
	private function parseArrayId($buffer, &$pos, &$rules) {
		if (strncmp(substr($buffer, $pos), 'array_id', 8) != 0) {
			return false;
		}

		$pos += 8;
		$rules['array_id'] = true;

		return true;
	}

	/**
	 * array_db <table>.<field>
	 *
	 * 'array_db' => array(
	 *     'table' => '<table>',
	 *     'field' => '<field>'
	 * )
	 */
	private function parseArrayDB($buffer, &$pos, &$rules) {
		$i = $pos;

		if (strncmp(substr($buffer, $i), 'array_db ', 9) != 0) {
			return false;
		}

		$i += 9;

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

		$pos = $i;
		$rules['array_db'] = [
			'table' => $table,
			'field' => $field
		];

		return true;
	}

	/**
	 * <field>
	 */
	private function parseField($buffer, &$pos, &$field) {
		$matches = [];
		if (1 != preg_match('/^[A-Za-z0-9_]+/', substr($buffer, $pos), $matches))
			return false;

		$pos += strlen($matches[0]);
		$field = $matches[0];

		return true;
	}

	/**
	 * <value1>[,...,<valueN>]
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
