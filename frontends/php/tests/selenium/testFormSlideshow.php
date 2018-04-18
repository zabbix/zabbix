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

		// Select slides clicking on the first link that appears in popup window.
		$this->zbxTestClick('add');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestCheckHeader('Screens');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('a'));
		$this->webDriver->findElement(WebDriverBy::tagName('a'))->click();
		$this->zbxTestWaitWindowClose();

		/**
		 * Click on submit button.
		 * Full XPath required because there are multiple [id="add"] elements.
		 */
		$submit_btn_selector = WebDriverBy::cssSelector('button[id="add"][type="submit"]');
		$this->webDriver->findElement($submit_btn_selector)->click();

		// Wait until page is loaded, just to be sure that creation of slideshow is completed on validation.
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//*[@value="Create slide show"]'));

		// Validate.
		$this->zbxTestTextPresent($data['name']);
		$this->assertEquals(1, DBcount('SELECT NULL FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Clone($data) {
		// Select slideshow to update.
		$slideshow = DBfetch(DBSelect('SELECT slideshowid FROM slideshows', 1));
		if ($slideshow) {
			// Log in and navigate to new slideshow form.
			$this->zbxTestLogin('slideconf.php?form=update&slideshowid='.$slideshow['slideshowid']);

			// Test page title.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('h1'));
			$this->zbxTestCheckHeader('Slide shows');

			// Press clone.
			$this->zbxTestClick('clone');

			// Change slide show name.
			$name_field = WebDriverBy::xpath('//*[@id="name"]');
			$new_name = sprintf('Clone of %s (%s)', $this->webDriver->findElement($name_field)->getAttribute('value'),
							date("H:i"));
			$this->webDriver->findElement($name_field)->clear();
			$this->zbxTestInputTypeWait('name', $new_name);

			/**
			 * Click on submit button.
			 * Button 'Add' can be reached using button[@value='Update']
			 */
			$this->zbxTestClickButton('Update');

			// Wait until page is loaded, just to be sure that creation of slideshow is completed on validation.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//*[@value="Create slide show"]'));

			// Test results.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show added');
			$this->assertEquals(1, DBCount('SELECT null FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
		}

		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Cancel($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php');

		// Name must be different for 'Cancel' test because slideshow with similar name can be created by 'Create' test.
		$data['name'] = $data['name'] . ' for cancel';

		// Click on 'Create slide show' button.
		$this->zbxTestClickButton('Create slide show');

		// Test page title.
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckHeader('Slide shows');

		// Do some changes in form.
		$this->zbxTestInputTypeWait('name', $data['name']);

		// Change your mind and cancel form creation.
		$this->zbxTestClick('cancel');

		// Test if slideshow is there.
		$this->assertEquals(0, DBcount('SELECT NULL FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_ValidateOnCreate($data) {
		// Log in and navigate to new slideshow form.
		$this->zbxTestLogin('slideconf.php');
		$this->zbxTestClickButton('Create slide show');

		// Test page title.
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('h1'));
		$this->zbxTestCheckHeader('Slide shows');

		// Clear input fields.
		$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="name"]'))->clear();
		$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="delay"]'))->clear();

		// Try to save changes.
		$submit_btn_selector = WebDriverBy::cssSelector('button[id="add"][type="submit"]');
		$this->webDriver->findElement($submit_btn_selector)->click();

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Incorrect value for field "Name": cannot be empty.');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Field "Default delay" is not correct: a time unit is expected');

		// Test if slideshow cannot be created with no slides.
		$this->zbxTestInputTypeWait('name', $data['name']);
		$this->zbxTestInputTypeWait('delay', $data['delay']);

		// Try to save changes.
		$submit_btn_selector = WebDriverBy::cssSelector('button[id="add"][type="submit"]');
		$this->webDriver->findElement($submit_btn_selector)->click();

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Slide show must contain slides.');
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_ChangeSlideshowName($data) {
		// Select slideshow to update.
		$slideshow = DBfetch(DBSelect('SELECT slideshowid FROM slideshows', 1));
		if ($slideshow) {
			$data['name'] = 'Changed name of ' . $data['name'];

			// Log in and navigate to new slideshow form.
			$this->zbxTestLogin('slideconf.php?form=update&slideshowid='.$slideshow['slideshowid']);

			// Test page title.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('h1'));
			$this->zbxTestCheckHeader('Slide shows');

			// Set new slideshow name.
			$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="name"]'))->clear();
			$this->zbxTestInputTypeWait('name', $data['name']);
			$this->zbxTestClickButton('Update');

			// Test results.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show updated');
			$new_slideshowid = DBfetch(DBSelect('SELECT slideshowid FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
			$this->assertEquals($slideshow['slideshowid'], $new_slideshowid['slideshowid']);
		}

		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_DeleteFromForm($data) {
		// Select slideshow to update.
		$slideshow = DBfetch(DBSelect('SELECT slideshowid FROM slideshows', 1));
		if ($slideshow) {
			// Log in and navigate to new slideshow form.
			$this->zbxTestLogin('slideconf.php?form=update&slideshowid='.$slideshow['slideshowid']);

			// Test page title.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('h1'));
			$this->zbxTestCheckHeader('Slide shows');

			// Click on delete button.
			$delete_btn_selector = WebDriverBy::cssSelector('button[value="Delete"]');
			$this->zbxTestWaitUntilElementClickable($delete_btn_selector);
			$this->webDriver->findElement($delete_btn_selector)->click();

			// Confirm deletion.
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
			$this->assertEquals(0, DBCount('SELECT null FROM slideshows WHERE slideshowid='.$slideshow['slideshowid']));
		}

		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_DeleteFromList($data) {
		// Log in.
		$this->zbxTestLogin('slideconf.php');

		// Test page title.
		$this->zbxTestCheckHeader('Slide shows');

		// Select all vsible checkboxes (since checkboxes are not visible, span elements should be clicked).
		$this->webDriver->findElement(WebDriverBy::className('list-table'))->findElement(WebDriverBy::tagName('span'))->click();

		// Click on submit button.
		$delete_btn_selector = WebDriverBy::cssSelector('button[value="slideshow.massdelete"][type="submit"]');
		$this->zbxTestWaitUntilElementClickable($delete_btn_selector);
		$this->webDriver->findElement($delete_btn_selector)->click();

		// Confirm deletion.
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
		$this->zbxTestCheckFatalErrors();
	}
}
