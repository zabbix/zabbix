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

class testPageAdministrationDMProxies extends CWebTest {
	// Returns all proxies
	public static function allProxies() {
		return DBdata("select * from hosts where status in (".HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.") order by hostid");
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_CheckLayout($proxy) {
		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		// Header
		$this->ok(array('Name', 'Mode', 'Last seen (age)', 'Host count', 'Item count', 'Required performance (vps)', 'Hosts'));
		// Data
		$this->ok(array($proxy['host']));
		$this->dropdown_select('go', 'Enable selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_SimpleUpdate($proxy) {
		$proxyid=$proxy['hostid'];
		$name=$proxy['host'];

		$sqlProxy="select * from hosts where host='$name' order by hostid";
		$oldHashProxy=DBhash($sqlProxy);
		$sqlHosts="select proxy_hostid from hosts order by hostid";
		$oldHashHosts=DBhash($sqlHosts);

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of proxies');
		$this->ok('Proxy updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF PROXIES');

		$this->assertEquals($oldHashProxy, DBhash($sqlProxy), "Chuck Norris: no-change proxy update should not update data in table 'hosts'");
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts), "Chuck Norris: no-change proxy update should not update 'hosts.proxy_hostid'");
	}

	public function testPageAdministrationDMProxies_MassActivateAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_MassActivate($proxy) {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageAdministrationDMProxies_MassDisableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_MassDisable($proxy) {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageAdministrationDMProxies_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_MassDelete($proxy) {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageAdministrationDMProxies_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
