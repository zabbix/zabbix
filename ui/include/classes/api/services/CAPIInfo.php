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
