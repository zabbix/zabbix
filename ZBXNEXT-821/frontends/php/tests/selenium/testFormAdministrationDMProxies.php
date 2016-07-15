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

define('PROXY_GOOD', 0);
define('PROXY_BAD', 1);

class testFormAdministrationDMProxies extends CWebTest {
	private $proxy_name = 'proxy_name_1';
	private $new_proxy_name = 'proxy_name_new';
	private $cloned_proxy_name = 'proxy_name_new_clone';
	private $long_proxy_name =  '1234567890123456789012345678901234567890123456789012345678901234';
	private $long_proxy_name2 = '12345678901234567890123456789012345678901234567890123456789012345';
	private $proxy_host = 'Zabbix server';
	private $passive_proxy_host = 'H1';
	private $passive_proxy_name = 'passive_proxy_name1';

	public function testFormAdministrationDMProxies_backup() {
		DBsave_tables('hosts');
	}

	public function testFormAdministrationDMProxies_CheckLayout() {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Mode');
		$this->zbxTestTextPresent('Encryption');
		$this->zbxTestTextPresent('Last seen (age)');
		$this->zbxTestTextPresent('Host count');
		$this->zbxTestTextPresent('Item count');
		$this->zbxTestTextPresent('Required performance (vps)');
		$this->zbxTestTextPresent('Hosts');

		$this->zbxTestClickButtonText('Create proxy');
		$this->zbxTestTextPresent('Proxy name');
		$this->zbxTestTextPresent('Proxy mode');
		$this->zbxTestTextPresent('Hosts');
		$this->zbxTestTextPresent('Proxy hosts');
		$this->zbxTestTextPresent('Other hosts');
		$this->zbxTestTextPresent('Description');

		$this->zbxTestAssertElementPresentId('host');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
		$this->zbxTestAssertAttribute('//input[@id=\'host\']', 'maxlength', '128');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestDropdownHasOptions('status', ['Active', 'Passive']);
		$this->zbxTestAssertElementPresentId('proxy_hostids_left');
		$this->zbxTestAssertElementPresentId('proxy_hostids_right');
		$this->zbxTestAssertElementPresentId('add');
		$this->zbxTestAssertElementPresentId('remove');
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.create']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

		// Switch to passive mode
		$this->zbxTestDropdownSelectWait('status', 'Passive');
		$this->zbxTestTextPresent('Proxy name');
		$this->zbxTestTextPresent('Proxy mode');
		$this->zbxTestTextPresent('Interface');
		$this->zbxTestTextPresent(['IP address', 'DNS name', 'Connect to', 'Port']);
		$this->zbxTestTextPresent('Hosts');
		$this->zbxTestTextPresent('Proxy hosts');
		$this->zbxTestTextPresent('Other hosts');
		$this->zbxTestTextPresent('Description');

		$this->zbxTestAssertElementPresentId('host');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
		$this->zbxTestAssertAttribute('//input[@id=\'host\']', 'maxlength', '128');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertElementPresentId('ip');
		$this->zbxTestAssertAttribute('//input[@id=\'ip\']', 'maxlength', '64');
		$this->zbxTestAssertElementPresentId('dns');
		$this->zbxTestAssertAttribute('//input[@id=\'dns\']', 'maxlength', '64');
		$this->zbxTestAssertElementPresentId('port');
		$this->zbxTestAssertAttribute('//input[@id=\'port\']', 'maxlength', '64');
		$this->zbxTestAssertElementPresentId('proxy_hostids_left');
		$this->zbxTestAssertElementPresentId('proxy_hostids_right');
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.create']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");
	}


	// Returns all possible proxy data
	public static function dataCreate() {
		// Ok/bad, name, mode, hosts, ip, dns, connect_to, port, error
		return [
			[PROXY_GOOD, 'New active proxy 1', HOST_STATUS_PROXY_ACTIVE,
				['ЗАББИКС Сервер'], 0, 0, 0, 0, ''
			],
			[PROXY_GOOD, 'New active proxy 2', HOST_STATUS_PROXY_ACTIVE,
				[], 0, 0, 0, 0, ''
			],
			[PROXY_GOOD, 'New passive proxy 1', HOST_STATUS_PROXY_PASSIVE,
				[], '192.168.1.1', 'proxy123.zabbix.com', 0, 11051, ''
			],
			[PROXY_GOOD, 'New passive proxy with IP macro', HOST_STATUS_PROXY_PASSIVE,
				[], '{$PROXY_IP}', 'proxy123.zabbix.com', 0, 11051, ''
			],
			[PROXY_GOOD, 'New passive proxy with port macro', HOST_STATUS_PROXY_PASSIVE,
				[], '192.168.1.1', 'proxy123.zabbix.com', 0, '{$PROXY_PORT}', ''
			],
			[
				PROXY_BAD,
				'New passive proxy 2',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Invalid IP address "wrong ip".']
			],
			[
				PROXY_BAD,
				'%^&',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Incorrect characters used for proxy name']
			],
			[
				PROXY_BAD,
				'Прокси',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Incorrect characters used for proxy name "Прокси".']
			],
			[
				PROXY_BAD,
				'New passive proxy 3',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'port',
				['Cannot add proxy', 'Incorrect interface port "port" provided.']
			],
			[PROXY_BAD,
				'New active proxy 1',
				HOST_STATUS_PROXY_ACTIVE,
				[],
				0,
				0,
				0,
				0,
				['Cannot add proxy', 'Proxy "New active proxy 1" already exists.']
			],
			[PROXY_BAD,
				'New passive proxy with wrong port macro',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'$PROXY_PORT',
				['Cannot add proxy', 'Incorrect interface port "$PROXY_PORT" provided.']
			],
			[PROXY_BAD,
				'New passive proxy with wrong IP macro',
				HOST_STATUS_PROXY_PASSIVE,
				[],
				'$PROXY_IP',
				'proxy123.zabbix.com',
				0,
				11051,
				['Cannot add proxy', 'Invalid IP address "$PROXY_IP".']
			]
		];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationDMProxies_Create($expected, $name, $mode, $hosts, $ip, $dns, $connect_to, $port, $errormsgs) {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');

		// create proxy
		$this->zbxTestClickButtonText('Create proxy');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');

		$this->zbxTestInputType('host', $name);
		switch ($mode) {
			case HOST_STATUS_PROXY_ACTIVE:
				$this->zbxTestDropdownSelectWait('status', 'Active');
				break;

			case HOST_STATUS_PROXY_PASSIVE:
				$this->zbxTestDropdownSelectWait('status', 'Passive');
				$this->zbxTestInputType('ip', $ip);
				$this->zbxTestInputType('dns', $dns);
// TODO connect_to is not supported yet
				$this->zbxTestInputType('port', $port);
				break;
		}

		// adding host that will be monitored by this proxy
		foreach ($hosts as $host) {
			$this->zbxTestDropdownSelect('proxy_hostids_right', $host);
			$this->zbxTestClick('add');
		}
		$this->zbxTestClickButton('proxy.create');
		switch ($expected) {
			case PROXY_GOOD:
				$this->zbxTestTextPresent('Proxy added');
				$this->zbxTestCheckTitle('Configuration of proxies');
				$this->zbxTestCheckHeader('Proxies');
				$this->zbxTestTextPresent(['Mode', 'Name', 'Encryption','Last seen (age)', 'Host count', 'Required performance (vps)', 'Hosts']);
				$this->zbxTestTextPresent($name);

				switch ($mode) {
					case HOST_STATUS_PROXY_ACTIVE:
						$sql = "SELECT hostid FROM hosts WHERE host='$name' AND status=$mode";
						$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Active proxy has not been added into Zabbix DB');
						break;

					case HOST_STATUS_PROXY_PASSIVE:
						$sql = "SELECT hostid FROM hosts WHERE host='$name' AND status=$mode";
						$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Active proxy has not been added into Zabbix DB');

						$sql = "SELECT h.hostid FROM hosts h, interface i WHERE h.host='$name' AND h.status=$mode and h.hostid=i.hostid and i.port='$port' and i.dns='$dns' and i.ip='$ip' and i.main=".INTERFACE_PRIMARY;
						$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Interface was not linked correcty to proxy');
						break;
				}
				break;

			case PROXY_BAD:
				$this->zbxTestCheckTitle('Configuration of proxies');
				$this->zbxTestCheckHeader('Proxies');
				$this->zbxTestTextPresent('Proxy name');
				foreach ($errormsgs as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}
	}

	public static function dataUpdateProxyName() {
		// Name, newname
		return [
			['New active proxy 1', 'New active proxy 1 updated'],
			['New active proxy 2', 'New active proxy 2 updated'],
			['New passive proxy 1', 'New passive proxy 1 updated']
		];
	}

	/**
	 * @dataProvider dataUpdateProxyName
	 */
	public function testFormAdministrationDMProxies_UpdateProxyName($name, $newname) {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestCheckHeader('Proxies');

		// check presence of buttons
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.update']");
		$this->zbxTestAssertElementPresentId('clone');
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

		$sql=
			'SELECT hostid,proxy_hostid,status,error,available,errors_from,ipmi_authtype,ipmi_privilege,ipmi_username,'.
				'ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,'.
				'maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,'.
				'snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error'.
			' FROM hosts'.
			' ORDER BY hostid';

		$oldHash = DBhash($sql);

		// update proxy name
		$this->zbxTestInputType('host', $newname);
		$this->zbxTestClickButton('proxy.update');
		$this->zbxTestTextPresent('Proxy updated');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent($newname);

		$this->assertEquals($oldHash, DBhash($sql));

		// check that proxy name has been updated in the DB
		$sql = "SELECT * FROM hosts WHERE host='$newname' AND status in (".HOST_STATUS_PROXY_ACTIVE.",".HOST_STATUS_PROXY_PASSIVE.")";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Proxy name has not been updated');
	}

	public static function dataClone() {
		// Name, clone name
		return [
			['Active proxy 1', 'Active proxy 1 cloned'],
			['Active proxy 2', 'Active proxy 2 cloned'],
			['Passive proxy 1', 'Passive proxy 1 cloned']
		];
	}


	/**
	 * @dataProvider dataClone
	 */
	public function testFormAdministrationDMProxies_Clone($name, $newname) {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestCheckHeader('Proxies');

		// check presence of buttons
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.update']");
		$this->zbxTestAssertElementPresentId('clone');
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

		// update proxy name
		$this->zbxTestClickWait('clone');
		$this->zbxTestTextPresent('Proxy');
		$this->zbxTestInputType('host', $newname);
		$this->zbxTestClickButton('proxy.create');
		$this->zbxTestTextPresent('Proxy added');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent($newname);

		// checking that proxy name has been updated in the DB
		$sql = "SELECT * FROM hosts WHERE host='$newname' AND status in (".HOST_STATUS_PROXY_ACTIVE.",".HOST_STATUS_PROXY_PASSIVE.")";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Proxy has not been created');
	}

	public static function dataDelete() {
		return [
			['Active proxy 2'],
			['Passive proxy 2']
		];
	}

	/**
	 * @dataProvider dataDelete
	 */
	public function testFormAdministrationDMProxies_Delete($name) {
		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestClickLinkText($name);

		$this->zbxTestTextPresent(['Update', 'Clone', 'Delete', 'Cancel']);

		$this->zbxTestClickButtonText('Delete');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Proxy deleted');
		$this->zbxTestAssertElementNotPresentXpath("//a[text()='".$name."']");

		$sql = "SELECT * FROM hosts WHERE host='$name'";
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Proxy has not been deleted');
	}

	public function testFormAdministrationDMProxies_restore() {
		DBrestore_tables('hosts');
	}
}
