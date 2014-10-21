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


class CPartialSchemaValidator extends CSchemaValidator implements CPartialValidatorInterface {

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
	public function validatePartial(array $array, array $fullArray) {
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

	public function addPostValidator(CValidator $validator) {
		// the post validators for the partial schema validator must implement the "CPartialValidatorInterface" interface.
		if (!$validator instanceof CPartialValidatorInterface) {
			throw new Exception(
				'Partial schema validator post validator must implement the "CPartialValidatorInterface" interface.'
			);
		}

		parent::addPostValidator($validator);
	}

}
