<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CColorValidator extends CValidator {

	/**
	 * Validate hex color number.
	 *
	 * @param $color
	 *
	 * @return bool
	 */
	public function validate($color) {
		if (!preg_match('/[0-9a-f]{6}/i', $color)) {
			$this->setError(_s('Colour "%1$s" is not correct: expecting hexadecimal colour code (6 symbols).', $color));
			return false;
		}

		return true;
	}
}
