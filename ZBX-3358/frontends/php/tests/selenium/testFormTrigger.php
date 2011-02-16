<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testFormTrigger extends CWebTest
{

	/**
	 * Check if it is possible to add circular trigger dependency
	 * @author Konstantin Buravcov
	 */
	public function testFormTrigger_CircularDependency()
	{
		$this->login('triggers.php');
		$this->assertTitle('Configuration of triggers');

		// selecting group 'Zabbix server' if it is not already selected
		$this->dropdown_select_wait('groupid', 'all');
		$this->dropdown_select_wait('hostid', 'Zabbix server');

		// clicking on template name
		$this->click('link=SSH server is down on {HOSTNAME}');
		$this->wait();

		// clicking on "Add" button
		$this->button_click('btn1');
		// switching to popoup that has opened
		$this->waitForPopUp('zbx_popup');
		$this->selectWindow('zbx_popup');

		// selecting the same trigger
		$this->assertTitle('TRIGGERS');
		$this->ok('Group');
		$this->dropdown_select_wait("groupid", "label=Zabbix servers");
		$this->dropdown_select_wait("hostid", "label=Zabbix server");
		$this->click('//span[text()="SSH server is down on Zabbix server"]');
		$this->selectWindow("null");
		$this->wait();

		// did it show up in dependencies?
		$this->assertTitle('Configuration of triggers');
		$this->ok('SSH server is down on Zabbix server');

		// clicking on 'Save'
		$this->button_click('save');
		$this->wait();

		// and error should appear
		$this->ok('Incorrect dependency');
	}

	/**
	 * Check if it is possible to add trigger dependency: "templated trigger depends on host trigger"
	 * @author Konstantin Buravcov
	 */
	public function testFormTrigger_TemplateToHostDependency()
	{
		$this->login('triggers.php');
		$this->assertTitle('Configuration of triggers');

		// selecting group 'Zabbix server' if it is not already selected
		$this->dropdown_select_wait('groupid', 'Templates');
		$this->dropdown_select_wait('hostid', 'Template_AIX');

		// clicking on template name
		$this->click('link=SSH server is down on {HOSTNAME}');
		$this->wait();

		// clicking on "Add" button
		$this->button_click('btn1');
		// switching to popoup that has opened
		$this->waitForPopUp('zbx_popup');
		$this->selectWindow('zbx_popup');

		// selecting the same trigger
		$this->assertTitle('TRIGGERS');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->dropdown_select_wait('hostid', 'Zabbix server');
		$this->click('//span[text()="Configured max number of opened files is too low on Zabbix server"]');
		$this->selectWindow("null");
		$this->wait();

		// did it show up in dependencies?
		$this->assertTitle('Configuration of triggers');
		$this->ok('Configured max number of opened files is too low on Zabbix server');

		// clicking on 'Save'
		$this->button_click('save');
		$this->wait();

		// and error should appear
		$this->ok('Cannot add dependency on trigger inside host');
	}


	/**
	 * Check if it is possible to add trigger dependency: "host trigger depends on templated trigger"
	 * @author Konstantin Buravcov
	 */
	public function testFormTrigger_HostToTemplateDependency()
	{
		$this->login('triggers.php');
		$this->assertTitle('Configuration of triggers');

		// selecting group 'Zabbix server' if it is not already selected
		$this->dropdown_select_wait('groupid', 'Templates');
		$this->dropdown_select_wait('hostid', 'Template_AIX');

		// clicking on template name
		$this->click('link=IMAP server is down on {HOSTNAME}');
		$this->wait();

		// clicking on "Add" button
		$this->button_click('btn1');
		// switching to popoup that has opened
		$this->waitForPopUp('zbx_popup');
		$this->selectWindow('zbx_popup');

		// selecting the same trigger
		$this->assertTitle('TRIGGERS');
		$this->dropdown_select_wait('groupid', 'Zabbix servers');
		$this->dropdown_select_wait('hostid', 'Zabbix server');
		$this->click('//span[text()="Apache is not running on Zabbix server"]');
		$this->selectWindow("null");
		$this->wait();

		// did it show up in dependencies?
		$this->assertTitle('Configuration of triggers');
		$this->ok('Apache is not running on Zabbix server');

		// clicking on 'Save'
		$this->button_click('save');
		$this->wait();

		// and error should appear
		$this->ok('Cannot add dependency on trigger inside host');
	}

}
?>
