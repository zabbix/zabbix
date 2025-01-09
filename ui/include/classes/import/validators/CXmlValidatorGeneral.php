<?php
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
 * General XML validator
 */
abstract class CXmlValidatorGeneral {

	public const YAML = 'yaml';
	public const XML = 'xml';
	public const JSON = 'json';

	/**
	 * Format of import source.
	 *
	 * @var string
	 */
	protected $format;

	/**
	 * Key validation for XML_INDEXED_ARRAY containers.
	 *
	 * @var bool
	 */
	protected $strict = false;

	/**
	 * This validation is for importcompare, it should produce same output in import, as for export.
	 *
	 * @var bool
	 */
	protected $preview = false;

	/**
	 * @param string $format  Format of import source.
	 */
	public function __construct(string $format) {
		$this->format = $format;
	}

	/**
	 * Get key validation for XML_INDEXED_ARRAY containers.
	 *
	 * @return bool
	 */
	public function getStrict(): bool {
		return $this->strict;
	}

	/**
	 * Set key validation for XML_INDEXED_ARRAY containers.
	 *
	 * @param bool $strict
	 *
	 * @return self
	 */
	public function setStrict(bool $strict): self {
		$this->strict = $strict;

		return $this;
	}

	/**
	 * On import preview validation should not change content.
	 *
	 * @return bool
	 */
	public function isPreview(): bool {
		return $this->preview;
	}

	/**
	 * On import preview validation should not change content.
	 *
	 * @param bool $preview
	 *
	 * @return self
	 */
	public function setPreview(bool $preview): self {
		$this->preview = $preview;

		return $this;
	}

	/**
	 * Validate import data.
	 *
	 * @param array|string $data  Import data.
	 * @param string       $path  XML path (for error reporting).
	 *
	 * @return mixed  Validator does some manipulations for the incoming data. For example, converts empty tags to an
	 *                array, if desired. Converted data is returned.
	 */
	abstract public function validate(array $data, string $path);

	/**
	 * Validate import data against the rules.
	 *
	 * @param array        $rules  Validation rules.
	 * @param array|string $data   Import data.
	 * @param string       $path   XML path (for error reporting).
	 *
	 * @return mixed  Validator does some manipulations for the incoming data. For example, converts empty tags to an
	 *                array, if desired. Converted data is returned.
	 */
	protected function doValidate(array $rules, $data, string $path) {
		$this->doValidateRecursive($rules, $data, null, $path);

		return $data;
	}

	/**
	 * Validate import data recursively.
	 *
	 * @param array        $rules        Validation rules.
	 * @param array|string $data         Import data.
	 * @param array        $parent_data  Data's parent array (used for "ex_validate" callback functions).
	 * @param string       $path         XML path (for error reporting).
	 *
	 * @throws Exception if $data does not correspond to validation $rules.
	 */
	private function doValidateRecursive(array $rules, &$data, ?array $parent_data, string $path): void {
		if (array_key_exists('preprocessor', $rules)) {
			$data = call_user_func($rules['preprocessor'], $data);
		}

		if ($rules['type'] & XML_STRING) {
			$this->validateString($data, $path);

			$this->validateConstant($data, $rules, $path);
		}
		elseif ($rules['type'] & XML_ARRAY) {
			if ($data === '') {
				$data = [];
			}

			$this->validateArray($data, $path);

			// unexpected tag validation
			if (!array_key_exists('check_unexpected', $rules) || $rules['check_unexpected']) {
				foreach ($data as $tag => $value) {
					if (!array_key_exists($tag, $rules['rules'])) {
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path,
							_s('unexpected tag "%1$s"', $tag)
						));
					}
				}
			}

			// validation of the values type
			foreach ($rules['rules'] as $tag => $tag_rules) {
				$tag_rules = $this->getResultRule($tag_rules, $data, $rules['rules']);

				if ($tag_rules['type'] & XML_IGNORE_TAG) {
					continue;
				}

				if (array_key_exists('import', $tag_rules) && !$this->preview) {
					$data[$tag] = call_user_func($tag_rules['import'], $data);
				}

				if (array_key_exists($tag, $data)) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->doValidateRecursive($tag_rules, $data[$tag], $data, $subpath);
				}
				elseif (($tag_rules['type'] & XML_REQUIRED) || (array_key_exists('ex_required', $tag_rules)
						&& call_user_func($tag_rules['ex_required'], $data))) {
					throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path,
						_s('the tag "%1$s" is missing', $tag)
					));
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			if ($data === '') {
				$data = [];
			}

			$this->validateArray($data, $path);

			$index = 0;
			$prefix = $rules['prefix'];

			if (array_key_exists('extra', $rules)) {
				if (!array_key_exists($rules['extra'], $data)
						&& ($rules['rules'][$rules['extra']]['type'] & XML_REQUIRED)) {
					throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path,
						_s('the tag "%1$s" is missing', $rules['extra'])
					));
				}
			}

			foreach ($data as $tag => &$value) {
				if (array_key_exists('extra', $rules) && $rules['extra'] == $tag) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->doValidateRecursive($rules['rules'][$tag], $value, $data, $subpath);
					continue;
				}

				if ($this->strict) {
					switch ($this->format) {
						case self::XML:
							$is_valid_tag = ($tag === $prefix.($index == 0 ? '' : $index) || $tag === $index);
							break;

						case self::YAML:
						case self::JSON:
							$is_valid_tag = ctype_digit(strval($tag));
							break;

						default:
							throw new Exception(_('Internal error.'));
					}

					if (!$is_valid_tag) {
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('unexpected tag "%1$s"', $tag)));
					}
				}

				$index++;
				$subpath = ($path === '/' ? $path : $path.'/').$prefix.'('.$index.')';
				$this->doValidateRecursive($rules['rules'][$prefix], $value, $data, $subpath);
			}
			unset($value);

			$extra = null;

			if (array_key_exists('extra', $rules)) {
				if (array_key_exists($rules['extra'], $data)) {
					$extra = $data[$rules['extra']];
					unset($data[$rules['extra']]);
				}
			}

			if ($extra !== null) {
				$data[$rules['extra']] = $extra;
			}
		}

		if (array_key_exists('ex_validate', $rules)) {
			$data = call_user_func($rules['ex_validate'], $data, $parent_data, $path);
		}
	}

	/**
	 * String validator.
	 *
	 * @param mixed  $value  Value for validation.
	 * @param string $path   XML path (for error reporting).
	 *
	 * @throws Exception if this $value is not a character string.
	 */
	private function validateString($value, string $path): void {
		if (!is_string($value)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('a character string is expected')));
		}
	}

	/**
	 * Array validator.
	 *
	 * @param mixed  $value  Value for validation.
	 * @param string $path   XML path (for error reporting).
	 *
	 * @throws Exception if this $value is not an array.
	 */
	private function validateArray($value, string $path): void {
		if (!is_array($value)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('an array is expected')));
		}
	}

	/**
	 * Constant validator.
	 *
	 * @param mixed  $value  Value for validation.
	 * @param array  $rules  XML rules.
	 * @param string $path   XML path (for error reporting).
	 *
	 * @throws Exception if this $value is an invalid constant.
	 */
	private function validateConstant($value, array $rules, string $path): void {
		if (array_key_exists('in', $rules) && !in_array($value, array_values($rules['in']))) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('unexpected constant "%1$s"', $value)));
		}
	}

	private function getResultRule(array $tag_rules, array &$data, array $parent_rules): array {
		while ($tag_rules['type'] & XML_MULTIPLE) {
			$matched_multiple_rule = null;

			foreach ($tag_rules['rules'] as $multiple_rule) {
				if ($this->multipleRuleMatched($multiple_rule, $data, $parent_rules)) {
					$multiple_rule['type'] = ($tag_rules['type'] & XML_REQUIRED) | $multiple_rule['type'];
					$matched_multiple_rule =
						$multiple_rule + array_intersect_key($tag_rules, array_flip(['default']));
					break;
				}
			}

			if ($matched_multiple_rule === null) {
				// For use by developers. Do not translate.
				throw new Exception('Incorrect XML_MULTIPLE validation rules.');
			}

			$tag_rules = $matched_multiple_rule;
		}

		return $tag_rules;
	}

	private function multipleRuleMatched(array $multiple_rule, array &$data, array $rules): bool {
		if (array_key_exists('else', $multiple_rule)) {
			return true;
		}
		elseif (is_array($multiple_rule['if'])) {
			$field_name = $multiple_rule['if']['tag'];

			if (array_key_exists($field_name, $data)) {
				return in_array($data[$field_name], $multiple_rule['if']['in']);
			}
			else {
				$tag_rules = $this->getResultRule($rules[$field_name], $data, $rules);

				if (!$this->isPreview()) {
					$data[$field_name] = array_key_exists('in', $tag_rules)
						? $tag_rules['in'][$tag_rules['default']]
						: $tag_rules['default'];
				}

				return array_key_exists($tag_rules['default'], $multiple_rule['if']['in']);
			}
		}
		elseif ($multiple_rule['if'] instanceof Closure) {
			return call_user_func($multiple_rule['if'], $data);
		}

		return false;
	}
}
