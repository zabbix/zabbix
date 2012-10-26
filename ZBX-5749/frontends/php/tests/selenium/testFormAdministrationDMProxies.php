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

	public function testFormAdministrationDMProxies_CheckLayout() {

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');

		$this->button_click('form');
		$this->wait();
		$this->ok('Proxy name');
		$this->ok('Proxy mode');
		$this->ok('Hosts');
		$this->ok('Proxy hosts');
		$this->ok('Other hosts');

		$this->assertElementPresent('host');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
		$this->assertAttribute("//input[@id='host']/@maxlength", '64');
		$this->assertElementPresent('status');
		$this->assertElementPresent("//select[@id='status']/option[text()='Active']");
		$this->assertElementPresent("//select[@id='status']/option[text()='Passive']");
		$this->assertElementPresent('hosts_left');
		$this->assertElementPresent('hosts_right');
		$this->assertElementPresent('save');
		$this->assertElementPresent('cancel');

		// Switch to passive mode
		$this->dropdown_select_wait('status', 'Passive');
		$this->ok('Proxy name');
		$this->ok('Proxy mode');
		$this->ok('Interface');
		$this->ok('IP address');
		$this->ok('DNS name');
		$this->ok('Connect to');
		$this->ok('Port');
		$this->ok('Hosts');
		$this->ok('Proxy hosts');
		$this->ok('Other hosts');

		$this->assertElementPresent('host');
		// this check will fail in case of incorrect maxlength value for this "host" element!!!
		$this->assertAttribute("//input[@id='host']/@maxlength", '64');
		$this->assertElementPresent('status');
		$this->assertElementPresent('interfaces_0_ip');
		$this->assertAttribute("//input[@id='interfaces_0_ip']/@maxlength", '39');
		$this->assertElementPresent('interfaces_0_dns');
		$this->assertAttribute("//input[@id='interfaces_0_dns']/@maxlength", '64');
		$this->assertElementPresent('interfaces_0_port');
		$this->assertAttribute("//input[@id='interfaces_0_port']/@maxlength", '64');
		$this->assertElementPresent('hosts_left');
		$this->assertElementPresent('hosts_right');
		$this->assertElementPresent('save');
		$this->assertElementPresent('cancel');
	}


	// Returns all possible proxy data
	public static function dataCreate() {
		// Ok/bad, name, mode, hosts, ip, dns, connect_to, port, error
		return array(
			array(PROXY_GOOD, 'New active proxy 1', HOST_STATUS_PROXY_ACTIVE,
				array('ЗАББИКС Сервер'), 0, 0, 0, 0, ''
			),
			array(PROXY_GOOD, 'New active proxy 2', HOST_STATUS_PROXY_ACTIVE,
				array(), 0, 0, 0, 0, ''
			),
			array(PROXY_GOOD, 'New passive proxy 1', HOST_STATUS_PROXY_PASSIVE,
				array(), '192.168.1.1', 'proxy123.zabbix.com', 0, 11051, ''
			),
			array(PROXY_GOOD, 'New passive proxy with IP macro', HOST_STATUS_PROXY_PASSIVE,
				array(), '{$PROXY_IP}', 'proxy123.zabbix.com', 0, 11051, ''
			),
			array(PROXY_GOOD, 'New passive proxy with port macro', HOST_STATUS_PROXY_PASSIVE,
				array(), '192.168.1.1', 'proxy123.zabbix.com', 0, '{$PROXY_PORT}', ''
			),
			array(
				PROXY_BAD,
				'New passive proxy 2',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				array('Cannot add proxy', 'Incorrect interface IP parameter')
			),
			array(
				PROXY_BAD,
				'%^&',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				array('Cannot add proxy', 'Incorrect characters used for Proxy name "%^&".')
			),
			array(
				PROXY_BAD,
				'Прокси',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				array('Cannot add proxy', 'Incorrect characters used for Proxy name "Прокси".')
			),
			array(
				PROXY_BAD,
				'New passive proxy 3',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'port',
				array('Cannot add proxy', 'Incorrect interface port "port" provided')
			),
			array(PROXY_BAD,
				'New active proxy 1',
				HOST_STATUS_PROXY_ACTIVE,
				array(),
				0,
				0,
				0,
				0,
				array('Cannot add proxy', 'Proxy "New active proxy 1" already exists.')
			),
			array(PROXY_BAD,
				'New passive proxy with wrong port macro',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'$PROXY_PORT',
				array('Cannot add proxy', 'Incorrect interface port "$PROXY_PORT" provided')
			),
			array(PROXY_BAD,
				'New passive proxy with wrong IP macro',
				HOST_STATUS_PROXY_PASSIVE,
				array(),
				'$PROXY_IP',
				'proxy123.zabbix.com',
				0,
				11051,
				array('Cannot add proxy', 'Incorrect interface IP parameter "$PROXY_IP" provided.')
			)
		);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationDMProxies_Create($expected, $name, $mode, $hosts, $ip, $dns, $connect_to, $port, $errormsgs) {

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');
		$this->ok('Name');
		$this->ok('Mode');
		$this->ok('Last seen (age)');
		$this->ok('Host count');
		$this->ok('Item count');
		$this->ok('Required performance (vps)');
		$this->ok('Hosts');

		// create proxy
		$this->button_click('form');
		$this->wait();
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('Proxy');

		$this->input_type('host', $name);

		// checking possible values of the "status" drop-down element
		$this->assertElementPresent("//select[@id='status']/option[text()='Active']");
		$this->assertElementPresent("//select[@id='status']/option[text()='Passive']");
		switch ($mode) {
			case HOST_STATUS_PROXY_ACTIVE:
				$this->dropdown_select_wait('status', 'Active');
				break;

			case HOST_STATUS_PROXY_PASSIVE:
				$this->dropdown_select_wait('status', 'Passive');
				$this->input_type('interfaces_0_ip', $ip);
				$this->input_type('interfaces_0_dns', $dns);
// TODO connect_to is not supported yet
				$this->input_type('interfaces_0_port', $port);
				break;
		}

		// adding host that will be monitored by this proxy
		foreach ($hosts as $host) {
			$this->dropdown_select('hosts_right', $host);
			$this->click('add');
		}
		$this->button_click('save');
		$this->wait();
		switch ($expected) {
			case PROXY_GOOD:
				$this->ok('Proxy added');
				$this->checkTitle('Configuration of proxies');
				$this->ok('CONFIGURATION OF PROXIES');
				$this->ok('PROXIES');
				$this->ok(array('Mode', 'Name', 'Last seen (age)', 'Host count', 'Required performance (vps)', 'Hosts'));
				$this->ok($name);

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
				$this->checkTitle('Configuration of proxies');
				$this->ok('CONFIGURATION OF PROXIES');
				$this->ok('PROXIES');
				$this->ok('Proxy name');
				foreach ($errormsgs as $msg) {
					$this->ok($msg);
				}
				break;
		}
	}

	public function testFormAdministrationDMProxies_UpdatePassive2Active() {
		// TODO
		$this->markTestIncomplete();
	}

	public function testFormAdministrationDMProxies_UpdateActive2Passive() {
		// TODO
		$this->markTestIncomplete();
	}

	public static function dataUpdateProxyName() {
		// Name, newname
		return array(
			array('New active proxy 1', 'New active proxy 1 updated'),
			array('New active proxy 2', 'New active proxy 2 updated'),
			array('New passive proxy 1', 'New passive proxy 1 updated')
		);
	}

	/**
	 * @dataProvider dataUpdateProxyName
	 */
	public function testFormAdministrationDMProxies_UpdateProxyName($name, $newname) {

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');
		$this->click('link='.$name);
		$this->wait();

		// check presence of buttons
		$this->assertElementPresent('save');
		$this->assertElementPresent('clone');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');

		$sqlHash = "SELECT hostid,proxy_hostid,status,disable_until,error,available,errors_from,lastaccess,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error FROM hosts ORDER BY hostid";
		$oldHash = DBhash($sqlHash);

		// update proxy name
		$this->input_type('host', $newname);
		$this->button_click('save');
		$this->wait();
		$this->ok('Proxy updated');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');
		$this->ok($newname);

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");

		// check that proxy name has been updated in the DB
		$sql = "SELECT * FROM hosts WHERE host='$newname' AND status in (".HOST_STATUS_PROXY_ACTIVE.",".HOST_STATUS_PROXY_PASSIVE.")";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Proxy name has not been updated');
	}

	public static function dataClone() {
		// Name, newname
		return array(
			array('Active proxy 1', 'Active proxy 1 cloned'),
			array('Active proxy 2', 'Active proxy 2 cloned'),
			array('Passive proxy 1', 'Passive proxy 1 cloned')
		);
	}


	/**
	 * @dataProvider dataClone
	 */
	public function testFormAdministrationDMProxies_Clone($name, $newname) {

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');
		$this->click('link='.$name);
		$this->wait();

		// check presence of buttons
		$this->assertElementPresent('save');
		$this->assertElementPresent('clone');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');

		$sqlHash = "SELECT hostid,proxy_hostid,status,disable_until,error,available,errors_from,lastaccess,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error FROM hosts ORDER BY hostid";
		$oldHash = DBhash($sqlHash);

		// update proxy name
		$this->button_click('clone');
		$this->wait();
		$this->ok('Proxy');
		$this->input_type('host', $newname);
		$this->button_click('save');
		$this->wait();
		$this->ok('Proxy added');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');
		$this->ok($newname);

		// checking that proxy name has been updated in the DB
		$sql = "SELECT * FROM hosts WHERE host='$newname' AND status in (".HOST_STATUS_PROXY_ACTIVE.",".HOST_STATUS_PROXY_PASSIVE.")";
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Proxy has not been created');
	}

	public static function dataDelete() {
		// Name, newname
		return array(
			array('Active proxy 2'),
			array('Passive proxy 2')
		);
	}

	/**
	 * @dataProvider dataDelete
	 */
	public function testFormAdministrationDMProxies_Delete($name) {
		$this->chooseOkOnNextConfirmation();

		$this->login('proxies.php');
		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');

		$this->click('link='.$name);
		$this->wait();

		$this->assertElementPresent('save');
		$this->assertElementPresent('clone');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');

		$this->button_click('delete');
		$this->waitForConfirmation();
		$this->wait();

		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('Proxy deleted');

		$sql = "SELECT * FROM hosts WHERE host='$name'";
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Proxy has not been deleted');

		$this->checkTitle('Configuration of proxies');
		$this->ok('CONFIGURATION OF PROXIES');
		$this->ok('PROXIES');

	}
}
?>
