<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	private $sqlHashProxy = '';
	private $oldHashProxy = '';
	private $sqlHashInterface = '';
	private $oldHashInterface = '';
	private $sqlHashHosts = '';
	private $oldHashHosts = '';
	private $sqlHashDRules = '';
	private $oldHashDRules = '';

	private function calculateHash($proxy_hostid) {
		$this->sqlHashProxy = 'SELECT * FROM hosts WHERE hostid='.$proxy_hostid;
		$this->oldHashProxy = DBhash($this->sqlHashProxy);
		$this->sqlHashInterface = 'SELECT * FROM interface WHERE hostid='.$proxy_hostid.' ORDER BY interfaceid';
		$this->oldHashInterface = DBhash($this->sqlHashInterface);
		$this->sqlHashHosts = 'SELECT hostid,proxy_hostid FROM hosts WHERE proxy_hostid='.$proxy_hostid.' ORDER BY hostid';
		$this->oldHashHosts = DBhash($this->sqlHashHosts);
		$this->sqlHashDRules = 'SELECT druleid,proxy_hostid FROM drules WHERE proxy_hostid='.$proxy_hostid.' ORDER BY druleid';
		$this->oldHashDRules = DBhash($this->sqlHashDRules);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashProxy, DBhash($this->sqlHashProxy));
		$this->assertEquals($this->oldHashInterface, DBhash($this->sqlHashInterface));
		$this->assertEquals($this->oldHashHosts, DBhash($this->sqlHashHosts));
		$this->assertEquals($this->oldHashDRules, DBhash($this->sqlHashDRules));
	}

	public static function allProxies() {
		return DBdata(
			'SELECT hostid,host'.
			' FROM hosts'.
			' WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'
		);
	}

	public function testPageAdministrationDMProxies_CheckLayout() {
		$this->zbxTestLogin('proxies.php');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestTextPresent('CONFIGURATION OF PROXIES');
		$this->zbxTestTextPresent('Proxies');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent(array(
			'Name', 'Mode', 'Last seen (age)', 'Host count', 'Item count', 'Required performance (vps)', 'Hosts'
		));

		$this->zbxTestDropdownHasOptions('action', array('Enable selected', 'Disable selected', 'Delete selected'));
		$this->assertElementValue('goButton', 'Go (0)');
	}

	/**
	* @dataProvider allProxies
	*/
	public function testPageAdministrationDMProxies_SimpleUpdate($proxy) {
		$this->calculateHash($proxy['hostid']);

		$this->zbxTestLogin('proxies.php');
		$this->zbxTestClickWait('link='.$proxy['host']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestTextPresent('Proxy updated');
		$this->zbxTestTextPresent($proxy['host']);

		$this->verifyHash();
	}

}
