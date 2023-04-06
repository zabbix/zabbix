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
class testPermissionsWithoutCSRF extends CWebTest {

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
			'name' => 'api_token_update',
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
					'link' => 'sysmaps.php?form=Create+map',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #1 Map update.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=update&sysmapid=3',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #2 Host group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=hostgroup.edit'
				]
			],
			// #3 Host group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=hostgroup.edit&groupid=50012'
				]
			],
			// #4 Template group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=templategroup.edit'
				]
			],
			// #5 Template group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=templategroup.edit&groupid=14'
				]
			],
			// #6 Template create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=create',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #7 Template update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=update&templateid=10169',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #8 Host create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=host.edit'
				]
			],
			// #9 Host update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=host.edit&hostid=99062'
				]
			],
			// #10 Item update.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=update&hostid=50011&itemid=99086&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #11 Item create.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=create&hostid=50011&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #12 Trigger update.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?form=update&triggerid=100034&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #13 Trigger create.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?hostid=50011&form=create&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #14 Graph update.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?form=update&graphid=700026&filter_hostids%5B0%5D=99202&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #15 Graph create.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?hostid=50011&form=create&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #16 Discovery rule update.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=update&itemid=99107&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #17 Discovery rule create.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=create&hostid=99202&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #18 Web scenario update.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #19 Web scenario create.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=create&hostid=50001&context=host',
					'error' => [
						'message' => 'Zabbix has received an incorrect request.',
						'details' => 'Operation cannot be performed due to unauthorized request.'
					]
				]
			],
			// #20 Maintenance create.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'link' => 'zabbix.php?action=maintenance.list',
					'overlay' => 'create'
				]
			],
			// #21 Maintenance update.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'link' => 'zabbix.php?action=maintenance.list',
					'overlay' => 'update'
				]
			],
			// #22 Action create.
			[
				[
					'db' => 'SELECT * FROM actions',
					'link' => 'zabbix.php?action=action.list&eventsource=0',
					'overlay' => 'create'
				]
			],
			// #23 Action update.
			[
				[
					'db' => 'SELECT * FROM actions',
					'link' => 'zabbix.php?action=action.list&eventsource=0',
					'overlay' => 'update'
				]
			],
			// #24 Event correlation create.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'link' => 'zabbix.php?action=correlation.edit',
					'return_button' => true
				]
			],
			// #25 Event correlation update.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'link' => 'zabbix.php?correlationid=99002&action=correlation.edit',
					'return_button' => true
				]
			],
			// #26 Discovery create.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'zabbix.php?action=discovery.edit',
					'return_button' => true
				]
			],
			// #27 Discovery update.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'zabbix.php?action=discovery.edit&druleid=5',
					'return_button' => true
				]
			],
			// #28 GUI update.
			[
				[
					'db' => 'SELECT * FROM config',
					'link' => 'zabbix.php?action=gui.edit',
					'return_button' => true
				]
			],
			// #29 Autoregistration update.
			[
				[
					'db' => 'SELECT * FROM autoreg_host',
					'link' => 'zabbix.php?action=autoreg.edit',
					'return_button' => true
				]
			],
			// #30 Housekeeping update.
			[
				[
					'db' => 'SELECT * FROM housekeeper',
					'link' => 'zabbix.php?action=housekeeping.edit',
					'return_button' => true
				]
			],
			// #31 Image update.
			[
				[
					'db' => 'SELECT * FROM images',
					'link' => 'zabbix.php?action=image.edit&imageid=1',
					'return_button' => true
				]
			],
			// #32 Image create.
			[
				[
					'db' => 'SELECT * FROM images',
					'link' => 'zabbix.php?action=image.edit&imagetype=1',
					'return_button' => true
				]
			],
			// #33 Icon map update.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'link' => 'zabbix.php?action=iconmap.edit&iconmapid=101',
					'return_button' => true
				]
			],
			// #34 Icon map create.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'link' => 'zabbix.php?action=iconmap.edit',
					'return_button' => true
				]
			],
			// #35 Regular expression update.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'link' => 'zabbix.php?action=regex.edit&regexid=20',
					'return_button' => true
				]
			],
			// #36 Regular expression create.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'link' => 'zabbix.php?action=regex.edit',
					'return_button' => true
				]
			],
			// #37 Macros update.
			[
				[
					'db' => 'SELECT * FROM globalmacro',
					'link' => 'zabbix.php?action=macros.edit',
					'return_button' => true
				]
			],
			// #38 Trigger displaying options update.
			[
				[
					'db' => 'SELECT * FROM config',
					'link' => 'zabbix.php?action=trigdisplay.edit',
					'return_button' => true
				]
			],
			// #39 API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=token.list',
					'overlay' => 'create'
				]
			],
			// #40 API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=token.list',
					'overlay' => 'update'
				]
			],
			// #41 Other parameters update.
			[
				[
					'db' => 'SELECT * FROM config',
					'link' => 'zabbix.php?action=miscconfig.edit',
					'return_button' => true
				]
			],
			// #42 Proxy update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=proxy.list',
					'overlay' => 'update'
				]
			],
			// #43 Proxy create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=proxy.list',
					'overlay' => 'create'
				]
			],
			// #44 Authentication update.
			[
				[
					'db' => 'SELECT * FROM config',
					'link' => 'zabbix.php?action=authentication.edit',
					'return_button' => true
				]
			],
			//#45 User group update.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'link' => 'zabbix.php?action=usergroup.edit&usrgrpid=7',
					'return_button' => true
				]
			],
			// #46 User group create.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'link' => 'zabbix.php?action=usergroup.edit',
					'return_button' => true
				]
			],
			// #47 User update.
			[
				[
					'db' => 'SELECT * FROM users',
					'link' => 'zabbix.php?action=user.edit&userid=1',
					'return_button' => true
				]
			],
			// #48 User create.
			[
				[
					'db' => 'SELECT * FROM users',
					'link' => 'zabbix.php?action=user.edit',
					'return_button' => true
				]
			],
			// #49 Media update.
			[
				[
					'db' => 'SELECT * FROM media',
					'link' => 'zabbix.php?action=mediatype.edit&mediatypeid=1',
					'return_button' => true
				]
			],
			// #50 create.
			[
				[
					'db' => 'SELECT * FROM media',
					'link' => 'zabbix.php?action=mediatype.edit',
					'return_button' => true
				]
			],
			// #51 Script update.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'link' => 'zabbix.php?action=script.edit&scriptid=1',
					'return_button' => true
				]
			],
			// #52 Script create.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'link' => 'zabbix.php?action=script.edit',
					'return_button' => true
				]
			],
			// #53 User profile update.
			[
				[
					'db' => 'SELECT * FROM profiles',
					'link' => 'zabbix.php?action=userprofile.edit',
					'return_button' => true
				]
			],
			// #54 User role update.
			[
				[
					'db' => 'SELECT * FROM role',
					'link' => 'zabbix.php?action=userrole.edit&roleid=2',
					'return_button' => true
				]
			],
			// #55 User role create.
			[
				[
					'db' => 'SELECT * FROM role',
					'link' => 'zabbix.php?action=userrole.edit',
					'return_button' => true
				]
			],
			// #56 User API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=user.token.list',
					'overlay' => 'create'
				]
			],
			// #57 User API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=user.token.list',
					'overlay' => 'update'
				]
			],
			// #58 Scheduled report create.
			[
				[
					'db' => 'SELECT * FROM report',
					'link' => 'zabbix.php?action=scheduledreport.edit',
					'return_button' => true
				]
			],
			// #59 Scheduled report update.
			[
				[
					'db' => 'SELECT * FROM report',
					'link' => 'zabbix.php?action=scheduledreport.edit&reportid=3',
					'return_button' => true
				]
			],
			// #60 Connector create.
			[
				[
					'db' => 'SELECT * FROM connector',
					'link' => 'zabbix.php?action=connector.list',
					'overlay' => 'create'
				]
			],
			// #61 Connector update.
			[
				[
					'db' => 'SELECT * FROM connector',
					'link' => 'zabbix.php?action=connector.list',
					'overlay' => 'update'
				]
			],
			// #62 Problem update.
			[
				[
					'db' => 'SELECT * FROM problem, events, acknowledges',
					'link' => 'zabbix.php?&action=problem.view&filter_set=1',
					'overlay' => 'problem'
				]
			]
		];
	}

	/**
	 * @dataProvider getElementRemoveData
	 */
	public function testPermissionsWithoutCSRF_ElementRemove($data) {
		$old_hash = CDBHelper::getHash($data['db']);
		$this->page->login()->open($data['link'])->waitUntilReady();

		// If form opens in the overlay dialog - open that dialog.
		if (array_key_exists('overlay', $data)) {
			switch ($data['overlay']) {
				case 'create':
					$clickable_element = $this->query("xpath://div[@class=\"header-controls\"]//button");
					break;

				case 'update':
					$clickable_element = $this->query('xpath://table[@class="list-table"]//tr[1]/td[2]/a');
					break;

				case 'problem':
					$clickable_element = $this->query('xpath://table[@class="list-table"]//tr[1]//a[text()="Update"]') ;
					break;
			}

			$clickable_element->one()->waitUntilClickable()->click();
			$element = COverlayDialogElement::find()->waitUntilReady()->one();
		}
		else {
			$element = $this;
		}

		// Delete hidden input with CSRF token.
		$element->query('xpath:.//input[@name="_csrf_token"]')->one()->delete();

		// Submit Update or Create form.
		$update_button = 'xpath://div[contains(@class, "tfoot-buttons")]//button[text()="Update"] |'.
			'//div[@class="overlay-dialogue-footer"]//button[text()="Update"] | //div[@class="form-actions"]//button[text()="Update"]';
		$add_button = 'xpath://button[text()="Add" and @type="submit"] | '.
				' //div[@class="overlay-dialogue-footer"]//button[text()="Add"]';
		$query = ($this->query($update_button)->exists())
			? $update_button
			: $add_button;
		$this->query($query)->waitUntilClickable()->one()->click();

		// Check the error message depending on case.
		$error = CTestArrayHelper::get($data, 'error',
			[
				'message' => 'Access denied',
				'details' => 'You are logged in as "Admin". You have no permissions to access this page.'
			]
		);
		$this->assertMessage(TEST_BAD, $error['message'], $error['details']);

		// Check 'Go to "Dashboards"' button, if it exists, or make sure that it is absent.
		$return_button = 'Go to "Dashboards"';
		if (CTestArrayHelper::get($data, 'return_button')) {
			$this->query('button', $return_button)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}
		else {
			$this->assertFalse($this->query('button', $return_button)->exists());
		}

		// Compare db hashes to check that form didn't make any changes.
		$this->assertEquals($old_hash, CDBHelper::getHash($data['db']));

		// Close overlay if it is necessary.
		if (CTestArrayHelper::get($data, 'overlay')) {
			$element->close();
		}
	}
}
