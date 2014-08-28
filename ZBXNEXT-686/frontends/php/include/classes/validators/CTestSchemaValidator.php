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

	public $schema = false;

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
		elseif ($values != $schema) {
			throw new InvalidArgumentException(
				'Unexpected value '.json_encode($values).' for path "'.implode('->', $path).'", expected '.json_encode($schema)
			);
		}

		return true;
	}

	protected function checkLiteralArray(array $values, $schema, array $path) {
		foreach ($values as $field => $value) {
			$subpath = $path;
			$subpath[] = $field;

			// check if the field is defined in the schema
			if (!isset($schema[$field])) {
				throw new InvalidArgumentException(
					'Unexpected key "'.$field.'" for path "'.implode('->', $path).'"'
				);
			}

			if ($schema[$field] !== null) {
				$this->checkRecursive($value, $schema[$field], $subpath);
			}

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

	protected function checkAssertArray($values, array $schema, $path) {
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

	protected function isAssertArray($schema) {
		return array_key_exists('_assert', $schema)
				|| array_key_exists('_each', $schema)
				|| array_key_exists('_keys', $schema);
	}


	protected function checkAssert($value, $assert, array $path) {
		$rules = explode('|', $assert);
		$validator = Respect\Validation\Validator::create();

		foreach ($rules as $rule) {
			preg_match("/^(?'rule'[a-z]+)(\((?'params'[^)]+)\)){0,1}$/i", $rule, $matches);

			if (!isset($matches['rule'])) {
				throw new \Exception(sprintf('Can not parse validation rule "%s"', $rule));
			}

			$rule = $matches['rule'];

			if (isset($matches['params'])) {
				$params = explode(',', $matches['params']);
				$params = array_map(function ($value) {
					return trim($value);
				}, $params);
			} else {
				$params = array();
			}

			$validatorInstance = call_user_func_array(array($validator, $rule), $params);
			/* @var $validatorInstance AbstractRule */
			try {
				$validatorInstance->assert($value);
			} catch (\InvalidArgumentException $e) {
				throw new InvalidArgumentException(
					'Value '.json_encode($value).' for path "'.implode('->', $path).'" doesn\'t match assertion "'.$assert.'"'
				);
			}
		}
	}
}
