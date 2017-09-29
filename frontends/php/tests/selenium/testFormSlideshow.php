<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class testFormSlideshow extends CWebTest {
	// Returns layout data
	public static function formData() {
		return [
			[
				['name' => sprintf('Test Slideshow %s', time()), 'delay' => '30s']
			]
		];
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Create($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php?config=slides.php&form=Create+slide+show');

		// Test page title.
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckHeader('Slide shows');

		// Fill out the form.
		$this->zbxTestInputTypeWait('name', $data['name']);
		$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="delay"]'))->clear();
		$this->zbxTestInputTypeWait('delay', $data['delay']);

		// Select slides.
		$this->zbxTestClick('add');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestCheckHeader('Screens');
		$this->zbxTestCheckboxSelect('all_screens');
		$this->zbxTestClick('select');
		$this->webDriver->switchTo()->window('');

		// Click on submit button.
		$submit_btn_selector = WebDriverBy::cssSelector('button[id="add"][type="submit"]');
		$this->webDriver->findElement($submit_btn_selector)->click();

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show added');
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Clone($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php');
		//$this->zbxTestCheckHeader('Slide shows');

		// Click on the Properties link of the first item in the list.
		$this->zbxTestClickLinkTextWait('Properties');

		// Check the name of screenshow.
		$this->zbxTestCheckHeader('Slide shows');

		// Click on Clone button.
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::id('clone'));
		$this->webDriver->findElement(WebDriverBy::id('clone'))->click();

		// Change slide show name.
		$name_field = WebDriverBy::xpath('//*[@id="name"]');
		$new_name = sprintf('Clone of %s', $this->webDriver->findElement($name_field)->getAttribute('value'));
		$this->webDriver->findElement($name_field)->clear();
		$this->zbxTestInputTypeWait('name', $new_name);

		// Click on submit button.
		$submit_btn_selector = WebDriverBy::cssSelector('button[id="add"][type="submit"]');
		$this->webDriver->findElement($submit_btn_selector)->click();

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show added');
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Cancel($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php');

		// Click on 'Create slide show' button.
		$this->zbxTestClick('form');

		// Test page title.
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckHeader('Slide shows');

		// Fill out the form.
		$this->zbxTestInputTypeWait('name', $data['name']);
		$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="delay"]'))->clear();

		// Change your mind and cancel form creation.
		$this->zbxTestClick('cancel');

		// Test if slideshow is there.
		$this->zbxTestTextNotPresent($data['name']);
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Delete($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php');

		// Test page title.
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckHeader('Slide shows');

		// Click on the Properties link of the first item in the list.
		$this->zbxTestCheckboxSelect('all_shows');

		// Click on submit button.
		$delete_btn_selector = WebDriverBy::cssSelector('button[value="slideshow.massdelete"][type="submit"]');
		$this->zbxTestWaitUntilElementClickable($delete_btn_selector);
		$this->webDriver->findElement($delete_btn_selector)->click();

		try {
			$this->webDriver->wait(10)->until(WebDriverExpectedCondition::alertIsPresent());
			$this->webDriver->switchTo()->alert()->accept();
		}
		catch (TimeoutException $ex) {
			$this->zbxTestWaitUntilElementClickable($delete_btn_selector);
			$this->webDriver->findElement($delete_btn_selector)->click();
			$this->webDriver->switchTo()->alert()->accept();
		}

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show deleted');
	}
}
