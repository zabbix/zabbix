<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Form input validator.
 */
class CFormValidator {

	private $rules;
	private $errors = [];
	private $field_values = [];
	private $has_fatal = false;
	private $uniq_checks = [];
	private $api_uniq_checks = [];

	private $when_resolved_data = [];
	private $existing_rule_paths = [];

	const SUCCESS = 0;
	const ERROR = 1;
	const ERROR_FATAL = 2;

	const ERROR_LEVEL_PRIMARY = 0;
	const ERROR_LEVEL_DELAYED = 1;
	const ERROR_LEVEL_UNIQ = 2;
	const ERROR_LEVEL_API = 3;
	const ERROR_LEVEL_UNKNOWN = 4;

	public function __construct(array $rules) {
		$this->rules = $this->normalizeRules($rules);
	}

	public function getRules(): array {
		return $this->rules;
	}

	/**
	 * Normalize high level validation rules.
	 *
	 * Supported rules:
	 *   Data types:
	 *     'boolean', 'string', 'integer', 'float', 'id', 'objects', 'object', 'array', 'db <table>.<field>',
	 *     'file'
	 *   Constraints:
	 *     'not_empty', 'required', 'length', 'api_uniq'
	 *   Value comparisons:
	 *     'min', 'max', 'in'
	 *   Subarray checks:
	 *     'fields', 'field'
	 *   Conditions:
	 *     'when'
	 *   Error messages:
	 *     'messages'
	 *
	 * @param array  $rules
	 *
	 * @return array
	 */
	protected function normalizeRules(array $rules, string $rule_path = ''): array {
		$this->existing_rule_paths[] = $rule_path;
		$result = [];

		foreach ($rules as $key => $value) {
			if (is_int($key)) {
				if (!is_string($value)) {
					throw new Exception('[RULES ERROR] For numeric keys, rule value should be a string: (Path: '.$rule_path.', Key: '.$key.')');
				}

				if (in_array($value, ['required', 'not_empty', 'allow_macro'], true)) {
					if (array_key_exists($value, $result)) {
						throw new Exception('[RULES ERROR] Option "'.$value.'" is specified multiple times (Path: '.$rule_path.')');
					}

					$result[$value] = true;
				}
				elseif (in_array($value, ['id', 'integer', 'float', 'string', 'object', 'objects', 'array', 'file'],
						true)) {
					if (array_key_exists('type', $result)) {
						// "type" is specified multiple times.
						throw new Exception('[RULES ERROR] Rule "type" is specified multiple times (Path: '.$rule_path.')');
					}

					$result['type'] = $value;

					if ($value === 'file') {
						$result['file-type'] = 'file';
					}
				}
				elseif ($value === 'boolean') {
					if (array_key_exists('type', $result)) {
						throw new Exception('[RULES ERROR] Rule "type" is specified multiple times (Path: '.$rule_path.')');
					}

					$result['type'] = 'integer';
					$result['in'] = [0, 1];
				}
				elseif (strncmp($value, 'db ', 3) === 0) {
					if (array_key_exists('type', $result)) {
						throw new Exception('[RULES ERROR] Rule "type" is specified multiple times (Path: '.$rule_path.')');
					}

					[$db_table, $db_field] = explode('.', substr($value, 3));
					$db_field_schema = DB::getSchema($db_table)['fields'][$db_field];

					switch ($db_field_schema['type']) {
						case DB::FIELD_TYPE_ID:
							$result['type'] = 'id';
							break;

						case DB::FIELD_TYPE_INT:
							$result['type'] = 'integer';
							break;

						case DB::FIELD_TYPE_CHAR:
						case DB::FIELD_TYPE_TEXT:
							if (array_key_exists('length', $result)) {
								throw new Exception('[RULES ERROR] Rule "length" is specified multiple times (Path: '.$rule_path.')');
							}

							$result['type'] = 'string';
							$result['length'] = DB::getFieldLengthBySchema($db_field_schema);
							break;

						default:
							throw new Exception('[RULES ERROR] Unknown field type in db schema (Path: '.$rule_path.')');
					}
				}
				elseif (strncmp($value, 'setting ', 8) === 0) {
					if (array_key_exists('type', $result)) {
						throw new Exception('[RULES ERROR] Rule "type" is specified multiple times (Path: '.$rule_path.')');
					}

					$db_field = substr($value, 8);

					if (CSettingsSchema::getDbType($db_field) & DB::FIELD_TYPE_CHAR) {
						if (array_key_exists('length', $result)) {
							throw new Exception('[RULES ERROR] Rule "length" is specified multiple times (Path: '.$rule_path.')');
						}

						$result['type'] = 'string';
						$result['length'] = CSettingsSchema::getFieldLength($db_field);
					}
					elseif (CSettingsSchema::getDbType($db_field) & (DB::FIELD_TYPE_ID)) {
						$result['type'] = 'id';
					}
					elseif (CSettingsSchema::getDbType($db_field) & (DB::FIELD_TYPE_INT)) {
						$result['type'] = 'integer';
					}
					else {
						throw new Exception('[RULES ERROR] Unknown field type in db schema (Path: '.$rule_path.')');
					}
				}
				elseif (strncmp($value, 'in ', 3) === 0) {
					if (array_key_exists('in', $result) || array_key_exists('not_in', $result)) {
						throw new Exception('[RULES ERROR] Rule "in" or "not_in" is specified multiple times (Path: '.$rule_path.')');
					}

					$result['in'] = self::parseIn(substr($value, 3));
				}
				elseif (strncmp($value, 'not_in ', 7) === 0) {
					if (array_key_exists('in', $result) || array_key_exists('not_in', $result)) {
						throw new Exception('[RULES ERROR] Rule "in" or "not_in" is specified multiple times (Path: '.$rule_path.')');
					}

					$result['not_in'] = self::parseIn(substr($value, 7));
				}
				else {
					throw new Exception('[RULES ERROR] Unknown rule "'.$value.'" (Path: '.$rule_path.')');
				}
			}
			else {
				switch ($key) {
					case 'not_in':
					case 'in':
						if (array_key_exists('in', $result) || array_key_exists('not_in', $result)) {
							throw new Exception('[RULES ERROR] Rule "in" or "not_in" is specified multiple times (Path: '.$rule_path.')');
						}

						if (!is_array($value) || !$value) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" should contain non-empty array (Path: '.$rule_path.')');
						}

						$result[$key] = $value;
						break;

					case 'fields':
					case 'field':
					case 'messages':
						if (!is_array($value) || !$value) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" should contain non-empty array (Path: '.$rule_path.')');
						}

						if ($key === 'fields') {
							$value = $this->normalizeRulesFields($value, $rule_path);
						}
						elseif ($key === 'field') {
							$value = $this->normalizeRules($value, $rule_path);
						}

						$result[$key] = $value;
						break;

					case 'when':
						$result[$key] = $this->normalizeWhenRule($value, $rule_path);
						break;

					case 'uniq':
						$result[$key] = self::normalizeUniqRule($value);
						break;

					case 'min':
					case 'max':
						if (!is_int($value) && !is_float($value)) {
							// Value should be a number.
							throw new Exception('[RULES ERROR] Rule "'.$key.'" should contain a number (Path: '.$rule_path.')');
						}

						$result[$key] = $value;
						break;

					case 'length':
						if (array_key_exists($key, $result)) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" is specified multiple times (Path: '.$rule_path.')');
						}

						if (!is_int($value)) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" should contain an integer (Path: '.$rule_path.')');
						}

						$result[$key] = $value;
						break;

					case 'use':
						$result[$key] = $value;
						break;

					case 'allow_macro':
						$result[$key] = true;
						break;

					case 'api_uniq':
						$result[$key] = self::normalizeApiUniqRule($value, $rule_path);
						break;

					case 'regex':
						if (array_key_exists($key, $result)) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" is specified multiple times (Path: '.$rule_path.')');
						}

						if (preg_match($value, '') === false) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" contains invalid regex (Path: '.$rule_path.')');
						}

						$result[$key] = $value;
						break;

					case 'max-size':
						$result[$key] = (int) $value;
						$result['max-size-human-readable'] = convertUnits(['value' => $result[$key], 'units' => 'B']);

						break;
					case 'file-type':
						if (!in_array($value, ['file', 'image'])) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" contains invalid value (Path: ' .$rule_path.')');
						}

						$result[$key] = $value;

						break;

					default:
						throw new Exception('[RULES ERROR] Unknown rule "'.$key.'" (Path: '.$rule_path.')');
				}
			}
		}

		// Some rule checking.
		if (!array_key_exists('type', $result)) {
			throw new Exception('[RULES ERROR] Rule "type" is mandatory (Path: '.$rule_path.')');
		}

		if (array_key_exists('not_empty', $result)) {
			if (!in_array($result['type'], ['string', 'objects', 'array', 'file'])) {
				throw new Exception('[RULES ERROR] Rule "not_empty" is not compatible with type "'.$result['type'].'" (Path: '.$rule_path.')');
			}
		}

		if (array_key_exists('in', $result) || array_key_exists('not_in', $result)) {
			if (!in_array($result['type'], ['integer', 'float', 'string'])) {
				throw new Exception('[RULES ERROR] Rule "in" and "not_in" is not compatible with type "'.$result['type'].'" (Path: '.$rule_path.')');
			}

			$options = array_key_exists('in', $result) ? $result['in'] : $result['not_in'];

			if (self::validateInOptions($options, $result['type']) === false) {
				throw new Exception('[RULES ERROR] Invalid value for rule "in" or "not_in" (Path: '.$rule_path.')');
			}
		}

		if (array_key_exists('fields', $result) && !in_array($result['type'], ['objects', 'object'])) {
			throw new Exception('[RULES ERROR] Rule "fields" is not compatible with type "'.$result['type'].'" (Path: '.$rule_path.')');
		}

		if (in_array($result['type'], ['objects', 'object'])
				&& !array_key_exists('fields', $result) && !array_key_exists('when', $result)) {
			throw new Exception('[RULES ERROR] For object/objects in non-conditional rule row "fields" rule must be present (Path: '.$rule_path.')');
		}

		if (array_key_exists('field', $result) && $result['type'] !== 'array') {
			throw new Exception('[RULES ERROR] Rule "field" is supported only by type "array" (Path: '.$rule_path.')');
		}

		if (array_key_exists('api_uniq', $result) && $result['type'] !== 'object') {
			throw new Exception('[RULES ERROR] Rule "api_uniq" is supported only by type "object" (Path: '.$rule_path.')');
		}

		if ((array_key_exists('min', $result) || array_key_exists('max', $result))
				&& !in_array($result['type'], ['integer', 'float'])) {
			throw new Exception('[RULES ERROR] Rule "min" or "max" is not compatible with type "'.$result['type'].'" (Path: '.$rule_path.')');
		}

		if (array_key_exists('length', $result) && $result['type'] !== 'string') {
			throw new Exception('[RULES ERROR] Rule "length" is supported only by type "string" (Path: '.$rule_path.')');
		}

		if (array_key_exists('allow_macro', $result) && $result['type'] !== 'string') {
			throw new Exception('[RULES ERROR] Rule "length" is supported only by type "string" (Path: '.$rule_path.')');
		}

		if (array_key_exists('when', $result)) {
			foreach ($result['when'] as $when) {
				$when_function = array_keys($when)[1];

				if (($when_function === 'in' || $when_function === 'not_in')
						&& self::validateInOptions($when[$when_function]) === false) {
					throw new Exception('[RULES ERROR] Invalid value for rule "in" or "not_in" in "when" condition (Path: '.$rule_path.')');
				}
			}
		}

		if (array_key_exists('messages', $result)) {
			foreach (array_keys($result['messages']) as $key) {
				if (!array_key_exists($key, $result)) {
					throw new Exception('[RULES ERROR] Message is defined for non-existing rule "'.$key.'" (Path: '.$rule_path.')');
				}
			}
		}

		/*
		 * Boolean rules are converted to 'integers' at this point but it may have some special conditions.
		 *
		 * If boolean is defined with 'required', the only allowed value remains '1' ('selected/checked/marked' if we
		 * think about it as checkbox). It also comes with default 'custom' error message.
		 */
		if (in_array('boolean', $rules, true) && array_key_exists('required', $result)) {
			$result['in'] = [1];

			if (!array_key_exists('messages', $result) || !array_key_exists('in', $result['messages'])) {
				$result['messages']['in'] = _('Must be selected.');
			}
		}

		return $result;
	}

	/**
	 * Normalize 'fields' and 'field' contents.
	 *
	 * @param array  $rules
	 *
	 * @return array
	 */
	private function normalizeRulesFields(array $rules, string $rule_path): array {
		$result = [];

		foreach ($rules as $field => $field_rules) {
			$result[$field] = [];

			if (!is_array($field_rules)) {
				throw new Exception('[RULES ERROR] Field "'.$field.'" should have an array of rule rows (Path: '.$rule_path.')');
			}

			if ($field_rules
					&& (!array_key_exists(0, $field_rules) || !is_array($field_rules[0])
						|| array_key_first($field_rules) != 0)
					) {
				$field_rules = [$field_rules];
			}

			foreach ($field_rules as $rule_row) {
				$result[$field][] = $this->normalizeRules($rule_row, $rule_path.'/'.$field);
			}
		}

		return $result;
	}

	/**
	 * Normalize 'when' rule.
	 *
	 * @param array  $when_rule
	 *
	 * @return array
	 */
	private function normalizeWhenRule(array $when_rules, string $rule_path): array {
		if (!is_array($when_rules)) {
			throw new Exception('[RULES ERROR] When condition should be an array (Path: '.$rule_path.')');
		}

		if (!is_array($when_rules[0])) {
			$when_rules = [$when_rules];
		}

		foreach ($when_rules as &$when_rule) {
			if (!is_array($when_rule) || count($when_rule) !== 2) {
				throw new Exception('[RULES ERROR] When condition should be an array with at least two elements (Path: '.$rule_path.')');
			}

			if (!is_int(array_keys($when_rule)[0]) || !is_string($when_rule[0]) || $when_rule[0] === '') {
				throw new Exception('[RULES ERROR] Missing or invalid comparison field. (Path: '.$rule_path.')');
			}

			$when_field_path = self::getWhenFieldAbsolutePath($when_rule[0], $rule_path);

			if (!in_array($when_field_path, $this->existing_rule_paths)) {
				throw new Exception('[RULES ERROR] Only fields defined prior to this can be used for "when" checks (Path: '.$rule_path.')');
			}

			$result = [$when_rule[0]];

			$key = array_keys($when_rule)[1];
			$value = $when_rule[$key];

			if (is_bool($value)) {
				$result['in'] = $value ? [1] : [0];
			}
			elseif (is_int($key)) {
				if (!is_string($value)) {
					throw new Exception('[RULES ERROR] For numeric keys, when rule value should be a string: (Path: '.$rule_path.', Key: '.$key.')');
				}

				if (in_array($value, ['empty', 'not_empty', 'exist', 'not_exist'], true)) {
					$result[$value] = true;
				}
				elseif (in_array($value, ['id', 'integer', 'float', 'string', 'object', 'objects', 'array'], true)) {
					$result['type'] = $value;
				}
				elseif (strncmp($value, 'in ', 3) === 0) {
					$result['in'] = self::parseIn(substr($value, 3));
				}
				elseif (strncmp($value, 'not_in ', 7) === 0) {
					$result['not_in'] = self::parseIn(substr($value, 7));
				}
				else {
					throw new Exception('[RULES ERROR] Unknown when rule "'.$value.'" (Path: '.$rule_path.')');
				}
			}
			else {
				switch ($key) {
					case 'regex':
						if (preg_match($value, '') === false) {
							throw new Exception('[RULES ERROR] Rule "'.$key.'" contains invalid regex (Path: '.$rule_path.')');
						}
						$result[$key] = $value;
						break;

					case 'not_in':
					case 'in':
						$result[$key] = $value;
						break;

					default:
						throw new Exception('[RULES ERROR] Unknown when rule "'.$key.'" (Path: '.$rule_path.')');
				}
			}

			$when_rule = $result;
		}
		unset($when_rule);

		return $when_rules;
	}

	/**
	 * Normalize 'uniq' rule.
	 *
	 * @param array  $uniq_rule
	 *
	 * @return array
	 */
	private static function normalizeUniqRule(array $uniq_rule): array {
		$result = [];

		if (count(array_filter($uniq_rule, 'is_string')) == count($uniq_rule)) {
			$uniq_rule = [$uniq_rule];
		}

		foreach ($uniq_rule as $rule) {
			$rule = is_string($rule) ? [$rule] : $rule;

			if ($rule) {
				$result[] = $rule;
			}
		}

		return $result;
	}

	private static function normalizeApiUniqRule(array $value, string $rule_path): array {
		if (!is_array($value) || !array_key_exists(0, $value)) {
			throw new Exception('[RULES ERROR] Rule "api_uniq" should contain non-empty array (Path: '.$rule_path.')');
		}

		if (!is_array($value[0])) {
			$value = [$value];
		}

		foreach ($value as &$api_uniq_check) {
			if (count(explode('.', $api_uniq_check[0])) != 2) {
				throw new Exception(
					'[RULES ERROR] Rule "api_uniq" should contain a valid API call (Path: '.$rule_path.', API call:'.
					$api_uniq_check[0].')'
				);
			}
			$api_uniq_check += [1 => [], 2 => null, 3 => null, 4 => []];
			$api_uniq_check[1] = ['filter' => $api_uniq_check[1]];
			$api_uniq_check[1] += $api_uniq_check[4];
			unset($api_uniq_check[4]);
		}
		unset($api_uniq_check);

		return $value;
	}

	/**
	 * Parse 'in' rule, that was passed as string.
	 *
	 * @param string  $rule
	 *
	 * @return array
	 */
	private static function parseIn(string $rule): array {
		$result = [];
		$values = explode(',', $rule);

		if (!$values) {
			throw new Exception('[RULES ERROR] Rule "in" or "not_in" should contain non-empty value');
		}

		foreach ($values as $val) {
			if (strpos($val, ':') !== false) {
				$val = explode(':', $val);
				$val = array_map(fn ($v) => $v === '' ? null : $v, $val);
				$val += [0 => null, 1 => null];
			}

			$result[] = $val;
		}

		return $result;
	}

	/**
	 * Validate "in" and "not_in" parameters, based on field's type.
	 *
	 * @param array  $options      Options to validate.
	 * @param mixed  $data_type    Field's data type.
	 *
	 * @return bool
	 */
	private static function validateInOptions(array $options, ?string $data_type = null): bool {
		if ($data_type === 'integer' || $data_type === 'float') {
			foreach ($options as $part) {
				if (!is_numeric($part) && !is_array($part)) {
					return false;
				}
				elseif (is_array($part)
						&& (count($part) != 2 || $part[0] === $part[1]
							|| array_filter($part, fn ($val) => !is_numeric($val) && $val !== null))
						) {
					return false;
				}
			}
		}
		elseif ($data_type === 'string') {
			$valid_options = array_filter($options, fn ($option) => is_numeric($option) || is_string($option));

			return count($options) == count($valid_options);
		}
		else {
			foreach ($options as $part) {
				if (!is_numeric($part) && !is_string($part) && !is_array($part)) {
					return false;
				}
				elseif (is_array($part)
						&& (count($part) != 2 || $part[0] === $part[1]
							|| array_filter($part, fn ($val) => !is_numeric($val) && $val !== null))
						) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $data  Form input data.
	 * @param ?array  $files From files
	 *
	 * @return int
	 */
	public function validate(&$data, ?array &$files = null): int {
		if ($files === null) {
			$files = [];
		}

		$this->errors = [];

		$this->has_fatal = false;
		$this->uniq_checks = [];
		$this->api_uniq_checks = [];

		$this->field_values = $this->resolveWhenFields($this->rules, $data);

		$path = '';
		if ($this->validateObject($this->rules, $data, $error, $path, $files) === false) {
			$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);
		}

		foreach ($this->api_uniq_checks as $check) {
			$path = $check['path'];

			if (!$this->validateApiUniq($check['rules'], $path, $error)) {
				$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_API);
			}
		}

		foreach ($this->uniq_checks as $check) {
			$path = $check['path'];

			if (!self::validateDistinctness($check['rules'], $check['data'], $path, $error)) {
				$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_UNIQ);
			}
		}

		if ($this->errors) {
			$data = [];
		}

		if ($this->has_fatal) {
			return self::ERROR_FATAL;
		}
		else {
			return $this->errors ? self::ERROR : self::SUCCESS;
		}
	}

	/**
	 * Add error message to the stack.
	 *
	 * @param array   $rules                Validation rules.
	 * @param array   $rules['messages']    (optional) Array where check name is used as key and value as error message.
	 * @param string  $check_name           Custom check error message to find in $rules['messages'] array.
	 * @param string  $default              Default error message.
	 *
	 * @return string
	 */
	private static function getMessage(array $rules, string $check_name, string $default): string {
		return array_key_exists('messages', $rules) && array_key_exists($check_name, $rules['messages'])
			? $rules['messages'][$check_name]
			: $default;
	}

	/**
	 * Base field validation method.
	 *
	 * @param array  $rule    Validation rules.
	 * @param array  $data    Data to validate.
	 * @param string $field   Field to validate.
	 * @param string $path    Path of field.
	 * @param array $files    Files to validate
	 */
	private function validateField(array $rules, &$data, string $field, string $path, ?array &$files = []): void {
		if (array_key_exists('when', $rules)) {
			foreach ($rules['when'] as $when) {
				if ($this->testWhenCondition($when, $path) === false) {
					return;
				}
			}
		}

		// Single value ID may be passed as null when value is not specified.
		if ($rules['type'] !== 'file' && array_key_exists($field, $data) && $data[$field] === null) {
			unset($data[$field]);
		}

		$field_exists = $rules['type'] === 'file'
			? array_key_exists($field, $files)
			: array_key_exists($field, $data);

		if (!$field_exists) {
			if (array_key_exists('required', $rules)) {
				$this->addError(self::ERROR, $path,
					self::getMessage($rules, 'required', _('Required field is missing.')), self::ERROR_LEVEL_PRIMARY
				);
			}

			return;
		}

		switch ($rules['type']) {
			case 'id':
				if (!self::validateId($rules, $data[$field], $error)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'integer':
				if (!self::validateInt32($rules, $data[$field], $error)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'float':
				if (!self::validateFloat($rules, $data[$field], $error)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'string':
				if (!self::validateStringUtf8($rules, $data[$field], $error)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}

				if (!self::validateUse($rules, $data[$field], $error)) {
					$error = self::getMessage($rules, 'use', $error);

					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_DELAYED);

					return;
				}
				break;

			case 'array':
				if (!$this->validateArray($rules, $data[$field], $error, $path)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'object':
				if (!$this->validateObject($rules, $data[$field], $error, $path)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'objects':
				if (!$this->validateObjects($rules, $data[$field], $error, $path)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;

			case 'file':
				if (!$this->validateFile($rules, $files[$field], $error)) {
					$this->addError(self::ERROR, $path, $error, self::ERROR_LEVEL_PRIMARY);

					return;
				}
				break;
		}
	}

	/**
	 * Add validation error.
	 *
	 * @param int    $type       Type of error.
	 * @param string $path       Path of the erroneous dorm field.
	 * @param string $message    Error message.
	 * @param int    $level      Error level (optional).
	 */
	public function addError(int $type, string $path, string $message, $level = self::ERROR_LEVEL_UNKNOWN): void {
		if ($type == self::ERROR_FATAL) {
			$this->has_fatal = true;
		}

		if (!array_key_exists($path, $this->errors)) {
			$this->errors[$path] = [];
		}

		$same_error = array_filter($this->errors[$path],
			fn ($error) => $error['message'] === $message && $error['level'] == $level
		);

		if (!$same_error) {
			$this->errors[$path][] = [
				'message' => $message,
				'level' => $level
			];
		}
	}

	/**
	 * Returns array of error messages.
	 *
	 * @return array
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Function to add each field's most severe error to global errors stack.
	 */
	public function addErrorsToGlobal(): void {
		foreach ($this->errors as $field => $errors) {
			$levels = array_column($errors, 'level');
			$error = $errors[array_search(min($levels), $levels)];

			error(_s('%1$s: %2$s', $field, $error['message']));
		}
	}

	/**
	 * Identifier validator.
	 *
	 * @param array   $rules
	 * @param array   $rules['messages']  (optional) Error messages to use when some check fails.
	 * @param mixed   $value
	 * @param string  $error
	 *
	 * @return bool
	 */
	private static function validateId(array $rules, &$value, ?string &$error = null): bool {
		if (!self::isId($value)) {
			$error = self::getMessage($rules, 'type', _('This value is not a valid identifier.'));

			return false;
		}

		$value = (string) $value;

		if ($value[0] === '0') {
			$value = ltrim($value, '0');

			if ($value === '') {
				$value = '0';
			}
		}

		return true;
	}

	/**
	 * Integers validator.
	 *
	 * @param array  $rules
	 * @param array  $rules['in']         (optional) allowed ranges or list of allowed values.
	 * @param int    $rules['min']        (optional) minimal allowed value length.
	 * @param int    $rules['max']        (optional) maximum allowed value length.
	 * @param array  $rules['messages']   (optional) Error messages to use when some check fails.
	 * @param mixed  $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateInt32(array $rules, &$value, ?string &$error = null): bool {
		if (!self::isInt32($value)) {
			$error = self::getMessage($rules, 'type', _('This value is not a valid integer.'));

			return false;
		}

		if (!self::checkNumericIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'in', $error);

			return false;
		}

		if (!self::checkNumericNotIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'not_in', $error);

			return false;
		}

		if (array_key_exists('min', $rules) && bccomp($value, $rules['min']) == -1) {
			$error = self::getMessage($rules, 'min', _s('This value must be no less than "%1$s".', $rules['min']));

			return false;
		}

		if (array_key_exists('max', $rules) && bccomp($value, $rules['max']) == 1) {
			$error = self::getMessage($rules, 'max', _s('This value must be no greater than "%1$s".', $rules['max']));

			return false;
		}

		if (is_string($value)) {
			$value = (int) $value;
		}

		return true;
	}

	/**
	 * Floating point number validator.
	 *
	 * @param array  $rules
	 * @param array  $rules['in']         (optional) allowed ranges or list of allowed values.
	 * @param int    $rules['min']        (optional) minimal allowed value length.
	 * @param int    $rules['max']        (optional) maximum allowed value length.
	 * @param array  $rules['messages']   (optional) Error messages to use when some check fails.
	 * @param mixed  $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFloat($rules, &$value, ?string &$error = null): bool {
		if (!self::is_float($value)) {
			$error = self::getMessage($rules, 'type', _('This value is not a valid floating-point value.'));

			return false;
		}

		if (!self::checkNumericIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'in', $error);

			return false;
		}

		if (!self::checkNumericNotIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'not_in', $error);

			return false;
		}

		if (array_key_exists('min', $rules) && bccomp($value, $rules['min']) == -1) {
			$error = self::getMessage($rules, 'min', _s('This value must be no less than "%1$s".', $rules['min']));

			return false;
		}

		if (array_key_exists('max', $rules) && bccomp($value, $rules['max']) == 1) {
			$error = self::getMessage($rules, 'max', _s('This value must be no greater than "%1$s".', $rules['max']));

			return false;
		}

		return true;
	}

	/**
	 * String validator.
	 *
	 * @param array  $rules
	 * @param bool   $rules['not_empty']    (optional) pass if value must be filled.
	 * @param bool   $rules['allow_macro']  (optional) ignore other checks if value looks like user macro.
	 * @param int    $rules['length']       (optional) maximum allowed value length.
	 * @param array  $rules['messages']     (optional) error messages to use when some check fails.
	 * @param mixed  $value
	 * @param string $error
	 *
	 * @return bool
	 */
	public static function validateStringUtf8(array $rules, &$value, ?string &$error = null): bool {
		$value_check = is_numeric($value) ? (string) $value : $value;

		if (!is_string($value_check) || mb_check_encoding($value_check, 'UTF-8') !== true) {
			$error = self::getMessage($rules, 'type', _('This value is not a valid string.'));

			return false;
		}

		if (array_key_exists('not_empty', $rules) && $value_check === '') {
			$error = self::getMessage($rules, 'not_empty', _('This field cannot be empty.'));

			return false;
		}

		if (array_key_exists('allow_macro', $rules) && $value_check !== ''
				&& (new CUserMacroParser)->parse($value_check) == CParser::PARSE_SUCCESS) {
			return true;
		}

		if (array_key_exists('length', $rules) && mb_strlen($value_check) > $rules['length']) {
			$error = self::getMessage($rules, 'length', _('This value is too long.'));

			return false;
		}

		if (array_key_exists('regex', $rules) && !preg_match($rules['regex'], $value)) {
			$error = self::getMessage($rules, 'regex', _('This value does not match pattern.'));

			return false;
		}

		if (!self::checkStringIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'in', $error);

			return false;
		}

		if (!self::checkStringNotIn($rules, $value, $error)) {
			$error = self::getMessage($rules, 'not_in', $error);

			return false;
		}

		$value = $value_check;

		return true;
	}

	public static function validateUse(array $rules, string $value, &$error): bool {
		if (!array_key_exists('use', $rules)) {
			return true;
		}

		// Don't use parsers and validators just to check if string is not empty. That is task for 'not_empty' rule.
		if ($value === '') {
			return true;
		}

		$error = '';

		[$class_name, $class_options, $more_options] = $rules['use'] + [1 => null, 2 => []];

		switch ($class_name) {
			case 'CRegexValidator':
				$class_options = $class_options ?: [
					'messageInvalid' => _('invalid regular expression'),
					'messageRegex' => _('invalid regular expression')
				];
				break;
		}

		$instance = $class_options
			? new $class_name($class_options)
			: new $class_name();

		if ($instance instanceof CParser) {
			if (in_array($instance->parse($value), [CParser::PARSE_FAIL, CParser::PARSE_SUCCESS_CONT, false], true)) {
				$error = $instance->getError();

				// Some parsers may return empty string as error.
				if ($error === '') {
					$error = _('Invalid string.');
				}
			}
		}
		elseif ($instance instanceof CValidator) {
			if ($instance->validate($value) === false) {
				$error = $instance->getError() ?? _('Invalid string.');
			}
		}
		else {
			throw new Exception('Method not found', -32601);
		}

		/*
		 * Validator/parser error message is not used as a substring here. To use it as final error message, add full
		 * stop at the end and make first letter a capital.
		 */
		if ($error !== '' && substr($error, -1) !== '.') {
			$error = $error.'.';
		}
		$error = ucfirst($error);

		// Parser specific checks not supported by parser itself.
		if ($error === '') {
			if ($instance instanceof CAbsoluteTimeParser) {
				if (array_key_exists('min', $more_options)
						&& $instance->getDateTime(true)->getTimestamp() < $more_options['min']) {
					$error = _s('Value must be greater than %1$s.', date(ZBX_FULL_DATE_TIME, $more_options['min']));
				}

				if (array_key_exists('max', $more_options)
						&& $instance->getDateTime(true)->getTimestamp() > $more_options['max']) {
					$error = _s('Value must be smaller than %1$s.', date(ZBX_FULL_DATE_TIME, $more_options['max']));
				}
			}
			elseif ($instance instanceof CSimpleIntervalParser) {
				if (array_key_exists('min', $more_options) && timeUnitToSeconds($value, true) < $more_options['min']) {
					$error = _s('Value must be greater than %1$s.', $more_options['min']);
				}

				if (array_key_exists('max', $more_options) && timeUnitToSeconds($value, true) > $more_options['max']) {
					$error = _s('Value must be smaller than %1$s.', $more_options['max']);
				}
			}
		}

		return $error === '';
	}

	/**
	 * Object validator.
	 *
	 * @param array  $rules
	 * @param array  $rules['fields']
	 * @param string $rules['fields'][<field_name>][<role_name>]
	 * @param mixed  $value
	 * @param string $error
	 * @param string $path
	 * @param array $files
	 *
	 * @return bool
	 */
	public function validateObject(array $rules, &$value, ?string &$error = null, string &$path = '',
			?array &$files = []): bool {
		if (!is_array($value)) {
			$error = self::getMessage($rules, 'type', _('An array is expected.'));

			return false;
		}

		$value_fields = [];
		$file_fields = [];

		foreach ($rules['fields'] as $field => $rule_sets) {
			if (!$rule_sets) {
				$value_fields[$field] = true;
				$file_fields[$field] = true;
			}

			foreach ($rule_sets as $rule_set) {
				$this->validateField($rule_set, $value, $field, $path.'/'.$field, $files);

				if ($rule_set['type'] == 'file') {
					$file_fields[$field] = true;
				}
				else {
					$value_fields[$field] = true;
				}
			}
		}

		$value = array_intersect_key($value, $value_fields);
		$files = $files ? array_intersect_key($files, $file_fields) : $files;

		if (array_key_exists('api_uniq', $rules)) {
			foreach ($rules['api_uniq'] as $api_check) {
				$this->api_uniq_checks[] = [
					'rules' => $api_check,
					'path' => $path
				];
			}
		}

		return true;
	}

	/**
	 * Array of objects validator.
	 *
	 * @param array  $rules
	 * @param array  $rules['fields']
	 * @param bool   $rules['not_empty']
	 * @param mixed  $objects_values
	 * @param string $error
	 * @param string $path
	 *
	 * @return bool
	 */
	private function validateObjects(array $rules, &$objects_values, ?string &$error = null, string &$path = ''): bool {
		if (!is_array($objects_values)) {
			$error = self::getMessage($rules, 'type', _('An array is expected.'));

			return false;
		}

		if (array_key_exists('not_empty', $rules) && count($objects_values) == 0) {
			$error = self::getMessage($rules, 'not_empty', _('This field cannot be empty.'));

			return false;
		}

		if (array_key_exists('uniq', $rules)) {
			$this->uniq_checks[] = [
				'rules' => $rules,
				'data' => $objects_values,
				'path' => $path
			];
		}

		foreach ($objects_values as $index => &$value) {
			$path_to_test = $path.'/'.$index;
			if (!$this->validateObject(['fields' => $rules['fields']], $value, $error, $path_to_test)) {
				// Through the reference, give back to the caller which field failed.
				$path = $path_to_test;

				return false;
			}
		}
		unset($value);

		return true;
	}

	/**
	 * Array of values validator.
	 *
	 * @param array  $rules
	 * @param array  $rules['field']
	 * @param bool   $rules['not_empty']
	 * @param mixed  $array_values
	 * @param string $error
	 * @param string $path
	 *
	 * @return bool
	 */
	private function validateArray(array $rules, &$array_values, ?string &$error = null, string $path = ''): bool {
		if (!is_array($array_values)) {
			$error = self::getMessage($rules, 'type', _('An array is expected.'));

			return false;
		}

		$array_values = array_filter($array_values, fn ($value) => !is_null($value));

		if (array_key_exists('not_empty', $rules) && count($array_values) == 0) {
			$error = self::getMessage($rules, 'not_empty', _('This field cannot be empty.'));

			return false;
		}

		if (array_key_exists('field', $rules)) {
			foreach (array_keys($array_values) as $index) {
				$this->validateField($rules['field'], $array_values, $index, $path.'/'.$index);
			}
			unset($value);
		}

		return true;
	}

	/**
	 * Check if given values are uniq according the rules.
	 *
	 * @param array  $rules
	 * @param array  $rules['uniq']
	 * @param mixed  $array_values
	 * @param string $error
	 * @param string $path
	 *
	 * @return bool
	 */
	private static function validateDistinctness(array $rules, $array_values, string &$path, ?string &$error = null): bool {
		foreach ($rules['uniq'] as $field_names) {
			$values = array_map(fn ($entry) => array_intersect_key($entry, array_flip($field_names)), $array_values);
			$unique_values = [];

			foreach ($values as $key => $entry) {
				if (in_array($entry, $unique_values)) {
					$value = implode(', ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($entry), $entry));
					$error = self::getMessage($rules, 'uniq', _s('Entry "%1$s" is not unique.', $value));
					$path = $path.'/'.$key.'/'.$field_names[0];

					return false;
				}

				$unique_values[] = $entry;
			}
		}

		return true;
	}

	/**
	 * Check via API if item exists, excluding provided ID. Used for unique checks.
	 *
	 * @param string      $api
	 * @param array       $options
	 * @param string|null $exclude_primary_id
	 *
	 * @return bool
	 */
	public static function existsAPIObject(string $api, array $options, ?string $exclude_primary_id = null): bool {
		$options['preservekeys'] = true;
		$auth = [
			'type' => CJsonRpc::AUTH_TYPE_COOKIE,
			'auth' => CWebUser::$data['sessionid']
		];

		$response = API::getWrapper()->getClient()->callMethod($api, 'get', $options, $auth);

		if ($response->errorCode) {
			throw new Exception($response->errorMessage);
		}

		$result = $response->data;

		if ($result) {
			$matches = array_diff(array_keys($result), [$exclude_primary_id]);

			if (count($matches) > 0) {
				return true;
			}
		}

		return false;
	}

	private function resolveFieldReference(string $parameter, ?string $field_path, string $path): array {
		if (substr($parameter, 0, 1) === '{' && substr($parameter, -1, 1) === '}') {
			$field_data = $this->getWhenFieldValue(substr($parameter, 1, -1), $path);

			if (in_array($field_data['type'], ['id', 'integer', 'float', 'string'])) {
				$parameter = $field_data['value'];

				if ($field_path === null && $path === '') {
					$field_path = $field_data['path'];
				}
			}
		}

		return [$parameter, $field_path];
	}

	private function validateApiUniq(array $check, string &$path, ?string &$error = null): bool {
		[$method, $parameters, $exclude_id] = $check;
		[$api] = explode('.', $method);

		$field_path = null;

		// Replace field references by real values in API request parameters.
		foreach ($parameters['filter'] as &$parameter) {
			[$parameter, $field_path] = $this->resolveFieldReference($parameter, $field_path, $path);
		}
		unset($parameter);

		foreach ($parameters as $name => &$parameter) {
			if ($name === 'filter') {
				continue;
			}

			[$parameter, $field_path] = $this->resolveFieldReference($parameter, $field_path, $path);
		}
		unset($parameter);

		$parameters_set = !!array_filter($parameters);
		if (!$parameters_set) {
			// If all requested parameters are empty, skip this check.
			return true;
		}

		if ($exclude_id !== null) {
			$exclude_id_field_data = $this->getWhenFieldValue($exclude_id, $path);

			$exclude_id = $exclude_id_field_data['type'] === 'id' ? $exclude_id_field_data['value'] : null;
		}

		if (self::existsAPIObject($api, $parameters, $exclude_id)) {
			$error = _('This object already exists.');

			if ($path === '') {
				/*
				 * Replace it to the first referenced field. This is necessary just to specify what field validation
				 * failed. Used to link message to field.
				 *
				 * This is necessary because api_uniq is defined for whole object but error should be displayed for
				 * some specific field inside that object.
				 */
				$path = $field_path;
			}

			return false;
		}

		return true;
	}

	/**
	 * File validator
	 *
	 * @param array  $rules
	 * @param string $rules['file']['type']       File type (image or file).
	 * @param int    $rules['file']['max-size']  (optional) Maximum size of file
	 * @param array  $rules['messages']			 (optional) Error messages to use when some check fails.
	 * @param mixed  $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function validateFile(array $rules, &$value, ?string &$error = null): bool {
		$file = null;

		try {
			$file = new CUploadFile($value);
		}
		catch (Exception $e) {
			$error = $e->getMessage();

			return false;
		}

		if ($file->wasUploaded() || array_key_exists('not_empty', $rules)) {
			$file_content = null;

			try {
				$file_content = $file->getContent();
			}
			catch (Exception $e) {
				$error = $e->getMessage();

				return false;
			}

			if (array_key_exists('max-size', $rules)) {
				try {
					$file->validateFileSize($rules['max-size'], $rules['file-type']);
				}
				catch (Exception $e) {
					$error = self::getMessage($rules, 'max-size', $e->getMessage());

					return false;
				}
			}

			if ($rules['file-type'] === 'image') {
				try {
					if (@imageCreateFromString($file_content) === false) {
						throw new Exception(_('File format is unsupported.'));
					}
				}
				catch (Exception $e) {
					$error = self::getMessage($rules, 'file-type', $e->getMessage());

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check identifier value.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isId(&$value): bool {
		if (!is_scalar($value) || is_bool($value) || is_double($value) || !ctype_digit(strval($value))) {
			return false;
		}

		if (bccomp($value, ZBX_DB_MAX_ID) > 0) {
			return false;
		}

		$value = (string) $value;

		if ($value[0] === '0') {
			$value = ltrim($value, '0');

			if ($value === '') {
				$value = '0';
			}
		}

		return true;
	}

	/**
	 * Check integer value.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isInt32(&$value): bool {
		if ((!is_int($value) && !is_string($value)) || !preg_match('/^'.ZBX_PREG_INT.'$/', strval($value))) {
			return false;
		}

		if (bccomp($value, ZBX_MIN_INT32) == -1 || bccomp($value, ZBX_MAX_INT32) == 1) {
			return false;
		}

		if (is_string($value)) {
			$value = (int) $value;
		}

		return true;
	}

	/**
	 * Floating point number validator.
	 *
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	private static function is_float(&$value): bool {
		if (is_string($value)) {
			$number_parser = new CNumberParser();

			if ($number_parser->parse($value) == CParser::PARSE_SUCCESS) {
				$value = $number_parser->getMatch();
			}
			else {
				return false;
			}
		}
		elseif (!is_int($value) && !is_float($value)) {
			return false;
		}

		$value = (float) $value;

		return !is_nan($value) && !is_infinite($value);
	}

	/**
	 * Check if given value matches one of values inside $rules['in'].
	 *
	 * @param array  $rules
	 * @param int    $rules['in']  (optional)
	 * @param int    $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkNumericIn(array $rules, $value, ?string &$error = null): bool {
		if (!array_key_exists('in', $rules)) {
			return true;
		}

		$valid = !!array_filter($rules['in'], function ($allowed) use ($value) {
			return is_array($allowed) ? self::isInRange($value, $allowed) : $value == $allowed;
		});

		if (!$valid) {
			$scalars = array_filter($rules['in'], 'is_scalar');
			$ranges = array_filter($rules['in'], 'is_array');
			$ranges = array_map(fn ($val) => sprintf('%1$s:%2$s', $val[0] ?? '', $val[1] ?? ''), $ranges);

			$error_parts = [];

			if ($scalars) {
				$scalars = array_map(fn ($val) => '"'.$val.'"', $scalars);
				$error_parts[] = count($scalars) == 1
					? reset($scalars)
					: sprintf(_s('one of %1$s', implode(', ', $scalars)));
			}

			if ($ranges) {
				$error_parts[] = count($ranges) == 1
					? sprintf(_s('within range %1$s', reset($ranges)))
					: sprintf(_s('within ranges %1$s', implode(', ', $ranges)));
			}

			$error = sprintf(_s('This value must be %1$s.', implode(_s(' or '), $error_parts)));
		}

		return $valid;
	}

	/**
	 * Check if given value is not one of forbidden values inside $rules['in'].
	 *
	 * @param array  $rules
	 * @param int    $rules['not_in']  (optional)
	 * @param int    $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkNumericNotIn(array $rules, $value, ?string &$error = null): bool {
		if (!array_key_exists('not_in', $rules)) {
			return true;
		}

		$invalid = !!array_filter($rules['not_in'], function ($disalowed) use ($value) {
			return is_array($disalowed) ? self::isInRange($value, $disalowed) : $value == $disalowed;
		});

		if ($invalid) {
			$scalars = array_filter($rules['not_in'], 'is_scalar');
			$ranges = array_filter($rules['not_in'], 'is_array');
			$ranges = array_map(fn ($val) => sprintf('%1$s:%2$s', $val[0] ?? '', $val[1] ?? ''), $ranges);

			$error_parts = [];

			if ($scalars) {
				$scalars = array_map(fn ($val) => '"'.$val.'"', $scalars);
				$error_parts[] = count($scalars) == 1
					? reset($scalars)
					: sprintf(_s('one of %1$s', implode(', ', $scalars)));
			}

			if ($ranges) {
				$error_parts[] = count($ranges) == 1
					? sprintf(_s('within range %1$s', reset($ranges)))
					: sprintf(_s('within ranges %1$s', implode(', ', $ranges)));
			}

			$error = sprintf(_s('This value cannot be %1$s.', implode(_s(' or '), $error_parts)));
		}

		return !$invalid;
	}

	/**
	 * Check if given value matches one of values inside $rules['in'].
	 *
	 * @param array  $rules
	 * @param int    $rules['in']  (optional)
	 * @param int    $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkStringIn(array $rules, $value, ?string &$error = null): bool {
		if (array_key_exists('in', $rules) && !in_array($value, $rules['in'])) {
			$values = implode(', ', array_map(function ($val) {return '"'.$val.'"';}, $rules['in']));
			$error = _n('This value must be %1$s.', 'This value must be one of %1$s.',  $values, count($rules['in']));

			return false;
		}

		return true;
	}

	/**
	 * Check if given value is not one of values inside $rules['not_in'].
	 *
	 * @param array  $rules
	 * @param int    $rules['not_in']  (optional)
	 * @param int    $value
	 * @param string $error
	 *
	 * @return bool
	 */
	private static function checkStringNotIn(array $rules, $value, ?string &$error = null): bool {
		if (array_key_exists('not_in', $rules) && in_array($value, $rules['not_in'])) {
			$values = implode(', ', array_map(function ($val) {return '"'.$val.'"';}, $rules['not_in']));
			$error = _n('This value cannot be %1$s.', 'This value cannot be one of %1$s.',  $values, count($rules['not_in']));

			return false;
		}

		return true;
	}

	/**
	 * Check if given value is inside given range(s).
	 *
	 * @param  mixed $value       Value to test.
	 * @param  array $in_rules    Range with min and max value. At least one boundary is mandatory.
	 *
	 * @return bool
	 */
	private static function isInRange($value, array $in_rules): bool {
		[$from, $to] = $in_rules;

		if ($from === null) {
			return $value <= $to;
		}
		elseif ($to === null) {
			return $from <= $value;
		}
		else {
			return $from <= $value && $value <= $to;
		}
	}

	/**
	 * Calculate result of 'when' condition.
	 *
	 * @param array   $when_rules
	 * @param string  $when_rules[0]              Field name.
	 * @param mixed   $when_rules[<test_method>]  Validation rules to check the 'when' field value.
	 *
	 * @return bool
	 */
	private function testWhenCondition(array $when_rules, string $field_path): bool {
		$when_field = $this->getWhenFieldValue($when_rules[0], $field_path);
		unset($when_rules[0]);

		if (array_key_exists('exist', $when_rules) || array_key_exists('not_exist', $when_rules)) {
			return array_key_exists('exist', $when_rules)
				? $when_field['value'] !== null
				: $when_field['value'] === null;
		}

		switch ($when_field['type']) {
			case 'id':
				return self::validateId($when_rules, $when_field['value']);

			case 'integer':
				return self::validateInt32($when_rules, $when_field['value']);

			case 'float':
				return self::validateFloat($when_rules, $when_field['value']);

			case 'string':
				return self::validateStringUtf8($when_rules, $when_field['value']);

			default:
				/*
				 * If specific field has not been passed to validate(), it is not also stored in
				 * $this->field_values since it contains validated values only. Sometimes this is what is tested.
				 */
				if (array_key_exists('not_empty', $when_rules)) {
					return array_key_exists('value', $when_field) && $when_field['value'] === true;
				}
				elseif (array_key_exists('empty', $when_rules)) {
					return !array_key_exists('value', $when_field) || $when_field['value'] === false;
				}

				return false;
		}
	}

	/**
	 * Find the value and type for field that is referred in 'when' condition.
	 *
	 * @param string  $when_field    Field name to get the value and type for. Supports repeatable prefix '../' to get
	 *                               value from the levels above.
	 * @param string  $field_path    Path to the field in which context function was called.
	 *
	 * @return array
	 */
	private function getWhenFieldValue(string $when_field, string $field_path): array {
		$target_path = self::getWhenFieldAbsolutePath($when_field, $field_path);

		return array_key_exists($target_path, $this->field_values)
			? $this->field_values[$target_path] + ['path' => $target_path]
			: ['type' => null];
	}

	/**
	 * Function to create absolute path of given when field in context of given $field_path.
	 *
	 * E.g., function called with parameters $when_field = 'useip' and $field_path = '/interfaces/1/dns' produces
	 * string '/interfaces/1/useip'.
	 *
	 * @param string  $when_field    Reference to target field.
	 * @param string  $field_path    Absolute context field path for which target must be calculated.
	 *
	 * @return string
	 */
	private static function getWhenFieldAbsolutePath(string $when_field, string $field_path): string {
		$field_path_parts = explode('/', $field_path);
		$target_path = array_slice($field_path_parts, 0, -1);

		while (str_starts_with($when_field, '../')) {
			if (count($target_path) != 0 && is_numeric(end($target_path))) {
				$target_path = array_slice($target_path, 0, -1);
			}

			$when_field = substr($when_field, 3);
			$target_path = array_slice($target_path, 0, -1);
		}

		$path = implode('/', array_merge($target_path, [$when_field]));
		if (substr($path, 0, 1) !== '/') {
			$path = '/'.$path;
		}

		return $path;
	}

	/**
	 * Function is meant to collect type and value of all fields that in rules are referenced in 'when' condition.
	 */
	private function resolveWhenFields(array $rules_all, $data_all): array {
		$this->when_resolved_data = [
			'rules' => $rules_all,
			'data' => $data_all,
			'result' => [],
			'fields_to_lookup' => []
		];

		$this->scanObject($rules_all, $data_all, '');

		$this->when_resolved_data['fields_to_lookup'] = array_fill_keys(
			$this->when_resolved_data['fields_to_lookup'], 3
		);

		while ($this->when_resolved_data['fields_to_lookup']) {
			foreach ($this->when_resolved_data['fields_to_lookup'] as $path => $attempts_left) {
				if ($attempts_left == 0 || array_key_exists($path, $this->when_resolved_data['result'])) {
					unset($this->when_resolved_data['fields_to_lookup'][$path]);

					continue;
				}

				if ($this->registerReferencedField($path) === false) {
					$this->when_resolved_data['fields_to_lookup'][$path]--;
				}
			}
		}

		return $this->when_resolved_data['result'];
	}

	/**
	 * Function to walk through all rules. Meant to call self::checkField function for each rule regardless of its
	 * depth. This is a helper function for resolveWhenFields.
	 */
	private function scanObject(array $rules, $data, string $path): void {
		if (!is_array($data)) {
			return;
		}

		if (array_key_exists('api_uniq', $rules)) {
			foreach ($rules['api_uniq'] as $api_check) {
				foreach ($api_check[1]['filter'] as $param) {
					if (substr($param, 0, 1) === '{' && substr($param, -1, 1) === '}') {
						$this->when_resolved_data['fields_to_lookup'][]
							= self::getWhenFieldAbsolutePath(substr($param, 1, -1), $path);
					}
				}

				foreach ($api_check[1] as $name => $param) {
					if ($name === 'filter') {
						continue;
					}

					if (substr($param, 0, 1) === '{' && substr($param, -1, 1) === '}') {
						$this->when_resolved_data['fields_to_lookup'][]
							= self::getWhenFieldAbsolutePath(substr($param, 1, -1), $path);
					}
				}

				if (array_key_exists(2, $api_check) && $api_check[2] !== null) {
					$this->when_resolved_data['fields_to_lookup'][]
						= self::getWhenFieldAbsolutePath($api_check[2], $path);
				}
			}
		}

		if (!array_key_exists('fields', $rules)) {
			return;
		}

		foreach ($rules['fields'] as $field => $rule_sets) {
			$field_data = array_key_exists($field, $data) ? $data[$field] : null;

			foreach ($rule_sets as $rule_set) {
				$this->checkField($rule_set, $field_data, $path.'/'.$field);
			}
		}
	}

	/**
	 * Function to check each separate ruleset. This is a helper function for resolveWhenFields.
	 */
	private function checkField(array $rule_set, $data, string $rule_path): void {
		if (array_key_exists('when', $rule_set)) {
			foreach ($rule_set['when'] as $when) {
				$this->when_resolved_data['fields_to_lookup'][] = self::getWhenFieldAbsolutePath($when[0], $rule_path);
			}
		}

		if (!$data || !is_array($data)) {
			return;
		}

		if ($rule_set['type'] === 'objects') {
			foreach ($data as $field => $value) {
				$this->scanObject($rule_set, $value, $rule_path.'/'.$field);
			}
		}
		elseif ($rule_set['type'] === 'object') {
			$this->scanObject($rule_set, $data, $rule_path);
		}
	}

	/**
	 * Function to catch field's value in type. This is a helper function for resolveWhenFields.
	 */
	private function registerReferencedField($path): bool {
		$field_type = $this->getFieldTypeByPath($path);
		$field_data = $this->getFieldValueByPath($path);

		switch ($field_type) {
			case 'objects':
			case 'array':
				/*
				 * For fields of type='array' and type='objects' values are not stored because only supported
				 * 'when' methods are 'empty' and 'not_empty' so we need to know only if values was filled.
				 */
				$this->when_resolved_data['result'][$path] = [
					'type' => $field_type,
					'value' => $field_data && count($field_data) != 0
				];
				break;

			case 'id':
			case 'integer':
			case 'float':
			case 'string':
				$this->when_resolved_data['result'][$path] = [
					'type' => $field_type,
					'value' => $field_data
				];
				break;

			default:
				return false;
		}

		return true;
	}

	private function getFieldTypeByPath($path): ?string {
		$path_parts = explode('/', $path);
		$path_parts = array_slice($path_parts, 1);

		$paths_to_lookup = [[
			'rule' => $this->when_resolved_data['rules'],
			'path_so_far' => []
		]];
		$result_rule_sets = [];

		while($paths_to_lookup) {
			$current_path = array_shift($paths_to_lookup);
			$rule = $current_path['rule'];
			$path_so_far = $current_path['path_so_far'];

			if ($rule['type'] === 'objects') {
				// Add array index to path.
				$path_so_far[] = $path_parts[count($path_so_far)];
			}

			$field_name = $path_parts[count($path_so_far)];
			$path_so_far[] = $field_name;

			if (!array_key_exists('fields', $rule) || !array_key_exists($field_name, $rule['fields'])) {
				continue;
			}

			$matching_rules = $this->findMatchingRuleSets($rule['fields'][$field_name], '/'.implode('/', $path_so_far));

			if (count($path_so_far) == count($path_parts)) {
				$result_rule_sets += $matching_rules;
			}
			else {
				foreach ($matching_rules as $matching_rule) {
					if (in_array($matching_rule['type'], ['object', 'objects'])) {
						$paths_to_lookup[] = [
							'rule' => $matching_rule,
							'path_so_far' => $path_so_far
						];
					}
				}
			}
		}

		if (!$result_rule_sets) {
			return null;
		}

		$field_type = $result_rule_sets[0]['type'];

		foreach ($result_rule_sets as $rule_set) {
			if ($rule_set['type'] !== $field_type) {
				// Single field should not be matched to multiple types.
				throw new Exception('Internal error.');
			}
		}

		return $field_type;
	}

	/**
	 * Returns field type and data for given absolute path. This is a helper function for resolveWhenFields.
	 */
	private function getFieldValueByPath($path) {
		$path_parts = explode('/', $path);
		$path_parts = array_slice($path_parts, 1);
		$path_field_names = array_values(array_filter($path_parts, fn($part) => !is_numeric($part)));
		$field_data = $this->when_resolved_data['data'];

		foreach ($path_field_names as $field_name) {
			array_shift($path_parts);

			if (!array_key_exists($field_name, $field_data)) {
				$field_data = null;
				break;
			}

			$field_data = $field_data[$field_name];

			if (count($path_parts) != 0 && is_numeric($path_parts[0])) {
				$int_part = array_shift($path_parts);

				if (array_key_exists($int_part, $field_data)) {
					$field_data = $field_data[$int_part];
				}
				else {
					$field_data = null;
					break;
				}
			}
		}

		return $field_data;
	}

	/**
	 * Find field type from the first matching rule-set. This is a helper function for resolveWhenFields.
	 *
	 * @param array   $rule_sets    Rulesets to chose from.
	 * @param string  $field_path   Path till the field in original array of values.
	 *
	 * @return array
	 */
	private function findMatchingRuleSets(array $rule_sets, string $field_path) {
		$valid_rule_sets = [];

		foreach ($rule_sets as $rule_set) {
			if (array_key_exists('when', $rule_set) && $rule_set['when']) {
				foreach ($rule_set['when'] as $when) {
					$when_path = self::getWhenFieldAbsolutePath($when[0], $field_path);
					unset($when[0]);

					if (array_key_exists($when_path, $this->when_resolved_data['result'])
							&& self::checkValue($when, $this->when_resolved_data['result'][$when_path]) === true) {
						$valid_rule_sets[] = $rule_set;
					}
				}
			}
			else {
				$valid_rule_sets[] = $rule_set;
			}
		}

		return $valid_rule_sets;
	}

	/**
	 * Find which of alternative rules is used to define field type.
	 * This is a helper function for resolveWhenFields.
	 *
	 * @return bool
	 */
	private static function checkValue(array $when_rules, array $when_field): bool {
		switch ($when_field['type']) {
			case 'id':
				return self::validateId($when_rules, $when_field['value']);

			case 'integer':
				return self::validateInt32($when_rules, $when_field['value']);

			case 'float':
				return self::validateFloat($when_rules, $when_field['value']);

			case 'string':
				return self::validateStringUtf8($when_rules, $when_field['value']);

			case 'file':
				return self::validateFile($when_rules, $when_field['value']);

			default:
				/*
				 * If specific field has not been passed to validate(), it is not also stored in
				 * $field_values since it contains validated values only. Sometimes this is what is tested.
				 */
				if (array_key_exists('not_empty', $when_rules)) {
					return array_key_exists('value', $when_field) && $when_field['value'] === true;
				}
				elseif (array_key_exists('empty', $when_rules)) {
					return !array_key_exists('value', $when_field) || $when_field['value'] === false;
				}

				return true;
		}
	}
}
