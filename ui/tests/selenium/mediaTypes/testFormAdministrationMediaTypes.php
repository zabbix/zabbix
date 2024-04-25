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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup media_type
 */
class testFormAdministrationMediaTypes extends CWebTest {

	private static $mediatype_sql = 'SELECT * FROM media_type ORDER BY mediatypeid';

	private static $update_mediatypes = [
		'Email' => 'Email',
		'SMS' => 'SMS',
		'Script' => 'Test script'
	];
	private static $delete_mediatype = 'Email (HTML)';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function testFormAdministrationMediaTypes_GeneralLayout() {
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('button:Create media type')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();
		$this->page->assertTitle('Configuration of media types');
		$this->page->assertHeader('Media types');

		$form = $this->query('id:media-type-form')->asForm()->one();
		$this->assertEquals(['Media type', 'Message templates', 'Options'], $form->getTabs());

		// Check available media type types.
		$this->assertEquals(['Email', 'SMS', 'Script', 'Webhook'], $form->getField('Type')->getOptions()->asText());

		// Check common fields in Media type and Options tabs. Message templates are covered in separate test.
		$tabs = [
			[
				'tab name' => 'Media type',
				'defaults' => [
					'Name' =>  '',
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
		$maxsessions = $concurrent_sessions->query('id:maxsessions')->one();

		foreach ($session_settings as $setting => $visible) {
			$concurrent_sessions->fill($setting);
			$this->assertTrue($maxsessions->isVisible($visible));

			if ($visible) {
				$this->assertEquals(0, $maxsessions->getValue());
				$this->assertEquals(3, $maxsessions->getAttribute('maxlength'));
			}
		}

		// Check that Add and Cancel buttons are present in the form and that they are clickable.
		$this->assertEquals(2, $this->query('id', ['add', 'cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
	}

	public static function getLayoutMediaTypes() {
		return [
			[
				[
					'type' => 'Email',
					'defaults' => [
						'SMTP server' => 'mail.example.com',
						'SMTP server port' => 25,
						'SMTP helo' => 'example.com',
						'SMTP email' => 'zabbix@example.com',
						'Connection security' => 'None',
						'Authentication' => 'None',
						'Message format' => 'HTML'
					],
					'maxlength' => [
						'SMTP server' => 255,
						'SMTP server port' => 5,
						'SMTP helo' => 255,
						'SMTP email' => 255
					],
					'mandatory' => ['SMTP server', 'SMTP helo', 'SMTP email']
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
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('button:Create media type')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();
		$form = $this->query('id:media-type-form')->asForm()->one();
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
				$this->assertEquals(['Parameter', 'Action'], $script_params->getHeadersText());
				$this->assertEquals(['Add'], $script_params->getRows()->asText());

				// Click on the add button and check the added row for script parameter.
				$script_params->query('button:Add')->one()->click();
				$param_field = $script_params->query('xpath:.//input')->one();
				$this->assertEquals('', $param_field->getValue());
				$this->assertEquals(255, $param_field->getAttribute('maxlength'));

				// Check removal ofscript parameters.
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
				$this->assertEquals(['Name', 'Value', 'Action'], $params_table->getHeadersText());
				$params_table->checkValue($webhook_params);

				// Check that Remove button for each parameter and Add button are present and clickable in Parameters table.
				$this->assertEquals(count($webhook_params) + 1, $params_table->query('button', ['Add', 'Remove'])
						->all()->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
				);

				// Check Script dialog.
				$form->getField('Script')->query('xpath:./button')->one()->click();
				$script_dialog = COverlayDialogElement::find()->one()->waitUntilReady();

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
				$script_dialog->close();

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
	}

	/**
	 * Check attributes of provided fields.
	 *
	 * @param CFormElement	$form			form that contains the fields to be checked
	 * @param array			$parameters		field names, their attributes and attribute values
	 */
	private function checkTabFields($form, $parameters) {
		if (CTestArrayHelper::get($parameters, 'tab name', 'Media type') !== $form->getSelectedTab()) {
			$form->selectTab($parameters['tab name']);
		}

		// Check field default values.
		$form->checkValue($parameters['defaults']);

		// Check maxlengths of input elements.
		foreach ($parameters['maxlength'] as $field_name => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field_name)->getAttribute('maxlength'));
		}

		// Check mandatory fields marking.
		foreach ($parameters['mandatory'] as $label) {
			$this->assertEquals('form-label-asterisk', $form->getLabel($label)->getAttribute('class'));
		}
	}

	public function getMediaTypeData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => ''
					],
					'skip_prefix' => true,
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
					'skip_prefix' => true,
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
					'skip_prefix' => true,
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
			// Empty SMTP helo.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty SMTP helo',
						'SMTP helo' => ''
					],
					'error' => 'Invalid parameter "/1/smtp_helo": cannot be empty.'
				]
			],
			// Empty space in SMTP helo.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in SMTP helo',
						'SMTP helo' => '   '
					],
					'error' => 'Invalid parameter "/1/smtp_helo": cannot be empty.'
				]
			],
			// Empty SMTP email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty SMTP email',
						'SMTP email' => ''
					],
					'error' => 'Invalid parameter "/1/smtp_email": cannot be empty.'
				]
			],
			// Empty space in SMTP email.
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Empty space in SMTP email',
						'SMTP email' => '   '
					],
					'error' => 'Invalid parameter "/1/smtp_email": cannot be empty.'
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
						'Name' => 'Email with 2h in inerval'
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
			[
				[
					'expected' => TEST_BAD,
					'mediatype_tab' => [
						'Name' => 'Trailing and leading spaces in Attempt interval'
					],
					'options_tab' => [
						'Attempt interval' => '   10s   '
					],
					'error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
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
			[
				[
					'mediatype_tab' => [
						'Name' => 'Email media type with all possible parameters',
						'SMTP server' => 'παράδειγμα.%^&*(.com',
						'SMTP server port' => 666,
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
						'Description' => ' I like cheese',
						'Enabled' => false
					],
					'script_parameters' => [
						[
							'exec_param' => 'first parameter'
						],
						[
							'exec_param' => '良い一日を過ごしてください'
						],
						[
							'exec_param' => '!@#$%^&*()_+='
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
						'Name' => 'Email with options: unlimited concurrent sessions and 0h interval'
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
						'Name' => 'Email with options: 1h interval and default maxsessions (0)'
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
						'SMTP server' => '   παράδειγμα.%^&*(.com   ',
						'SMTP server port' => ' 25 ',
						'SMTP helo' => '  !@#$%%^&*(.com  ',
						'Authentication' => 'Username and password',
						'Username' => '   χρήστης  '
					],
					'options_tab' => [
						'id:maxsessions_type' => 'Custom',
						'id:maxsessions' => ' 7 ',
						'Attempts' => ' 2 '
					],
					'trim' => [
						'mediatype_tab' => [
							'Name',
							'SMTP server',
							'SMTP server port',
							'SMTP helo',
							'Username'
						],
						'options_tab' => [
							'id:maxsessions',
							'Attempts'
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

		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('link', $data['media_type'])->one()->WaitUntilClickable()->click();
		$this->query('id:media-type-form')->asForm()->waitUntilVisible()->one()->submit();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Media type updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
	}

	/**
	 * @dataProvider getGeneralMediaTypeData
	 */
	public function testFormAdministrationMediaTypes_Clone($data) {
		$clone_sql = 'SELECT type, smtp_server, smtp_helo, smtp_email, exec_path, gsm_modem, username, passwd, '.
				'status, smtp_port, smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, exec_params, '.
				'maxsessions, maxattempts, attempt_interval, content_type, script, timeout, process_tags, show_event_menu, '.
				'event_menu_url, event_menu_name, description FROM media_type WHERE name=';
		$old_hash = CDBHelper::getHash($clone_sql.zbx_dbstr($data['media_type']));

		// Clone the media type.
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('link', $data['media_type'])->WaitUntilClickable()->one()->click();
		$this->query('button:Clone')->one()->click();
		$form = $this->query('id:media-type-form')->asForm()->waitUntilVisible()->one();
		$clone_name = $data['media_type'].' clone';
		$form->fill(['Name' => $clone_name]);
		$form->submit();
		$this->page->waitUntilReady();

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
		$this->page->login()->open('zabbix.php?action=mediatype.list');
		$this->query('link', self::$delete_mediatype)->WaitUntilClickable()->one()->click();

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
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
	private function checkActionCancellation($action = 'create') {
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
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		$locator = ($action === 'create') ? 'button:Create media type' : 'link:'.self::$update_mediatypes['Email'];
		$this->query($locator)->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$form = $this->query('id:media-type-form')->asForm()->one();

		if ($action === 'delete') {
			$form->query('button:Delete')->waitUntilClickable()->one()->click();

			$this->page->dismissAlert();
		}
		else {
			if ($action === 'clone') {
				$form->query('button:Clone')->waitUntilClickable()->one()->click();
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

		$form->query('button:Cancel')->one()->click();

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
		$this->page->login()->open('zabbix.php?action=mediatype.list');

		if ($create) {
			$this->query('button:Create media type')->waitUntilClickable()->one()->click();
		}
		else {
			$type = CTestArrayHelper::get($data['mediatype_tab'], 'Type', 'Email');
			$this->query('link', self::$update_mediatypes[$type])->waitUntilClickable()->one()->click();

			// Add prefix to mediatype new name for update scenarios to avoid issues with duplicate names.
			if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
				$data['mediatype_tab']['Name'] = (array_key_exists('trim', $data))
					? '   Update: '.ltrim($data['mediatype_tab']['Name'])
					: 'Update: '.$data['mediatype_tab']['Name'];
			}
		}

		$this->page->waitUntilReady();

		$form = $this->query('id:media-type-form')->asForm()->one();
		$form->fill($data['mediatype_tab']);

		if (array_key_exists('script_parameters', $data)) {
			$form->getField('Script parameters')->asMultifieldTable()->fill($data['script_parameters']);
		}

		if (array_key_exists('options_tab', $data)) {
			$form->selectTab('Options');
			$form->fill($data['options_tab']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected') === TEST_BAD) {
			$title = ($create) ? 'Cannot add media type' : 'Cannot update media type';
			$this->assertMessage(TEST_BAD, $title, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
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
	}
}
