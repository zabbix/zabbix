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


abstract class CValidator {

	/**
	 * Name of the object that can be used in error message. If it is set, it will replace the %1$s placeholder and
	 * all other places holders will be shifted by +1.
	 *
	 * @var string
	 */
	protected $objectName;

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private $error;

	public function __construct(array $options = []) {
		// set options
		foreach ($options as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * Returns true if the given $value is valid, or set's an error and returns false otherwise.
	 *
	 * @abstract
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	abstract public function validate($value);

	/**
	 * Get first validation error.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Add validation error.
	 *
	 * @param $error
	 */
	protected function setError($error) {
		$this->error = $error;
	}

	/**
	 * @param string $name
	 */
	public function setObjectName($name) {
		$this->objectName = $name;
	}

	/**
	 * Throws an exception when trying to set a nonexistent validator option.
	 *
	 * @param $name
	 * @param $value
	 *
	 * @throws Exception
	 */
	public function __set($name, $value) {
		throw new Exception(sprintf('Incorrect option "%1$s" for validator "%2$s".', $name, get_class($this)));
	}

	/**
	 * Adds a validation error with custom parameter support. The value of $objectName will be passed as the
	 * first parameter.
	 *
	 * @param string    $message   Message optionally containing placeholders to substitute.
	 * @param mixed     $param     Unlimited number of optional parameters to replace sequential placeholders.
	 *
	 * @return string
	 */
	protected function error($message) {
		$arguments = array_slice(func_get_args(), 1);

		if ($this->objectName !== null) {
			array_unshift($arguments, $this->objectName);
		}

		$this->setError(vsprintf($message, $arguments));
	}


	/**
	 * Returns string representation of a variable.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function stringify($value) {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		elseif (is_null($value)) {
			return 'null';
		}
		elseif (is_object($value)) {
			return get_class($value);
		}
		elseif (is_scalar($value)) {
			return (string)$value;
		}
		else {
			return gettype($value);
		}
	}
}
