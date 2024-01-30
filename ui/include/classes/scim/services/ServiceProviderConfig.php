<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
