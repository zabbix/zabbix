<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup token, connector
 *
 * @dataSource ScheduledReports, Proxies
 *
 * @onBefore prepareApiTokenData
 */
class testPermissionsCSRF extends CWebTest {

	const UPDATE_API_TOKEN = 'api_token_update';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareApiTokenData() {
		$tokens = CDataHelper::call('token.create', [
			'name' => self::UPDATE_API_TOKEN,
			'userid' => '1'
		]);
		$this->assertArrayHasKey('tokenids', $tokens);

		$connectors = CDataHelper::call('connector.create', [
			'name' => 'test_token_connector',
			'url' => 'http://test.url'
		]);
		$this->assertArrayHasKey('connectorids', $connectors);
	}

	public static function getElementRemoveData() {
		return [
			// #0 Map create.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=Create+map'
				]
			],
			// #1 Map update.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=update&sysmapid=3'
				]
			],
			// #2 Host group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'access_denied' => true,
					'link' => 'zabbix.php?action=hostgroup.edit'
				]
			],
			// #3 Host group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'access_denied' => true,
					'link' => 'zabbix.php?action=hostgroup.edit&groupid=50012'
				]
			],
			// #4 Template group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'access_denied' => true,
					'link' => 'zabbix.php?action=templategroup.edit'
				]
			],
			// #5 Template group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'access_denied' => true,
					'link' => 'zabbix.php?action=templategroup.edit&groupid=14'
				]
			],
			// #6 Template create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=create'
				]
			],
			// #7 Template update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=update&templateid=10169'
				]
			],
			// #8 Host create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=host.edit'
				]
			],
			// #9 Host update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=host.edit&hostid=99062'
				]
			],
			// #10 Item update.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=update&hostid=50011&itemid=99086&context=host'
				]
			],
			// #11 Item create.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=create&hostid=50011&context=host'
				]
			],
			// #12 Trigger update.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?form=update&triggerid=100034&context=host'
				]
			],
			// #13 Trigger create.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?hostid=50011&form=create&context=host'
				]
			],
			// #14 Graph update.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?form=update&graphid=700026&filter_hostids%5B0%5D=99202&context=host'
				]
			],
			// #15 Graph create.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?hostid=50011&form=create&context=host'
				]
			],
			// #16 Discovery rule update.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=update&itemid=99107&context=host'
				]
			],
			// #17 Discovery rule create.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=create&hostid=99202&context=host'
				]
			],
			// #18 Web scenario update.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host'
				]
			],
			// #19 Web scenario create.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=create&hostid=50001&context=host'
				]
			],
			// #20 Maintenance create.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'access_denied' => true,
					'link' => 'zabbix.php?action=maintenance.list',
					'case' => 'popup create'
				]
			],
			// #21 Maintenance update.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'access_denied' => true,
					'link' => 'zabbix.php?action=maintenance.list',
					'case' => 'popup update'
				]
			],
			// #22 Action create.
			[
				[
					'db' => 'SELECT * FROM actions',
					'access_denied' => true,
					'link' => 'zabbix.php?action=action.list&eventsource=0',
					'case' => 'popup create'
				]
			],
			// #23 Action update.
			[
				[
					'db' => 'SELECT * FROM actions',
					'access_denied' => true,
					'link' => 'zabbix.php?action=action.list&eventsource=0',
					'case' => 'popup update'
				]
			],
			// #24 Event correlation create.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'access_denied' => true,
					'link' => 'zabbix.php?action=correlation.edit'
				]
			],
			// #25 Event correlation update.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'access_denied' => true,
					'link' => 'zabbix.php?correlationid=99002&action=correlation.edit'
				]
			],
			// #26 Discovery create.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'access_denied' => true,
					'link' => 'zabbix.php?action=discovery.edit'
				]
			],
			// #27 Discovery update.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'access_denied' => true,
					'link' => 'zabbix.php?action=discovery.edit&druleid=5'
				]
			],
			// #28 GUI update.
			[
				[
					'db' => 'SELECT * FROM config',
					'access_denied' => true,
					'link' => 'zabbix.php?action=gui.edit'
				]
			],
			// #29 Autoregistration update.
			[
				[
					'db' => 'SELECT * FROM autoreg_host',
					'access_denied' => true,
					'link' => 'zabbix.php?action=autoreg.edit'
				]
			],
			// #30 Housekeeping update.
			[
				[
					'db' => 'SELECT * FROM housekeeper',
					'access_denied' => true,
					'link' => 'zabbix.php?action=housekeeping.edit'
				]
			],
			// #31 Image update.
			[
				[
					'db' => 'SELECT * FROM images',
					'access_denied' => true,
					'link' => 'zabbix.php?action=image.edit&imageid=1'
				]
			],
			// #32 Image create.
			[
				[
					'db' => 'SELECT * FROM images',
					'access_denied' => true,
					'link' => 'zabbix.php?action=image.edit&imagetype=1'
				]
			],
			// #33 Icon map update.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'access_denied' => true,
					'link' => 'zabbix.php?action=iconmap.edit&iconmapid=101'
				]
			],
			// #34 Icon map create.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'access_denied' => true,
					'link' => 'zabbix.php?action=iconmap.edit'
				]
			],
			// #35 Regular expression update.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'access_denied' => true,
					'link' => 'zabbix.php?action=regex.edit&regexid=20'
				]
			],
			// #36 Regular expression create.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'access_denied' => true,
					'link' => 'zabbix.php?action=regex.edit'
				]
			],
			// #37 Macros update.
			[
				[
					'db' => 'SELECT * FROM globalmacro',
					'access_denied' => true,
					'link' => 'zabbix.php?action=macros.edit'
				]
			],
			// #38 Trigger displaying options update.
			[
				[
					'db' => 'SELECT * FROM config',
					'access_denied' => true,
					'link' => 'zabbix.php?action=trigdisplay.edit'
				]
			],
			// #39 API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'access_denied' => true,
					'link' => 'zabbix.php?action=token.list',
					'case' => 'token create'
				]
			],
			// #40 API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'access_denied' => true,
					'link' => 'zabbix.php?action=token.list',
					'case' => 'token update'
				]
			],
			// #41 Other parameters update.
			[
				[
					'db' => 'SELECT * FROM config',
					'access_denied' => true,
					'link' => 'zabbix.php?action=miscconfig.edit'
				]
			],
			// #42 Proxy update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=proxy.list',
					'case' => 'proxy update',
					'proxy' => 'Active proxy 1'
				]
			],
			// #43 Proxy create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=proxy.list',
					'case' => 'proxy create'
				]
			],
			// #44 Authentication update.
			[
				[
					'db' => 'SELECT * FROM config',
					'access_denied' => true,
					'link' => 'zabbix.php?action=authentication.edit'
				]
			],
			//#45 User group update.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'access_denied' => true,
					'link' => 'zabbix.php?action=usergroup.edit&usrgrpid=7'
				]
			],
			// #46 User group create.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'access_denied' => true,
					'link' => 'zabbix.php?action=usergroup.edit'
				]
			],
			// #47 User update.
			[
				[
					'db' => 'SELECT * FROM users',
					'access_denied' => true,
					'link' => 'zabbix.php?action=user.edit&userid=1'
				]
			],
			// #48 User create.
			[
				[
					'db' => 'SELECT * FROM users',
					'access_denied' => true,
					'link' => 'zabbix.php?action=user.edit'
				]
			],
			// #49 Media update.
			[
				[
					'db' => 'SELECT * FROM media',
					'access_denied' => true,
					'link' => 'zabbix.php?action=mediatype.edit&mediatypeid=1'
				]
			],
			// #50 create.
			[
				[
					'db' => 'SELECT * FROM media',
					'access_denied' => true,
					'link' => 'zabbix.php?action=mediatype.edit'
				]
			],
			// #51 Script update.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=script.edit&scriptid=1'
				]
			],
			// #52 Script create.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'access_denied' => true,
					'link' => 'zabbix.php?action=script.edit'
				]
			],
			// #53 User profile update.
			[
				[
					'db' => 'SELECT * FROM profiles',
					'access_denied' => true,
					'link' => 'zabbix.php?action=userprofile.edit'
				]
			],
			// #54 User role update.
			[
				[
					'db' => 'SELECT * FROM role',
					'access_denied' => true,
					'link' => 'zabbix.php?action=userrole.edit&roleid=2'
				]
			],
			// #55 User role create.
			[
				[
					'db' => 'SELECT * FROM role',
					'access_denied' => true,
					'link' => 'zabbix.php?action=userrole.edit'
				]
			],
			// #56 User API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'access_denied' => true,
					'link' => 'zabbix.php?action=user.token.list',
					'case' => 'token create'
				]
			],
			// #57 User API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'access_denied' => true,
					'link' => 'zabbix.php?action=user.token.list',
					'case' => 'token update'
				]
			],
			// #58 Scheduled report create.
			[
				[
					'db' => 'SELECT * FROM report',
					'access_denied' => true,
					'link' => 'zabbix.php?action=scheduledreport.edit'
				]
			],
			// #59 Scheduled report update.
			[
				[
					'db' => 'SELECT * FROM report',
					'access_denied' => true,
					'link' => 'zabbix.php?action=scheduledreport.edit&reportid=3'
				]
			],
			// #60 Connector create.
			[
				[
					'db' => 'SELECT * FROM connector',
					'access_denied' => true,
					'link' => 'zabbix.php?action=connector.list',
					'case' => 'popup create'
				]
			],
			// #61 Connector update.
			[
				[
					'db' => 'SELECT * FROM connector',
					'access_denied' => true,
					'link' => 'zabbix.php?action=connector.list',
					'case' => 'popup update'
				]
			],
		];
	}

	/**
	 * @dataProvider getElementRemoveData
	 */
	public function testCSRF_ElementRemove($data) {
		$old_hash = CDBHelper::getHash($data['db']);
		$this->page->login()->open($data['link'])->waitUntilReady();

		if (array_key_exists('case', $data)) {
			switch ($data['case']) {
				case 'token create':
					$this->query('button:Create API token')->waitUntilClickable()->one()->click();
					$element = COverlayDialogElement::find()->waitUntilReady()->one();
					$fill_data = ['Name' => 'test', 'User' => 'admin-zabbix', 'Expires at' => '2037-12-31 00:00:00'];

					if (strpos($data['link'], 'user')) {
						unset($fill_data['User']);
					}

					$element->asForm()->fill($fill_data);
					break;

				case 'token update':
				case 'proxy update':
					$name = ($data['case'] === 'token update') ? self::UPDATE_API_TOKEN : $data['proxy'];
					$this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible()
							->findRow('Name', $name)->getColumn('Name')->query('tag:a')->waitUntilClickable()->one()->click();
					$element = COverlayDialogElement::find()->waitUntilReady()->one();
					break;

				case 'proxy create':
					$this->query('button:Create proxy')->waitUntilClickable()->one()->click();
					$element = COverlayDialogElement::find()->waitUntilReady()->one();
					$element->asForm()->fill(['Proxy name' => 'test remove sid']);
					break;

				case 'popup create':
					$this->query('xpath://div[@class="header-controls"]//button')->one()->waitUntilClickable()->click();
					$element = COverlayDialogElement::find()->waitUntilReady()->one();
					break;

				case 'popup update':
					$this->query('xpath://table[@class="list-table"]//tr[1]/td[2]/a')->one()->waitUntilClickable()->click();
					$element = COverlayDialogElement::find()->waitUntilReady()->one();
					break;
			}
		}
		else {
			$element = $this;
		}

		$element->query('xpath:.//input[@name="_csrf_token"]')->one()->delete();

		$query = ($this->query('button:Update')->exists())
			? 'button:Update'
			: 'xpath://button[text()="Add" and @type="submit"] | //div[@class="overlay-dialogue-footer"]//button[text()="Add"]';
		$this->query($query)->waitUntilClickable()->one()->click();

		if (CTestArrayHelper::get($data, 'access_denied')) {
			$message = 'Access denied';
			$details = 'You are logged in as "Admin". You have no permissions to access this page.';
		}
		elseif (CTestArrayHelper::get($data, 'server_error')) {
			$message = 'Unexpected server error.';
			$details = null;
		}
		else {
			$message = 'Zabbix has received an incorrect request.';
			$details = 'Operation cannot be performed due to unauthorized request.';
		}

		$this->assertMessage(TEST_BAD, $message, $details);

		if (CTestArrayHelper::get($data, 'incorrect_request')) {
			$this->query('button:Go to "Dashboards"')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}

		$this->assertEquals($old_hash, CDBHelper::getHash($data['db']));

		if (CTestArrayHelper::get($data, 'case')) {
			$element->close();
		}
	}
}
