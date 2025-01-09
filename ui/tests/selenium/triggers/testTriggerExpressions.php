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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

class testTriggerExpressions extends CWebTest {

	const TRIGGER_ID = 17094;		//'PHP-FPM: Pool has been restarted'

	public function testTriggerExpressions_SimpleTest() {
		// Open advanced editor for testing trigger expression results.
		$description = CDBHelper::getValue('SELECT description FROM triggers WHERE triggerid='.self::TRIGGER_ID);
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_set=1&context=template');
		$form = CFilterElement::find()->one()->getForm();
		$form->fill(['Name' => $description])->submit();
		$this->query('link', $description)->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->query('button:Expression constructor')->waitUntilPresent()->one()->click();
		$this->query('button:Test')->waitUntilPresent()->one()->click();

		$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		// Check table headers presence in tesing dialog.
		$table_headers = ['Expression variable elements', 'Result type', 'Value',
						'Expression', 'Result', 'Error'];

		foreach ($table_headers as $header) {
			$this->assertTrue($dialog->query('xpath://table//th[text() ="'.$header.'"]')->one()->isPresent());
		}

		// Type value in expression testing form.
		$dialog->query('xpath:.//input[@type="text"]')->waitUntilPresent()->one()->fill('20M');

		// Verify zabbix server connection error message.
		$dialog->query('button:Test')->one()->click();

		$message = $dialog->query('tag:output')->waitUntilPresent()->asMessage()->one();
		$this->assertTrue($message->isBad());
		$this->assertEquals('Cannot evaluate expression', $message->getTitle());

		$message_details = "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
				"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
				"2. Security environment (for example, SELinux) is blocking the connection;\n".
				"3. Zabbix server daemon not running;\n".
				"4. Firewall is blocking TCP connection.\n".
				"Connection refused";

		$this->assertTrue($message->hasLine($message_details));
	}
}
