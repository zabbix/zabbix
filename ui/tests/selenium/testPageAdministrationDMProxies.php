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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageAdministrationDMProxies extends CLegacyWebTest {

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
		$this->oldHashProxy = CDBHelper::getHash($this->sqlHashProxy);
		$this->sqlHashInterface = 'SELECT * FROM interface WHERE hostid='.$proxy_hostid.' ORDER BY interfaceid';
		$this->oldHashInterface = CDBHelper::getHash($this->sqlHashInterface);
		$this->sqlHashHosts = 'SELECT hostid,proxy_hostid FROM hosts WHERE proxy_hostid='.$proxy_hostid.' ORDER BY hostid';
		$this->oldHashHosts = CDBHelper::getHash($this->sqlHashHosts);
		$this->sqlHashDRules = 'SELECT druleid,proxy_hostid FROM drules WHERE proxy_hostid='.$proxy_hostid.' ORDER BY druleid';
		$this->oldHashDRules = CDBHelper::getHash($this->sqlHashDRules);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashProxy, CDBHelper::getHash($this->sqlHashProxy));
		$this->assertEquals($this->oldHashInterface, CDBHelper::getHash($this->sqlHashInterface));
		$this->assertEquals($this->oldHashHosts, CDBHelper::getHash($this->sqlHashHosts));
		$this->assertEquals($this->oldHashDRules, CDBHelper::getHash($this->sqlHashDRules));
	}

	public static function proxies() {
		return CDBHelper::getDataProvider(
			'SELECT hostid,host'.
			' FROM hosts'.
			' WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.') AND hostid<>20003'
		);
	}

	public function testPageAdministrationDMProxies_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent([
			'Name', 'Mode', 'Encryption', 'Last seen (age)', 'Host count', 'Item count', 'Required performance (vps)', 'Hosts'
		]);

		$this->zbxTestTextPresent(['Enable hosts', 'Disable hosts', 'Delete']);
	}

	/**
	* @dataProvider proxies
	*/
	public function testPageAdministrationDMProxies_SimpleUpdate($proxy) {
		$this->calculateHash($proxy['hostid']);

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestClickLinkText($proxy['host']);
		$this->zbxTestClickButtonText('Update');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestTextPresent('Proxy updated');
		$this->zbxTestTextPresent($proxy['host']);

		$this->verifyHash();
	}

}
