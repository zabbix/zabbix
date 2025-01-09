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


namespace SCIM;

use CApiService;

abstract class ScimApiService extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'put' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'post' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'patch' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	public const SCIM_PATCH_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';

	/**
	 * Returns array with information that is or is not supported by Zabbix SCIM model.
	 *
	 * @return array
	 */
	public function get(array $options): array {
		self::exception(ZBX_API_ERROR_NO_METHOD, 'The endpoint does not support the provided method.');
	}

	public function put(array $options): array {
		self::exception(ZBX_API_ERROR_NO_METHOD, 'The endpoint does not support the provided method.');
	}

	public function post(array $options): array {
		self::exception(ZBX_API_ERROR_NO_METHOD, 'The endpoint does not support the provided method.');
	}

	public function patch(array $options): array {
		self::exception(ZBX_API_ERROR_NO_METHOD, 'The endpoint does not support the provided method.');
	}

	public function delete(array $options): array {
		self::exception(ZBX_API_ERROR_NO_METHOD, 'The endpoint does not support the provided method.');
	}
}
