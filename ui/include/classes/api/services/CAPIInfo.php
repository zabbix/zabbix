<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Class containing methods for operations with API.
 */
class CAPIInfo extends CApiService {

	public const ACCESS_RULES = [
		'version' => []
	];

	/**
	 * Get API version.
	 *
	 * @return string
	 */
	public function version(array $request) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' =>[]];
		if (!CApiInputValidator::validate($api_input_rules, $request, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return ZABBIX_API_VERSION;
	}
}
