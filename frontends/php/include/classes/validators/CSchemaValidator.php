<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class CSchemaValidator extends CValidator {

	/**
	 * Array of validators where keys are object field names and values CValidator objects.
	 *
	 * @var array
	 */
	public $validators = array();

	/**
	 * Array of validators to validate the whole object.
	 *
	 * @var array
	 */
	public $postValidators = array();

	/**
	 * Array of required field names.
	 *
	 * @var array
	 */
	public $required = array();

	/**
	 * Error message if a required field is missing.
	 *
	 * @var string
	 */
	public $messageRequired = 'No "%1$s" given.';

	/**
	 * Error message if an unsupported field is given.
	 *
	 * @var string
	 */
	public $messageUnsupported = 'Wrong fields.';

	/**
	 * Checks each object field against the given validator, and then the whole object against the post validators.
	 *
	 * @param array $value
	 *
	 * @return bool
	 */
	public function validate($value)
	{
		$required = array_flip($this->required);
		$unvalidatedFields = array_flip(array_keys($value));

		// field validators
		foreach ($this->validators as $field => $validator) {
			if (isset($value[$field])) {
				if ($validator->validate($value[$field])) {
					unset($unvalidatedFields[$field]);
				}
				else {
					$this->setError($validator->getError());

					return false;
				}
			}
			elseif (isset($required[$field])) {
				$this->error($this->messageRequired, $field);

				return false;
			}
		}

		// check if any unsupported fields remain
		if ($unvalidatedFields) {
			$this->error($this->messageUnsupported);

			return false;
		}

		// post validators
		foreach ($this->postValidators as $validator) {
			if (!$validator->validate($value)) {
				$this->setError($validator->getError());

				return false;
			}
		}

		return true;
	}

	/**
	 * Set the object name for the current validator and all included validators.
	 *
	 * @param string $name
	 */
	public function setObjectName($name) {
		parent::setObjectName($name);

		foreach ($this->validators as $validator) {
			$validator->setObjectName($name);
		}

		foreach ($this->postValidators as $validator) {
			$validator->setObjectName($name);
		}
	}

}
