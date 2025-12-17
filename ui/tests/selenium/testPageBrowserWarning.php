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

require_once __DIR__.'/../include/CWebTest.php';

//Test checkes  /browserwarning.php  web page.

class testPageBrowserWarning extends CWebTest {

	public function testPageBrowserWarning_CheckLayout() {
		$this->page->login()->open('browserwarning.php')->waitUntilReady();
		$this->page->assertTitle('You are using an outdated browser.');

		//Assert text - 1st paragraph
		$this->assertEquals("Zabbix frontend is built on advanced, modern technologies and does not support old browsers. It is highly recommended that you choose and install a modern browser. It is free of charge and only takes a couple of minutes.",
		$this->query('css:.browser-warning-container > p:nth-of-type(1)')->one()->getText());

		//Assert text -  2nd paragraph
		$this->assertEquals("New browsers usually come with support for new technologies, increasing web page speed, better privacy settings and so on. They also resolve security and functional issues.",
		$this->query('css:.browser-warning-container > p:nth-of-type(2)')->one()->getText());

		//Check, that links are clickable
		$links = [
			'Google Chrome' => 'http://www.google.com/chrome',
			'Mozilla Firefox' => 'http://www.mozilla.org/firefox',
			'Microsoft Edge' => 'https://www.microsoft.com/en-us/edge',
			'Opera browser' => 'http://www.opera.com/download',
			'Apple Safari' => 'http://www.apple.com/safari/download'
		];
		foreach($links as $key => $item){
		$this->assertEquals($this->query("link:$key")->one()->getAttribute('href'), $item);
		}

		//Check, that user can go to Zabbix page
		$this->query('link:Continue despite this warning')->one()->click();
		$this->query('css:#dashboard')->one()->waitUntilPresent();
		$this->page->assertTitle('Dashboard');
	}
}
