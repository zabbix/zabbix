<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * @onBefore prepareUserMediaData
 *
 * @dataSource LoginUsers
 *
 * @backup users
 */
class testPageUsers extends CLegacyWebTest {
	public $userAlias = 'Admin';
	public $userName = 'Zabbix';
	public $userSurname = 'Administrator';
	public $userRole = 'Super admin role';

	/**
	 * Data for MassDelete scenario.
	 */
	public function prepareUserMediaData() {
		CDataHelper::call('user.update', [
			[
				'userid' => 1,
				'medias' => [
					[
						'mediatypeid' => 10, // Discord.
						'sendto' => 'test@zabbix.com',
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 16,
						'period' => '1-7,00:00-24:00'
					],
					[
						'mediatypeid' => 12, // Jira.
						'sendto' => 'test_account',
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 63,
						'period' => '6-7,09:00-18:00'
					]
				]
			]
		]);
	}

	public static function allUsers() {
		return CDBHelper::getDataProvider('select * from users');
	}

	public function testPageUsers_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestCheckHeader('Users');


		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$this->assertEquals(['Username', 'Name', 'Last name', 'User roles', 'User groups'], $form->getLabels()->asText());
		$form->fill(['User groups' => 'Zabbix administrators']);
		$form->submit();

		$this->zbxTestTextNotPresent('guest');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[3]", $this->userName);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[4]", $this->userSurname);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[5]", $this->userRole);

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Username']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Last name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='User role']");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Groups')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Is online?')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Login')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Frontend access')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Debug mode')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Status')]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Unblock'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete'][@disabled]");
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');

		$form->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
	}

	/**
	* @dataProvider allUsers
	*/
	public function testPageUsers_SimpleUpdate($user) {
		$userid = $user['userid'];
		$alias = $user['username'];

		DBexecute('UPDATE users SET autologout=0 WHERE userid=2');

		$sqlHashUser = 'select * from users where userid='.$userid;
		$oldHashUser = CDBHelper::getHash($sqlHashUser);
		$sqlHashGroup = 'select * from users_groups where userid='.$userid.' order by id';
		$oldHashGroup = CDBHelper::getHash($sqlHashGroup);
		$sqlHashMedia = 'select * from media where userid='.$userid.' order by mediaid';
		$oldHashMedia = CDBHelper::getHash($sqlHashMedia);

		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');

		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$this->zbxTestTextPresent($alias);
		$this->zbxTestClickLinkText($alias);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Users');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User updated');
		$this->zbxTestTextPresent($alias);

		$this->assertEquals($oldHashUser, CDBHelper::getHash($sqlHashUser));
		$this->assertEquals($oldHashGroup, CDBHelper::getHash($sqlHashGroup));
		$this->assertEquals($oldHashMedia, CDBHelper::getHash($sqlHashMedia));
	}

	public function testPageUsers_FilterByAlias() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();
		$form->fill(['Username' => $this->userAlias]);
		$form->submit();
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterNone() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();
		$form->fill(['Username' => '1928379128ksdhksdjfh']);
		$form->submit();
		$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestInputTypeOverwrite('filter_username', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterByAllFields() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();

		$form->fill([
			'User groups' => 'Zabbix administrators',
			'Username' =>  $this->userAlias,
			'Last name' => $this->userSurname,
			'User roles' => $this->userRole
		]);
		$form->submit();

		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageUsers_FilterReset() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	 * @backup users
	 */
	public function testPageUsers_MassDelete() {
		$result=DBselect("SELECT userid,username FROM users");

		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->query('button:Reset')->waitUntilClickable()->one()->click();

		while ($user = DBfetch($result)) {
			$id = $user['userid'];
			$alias = $user['username'];

			$this->zbxTestClickButtonText('Reset');
			$this->zbxTestWaitForPageToLoad();

			$this->zbxTestCheckboxSelect('userids_' . $id);
			$this->zbxTestClickButton('user.delete');

			$this->zbxTestAcceptAlert();
			$this->zbxTestCheckTitle('Configuration of users');
			if (in_array($alias, ['guest', 'Admin', 'test-timezone', 'admin user for testFormScheduledReport',
					'user-recipient of the report', 'user-for-blocking'])) {
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' ,'Cannot delete user');
				$this->assertNotEquals(0, CDBHelper::getCount("select * from users where userid=$id"));
				$this->assertNotEquals(0, CDBHelper::getCount("select * from users_groups where userid=$id"));
				if ($alias === 'Admin') {
					$this->assertNotEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
				}
				else {
					$this->assertEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
				}
			}
			else {
				$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'User deleted');
				$this->assertEquals(0, CDBHelper::getCount("select * from users where userid=$id"));
				$this->assertEquals(0, CDBHelper::getCount("select * from users_groups where userid=$id"));
				$this->assertEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
			}
		}
	}
}
