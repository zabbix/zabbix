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


class CNewValidator {

	private $fields;
	private $rules;
	private $input = array();
	private $output = array();
	private $errors = array();
	private $errorsFatal = array();

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private $error;

	public function __construct(array $input, array $rules) {
		$this->input = $input;
		$this->rules = $rules;

		$this->validate();
	}

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	private function validate() {
		foreach ($this->rules as $field => $fieldRules) {
			$field_trimmed = trim($field, ' ');
			$result = $this->validateField($field_trimmed, $fieldRules);

			// add to output only existing fields
			if (array_key_exists($field_trimmed, $this->input)) {
				$this->output[$field_trimmed] = $this->input[$field_trimmed];
			}
		}
	}

	private function validateField($field, $rules) {
		$rules = explode('|', $rules);

		$key = array_search('fatal', $rules);
		if ($key !== false) {
			$fatal = true;
			unset($rules[$key]);
		}
		else {
			$fatal = false;
		}

		$result = true;
		foreach ($rules as $rule) {
			$result = $result && $this->validateRule($field, trim($rule, ' '), $fatal);
		}

		return $result;
	}

	public static function is_int($value) {
		return zbx_ctype_digit($value) &&
			$value >= 0 && bccomp($value, '10000000000000000000')<0;
	}

	public static function is_array($value) {
		return is_array($value);
	}

	public static function is_db_type($value, $dbfield) {
		list ($table_schema, $field_schema) = explode('.', $dbfield);
		$table_schema = DB::getSchema($table_schema);
		$field_type = $table_schema['fields'][$field_schema]['type'];

		switch ($field_type) {
			case DB::FIELD_TYPE_ID:
			case DB::FIELD_TYPE_INT:
				$result = CNewValidator::is_int($value);
				break;
			case DB::FIELD_TYPE_CHAR:
				$result = true;
				break;
			case DB::FIELD_TYPE_TEXT:
				$result = true;
				break;
			default:
				$result = false;
				break;
		}
		return $result;
	}

// $fatal: false - non fatal, true - any error is considered fatal
	private function validateRule($field, $rule, $fatal) {

		$field_parts = explode('/', $field);
		$field_major = $field_parts[0];
		$field_minor = isset($field_parts[1]) ? $field_parts[1] : null;

		$parts = explode(':', $rule);

		$command = $parts[0];
		$params = isset($parts[1]) ? explode(',', $parts[1]) : array();

		// simple field name
		$result = true;
		if ($field_minor === null) {
			switch ($command) {
			case 'required':
				if (!array_key_exists($field, $this->input)) {
					$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
					$result = false;
				}
				break;
			// required_if:field,value
			case 'required_if':
				if (array_key_exists($field, $this->input) && !array_key_exists($params[0], $this->input)) {
					$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					$result = false;
				}
				else if (!array_key_exists($field, $this->input) && (array_key_exists($params[0], $this->input) && $this->input[$params[0]] == $params[1])) {
					$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					$result = false;
				}
				break;
			// required_if_not:field,value
			case 'required_if_not':
				if (array_key_exists($field, $this->input) && array_key_exists($params[0], $this->input) && $this->input[$params[0]] == $params[1]) {
					$this->addError($fatal, _s('Field "%1$s" must present if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					$result = false;
				}
				break;
			case 'array':
				if (array_key_exists($field, $this->input)) {
					if(!CNewValidator::is_array($this->input[$field])) {
						$this->addError($fatal, _s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field));
						$result = false;
					}
				}
				break;
			case 'not_empty':
				if (array_key_exists($field, $this->input)) {
					if($this->input[$field] === '') {
						$this->addError($fatal, _s('Incorrect value for field "%1$s": cannot be empty.', $field));
						$result = false;
					}
				}
				break;
			// in_int:value_int1,value_int2,value_int3,...
			case 'in_int':
				if (array_key_exists($field, $this->input)) {
					if (!in_array($this->input[$field], $params, true)) {
						$this->addError($fatal, _s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field));
						$result = false;
					}
				}
				break;
			case 'ip':
				if (array_key_exists($field, $this->input)) {
					if (!validate_ip($this->input[$field], $arr)) {
						$this->addError($fatal, _s('Field "%1$s" is not IP.', $field));
						$result = false;
					}
				}
				break;
			// in_str:value1,value2,value3,...
			case 'in_str':
				if (array_key_exists($field, $this->input)) {
					if (!in_array($this->input[$field], $params, true)) {
						$this->addError($fatal, _s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field));
						$result = false;
					}
				}
				break;
			// db:table.field
			case 'db':
				if (array_key_exists($field, $this->input)) {
					if (!CNewValidator::is_db_type($this->input[$field], $params[0])) {
						$this->addError($fatal, _s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field));
						$result = false;
					}
				}
				break;
			// db_array:table.field
			case 'array_db':
				if (array_key_exists($field, $this->input)) {
					foreach ($this->input[$field] as $value) {
						if (!CNewValidator::is_db_type($value, $params[0])) {
							$this->addError($fatal, _s('Incorrect value "%1$s" for "%2$s" field.', $this->input[$field], $field));
							$result = false;
						}
					}
				}
				break;
			default:
				break;
			}
		}
		// compound field name like field/subfield
		else {
			switch ($command) {
			case 'required':
/*				if (!array_key_exists($field_major, $this->input)) {
					$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
				}
				else if (!CNewValidator::is_array($this->input[$field_major])) {
					$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
				}
				else {
					foreach ($this->input[$field_major] as $array_element) {
						if (!array_key_exists($field_major, $array_element)) {
							$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
						}
					}
				}*/
				break;
			// in_int:value_int1,value_int2,value_int3,...
			case 'in_int':
			// required_if:field,value
			case 'required_if':
			case 'ip':
			case 'db':
/*				if (!array_key_exists($field_major, $this->input)) {
					$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
				}
				else if (!CNewValidator::is_array($this->input[$field_major])) {
					$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
				} else {
					if (array_key_exists($field, $this->input) && !array_key_exists($params[0], $this->input)) {
						$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					}
					if (!array_key_exists($field, $this->input) && (array_key_exists($params[0], $this->input) && $this->input[$params[0]] == $params[1])) {
						$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					}
				}*/
				break;
			default:
				break;
			}
		}

		return $result;
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
	 * Get validation errors.
	 *
	 * @return array of error messages
	 */
	public function getAllErrors() {
		return array_merge($this->errorsFatal, $this->errors);
	}

	/**
	 * Get result.
	 *
	 * @param $error
	 */
	public function isError() {
		return (count($this->errors) != 0);
	}

	/**
	 * Get result.
	 *
	 * @param $error
	 */
	public function isErrorFatal() {
		return (count($this->errorsFatal) != 0);
	}
}
