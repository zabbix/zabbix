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


class CTextHelper {

	private function __construct() {}

	/**
	 * Shortens a string to a specific length and adds an ellipsis if it exceeds that limit.
	 *
	 * @param string $string
	 * @param int    $limit
	 *
	 * @return string
	 */
	public static function trimWithEllipsis(string $string, int $limit) {
		if (mb_strlen($string) > $limit) {
			$string = mb_substr($string, 0, $limit).'...';
		}

		return $string;
	}
}
