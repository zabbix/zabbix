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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup media_type
 *
 * @onBefore prepareMediaTypeMessageTemplatesData
 */
class testFormAdministrationMediaTypeMessageTemplates extends CWebTest {

	// SQL query to get message_template and media_type tables to compare hash values.
	private $sql = 'SELECT * FROM media_type mt INNER JOIN media_type_message mtm ON mt.mediatypeid=mtm.mediatypeid'.
			' ORDER BY mt.mediatypeid';

	public static function prepareMediaTypeMessageTemplatesData() {
		CDataHelper::call('mediatype.create', [
			[
				'name' => 'Email (HTML) Service',
				'type' => 0,
				'smtp_server' => 'apimail@company.com',
				'smtp_helo' => 'zabbix.com',
				'smtp_email' => 'zabbix@company.com',
				'message_templates' => [
					[
						'eventsource' => 4,
						'recovery' => 0,
						'subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
						'message' => '<b>Service problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br>'.
								'<b>Service problem name:</b> {EVENT.NAME}<br><b>Service:</b> {SERVICE.NAME}<br><b>'.
								'Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>'.
								'Service description: {SERVICE.DESCRIPTION}<br><br>{SERVICE.ROOTCAUSE}'
					],
					[
						'eventsource' => 4,
						'recovery' => 1,
						'subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' => '<b>Service "{SERVICE.NAME}" has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
								'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br><b>Problem duration:</b> '.
								'{EVENT.DURATION}<br><b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}'.
								'<br>Service description: {SERVICE.DESCRIPTION}'
					],
					[
						'eventsource' => 4,
						'recovery' => 2,
						'subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
						'message' => '<b>Changed "{SERVICE.NAME}" service status</b> to {EVENT.UPDATE.SEVERITY} at '.
								'{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br><b>Current problem age</b> is {EVENT.AGE}.<br>'.
								'Service description: {SERVICE.DESCRIPTION}<br><br>{SERVICE.ROOTCAUSE}'
					]
				]
			],
			[
				'name' => 'Email Service',
				'type' => 0,
				'smtp_server' => 'apimail@company.com',
				'smtp_helo' => 'zabbix.com',
				'smtp_email' => 'zabbix@company.com',
				'message_templates' => [
					[
						'eventsource' => 4,
						'recovery' => 0,
						'subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
						'message' => '<b>Service problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br><b>Service '.
								'problem name:</b> {EVENT.NAME}<br><b>Service:</b> {SERVICE.NAME}<br><b>Severity:</b> '.
								'{EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>'.
								'Service description: {SERVICE.DESCRIPTION}<br><br>{SERVICE.ROOTCAUSE}'
					],
					[
						'eventsource' => 4,
						'recovery' => 1,
						'subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
						'message' => '<b>Service "{SERVICE.NAME}" has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
								'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br><b>Problem duration:</b> '.
								'{EVENT.DURATION}<br><b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}'.
								'<br>Service description: {SERVICE.DESCRIPTION}'
					],
					[
						'eventsource' => 4,
						'recovery' => 2,
						'subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
						'message' => '<b>Changed "{SERVICE.NAME}" service status</b> to {EVENT.UPDATE.SEVERITY} at '.
								'{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br><b>Current problem age</b> is {EVENT.AGE}.<br>'.
								'Service description: {SERVICE.DESCRIPTION}<br><br>{SERVICE.ROOTCAUSE}'
					]
				]
			],
			[
				'name' => 'SMS Service',
				'type' => 2,
				'gsm_modem' => 'test',
				'message_templates' => [
					[
						'eventsource' => 4,
						'recovery' => 0,
						'message' => "{EVENT.NAME}\n".'{EVENT.DATE} {EVENT.TIME}'
					],
					[
						'eventsource' => 4,
						'recovery' => 1,
						'message' => "{EVENT.NAME}\n".'{EVENT.DATE} {EVENT.TIME}'
					],
					[
						'eventsource' => 4,
						'recovery' => 2,
						'message' => "{EVENT.NAME}\n".'{EVENT.DATE} {EVENT.TIME}'
					]
				]
			]
		]);
	}

	public function testFormAdministrationMediaTypeMessageTemplates_Layout() {
		// Open a new media type configuration form and switch to Message templates tab.
		$this->openMediaTypeTemplates('new');

		// Check message templates tab.
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();
		$this->assertTrue($templates_list->query('button:Add')->one()->isEnabled());
		$this->assertSame(['Message type', 'Template', 'Actions'], $templates_list->getHeadersText());
		$this->assertEquals(1, $templates_list->getRows()->count());
		// Check that media type configuration form buttons are clickable from Message templates tab.
		$this->assertEquals(2, $this->query('id', ['add', 'cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());

		// Check message template configuration form.
		$templates_list->query('button:Add')->one()->click();
		$overlay = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Message template', $overlay->getTitle());
		$form = $this->query('id:mediatype_message_form')->asForm()->one();
		$this->assertEquals(['Message type', 'Subject', 'Message'], $form->getLabels()->asText());
		$form->getField('Message type')->checkValue('Problem');
		$this->assertEquals(255, $form->getField('Subject')->getAttribute('maxlength'));
		$this->assertEquals(65535, $form->getField('Message')->getAttribute('maxlength'));
		// Check that both buttons in the media type template configuration form are clickable.
		$this->assertEquals(2, $overlay->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count());

		// Add a "Problem" message template and check that corresponding row is added in Message templates table.
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$templates_list->invalidate();
		$row = $templates_list->findRow('Message type', 'Problem');

		// Check that both buttons in column Actions are clickable.
		$this->assertEquals(2, $row->getColumn('Actions')->query('button', ['Edit', 'Remove'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
		// Check that it is possible to edit a newly created message template.
		$row->query('button:Edit')->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady()->close();

		// Check that it is possible to remove a newly created message template.
		$row->query('button:Remove')->one()->click();
		// Check that only the row with "Add" button is present after removing the previously added row.
		$this->assertEquals(1, $templates_list->getRows()->count());
	}

	public function getDefaultMessageTemplateData() {
		return [
			// Default messages for plain text Email media type
			[
				[
					'media_type' => [
						'Name' => 'Email default values',
						'Type' => 'Email',
						'Message format' => 'Plain text'
					],
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Subject' => 'Problem: {EVENT.NAME}',
							'Message' => "Problem started at {EVENT.TIME} on {EVENT.DATE}\n".
									"Problem name: {EVENT.NAME}\n".
									"Host: {HOST.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Operational data: {EVENT.OPDATA}\n".
									"Original problem ID: {EVENT.ID}\n".
									"{TRIGGER.URL}"
						],
						[
							'Message type' => 'Problem recovery',
							'Subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => "Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
									"Problem name: {EVENT.NAME}\n".
									"Problem duration: {EVENT.DURATION}\n".
									"Host: {HOST.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Original problem ID: {EVENT.ID}\n".
									"{TRIGGER.URL}"
						],
						[
							'Message type' => 'Problem update',
							'Subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
							'Message' => "{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
									"{EVENT.UPDATE.MESSAGE}\n\n".
									"Current problem status is {EVENT.STATUS}, age is {EVENT.AGE}, acknowledged: {EVENT.ACK.STATUS}."
						],
						[
							'Message type' => 'Service',
							'Subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
							'Message' => "Service problem started at {EVENT.TIME} on {EVENT.DATE}\n".
									"Service problem name: {EVENT.NAME}\n".
									"Service: {SERVICE.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Original problem ID: {EVENT.ID}\n".
									"Service description: {SERVICE.DESCRIPTION}\n\n".
									"{SERVICE.ROOTCAUSE}"
						],
						[
							'Message type' => 'Service recovery',
							'Subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => 'Service "{SERVICE.NAME}" has been resolved at {EVENT.RECOVERY.TIME} on '.
									"{EVENT.RECOVERY.DATE}\nProblem name: {EVENT.NAME}\n".
									"Problem duration: {EVENT.DURATION}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Original problem ID: {EVENT.ID}\n".
									"Service description: {SERVICE.DESCRIPTION}"
						],
						[
							'Message type' => 'Service update',
							'Subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
							'Message' => "Changed \"{SERVICE.NAME}\" service status to {EVENT.UPDATE.SEVERITY} at ".
									"{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
									"Current problem age is {EVENT.AGE}.\n".
									"Service description: {SERVICE.DESCRIPTION}\n\n".
									"{SERVICE.ROOTCAUSE}"
						],
						[
							'Message type' => 'Discovery',
							'Subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
							'Message' => "Discovery rule: {DISCOVERY.RULE.NAME}\n\n".
									"Device IP: {DISCOVERY.DEVICE.IPADDRESS}\n".
									"Device DNS: {DISCOVERY.DEVICE.DNS}\n".
									"Device status: {DISCOVERY.DEVICE.STATUS}\n".
									"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\n".
									"Device service name: {DISCOVERY.SERVICE.NAME}\n".
									"Device service port: {DISCOVERY.SERVICE.PORT}\n".
									"Device service status: {DISCOVERY.SERVICE.STATUS}\n".
									"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
						],
						[
							'Message type' => 'Autoregistration',
							'Subject' => 'Autoregistration: {HOST.HOST}',
							'Message' => "Host name: {HOST.HOST}\n".
									"Host IP: {HOST.IP}\n".
									"Agent port: {HOST.PORT}"
						],
						[
							'Message type' => 'Internal problem',
							'Subject' => '',
							'Message' => ''
						],
						[
							'Message type' => 'Internal problem recovery',
							'Subject' => '',
							'Message' => ''
						]
					]
				]
			],
			// Default messages for HTML text Email media type
			[
				[
					'media_type' => [
						'Name' => 'Email HTML default values',
						'Type' => 'Email',
						'Message format' => 'HTML'
					],
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Subject' => 'Problem: {EVENT.NAME}',
							'Message' => '<b>Problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br><b>Problem name:</b> '.
									'{EVENT.NAME}<br><b>Host:</b> {HOST.NAME}<br><b>Severity:</b> {EVENT.SEVERITY}<br>'.
									'<b>Operational data:</b> {EVENT.OPDATA}<br><b>Original problem ID:</b> '.
									'{EVENT.ID}<br>{TRIGGER.URL}'

						],
						[
							'Message type' => 'Problem recovery',
							'Subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => '<b>Problem has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
									'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br><b>Problem '.
									'duration:</b> {EVENT.DURATION}<br><b>Host:</b> {HOST.NAME}<br><b>Severity:</b> '.
									'{EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>{TRIGGER.URL}'


						],
						[
							'Message type' => 'Problem update',
							'Subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
							'Message' => '<b>{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem</b> at {EVENT.UPDATE.DATE} '.
									'{EVENT.UPDATE.TIME}.<br>{EVENT.UPDATE.MESSAGE}<br><br><b>Current problem status:'.
									'</b> {EVENT.STATUS}<br><b>Age:</b> {EVENT.AGE}<br><b>Acknowledged:</b> {EVENT.ACK.STATUS}.'
						],
						[
							'Message type' => 'Service',
							'Subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
							'Message' => '<b>Service problem started</b> at {EVENT.TIME} on {EVENT.DATE}'.
									'<br><b>Service problem name:</b> {EVENT.NAME}<br><b>Service:</b> '.
									'{SERVICE.NAME}<br><b>Severity:</b> {EVENT.SEVERITY}<br><b>Original '.
									'problem ID:</b> {EVENT.ID}<br><b>Service description:</b> {SERVICE.DESCRIPTION}<br>'.
									'<br>{SERVICE.ROOTCAUSE}'
						],
						[
							'Message type' => 'Service recovery',
							'Subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => '<b>Service "{SERVICE.NAME}" has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
									'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br>'.
									'<b>Problem duration:</b> {EVENT.DURATION}<br>'.
									'<b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>'.
									'<b>Service description:</b> {SERVICE.DESCRIPTION}'
						],
						[
							'Message type' => 'Service update',
							'Subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
							'Message' => '<b>Changed "{SERVICE.NAME}" service status</b> to {EVENT.UPDATE.SEVERITY} at '.
									'{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br><b>Current problem age</b> is {EVENT.AGE}.'.
									'<br><b>Service description:</b> {SERVICE.DESCRIPTION}<br><br>{SERVICE.ROOTCAUSE}'
						],
						[
							'Message type' => 'Discovery',
							'Subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
							'Message' => '<b>Discovery rule:</b> {DISCOVERY.RULE.NAME}<br><br><b>Device IP:</b> '.
									'{DISCOVERY.DEVICE.IPADDRESS}<br><b>Device DNS:</b> {DISCOVERY.DEVICE.DNS}<br>'.
									'<b>Device status:</b> {DISCOVERY.DEVICE.STATUS}<br><b>Device uptime:</b> '.
									'{DISCOVERY.DEVICE.UPTIME}<br><br><b>Device service name:</b> {DISCOVERY.SERVICE.NAME}'.
									'<br><b>Device service port:</b> {DISCOVERY.SERVICE.PORT}<br><b>Device service '.
									'status:</b> {DISCOVERY.SERVICE.STATUS}<br><b>Device service uptime:</b> '.
									'{DISCOVERY.SERVICE.UPTIME}'
						],
						[
							'Message type' => 'Autoregistration',
							'Subject' => 'Autoregistration: {HOST.HOST}',
							'Message' => '<b>Host name:</b> {HOST.HOST}<br><b>Host IP:</b> {HOST.IP}<br><b>Agent port:'.
									'</b> {HOST.PORT}'
						],
						[
							'Message type' => 'Internal problem',
							'Subject' => '',
							'Message' => ''
						],
						[
							'Message type' => 'Internal problem recovery',
							'Subject' => '',
							'Message' => ''
						]
					]
				]
			],
			// Default messages for SMS media type
			[
				[
					'media_type' => [
						'Name' => 'SMS default values',
						'Type' => 'SMS'
					],
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Message' => "{EVENT.SEVERITY}: {EVENT.NAME}\n".
									"Host: {HOST.NAME}\n".
									"{EVENT.DATE} {EVENT.TIME}"
						],
						[
							'Message type' => 'Problem recovery',
							'Message' => "Resolved in {EVENT.DURATION}: {EVENT.NAME}\n".
									"Host: {HOST.NAME}\n".
									"{EVENT.DATE} {EVENT.TIME}"
						],
						[
							'Message type' => 'Problem update',
							'Message' => '{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem in {EVENT.AGE} at '.
									'{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}'
						],
						[
							'Message type' => 'Service',
							'Message' => "{EVENT.NAME}\n".
									'{EVENT.DATE} {EVENT.TIME}'
						],
						[
							'Message type' => 'Service recovery',
							'Message' => "{EVENT.NAME}\n".
									'{EVENT.DATE} {EVENT.TIME}'
						],
						[
							'Message type' => 'Service update',
							'Message' => "{EVENT.NAME}\n".
									'{EVENT.DATE} {EVENT.TIME}'
						],
						[
							'Message type' => 'Discovery',
							'Message' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}'
						],
						[
							'Message type' => 'Autoregistration',
							'Message' => "Autoregistration: {HOST.HOST}\n".
									"Host IP: {HOST.IP}\n".
									"Agent port: {HOST.PORT}"
						],
						[
							'Message type' => 'Internal problem',
							'Message' => ''
						],
						[
							'Message type' => 'Internal problem recovery',
							'Message' => ''
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getDefaultMessageTemplateData
	 */
	public function testFormAdministrationMediaTypeMessageTemplates_DefaultMessageContent($data) {
		// Open a new media type configuration form and switch to Message templates tab.
		$this->openMediaTypeTemplates('new', $data['media_type']);
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();
		// Add each type of message template and check the values of default messages.
		$last = count($data['message_templates']) - 1;
		foreach ($data['message_templates'] as $i => $template) {
			$templates_list->query('button:Add')->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();

			// Check the fields available in the message template configuration form.
			$form = $this->query('id:mediatype_message_form')->asForm()->one();
			$form->checkValue($template);
			if ($data['media_type']['Type'] === 'SMS') {
				$this->assertFalse($form->query('id:subject')->one(false)->isValid());
			}
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$templates_list->waitUntilReady()->invalidate();

			// Check that the number of rows has increased after adding previously checked message template.
			$this->assertEquals($i + 2, $templates_list->getRows()->count());
			$row = $templates_list->findRow('Message type', $template['Message type']);
			// Convert the reference message to a single line and compare it with the message added to message templates.
			$message = preg_replace('/[\r\n ]+/', ' ', $template['Message']);
			$this->assertEquals($message, $row->getColumn('Template')->getText());

			// Check that it is no logner possible to add same type of message template.
			$templates_list->invalidate();
			if ($i === $last) {
				$this->assertFalse($templates_list->query('button:Add (message type limit reached)')->one()->isEnabled());
			}
			else {
				$templates_list->query('button:Add')->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
				$form->invalidate();
				$disabled_options = $form->getField('Message type')->getOptions()->filter(
						new CElementFilter(CElementFilter::ATTRIBUTES_PRESENT, ['disabled'])
				)->asText();
				$this->assertContains($template['Message type'], $disabled_options);
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	public function getUpdateMessageTemplateData() {
		return [
			// Change message format from plain text to HTML
			[
				[
					'media_type' => 'Email',
					'media_type_fields' => [
						'Message format' => 'HTML'
					],
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Subject' => 'Problem: {EVENT.NAME}',
							'Message' => '<b>Problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br><b>Problem name:</b> '.
									'{EVENT.NAME}<br><b>Host:</b> {HOST.NAME}<br><b>Severity:</b> {EVENT.SEVERITY}<br>'.
									'<b>Operational data:</b> {EVENT.OPDATA}<br><b>Original problem ID:</b> '.
									'{EVENT.ID}<br>{TRIGGER.URL}'

						],
						[
							'Message type' => 'Problem recovery',
							'Subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => '<b>Problem has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
									'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br><b>Problem '.
									'duration:</b> {EVENT.DURATION}<br><b>Host:</b> {HOST.NAME}<br><b>Severity:</b> '.
									'{EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br>{TRIGGER.URL}'


						],
						[
							'Message type' => 'Problem update',
							'Subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
							'Message' => '<b>{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem</b> at {EVENT.UPDATE.DATE} '.
									'{EVENT.UPDATE.TIME}.<br>{EVENT.UPDATE.MESSAGE}<br><br><b>Current problem status:'.
									'</b> {EVENT.STATUS}<br><b>Age:</b> {EVENT.AGE}<br><b>Acknowledged:</b> {EVENT.ACK.STATUS}.'
						],
						[
							'Message type' => 'Discovery',
							'Subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
							'Message' => '<b>Discovery rule:</b> {DISCOVERY.RULE.NAME}<br><br><b>Device IP:</b> '.
									'{DISCOVERY.DEVICE.IPADDRESS}<br><b>Device DNS:</b> {DISCOVERY.DEVICE.DNS}<br>'.
									'<b>Device status:</b> {DISCOVERY.DEVICE.STATUS}<br><b>Device uptime:</b> '.
									'{DISCOVERY.DEVICE.UPTIME}<br><br><b>Device service name:</b> {DISCOVERY.SERVICE.NAME}'.
									'<br><b>Device service port:</b> {DISCOVERY.SERVICE.PORT}<br><b>Device service '.
									'status:</b> {DISCOVERY.SERVICE.STATUS}<br><b>Device service uptime:</b> '.
									'{DISCOVERY.SERVICE.UPTIME}'
						],
						[
							'Message type' => 'Autoregistration',
							'Subject' => 'Autoregistration: {HOST.HOST}',
							'Message' => '<b>Host name:</b> {HOST.HOST}<br><b>Host IP:</b> {HOST.IP}<br><b>Agent port:'.
									'</b> {HOST.PORT}'
						]
					]
				]
			],
			// Change message format from plain text to HTML for "Email (HTML) Service"
			[
				[
					'media_type' => 'Email (HTML) Service',
					'media_type_fields' => [
						'Message format' => 'HTML'
					],
					'message_templates' => [
						[
							'Message type' => 'Service',
							'Subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
							'Message' => "Service problem started at {EVENT.TIME} on {EVENT.DATE}\n".
									"Service problem name: {EVENT.NAME}\n".
									"Service: {SERVICE.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Original problem ID: {EVENT.ID}\n\n".
									"{SERVICE.ROOTCAUSE}"
						],
						[
							'Message type' => 'Service recovery',
							'Subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => 'Service "{SERVICE.NAME}" has been resolved at {EVENT.RECOVERY.TIME} on '.
									"{EVENT.RECOVERY.DATE}\nProblem name: {EVENT.NAME}\nProblem duration: {EVENT.DURATION}\n".
									"Severity: {EVENT.SEVERITY}\nOriginal problem ID: {EVENT.ID}"
						],
						[
							'Message type' => 'Service update',
							'Subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
							'Message' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} at '.
									"{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\nCurrent problem age is {EVENT.AGE}.\n\n".
									"{SERVICE.ROOTCAUSE}"
						]
					]
				]
			],
			// Change message format from HTML to plain text for "Email (HTML) Service"
			[
				[
					'media_type' => 'Email (HTML) Service',
					'media_type_fields' => [
						'Message format' => 'Plain text'
					],
					'message_templates' => [
						[
							'Message type' => 'Service',
							'Subject' => 'Service "{SERVICE.NAME}" problem: {EVENT.NAME}',
							'Message' => '<b>Service problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br>'.
									'<b>Service problem name:</b> {EVENT.NAME}<br><b>Service:</b> {SERVICE.NAME}<br>'.
									'<b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}<br><br>'.
									'{SERVICE.ROOTCAUSE}'
						],
						[
							'Message type' => 'Service recovery',
							'Subject' => 'Service "{SERVICE.NAME}" resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => '<b>Service "{SERVICE.NAME}" has been resolved</b> at {EVENT.RECOVERY.TIME} on '.
									'{EVENT.RECOVERY.DATE}<br><b>Problem name:</b> {EVENT.NAME}<br><b>Problem duration:</b> '.
									'{EVENT.DURATION}<br><b>Severity:</b> {EVENT.SEVERITY}<br><b>Original problem ID:</b> {EVENT.ID}'
						],
						[
							'Message type' => 'Service update',
							'Subject' => 'Changed "{SERVICE.NAME}" service status to {EVENT.UPDATE.SEVERITY} in {EVENT.AGE}',
							'Message' => '<b>Changed "{SERVICE.NAME}" service status</b> to {EVENT.UPDATE.SEVERITY} at '.
									'{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br><b>Current problem age</b> is '.
									'{EVENT.AGE}.<br><br>{SERVICE.ROOTCAUSE}'
						]
					]
				]
			],
			// Change message format from HTML to plain text
			[
				[
					'media_type' => 'Email (HTML)',
					'media_type_fields' => [
						'Message format' => 'Plain text'
					],
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Subject' => 'Problem: {EVENT.NAME}',
							'Message' => "Problem started at {EVENT.TIME} on {EVENT.DATE}\n".
									"Problem name: {EVENT.NAME}\n".
									"Host: {HOST.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Operational data: {EVENT.OPDATA}\n".
									"Original problem ID: {EVENT.ID}\n".
									"{TRIGGER.URL}"
						],
						[
							'Message type' => 'Problem recovery',
							'Subject' => 'Resolved in {EVENT.DURATION}: {EVENT.NAME}',
							'Message' => "Problem has been resolved at {EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n".
									"Problem name: {EVENT.NAME}\n".
									"Problem duration: {EVENT.DURATION}\n".
									"Host: {HOST.NAME}\n".
									"Severity: {EVENT.SEVERITY}\n".
									"Original problem ID: {EVENT.ID}\n".
									"{TRIGGER.URL}"
						],
						[
							'Message type' => 'Problem update',
							'Subject' => 'Updated problem in {EVENT.AGE}: {EVENT.NAME}',
							'Message' => "{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at {EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n".
									"{EVENT.UPDATE.MESSAGE}\n\n".
									"Current problem status is {EVENT.STATUS}, age is {EVENT.AGE}, acknowledged: {EVENT.ACK.STATUS}."
						],
						[
							'Message type' => 'Discovery',
							'Subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
							'Message' => "Discovery rule: {DISCOVERY.RULE.NAME}\n\n".
									"Device IP: {DISCOVERY.DEVICE.IPADDRESS}\n".
									"Device DNS: {DISCOVERY.DEVICE.DNS}\n".
									"Device status: {DISCOVERY.DEVICE.STATUS}\n".
									"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n\n".
									"Device service name: {DISCOVERY.SERVICE.NAME}\n".
									"Device service port: {DISCOVERY.SERVICE.PORT}\n".
									"Device service status: {DISCOVERY.SERVICE.STATUS}\n".
									"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
						],
						[
							'Message type' => 'Autoregistration',
							'Subject' => 'Autoregistration: {HOST.HOST}',
							'Message' => "Host name: {HOST.HOST}\n".
									"Host IP: {HOST.IP}\n".
									"Agent port: {HOST.PORT}"
						]
					]
				]
			],
			// Update existing message templates for "Email Service".
			[
				[
					'media_type' => 'Email Service',
					'message_templates' => [
						[
							'Message type' => 'Service',
							'Subject' => 'New service subject !@#$%^&*()_+ōš六書',
							'Message' => 'New service message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Service recovery',
							'Subject' => 'New service recovery subject !@#$%^&*()_+ōš六書',
							'Message' => 'New service recovery message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Service update',
							'Subject' => 'New service update subject !@#$%^&*()_+ōš六書',
							'Message' => 'New service update message !@#$%^&*()_+ōš六書'
						]
					]
				]
			],
			// Update existing message templates for Email media type.
			[
				[
					'media_type' => 'Email',
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Subject' => 'New problem subject !@#$%^&*()_+ōš六書',
							'Message' => 'New problem message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Problem recovery',
							'Subject' => 'New problem recovery subject !@#$%^&*()_+ōš六書',
							'Message' => 'New problem recovery message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Problem update',
							'Subject' => 'New problem update subject !@#$%^&*()_+ōš六書',
							'Message' => 'New problem update message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Discovery',
							'Subject' => 'New discovery subject !@#$%^&*()_+ōš六書',
							'Message' => 'New discovery message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Autoregistration',
							'Subject' => 'New autoregistration subject !@#$%^&*()_+ōš六書',
							'Message' => 'New autoregistration message !@#$%^&*()_+ōš六書'
						]
					]
				]
			],
			// Update existing message templates for "SMS Service".
			[
				[
					'media_type' => 'SMS Service',
					'message_templates' => [
						[
							'Message type' => 'Service',
							'Message' => 'New service SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Service recovery',
							'Message' => 'New service recovery SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Service update',
							'Message' => 'New service update SMS !@#$%^&*()_+ōš六書'
						]
					]
				]
			],
			// Update existing message templates for SMS media type.
			[
				[
					'media_type' => 'SMS',
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'Message' => 'New problem SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Problem recovery',
							'Message' => 'New problem recovery SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Problem update',
							'Message' => 'New problem update SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Discovery',
							'Message' => 'New discovery SMS !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Autoregistration',
							'Message' => 'New autoregistration SMS !@#$%^&*()_+ōš六書'
						]
					]
				]
			],
			// Remove all message templates.
			[
				[
					'media_type' => 'Email',
					'remove_all' => true
				]
			],
			// Remove part of old and add new message templates.
			[
				[
					'media_type' => 'Email (HTML)',
					'message_templates' => [
						[
							'Message type' => 'Problem',
							'action' => 'Remove temporary'
						],
						[
							'Message type' => 'Problem recovery',
							'action' => 'Remove'
						],
						[
							'Message type' => 'Problem update',
							'action' => 'Remove'
						],
						[
							'Message type' => 'Problem',
							'action' => 'Add',
							'Subject' => 'New problem subject !@#$%^&*()_+ōš六書',
							'Message' => 'New problem message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Internal problem',
							'action' => 'Add',
							'Subject' => 'New internal problem subject !@#$%^&*()_+ōš六書',
							'Message' => 'New internal problem message !@#$%^&*()_+ōš六書'
						],
						[
							'Message type' => 'Discovery',
							'action' => 'Skip',
							'Subject' => 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}',
							'Message' => '<b>Discovery rule:</b> {DISCOVERY.RULE.NAME}<br><br><b>Device IP:</b> '.
									'{DISCOVERY.DEVICE.IPADDRESS}<br><b>Device DNS:</b> {DISCOVERY.DEVICE.DNS}<br>'.
									'<b>Device status:</b> {DISCOVERY.DEVICE.STATUS}<br><b>Device uptime:</b> '.
									'{DISCOVERY.DEVICE.UPTIME}<br><br><b>Device service name:</b> {DISCOVERY.SERVICE.NAME}'.
									'<br><b>Device service port:</b> {DISCOVERY.SERVICE.PORT}<br><b>Device service '.
									'status:</b> {DISCOVERY.SERVICE.STATUS}<br><b>Device service uptime:</b> '.
									'{DISCOVERY.SERVICE.UPTIME}'
						],
						[
							'Message type' => 'Autoregistration',
							'action' => 'Skip',
							'Subject' => 'Autoregistration: {HOST.HOST}',
							'Message' => '<b>Host name:</b> {HOST.HOST}<br><b>Host IP:</b> {HOST.IP}<br><b>Agent port:'.
									'</b> {HOST.PORT}'
						]
					],
					'rows' => 4
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateMessageTemplateData
	 * @backup media_type
	 */
	public function testFormAdministrationMediaTypeMessageTemplates_Update($data) {
		// Open configuration of an existing media type, update its format if needed and switch to Message templates tab.
		$this->openMediaTypeTemplates($data['media_type'], CTestArrayHelper::get($data, 'media_type_fields'));
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();
		// Edit the list of existing message templates or remove all message templates if corresponding key exists.
		if (array_key_exists('remove_all', $data)) {
			$templates_list->query('button:Remove')->all()->click();
		}
		else {
			$this->modifyMessageTemplates($data);
		}
		$this->query('id:media-type-form')->asForm()->one()->submit();
		// Open message template list of the edited media type and check that message template updates took place.
		$this->query('link', $data['media_type'])->one()->WaitUntilClickable()->click();
		$media_form = $this->query('id:media-type-form')->asForm()->one();
		$media_form->selectTab('Message templates');
		$templates_list->invalidate();
		if (array_key_exists('remove_all', $data)) {
			// Check that only the row with "Add" button is present after removing all message templates.
			$this->assertEquals(1, $templates_list->getRows()->count());
		}
		else {
			$this->assertEquals(CTestArrayHelper::get($data, 'rows', count($data['message_templates'])),
					$templates_list->getRows()->count() - 1
			);
			$this->checkMessageTemplates($data, $templates_list);
		}
	}

	public function testFormAdministrationMediaTypeMessageTemplates_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);
		// Update "Problem" message template of Email media type without making any changes.
		$this->openMediaTypeTemplates('Email');
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();
		$templates_list->findRow('Message type', 'Problem')->query('button:Edit')->one()->click();
		$this->query('id:mediatype_message_form')->waitUntilVisible()->asForm()->one()->submit();
		$this->query('id:media-type-form')->asForm()->one()->submit();

		// Check that no DB changes took place.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testFormAdministrationMediaTypeMessageTemplates_Cancel() {
		$old_hash = CDBHelper::getHash($this->sql);
		// Open Email (HTML) media type, modify "Problem" message template and remove "Discovery" message template.
		$this->openMediaTypeTemplates('Email (HTML)');
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();

		$templates_list->findRow('Message type', 'Problem')->query('button:Edit')->one()->click();
		$form = $this->query('id:mediatype_message_form')->waitUntilVisible()->asForm()->one();
		$form->fill([
			'Message type' => 'Internal problem',
			'Subject' => 'New subject',
			'Message' => 'New message'
		])->submit();
		$templates_list->invalidate();
		$templates_list->findRow('Message type', 'Discovery')->query('button:Remove')->one()->click();
		// Cancel all previously made modifications.
		$this->query('button:Cancel')->one()->click();

		// Check that no DB changes took place.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	/**
	 * Function that modifies, adds or removes message templates according to the action defined in data provider.
	 */
	private function modifyMessageTemplates($data) {
		$templates_list = $this->query('id:messageTemplatesFormlist')->asTable()->one();
		foreach ($data['message_templates'] as $template) {
			switch (CTestArrayHelper::get($template, 'action', 'Edit')) {
				case 'Edit':
					$templates_list->findRow('Message type', $template['Message type'])->query('button:Edit')->one()->click();
					$form = $this->query('id:mediatype_message_form')->waitUntilVisible()->asForm()->one();
					$form->fill($template);
					$form->submit();
					COverlayDialogElement::ensureNotPresent();
					break;
				case 'Add':
					$templates_list->query('button:Add')->waitUntilClickable()->one()->click();
					$form = $this->query('id:mediatype_message_form')->waitUntilVisible()->asForm()->one();
					unset($template['action']);
					$form->fill($template);
					$form->submit();
					COverlayDialogElement::ensureNotPresent();
					break;
				case 'Remove':
				case 'Remove temporary':
					$templates_list->findRow('Message type', $template['Message type'])->query('button:Remove')->one()->click();
					break;
			}
		}
	}

	/**
	 * Function that checks if the manipulations with the corresponding media type template took place.
	 */
	private function checkMessageTemplates($data, $templates_list) {
		foreach ($data['message_templates'] as $template) {
			$action = CTestArrayHelper::get($template, 'action', 'Edit');
			unset($template['action']);
			switch ($action) {
				case 'Edit':
				case 'Add':
				case 'Skip':
					// Open the corresponding message template and check its content according to the values in data provider.
					$templates_list->findRow('Message type', $template['Message type'])->query('button:Edit')->one()->click();
					COverlayDialogElement::find()->one()->waitUntilReady();
					$form = $this->query('id:mediatype_message_form')->asForm()->one();
					$form->checkValue($template);
					COverlayDialogElement::find()->one()->close();
					break;
				case 'Remove':
					// Check that the previously removed row is not present in message template list.
					$this->assertFalse($templates_list->findRow('Message type', $template['Message type'])->isValid());
					break;
			}
		}
	}

	/**
	 * Function creates new or opens existing media type, modifies media type parameters and opens message templates tab.
	 */
	private function openMediaTypeTemplates($media_type, $media_type_fields = null) {
		if ($media_type === 'new') {
			$this->page->login()->open('zabbix.php?action=mediatype.edit')->waitUntilReady();
		}
		else {
			$this->page->login()->open('zabbix.php?action=mediatype.list')->waitUntilReady();
			$this->query('link', $media_type)->one()->WaitUntilClickable()->click();
		}
		$media_form = $this->query('id:media-type-form')->asForm()->one();
		if ($media_type_fields !== null) {
			$media_form->fill($media_type_fields);
		}
		$media_form->selectTab('Message templates');
	}
}
