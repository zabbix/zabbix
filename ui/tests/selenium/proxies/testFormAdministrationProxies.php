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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxy host form.
 *
 * @dataSource Proxies
 *
 * @onBefore prepareProxyData
 *
 * @backup proxy
 */
class testFormAdministrationProxies extends CWebTest {

	private $sql = 'SELECT * FROM proxy ORDER BY proxyid';

	protected static $update_proxy = 'Active proxy for update';
	const CHANGE_ACTIVE_PROXY = 'Active proxy for refresh cancel simple update';
	const CHANGE_PASSIVE_PROXY = 'Passive proxy for refresh cancel simple update';
	const DELETE_PROXY_WITH_HOSTS = 'Proxy_2 for filter';
	const DELETE_PROXY_WITH_DISCOVERY_RULE = 'Delete Proxy used in Network discovery rule';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Function used to create proxies.
	 */
	public function prepareProxyData() {
		CDataHelper::call('proxy.create', [
			[
				'name' => self::$update_proxy,
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
				'description' => 'Description for update',
				'tls_connect' => 1,
				'tls_accept'=> 1
			],
			[
				'name' => self::CHANGE_ACTIVE_PROXY,
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
				'description' => 'Active description for refresh',
				'tls_connect' => 1,
				'tls_accept'=> 7,
				'tls_psk_identity' => 'activerefreshpsk',
				'tls_psk' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
				'tls_issuer' => 'activerefreshpsk',
				'tls_subject' => 'activerefreshpsk',
				'allowed_addresses' => '127.0.1.2',
				'custom_timeouts' => 1,
				'timeout_zabbix_agent' => '300s',
				'timeout_simple_check' => '300s',
				'timeout_snmp_agent' => '300s',
				'timeout_external_check' => '300s',
				'timeout_db_monitor' => '300s',
				'timeout_http_agent' => '300s',
				'timeout_ssh_agent' => '300s',
				'timeout_telnet_agent' => '300s',
				'timeout_script' => '300s',
				'timeout_browser' => '300s'
			],
			[
				'name' => self::CHANGE_PASSIVE_PROXY,
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.9.9.9',
				'port' => 10051,
				'description' => '_Passive description for refresh',
				'tls_connect' => 4,
				'tls_accept'=> 1,
				'tls_issuer' => 'passiverefreshpsk',
				'tls_subject' => 'passiverefreshpsk',
				'custom_timeouts' => 1,
				'timeout_zabbix_agent' => '300s',
				'timeout_simple_check' => '300s',
				'timeout_snmp_agent' => '300s',
				'timeout_external_check' => '300s',
				'timeout_db_monitor' => '300s',
				'timeout_http_agent' => '300s',
				'timeout_ssh_agent' => '300s',
				'timeout_telnet_agent' => '300s',
				'timeout_script' => '300s',
				'timeout_browser' => '300s'
			]
		]);
	}

	public function getLayoutData() {
		return [
			// Data for Active proxy mode and Connections to proxy - No encryption.
			[
				[
					'operating_mode' => 'Active',
					'check_layout' => true,
					'check_alert' => true,
					'Connections to proxy' => 'No encryption',
					'settings' => [
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity'  => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity'  => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						]
					]
				]
			],
			[
				[
					'operating_mode' => 'Active',
					'Connections to proxy' => 'PSK',
					'settings' => [
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => false],
								'tls_psk' => ['visible' => true, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity'  => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => false],
								'tls_psk' => ['visible' => true, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => false],
								'tls_psk' => ['visible' => true, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						]
					]
				]
			],
			[
				[
					'operating_mode' => 'Active',
					'Connections to proxy' => 'Certificate',
					'settings' => [
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => false],
								'tls_subject' => ['visible' => true, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity'  => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => true
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => false,
								'id:tls_accept_psk' => false,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => false],
								'tls_subject' => ['visible' => true, 'enabled' => false]
							]
						],
						[
							'Connections from proxy' => [
								'id:tls_accept_none' => true,
								'id:tls_accept_psk' => true,
								'id:tls_accept_certificate' => false
							],
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => false],
								'tls_subject' => ['visible' => true, 'enabled' => false]
							]
						]
					]
				]
			],
			// Data for Passive proxy mode.
			[
				[
					'operating_mode' => 'Passive',
					'check_layout' => true,
					'Connections from proxy' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => false,
						'id:tls_accept_certificate' => false
					],
					'settings' => [
						[
							'Connections to proxy' => 'No encryption',
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'PSK',
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'Certificate',
							'inputs' => [
								'tls_psk_identity'  => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						]
					]
				]
			],
			[
				[
					'operating_mode' => 'Passive',
					'Connections from proxy' => [
						'id:tls_accept_none' => false,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => false
					],
					'settings' => [
						[
							'Connections to proxy' => 'No encryption',
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => false],
								'tls_psk' => ['visible' => true, 'enabled' => false],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'PSK',
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => false, 'enabled' => false],
								'tls_subject' => ['visible' => false, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'Certificate',
							'inputs' => [
								'tls_psk_identity'  => ['visible' => true, 'enabled' => false],
								'tls_psk' => ['visible' => true, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						]
					]
				]
			],
			[
				[
					'operating_mode' => 'Passive',
					'Connections from proxy' => [
						'id:tls_accept_none' => false,
						'id:tls_accept_psk' => false,
						'id:tls_accept_certificate' => true
					],
					'settings' => [
						[
							'Connections to proxy' => 'No encryption',
							'inputs' => [
								'tls_psk_identity' => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => false],
								'tls_subject' => ['visible' => true, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'PSK',
							'inputs' => [
								'tls_psk_identity' => ['visible' => true, 'enabled' => true],
								'tls_psk' => ['visible' => true, 'enabled' => true],
								'tls_issuer' => ['visible' => true, 'enabled' => false],
								'tls_subject' => ['visible' => true, 'enabled' => false]
							]
						],
						[
							'Connections to proxy' => 'Certificate',
							'inputs' => [
								'tls_psk_identity'  => ['visible' => false, 'enabled' => false],
								'tls_psk' => ['visible' => false, 'enabled' => false],
								'tls_issuer' => ['visible' => true, 'enabled' => true],
								'tls_subject' => ['visible' => true, 'enabled' => true]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormAdministrationProxies_Layout($data) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('button:Create proxy')->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New proxy', $dialog->getTitle());
		$form = $this->query('id:proxy-form')->asForm()->one();

		// Following checks should be performed only in first case, because form is the same in all cases.
		if (CTestArrayHelper::get($data, 'check_layout')) {
			if ($data['operating_mode'] === 'Active') {
				// Check fields lengths.
				$field_maxlengths = [
					'Proxy name' => 128,
					'id:local_address' => 255,
					'id:local_port' => 64,
					'Proxy address' => 255,
					'Description' => 65535,
					'PSK identity' => 128,
					'PSK' => 512,
					'Issuer' => 1024,
					'Subject' => 1024
				];

				foreach ($field_maxlengths as $name => $maxlength) {
					$field = $form->getField($name);
					$this->assertEquals(($name === 'id:local_port' ? '10051' : ''), $field->getValue());
					$this->assertEquals($maxlength, $field->getAttribute('maxlength'));
				}

				// Check that "Address for active agents" field and that it is displayed only when a proxy group is selected.
				$address_field = $form->getField('Address for active agents');
				$this->assertFalse($address_field->isDisplayed());
				$form->fill(['Proxy group' => 'Online proxy group']);
				$this->assertTrue($address_field->isDisplayed());

				$this->assertEquals(['Address', 'Port'], $address_field->asTable()->getHeadersText());
				$this->assertEquals(['Proxy name', 'Address for active agents'], $form->getRequiredLabels());

				// Check timeouts.
				$form->selectTab('Timeouts');

				// Check Timeouts for item types radio buttons.
				$this->assertEquals(['Global', 'Override'],
						$form->getField('Timeouts for item types')->asSegmentedRadio()->getLabels()->asText()
				);

				// Global timeouts link is clickable.
				$this->assertTrue($form->query('link:Global timeouts')->one()->isClickable());

				// Default value for proxy should be Global.
				$form->checkValue(['Timeouts for item types' => 'Global']);

				// Available timeouts list.
				$timeouts = ['Zabbix agent', 'Simple check', 'SNMP agent', 'External check', 'Database monitor',
						'HTTP agent', 'SSH agent', 'TELNET agent', 'Script', 'Browser'
				];

				// Every timeout has mandatory status.
				$this->assertEquals($timeouts, $form->getRequiredLabels());

				// Check timeouts fields values and status after switching between Global and Override.
				foreach (['Override' => true, 'Global' => false] as $timeout => $enabled) {
					$form->fill(['Timeouts for item types' => $timeout]);

					foreach ($timeouts as $timeout) {
						$default = ($timeout === 'Browser') ? '60s' : '3s';
						$field = $form->getField($timeout);
						$this->assertEquals($default, $field->getValue());
						$this->assertEquals(255, $field->getAttribute('maxlength'));
						$this->assertEquals($enabled, $field->isClickable());
					}
				}

				// Check form tabs.
				$this->assertEquals(['Proxy', 'Encryption', 'Timeouts'], $form->getTabs());
				$form->checkValue(['Proxy mode' => 'Active']);
			}
			else {
				$form->getField('Proxy mode')->asSegmentedRadio()->select('Passive');

				// Check that 'Proxy address' is disappeared.
				$this->assertFalse($form->getField('Proxy address')->isVisible());

				// Check Interface field for passive scenario.
				$selector = 'xpath:.//div[@class="table-forms-separator"]/table';
				$this->assertTrue($dialog->query($selector)->one()->isEnabled());
				$this->assertEquals(['Address', 'Port'], $dialog->query($selector)->one()->asTable()->getHeadersText());

				// Check interface fields values.
				foreach (['address' => '127.0.0.1', 'port' => '10051'] as $id => $value) {
					$this->assertEquals($value, $dialog->query('id', $id)->one()->getValue());
				}

				// Check interface fields lengths.
				foreach (['address' => 255, 'port' => 64] as $id => $length) {
					$this->assertEquals($length, $dialog->query('id', $id)->one()->getAttribute('maxlength'));
				}
			}

			// Check Encryption tabs.
			$form->selectTab('Encryption');
			$form->checkValue([
				'Connections to proxy' => 'No encryption',
				'id:tls_accept_none' => true,
				'id:tls_accept_psk' => false,
				'id:tls_accept_certificate' => false
			]);
		}
		else {
			if ($data['operating_mode'] === 'Passive') {
				$form->fill(['Proxy mode' => 'Passive']);
			}

			$form->selectTab('Encryption');
		}

		// Condition for checking connection encryption fields.
		$condition = ($data['operating_mode'] === 'Active')
			? ($data['Connections to proxy'] !== 'No encryption')
			: ($data['Connections from proxy'] !== [
					'id:tls_accept_none' => true,
					'id:tls_accept_psk' => false,
					'id:tls_accept_certificate' => false
				]);

		$checked_proxy = ($data['operating_mode'] === 'Active') ? 'Active' : 'Passive';
		$opposite_proxy = ($data['operating_mode'] === 'Active') ? 'Passive' : 'Active';

		$this->switchAndAssertEncryption($data, $form, $condition, $checked_proxy, $opposite_proxy);

		if (CTestArrayHelper::get($data, 'check_alert')) {
			// Check alert when trying to refresh page.
			$this->page->refresh();
			// TODO: temporarily commented out due webdriver issue, alert is not displayed while leaving page during test execution
//			$this->page->acceptAlert();

			// Check that after accepting alert user remained on Proxies page.
			$this->page->assertHeader('Proxies');
		}
		else {
			$dialog->close();
		}
	}

	/**
	 * Function for switching different combinations of connections and proxy encryption
	 * and checking fields visibility and editability.
	 *
	 * @param array           $data            given data provider
	 * @param CFormElement    $form            proxy configuration form
	 * @param boolean         $condition       defines if opposite proxy needs to be selected
	 * @param string          $checked_proxy   name of proxy which layout is checked
	 * @param string          $opposite_proxy  name of proxy which is opposite to checked proxy
	 */
	private function switchAndAssertEncryption($data, $form, $condition, $checked_proxy, $opposite_proxy) {
		if ($condition) {
			$form->selectTab('Proxy');
			$form->fill(['Proxy mode' => $opposite_proxy]);
			$form->selectTab('Encryption');

			if ($checked_proxy === 'Active') {
				$form->getField('Connections to proxy')->fill($data['Connections to proxy']);
			}
			else {
				$form->fill($data['Connections from proxy']);
			}

			$form->selectTab('Proxy');
			$form->fill(['Proxy mode' => $checked_proxy]);
			$form->selectTab('Encryption');
			$form->invalidate();
		}

		foreach ($data['settings'] as $setting) {
			if ($checked_proxy === 'Active') {
				$form->fill($setting['Connections from proxy']);
			}
			else {
				$form->getField('Connections to proxy')->fill($setting['Connections to proxy']);
			}

			foreach ($setting['inputs'] as $id => $value) {
				$input = $form->query('id', $id)->one(false);
				$this->assertTrue($input->isVisible($value['visible']));
				$this->assertTrue($input->isEnabled($value['enabled']));
			}
		}
	}

	public function getCreateProxyData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Minimal fields proxy 123'
					]
				]
			]
		];
	}

	public function getUpdateProxyData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Active proxy 1'
					],
					'error' => 'Proxy "Active proxy 1" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '@#$%^&*()_+'
					],
					'error' => 'Invalid parameter "/1/name": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '😀'
					],
					'error' => 'Invalid parameter "/1/name": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '{$USERMACRO}'
					],
					'error' => 'Invalid parameter "/1/name": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '{#LLDMACRO}'
					],
					'error' => 'Invalid parameter "/1/name": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'кириллица'
					],
					'error' => 'Invalid parameter "/1/name": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty local address',
						'Proxy group' => 'Online proxy group'
					],
					'error' => 'Incorrect value for field "Address for active agents: Address": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Invalid symbols in local address',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '!@#$%^&*()_+'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Space in local address - IP',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0 .0.1'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Space in local address - DNS',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => 'zab bix'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'User macro in local address',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '{$MACRO}'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Built-in macro in local address',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '{TIME}'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'LLD macro in local address',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '{#MACRO}'
					],
					'error' => 'Invalid parameter "/1/local_address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => ''
					],
					'error' => 'Incorrect value for field "Address for active agents: Port": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Non-numeric value in local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => '3k'
					],
					'error' => 'Invalid parameter "/1/local_port": an integer is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Too high number in local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => '65536'
					],
					'error' => 'Invalid parameter "/1/local_port": value must be one of 0-65535.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Negative number in local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => '-1'
					],
					'error' => 'Invalid parameter "/1/local_port": value must be one of 0-65535.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Built in macro in local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => '{TIME}'
					],
					'error' => 'Invalid parameter "/1/local_port": an integer is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'LLD macro in local address port',
						'Proxy group' => 'Online proxy group',
						'id:local_address' => '127.0.0.1',
						'id:local_port' => '{#PORT}'
					],
					'error' => 'Invalid parameter "/1/local_port": an integer is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty IP address',
						'Proxy mode' => 'Passive',
						'id:address' => ''
					],
					'error' => 'Incorrect value for field "Interface: Address": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty port',
						'Proxy mode' => 'Passive',
						'id:port' => ''
					],
					'error' => 'Incorrect value for field "Interface: Port": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Wrong Address',
						'Proxy mode' => 'Passive',
						'id:address' => '🙂🙂🙂😀😀😀'
					],
					'error' => 'Invalid parameter "/1/address": an IP or DNS is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Wrong Port',
						'Proxy mode' => 'Passive',
						'id:port' => 65536
					],
					'error' => 'Invalid parameter "/1/port": value must be one of 0-65535.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Active empty Encryption',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_none' => false,
						'id:tls_accept_psk' => false,
						'id:tls_accept_certificate' => false
					],
					'error' => 'Incorrect value for field "Connections from proxy": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Active empty PSK',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'test'
					],
					'error' => 'Incorrect value for field "PSK": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Active empty PSK identity',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb'
					],
					'error' => 'Incorrect value for field "PSK identity": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Short PSK',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'test',
						'PSK' => '41b4d07b27a8efdcc15d474'
					],
					'error' => 'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Uueven PSK',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'test',
						'PSK' => '3713AD479CE5B2FA06EB308D7AE96408A70ADE7F630191B6035E13B6DD779B68303FA08E38E38E38E38E38E38E38E38E38E37'
					],
					'error' => 'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'All timeout fields empty',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '',
						'Simple check' => '',
						'SNMP agent' => '',
						'External check' => '',
						'Database monitor' => '',
						'HTTP agent' => '',
						'SSH agent' => '',
						'TELNET agent' => '',
						'Script' => '',
						'Browser' => ''
					],
					'error' => [
						'Incorrect value for field "timeout_zabbix_agent": cannot be empty.',
						'Incorrect value for field "timeout_simple_check": cannot be empty.',
						'Incorrect value for field "timeout_snmp_agent": cannot be empty.',
						'Incorrect value for field "timeout_external_check": cannot be empty.',
						'Incorrect value for field "timeout_db_monitor": cannot be empty.',
						'Incorrect value for field "timeout_http_agent": cannot be empty.',
						'Incorrect value for field "timeout_ssh_agent": cannot be empty.',
						'Incorrect value for field "timeout_telnet_agent": cannot be empty.',
						'Incorrect value for field "timeout_script": cannot be empty.',
						'Incorrect value for field "timeout_browser": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Zabbix agent timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_zabbix_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Zabbix agent timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_zabbix_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Zabbix agent timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_zabbix_agent": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Zabbix agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_zabbix_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Simple check timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Simple check' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_simple_check": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Simple check timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Simple check' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_simple_check": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Simple check timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Simple check' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_simple_check": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Simple check timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Simple check' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_simple_check": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SNMP agent timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SNMP agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_snmp_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SNMP agent timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SNMP agent' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_snmp_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SNMP agent timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SNMP agent' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_snmp_agent": a number is too large'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SNMP agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SNMP agent' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_snmp_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'External check timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'External check' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_external_check": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'External check timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'External check' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_external_check": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'External check timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'External check' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_external_check": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'External check timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'External check' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_external_check": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Database monitor timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Database monitor' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_db_monitor": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Database monitor timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Database monitor' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_db_monitor": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Database monitor timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Database monitor' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_db_monitor": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Database monitor timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Database monitor' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_db_monitor": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'HTTP agent timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'HTTP agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_http_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'HTTP agent timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'HTTP agent' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_http_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'HTTP agent timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'HTTP agent' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_http_agent": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'HTTP agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'HTTP agent' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_http_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SSH agent timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SSH agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_ssh_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SSH agent timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SSH agent' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_ssh_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SSH agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SSH agent' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_ssh_agent": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'SSH agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SSH agent' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_ssh_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'TELNET agent timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'TELNET agent' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_telnet_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'TELNET agent timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'TELNET agent' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_telnet_agent": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'TELNET agent timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'TELNET agent' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_telnet_agent": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'TELNET agent timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'TELNET agent' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_telnet_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Script timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Script' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_script": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Script timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Script' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_script": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Script timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Script' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_script": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Script timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Script' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_script": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Browser timeout - 0',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Browser' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout_browser": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Browser timeout - 601',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Browser' => '601'
					],
					'error' => [
						'Invalid parameter "/1/timeout_browser": value must be one of 1-600.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Browser timeout - too large',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Browser' => '1234567890123456'
					],
					'error' => [
						'Invalid parameter "/1/timeout_browser": a number is too large.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Browser timeout - test',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Browser' => 'test'
					],
					'error' => [
						'Invalid parameter "/1/timeout_browser": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Timeouts field - global macros',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'TELNET agent' => '{PROXY.NAME}'
					],
					'error' => [
						'Invalid parameter "/1/timeout_telnet_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Timeouts field - LLD macros',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '{#MACROS}'
					],
					'error' => [
						'Invalid parameter "/1/timeout_zabbix_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Timeouts field - XSS',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SSH agent' => '<script>alert("hi!");</script>'
					],
					'error' => [
						'Invalid parameter "/1/timeout_ssh_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Timeouts field - unicode',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'SNMP agent' => '☺♥²©™"\''
					],
					'error' => [
						'Invalid parameter "/1/timeout_snmp_agent": a time unit is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Timeouts field - error order',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'External check' => 'test',
						'TELNET agent' => '800'
					],
					'error' => [
						'Invalid parameter "/1/timeout_external_check": a time unit is expected.'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All timeout fields - 300',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '300',
						'Simple check' => '300',
						'SNMP agent' => '300',
						'External check' => '300',
						'Database monitor' => '300',
						'HTTP agent' => '300',
						'SSH agent' => '300',
						'TELNET agent' => '300',
						'Script' => '300',
						'Browser' => '300'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All timeout fields - macros',
						'Proxy mode' => 'Active'
					],
					'timeout_fields' => [
						'Zabbix agent' => '{$MACROS}',
						'Simple check' => '{$MACROS}',
						'SNMP agent' => '{$MACROS}',
						'External check' => '{$MACROS}',
						'Database monitor' => '{$MACROS}',
						'HTTP agent' => '{$MACROS}',
						'SSH agent' => '{$MACROS}',
						'TELNET agent' => '{$MACROS}',
						'Script' => '{$MACROS}',
						'Browser' => '{$MACROS}'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All fields Passive proxy No encryption',
						'Proxy mode' => 'Passive',
						'Proxy group' => 'Offline group',
						'id:local_address' => 'zabbix.com',
						'id:local_port' => '65535'
					],
					'encryption_fields' => [
						'Connections to proxy' => 'No encryption'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => '-All fields Active proxy 123',
						'Proxy mode' => 'Active',
						'Proxy group' => 'Offline group',
						'id:local_address' => '192.168.0.1',
						'id:local_port' => '{$PORT}',
						'Proxy address' => '120.9.9.9',
						'Description' => "~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//"
					],
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => 'test test',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => 'test test',
						'Subject' => 'test test'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'IPv6',
						'Proxy mode' => 'Passive',
						'id:address' => '::1',
						'id:port' => 999
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Utf8 mb4',
						'Proxy mode' => 'Active',
						'Description' => '😊️❤️❤️😉'
					],
					'encryption_fields' => [
						'id:tls_accept_none' => false,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => '🙂🙂🙂😀😀😀',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => '😁😉😉',
						'Subject' => '💫😊😊🙃🙃'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All fields Passive proxy PSK',
						'Proxy mode' => 'Passive',
						'id:address' => '192.168.2.3',
						'id:port' => 65535
					],
					'encryption_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'test',
						'PSK' => '581F7BA5C7D5EB29A4AB80E25E4239A771AAFD989E68E923389685F16258F8A6B39900E38E38E38E38E38E38E38E38E38E38'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => '      Selenium test proxy with spaces    ',
						'Proxy group' => 'Offline group',
						'id:local_address' => '   127.0.0.1   ',
						'id:local_port' => '   11111   ',
						'Description' => '       Test description with trailing spaces        ',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_certificate' => true,
						'Issuer' => '            test          ',
						'Subject' => '      test        '
					],
					'trim' => true
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'IPv6 _2',
						'Proxy mode' => 'Passive',
						'id:address' => 'fe80::1ff:fe23:4567:890a',
						'id:port' => 0
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Proxy with multiline description',
						'Proxy mode' => 'Active',
						'Description' => "Test multiline description".
							"\n".
							"next line ~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//"
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'LLD macros in encryption fields',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => '{#LLDMACRO}',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => '{#LLDMACRO}',
						'Subject' => '{#LLDMACRO}'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'User macros in encryption fields',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => '{$USERMACRO}',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => '{$USERMACRO}',
						'Subject' => '{$USERMACRO}'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All fields Passive proxy Certificate',
						'Proxy mode' => 'Passive',
						'id:address' => '192.168.3.99'
					],
					'encryption_fields' => [
						'Connections to proxy' => 'Certificate',
						'Issuer' => 'test',
						'Subject' => 'test'
					],
					'check_PSK_button' => true
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Special symbols in encryption fields',
						'Proxy mode' => 'Active'
					],
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => "~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//",
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => '~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//',
						'Subject' => '~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateProxyData
	 * @dataProvider getUpdateProxyData
	 *
	 * @backupOnce hosts
	 */
	public function testFormAdministrationProxies_Create($data) {
		$this->checkForm($data);
	}

	/**
	 * @dataProvider getUpdateProxyData
	 */
	public function testFormAdministrationProxies_Update($data) {
		$this->checkForm($data, true);
	}

	/**
	 * Function for testing create or update proxy form.
	 *
	 * @param array      $data      given data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	private function checkForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();

		if ($update) {
			$this->query('link', self::$update_proxy)->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('button:Create proxy')->one()->waitUntilClickable()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-form')->asForm()->one();
		$form->fill($data['proxy_fields']);

		if (CTestArrayHelper::get($data, 'encryption_fields')) {
			$form->selectTab('Encryption');
			$form->invalidate();

			// Fill PSK to get 'Change PSK' button enabled.
			if (CTestArrayHelper::get($data, 'encryption_fields.Connections to proxy') === 'PSK') {
				$form->fill(['Connections to proxy' => 'PSK']);
			}

			$button = $form->query('button:Change PSK')->one(false);
			if ($update && $button->isEnabled()) {
				$button->click();
				$form->fill($data['encryption_fields']);
			}
			elseif (CTestArrayHelper::get($data, 'check_PSK_button')) {
				$this->assertFalse($button->isEnabled());
				$form->fill($data['encryption_fields']);
				$this->assertFalse($button->isEnabled());
			}
			else {
				$form->fill($data['encryption_fields']);
			}
		}

		if (CTestArrayHelper::get($data, 'timeout_fields')) {
			$form->selectTab('Timeouts');
			$form->invalidate();

			$form->fill(['Timeouts for item types' => 'Override']);
			$form->fill($data['timeout_fields']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, ($update ? 'Cannot update proxy' : 'Cannot add proxy'), $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, $update ? 'Proxy updated' : 'Proxy added');

			// Remove leading and trailing spaces from data for assertion.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$trimmed_fields = [
					'proxy_fields' => ['Proxy name', 'Description', 'id:local_address', 'id:local_port'],
					'encryption_fields' => ['Issuer', 'Subject']
				];

				foreach ($trimmed_fields as $tab => $field) {
					foreach ($field as $i => $value) {
						$data[$tab][$value] = trim($data[$tab][$value]);
					}
				}
			}

			// Check values in frontend form.
			$this->query('link', $data['proxy_fields']['Proxy name'])->waitUntilClickable()->one()->click();
			$form = $dialog->asForm();

			// Remove PSK fields' values for checking.
			if (CTestArrayHelper::get($data, 'encryption_fields.PSK identity')) {
				unset($data['encryption_fields']['PSK identity']);
				unset($data['encryption_fields']['PSK']);

				// Check change PSK button if PSK fields were filled.
				$form->selectTab('Encryption');
				$form->query('button:Change PSK')->waitUntilClickable()->one()->click();

				foreach (['PSK identity', 'PSK'] as $field) {
					$this->assertEquals('', $form->getField($field)->waitUntilVisible()->getText());
				}
			}

			$form->checkValue(array_merge($data['proxy_fields'], CTestArrayHelper::get($data, 'encryption_fields', [])));
			$dialog->close();

			// Check DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM proxy WHERE name ='.
					zbx_dbstr($data['proxy_fields']['Proxy name']))
			);

			if ($update) {
				self::$update_proxy = $data['proxy_fields']['Proxy name'];
			}
		}
	}

	public function getActivePassiveProxyData() {
		return [
			[
				[
					'proxy' => self::CHANGE_ACTIVE_PROXY
				]
			],
			[
				[
					'proxy' => self::CHANGE_PASSIVE_PROXY
				]
			],
			[
				[
					'proxy' => 'Active proxy to delete',
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
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
					'proxy' => 'Passive proxy 3',
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
	 * @dataProvider getActivePassiveProxyData
	 */
	public function testFormAdministrationProxies_RefreshConfiguration($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-form')->asForm()->one();
		$old_fields = $form->getFields()->asValues();

		// Check alert when trying to refresh configuration.
		$dialog->query('button:Refresh configuration')->waitUntilClickable()->one()->click();
		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('Refresh configuration of the selected proxy?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Request created successfully');
		$dialog->close();

		// Check that form fields did not change.
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->invalidate();
		$this->assertEquals($old_fields, $form->getFields()->asValues());

		// Check that DB hash did not change.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		$dialog->close();
	}

	/**
	 * @dataProvider getActivePassiveProxyData
	 */
	public function testFormAdministrationProxies_Clone($data) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-form')->asForm()->one();
		$original_fields = $form->getFields()->asValues();

		// Get original passive proxy interface fields.
		if (str_contains($data['proxy'], 'Passive')) {
			$original_fields = $this->getInterfaceValues($dialog, $original_fields);
		}

		$new_name = 'Cloned proxy '.microtime();

		// Clone proxy.
		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$form->invalidate();
		$form->fill(['Proxy name' => $new_name]);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Proxy added');
		// The next message 'Proxy updated' may not update on time.
		CMessageElement::find()->one()->close();

		// Check cloned proxy form fields.
		$this->query('link', $new_name)->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->invalidate();
		$original_fields['Proxy name'] = $new_name;
		$cloned_fields = $form->getFields()->asValues();

		// Get cloned passive proxy interface fields.
		if (str_contains($data['proxy'], 'Passive')) {
			$cloned_fields = $this->getInterfaceValues($dialog, $cloned_fields);
		}

		$this->assertEquals($original_fields, $cloned_fields);

		// Check "Encryption" tabs functionality.
		if (CTestArrayHelper::get($data, 'encryption_fields')) {
			$form->selectTab('Encryption');
			$form->fill($data['encryption_fields']);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Proxy updated');
		}
		else {
			$dialog->close();
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
		foreach (['address', 'port'] as $id) {
			$fields[$id] = $dialog->query('id', $id)->one()->getValue();
		}

		return $fields;
	}

	/**
	 * @dataProvider getActivePassiveProxyData
	 */
	public function testFormAdministrationProxies_SimpleUpdate($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();

		// Check that user remained on Proxies page.
		$this->page->waitUntilReady();
		$this->page->assertHeader('Proxies');
		$this->assertMessage(TEST_GOOD, 'Proxy updated');

		// Check name remained in frontend table.
		$this->assertTrue($this->query('link', $data['proxy'])->exists());

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function getCancelData() {
		return [
			[
				[
					'action' => 'Create'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			],
			[
				[
					'action' => 'Refresh configuration'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormAdministrationProxies_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);

		$fields = [
			'proxy_fields' => [
				'Proxy name' => 'Proxy for cancel',
				'Proxy mode' => 'Passive',
				'Proxy group' => 'Group without proxies',
				'id:local_address' => '127.0.0.1',
				'id:local_port' => 33333,
				'id:address' => '192.8.8.8',
				'id:port' => 222,
				'Description' => 'Description for cancel'
			],
			'encryption_fields' => [
				'Connections to proxy' => 'Certificate',
				'Issuer' => 'Issuer for cancel',
				'Subject' => 'Subject for cancel'
			]
		];

		$this->page->login()->open('zabbix.php?action=proxy.list');

		if ($data['action'] === 'Create') {
			$this->query('button:Create proxy')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link', self::$update_proxy)->one()->waitUntilClickable()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($data['action'] === 'Delete' || $data['action'] === 'Refresh configuration') {
			$dialog->query('button', $data['action'])->waitUntilClickable()->one()->click();
			$this->assertTrue($this->page->isAlertPresent());
			$this->page->dismissAlert();
			$dialog->close();
		}
		else {
			if ($data['action'] === 'Clone') {
				$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
				$dialog->invalidate();
			}

			$form = $dialog->asForm();
			$form->fill($fields['proxy_fields']);
			$form->selectTab('Encryption');
			$form->fill($fields['encryption_fields']);
			$dialog->query('button:Cancel')->one()->waitUntilClickable()->click();
			$dialog->ensureNotPresent();
		}

		// Check that user remained on Proxies page.
		$this->page->assertTitle('Configuration of proxies');

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function getProxyDeleteData() {
		return array_merge($this->getActivePassiveProxyData(), [
			[
				[
					'expected' => TEST_BAD,
					'proxy' => self::DELETE_PROXY_WITH_HOSTS,
					'error' => "Host \"Host_2 with proxy\" is monitored by proxy \"Proxy_2 for filter\"."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy' => self::DELETE_PROXY_WITH_DISCOVERY_RULE,
					'error' => "Proxy \"Delete Proxy used in Network discovery rule\" is used by discovery rule ".
						"\"Discovery rule for proxy delete test\"."
				]
			]
		]);
	}

	/**
	 * @dataProvider getProxyDeleteData
	 */
	public function testFormAdministrationProxies_Delete($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('link', $data['proxy'])->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();

		// Check alert.
		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('Delete selected proxy?', $this->page->getAlertText());
		$this->page->acceptAlert();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot delete proxy', $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));

			// Close dialog.
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, 'Proxy deleted');

			// Check DB.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hosts WHERE host='.zbx_dbstr($data['proxy'])));
		}

		// Check frontend table.
		$this->assertEquals(array_key_exists('expected', $data), $this->query('link', $data['proxy'])->exists());

		// Check that user redirected on Proxies page.
		$this->page->assertTitle('Configuration of proxies');
	}
}
