<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageTemplatesWebScenario extends CWebTest {
	// Returns all templates
	public static function allTemplates() {
		return DBdata("select * from hosts where status in (".HOST_STATUS_TEMPLATE.')');
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplatesWebScenario_CheckLayout($template) {
		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'Templates');
		$this->checkTitle('Configuration of templates');
		$this->ok('TEMPLATES');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Templates', 'Applications', 'Items', 'Triggers', 'Graphs', 'Screens', 'Discovery', 'Web',
'Linked templates', 'Linked to'));
		// Data
		$this->ok(array($template['name']));
		$this->dropdown_select('go', 'Export selected');
		$this->dropdown_select('go', 'Delete selected');
		$this->dropdown_select('go', 'Delete selected with linked elements');
		}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplatesWebScenario_SimpleUpdate($template) {
		$host = $template['host'];
		$name = $template['name'];

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldHashItems = DBhash($sqlItems);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		$this->checkTitle('Configuration of templates');

		$this->ok($name);
		$this->button_click("link=$name");
		$this->wait();
		// Configuration of web monitoring scenario page
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click("name=form");
		$this->wait();

		// Label naming
		$this->ok(array('Host', 'Name', 'Application', 'New application', 'Authentication', 'Update interval (in sec)',
'Agent', 'Variables', 'Enabled'));

		// Form buttons
		$this->assertElementPresent('button_popup');
		$this->assertElementPresent('cancel');
		$this->assertElementPresent('save');

		// Form elements
		$this->assertElementPresent('hostname');
		$this->assertElementPresent('name');
		$this->assertElementPresent('applicationid');
		$this->assertElementPresent('new_application');
		$this->assertElementPresent('authentication');
		$this->assertElementPresent('delay');
		$this->assertElementPresent('agent');
		$this->assertElementPresent('macros');
		$this->assertElementPresent('status');
		$this->assertElementPresent('tab_stepTab');

		// Elements' attributes
		$this->assertAttribute("//input[@id='hostname']/@size", 50);
		$this->assertAttribute("//input[@id='hostname']/@value", $name);
		$this->assertAttribute("//input[@id='name']/@maxlength", 64);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='new_application']/@maxlength", 255);
		$this->assertAttribute("//input[@id='new_application']/@size", 50);
		$this->assertAttribute("//input[@id='delay']/@maxlength", 5);
		$this->assertAttribute("//input[@id='delay']/@size", 5);

		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");

		// Data
		sleep(1);
		$this->assertElementPresent("//span[text()='".$name."']");
		$this->assertElementPresent('groupid');
		$this->close();
		$this->selectWindow("null");

		// Steps tab
		$this->button_click('tab_stepTab');

		// Form buttons steps tab
		$this->assertElementPresent('add_step');
		$this->assertElementPresent('cancel');
		$this->assertElementPresent('save');

		// Header
		$this->ok(array('Name', 'Timeout', 'URL', 'Required', 'Status codes'));
		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");

		// Label naming
		$this->ok(array('Name', 'URL', 'Post', 'Timeout', 'Required string', 'Required status codes'));

		// Form buttons
		$this->assertElementPresent('cancel');
		$this->assertElementPresent('save');

		// Form elements
		$this->assertElementPresent('name');
		$this->assertElementPresent('url');
		$this->assertElementPresent('posts');
		$this->assertElementPresent('timeout');
		$this->assertElementPresent('required');
		$this->assertElementPresent('status_codes');

		// Elements' attributes
		$this->assertAttribute("//input[@id='name']/@maxlength", 64);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='url']/@maxlength", 255);
		$this->assertAttribute("//input[@id='url']/@size", 50);
		$this->assertAttribute("//input[@id='timeout']/@maxlength", 5);
		$this->assertAttribute("//input[@id='timeout']/@size", 5);
		$this->assertAttribute("//input[@id='required']/@maxlength", 255);
		$this->assertAttribute("//input[@id='required']/@size", 50);
		$this->assertAttribute("//input[@id='status_codes']/@maxlength", 255);
		$this->assertAttribute("//input[@id='status_codes']/@size", 50);

		$this->button_click('cancel');
		$this->selectWindow("null");

		// Main window
		$this->button_click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of templates');
		$this->ok('Template updated');
		$this->ok("$name");
		$this->ok('TEMPLATES');

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertEquals($oldHashItems, DBhash($sqlItems));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}



	public function testPageTemplatesWebScenario_Create() {
		$host = 'Template App Agentless';

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldCountItems = DBcount($sqlItems);
		$sqlHttptests = "select * from httptest order by httptestid";
		$oldCountHttptests = DBcount($sqlHttptests);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);


		$host2 = 'Template App Zabbix Server';

		$sqlTemplate2 = "select * from hosts where host='$host2'";
		$oldHashTemplate2 = DBhash($sqlTemplate2);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		$this->button_click("link=Template App Agentless");
		$this->wait();
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		// Add scenario
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Agentless");
		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Template App Agentless']");
		$this->selectWindow(null);
		$this->wait();
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Agentless");

		$this->input_type('id=name','MyWebScenario');
		$this->button_click('tab_stepTab');

		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();
		$this->ok("MyWebStep");
		$this->ok("MyWebLink");
		$this->button_click('save');
		$this->wait();

		// Confirms that Web Scenario is added OK
		$this->ok("Scenario added");
		$this->ok("MyWebScenario");
		$this->ok("Enabled");
		$this->ok("Displaying 1 to 1 of 1 found");

		// Add another scenario
		$this->button_click("link=Template list");
		$this->wait();
		$this->button_click("link=Template App Zabbix Server");
		$this->wait();
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Server");
		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Template App Zabbix Server']");
		$this->selectWindow(null);
		$this->wait();
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Server");

		$this->input_type('id=name','MyWebScenario');
		$this->button_click('tab_stepTab');

		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();
		$this->ok("MyWebStep");
		$this->ok("MyWebLink");
		$this->button_click('save');
		$this->wait();

		// Confirms that Web Scenario is added OK
		$this->ok("Scenario added");
		$this->ok("MyWebScenario");
		$this->ok("Enabled");
		$this->ok("Displaying 1 to 1 of 1 found");

		$this->button_click("link=Template list");
		$this->wait();
		$this->button_click("xpath=(//a[contains(text(),'ЗАББИКС Сервер')])[1]");
		$this->wait();
		$this->ok("Web scenarios (1)");
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->ok("Template App Zabbix Server: MyWebScenario");

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertNotEquals($oldCountItems, DBcount($sqlItems));
		$this->assertNotEquals($oldCountHttptests, DBcount($sqlHttptests));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
		$this->assertEquals($oldHashTemplate2, DBhash($sqlTemplate2));
}

	public function testPageTemplatesWebScenario_CreateCheck() {
		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		$this->button_click("link=Template App Agentless");
		$this->wait();
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click("link=MyWebScenario");
		$this->wait();

		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Agentless");
		$this->assertAttribute("//input[@id='name']/@value", "MyWebScenario");
		$this->button_click('tab_stepTab');
		$this->button_click("//span[text()='MyWebStep']");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->assertAttribute("//input[@id='name']/@value", "MyWebStep");
		$this->assertAttribute("//input[@id='url']/@value", "MyWebLink");
		$this->assertAttribute("//input[@id='timeout']/@value", "15");
		$this->button_click('cancel');
}
	public function testPageTemplatesWebScenario_CreateMass() {

		$host = 'Template App Zabbix Agent';

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldCountItems = DBcount($sqlItems);
		$sqlHttptests = "select * from httptest order by httptestid";
		$oldCountHttptests = DBcount($sqlHttptests);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		// Add scenarios for DeleteAll
		$this->button_click("link=Template App Zabbix Agent");
		$this->wait();
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");
		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Template App Zabbix Agent']");
		$this->selectWindow(null);
		$this->wait();
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");

		$this->input_type('id=name','MyWebScenario1');
		$this->button_click('tab_stepTab');

		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();
		$this->ok("MyWebStep");
		$this->ok("MyWebLink");
		$this->button_click('save');
		$this->wait();

		// Confirms that Web Scenario is added OK
		$this->ok("Scenario added");
		$this->ok("MyWebScenario1");
		$this->ok("Enabled");
		$this->ok("Displaying 1 to 1 of 1 found");

		$this->button_click('form');
		$this->wait();

		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");
		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Template App Zabbix Agent']");
		$this->selectWindow(null);
		$this->wait();
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");

		$this->input_type('id=name','MyWebScenario2');
		$this->button_click('tab_stepTab');

		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();
		$this->ok("MyWebStep");
		$this->ok("MyWebLink");
		$this->button_click('save');
		$this->wait();

		// Confirms that Web Scenario is added OK
		$this->ok("Scenario added");
		$this->ok("MyWebScenario2");
		$this->ok("Enabled");
		$this->ok("Displaying 1 to 2 of 2 found");

		$this->button_click('form');
		$this->wait();

		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");
		$this->button_click('button_popup');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Template App Zabbix Agent']");
		$this->selectWindow(null);
		$this->wait();
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App Zabbix Agent");

		$this->input_type('id=name','MyWebScenario3');
		$this->button_click('tab_stepTab');

		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();
		$this->ok("MyWebStep");
		$this->ok("MyWebLink");
		$this->button_click('save');
		$this->wait();

		// Confirms that Web Scenario is added OK
		$this->ok("Scenario added");
		$this->ok("MyWebScenario3");
		$this->ok("Enabled");
		$this->ok("Displaying 1 to 3 of 3 found");

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertNotEquals($oldCountItems, DBcount($sqlItems));
		$this->assertNotEquals($oldCountHttptests, DBcount($sqlHttptests));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	public function testPageTemplatesWebScenario_InccorectCreate() {

		$host = 'Template App MySQL';

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldHashItems = DBhash($sqlItems);
		$sqlHttptests = "select * from httptest order by httptestid";
		$oldHashHttptests = DBhash($sqlHttptests);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		// Check for error popups and messages
		$this->button_click("link=Template App MySQL");
		$this->wait();
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		// Scenario tab
		$this->assertAttribute("//input[@id='hostname']/@value", "Template App MySQL");
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Name": cannot be empty.');
		$this->ok('Warning. Field "Steps" is mandatory.');

		$this->input_type('id=name','MyWebScenario');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');

		$this->input_type('id=delay','-1');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Update interval (in sec)": must be between 1 and 86400.');

		$this->input_type('id=delay','99999');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Update interval (in sec)": must be between 1 and 86400.');

		$this->input_type('id=delay','86401');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Update interval (in sec)": must be between 1 and 86400.');

		$this->input_type('id=delay','0');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Update interval (in sec)": must be between 1 and 86400.');

		$this->input_type('id=delay','1');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');

		$this->input_type('id=delay','86400');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');

		$this->dropdown_select_wait('authentication', 'Basic authentication');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "User": cannot be empty.');
		$this->ok('Warning. Incorrect value for field "Password": cannot be empty.');

		$this->input_type('id=http_password','Psw');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "User": cannot be empty.');

		$this->input_type('id=http_password','');
		$this->input_type('id=http_user','Usr');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Password": cannot be empty.');

		$this->dropdown_select_wait('authentication', 'NTLM authentication');
		$this->input_type('id=http_password','');
		$this->input_type('id=http_user','');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "User": cannot be empty.');
		$this->ok('Warning. Incorrect value for field "Password": cannot be empty.');

		$this->input_type('id=http_password','Password');
		$this->input_type('id=http_user','');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "User": cannot be empty.');

		$this->input_type('id=http_password','');
		$this->input_type('id=http_user','User');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');
		$this->ok('Warning. Incorrect value for field "Password": cannot be empty.');

		$this->dropdown_select_wait('authentication', 'None');

		// Steps tab
		$this->button_click('tab_stepTab');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Field "Steps" is mandatory.');

		// Steps pop-up
		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Name".');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=name','MyWebStep');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=timeout','0');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=timeout','65535');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=status_codes','1-4');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=status_codes','1-4,7-8');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=status_codes','1-4,12');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=status_codes','10,12');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "URL": cannot be empty.');

		$this->input_type('id=url','MyWebLink');
		$this->input_type('id=timeout','99999');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Timeout": must be between 0 and 65535.');

		$this->input_type('id=timeout','-1');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Timeout": must be between 0 and 65535.');

		$this->input_type('id=timeout','15');
		$this->input_type('status_codes','Code');
		$this->button_click('save');
		$this->wait();
		$this->ok('Warning. Field "status_codes" is not integer list or range.');

		$this->input_type('id=timeout','StringValue');
		$this->input_type('status_codes','Code');
		$this->button_click('save');
		$this->wait();
		$this->ok('Warning. Field "status_codes" is not integer list or range.');

		$this->input_type('status_codes','');
		$this->button_click('save');
		$this->selectWindow(null);
		$this->wait();

		// Steps pop-up
		$this->button_click('add_step');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");

		$this->input_type('id=name','MyWebStep');
		$this->input_type('id=url','MyWebLink');
		$this->button_click('save');
		sleep(5);
		$this->ok('ERROR: Step with name "MyWebStep" already exists.');
		$this->button_click('cancel');

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertEquals($oldHashItems, DBhash($sqlItems));
		$this->assertEquals($oldHashHttptests, DBhash($sqlHttptests));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	public function testPageTemplatesWebScenario_Delete() {
		$host = 'Template App Agentless';

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldCountItems = DBcount($sqlItems);
		$sqlHttptests = "select * from httptest order by httptestid";
		$oldCountHttptests = DBcount($sqlHttptests);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);


		$host2 = 'Template App Zabbix Server';

		$sqlTemplate2 = "select * from hosts where host='$host2'";
		$oldHashTemplate2 = DBhash($sqlTemplate2);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		// Deletes the added Web Scenario
		$this->button_click("xpath=(//a[contains(text(),'Web')])[3]");
		$this->wait();
		$this->ok(array('Name', 'Number of steps', 'Update interval', 'Status'));
		$this->ok('Web scenarios (1)');
		$this->checkbox_select("xpath=(//input[@type='checkbox'])[2]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();
		$this->ok("Web scenario deleted");
		$this->ok("No web scenarios defined.");
		$this->nok("MyWebScenario");
		$this->ok("Displaying 0");
		$this->waitForConfirmation("Delete selected WEB scenarios?");
		$this->chooseOkOnNextConfirmation();

		// Verify the Web Scenario has been deleted
		$this->button_click('link=Template list');
		$this->wait();
		$this->button_click("link=Template App Agentless");
		$this->wait();
		$this->nok("Web scenarios (1)");
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->ok("No web scenarios defined.");
		$this->assertElementNotPresent("xpath=(//input[@type='checkbox'])[2]");

		$this->button_click('link=Template list');
		$this->wait();

		// Deletes the added Web Scenario
		$this->button_click("xpath=(//a[contains(text(),'Web')])[6]");
		$this->wait();
		$this->ok(array('Name', 'Number of steps', 'Update interval', 'Status'));
		$this->ok('Web scenarios (1)');
		$this->checkbox_select("xpath=(//input[@type='checkbox'])[2]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();
		$this->ok("Web scenario deleted");
		$this->ok("No web scenarios defined.");
		$this->nok("MyWebScenario");
		$this->ok("Displaying 0");
		$this->waitForConfirmation("Delete selected WEB scenarios?");
		$this->chooseOkOnNextConfirmation();

		// Verify the Web Scenario has been deleted
		$this->button_click('link=Template list');
		$this->wait();
		$this->button_click("link=Template App Zabbix Server");
		$this->wait();
		$this->nok("Web scenarios (1)");
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->ok("No web scenarios defined.");
		$this->assertElementNotPresent("xpath=(//input[@type='checkbox'])[2]");

		$this->button_click("link=Template list");
		$this->wait();
		$this->button_click("xpath=(//a[contains(text(),'ЗАББИКС Сервер')])[1]");
		$this->wait();
		$this->nok("Web scenarios (1)");
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->nok("Template App Zabbix Server: MyWebScenario");

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertNotEquals($oldCountItems, DBcount($sqlItems));
		$this->assertNotEquals($oldCountHttptests, DBcount($sqlHttptests));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
		$this->assertEquals($oldHashTemplate2, DBhash($sqlTemplate2));
	}

	public function testPageTemplatesWebScenario_DeleteMass() {
		$host = 'Template App Zabbix Agent';

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldCountItems = DBcount($sqlItems);
		$sqlHttptests = "select * from httptest order by httptestid";
		$oldCountHttptests = DBcount($sqlHttptests);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->login('templates.php');
		$this->dropdown_select_wait('groupid', 'all');

		// Deletes the added Web Scenario
		$this->button_click("xpath=(//a[contains(text(),'Web')])[5]");
		$this->wait();
		$this->ok(array('Name', 'Number of steps', 'Update interval', 'Status'));
		$this->ok('Web scenarios (3)');
		$this->checkbox_select("xpath=(//input[@type='checkbox'])[1]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();
		$this->ok("Web scenario deleted");
		$this->ok("No web scenarios defined.");
		$this->nok("MyWebScenario");
		$this->ok("Displaying 0");
		$this->waitForConfirmation("Delete selected WEB scenarios?");
		$this->chooseOkOnNextConfirmation();

		// Verify the Web Scenario has been deleted
		$this->button_click('link=Template list');
		$this->wait();
		$this->button_click("link=Template App Zabbix Agent");
		$this->wait();
		$this->nok("Web scenarios (3)");
		$this->button_click("link=Web scenarios");
		$this->wait();
		$this->ok("No web scenarios defined.");

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertNotEquals($oldCountItems, DBcount($sqlItems));
		$this->assertNotEquals($oldCountHttptests, DBcount($sqlHttptests));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}
}
?>
