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


class CJsonImportReader extends CImportReader {

	/**
	 * Convert string with data in JSON format to php array.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public function read($string) {
		$data = json_decode($string, true);

		if ($data === null){
			throw new Exception(_s('Cannot read JSON: %1$s.', json_last_error_msg()));
		}

		return $data;
	}
}
