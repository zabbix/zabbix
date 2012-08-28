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


class CStatusCodeValidator extends CValidator {

	/**
	 * Validate http response code reange.
	 * Range can contain ',' and '-'
	 * Range can be empty string.
	 *
	 * Examples: '100-199, 301, 404, 500-550'
	 *
	 * @param string $statusCodeRange
	 *
	 * @return bool
	 */
	public function validate($statusCodeRange) {
		if ($statusCodeRange == '') {
			return true;
		}

		foreach (explode(',', $statusCodeRange) as $range) {
			$range = explode('-', $range);
			if (count($range) > 2) {
				$this->setError(_s('Invalid response code range "%1$s".', $statusCodeRange));
				return false;
			}

			foreach ($range as $value) {
				if (!is_numeric($value)) {
					$this->setError(_s('Invalid response code "%1$s".', $value));
					return false;
				}
				if ($value > 999) {
					$this->setError(_s('Invalid response code "%1$s".', $value));
					return false;
				}
			}
		}

		return true;
	}
}
