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

class testTriggerDependenciesFromHost extends CWebTest {

	/**
	* @dataProvider testTriggerDependenciesFromHost_SimpleTestProvider
	*/
	public function testTriggerDependenciesFromHost_SimpleTest($hostId, $expected) {

		$this->login('triggers.php?groupid=1&hostid='.$hostId);

		$this->button_click("link=/etc/inetd.conf has been changed on server {HOST.NAME}");
		$this->wait();
		$this->button_click("id=tab_dependenciesTab");
		$this->button_click("id=bnt1");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->select("id=hostid", "label=Template_Linux");
		$this->wait();
		$this->button_click("triggers_'10015'");
		$this->button_click("select");
		$this->selectWindow("Configuration of triggers");
		$this->wait();
		$this->ok('Template_Linux: /boot/vmlinuz has been changed on server {HOST.NAME}');
		$this->button_click("save");
		$this->wait();
		$this->ok($expected);


	}

	public function testTriggerDependenciesFromHost_SimpleTestProvider() {
		return array (
			array('10054', 'Cannot add dependency from template to host'),
			array('10001', 'Trigger updated')
		);
	}
}
?>
