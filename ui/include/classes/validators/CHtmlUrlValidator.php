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


class CHtmlUrlValidator {

	/**
	 * Verifies that URL will not lead to third party pages.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function validateSameSite(string $url): bool {
		$root_path = __DIR__.'/../../../';
		preg_match('/^\/?(?<filename>[a-z0-9_.]+\.php)(\?.*)?$/i', $url, $url_parts);

		return array_key_exists('filename', $url_parts) && file_exists($root_path.$url_parts['filename']);
	}
}
