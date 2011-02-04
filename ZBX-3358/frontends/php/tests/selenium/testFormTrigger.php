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

	public function testFormTrigger_CircularDependency()
	{
		$this->login('triggers.php');
		$this->assertTitle('Configuration of triggers');

		$this->dropdown_select_wait('groupid', 'all');
		$this->dropdown_select_wait('hostid', 'Zabbix server');

		$this->click('link=SSH server is down on {HOSTNAME}');
		$this->wait();

		$this->button_click('btn1');
		$this->waitForPopUp('zbx_popup');
		$this->selectWindow('zbx_popup');

		$this->assertTitle('TRIGGERS');
		$this->click('//span[text()="SSH server is down on Zabbix server"]');
		$this->selectWindow("null");
		$this->wait();

		$this->assertTitle('Configuration of triggers');
		$this->ok('SSH server is down on Zabbix server');

		$this->button_click('save');
		$this->wait();
		$this->ok('Incorrect dependency');
	}

}
?>
