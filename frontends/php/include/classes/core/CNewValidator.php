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


class CNewValidator {

	private $fields;
	private $rules;
	private $input = array();
	private $output = array();
	private $errors = array();
	private $errorsFatal = array();

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
						$this->addError($fatal, _s('Incorrect value for field "%1$s": cannot be empty.', $field));
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
				 * 'not_empty' => true
				 */
				case 'not_empty':
					if (!array_key_exists($field, $this->input)) {
						$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
						return false;
					}
					break;

				/*
				 * 'required' => true
				 */
				case 'required':
					if (!array_key_exists($field, $this->input)) {
						$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
						$result = false;
					}
					break;

				/*
				 * 'required_if' => array(
				 *     <param1> => true,
				 *     <param2> => array(<values>),
				 *     ...
				 *  )
				 */
				case 'required_if':
					if (array_key_exists($field, $this->input)) {
						break;
					}

					$required = true;

					foreach ($params as $field2 => $values) {
						$required = (array_key_exists($field2, $this->input)
							&& ($values === true || in_array($this->input[$field2], $values)));

						if (!$required) {
							break;
						}
					}

					if ($required && !array_key_exists($field, $this->input)) {
						$this->addError($fatal, _s('Field "%1$s" is mandatory.', $field));
						return false;
					}
//					elseif (!$required && array_key_exists($field, $this->input)) {
//						$this->addError($fatal, _s('Field "%1$s" must be missing.', $field));
//						return false;
//					}
					break;

				default:
					// the message can be not translated because it is an internal error
					$this->addError($fatal, 'Invalid validation rule "'.$rule.'".');
					return false;
			}
//			if (!$this->validateRule($field, $, $fatal)) {
//				return false;
//			}
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

	public static function is_int($value) {
		if (1 != preg_match('/^\-?[0-9]+$/', $value)) {
			return false;
		}

		// between INT_MIN and INT_MAX
		return (bccomp($value, '-2147483648') >= 0 && bccomp($value, '2147483647') <= 0);
	}

	public static function is_array($value) {
		return is_array($value);
	}

	private function check_db_value($field_schema, $value) {
		switch ($field_schema['type']) {
			case DB::FIELD_TYPE_ID:
				return $this->is_id($value);

			case DB::FIELD_TYPE_INT:
				return $this->is_int($value);

			case DB::FIELD_TYPE_CHAR:
				return (mb_strlen($value) <= $field_schema['length']);

			case DB::FIELD_TYPE_TEXT:
				// TODO: check length
				return true;

			default:
				return false;
		}
	}

	private function is_array_id(array $values, $table, $field) {
		$table_schema = DB::getSchema($table);

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
			// required_if:field,value1,value2,...
			case 'required_if':
				if (array_key_exists($field, $this->input) && !array_key_exists($params[0], $this->input)) {
					$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
					$result = false;
				}
//				else if (!array_key_exists($field, $this->input) && (array_key_exists($params[0], $this->input) && $this->input[$params[0]] == $params[1])) {
//					$this->addError($fatal, _s('Field "%1$s" must present only if "%2$s" exists and equal to "%3$s".', $field, $params[0], $params[1]));
//					$result = false;
//				}
				break;
			// required_if_not:field,value1,value2,...
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
					if (!CNewValidator::is_db($this->input[$field], $params[0])) {
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
