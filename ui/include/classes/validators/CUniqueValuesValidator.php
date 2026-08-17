<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CUniqueValuesValidator extends CValidator {
	private string $separator = ',';

	/**
	 * Checks if the given string when separated by separator:
	 * - contains only unique trimmed values
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$values = array_map('trim', explode($this->separator, $value));

		if (array_unique($values) !== $values) {
			$this->setError(_('values must be unique'));
			return false;
		}

		return true;
	}
}
