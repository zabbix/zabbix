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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

/**
 * @backup token, connector
 *
 * @dataSource ScheduledReports, Proxies, Services, Sla
 *
 * @onBefore prepareData
 */
class testPermissionsWithoutCSRF extends CWebTest {

	const INCORRECT_REQUEST = [
		'message' => 'Zabbix has received an incorrect request.',
		'details' => 'Operation cannot be performed due to unauthorized request.'
	];

	const ACCESS_DENIED = [
		'message' => 'Access denied',
		'details' => 'You are logged in as "Admin". You have no permissions to access this page.'
	];

	const ACCESS_DENIED_WITHOUT_HTML = '{"error":{"title":"Access denied","messages":["You are logged in as \"Admin\".'.
			' You have no permissions to access this page.","If you think this message is wrong, please consult your'.
			' administrators about getting the necessary permissions."]}}';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareData() {
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

		// Create event correlation.
		CDataHelper::call('correlation.create', [
			[
				'name' => 'Event correlation for element remove',
				'filter' => [
					'evaltype' => 0,
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'element remove'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			]
		]);
	}

	public static function getElementRemoveData() {
		return [
			// #0 Map create.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=Create+map',
					'incorrect_request' => true
				]
			],
			// #1 Map update.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=update&sysmapid=1',
					'incorrect_request' => true
				]
			],
			// #2 Host group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=popup&popup=hostgroup.edit',
					'fields' => [
						'id:name' => 'CSRF validation host group create'
					]
				]
			],
			// #3 Host group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=popup&popup=hostgroup.edit&groupid=50012'
				]
			],
			// #4 Template group create.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=popup&popup=templategroup.edit',
					'fields' => [
						'id:name' => 'CSRF validation template group create'
					]
				]
			],
			// #5 Template group update.
			[
				[
					'db' => 'SELECT * FROM hstgrp',
					'link' => 'zabbix.php?action=popup&popup=templategroup.edit&groupid=14'
				]
			],
			// #6 Template create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=template.list',
					'overlay' => 'create',
					'fields' => [
						'id:template_name' => 'CSRF validation template create',
						'xpath://div[@id="template_groups_"]/..' => 'Templates'
					]
				]
			],
			// #7 Template update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=template.list',
					'overlay' => 'update',
					'fields' => [
						'id:template_name' => 'CSRF validation template update',
						'xpath://div[@id="template_groups_"]/..' => 'templates'
					]
				]
			],
			// #8 Host create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=host.list',
					'overlay' => 'create_host',
					'fields' => [
						'id:host' => 'CSRF validation host create',
						'xpath://div[@id="groups_"]/..' => 'Zabbix servers'
					]
				]
			],
			// #9 Host update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=popup&popup=host.edit&hostid=99062',
					'fields' => [
						'id:host' => 'CSRF validation host update',
						'xpath://div[@id="groups_"]/..' => 'Zabbix servers'
					]
				]
			],
			// #10 Item update.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=50011&context=host',
					'overlay' => 'item_update'
				]
			],
			// #11 Item create.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids[0]=50011&context=host',
					'overlay' => 'create',
					'fields' => [
						'id:name' => 'CSRF validation item create',
						'id:key' => 'csrf.test.key'
					]
				]
			],
			// #12 Trigger update.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids[0]=50011',
					'overlay' => 'trigger_update'
				]
			],
			// #13 Trigger create.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'zabbix.php?action=trigger.list&filter_set=1&context=host&filter_hostids[0]=50011',
					'overlay' => 'create',
					'fields' => [
						'id:name' => 'CSRF test name',
						'id:expression' => 'last(/1_Host_to_check_Monitoring_Overview/trap[1])>0'
					]
				]
			],
			// #14 Graph update.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B0%5D=700008&context=host',
					'overlay' => 'update'
				]
			],
			// #15 Graph create.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B0%5D=50011&context=host',
					'overlay' => 'create'
				]
			],
			// #16 Discovery rule update.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'host_discovery.php?form=update&itemid=400430&context=host',
					'incorrect_request' => true
				]
			],
			// #17 Discovery rule create.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'host_discovery.php?form=create&hostid=50001&context=host',
					'incorrect_request' => true
				]
			],
			// #18 Web scenario update.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host',
					'incorrect_request' => true
				]
			],
			// #19 Web scenario create.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=create&hostid=50001&context=host',
					'incorrect_request' => true
				]
			],
			// #20 Maintenance create.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'link' => 'zabbix.php?action=maintenance.list',
					'overlay' => 'create',
					'fields' => [
						'id:name' => 'CSRF maintenance test name',
						'xpath://div[@id="groupids_"]/..' => 'Zabbix servers'
					],
					'secondary_dialog' => [
						'field' => 'id:timeperiods',
						'fill' => [
							'Period type' => 'One time only'
						]
					]
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
					'link' => 'zabbix.php?action=action.list&eventsource=3',
					'overlay' => 'update'
				]
			],
			// #24 Event correlation create.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'link' => 'zabbix.php?action=correlation.list',
					'overlay' => 'create'
				]
			],
			// #25 Event correlation update.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'link' => 'zabbix.php?action=correlation.list',
					'overlay' => 'update'
				]
			],
			// #26 Discovery create.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'zabbix.php?action=discovery.list',
					'overlay' => 'create'
				]
			],
			// #27 Discovery update.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'zabbix.php?action=discovery.list',
					'overlay' => 'update'
				]
			],
			// #28 GUI update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=gui.edit',
					'return_button' => true
				]
			],
			// #29 Autoregistration update.
			[
				[
					'db' => 'SELECT * FROM autoreg_host',
					'link' => 'zabbix.php?action=autoreg.edit'
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
					'link' => 'zabbix.php?action=image.edit&imageid=1'
				]
			],
			/* Commented till problem with file upload solved.
			// Image create.
			[
				[
					'db' => 'SELECT * FROM images',
					'link' => 'zabbix.php?action=image.edit&imagetype=1',
					'fields' => [
						'id:name' => 'CSRF image test name',
						'id:image' => PHPUNIT_BASEDIR.'/ui/tests/images/image.png'
					]
				]
			],*/
			// #32 Icon map update.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'link' => 'zabbix.php?action=iconmap.edit&iconmapid=101'
				]
			],
			// #33 Icon map create.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'link' => 'zabbix.php?action=iconmap.edit',
					'fields' => [
						'id:name' => 'CSRF icon test name',
						'id:mappings_0_expression' => 'CSRF_test'
					]
				]
			],
			// #34 Regular expression update.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'link' => 'zabbix.php?action=regex.edit&regexpid=2'
				]
			],
			// #35 Regular expression create.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'link' => 'zabbix.php?action=regex.edit',
					'fields' => [
						'id:name' => 'CSRF regex test name',
						'id:expressions_0_expression' => 'abc'
					]
				]
			],
			// #36 Macros update.
			[
				[
					'db' => 'SELECT * FROM globalmacro',
					'link' => 'zabbix.php?action=macros.edit'
				]
			],
			// #37 Trigger displaying options update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=trigdisplay.edit',
					'return_button' => true
				]
			],
			// #38 API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=token.list',
					'overlay' => 'create'
				]
			],
			// #39 API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=token.list',
					'overlay' => 'update'
				]
			],
			// #40 Other parameters update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=miscconfig.edit',
					'return_button' => true
				]
			],
			// #41 Proxy update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=proxy.list',
					'overlay' => 'update',
					'fields' => [
						'id:name' => 'CSRF validation proxy update'
					]
				]
			],
			// #42 Proxy create.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'zabbix.php?action=proxy.list',
					'overlay' => 'create',
					'fields' => [
						'id:name' => 'CSRF validation proxy create'
					]
				]
			],
			// #43 Authentication update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=authentication.edit',
					'return_button' => true
				]
			],
			// #44 User group update.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'link' => 'zabbix.php?action=usergroup.edit&usrgrpid=7',
					'return_button' => true
				]
			],
			// #45 User group create.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'link' => 'zabbix.php?action=usergroup.edit',
					'return_button' => true
				]
			],
			// #46 User update.
			[
				[
					'db' => 'SELECT * FROM users',
					'link' => 'zabbix.php?action=user.edit&userid=1',
					'return_button' => true
				]
			],
			// #47 User create.
			[
				[
					'db' => 'SELECT * FROM users',
					'link' => 'zabbix.php?action=user.edit',
					'return_button' => true
				]
			],
			// #48 Media update.
			[
				[
					'db' => 'SELECT * FROM media',
					'link' => 'zabbix.php?action=mediatype.list',
					'overlay' => 'update'
				]
			],
			// #49 Media create.
			[
				[
					'db' => 'SELECT * FROM media',
					'link' => 'zabbix.php?action=mediatype.list',
					'overlay' => 'create'
				]
			],
			// #50 Script update.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'link' => 'zabbix.php?action=script.list',
					'overlay' => 'update'
				]
			],
			// #51 Script create.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'link' => 'zabbix.php?action=script.list',
					'overlay' => 'create'
				]
			],
			// #52 User profile update.
			[
				[
					'db' => 'SELECT * FROM profiles',
					'link' => 'zabbix.php?action=userprofile.edit',
					'return_button' => true
				]
			],
			// #53 User role update.
			[
				[
					'db' => 'SELECT * FROM role',
					'link' => 'zabbix.php?action=userrole.edit&roleid=2',
					'return_button' => true
				]
			],
			// #54 User role create.
			[
				[
					'db' => 'SELECT * FROM role',
					'link' => 'zabbix.php?action=userrole.edit',
					'return_button' => true
				]
			],
			// #55 User API token create.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=user.token.list',
					'overlay' => 'create'
				]
			],
			// #56 User API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'link' => 'zabbix.php?action=user.token.list',
					'overlay' => 'update'
				]
			],
			// #57 Scheduled report create.
			[
				[
					'db' => 'SELECT * FROM report',
					'link' => 'zabbix.php?action=scheduledreport.edit',
					'return_button' => true
				]
			],
			// #58 Scheduled report update.
			[
				[
					'db' => 'SELECT * FROM report',
					'link' => 'zabbix.php?action=scheduledreport.edit&reportid=3',
					'return_button' => true
				]
			],
			// #59 Connector create.
			[
				[
					'db' => 'SELECT * FROM connector',
					'link' => 'zabbix.php?action=connector.list',
					'overlay' => 'create'
				]
			],
			// #60 Connector update.
			[
				[
					'db' => 'SELECT * FROM connector',
					'link' => 'zabbix.php?action=connector.list',
					'overlay' => 'update'
				]
			],
			// #61 Problem update.
			[
				[
					'db' => 'SELECT * FROM problem, events, acknowledges',
					'link' => 'zabbix.php?&action=problem.view&filter_set=1',
					'overlay' => 'problem'
				]
			],
			// #62 Service create.
			[
				[
					'db' => 'SELECT * FROM services',
					'link' => 'zabbix.php?action=service.list.edit',
					'overlay' => 'create',
					'fields' => [
						'id:name' => 'CSRF service create'
					]
				]
			],
			// #63 Service update.
			[
				[
					'db' => 'SELECT * FROM services',
					'link' => 'zabbix.php?action=service.list.edit',
					'overlay' => 'service'
				]
			],
			// #64 SLA create.
			[
				[
					'db' => 'SELECT * FROM sla',
					'link' => 'zabbix.php?action=sla.list',
					'overlay' => 'create'
				]
			],
			// #65 SLA update.
			[
				[
					'db' => 'SELECT * FROM sla',
					'link' => 'zabbix.php?action=sla.list',
					'overlay' => 'update'
				]
			],
			// #66 Geomap update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=geomaps.edit'
				]
			],
			// #67 Module update.
			[
				[
					'db' => 'SELECT * FROM module',
					'link' => 'zabbix.php?action=module.list',
					'overlay' => 'update'
				]
			],
			// #68 Audit log administration update.
			[
				[
					'db' => 'SELECT * FROM module',
					'link' => 'zabbix.php?action=audit.settings.edit',
					'return_button' => true
				]
			],
			// #69 Timeout options update.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?action=timeouts.edit',
					'return_button' => true
				]
			]
		];
	}

	/**
	 * Test function for checking the "POST" form, but with the deleted CSRF token element.
	 *
	 * @dataProvider getElementRemoveData
	 *
	 * TODO: remove ignoreBrowserErrors after DEV-4233
	 * @ignoreBrowserErrors
	 */
	public function testPermissionsWithoutCSRF_ElementRemove($data) {
		$old_hash = CDBHelper::getHash($data['db']);
		$this->page->login()->open($data['link'])->waitUntilReady();

		// If form opens in the overlay dialog - open that dialog.
		if (array_key_exists('overlay', $data)) {
			$selectors = [
				'create' => "//div[@class=\"header-controls\"]//button",
				'create_host' => "//div[@class=\"header-controls\"]//button[@class=\"js-create-host\"]",
				'update' => "//table[@class=\"list-table\"]//tr[1]/td[2]/a",
				'trigger_update' => "//table[@class=\"list-table\"]//tr[1]/td[4]/a",
				'item_update' => "//table[@class=\"list-table\"]//tr[1]/td[3]/a",
				'problem' => '//table[@class="list-table"]//tr[1]//a[text()="Update"]',
				'service' => '//table[@class="list-table"]//tr[1]//button[@title="Edit"]'
			];

			$this->query('xpath', $selectors[$data['overlay']])->one()->waitUntilClickable()->click();
			$element = COverlayDialogElement::find()->waitUntilReady()->one();
		}
		else {
			$element = $this;
		}

		// Mandatory fields in views with inline validation should be filled in, to stop it from preventing form submission.
		if (array_key_exists('fields', $data)) {
			foreach ($data['fields'] as $field => $value) {
				$this->query($field)->one()->detect()->fill($value);
			}
		}

		// Fill in mandatory fields in a secondary form if it contains fields that are required for form submission.
		if (array_key_exists('secondary_dialog', $data)) {
			$this->query($data['secondary_dialog']['field'])->one()->query('button:Add')->one()->click();

			$secondary_form = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$secondary_form->fill($data['secondary_dialog']['fill']);
			$secondary_form->submit();
			$secondary_form->waitUntilNotVisible();
		}

		// Delete hidden input with CSRF token.
		$element->query('xpath:.//input[@name="_csrf_token"]')->one()->delete();

		// Submit Update or Create form.
		$update_button = 'xpath://div[contains(@class, "tfoot-buttons")]//button[text()="Update"] |'.
				'//div[@class="overlay-dialogue-footer"]//button[text()="Update"] |'.
				' //div[contains(@class, "form-actions")]//button[text()="Update"]';
		$add_button = 'xpath://button[text()="Add" and @type="submit"] | '.
				' //div[@class="overlay-dialogue-footer"]//button[text()="Add"]';
		$query = ($this->query($update_button)->exists()) ? $update_button : $add_button;
		$this->query($query)->waitUntilClickable()->one()->click();

		// Check the error message depending on case.
		$error = CTestArrayHelper::get($data, 'incorrect_request') ? self::INCORRECT_REQUEST : self::ACCESS_DENIED;
		$this->assertMessage(TEST_BAD, $error['message'], $error['details']);
		$this->checkReturnButton($data);

		// Compare db hashes to check that form didn't make any changes.
		$this->assertEquals($old_hash, CDBHelper::getHash($data['db']));

		// Close overlay if it is necessary.
		if (CTestArrayHelper::get($data, 'overlay')) {
			$element->close();
		}
	}

	public static function getCheckTokenData() {
		return [
			// #0 Correct token. Even if CSRF token is correct, it should not work by direct URL (update LLD).
			[
				[
					'token' => true,
					'token_url' => 'host_discovery.php?form=update&hostid=50001&itemid=400430&context=host',
					'db' => 'SELECT * FROM items',
					'link' => 'host_discovery.php?form=update&hostid=50001&itemid=400430&context=host&name=test'.
						'&description=&key=trap%5B4%5D&type=2&value_type=3&inventory_link=0&trapper_hosts=&units=UNIT'.
						'&lifetime=1&formula=test&evaltype=1&update=Update&_csrf_token=',
					'error' => self::INCORRECT_REQUEST
				]
			],
			// #1 Correct token (update global macros).
			[
				[
					'token' => true,
					'token_url' => 'zabbix.php?action=macros.edit',
					'db' => 'SELECT * FROM globalmacro',
					'link' => 'zabbix.php?macros%5B0%5D%5Bmacro%5D=&macros%5B0%5D%5Bvalue%5D=&macros%5B0%5D%5Btype%5D=0'
						.'&macros%5B0%5D%5Bdescription%5D=&update=Update&_csrf_token=',
					'error' => [
						'message' => 'Page not found',
						'details' => null
					]
				]
			],
			// #2 No token.
			[
				[
					'db' => 'SELECT * FROM report',
					'link' => 'zabbix.php?form_refresh=1&reportid=4&old_dashboardid=1&userid=95'.
							'&name=Report+for+delete&dashboardid=1&period=0&cycle=0&hours=00&minutes=00&weekdays%5B1%5D=1'.
							'&weekdays%5B2%5D=2&weekdays%5B4%5D=4&weekdays%5B8%5D=8&weekdays%5B16%5D=16&weekdays%5B32%5D=32'.
							'&weekdays%5B64%5D=64&active_since=&active_till=&subject=subject+for+report+delete+test'.
							'&message=message+for+report+delete+test&subscriptions%5B0%5D%5Brecipientid%5D=96'.
							'&subscriptions%5B0%5D%5Brecipient_type%5D=0&subscriptions%5B0%5D%5Brecipient_name%5D=user-recipient+of+the+report'.
							'&subscriptions%5B0%5D%5Brecipient_inaccessible%5D=0&subscriptions%5B0%5D%5Bcreatorid%5D=96'.
							'&subscriptions%5B0%5D%5Bcreator_type%5D=0&subscriptions%5B0%5D%5Bcreator_name%5D=user-recipient+of+the+report'.
							'&subscriptions%5B0%5D%5Bcreator_inaccessible%5D=0&subscriptions%5B0%5D%5Bexclude%5D=0'.
							'&subscriptions%5B1%5D%5Brecipientid%5D=7&subscriptions%5B1%5D%5Brecipient_type%5D=1'.
							'&subscriptions%5B1%5D%5Brecipient_name%5D=Zabbix+administrators&subscriptions%5B1%5D%5Brecipient_inaccessible%5D=0'.
							'&subscriptions%5B1%5D%5Bcreatorid%5D=0&subscriptions%5B1%5D%5Bcreator_type%5D=1'.
							'&subscriptions%5B1%5D%5Bcreator_name%5D=Recipient&subscriptions%5B1%5D%5Bcreator_inaccessible%5D=0'.
							'&description=&status=0&action=scheduledreport.update',
					'error' => self::ACCESS_DENIED,
					'return_button' => true
				]
			],
			// #3 Empty token.
			[
				[
					'db' => 'SELECT * FROM role',
					'link' => 'zabbix.php?form_refresh=1&_csrf_token=&roleid=2&name=Admin+role&type=2&ui_monitoring_dashboard=1'.
							'&ui_monitoring_problems=1&ui_monitoring_hosts=1&ui_monitoring_latest_data=1&ui_monitoring_maps=1'.
							'&ui_monitoring_discovery=1&ui_services_services=1&ui_services_sla=1&ui_services_sla_report=1'.
							'&ui_inventory_overview=1&ui_inventory_hosts=1&ui_reports_system_info=0&ui_reports_scheduled_reports=1'.
							'&ui_reports_availability_report=1&ui_reports_top_triggers=1&ui_reports_audit=0&ui_reports_action_log=0'.
							'&ui_reports_notifications=1&ui_configuration_template_groups=1&ui_configuration_host_groups=1'.
							'&ui_configuration_templates=1&ui_configuration_hosts=1&ui_configuration_maintenance=1'.
							'&ui_configuration_event_correlation=0&ui_configuration_discovery=1&ui_configuration_trigger_actions=1'.
							'&ui_configuration_service_actions=1&ui_configuration_discovery_actions=1&ui_configuration_autoregistration_actions=1'.
							'&ui_configuration_internal_actions=1&ui_administration_media_types=0&ui_administration_scripts=0&ui_administration_user_groups=0'.
							'&ui_administration_user_roles=0&ui_administration_users=0&ui_administration_api_tokens=0&ui_administration_authentication=0'.
							'&ui_administration_general=0&ui_administration_audit_log=0&ui_administration_housekeeping=0&ui_administration_proxies=0'.
							'&ui_administration_macros=0&ui_administration_queue=0&ui_default_access=1&service_write_access=1&service_write_tag_tag='.
							'&service_write_tag_value=&service_read_access=1&service_read_tag_tag=&service_read_tag_value='.
							'&modules%5B1%5D=1&modules%5B2%5D=1&modules%5B3%5D=1&modules%5B4%5D=1&modules%5B5%5D=1'.
							'&modules%5B6%5D=1&modules%5B7%5D=1&modules%5B19%5D=1&modules%5B8%5D=1&modules%5B9%5D=1'.
							'&modules%5B10%5D=1&modules%5B11%5D=1&modules%5B12%5D=1&modules%5B13%5D=1&modules%5B14%5D='.
							'1&modules%5B15%5D=1&modules%5B16%5D=1&modules%5B17%5D=1&modules%5B18%5D=1&modules%5B20%5D=1'.
							'&modules%5B21%5D=1&modules%5B22%5D=1&modules%5B23%5D=1&modules%5B24%5D=1&modules_default_access=1'.
							'&api_access=1&api_mode=0&actions_edit_dashboards=1&actions_edit_maps=1&actions_edit_maintenance=1'.
							'&actions_add_problem_comments=1&actions_change_severity=1&actions_acknowledge_problems=1'.
							'&actions_suppress_problems=1&actions_close_problems=1&actions_execute_scripts=1&actions_manage_api_tokens=1'.
							'&actions_manage_scheduled_reports=1&actions_manage_sla=1&actions_invoke_execute_now=1&actions_change_problem_ranking=1'.
							'&actions_default_access=1&action=userrole.update',
					'error' => self::ACCESS_DENIED,
					'return_button' => true
				]
			],
			// #4 Incorrect token.
			[
				[
					'db' => 'SELECT * FROM settings',
					'link' => 'zabbix.php?_csrf_token=12345abcd&tls_accept=1&tls_in_none=1&tls_psk_identity=&tls_psk='.
							'&action=autoreg.update',
					'error' => self::ACCESS_DENIED_WITHOUT_HTML
				]
			]
		];
	}

	/**
	 * Test function for checking the "GET" form (direct url), with the different types of CSRF tokens.
	 *
	 *  TODO: The ignoreBrowserErrors is added to ignore error in  #4 test case when opening page without html
	 *  favicon.ico - Failed to load resource: the server responded with a status of 404 (Not Found)
	 *
	 * @ignoreBrowserErrors
	 * @dataProvider getCheckTokenData
	 */
	public function testPermissionsWithoutCSRF_CheckToken($data) {
		$old_hash = CDBHelper::getHash($data['db']);
		$this->page->login();

		// Get the correct token from form and put it to the direct URL.
		if (CTestArrayHelper::get($data, 'token')) {
			$this->page->open($data['token_url'])->waitUntilReady();
			$this->page->open($data['link'].$this->query('xpath:.//input[@name="_csrf_token"]')
					->one()->getAttribute('value'))->waitUntilReady();
		}
		else {
			$this->page->open($data['link'])->waitUntilReady();
		}

		// Check the error message depending on case.
		if ($data['error'] === self::ACCESS_DENIED_WITHOUT_HTML) {
			$this->assertTrue(strstr($this->page->getSource(), $data['error']) !== false, '"'.$data['error'].'" must exist.');
		}
		else {
			$this->assertMessage(TEST_BAD, $data['error']['message'], $data['error']['details']);
			$this->checkReturnButton($data);
		}

		// Compare db hashes to check that form didn't make any changes.
		$this->assertEquals($old_hash, CDBHelper::getHash($data['db']));
	}

	/**
	 * Check 'Go to "Dashboards"' button, if it exists, or make sure that it is absent.
	 */
	private function checkReturnButton($data) {
		$return_button = 'Go to "Dashboards"';
		if (CTestArrayHelper::get($data, 'return_button')) {
			$this->query('button', $return_button)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}
		else {
			$this->assertFalse($this->query('button', $return_button)->exists());
		}
	}
}
