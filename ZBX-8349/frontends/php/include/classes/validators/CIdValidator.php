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


class CIdValidator extends CStringValidator {

	/**
	 * Numeric ID regex.
	 *
	 * @var string
	 */
	public $regex = '/^\d+$/';

	/**
	 * Error message if the id is not in range 0..ZBX_DB_MAX_ID
	 *
	 * @var string
	 */
	public $messageRange;

	/**
	 * Validates ID value
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		// CStringValidator uses zbx_empty, which considers '0' as non-empty value. We do an additional check.
		if (!$this->empty && empty($value)) {
			$this->error($this->messageEmpty);

			return false;
		}

		if (bccomp($value, 0)  == -1 || bccomp($value, ZBX_DB_MAX_ID) == 1) {
			$this->error($this->messageRange, $value);

			return false;
		}

		return parent::validate($value);
	}
}
