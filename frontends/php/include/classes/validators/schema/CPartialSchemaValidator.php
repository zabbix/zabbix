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


class CPartialSchemaValidator extends CSchemaValidator implements CPartialValidatorInterface {

	public function __construct(array $options = array()) {
		// check that all given post validators are instances of CPartialValidator
		if (isset($options['postValidators']) && $options['postValidators']) {
			foreach ($options['postValidators'] as $postValidator) {
				if (!$postValidator instanceof CPartialValidator) {
					throw new Exception('Partial schema validator post validator must be an instance of CPartialValidator.');
				}
			}
		}

		parent::__construct($options);
	}

	/**
	 * Validates a partial array. Some data may be missing from the given $array, then it will be taken from the
	 * full array.
	 *
	 * Since the array can be incomplete, this method does not validate required parameters.
	 *
	 * @param array $array
	 * @param array $fullArray
	 *
	 * @return bool
	 */
	public function validatePartial(array $array, array $fullArray = null) {
		$unvalidatedFields = array_flip(array_keys($array));

		// field validators
		foreach ($this->validators as $field => $validator) {
			unset($unvalidatedFields[$field]);

			// if the value is present
			if (isset($array[$field])) {
				// validate it if a validator is given, skip it otherwise
				if ($validator && !$validator->validate($array[$field])) {
					$this->setError($validator->getError());

					return false;
				}
			}
		}

		// check if any unsupported fields remain
		if ($unvalidatedFields) {
			reset($unvalidatedFields);
			$field = key($unvalidatedFields);
			$this->error($this->messageUnsupported, $field);

			return false;
		}

		// post validators
		foreach ($this->postValidators as $validator) {
			if (!$validator->validatePartial($array, $fullArray)) {
				$this->setError($validator->getError());

				return false;
			}
		}

		return true;
	}

}
