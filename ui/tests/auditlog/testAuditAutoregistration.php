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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup config_autoreg_tls, config, ids
 */
class testAuditAutoregistration extends testPageReportsAuditValues {

	/**
	 * Id of Autoregistration.
	 *
	 * @var integer
	 */
	protected static $ids = 1;

	public $updated = "autoregistration.tls_accept: 1 => 3".
			"\nautoregistration.tls_psk: ****** => ******".
			"\nautoregistration.tls_psk_identity: ****** => ******";

	public $resource_name = 'Autoregistration';

	/**
	 * Check audit of updated Autoregistration.
	 */
	public function testAuditAutoregistration_Update() {
		CDataHelper::call('autoregistration.update', [
			'tls_accept' => '3',
			'tls_psk_identity' => 'PSK 001',
			'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}
}
