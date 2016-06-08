<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testFormAdministrationGeneralImages extends CWebTest {
	public $icon_image_name = '1image1';
	public $icon_image_name2 = '2image2';
	public $bg_image_name = '1bgimage1';
	public $bg_image_name2 = '2bgimage2';
	public $file_path = PHPUNIT_BASEDIR.'/tests/images/image.png';

	public function testFormAdministrationGeneralImages_CheckLayout() {

		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Images');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Type']);
		$this->zbxTestAssertElementPresentXpath("//select[@id='imagetype']/option[text()='Icon']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='imagetype']/option[text()='Background']");
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestClickWait('form');

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", "maxlength", '64');
		$this->zbxTestAssertElementPresentId('image');
		$this->zbxTestAssertElementPresentId('add');
		$this->zbxTestAssertElementPresentId('cancel');

	}

	public function testFormAdministrationGeneralImages_AddImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestClickWait('form');

		$this->zbxTestAssertElementPresentId('name');
		$this->zbxTestInputType('name', $this->icon_image_name);
		$this->zbxTestInputType('image', $this->file_path);
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Type', 'Image added']);

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Image with such name has not been added to the DB');

	}

	public function testFormAdministrationGeneralImages_CancelIconImageChanges() {

		$sqlIcons = 'SELECT * FROM images WHERE imagetype=1';
		$oldHashIcons=DBhash($sqlIcons);

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestClickLinkText($this->icon_image_name);
		$this->zbxTestInputType('name', $this->icon_image_name2);
		$this->zbxTestInputType('image', $this->file_path);
		$this->zbxTestClick('cancel');

		// checking that image has not been changed after clicking on the "Cancel" button in the confirm dialog box
		$this->assertEquals($oldHashIcons, DBhash($sqlIcons), 'Chuck Norris: No-change images update should not update data in table "images"');

	}

	public function testFormAdministrationGeneralImages_UpdateImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestClickLinkText($this->icon_image_name);
		$this->zbxTestInputType('name', $this->icon_image_name2);
		$this->zbxTestInputType('image', $this->file_path);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(['Images', 'Image updated']);

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name2.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Image with such name does not exist in the DB');
	}

	public function testFormAdministrationGeneralImages_DeleteImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestClickLinkText($this->icon_image_name2);
		$this->zbxTestClick('delete');
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(['Images', 'Image deleted']);

		$sql = 'SELECT * FROM images WHERE imagetype=1 AND name=\''.$this->icon_image_name2.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Image with such name still exist in the DB');
	}

	public function testFormAdministrationGeneralImages_AddBgImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestDropdownSelect('imagetype', 'Background');
		$this->zbxTestClickWait('form');
		$this->zbxTestInputType('name', $this->bg_image_name);
		$this->zbxTestInputType('image', $this->file_path);
		$this->zbxTestClickWait('add');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(['Images', 'Type', 'Image added']);

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Image with such name has not been added to the DB');
	}

	public function testFormAdministrationGeneralImages_UpdateBgImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestTextPresent('Type');
		$this->zbxTestWaitUntilElementVisible(WebdriverBy::xpath("//div[@class='cell']"));
		$this->zbxTestClickLinkText($this->bg_image_name);
		$this->zbxTestInputType('name', $this->bg_image_name2);
		$this->zbxTestInputType('image', $this->file_path);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(['Images', 'Image updated']);

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name2.'\'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Image with such name does not exist in the DB');
	}

	public function testFormAdministrationGeneralImages_DeleteBgImage() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestClickLinkText($this->bg_image_name2);
		$this->zbxTestClick('delete');
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Images', 'Image deleted']);

		$sql = 'SELECT * FROM images WHERE imagetype=2 AND name=\''.$this->bg_image_name2.'\'';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Image with such name still exist in the DB');
	}

}
