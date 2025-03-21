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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

class testPageAdministrationGeneralImages extends CLegacyWebTest {

	public static function allImages() {
		return CDBHelper::getDataProvider('SELECT imageid,imagetype,name FROM images LIMIT 5');
	}

	public function testPageAdministrationGeneralImages_CheckLayoutIcons() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestAssertElementPresentXpath('//button[text()="Create icon"]');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent('Type');
		$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=1 LIMIT 5'));
		if (!$db_images) {
			$this->zbxTestTextPresent('No data found');
		}
		else {
			foreach ($db_images as $db_image) {
				$this->zbxTestAssertElementPresentXpath("//div[@id='image']//a[text()='".$db_image['name']."']");
			}
		}
	}

// TODO: need background images
	public function testPageAdministrationGeneralImages_CheckLayoutBgImages() {

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestAssertElementPresentXpath('//button[text()="Create background"]');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=2 LIMIT 5'));
		if (!$db_images) {
			$this->zbxTestTextPresent('No data found');
		}
		else {
			foreach ($db_images as $db_image) {
				$this->zbxTestAssertElementPresentXpath("//div[@id='image']//a[text()='".$db_image['name']."']");
			}
		}
	}

	/**
	 * @dataProvider allImages
	 */
	public function testPageAdministrationGeneralImages_IconSimpleUpdate($image) {
		$sql_image = 'SELECT * FROM images WHERE imageid='.$image['imageid'];
		$old_image_hash = CDBHelper::getHash($sql_image);

		$this->zbxTestLogin('zabbix.php?action=image.list');
		$this->zbxTestAssertElementPresentXpath('//button[text()="Create icon"]');
		$this->zbxTestDropdownSelectWait('imagetype', $image['imagetype'] == IMAGE_TYPE_ICON ? 'Icon' : 'Background');
		$this->zbxTestClickLinkTextWait($image['name']);
		$this->page->waitUntilReady();
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Name', 'Upload', 'Image']);
		$this->zbxTestAssertElementPresentId('update');
		$this->zbxTestAssertElementPresentId('delete');
		$this->zbxTestAssertElementPresentId('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Image updated');

		$this->assertEquals($old_image_hash, CDBHelper::getHash($sql_image));
	}

}
