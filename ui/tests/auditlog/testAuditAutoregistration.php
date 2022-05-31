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
 * @backup config_autoreg_tls, ids
 */
class testAuditAutoregistration extends testPageReportsAuditValues {

	/**
	 * Id of Autoregistration.
	 *
	 * @var integer
	 */
	protected static $ids = 1;

	public $updated = "dashboard.auto_start: 1 => 0".
			"\ndashboard.display_period: 30 => 60".
			"\ndashboard.name: Audit dashboard => Updated dashboard name".
			"\ndashboard.pages[1468]: Deleted".
			"\ndashboard.pages[1469]: Added".
			"\ndashboard.pages[1469].dashboard_pageid: 1469".
			"\ndashboard.pages[1469].widgets[3906]: Added".
			"\ndashboard.pages[1469].widgets[3906].height: 3".
			"\ndashboard.pages[1469].widgets[3906].type: clock".
			"\ndashboard.pages[1469].widgets[3906].widgetid: 3906".
			"\ndashboard.pages[1469].widgets[3906].width: 4".
			"\ndashboard.pages[1470]: Added".
			"\ndashboard.pages[1470].dashboard_pageid: 1470".
			"\ndashboard.pages[1470].display_period: 60".
			"\ndashboard.userGroups[2]: Updated".
			"\ndashboard.userGroups[2].permission: 2 => 3";

	public $resource_name = 'Autoregistration';

	/**
	 * Check audit of updated Autoregistration.
	 */
	public function testAuditAutoregistration_Update() {
		CDataHelper::call('autoregistration.update', [
			[
				'tls_accept' => '3',
				'tls_psk_identity' => 'PSK 001',
				'tls_psk' => '11111595725ac58dd977beef14b97461a7c1045b9a1c923453302c5473193478'
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}
}
