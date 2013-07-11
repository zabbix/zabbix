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
require_once dirname(__FILE__).'/../../include/items.inc.php';


class testZBX6663 extends CWebTest {

	public function testZBX6663_MassSelect() {

		$template = 'Template OS AIX';
		$templatedApp = 'Template App Zabbix Agent';

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$template);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Applications']");

		$this->zbxTestCheckboxSelect('all_applications');
		$this->assertVisible('//input[@value="Go (11)"]');

		$this->zbxTestClickWait('link='.$templatedApp);
		$this->assertVisible('//input[@value="Go (0)"]');
	}
}
