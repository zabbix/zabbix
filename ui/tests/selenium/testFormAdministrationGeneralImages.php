<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testFormAdministrationGeneralImages extends CLegacyWebTest {
	public $icon_image_name = '1image1';
	public $icon_image_name2 = '2image2';
	public $bg_image_name = '1bgimage1';
	public $bg_image_name2 = '2bgimage2';

	public function testFormAdministrationGeneralImages_CheckLayout() {

		$this->zbxTestLogin('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Images');

		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Type']);
		$this->zbxTestAssertElementPresentXpath("//select[@id='imagetype']/option[text()='Icon']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='imagetype']/option[text()='Background']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Create icon']");
		$this->zbxTestClickButtonText('Create icon');

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", "maxlength", '64');
		$this->zbxTestAssertElementPresentId('image');
		$this->zbxTestAssertElementPresentXpath("//button[text()='Add']");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Cancel']");

	}

	public function testFormAdministrationGeneralImages_AddImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestClickButtonText('Create icon');

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestInputType('name', $this->icon_image_name);
		$this->zbxTestInputType('image', PHPUNIT_BASEDIR.'/ui/tests/images/image.png');
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Type', 'Image added']);

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name.'\'';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name has not been added to the DB');

	}

	public function testFormAdministrationGeneralImages_CancelIconImageChanges() {

		$sqlIcons = 'SELECT * FROM images WHERE imagetype=1';
		$oldHashIcons=CDBHelper::getHash($sqlIcons);

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestClickLinkText($this->icon_image_name);
		$this->zbxTestInputTypeWait('name', $this->icon_image_name2);
		$this->zbxTestInputTypeWait('image', PHPUNIT_BASEDIR.'/ui/tests/images/image.png');
		$this->zbxTestClickButtonText('Cancel');

		// checking that image has not been changed after clicking on the "Cancel" button in the confirm dialog box
		$this->assertEquals($oldHashIcons, CDBHelper::getHash($sqlIcons), 'Chuck Norris: No-change images update should not update data in table "images"');

	}

	public function testFormAdministrationGeneralImages_UpdateImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestClickLinkText($this->icon_image_name);
		$this->zbxTestInputTypeOverwrite('name', $this->icon_image_name2);
		$this->zbxTestInputType('image', PHPUNIT_BASEDIR.'/ui/tests/images/image.png');
		$this->zbxTestClickButtonText('Update');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Image updated');

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name2.'\'';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name does not exist in the DB');
	}

	public function testFormAdministrationGeneralImages_DeleteImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestClickLinkTextWait($this->icon_image_name2);
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Image deleted');
		$this->zbxTestTextPresent(['Images', 'Image deleted']);

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name2.'\'';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name still exist in the DB');
	}

	public function testFormAdministrationGeneralImages_AddBgImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestClickButtonText('Create background');
		$this->zbxTestInputType('name', $this->bg_image_name);
		$this->zbxTestInputType('image', PHPUNIT_BASEDIR.'/ui/tests/images/image.png');
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(['Images', 'Type', 'Image added']);

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name.'\'';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name has not been added to the DB');
	}

	public function testFormAdministrationGeneralImages_UpdateBgImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestTextPresent('Type');
		$this->zbxTestWaitUntilElementVisible(WebdriverBy::xpath("//div[@class='cell']"));
		$this->zbxTestClickLinkText($this->bg_image_name);
		$this->zbxTestInputTypeOverwrite('name', $this->bg_image_name2);
		$this->zbxTestInputTypeWait('image', PHPUNIT_BASEDIR.'/ui/tests/images/image.png');
		$this->zbxTestClickButtonText('Update');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Image updated');

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name2.'\'';
		$this->assertEquals(1, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name does not exist in the DB');
	}

	public function testFormAdministrationGeneralImages_DeleteBgImage() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestClickLinkTextWait($this->bg_image_name2);
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Image deleted']);

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name2.'\'';
		$this->assertEquals(0, CDBHelper::getCount($sql), 'Chuck Norris: Image with such name still exist in the DB');
	}

}
