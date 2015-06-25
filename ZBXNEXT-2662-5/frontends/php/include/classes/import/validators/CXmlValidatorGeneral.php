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


/**
 * General Xml validator
 */
class CXmlValidatorGeneral {

	/**
	 * @var array
	 */
	private $rules;

	public function __construct(array $rules) {
		$this->rules = $rules;
	}

	/**
	 * Base validation function.
	 *
	 * @param mixed  $data	import data
	 * @param string $path	XML path (for error reporting)
	 *
	 * @return array		Validator does some manipulation for the incoming data. For example, converts empty tags to
	 *						an array, if desired. Converted array is returned.
	 */
	public function validate($data, $path) {
		$this->validateData($this->rules, $data, null, $path);

		return $data;
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $rules			validation rules
	 * @param mixed  $data			import data
	 * @param array  $parent_data	data's parent array (used for "ex_validate" callback functions)
	 * @param string $path			XML path (for error reporting)
	 *
	 * @throw Exception				if $data does not correspond to validation $rules
	 */
	public function validateData(array $rules, &$data, array $parent_data = null, $path) {
		if (array_key_exists('preprocessor', $rules)) {
			$data = call_user_func($rules['preprocessor'], $data);
		}

		if ($rules['type'] & XML_STRING) {
			$this->validateString($data, $path);
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
						throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path,
							_s('unexpected tag "%1$s"', $tag)
						));
					}
				}
			}

			// validation of the values type
			foreach ($rules['rules'] as $tag => $rule) {
				if (array_key_exists($tag, $data)) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->validateData($rule, $data[$tag], $data, $subpath);
				}
				elseif (($rule['type'] & XML_REQUIRED) || (array_key_exists('ex_required', $rule)
						&& call_user_func($rule['ex_required'], $data))) {
					throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path,
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
					throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path,
						_s('the tag "%1$s" is missing', $rules['extra'])
					));
				}
			}

			foreach ($data as $tag => &$value) {
				if (array_key_exists('extra', $rules) && $rules['extra'] == $tag) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->validateData($rules['rules'][$tag], $value, $data, $subpath);
					continue;
				}

				if ($tag !== $prefix.($index == 0 ? '' : $index)) {
					throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path,
						_s('unexpected tag "%1$s"', $tag)
					));
				}

				$index++;
				$subpath = ($path === '/' ? $path : $path.'/').$prefix.'('.$index.')';
				$this->validateData($rules['rules'][$prefix], $value, $data, $subpath);
			}
			unset($value);

			// indexing the array numerically

			$extra = null;

			if (array_key_exists('extra', $rules)) {
				if (array_key_exists($rules['extra'], $data)) {
					$extra = $data[$rules['extra']];
					unset($data[$rules['extra']]);
				}
			}

			$data = array_values($data);

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
	 * @param mixed  $value	value for validation
	 * @param string $path	XML path (for error reporting)
	 *
	 * @throw Exception		if this $value is not a character string
	 */
	private function validateString($value, $path) {
		if (!is_string($value)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _('a character string is expected')));
		}
	}

	/**
	 * Array validator.
	 *
	 * @param mixed  $value	value for validation
	 * @param string $path	XML path (for error reporting)
	 *
	 * @throw Exception		if this $value is not an array
	 */
	private function validateArray($value, $path) {
		if (!is_array($value)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _('an array is expected')));
		}
	}
}
