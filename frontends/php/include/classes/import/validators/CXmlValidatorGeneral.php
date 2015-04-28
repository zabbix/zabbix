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

	const XML_STRING = 0x01;
	const XML_ARRAY = 0x02;
	const XML_INDEXED_ARRAY = 0x04;
	const XML_REQUIRED = 0x08;

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
	 * @param array  $data	import data
	 * @param string $path	XML path (for error reporting)
	 */
	public function validate(array $data, $path) {
		$this->validateData($this->rules, $data, $path);
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $rules		validation rules
	 * @param mixed  $data		import data
	 * @param string $path		XML path (for error reporting)
	 *
	 * @throw Exception		if $data does not correspond to validation $rules
	 */
	public function validateData(array $rules, $data, $path) {
		if ($rules['type'] & self::XML_STRING) {
			$this->validateString($data, $path);
		}
		elseif ($rules['type'] & self::XML_ARRAY) {
			$this->validateArray($data, $path);

			// unexpected tag validation
			if (!array_key_exists('check_unexpected', $rules) || $rules['check_unexpected']) {
				foreach ($data as $tag => $value) {
					if (!array_key_exists($tag, $rules['rules'])) {
						throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path,
							_s('unexpected tag "%1$s"', $tag)
						));
					}
				}
			}

			// validation of the values type
			foreach ($rules['rules'] as $tag => $rule) {
				if (array_key_exists($tag, $data)) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->validateData($rule, $data[$tag], $subpath);
				}
				elseif ($rule['type'] & self::XML_REQUIRED) {
					throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path,
						_s('the tag "%1$s" is missing', $tag)
					));
				}
			}
		}
		elseif ($rules['type'] & self::XML_INDEXED_ARRAY) {
			$this->validateArray($data, $path);

			$index = 0;
			$prefix = $rules['prefix'];

			if (array_key_exists('extra', $rules)) {
				if (!array_key_exists($rules['extra'], $data)
						&& ($rules['rules'][$rules['extra']]['type'] & self::XML_REQUIRED)) {
					throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path,
						_s('the tag "%1$s" is missing', $rules['extra'])
					));
				}
			}

			foreach ($data as $tag => $value) {
				if (array_key_exists('extra', $rules) && $rules['extra'] == $tag) {
					$subpath = ($path === '/' ? $path : $path.'/').$tag;
					$this->validateData($rules['rules'][$rules['extra']], $value, $subpath);
					continue;
				}

				if ($tag !== $prefix.($index == 0 ? '' : $index)) {
					throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path,
						_s('unexpected tag "%1$s"', $tag)
					));
				}

				$index++;
				$subpath = ($path === '/' ? $path : $path.'/').$prefix.'('.$index.')';
				$this->validateData($rules['rules'][$prefix], $value, $subpath);
			}
		}

		if (array_key_exists('ex_validate', $rules)) {
			call_user_func($rules['ex_validate'], $data, $path);
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, _('a character string is expected')));
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, _('an array is expected')));
		}
	}
}
