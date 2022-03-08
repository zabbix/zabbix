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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxy host form.
 *
 * @backup hosts
 */
class testFormAdministrationGeneralProxies extends CWebTest {

	private $sql = 'SELECT * FROM hosts ORDER BY hostid';

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function getLayoutData() {
		return [
			// Data for Active proxy mode and Connections to proxy - No encryption.
			[
				[
					'mode' => 'Active',
					'check_layout' => true,
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
					'mode' => 'Active',
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
					'mode' => 'Active',
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
					'mode' => 'Passive',
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
					'mode' => 'Passive',
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
					'mode' => 'Passive',
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
	public function testFormAdministrationGeneralProxies_Layout($data) {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of proxies');
		$this->page->assertHeader('Proxies');

		$this->query('button:Create proxy')->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New proxy', $dialog->getTitle());
		$form = $this->query('id:proxy-form')->asForm()->one();

		// Following checks should be performed only in first case, because form is the same in all cases.
		if (CTestArrayHelper::get($data, 'check_layout')) {
			if ($data['mode'] === 'Active') {
				// Check fileds lengths.
				foreach (['Proxy name' => 128, 'Proxy address' => 255,
//					'Description' => 65535,
					'PSK identity' => 128, 'PSK' => 512, 'Issuer' => 1024, 'Subject' => 1024] as $field_name => $maxlength) {
					$field = $form->getField($field_name);
					$this->assertEquals('', $field->getValue());
					$this->assertEquals($maxlength, $field->getAttribute('maxlength'));
				}

				// Check form tabs.
				$this->assertEquals(['Proxy', 'Encryption'], $form->getTabs());
				$form->checkValue(['Proxy mode' => 'Active']);
			}
			else{
				$form->getField('Proxy mode')->asSegmentedRadio()->select('Passive');

				// Check that 'Proxy address' is disappeared.
				$this->assertFalse($form->getField('Proxy address')->isVisible());

				// Check Interface field for passive scenario.
				$selector = 'xpath://div[@class="table-forms-separator"]/table';
				$this->assertTrue($dialog->query($selector)->one()->isEnabled());
				$this->assertEquals(['IP address', 'DNS name', 'Connect to', 'Port'],
						$dialog->query($selector)->one()->asTable()->getHeadersText()
				);

				// Check interface fields values.
				foreach (['ip' => '127.0.0.1', 'dns' => 'localhost', 'port' => '10051'] as $id => $value) {
					$this->assertEquals($value, $dialog->query('id', $id)->one()->getValue());
				}
				$this->assertEquals('IP', $dialog->query('id:useip')->one()->asSegmentedRadio()->getValue());

				// Check interface fields lenghts.
				foreach (['ip' => 64, 'dns' => 255, 'port' => 64] as $id => $length) {
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
			if ($data['mode'] === 'Passive') {
				$form->fill(['Proxy mode' => 'Passive']);
			}

			$form->selectTab('Encryption');
		}

		// Condition for checking connection encryption fields.
		$condition = ($data['mode'] === 'Active')
			? ($data['Connections to proxy'] !== 'No encryption')
			: ($data['Connections from proxy'] !== [
				'id:tls_accept_none' => true,
				'id:tls_accept_psk' => false,
				'id:tls_accept_certificate' => false
			]);

		$checked_proxy = ($data['mode'] === 'Active') ? 'Active' : 'Passive';
		$opposite_proxy = ($data['mode'] === 'Active') ? 'Passive' : 'Active';

		$this->switchAndAssertEncryption($data, $form, $condition, $checked_proxy, $opposite_proxy);


		$dialog->close();

		// Check alert when trying to refresh page.
//		$this->page->refresh();
//		$this->assertTrue($this->page->isAlertPresent());
//		var_dump($this->page->getAlertText());
//		$this->assertEquals('', $this->page->getAlertText());
//		$this->page->acceptAlert();

		// Check that after accepting alert user remained on Proxies page.
		$this->page->assertTitle('Configuration of proxies');
		$this->page->assertHeader('Proxies');
	}

	/**
	 * Function for switching different combinations of connections and proxy encryption
	 * and checking fields visibility and editability.
	 *
	 * @param array           $data            given data provider
	 * @param CFormElement    $form            proxy configuration form
	 * @param boolean         $codition        defines if opposite proxy needs to be selected
	 * @param string          $checked_proxy   name of proxy which layout is checked
	 * @param string          $opposite_proxy  name of proxy which is opposite to checked proxy
	 */
	private function switchAndAssertEncryption($data, $form, $codition, $checked_proxy, $opposite_proxy) {
		if ($codition) {
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
				$this->assertTrue($form->query('id', $id)->one(false)->isVisible($value['visible']));
				$this->assertTrue($form->query('id', $id)->one(false)->isEnabled($value['enabled']));
			}
		}
	}

	public function getCreateData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [],
					'error' => 'Incorrect value for field "host": cannot be empty.'
				]
			],
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
					'error' => 'Incorrect value for field "host": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty IP address',
						'Proxy mode' => 'Passive',
						'id:ip' => ''
					],
					'error' => 'Incorrect value for field "IP address": cannot be empty.'
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
					'error' => 'Incorrect value for field "Port": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Empty DNS',
						'Proxy mode' => 'Passive',
						'id:dns' => '',
						'id:useip' => 'DNS'
					],
					'error' => 'Incorrect value for field "DNS name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Wrong IP',
						'Proxy mode' => 'Passive',
						'id:ip' => 'test'
					],
					'error' => 'Invalid parameter "/1/interface/ip": an IP address is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '@#$%^&*()_+'
					],
					'error' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '😀'
					],
					'error' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '{$USERMACRO}'
					],
					'error' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => '{#LLDMACRO}'
					],
					'error' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'кириллица'
					],
					'error' => 'Invalid parameter "/1/host": invalid host name.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Active empty PSK identity',
						'Proxy mode' => 'Active',
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
						'Proxy mode' => 'Active',
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
					],
					'error' => 'Incorrect value for field "PSK identity": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Wrong PSK',
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
						'Proxy name' => 'Short PSK',
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
						'Proxy name' => 'Short letters PSK',
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'test',
						'PSK' => 'qwertyuiopa'
					],
					'error' => 'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'proxy_fields' => [
						'Proxy name' => 'Wrong letters PSK',
					],
					'encryption_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'test',
						'PSK' => 'qwertyuiopasdfghjkloaqcvfrtybnaqs'
					],
					'error' => 'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Minimal fields proxy 123'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'IPv6',
						'Proxy mode' => 'Passive',
						'id:ip' => '::1'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'IPv6 _2',
						'Proxy mode' => 'Passive',
						'id:ip' => 'fe80::1ff:fe23:4567:890a'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => '-All fields Active proxy',
						'Proxy mode' => 'Active',
						'Proxy address' => '120.9.9.9',
						'Description' => "~`!@#$%^&*()_+-=”№;:?Х[]{}|\\|//"
					],
					'encryption_fields' => [
						'id:tls_accept_none' => true,
						'id:tls_accept_psk' => true,
						'id:tls_accept_certificate' => true,
						'PSK identity' => 'test',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb',
						'Issuer' => 'test',
						'Subject' => 'test'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'Utf8 mb4',
						'Description' => '😊️❤️❤️😉'
					],
					'encryption_fields' => [
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
						'id:ip' => '192.168.2.3',
						'id:dns' => 'mytesthost',
						'id:useip' => 'DNS',
					],
					'encryption_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'test',
						'PSK' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => 'All fields Passive proxy Certificate',
						'Proxy mode' => 'Passive',
						'id:ip' => '192.168.3.99',
						'id:dns' => 'mytesthost',
						'id:useip' => 'IP',
					],
					'encryption_fields' => [
						'Connections to proxy' => 'Certificate',
						'Issuer' => 'test',
						'Subject' => '💫test'
					]
				]
			],
			[
				[
					'proxy_fields' => [
						'Proxy name' => '      Selenium test proxy with spaces    ',
						'Description' => '       Test description with trailling spaces        '
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
						'Proxy name' => 'Proxy with multiline description',
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
						'Proxy name' => 'Special symbos in encryption fields',
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
	 * @dataProvider getCreateData
	 */
	public function testFormAdministrationGeneralProxies_Create($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->query('button:Create proxy')->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:proxy-form')->asForm()->one();
		$form->fill($data['proxy_fields']);

		if (CTestArrayHelper::get($data, 'encryption_fields')) {
			$form->selectTab('Encryption');
			$form->invalidate();
			$form->fill($data['encryption_fields']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, 'Proxy created');

			// Remove leading and trailing spaces from data for assertion.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$trimmed_fields = [
					'proxy_fields' => ['Proxy name', 'Description'],
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
			}

			// Check change PSK button if PSK fields were filled.
			if (CTestArrayHelper::get($data, 'encryption_fields.PSK identity')) {
				$form->selectTab('Encryption');
				$form->query('button:Change PSK')->waitUntilClickable()->one()->click();

				foreach (['PSK identity', 'PSK'] as $field) {
					$this->assertEquals('', $form->getField($field)->waitUntilVisible()->getText());
				}
			}

			$form->checkValue(array_merge($data['proxy_fields'], CTestArrayHelper::get($data, 'encryption_fields', [])));
			$dialog->close();

			// Check DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM hosts WHERE host='.zbx_dbstr($data['proxy_fields']['Proxy name'])));
		}
	}
}
