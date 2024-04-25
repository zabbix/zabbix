<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

define('PROXY_GOOD', 0);
define('PROXY_BAD', 1);

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup hosts
 */
class testFormAdministrationDMProxies extends CLegacyWebTest  {
	private $proxy_name = 'proxy_name_1';
	private $new_proxy_name = 'proxy_name_new';
	private $cloned_proxy_name = 'proxy_name_new_clone';
	private $long_proxy_name =  '1234567890123456789012345678901234567890123456789012345678901234';
	private $long_proxy_name2 = '12345678901234567890123456789012345678901234567890123456789012345';
	private $proxy_host = 'Zabbix server';
	private $passive_proxy_host = 'H1';
	private $passive_proxy_name = 'passive_proxy_name1';

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function testFormAdministrationDMProxies_CheckLayout() {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');

		$this->zbxTestClickButtonText('Create proxy');
		$this->zbxTestTextPresent(['Proxy name', 'Proxy mode', 'Proxy address', 'Description']);

		$this->zbxTestAssertElementPresentId('host');
		$this->zbxTestAssertAttribute('//input[@id=\'host\']', 'maxlength', '128');
		$this->zbxTestAssertElementText("//ul[@id='status']//label[@for='status_0']", 'Active');
		$this->zbxTestAssertElementText("//ul[@id='status']//label[@for='status_1']", 'Passive');
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.create']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

		// Switch to passive mode
		$this->zbxTestClickXpathWait("//ul[@id='status']//label[@for='status_1']");
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('ip'));
		$this->zbxTestTextPresent(['Proxy name', 'Proxy mode', 'Interface', 'Description']);
		$this->zbxTestTextPresent(['IP address', 'DNS name', 'Connect to', 'Port']);

		$this->zbxTestAssertElementPresentId('host');
		$this->zbxTestAssertAttribute('//input[@id=\'host\']', 'maxlength', '128');
		$this->zbxTestAssertElementPresentId('status');
		$this->zbxTestAssertElementPresentId('ip');
		$this->zbxTestAssertAttribute('//input[@id=\'ip\']', 'maxlength', '64');
		$this->zbxTestAssertElementPresentId('dns');
		$this->zbxTestAssertAttribute('//input[@id=\'dns\']', 'maxlength', '255');
		$this->zbxTestAssertElementPresentId('port');
		$this->zbxTestAssertAttribute('//input[@id=\'port\']', 'maxlength', '64');
		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.create']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");
	}

	// Returns all possible proxy data
	public static function dataCreate() {
		// Ok/bad, name, mode, hosts, ip, dns, connect_to, port, error
		return [
			[PROXY_GOOD, 'New active proxy 1', HOST_STATUS_PROXY_ACTIVE,
				0, 0, 'No encryption', 0, ''
			],
			[PROXY_GOOD, 'New active proxy 2', HOST_STATUS_PROXY_ACTIVE,
				0, 0, 'PSK', 0, ''
			],
			[PROXY_GOOD, 'New active proxy 3', HOST_STATUS_PROXY_ACTIVE,
				0, 0, 'Certificate', 0, ''
			],
			[PROXY_GOOD, 'New passive proxy 1', HOST_STATUS_PROXY_PASSIVE,
				'192.168.1.1', 'proxy123.zabbix.com', 'No encryption', 11051, ''
			],
			[PROXY_GOOD, 'New passive proxy with IP macro', HOST_STATUS_PROXY_PASSIVE,
				'{$PROXY_IP}', 'proxy123.zabbix.com', 'PSK', 11051, ''
			],
			[PROXY_GOOD, 'New passive proxy with port macro', HOST_STATUS_PROXY_PASSIVE,
				'192.168.1.1', 'proxy123.zabbix.com', 'Certificate', '{$PROXY_PORT}', ''
			],
			[
				PROXY_BAD,
				'New passive proxy 2',
				HOST_STATUS_PROXY_PASSIVE,
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Invalid parameter "/1/interface/ip": an IP address is expected.']
			],
			[
				PROXY_BAD,
				'%^&',
				HOST_STATUS_PROXY_PASSIVE,
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Invalid parameter "/1/host": invalid host name.']
			],
			[
				PROXY_BAD,
				'Прокси',
				HOST_STATUS_PROXY_PASSIVE,
				'wrong ip',
				'proxy123.zabbix.com',
				11051,
				0,
				['Cannot add proxy', 'Invalid parameter "/1/host": invalid host name.']
			],
			[
				PROXY_BAD,
				'New passive proxy 3',
				HOST_STATUS_PROXY_PASSIVE,
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'port',
				['Cannot add proxy', 'Invalid parameter "/1/interface/port": an integer is expected.']
			],
			[PROXY_BAD,
				'Active proxy 1',
				HOST_STATUS_PROXY_ACTIVE,
				0,
				0,
				0,
				0,
				['Cannot add proxy', 'Proxy "Active proxy 1" already exists.']
			],
			[PROXY_BAD,
				'New passive proxy with wrong port macro',
				HOST_STATUS_PROXY_PASSIVE,
				'192.168.1.1',
				'proxy123.zabbix.com',
				0,
				'$PROXY_PORT',
				['Cannot add proxy', 'Invalid parameter "/1/interface/port": an integer is expected.']
			],
			[PROXY_BAD,
				'New passive proxy with wrong IP macro',
				HOST_STATUS_PROXY_PASSIVE,
				'$PROXY_IP',
				'proxy123.zabbix.com',
				0,
				11051,
				['Cannot add proxy', 'Invalid parameter "/1/interface/ip": an IP address is expected.']
			]
		];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormAdministrationDMProxies_Create($expected, $name, $mode, $ip, $dns, $connect_to, $port, $errormsgs) {

		$this->zbxTestLogin('zabbix.php?action=proxy.list');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');

		$this->zbxTestClickButtonText('Create proxy');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');

		$this->zbxTestInputTypeWait('host', $name);

		switch ($mode) {
			case HOST_STATUS_PROXY_ACTIVE:
				$this->zbxTestClickXpathWait("//ul[@id='status']//label[@for='status_0']");
				$this->zbxTestClickWait('tab_encryptionTab');
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('encryption'));
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_connect_0'][@disabled]");
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_connect_1'][@disabled]");
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_connect_2'][@disabled]");

				switch ($connect_to) {
					case 'No encryption':
						$this->zbxTestAssertNotVisibleId('tls_psk_identity');
						$this->zbxTestAssertNotVisibleId('tls_psk');
						$this->zbxTestAssertNotVisibleId('tls_issuer');
						$this->zbxTestAssertNotVisibleId('tls_subject');
						break;

					case 'PSK':
						$this->zbxTestCheckboxSelect('tls_in_psk');
						$this->zbxTestInputTypeWait('tls_psk_identity', 'test identity');
						$this->zbxTestInputTypeWait('tls_psk', '12345678901234567890123456789012');
						break;

					case 'Certificate':
						$this->zbxTestCheckboxSelect('tls_in_cert');
						$this->zbxTestAssertElementPresentId('tls_issuer');
						$this->zbxTestAssertElementPresentId('tls_subject');
						break;
				}
				break;

			case HOST_STATUS_PROXY_PASSIVE:
				$this->zbxTestClickXpathWait("//ul[@id='status']//label[@for='status_1']");
				$this->zbxTestInputTypeOverwrite('ip', $ip);
				$this->zbxTestInputTypeOverwrite('dns', $dns);
				$this->zbxTestInputTypeOverwrite('port', $port);
				$this->zbxTestClickWait('tab_encryptionTab');
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('encryption'));
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_in_none'][@disabled]");
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_psk_identity'][@disabled]");
				$this->zbxTestAssertElementPresentXpath("//input[@id='tls_psk'][@disabled]");

				switch ($connect_to) {
					case 'No encryption':
						$this->assertTrue($this->zbxTestCheckboxSelected('tls_connect_0'));
						break;

					case 'PSK':
						$this->zbxTestClickXpathWait("//ul[@id='tls_connect']//label[@for='tls_connect_1']");
						$this->zbxTestInputTypeWait('tls_psk_identity', 'test identity');
						$this->zbxTestInputTypeWait('tls_psk', '12345678901234567890123456789012');
						break;

					case 'Certificate':
						$this->zbxTestClickXpathWait("//ul[@id='tls_connect']//label[@for='tls_connect_2']");
						$this->zbxTestAssertElementPresentId('tls_issuer');
						$this->zbxTestAssertElementPresentId('tls_subject');
						break;
				}
				break;
		}

		$this->zbxTestClickButton('proxy.create');
		switch ($expected) {
			case PROXY_GOOD:
				$this->zbxTestTextNotPresent('Cannot add proxy');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Proxy added');
				$this->zbxTestCheckTitle('Configuration of proxies');
				$this->zbxTestCheckHeader('Proxies');
				$this->zbxTestTextPresent(['Mode', 'Name', 'Encryption', 'Last seen (age)', 'Host count', 'Required performance (vps)', 'Hosts']);
				$this->zbxTestTextPresent($name);

				switch ($mode) {
					case HOST_STATUS_PROXY_ACTIVE:
						$sql = "SELECT hostid FROM hosts WHERE host='$name' AND status=$mode";
						$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Active proxy has not been added into Zabbix DB');
						break;

					case HOST_STATUS_PROXY_PASSIVE:
						$sql = "SELECT hostid FROM hosts WHERE host='$name' AND status=$mode";
						$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Active proxy has not been added into Zabbix DB');

						$sql = "SELECT h.hostid FROM hosts h, interface i WHERE h.host='$name' AND h.status=$mode and h.hostid=i.hostid and i.port='$port' and i.dns='$dns' and i.ip='$ip' and i.main=".INTERFACE_PRIMARY;
						$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Interface was not linked correctly to proxy');
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
			['Active proxy 3', 'New active proxy 3 updated'],
			['Passive proxy 3', 'New passive proxy 3 updated']
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

		$this->zbxTestAssertElementPresentXpath("//button[@value='proxy.update']");
		$this->zbxTestAssertElementPresentId('clone');
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

		$sql=
			'SELECT hostid,proxy_hostid,status,ipmi_authtype,ipmi_privilege,ipmi_username,'.
				'ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from'.
			' FROM hosts'.
			' ORDER BY hostid';

		$oldHash = CDBHelper::getHash($sql);

		$this->zbxTestInputTypeOverwrite('host', $newname);
		$this->zbxTestClickButton('proxy.update');
		$this->zbxTestTextPresent('Proxy updated');
		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent($newname);

		$this->assertEquals($oldHash, CDBHelper::getHash($sql));

		$sql = "SELECT * FROM hosts WHERE host='$newname' AND status in (".HOST_STATUS_PROXY_ACTIVE.",".HOST_STATUS_PROXY_PASSIVE.")";
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Proxy name has not been updated');
	}

	public function getCloneData() {
		return [
			[
				[
					'proxy' => 'Active proxy 2'
				]
			],
			[
				[
					'proxy' => 'Passive proxy 2'
				]
			],
			[
				[
					'proxy' => 'Active proxy to delete',
					'encryption_fields' => [
						'id:tls_in_none' => true,
						'id:tls_in_psk' => true,
						'id:tls_in_cert' => true,
						'PSK identity' => "~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//",
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => 'test test',
						'Subject' => 'test test'
					]
				]
			],
			[
				[
					'proxy' => 'Passive proxy 2',
					'encryption_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => "~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//",
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb'
					]
				]
			],
			[
				[
					'proxy' => 'Passive proxy 2',
					'encryption_fields' => [
						'Connections to proxy' => 'Certificate',
						'Issuer' => 'test test',
						'Subject' => 'test test'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormAdministrationDMProxies_Clone($data) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();

		$form = $this->query('id:proxy-form')->asForm()->one();
		$original_fields = $form->getFields()->asValues();

		// Get original passive proxy interface fields.
		if (str_contains($data['proxy'], 'Passive')) {
			$original_fields = $this->getInterfaceValues($form, $original_fields);
		}

		$new_name = 'Cloned proxy '.microtime();

		$this->query('button:Clone')->waitUntilClickable()->one()->click();
		$form->fill(['Proxy name' => $new_name]);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Proxy added');

		// Check cloned proxy form fields.
		$this->query('link', $new_name)->one()->waitUntilClickable()->click();
		$original_fields['Proxy name'] = $new_name;
		$cloned_fields = $form->getFields()->asValues();

		// Get cloned passive proxy interface fields.
		if (str_contains($data['proxy'], 'Passive')) {
			$cloned_fields = $this->getInterfaceValues($form, $cloned_fields);
		}

		$this->assertEquals($original_fields, $cloned_fields);

		// Check "Encryption" tabs functionality.
		if (CTestArrayHelper::get($data, 'encryption_fields')) {
			$form->selectTab('Encryption');
			$form->fill($data['encryption_fields'])->waitUntilReady();
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Proxy updated');
		}
	}

	/**
	 * Function for returning interface fields.
	 *
	 * @param COverlayDialogElement    $dialog    proxy form overlay dialog
	 * @param array                    $fields	  passive proxy interface fields
	 *
	 * @return array
	 */
	private function getInterfaceValues($dialog, $fields) {
		foreach (['ip', 'dns', 'port'] as $id) {
			$fields[$id] = $dialog->query('id', $id)->one()->getValue();
		}
		$fields['useip'] = $dialog->query('id:useip')->one()->asSegmentedRadio()->getValue();

		return $fields;
	}

	public static function dataDelete() {
		return [
			['Active proxy to delete'],
			['Passive proxy to delete']
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

		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestTextPresent(['Update', 'Clone', 'Delete', 'Cancel']);

		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of proxies');
		$this->zbxTestCheckHeader('Proxies');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Proxy deleted');
		$this->zbxTestAssertElementNotPresentXpath("//a[text()='".$name."']");

		$sql = "SELECT * FROM hosts WHERE host='$name'";
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Proxy has not been deleted');
	}
}
