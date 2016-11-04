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


class CNewValidator {

	private $rules;
	private $input = [];
	private $output = [];
	private $errors = [];
	private $errorsFatal = [];

	/**
	 * Parser for validation rules.
	 *
	 * @var CValidationRule
	 */
	private $validationRuleParser;

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private $error;

	public function __construct(array $input, array $rules) {
		$this->input = $input;
		$this->rules = $rules;
		$this->validationRuleParser = new CValidationRule();

		$this->validate();
	}

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 */
	private function validate() {
		foreach ($this->rules as $field => $rule) {
			$result = $this->validateField($field, $rule);

			if (array_key_exists($field, $this->input)) {
				$this->output[$field] = $this->input[$field];
			}
		}
	}

	private function validateField($field, $rules) {
		if (false === ($rules = $this->validationRuleParser->parse($rules))) {
			$this->addError(true, $this->validationRuleParser->getError());
			return false;
		}

		$fatal = array_key_exists('fatal', $rules);

		foreach ($rules as $rule => $params) {
			switch ($rule) {
				/*
				 * 'fatal' => true
				 */
				case 'fatal':
					// nothing to do
					break;

				/*
				 * 'not_empty' => true
				 */
				case 'not_empty':
					if (array_key_exists($field, $this->input) && $this->input[$field] === '') {
						$this->addError($fatal,
							_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty'))
						);
						return false;
					}
					break;

				case 'json':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !CJs::decodeJson($this->input[$field])) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				/*
				 * 'in' => array(<values)
				 */
				case 'in':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !in_array($this->input[$field], $params)) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				case 'int32':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !$this->is_int32($this->input[$field])) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				case 'id':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !$this->is_id($this->input[$field])) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				/*
				 * 'array_id' => true
				 */
				case 'array_id':
					if (array_key_exists($field, $this->input)) {
						if (!is_array($this->input[$field]) || !$this->is_array_id($this->input[$field])) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				/*
				 * 'array' => true
				 */
				case 'array':
					if (array_key_exists($field, $this->input) && !is_array($this->input[$field])) {
						$this->addError($fatal,
							_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
						);

						return false;
					}
					break;

				/*
				 * 'array_db' => array(
				 *     'table' => <table_name>,
				 *     'field' => <field_name>
				 * )
				 */
				case 'array_db':
					if (array_key_exists($field, $this->input)) {
						if (!is_array($this->input[$field])
								|| !$this->is_array_db($this->input[$field], $params['table'], $params['field'])) {

							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				/*
				 * 'ge' => <value>
				 */
				case 'ge':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !$this->is_int32($this->input[$field])
								|| $this->input[$field] < $params) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
							);

							return false;
						}
					}
					break;

				/*
				 * 'le' => <value>
				 */
				case 'le':
					if (array_key_exists($field, $this->input)) {
						if (!is_string($this->input[$field]) || !$this->is_int32($this->input[$field])
								|| $this->input[$field] > $params) {
							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
							);

							return false;
						}
					}
					break;

				/*
				 * 'db' => array(
				 *     'table' => <table_name>,
				 *     'field' => <field_name>
				 * )
				 */
				case 'db':
					if (array_key_exists($field, $this->input)) {
						if (!$this->is_db($this->input[$field], $params['table'], $params['field'])) {

							$this->addError($fatal,
								_s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field)
								// TODO: stringify($this->input[$field]) ???
							);
							return false;
						}
					}
					break;

				/*
				 * 'required' => true
				 */
				case 'required':
					if (!array_key_exists($field, $this->input)) {
						$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
						return false;
					}
					break;

				/*
				 * 'string' => true
				 */
				case 'string':
					if (array_key_exists($field, $this->input) && !is_string($this->input[$field])) {
						$this->addError($fatal,
							_s('Incorrect value for field "%1$s": %2$s.', $field, _('a character string is expected'))
						);
						return false;
					}
					break;

				/*
				 * 'time' => true
				 */
				case 'time':
					if (array_key_exists($field, $this->input) && !$this->is_time($this->input[$field])) {
						$this->addError($fatal,
							_s('Incorrect value for field "%1$s": %2$s.', $field, _('a time is expected'))
						);
						return false;
					}
					break;

				default:
					// the message can be not translated because it is an internal error
					$this->addError($fatal, 'Invalid validation rule "'.$rule.'".');
					return false;
			}
		}

		return true;
	}

	private function is_id($value) {
		if (1 != preg_match('/^[0-9]+$/', $value)) {
			return false;
		}

		// between 0 and _I64_MAX
		return (bccomp($value, '0') >= 0 && bccomp($value, '9223372036854775807') <= 0);
	}

	public static function is_int32($value) {
		if (1 != preg_match('/^\-?[0-9]+$/', $value)) {
			return false;
		}

		// between INT_MIN and INT_MAX
		return (bccomp($value, '-2147483648') >= 0 && bccomp($value, '2147483647') <= 0);
	}

	public static function is_uint64($value) {
		if (1 != preg_match('/^[0-9]+$/', $value)) {
			return false;
		}

		// between 0 and _UI64_MAX
		return (bccomp($value, '0') >= 0 && bccomp($value, '18446744073709551615') <= 0);
	}

	private function check_db_value($field_schema, $value) {
		switch ($field_schema['type']) {
			case DB::FIELD_TYPE_ID:
				return $this->is_id($value);

			case DB::FIELD_TYPE_INT:
				return $this->is_int32($value);

			case DB::FIELD_TYPE_CHAR:
				return (mb_strlen($value) <= $field_schema['length']);

			case DB::FIELD_TYPE_TEXT:
				// TODO: check length
				return true;

			default:
				return false;
		}
	}

	private function is_array_id(array $values) {
		foreach ($values as $value) {
			if (!is_string($value) || !$this->is_id($value)) {
				return false;
			}
		}

		return true;
	}

	private function is_array_db(array $values, $table, $field) {
		$table_schema = DB::getSchema($table);

		foreach ($values as $value) {
			if (!is_string($value) || !$this->check_db_value($table_schema['fields'][$field], $value)) {
				return false;
			}
		}

		return true;
	}

	private function is_db($value, $table, $field) {
		$table_schema = DB::getSchema($table);

		return (is_string($value) && $this->check_db_value($table_schema['fields'][$field], $value));
	}

	private function isLeapYear($year) {
		return (0 == $year % 4 && (0 != $year % 100 || 0 == $year % 400));
	}

	private function getDaysInMonth($year, $month) {
		if (in_array($month, [4, 6, 9, 11], true)) {
			return 30;
		}

		if ($month == 2) {
			return $this->isLeapYear($year) ? 29 : 28;
		}

		return 31;
	}

	private function is_time($value) {
		// YYYYMMDDhhmmss

		if (!is_string($value) || strlen($value) != 14 || !ctype_digit($value)) {
			return false;
		}

		$Y = (int) substr($value, 0, 4);
		$M = (int) substr($value, 4, 2);
		$D = (int) substr($value, 6, 2);
		$h = (int) substr($value, 8, 2);
		$m = (int) substr($value, 10, 2);
		$s = (int) substr($value, 12, 2);

		return ($Y >= 1990 && $M >= 1 && $M <= 12 && $D >= 1 && $D <= $this->getDaysInMonth($Y, $M)
			&& $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59 && $s >= 0 && $s <= 59);
	}

	/**
	 * Add validation error.
	 *
	 * @return string
	 */
	public function addError($fatal, $error) {
		if ($fatal) {
			$this->errorsFatal[] = $error;
		}
		else {
			$this->errors[] = $error;
		}
	}

	/**
	 * Get valid fields.
	 *
	 * @return array of fields passed validation
	 */
	public function getValidInput() {
		return $this->output;
	}

	/**
	 * Returns array of error messages.
	 *
	 * @return array
	 */
	public function getAllErrors() {
		return array_merge($this->errorsFatal, $this->errors);
	}

	/**
	 * Returns true if validation failed with errors.
	 *
	 * @return bool
	 */
	public function isError() {
		return (bool) $this->errors;
	}

	/**
	 * Returns true if validation failed with fatal errors.
	 *
	 * @return bool
	 */
	public function isErrorFatal() {
		return (bool) $this->errorsFatal;
	}
}
