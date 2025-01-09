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


namespace SCIM\services;

use SCIM\ScimApiService;

class ServiceProviderConfig extends ScimApiService {

	/**
	 * Returns array with information that is or is not supported by Zabbix SCIM model.
	 *
	 * @return array
	 */
	public function get(array $options = []): array {
		return [
			'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
			'patch' => ['supported' => true],
			'bulk' => [
				'supported' => false,
				'maxOperations' => 0,
				'maxPayloadSize' => 0
			],
			'filter' => [
				'supported' => false,
				'maxResults' => 0
			],
			'changePassword' => ['supported' => false],
			'sort' => ['supported' => false],
			'etag' => ['supported' => false],
			'authenticationSchemes' => [
				'name' => 'OAuth Bearer Token',
				'description' => 'Authentication Scheme using the OAuth Bearer Token Standard',
				'type' => 'oauthbearertoken'
			]
		];
	}
}
