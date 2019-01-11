<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CUpdateDiscoveredValidator extends CValidator implements CPartialValidatorInterface {

	/**
	 * Fields that can be updated for discovered objects. If no fields are set, updating discovered objects
	 * will be forbidden.
	 *
	 * @var array
	 */
	public $allowed = [];

	/**
	 * Error message in case updating discovered objects is totally forbidden.
	 *
	 * @var string
	 */
	public $messageAllowed;

	/**
	 * Error message in case we can update only certain fields for discovered objects.
	 *
	 * @var string
	 */
	public $messageAllowedField;

	/**
	 * Checks that only the allowed fields for discovered objects are updated.
	 *
	 * The object must have the "flags" property defined.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function validate($object) {
		$allowedFields = array_flip($this->allowed);

		if ($object['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			// ignore the "flags" field
			unset($object['flags']);

			foreach ($object as $field => $value) {
				if (!isset($allowedFields[$field])) {
					// if we allow to update some fields, throw an error referencing a specific field
					// we check if there is more than 1 field, because the PK must always be present
					if (count($allowedFields) > 1) {
						$this->error($this->messageAllowedField, $field);
					}
					else {
						$this->error($this->messageAllowed);
					}

					return false;
				}
			}
		}

		return true;
	}

	public function validatePartial(array $array, array $fullArray) {
		$array['flags'] = $fullArray['flags'];

		return $this->validate($array);
	}

}
