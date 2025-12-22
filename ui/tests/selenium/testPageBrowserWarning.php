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

class testPageBrowserWarning extends CWebTest {

	public function testPageBrowserWarning_WhenNotLoggedIn() {
		$this->page->open('browserwarning.php');
		$this->query('link:Continue despite this warning')->one()->click();
		$this->assertTrue($this->query('button:Sign in')->one()->isClickable());
	}

	public function testPageBrowserWarning_Layout() {
		$this->page->login()->open('browserwarning.php')->waitUntilReady();
		$this->assertEquals('You are using an outdated browser.', $this->query('tag:h2')->one()->getText());

		$text = ['Zabbix frontend is built on advanced, modern technologies and does not support old browsers.'.
		' It is highly recommended that you choose and install a modern browser. It is free of charge'.
		' and only takes a couple of minutes.', 'New browsers usually come with support for new technologies,'.
		' increasing web page speed, better privacy'.
		' settings and so on. They also resolve security and functional issues.'];
		$this->assertEquals($text, $this->query('tag:p')->all()->asText());

		$links = [
			'Google Chrome' => 'http://www.google.com/chrome',
			'Mozilla Firefox' => 'http://www.mozilla.org/firefox',
			'Microsoft Edge' => 'https://www.microsoft.com/en-us/edge',
			'Opera browser' => 'http://www.opera.com/download',
			'Apple Safari' => 'http://www.apple.com/safari/download'
		];
		foreach ($links as $link => $url) {
			$this->assertEquals($this->query('link', $link)->one()->getAttribute('href'), $url);
			$this->assertTrue($this->query('link', $link)->one()->isClickable());
		}

		$this->assertScreenshot($this->query('class:browser-warning-container')->one());

		// Navigate to dashboard page for logged in user.
		$this->query('link:Continue despite this warning')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Dashboard for Copying widgets _1');
	}
}
