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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CTestSchemaValidator extends CValidator {

	/**
	 * Schema to compare the value against.
	 *
	 * The schema and each of it's elements can contain the following values:
	 * - null 					- the value will not be validated;
	 * - a literal value 		- can be a string, array, int etc; the value will be compared to the given literal;
	 * 							arrays will be compared recursively;
	 * - an "assertion array" 	- an array containing one of these reserved keys used to perform advanced checks.
	 *
	 * The following keys are supported for assertion arrays:
	 * - _assert	- a validator schema to compare the value against where keys are validator names and
	 * 				values - arrays of parameters that will be passed to the validator; validator names will be translated
	 * 				to class names, for instance, "string" into "CStringValidator";
	 * - _keys		- a validator schema that will be used to validate keys of array values;
	 * - _each		- a validator schema that will be used to validate each array value.
	 *
	 * @var mixed
	 */
	public $schema;

	/**
	 * Error message for invalid values.
	 *
	 * @var string
	 */
	public $messageError = '%s';

	public function validate($values) {
		try {
			$this->checkRecursive($values, $this->schema);

			return true;
		}
		catch (InvalidArgumentException $e) {
			$this->error($this->messageError, $e->getMessage());

			return false;
		}
	}

	/**
	 * Recursively checks the value against the schema.
	 *
	 * @param mixed $values
	 * @param mixed $schema		see self::$schema
	 * @param array $path		the path to the current value
	 *
	 * @return bool
	 *
	 * @throws InvalidArgumentException
	 */
	protected function checkRecursive($values, $schema, array $path = array()) {
		// a literal array
		if (is_array($schema)) {
			if ($this->isAssertArray($schema)) {
				$this->checkAssertArray($values, $schema, $path);
			}
			else {
				$this->checkLiteralArray($values, $schema, $path);
			}
		}
		// a literal scalar value
		elseif ($schema !== null && $values != $schema) {
			throw new InvalidArgumentException(
				'Unexpected value '.json_encode($values).' for path "'.implode('->', $path).'", expected '.json_encode($schema)
			);
		}

		return true;
	}

	/**
	 * Recursively check a literal array
	 *
	 * @param array $values
	 * @param mixed	$schema
	 * @param array $path
	 *
	 * @throws InvalidArgumentException
	 */
	protected function checkLiteralArray(array $values, $schema, array $path) {
		foreach ($values as $field => $value) {
			$subpath = $path;
			$subpath[] = $field;

			// check if the field is defined in the schema
			if (!array_key_exists($field, $schema)) {
				throw new InvalidArgumentException(
					'Unexpected key "'.$field.'" for path "'.implode('->', $path).'"'
				);
			}

			$this->checkRecursive($value, $schema[$field], $subpath);

			unset($schema[$field]);
		}

		// check if any fields are present in the schema but missing from the values
		if ($schema) {
			$missingFields = array_keys($schema);
			throw new InvalidArgumentException(
				'Missing key "'.$missingFields[0].'" for path "'.implode('->', $path).'"'
			);
		}
	}

	/**
	 * Check an assertion array.
	 *
	 * @param mixed $values
	 * @param array $schema
	 * @param array $path
	 */
	protected function checkAssertArray($values, array $schema, array $path) {
		if (isset($schema['_assert'])) {
			$this->checkAssert($values, $schema['_assert'], $path);
		}

		if (isset($schema['_keys'])) {
			$this->checkAssert(array_keys($values), $schema['_keys'], $path);
		}

		if (isset($schema['_each'])) {
			foreach ($values as $field => $value) {
				$subpath = $path;
				$subpath[] = $field;

				$this->checkAssert($value, $schema['_each'], $subpath);
			}
		}
	}

	/**
	 * Returns true if the schema in an assertion array.
	 *
	 * @param $schema
	 * @return bool
	 */
	protected function isAssertArray($schema) {
		return array_key_exists('_assert', $schema)
				|| array_key_exists('_each', $schema)
				|| array_key_exists('_keys', $schema);
	}

	/**
	 * Check a particular assertion.
	 *
	 * @param mixed $value
	 * @param array $assert
	 * @param array $path
	 *
	 * @throws InvalidArgumentException
	 */
	protected function checkAssert($value, array $assert, array $path) {
		foreach ($assert as $name => $params) {
			$validatorClass = 'C'.ucfirst($name).'Validator';
			$validator = new $validatorClass(($params === null) ? array() : $params);
			/* @var $validator CValidator */
			if (!$validator->validate($value)) {
				throw new InvalidArgumentException(
					'Value '.json_encode($value).' for path "'.implode('->', $path).'" doesn\'t match assertion "'.$name.'"'
				);
			}
		}
	}
}
