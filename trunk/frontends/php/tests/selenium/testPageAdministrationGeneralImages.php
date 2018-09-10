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

class testPageAdministrationGeneralImages extends CWebTest {

	public static function allImages() {
		return DBdata('SELECT imageid,imagetype,name FROM images LIMIT 5');
	}

	public function testPageAdministrationGeneralImages_CheckLayoutIcons() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent('Type');
		$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=1 LIMIT 5'));
		if (!$db_images) {
			$this->zbxTestTextPresent('No data found.');
		}
		else {
			foreach ($db_images as $db_image) {
				$this->zbxTestAssertElementPresentXpath("//div[@id='image']//a[text()='".$db_image['name']."']");
			}
		}
	}

// TODO: need background images
	public function testPageAdministrationGeneralImages_CheckLayoutBgImages() {

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=2 LIMIT 5'));
		if (!$db_images) {
			$this->zbxTestTextPresent('No data found.');
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
		$old_image_hash = DBhash($sql_image);

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestDropdownSelectWait('imagetype', $image['imagetype'] == IMAGE_TYPE_ICON ? 'Icon' : 'Background');
		$this->zbxTestClickLinkTextWait($image['name']);
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Name', 'Upload', 'Image']);
		$this->zbxTestAssertElementPresentId('update');
		$this->zbxTestAssertElementPresentId('delete');
		$this->zbxTestAssertElementPresentId('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Image updated');

		$this->assertEquals($old_image_hash, DBhash($sql_image));
	}

}
