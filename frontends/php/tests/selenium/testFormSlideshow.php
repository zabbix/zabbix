<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

/**
 * @backup slideshows
 */
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
		$this->zbxTestInputTypeOverwrite('delay', $data['delay']);

		// Select slides clicking on the first link that appears in popup window.
		$this->zbxTestClick('add');
		$this->zbxTestLaunchOverlayDialog('Screens');
		$this->zbxTestClickLinkTextWait('Zabbix server');

		/**
		 * Click on submit button.
		 * Full XPath required because there are multiple [id="add"] elements.
		 */
		$this->zbxTestClickXpathWait("//button[@id='add'][@type='submit']");

		// Wait until page is loaded, just to be sure that creation of slideshow is completed on validation.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show added');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//*[@value="Create slide show"]'));

		// Validate.
		$this->zbxTestTextPresent($data['name']);
		$this->assertEquals(1, DBcount('SELECT NULL FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckFatalErrors();
	}

	public function testFormSlideshow_Clone() {
		// Select slideshow to update.
		$slideshow = DBfetch(DBSelect('SELECT slideshowid FROM slideshows', 1));
		if ($slideshow) {
			// Log in and navigate to new slideshow form.
			$this->zbxTestLogin('slideconf.php?form=update&slideshowid='.$slideshow['slideshowid']);

			// Press clone.
			$this->zbxTestClickWait('clone');

			// Test page header.
			$this->zbxTestCheckHeader('Slide shows');

			// Change slide show name.
			$get_name = $this->zbxTestGetValue("//*[@id='name']");
			$new_name = sprintf('Clone of %s (%s)', $get_name, date("H:i"));
			$this->zbxTestInputType('name', $new_name);

			/**
			 * Click on submit button.
			 * Button 'Add' can be reached using button[@value='Update']
			 */
			$this->zbxTestClickButton('Update');

			// Wait until page is loaded, just to be sure that creation of slideshow is completed on validation.
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//*[@value="Create slide show"]'));

			// Test results.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show added');
			$this->assertEquals(1, DBCount('SELECT null FROM slideshows WHERE name='.zbx_dbstr($get_name)));
			$this->assertEquals(1, DBCount('SELECT null FROM slideshows WHERE name='.zbx_dbstr($new_name)));
		}

		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider formData
	 */
	public function testFormSlideshow_Cancel($data) {
		$sql_hash = 'SELECT * FROM slideshows ORDER BY slideshowid';
		$old_hash = DBhash($sql_hash);

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
		$this->assertEquals($old_hash, DBhash($sql_hash));
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
		$this->zbxTestCheckHeader('Slide shows');

		// Clear input fields.
		$this->webDriver->findElement(WebDriverBy::xpath('//*[@id="delay"]'))->clear();

		// Try to save changes.
		$this->zbxTestClickXpathWait("//button[@id='add'][@type='submit']");

		// Validate.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Incorrect value for field "Name": cannot be empty.');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Field "Default delay" is not correct: a time unit is expected');

		// Test if slideshow cannot be created with no slides.
		$this->zbxTestInputTypeWait('name', $data['name']);
		$this->zbxTestInputTypeWait('delay', $data['delay']);

		// Try to save changes.
		$this->zbxTestClickXpathWait("//button[@id='add'][@type='submit']");

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
			$this->zbxTestCheckHeader('Slide shows');

			// Set new slideshow name.
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
			$this->zbxTestClickButton('Update');

			// Test results.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show updated');
			$new_slideshowid = DBfetch(DBSelect('SELECT slideshowid FROM slideshows WHERE name='.zbx_dbstr($data['name'])));
			$this->assertEquals($slideshow['slideshowid'], $new_slideshowid['slideshowid']);
		}

		$this->zbxTestCheckFatalErrors();
	}

	public function testFormSlideshow_DeleteFromForm() {
		// Select slideshow to update.
		$slideshow = DBfetch(DBSelect('SELECT slideshowid FROM slideshows', 1));
		if ($slideshow) {
			// Log in and navigate to new slideshow form.
			$this->zbxTestLogin('slideconf.php?form=update&slideshowid='.$slideshow['slideshowid']);

			// Test page title.
			$this->zbxTestCheckHeader('Slide shows');

			// Click on delete button.
			$this->zbxTestClickWait('delete');

			// Confirm deletion.
			$this->zbxTestAcceptAlert();

			// Validate.
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show deleted');
			$this->assertEquals(0, DBCount('SELECT null FROM slideshows WHERE slideshowid='.$slideshow['slideshowid']));
		}

		$this->zbxTestCheckFatalErrors();
	}
}
