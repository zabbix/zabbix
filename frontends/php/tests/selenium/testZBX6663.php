<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testZBX6663 extends CWebTest {


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testZBX6663_Setup() {
		DBsave_tables('hosts');

		// Link template to host
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link=Simple form test host');

		$this->zbxTestClick('tab_templateTab');

		$this->assertElementPresent("//div[@id='templates_']/input");
		$this->input_type("//div[@id='templates_']/input", 'Inheritance test template');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClickWait('add_template');

		$this->zbxTestTextPresent('Inheritance test template');
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Host updated');

		// Link template to template
		$this->zbxTestOpen('templates.php');
		$this->zbxTestClickWait('link=Inheritance test template');

		$this->zbxTestClick('tab_templateTab');

		$this->assertElementPresent("//div[@id='templates_']/input");
		$this->input_type("//div[@id='templates_']/input", 'Template App MySQL');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClickWait('add_template');

		$this->input_type("//div[@id='templates_']/input", 'Template OS AIX');
		sleep(1);
		$this->zbxTestClick("//span[@class='matched']");
		$this->zbxTestClickWait('add_template');

		$this->zbxTestTextPresent(array('Template App MySQL', 'Template OS AIX'));
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Template updated');

		// Create graph prototype for the host
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickWait('link=Simple form test host');
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=testInheritanceDiscoveryRule');
		$this->zbxTestClickWait('link=Graph prototypes');

		$this->zbxTestClickWait('form');
		$this->input_type('name', 'testGraphPrototypeZBX6663');
		$this->zbxTestLaunchPopup('add_protoitem');
		$this->zbxTestClick("//span[text()='itemDiscovery']");
		sleep(1);
		$this->selectWindow(null);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Graph added');

		// Create graph prototype for the host
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickWait('link=Inheritance test template');
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Mounted filesystem discovery');
		$this->zbxTestClickWait('link=Graph prototypes');

		$this->zbxTestClickWait('form');
		$this->input_type('name', 'testGraphPrototypeInheritanceZBX6663');
		$this->zbxTestLaunchPopup('add_protoitem');
		$this->zbxTestClick("//span[text()='Free disk space on {#FSNAME}']");
		sleep(1);
		$this->selectWindow(null);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Graph added');

		// Add web scenario to the template
		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link=Template App MySQL');
		$this->zbxTestClickWait('link=Web scenarios');

		$this->zbxTestClickWait('form');
		$this->input_type('name', 'testWebZBX6663');
		$this->zbxTestClick('tab_stepTab');
		$this->zbxTestClick('add_step');
		$this->waitForPopUp('zbx_popup', 6000);
		$this->selectWindow('zbx_popup');
		$this->zbxTestCheckFatalErrors();
		$this->input_type('name','testWebZBX6663 step');
		$this->input_type('url', 'testWebZBX6663 url');
		$this->zbxTestClick('save');
		$this->selectWindow(null);
		$this->wait();
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Scenario added');
	}

	// Returns test data
	public static function zbx_data() {
		return array(
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Applications',
					'checkbox' => 'applications'
				)
			),
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Items',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'templated' => 'Inheritance test template',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template OS Linux',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template OS Linux',
					'discoveryRule' => 'Mounted filesystem discovery',
					'link' => 'Item prototypes',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'host' => 'ЗАББИКС Сервер',
					'templated' => 'Template OS Linux',
					'discoveryRule' => 'Mounted filesystem discovery',
					'link' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'templated' => 'Inheritance test template',
					'discoveryRule' => 'testInheritanceDiscoveryRule',
					'link' => 'Graph prototypes',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'templated' => 'Inheritance test template',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				)
			),
			array(
				array(
					'template' => 'Template OS AIX',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Applications',
					'checkbox' => 'applications'
				)
			),
			array(
				array(
					'template' => 'Template OS AIX',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Items',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Template OS AIX',
					'templated' => 'Template App Zabbix Agent',
					'link' => 'Triggers',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template App MySQL',
					'link' => 'Graphs',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template OS AIX',
					'link' => 'Discovery rules',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template OS AIX',
					'discoveryRule' => 'Mounted filesystem discovery',
					'link' => 'Item prototypes',
					'checkbox' => 'items'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template OS AIX',
					'discoveryRule' => 'Mounted filesystem discovery',
					'link' => 'Trigger prototypes',
					'checkbox' => 'triggers'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template OS AIX',
					'discoveryRule' => 'Mounted filesystem discovery',
					'link' => 'Graph prototypes',
					'checkbox' => 'graphs'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'templated' => 'Template App MySQL',
					'link' => 'Web scenarios',
					'checkbox' => 'httptests'
				)
			)
		);
	}


	/**
	 * @dataProvider zbx_data
	 */
	public function testZBX6663_MassSelect($zbx_data) {

		$templated = $zbx_data['templated'];
		$link = $zbx_data['link'];
		$checkbox = $zbx_data['checkbox'];

		if (isset($zbx_data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$zbx_data['host']);
		}

		if (isset($zbx_data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$zbx_data['template']);
		}

		if (isset($zbx_data['discoveryRule'])) {
			$this->zbxTestClickWait('link=Discovery rules');
			$this->zbxTestClickWait('link='.$zbx_data['discoveryRule']);
		}

		$this->zbxTestClickWait("//div[@class='w']//a[text()='$link']");

		$this->assertVisible('//input[@value="Go (0)"]');
		$this->zbxTestCheckboxSelect("all_$checkbox");

		$this->zbxTestClickWait('link='.$templated);
		$this->assertVisible('//input[@value="Go (0)"]');
	}

	/**
	 * Restore the original tables.
	 */
	public function testZBX6663_Teardown() {
		DBrestore_tables('hosts');
	}
}
