<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		$this->zbxTestLogin('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->zbxTestTextPresent('CONFIGURATION OF PROXIES');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');

		// header
		$this->zbxTestTextPresent(array('Name', 'Mode', 'Last seen (age)', 'Host count', 'Item count', 'Required performance (vps)', 'Hosts'));

		// data
		$this->zbxTestTextPresent(array($proxy['host']));
		$this->zbxTestDropdownHasOptions('go', array('Enable selected', 'Disable selected', 'Delete selected'));
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

		$this->zbxTestLogin('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of proxies');
		$this->zbxTestTextPresent('Proxy updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('CONFIGURATION OF PROXIES');

		$this->assertEquals($oldHashProxy, DBhash($sqlProxy), "Chuck Norris: no-change proxy update should not update data in table 'hosts'");
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts), "Chuck Norris: no-change proxy update should not update 'hosts.proxy_hostid'");
	}
}
