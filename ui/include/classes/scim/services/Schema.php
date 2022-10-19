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

namespace SCIM\services;

use SCIM\ScimApiService;

class Schema extends ScimApiService {
	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	/**
	 * Returns array with information that is or is not supported by Zabbix SCIM model.
	 *
	 * @return array
	 */
	public function get(): array {
		self::exception(self::SCIM_ERROR_BAD_REQUEST, _('The requested endpoint is not available.'));
	}

	public function put(): void {
		self::exception(self::SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
	}

	public function post(): void {
		self::exception(self::SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
	}

	public function patch(): void {
		self::exception(self::SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
	}

	public function delete(): void {
		self::exception(self::SCIM_METHOD_NOT_SUPPORTED, _('The endpoint does not support the provided method.'));
	}
}

