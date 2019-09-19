<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/CRequest.php';
require_once dirname(__FILE__).'/CSDK.php';
require_once dirname(__FILE__).'/CClientError.php';
require_once dirname(__FILE__).'/CClient.php';

class CBaseCase extends PHPUnit_Framework_TestCase {

	/**
	 * @var $sdk CSDK
	 */
	public $sdk;

	/**
	 * @var $client CClient
	 */
	public $client;

	protected function setUp() {
		$this->client = new CClient('Admin', 'zabbix');
		$this->sdk = new CSDK();
	}

	protected function tearDown() {
		$this->sdk->undo($this->client);
		$this->client->call('user.logout');
	}

	public function getExpandedSignature(array $data) {
		$identifier_keys = ['groupid', 'templateid', 'itemid'];
		array_walk_recursive($data, function(&$value, $key) use (&$identifier_keys) {
			if (in_array($key, $identifier_keys)) {
				$value = '{MASKED}';
			}
		});

		return $this->getExportSignature($data);
	}

	public function getExportSignature(array $export) {
		unset($export['zabbix_export']['date']);

		return md5(json_encode($export));
	}
}
