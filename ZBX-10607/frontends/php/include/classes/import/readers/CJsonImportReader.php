<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CJsonImportReader extends CImportReader {

	/**
	 * convert string with data in JSON format to php array.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public function read($string) {
		if ($string === '') {
			throw new Exception(_s('Cannot read JSON: %1$s.', _('JSON is empty')));
		}

		$data = (new CJson)->decode($string, true);

		if ($data === null){
			if (json_last_error() == JSON_ERROR_NONE) {
				throw new Exception(_s('Cannot read JSON: %1$s.', _('Unknown error')));
			}
			throw new Exception(_s('Cannot read JSON: %1$s.', json_last_error_msg()));
		}

		return $data;
	}
}
