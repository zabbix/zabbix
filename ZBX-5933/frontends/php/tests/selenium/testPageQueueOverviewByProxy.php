<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageQueueOverviewByProxy extends CWebTest {
	// Returns all proxies
	public static function allProxies() {
		return DBdata("select * from hosts where status in (".HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.") order by hostid");
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageQueueOverviewByProxy_CheckLayout($proxy) {
		$this->login('queue.php?config=1');
		$this->checkTitle('Queue \[refreshed every 30 sec\]');
		$this->ok('Queue');
		$this->ok('QUEUE OF ITEMS TO BE UPDATED');
		// Header
		$this->ok(
			array(
				'Proxy',
				'5 seconds',
				'10 seconds',
				'30 seconds',
				'1 minute',
				'5 minutes',
				'More than 10 minutes'
			)
		);
		// Data
		$this->ok($proxy['host']);
		$this->ok('Server');
	}

	public function testPageQueueOverviewByProxy_VerifyDisplayedNumbers() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
