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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__) . '/../../include/CAPITest.php';

/**
 * Base class for SCIM API tests.
 */
class CAPIScimTest extends CAPITest {

	/**
	 * Check SCIM API response.
	 *
	 * @param array $response  response to be checked
	 * @param mixed $error     expected error
	 */
	public function checkResult($response, $error = null): void {
		// Check response data.
		if ($error === null || $error === false) {
			$this->assertArrayHasKey('schemas', $response, json_encode($response, JSON_PRETTY_PRINT));
		}
		else {
			$this->assertArrayHasKey('schemas', $response);
			$this->assertArrayHasKey('detail', $response);
			$this->assertArrayHasKey('status', $response);

			$this->assertSame($error['schemas'], $response['schemas']);
			$this->assertSame($error['detail'], $response['detail']);
			$this->assertSame($error['status'], $response['status']);
		}
	}

	/**
	 * Prepare request for SCIM API call and make API SCIM call (@see CAPIScimHelper::callRaw).
	 *
	 * @param string $method    SCIM API method to be called.
	 * @param array  $params    SCIM API call params.
	 * @param array  $error     expected error if any or null/false if successful result is expected.
	 *
	 * @return array
	 */
	public function call($method, $params, $error = null): array {
		$response = CAPIScimHelper::call($method, $params);
		$this->checkResult($response, $error);

		return $response;
	}
}
