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
 * @backup media_type
 *
 * @onBefore prepareData
 */
class testFormAdministrationMediaTypes extends CWebTest {

	const URL = 'zabbix.php?action=mediatype.list';

	protected static $mediatype_sql = 'SELECT * FROM media_type ORDER BY mediatypeid';
	protected static $update_mediatypes = [
		'Email' => 'Email',
		'SMS' => 'SMS',
		'Script' => 'Test script'
	];
	protected static $delete_mediatype = 'Email (HTML)';
	protected static $mediatypeids;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function prepareData() {
		// Create media types for Oauth checks.
		$oauth_media_data = [];
		foreach (['Generic SMTP OAuth', 'Gmail OAuth', 'Gmail relay OAuth', 'Office365 OAuth'] as $provider => $name) {
			$oauth_media_data[] = [
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => $provider,
				'name' => $name,
				'smtp_server' => 'test@test.com',
				'smtp_email' => 'zabbix@example.com',
				'smtp_authentication' => 2,
				'redirection_url' => 'https://test/zabbix.php?action=oauth.authorize',
				'client_id' => 'test',
				'client_secret' => 'test',
				'authorization_url' => 'https://test/oauth2/v2/auth?response_type=code&scope=https%3A%2F%2Fmail.kstest.com%2F&access_type=offline&prompt=consent"',
				'token_url' => 'https://zabbixexample.com/token?grant_type=authorization_code',
				'tokens_status' => 3,
				'access_token' => 'test',
				'access_token_updated' => time(),
				'access_expires_in' => '3599',
				'refresh_token' => 'test'
			];
		}
		CDataHelper::call('mediatype.create', $oauth_media_data);
		self::$mediatypeids = CDataHelper::getIds('name');

		CDataHelper::call('mediatype.create', [
			[
				'type' => MEDIA_TYPE_WEBHOOK,
				'name' => 'Switch webhook to script with no params',
				'script' => 'test.sh',
				'parameters' => [
					[
						'name' => 'HTTPProxy'
					],
					[
						'name' => 'Message',
						'value' => '{ALERT.MESSAGE}'
					],
					[
						'name' => 'Subject',
						'value' => '{ALERT.SUBJECT}'
					],
					[
						'name' => 'To',
						'value' => '{ALERT.SENDTO}'
					],
					[
						'name' => 'URL'
					]
				]
			],
			[
				'type' => MEDIA_TYPE_WEBHOOK,
				'name' => 'Switch webhook to script with custom params',
				'script' => 'empty.sh',
				'parameters' => [
					[
						'name' => 'Custom'
					],
					[
						'name' => 'Message',
						'value' => '{ALERT.MESSAGE}'
					]
				]
			],
			[
				'type' => MEDIA_TYPE_EXEC,
				'name' => 'Switch script to webhook with default params',
				'exec_path' => 'script.sh',
				'parameters' => [
					[
						'sortorder' => '0',
						'value' => 'custom parameter'
					]
				]
			],
			[
				'type' => MEDIA_TYPE_EXEC,
				'name' => 'Switch script to webhook with no params',
				'exec_path' => 'script2.sh',
				'parameters' => [
					[
						'sortorder' => '0',
						'value' => 'custom parameter'
					]
				]
			],
			[
				'type' => MEDIA_TYPE_EXEC,
				'name' => 'Switch script to webhook with custom params',
				'exec_path' => 'script3.sh'
			],
			[
				'type' => MEDIA_TYPE_EXEC,
				'name' => 'Test script',
				'exec_path' => 'selenium_test_script.sh',
				'parameters' => [
					[
						'sortorder' => '0',
						'value' => '{ALERT.SUBJECT}'
					]
				]
			]
		]);
	}

	public function testFormAdministrationMediaTypes_GeneralLayout() {
		$this->page->login()->open(self::URL);
		$this->query('button:Create media type')->waitUntilClickable()->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New media type', $overlay->getTitle());

		$form = $overlay->asForm();
		$this->assertEquals(['Media type', 'Message templates', 'Options'], $form->getTabs());

		// Check available media type types.
		$this->assertEquals(['Email', 'SMS', 'Script', 'Webhook'], $form->getField('Type')->getOptions()->asText());

		// Check common fields in Media type and Options tabs. Message templates are covered in separate test.
		$tabs = [
			[
				'tab name' => 'Media type',
				'defaults' => [
					'Name' => '',
					'Type' => 'Email',
					'Description' => '',
					'Enabled' => true
				],
				'maxlength' => [
					'Name' => 100
				],
				'mandatory' => ['Name']
			],
			[
				'tab name' => 'Options',
				'defaults' => [
					'Attempts' => 3,
					'Attempt interval' => '10s'
				],
				'maxlength' => [
					'Attempts' => 3,
					'Attempt interval' => 12
				],
				'mandatory' => ['Attempts', 'Attempt interval']
			]
		];

		foreach ($tabs as $parameters) {
			$this->checkTabFields($form, $parameters);
		}

		// Concurrent sessions is checked separately, as it has a hidden input and cannot be checked via checkValue.
		$concurrent_sessions = $form->getField('Concurrent sessions')->asSegmentedRadio();
		$this->assertEquals('One', $concurrent_sessions->getSelected());

		// Check the maxsessions input element.
		$session_settings = [
			'Custom' => true,
			'One' => false,
			'Unlimited' => false
		];

		foreach ($session_settings as $setting => $visible) {
			$concurrent_sessions->fill($setting);
			$maxsessions = $form->getFieldContainer('Concurrent sessions')->query('id:maxsessions')->one();
			$this->assertTrue($maxsessions->isVisible($visible));

			if ($visible) {
				$this->assertEquals(0, $maxsessions->getValue());
				$this->assertEquals(3, $maxsessions->getAttribute('maxlength'));
			}
		}

		// Check that Add and Cancel buttons are present in the form and that they are clickable.
		$this->assertEquals(2, $overlay->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
		$overlay->close();
	}

	public static function getLayoutMediaTypes() {
		return [
			[
				[
					'type' => 'Email',
					'defaults' => [
						'Email provider' => 'Generic SMTP',
						'SMTP server' => 'mail.example.com',
						'SMTP server port' => 25,
						'Email' => 'zabbix@example.com',
						'SMTP helo' => '',
						'Connection security' => 'None',
						'Authentication' => 'None',
						'Message format' => 'HTML'
					],
					'maxlength' => [
						'SMTP server' => 255,
						'SMTP server port' => 5,
						'SMTP helo' => 255,
						'Email' => 255
					],
					'mandatory' => ['SMTP server', 'Email']
				]
			],
			[
				[
					'type' => 'SMS',
					'defaults' => [
						'GSM modem' => '/dev/ttyS0'
					],
					'maxlength' => [
						'GSM modem' => 255
					],
					'mandatory' => ['GSM modem']
				]
			],
			[
				[
					'type' => 'Script',
					'defaults' => [
						'Script name' => ''
					],
					'maxlength' => [
						'Script name' => 255
					],
					'mandatory' => ['Script name']
				]
			],
			[
				[
					'type' => 'Webhook',
					'defaults' => [
						'Script' => '',
						'Timeout' => '30s',
						'Process tags' => false,
						'Include event menu entry' => false,
						'Menu entry name' => '',
						'Menu entry URL' => ''
					],
					'maxlength' => [
						'Timeout' => 255,
						'Menu entry name' => 255,
						'Menu entry URL' => 2048
					],
					'mandatory' => ['Script', 'Timeout', 'Menu entry name', 'Menu entry URL']
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutMediaTypes
	 */
	public function testFormAdministrationMediaTypes_MediatypeLayout($data) {
		$this->page->login()->open(self::URL);
		$this->query('button:Create media type')->waitUntilClickable()->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();

		$form = $overlay->asForm();
		$form->getField('Type')->fill($data['type']);

		$this->checkTabFields($form, $data);

		switch ($data['type']) {
			case 'Email':
				$connection_security = $form->getField('Connection security');

				// Check that SSL verify peer, SSL verify host, Username and Password fields are not visible.
				$this->assertEquals(0, $this->query('id', ['smtp_verity_peer', 'smtp_verify_host', 'smtp_usename',
					'smtp_password'])->all()->filter(new CElementFilter(CElementFilter::VISIBLE))->count()
				);
				foreach (['STARTTLS', 'SSL/TLS'] as $chosen_security) {
					$connection_security->fill($chosen_security);

					foreach (['SSL verify peer', 'SSL verify host'] as $field_name) {
						$ssl_field = $form->getField($field_name);
						$this->assertTrue($ssl_field->isVisible());
						$this->assertEquals(false, $ssl_field->getValue());
					}
				}

				$form->getField('Authentication')->fill('Username and password');

				foreach (['Username', 'Password'] as $field_name) {
					$auth_field = $form->getField($field_name);
					$this->assertTrue($auth_field->isVisible());
					$this->assertEquals('', $auth_field->getValue());
					$this->assertEquals(255, $auth_field->getAttribute('maxlength'));
				}

				// Check configuration for different email providers.
				$email_providers = $form->getField('Email provider')->getOptions()->asText();
				$this->assertEquals(['Generic SMTP', 'Gmail', 'Gmail relay', 'Office365', 'Office365 relay'], $email_providers);

				$auth = [
					'none' => [
						'defaults' => [
							'Email' => 'zabbix@example.com',
							'Authentication' => 'None',
							'Message format' => 'HTML'
						],
						'maxlength' => [
							'Email' => 255
						],
						'mandatory' => ['Email']
					],
					'password' => [
						'defaults' => [
							'Email' => 'zabbix@example.com',
							'Password' => '',
							'Message format' => 'HTML'
						],
						'maxlength' => [
							'Email' => 255,
							'Password' => 255
						],
						'mandatory' => ['Email', 'Password']
					]
				];

				// Remove "Generic SMTP" from list and check layout for other email providers.
				unset($email_providers[0]);

				foreach ($email_providers as $email_provider) {
					$form->getField('Email provider')->fill($email_provider);

					// Check that certain fields are not displayed depending on email provider.
					$hidden_fields = ['SMTP server', 'SMTP server port', 'SMTP helo', 'Connection security'];

					if (in_array($email_provider, ['Gmail', 'Office365'])) {
						$hidden_fields[] = implode(['Authentication', 'Password']);
					}

					$this->assertEquals([], array_intersect($hidden_fields, $form->getLabels()
							->filter(new CElementFilter(CElementFilter::VISIBLE))->asText())
					);

					if (in_array($email_provider, ['Gmail relay', 'Office365 relay'])) {
						$this->checkTabFields($form, $auth['none']);

						$auth_field = $form->getField('Authentication');
						$this->assertEquals(['None', 'Email and password', 'OAuth'], $auth_field->getLabels()->asText());
						$auth_field->fill('Email and password');

						if ($email_provider === 'Office365 relay') {
							$this->assertFalse($form->getField('OAuth')->isEnabled());
						}
					}

					$this->checkTabFields($form, $auth['password']);
				}

				// Check OAuth form layout.
				$oauth_fields = [
					'generic_smtp' => [
						'defaults' => [
							'Redirection endpoint' => '',
							'Client ID' => '',
							'Client secret' => '',
							'Authorization endpoint' => '',
							'name:authorization_url_parameters[0][name]' => '',
							'name:authorization_url_parameters[0][value]' => '',
							'id:authorization_mode_0' => 'Automatic',
							'id:code' => '',
							'Token endpoint' => '',
							'name:token_url_parameters[0][name]' => '',
							'name:token_url_parameters[0][value]' => ''
						],
						'maxlength' => [
							'Redirection endpoint' => 2048,
							'Client ID' => 255,
							'Client secret' => 255,
							'Authorization endpoint' => 2048,
							'name:authorization_url_parameters[0][name]' => 255,
							'name:authorization_url_parameters[0][value]' => 255,
							'id:code' => 255,
							'Token endpoint' => 2048,
							'name:token_url_parameters[0][name]' => 255,
							'name:token_url_parameters[0][value]' => 255
						],
						'mandatory' => ['Redirection endpoint', 'Client ID', 'Client secret', 'Authorization endpoint', 'Token endpoint']
					],
					'other' => [
						'defaults' => [
							'Redirection endpoint' => '',
							'Client ID' => '',
							'Client secret' => ''
						],
						'maxlength' => [
							'Redirection endpoint' => 2048,
							'Client ID' => 255,
							'Client secret' => 255
						],
						'mandatory' => ['Redirection endpoint', 'Client ID', 'Client secret']
					]
				];

				foreach (['Generic SMTP', 'Gmail', 'Gmail relay', 'Office365'] as $email_provider) {
					$form->fill(['Email provider' => $email_provider, 'Authentication' => 'OAuth']);

					// Verify oauth fields are displayed.
					$this->assertTrue($form->getLabel('OAuth tokens')->isDisplayed());
					$this->assertTrue($form->isRequired('OAuth tokens'));
					$this->assertTrue($form->query('button:Configure')->one()->isDisplayed());

					// Open oauth form.
					$form->query('button:Configure')->one()->click();
					$oauth_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$oauth_form = $oauth_overlay->asForm();
					$this->assertEquals('New oauth', $oauth_overlay->getTitle());

					// Check that "Copy" button is enabled and displayed.
					$this->assertTrue($oauth_form->query('xpath:.//button[contains(@class, "zi-copy")]')->one()->isClickable());

					// Check fields attributes.
					$oauth_provider = ($email_provider === 'Generic SMTP') ? $oauth_fields['generic_smtp'] : $oauth_fields['other'];
					$this->checkFieldsAttributes($oauth_form, $oauth_provider);

					// Check "Authorization code" related fields.
					if ($email_provider === 'Generic SMTP') {
						$this->assertEquals('Authorization code', $oauth_form->getField('id:code')->getAttribute('placeholder'));
						$this->assertFalse($oauth_form->getField('id:code')->isEnabled());
						$oauth_form->fill(['id:authorization_mode' => 'Manual']);
						$this->assertTrue($oauth_form->getField('id:code')->isEnabled());
					}

					$hints_data = [
						'common' => [
							[
								'label' => 'Redirection endpoint',
								'text' => "Destination URL where successful authorization redirects.\n".
								'The URL must comply with the OAuth provider\'s policy.'
							],
							[
								'label' => 'Client ID',
								'text' => 'The client identifier registered within the authorization server.'
							],
							[
								'label' => 'Client secret',
								'text' => 'The client secret registered within the authorization server.'
							]
						],
						'generic' => [
							[
								'label' => 'Authorization endpoint',
								'text' => 'Authorization server URL for requesting user authorization.'
							],
							[
								'label' => 'Authorization code',
								'text' => "Temporary token to exchange for an access token.\n".
									"Select retrieval method: automatically through a redirection page or manually if automatic retrieval fails."
							],
							[
								'label' => 'Token endpoint',
								'text' => 'Authorization server URL to exchange the authorization code for an access token.'
							]
						]
					];
					$hints = ($email_provider === 'Generic SMTP')
						? array_merge($hints_data['common'], $hints_data['generic'])
						: $hints_data['common'];
					foreach ($hints as $hint) {
						$this->checkHint($oauth_form, 'zi-help-filled-small', $hint['text'], $hint['label']);
					}

					// Check buttons related to 'Authorization parameters' and 'Token parameters' tables.
					if ($email_provider === 'Generic SMTP') {
						foreach (['id:oauth-auth-parameters-table', 'id:oauth-token-parameters-table'] as $locator) {
							$this->assertEquals(2, $oauth_form->query($locator)->one()->query('button', ['Add', 'Remove'])->all()
									->filter((CElementFilter::CLICKABLE))->count()
							);
						}
					}

					// Check if footer buttons present and clickable.
					$this->assertEquals(['Add', 'Cancel'], $oauth_overlay->getFooter()->query('button')->all()
							->filter(CElementFilter::CLICKABLE)->asText()
					);

					$oauth_overlay->close();
				}
				break;

			case 'SMS':
				$form->selectTab('Options');
				$labels_disabled = [
					'One' => true,
					'Unlimited' => false,
					'Custom' => false
				];

				// Check that only option "One" is enabled in the Concurrent sessions dialog.
				foreach ($form->getField('Concurrent sessions')->asSegmentedRadio()->getLabels() as $label_element) {
					$label_text = $label_element->getText();
					$this->assertEquals($labels_disabled[$label_text], $label_element->query('xpath:./../input')
							->one()->isEnabled()
					);
				}

				break;

			case 'Script':
				$script_params = $form->getField('Script parameters')->asTable();
				$this->assertEquals(['Value',''], $script_params->getHeadersText());
				$this->assertEquals('Add', $script_params->query('xpath:./tfoot//button')->one()->getText());

				// Click on the add button and check the added row for script parameter.
				$script_params->query('button:Add')->one()->click();
				$param_field = $script_params->query('xpath:.//input')->one();
				$this->assertEquals('', $param_field->getValue());
				$this->assertEquals(255, $param_field->getAttribute('maxlength'));

				// Check removal of script parameters.
				$script_params->query('button:Remove')->one()->click();
				$this->assertFalse($param_field->isVisible());
				break;

			case 'Webhook':
				// Check parameters table.
				$webhook_params = [
					['Name' => 'URL', 'Value' => ''],
					['Name' => 'HTTPProxy', 'Value' => ''],
					['Name' => 'To', 'Value' => '{ALERT.SENDTO}'],
					['Name' => 'Subject', 'Value' => '{ALERT.SUBJECT}'],
					['Name' => 'Message', 'Value' => '{ALERT.MESSAGE}']
				];

				$params_table = $this->query('id:parameters_table')->asMultifieldTable()->one();
				$this->assertEquals(['Name', 'Value', ''], $params_table->getHeadersText());
				$params_table->checkValue($webhook_params);

				// Check that Remove button for each parameter and Add button are present and clickable in Parameters table.
				$this->assertEquals(count($webhook_params) + 1, $params_table->query('button', ['Add', 'Remove'])
						->all()->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
				);

				// Check Script dialog.
				$script_dialog = $form->getField('Script')->edit();
				$this->assertEquals('JavaScript', $script_dialog->getTitle());
				$script_input = $script_dialog->query('xpath:.//textarea')->one();

				foreach (['placeholder' => 'return value', 'maxlength' => 65535] as $attribute => $value) {
					$this->assertEquals($value, $script_input->getAttribute($attribute));
				}

				// Check the element that counts remaining chars in script.
				$char_count = $script_dialog->query('class:multilineinput-char-count')->one();
				$this->assertEquals('65535 characters remaining', $char_count->getText());
				$script_input->fill('12345');
				$this->assertEquals('65530 characters remaining', $char_count->getText());

				// Check dialog buttons.
				$this->assertEquals(2, $script_dialog->query('button', ['Apply', 'Cancel'])->all()
						->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
				);
				$script_dialog->query('button:Cancel')->one()->click();

				// Check that Menu entry fields are enabled only when "Include event menu entry" is set.
				$this->assertEquals(2, $this->query('id', ['event_menu_name', 'event_menu_url'])->all()
						->filter(new CElementFilter(CElementFilter::ATTRIBUTES_PRESENT, ['disabled']))->count()
				);

				$form->getField('Include event menu entry')->fill(true);

				$this->assertEquals(2, $this->query('id', ['event_menu_name', 'event_menu_url'])->all()
						->filter(new CElementFilter(CElementFilter::ATTRIBUTES_NOT_PRESENT, ['disabled']))->count()
				);
				break;
		}

		$overlay->close();
	}

	/**
	 * Check fields attributes in particular tab.
	 *
	 * @param CFormElement	$form			form that contains the fields to be checked
	 * @param array			$parameters		field names, their attributes and attribute values
	 */
	protected function checkTabFields($form, $parameters) {
		if (CTestArrayHelper::get($parameters, 'tab name', 'Media type') !== $form->getSelectedTab()) {
			$form->selectTab($parameters['tab name']);
		}

		$this->checkFieldsAttributes($form, $parameters);
	}

	/**
	 * Check attributes of provided fields.
	 *
	 * @param CFormElement	$form			form that contains the fields to be checked
	 * @param array			$parameters		field names, their attributes and attribute values
	 */
	protected function checkFieldsAttributes($form, $parameters) {
		// Check field default values.
		$form->checkValue($parameters['defaults']);

		// Check maxlengths of input elements.
		foreach ($parameters['maxlength'] as $field_name => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field_name)->getAttribute('maxlength'));
		}

		// Check mandatory fields marking.
		foreach ($parameters['mandatory'] as $field) {
			$this->assertTrue($form->isRequired($field));
		}
	}

	/**
	 * Check field's hint text.
	 *
	 * @param CFormElement $form         given form
	 * @param string       $selector     hintbox selector
	 * @param string       $hint_text    text of the hint
	 * @param string       $label        checked field's label
	 */
	protected function checkHint($form, $selector, $hint_text, $label = null) {
		if ($label === null) {
			$form->query('xpath://button[contains(@class, '.CXPathHelper::escapeQuotes($selector).')]')->one()->click();
		}
		else {
			$form->getLabel($label)->query('xpath:./button[contains(@class, '.
				CXPathHelper::escapeQuotes($selector).')]')->one()->click();
		}

		$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible()->one();
		$this->assertEquals($hint_text, $hint->getText());
		$hint->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint->waitUntilNotPresent();
	}

	public function getMediaTypeData() {
		return [
			// Check that OAuth field is mandatory.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Test',
						'Authentication' => 'OAuth'
					],
					'error' => 'Field "oauth" is mandatory.'
				]
			],
			// Check OAuth form validation using Generic SMTP provider.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Generic SMTP',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => '',
						'Client ID' => '',
						'Client secret' => '',
						'Authorization endpoint' => '',
						'Token endpoint' => '',
						'id:authorization_mode' => 'Manual'
					],
					'error' => [
						'Incorrect value for field "redirection_url": cannot be empty.',
						'Incorrect value for field "client_id": cannot be empty.',
						'Incorrect value for field "client_secret": cannot be empty.',
						'Incorrect value for field "authorization_url": cannot be empty.',
						'Incorrect value for field "token_url": cannot be empty.',
						'Incorrect value for field "code": cannot be empty.'
					]
				]
			],
			// Check OAuth form validation using Gmail provider.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Gmail',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => '',
						'Client ID' => '',
						'Client secret' => ''
					],
					'error' => [
						'Incorrect value for field "redirection_url": cannot be empty.',
						'Incorrect value for field "client_id": cannot be empty.',
						'Incorrect value for field "client_secret": cannot be empty.'
					]
				]
			],
			// Error message when redirection url is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Gmail relay',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => '',
						'Client ID' => 'test',
						'Client secret' => 'test'
					],
					'error' => [
						'Incorrect value for field "redirection_url": cannot be empty.'
					]
				]
			],
			// Error message when client ID is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Office365',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => 'test',
						'Client ID' => '',
						'Client secret' => 'test'
					],
					'error' => [
						'Incorrect value for field "client_id": cannot be empty.'
					]
				]
			],
			// Error message when client secret is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Generic SMTP',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => 'test',
						'Client ID' => 'test',
						'Client secret' => '',
						'Authorization endpoint' => 'test',
						'Token endpoint' => 'test'
					],
					'error' => [
						'Incorrect value for field "client_secret": cannot be empty.'
					]
				]
			],
			// Error message when authorization endpoint is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Generic SMTP',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => 'test',
						'Client ID' => 'test',
						'Client secret' => 'test',
						'Authorization endpoint' => '',
						'Token endpoint' => 'test'
					],
					'error' => [
						'Incorrect value for field "authorization_url": cannot be empty.'
					]
				]
			],
			// Error message when authorization endpoint is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Generic SMTP',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => 'test',
						'Client ID' => 'test',
						'Client secret' => 'test',
						'Authorization endpoint' => 'test',
						'Token endpoint' => ''
					],
					'error' => [
						'Incorrect value for field "token_url": cannot be empty.'
					]
				]
			],
			// Error message when authorization code is empty.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Email provider' => 'Generic SMTP',
						'Authentication' => 'OAuth'
					],
					'oauth_form' => [
						'Redirection endpoint' => 'test',
						'Client ID' => 'test',
						'Client secret' => 'test',
						'Authorization endpoint' => 'test',
						'Token endpoint' => 'test',
						'id:authorization_mode' => 'Manual'
					],
					'error' => [
						'Incorrect value for field "code": cannot be empty.'
					]
				]
			],
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty space in name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => '   '
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Duplicate mediatype name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Discord'
					],
					'error' => 'Media type "Discord" already exists.'
				]
			],
			// Empty SMTP server.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty SMTP server',
						'SMTP server' => ''
					],
					'error' => 'Invalid parameter "/1/smtp_server": cannot be empty.'
				]
			],
			// Empty space in SMTP server.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in SMTP server',
						'SMTP server' => '   '
					],
					'error' => 'Invalid parameter "/1/smtp_server": cannot be empty.'
				]
			],
			// Too high SMTP server port.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Too high SMTP server port',
						'SMTP server port' => '99999'
					],
					'error' => 'Invalid parameter "/1/smtp_port": value must be one of 0-65535.'
				]
			],
			// Empty Email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty email',
						'Email' => ''
					],
					'error' => 'Invalid email address "".'
				]
			],
			// Empty space in Email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in Email',
						'Email' => '   '
					],
					'error' => 'Invalid email address "".'
				]
			],
			// Empty password for Gmail provider email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty password in for Gmail',
						'Email provider' => 'Gmail',
						'Password' => ''
					],
					'error' => 'Invalid parameter "/1/passwd": cannot be empty.'
				]
			],
			// Empty password for Gmail relay provider email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty password in for Gmail relay',
						'Email provider' => 'Gmail relay',
						'Authentication' => 'Email and password',
						'Password' => ''
					],
					'error' => 'Invalid parameter "/1/passwd": cannot be empty.'
				]
			],
			// Empty password for Office365 provider email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty password in for Office365',
						'Email provider' => 'Office365',
						'Password' => ''
					],
					'error' => 'Invalid parameter "/1/passwd": cannot be empty.'
				]
			],
			// Empty password for Office365 relay provider email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty password in for Office365 relay',
						'Email provider' => 'Office365 relay',
						'Authentication' => 'Email and password',
						'Password' => ''
					],
					'error' => 'Invalid parameter "/1/passwd": cannot be empty.'
				]
			],
			// Empty GSM modem.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty GSM modem',
						'Type' => 'SMS',
						'GSM modem' => ''
					],
					'error' => 'Invalid parameter "/1/gsm_modem": cannot be empty.'
				]
			],
			// Empty space in GSM modem.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in GSM modem',
						'Type' => 'SMS',
						'GSM modem' => '   '
					],
					'error' => 'Invalid parameter "/1/gsm_modem": cannot be empty.'
				]
			],
			// Empty Script name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty Script name',
						'Type' => 'Script',
						'Script name' => ''
					],
					'error' => 'Invalid parameter "/1/exec_path": cannot be empty.'
				]
			],
			// Empty space in Script name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in Script name',
						'Type' => 'Script',
						'Script name' => '   '
					],
					'error' => 'Invalid parameter "/1/exec_path": cannot be empty.'
				]
			],
			// Options validation - Attempts.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with zero attempt'
					],
					'options_tab' => [
						'Attempts' => 0
					],
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with empty attempt'
					],
					'options_tab' => [
						'Attempts' => ''
					],
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 101 attempts'
					],
					'options_tab' => [
						'Attempts' => 101
					],
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email attempts with symbols'
					],
					'options_tab' => [
						'Attempts' => 'æų'
					],
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email attempts with symbols'
					],
					'options_tab' => [
						'Attempts' => '☺'
					],
					'error' => 'Invalid parameter "/1/maxattempts": value must be one of 1-100.'
				]
			],
			// Options validation - Attempt interval.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 3601s in interval'
					],
					'options_tab' => [
						'Attempt interval' => '3601s'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 3601 in interval'
					],
					'options_tab' => [
						'Attempt interval' => '3601'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 61m in interval'
					],
					'options_tab' => [
						'Attempt interval' => '61m'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 2h in interval'
					],
					'options_tab' => [
						'Attempt interval' => '2h'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with symbols in interval'
					],
					'options_tab' => [
						'Attempt interval' => '1msms'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with smiley in interval'
					],
					'options_tab' => [
						'Attempt interval' => '☺'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with -1s interval'
					],
					'options_tab' => [
						'Attempt interval' => '-1s'
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with -1 interval'
					],
					'options_tab' => [
						'Attempt interval' => -1
					],
					'error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-3600.'
				]
			],
			// Options validation - Concurrent sessions.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Email with 101 in custom sessions'
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => 101
					],
					'error' => 'Invalid parameter "/1/maxsessions": value must be one of 0-100.'
				]
			],
			// Successful mediatype creation/update.
			[
				[
					'mediatype_tab' => [
						'Name' => 'Media type with only default parameters'
					]
				]
			],
			// SMTP generic email with all possible parameters defined.
			[
				[
					'mediatype_tab' => [
						'Name' => 'Email media type with all possible parameters',
						'SMTP server' => 'παράδειγμα.%^&*(.com',
						'SMTP server port' => 666,
						'Email' => 'zabbix@zabbix.com',
						'SMTP helo' => '!@#$%%^&*(.com',
						'Connection security' => 'STARTTLS',
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'Authentication' => 'Username and password',
						'Username' => 'χρήστης',
						'Password' => 'παράδειγμα',
						'Message format' => 'Plain text',
						'Description' => 'If only χρήστης was παράδειγμα then everyone would be happy',
						'Enabled' => false
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => 12,
						'Attempts' => 9,
						'Attempt interval' => 60
					]
				]
			],
			// Gmail relay email with all possible parameters defined.
			[
				[
					'mediatype_tab' => [
						'Name' => 'Gmail relay with all possible parameters',
						'Email provider' => 'Gmail relay',
						'Email' => 'gmail@zabbix.com',
						'Authentication' => 'Email and password',
						'Password' => 'παράδειγμα',
						'Message format' => 'Plain text',
						'Description' => 'If only χρήστης was παράδειγμα then everyone would be happy',
						'Enabled' => false
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => 3,
						'Attempts' => 2,
						'Attempt interval' => 1
					]
				]
			],
			// Office365 relay email with all possible parameters defined.
			[
				[
					'mediatype_tab' => [
						'Name' => 'Office365 relay with all possible parameters',
						'Email provider' => 'Office365 relay',
						'Email' => 'office365@zabbix.com',
						'Authentication' => 'Email and password',
						'Password' => '1',
						'Message format' => 'Plain text',
						'Description' => 'One more time: If only χρήστης was παράδειγμα then everyone would be happy',
						'Enabled' => false
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => 4,
						'Attempts' => 3,
						'Attempt interval' => 2
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => 'SMS media type with default values',
						'Type' => 'SMS'
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => 'SMS media type with all possible parameters',
						'Type' => 'SMS',
						'GSM modem' => 'χρήστης',
						'Description' => '良い一日を過ごしてください',
						'Enabled' => false
					],
					'options_tab' => [
						'id:maxsessions_type' => 'One',
						'Attempts' => 2,
						'Attempt interval' => '1s'
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => 'Script media type with minimalset of values',
						'Type' => 'Script',
						'Script name' => '良い一日を過ごしてください'
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => 'Script media type with parameters and options',
						'Type' => 'Script',
						'Script name' => '좋은 하루 되세요',
						'Description' => 'I like cheese',
						'Enabled' => false
					],
					'script_parameters' => [
						[
							'Value' => 'first parameter'
						],
						[
							'Value' => '良い一日を過ごしてください'
						],
						[
							'Value' => '!@#$%^&*()_+='
						]
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => 'abc',
						'Attempts' => 10,
						'Attempt interval' => '60m'
					],
					'substitute_maxsessions' => true
				]
			],
			// Successfully create media type with different options.
			[
				[
					'mediatype_tab' => [
						'Name' => 'Gmail email with options: unlimited concurrent sessions and 0h interval',
						'Email provider' => 'Gmail',
						'Password' => 'qwerty'
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Unlimited',
						'Attempts' => 100,
						'Attempt interval' => '0h'
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => 'Office365 email with options: 1h interval and default maxsessions (0)',
						'Email provider' => 'Office365',
						'Password' => '12345'
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'Attempts' => 100,
						'Attempt interval' => '1h'
					],
					'substitute_maxsessions' => true
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => '   Email with trailing and leading spaces in params   ',
						'Email provider' => 'Generic SMTP',
						'SMTP server' => '   παράδειγμα.%^&*(.com   ',
						'SMTP server port' => ' 25 ',
						'Email' => '   zabbix@zabbix.com   ',
						'SMTP helo' => '  !@#$%%^&*(.com  ',
						'Authentication' => 'Username and password',
						'Username' => '   χρήστης  ',
						'Description' => '   test  '
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => ' 7 ',
						'Attempts' => ' 2 ',
						'Attempt interval' => '   10s   '
					],
					'trim' => [
						'mediatype_tab' => [
							'Name',
							'SMTP server',
							'SMTP server port',
							'Email',
							'SMTP helo',
							'Username',
							'Description'
						],
						'options_tab' => [
							'id:maxsessions',
							'Attempts',
							'Attempt interval'
						]
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => '   SMS with trailing and leading spaces in params   ',
						'Type' => 'SMS',
						'GSM modem' => '   /dev/ttyS0   '
					],
					'trim' => [
						'mediatype_tab' => [
							'Name',
							'GSM modem'
						]
					]
				]
			],
			[
				[
					'mediatype_tab' => [
						'Name' => '   Script with trailing and leading spaces in params   ',
						'Type' => 'Script',
						'Script name' => '   Script name   '
					],
					'trim' => [
						'mediatype_tab' => [
							'Name',
							'Script name'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getMediaTypeData
	 */
	public function testFormAdministrationMediaTypes_Create($data) {
		$this->checkAction($data);
	}

	public function getGeneralMediaTypeData() {
		return [
			[
				[
					'media_type' => 'Email (HTML)'
				]
			],
			[
				[
					'media_type' => 'Test script'
				]
			],
			[
				[
					'media_type' => 'SMS'
				]
			]
		];
	}

	/**
	 * @dataProvider getGeneralMediaTypeData
	 */
	public function testFormAdministrationMediaTypes_SimpleUpdate($data) {
		$old_hash = CDBHelper::getHash(self::$mediatype_sql);

		$this->page->login()->open(self::URL);
		$this->query('link', $data['media_type'])->one()->WaitUntilClickable()->click();
		COverlayDialogElement::find()->one()->waitUntilReady()->asForm()->submit();
		COverlayDialogElement::ensureNotPresent();

		$this->assertMessage(TEST_GOOD, 'Media type updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
	}

	/**
	 * @dataProvider getGeneralMediaTypeData
	 */
	public function testFormAdministrationMediaTypes_Clone($data) {
		$clone_sql = 'SELECT type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,passwd,status,'.
				'smtp_port,smtp_security,smtp_verify_peer,smtp_verify_host,smtp_authentication,maxsessions,'.
				'maxattempts,attempt_interval,message_format,script,timeout,process_tags,show_event_menu,'.
				'event_menu_url,event_menu_name,description FROM media_type WHERE name=';
		$old_hash = CDBHelper::getHash($clone_sql.zbx_dbstr($data['media_type']));

		// Clone the media type.
		$this->page->login()->open(self::URL);
		$this->query('link', $data['media_type'])->WaitUntilClickable()->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Media type', $overlay->getTitle());
		$overlay->query('button:Clone')->one()->click();
		$form = $overlay->asForm();
		$this->assertEquals('New media type', $overlay->getTitle());
		$clone_name = $data['media_type'].' clone';
		$form->fill(['Name' => $clone_name]);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		$this->assertMessage(TEST_GOOD, 'Media type added');
		$this->assertEquals($old_hash, CDBHelper::getHash($clone_sql.zbx_dbstr($clone_name)));
	}

	/**
	 * @dataProvider getMediaTypeData
	 */
	public function testFormAdministrationMediaTypes_Update($data) {
		$this->checkAction($data, false);
	}

	public function testFormAdministrationMediaTypes_CancelCreate() {
		$this->checkActionCancellation();
	}

	public function testFormAdministrationMediaTypes_CancelUpdate() {
		$this->checkActionCancellation('update');
	}

	public function testFormAdministrationMediaTypes_CancelClone() {
		$this->checkActionCancellation('clone');
	}

	public function testFormAdministrationMediaTypes_CancelDelete() {
		$this->checkActionCancellation('delete');
	}

	public function testFormAdministrationMediaTypes_Delete() {
		$this->page->login()->open(self::URL);
		$this->query('link', self::$delete_mediatype)->WaitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Media type deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT mediatypeid FROM media_type WHERE name='.
				zbx_dbstr(self::$delete_mediatype))
		);
	}

	/**
	 * Check cancellation scenarios for create, update, clone and delete actions.
	 *
	 * @param string	$action		type of action to be checked
	 */
	protected function checkActionCancellation($action = 'create') {
		$new_values = [
			'Media type' => [
				'Name' => 'Email for action cancellation check',
				'SMTP server' => '我是一只猫.com',
				'SMTP server port' => 666,
				'SMTP helo' => 'ស្វា.com',
				'Connection security' => 'STARTTLS',
				'SSL verify peer' => true,
				'SSL verify host' => true,
				'Authentication' => 'Username and password',
				'Username' => 'χρήστης',
				'Password' => 'παράδειγμα',
				'Message format' => 'Plain text',
				'Description' => 'I want to go home...',
				'Enabled' => false
			],
			'Options' => [
				'id:maxsessions_type' => 'Custom',
				'id:maxsessions' => 5,
				'Attempts' => 4,
				'Attempt interval' => '3s'
			]
		];
		$old_hash = CDBHelper::getHash(self::$mediatype_sql);
		$this->page->login()->open(self::URL);

		$locator = ($action === 'create') ? 'button:Create media type' : 'link:'.self::$update_mediatypes['Email'];
		$this->query($locator)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$this->page->waitUntilReady();

		$form = $this->query('id:media-type-form')->asForm()->one();

		if ($action === 'delete') {
			$dialog->query('button:Delete')->waitUntilClickable()->one()->click();

			$this->page->dismissAlert();
		}
		else {
			if ($action === 'clone') {
				$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
			}
			elseif ($action === 'update') {
				unset($new_values['Media type']['Password']);
			}

			foreach ($new_values as $tab => $values) {
				if ($tab !== $form->getSelectedTab()) {
					$form->selectTab($tab);
				}
				$form->fill($values);
			}
		}

		$dialog->query('button:Cancel')->one()->click();

		$this->page->waitUntilReady();
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
	}

	/**
	 * Check Mediatype creation or update actions and their validation.
	 *
	 * @param array		$data		data provider
	 * @param boolean	$create		flag that specifies whether the action to be checked is a create action.
	 */
	public function checkAction($data, $create = true) {
		if (CTestArrayHelper::get($data, 'expected') === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$mediatype_sql);
		}

		// Open the corresponding media type form.
		$this->page->login()->open(self::URL)->waitUntilReady();

		if ($create) {
			$this->query('button:Create media type')->waitUntilClickable()->one()->click();
		}
		else {
			$type = CTestArrayHelper::get($data['mediatype_tab'], 'Type', 'Email');

			if (array_key_exists('oauth_form', $data)) {
				$this->query('link', $data['mediatype_tab']['Email provider'].' OAuth')->waitUntilClickable()->one()->click();
			}
			else {
				$this->query('link', self::$update_mediatypes[$type])->waitUntilClickable()->one()->click();
			}

			// Add prefix to mediatype new name for update scenarios to avoid issues with duplicate names.
			if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
				$data['mediatype_tab']['Name'] = (array_key_exists('trim', $data))
					? '   Update: '.ltrim($data['mediatype_tab']['Name'])
					: 'Update: '.$data['mediatype_tab']['Name'];
			}
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:media-type-form')->asForm()->one();

		if ($form->query('button:Change password')->one(false)->isValid() && array_key_exists('Password', $data['mediatype_tab'])) {
			$form->query('button:Change password')->one()->click();
		}

		$form->fill($data['mediatype_tab']);

		if (array_key_exists('oauth_form', $data)) {
			$form->query('button:Configure')->one()->click();
			$oauth_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$oauth_form = $oauth_overlay->asForm();

			if ($oauth_form->query('button:Change client secret')->one(false)->isValid()
					&& array_key_exists('Client secret', $data['oauth_form'])) {
				$oauth_form->query('button:Change client secret')->one()->click();
			}

			$oauth_form->fill($data['oauth_form']);
		}

		if (array_key_exists('script_parameters', $data)) {
			$form->getField('Script parameters')->asMultifieldTable()->fill($data['script_parameters']);
		}

		if (array_key_exists('options_tab', $data)) {
			$form->selectTab('Options');
			$form->fill($data['options_tab']);
		}

		if (array_key_exists('oauth_form', $data)) {
			$oauth_overlay->getFooter()->query('button', $create ? 'Add' : 'Update')->one()->click();
		}
		else {
			$dialog->getFooter()->query('button', $create ? 'Add' : 'Update')->one()->click();
		}
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected') === TEST_BAD) {
			$title = array_key_exists('oauth_form', $data)
				? 'Invalid OAuth configuration'
				: ($create ? 'Cannot add media type' : 'Cannot update media type');
			$this->assertMessage(TEST_BAD, $title, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));

			if (array_key_exists('oauth_form', $data)) {
				$oauth_overlay->close();
			}
		}
		else {
			$title = ($create) ? 'Media type added' : 'Media type updated';
			$this->assertMessage(TEST_GOOD, $title);

			// Trim leading and trailing spaces from expected results if necessary.
			if (array_key_exists('trim', $data)) {
				foreach ($data['trim'] as $tab => $fields) {
					foreach ($fields as $field) {
						$data[$tab][$field] = trim($data[$tab][$field]);
					}
				}
			}

			// Check that the media type was actually created.
			$this->assertEquals(1, CDBHelper::getCount('SELECT mediatypeid FROM media_type WHERE name='.
					CDBHelper::escape($data['mediatype_tab']['Name']))
			);

			if (!$create) {
				self::$update_mediatypes[$type] = $data['mediatype_tab']['Name'];
			}

			$this->page->query('link', $data['mediatype_tab']['Name'])->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
			$form->invalidate();

			if (array_key_exists('Password', $data['mediatype_tab'])) {
				$this->assertEquals($data['mediatype_tab']['Password'],
						CDBHelper::getValue('SELECT passwd FROM media_type WHERE name='.zbx_dbstr($data['mediatype_tab']['Name']))
				);

				unset($data['mediatype_tab']['Password']);
			}

			$form->checkValue($data['mediatype_tab']);

			if (array_key_exists('script_parameters', $data)) {
				// Add an existing script parameter to the result array for update test.
				if (!$create) {
					$data['script_parameters'] = array_merge([['Value' => '{ALERT.SUBJECT}']], $data['script_parameters']);
				}
				$form->getField('Script parameters')->asMultifieldTable()->checkValue($data['script_parameters']);
			}

			if (array_key_exists('options_tab', $data)) {
				$form->selectTab('Options');

				// If maxsessions input has invalid value, Sessions type is set to Unlimited.
				if (array_key_exists('substitute_maxsessions', $data)) {
					$data['options_tab']['id:maxsessions_type'] = 'Unlimited';

					if (array_key_exists('id:maxsessions', $data['options_tab'])) {
						unset($data['options_tab']['id:maxsessions']);
					}
				}
				$form->checkValue($data['options_tab']);
			}
		}

		$dialog->close();
	}

	public function getSavedParametersData() {
		return [
			[
				[
					'object' => 'Switch script to webhook with default params',
					'mediatype_tab' => [
						'Name' => 'Webhook for default parameters check',
						'Type' => 'Webhook',
						'Script' => 'test default'
					],
					'expected_parameters' => [
						[
							'Name' => 'HTTPProxy',
							'Value' => ''
						],
						[
							'Name' => 'Message',
							'Value' => '{ALERT.MESSAGE}'
						],
						[
							'Name' => 'Subject',
							'Value' => '{ALERT.SUBJECT}'
						],
						[
							'Name' => 'To',
							'Value' => '{ALERT.SENDTO}'
						],
						[
							'Name' => 'URL',
							'Value' => ''
						]
					]
				]
			],
			[
				[
					'object' => 'Switch webhook to script with no params',
					'mediatype_tab' => [
						'Name' => 'Script media type with minimal set of values',
						'Type' => 'Script',
						'Script name' => '良い一日を過ごしてください',
						'Script parameters' => []
					]
				]
			],
			[
				[
					'object' => 'Switch webhook to script with custom params',
					'mediatype_tab' => [
						'Name' => 'Script media type with several parameters',
						'Type' => 'Script',
						'Script name' => '좋은 하루 되세요',
						'Script parameters' => [
							[
								'Value' => 'first parameter'
							],
							[
								'Value' => '良い一日を過ごしてください'
							],
							[
								'Value' => '!@#$%^&*()_+='
							]
						]
					]
				]
			],
			[
				[
					'object' => 'Switch script to webhook with no params',
					'mediatype_tab' => [
						'Name' => 'Webhook with minimal set of values',
						'Type' => 'Webhook',
						'Script' => 'test no params'
					],
					'remove_parameters' => true
				]
			],
			[
				[
					'object' => 'Switch script to webhook with custom params',
					'mediatype_tab' => [
						'Name' => 'Webhook with custom parameters',
						'Type' => 'Webhook',
						'Script' => 'test custom'
					],
					'custom_parameters' => [
						[
							'Name' => 'From',
							'Value' => 'zabbix.com'
						],
						[
							'Name' => 'HTTPS',
							'Value' => 'true'
						]
					],
					'remove_parameters' => true
				]
			]
		];
	}

	/**
	 * Check that parameters are saved correctly when switching type from Webhook to Script and vice versa.
	 *
	 * @dataProvider getSavedParametersData
	 */
	public function testFormAdministrationMediaTypes_SavedParameters($data) {
		$this->page->login()->open(self::URL);
		$this->query('link', $data['object'])->waitUntilClickable()->one()->click();

		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->fill($data['mediatype_tab']);

		if (array_key_exists('remove_parameters', $data)) {
			$form->getField('Parameters')->asMultifieldTable()->clear();
		}

		if (array_key_exists('custom_parameters', $data)) {
			$form->getField('Parameters')->asMultifieldTable()->fill($data['custom_parameters']);
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Media type updated');

		$this->page->query('link', $data['mediatype_tab']['Name'])->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$form->invalidate();
		$form->checkValue($data['mediatype_tab']);

		if (array_key_exists('custom_parameters', $data)) {
			$form->getField('Parameters')->asMultifieldTable()->checkValue($data['custom_parameters']);
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Check scenarios when warning tooltip does or doesn't appear.
	 * Possible values:
	 * 		0 - (default) Both tokens contain invalid value;
	 * 		1 - Access token contain valid value;
	 * 		2 - Refresh token contain valid value;
	 * 		3 - Both tokens contain valid value.
	 */
	public function testFormAdministrationMediaTypes_TokenStatus() {
		$this->page->login()->open(self::URL)->waitUntilReady();

		foreach (['Generic SMTP OAuth', 'Gmail OAuth', 'Gmail relay OAuth', 'Office365 OAuth'] as $name) {
			foreach ([0, 1, 2, 3] as $tokens_status) {
				DBexecute('UPDATE media_type_oauth SET tokens_status='.$tokens_status.' WHERE mediatypeid='.
						self::$mediatypeids[$name]
				);
				$this->query('link', $name)->waitUntilClickable()->one()->click();
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();

				if ($tokens_status === 0 || $tokens_status === 1) {
					$this->checkHint($form, 'zi-i-negative', 'Refresh token is invalid or outdated.');
				}
				else {
					$this->assertFalse($form->query('xpath://button[contains(@class, "zi-i-negative")]')->one(false)->isValid());
				}

				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	public function getTimeIntervalData() {
		/**
		 * The following units are used: years, months, days, hours, minutes.
		 * Only the 3 most significant units will be displayed: #y #M #d, #M #d #h, #d #h #m and so on.
		 */
		return [
			[
				[
					'access_token_updated' => strtotime('now')
				]
			],
			[
				[
					'access_token_updated' => strtotime('-2 minutes -5 seconds')
				]
			],
			[
				[
					'access_token_updated' => strtotime('-3 hours -2 minutes')
				]
			],
			[
				[
					'access_token_updated' => strtotime('-4 days -5 hours -4 minutes -6 seconds') // 4d 5h 4m.
				]
			],
			[
				[
					'access_token_updated' => strtotime('-2 weeks -1 day -1 hour -1 minute -1 second') // 15d 1h 1m.
				]
			],
			/**
			 * If time interval contains month with 31 day then expected result will be 1M 27d 2h, otherwise 1M 26d 2h.
			 */
			[
				[
					'access_token_updated' => strtotime('-1 months -3 weeks -5 days -2 hours -4 minutes -3 seconds')
				]
			],
			/**
			 * If time interval contains month with 31 day then expected result will be 1y 1M 9d, otherwise 1y 1M 8d.
			 */
			[
				[
					'access_token_updated' => strtotime('-1 year -1 month -1 week -1 day -1 hour 1 minute -1 second')
				]
			]
		];
	}

	/**
	 * Check OAuth tokens configured time.
	 *
	 * @dataProvider getTimeIntervalData
	 */
	public function testFormAdministrationMediaTypes_ConfiguredTime($data) {
		DBexecute('UPDATE media_type_oauth SET access_token_updated='.$data['access_token_updated'].' WHERE mediatypeid='.
				self::$mediatypeids['Generic SMTP OAuth']
		);
		$this->page->login()->open('zabbix.php?action=popup&popup=mediatype.edit&mediatypeid='.
				self::$mediatypeids['Generic SMTP OAuth'])->waitUntilReady();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();

		// Check configured time taking into account that page and form opening could take extra time.
		$reference_uptime = [];
		for ($i = -1; $i <= 1; $i++) {
			$reference_uptime[] = convertUnitsS(time() - $data['access_token_updated'] + $i);
		}

		// Remove text from both sides and compare token uptime results.
		$uptime = preg_replace('/^Configured | ago$/', '', $form->query('id:js-oauth-status')->one()->getText());
		$this->assertTrue(in_array($uptime, $reference_uptime), $uptime.' is not among values '.implode(', ', $reference_uptime));

		COverlayDialogElement::find()->one()->close();
	}
}
