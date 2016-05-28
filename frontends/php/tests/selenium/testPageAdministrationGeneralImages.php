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

class testPageAdministrationGeneralImages extends CWebTest {

	public static function allImages() {
		return DBdata('SELECT imageid,imagetype,name FROM images LIMIT 5');
	}

	public function testPageAdministrationGeneralImages_CheckLayoutIcons() {
		$this->zbxTestLogin('adm.images.php');
		$this->assertElementPresent('configDropDown');
		$this->assertElementPresent('form');
		$this->checkTitle('Configuration of images');
		$this->zbxTestTextPresent(array('CONFIGURATION OF IMAGES', 'Images', 'Type'));
		$this->assertElementPresent('imagetype');
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Icon']");
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Background']");

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=1'));

		if (!$db_images) {
			$this->zbxTestTextPresent('No images defined.');
		}
		else {
			foreach ($db_images as $db_image) {
				$this->assertElementPresent("//form[@name='imageForm']//table//a[text()='".$db_image['name']."']");
			}
		}
	}

	public function testPageAdministrationGeneralImages_CheckLayoutBgImages() {
		$this->zbxTestLogin('adm.images.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->assertElementPresent('form');
		$this->checkTitle('Configuration of images');
		$this->zbxTestTextPresent(array('CONFIGURATION OF IMAGES', 'Images', 'Type'));
		$this->assertElementPresent('imagetype');
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Icon']");
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Background']");

		$db_images = DBfetchArray(DBselect('SELECT name FROM images WHERE imagetype=2'));

		if (!$db_images) {
			$this->zbxTestTextPresent('No images defined.');
		}
		else {
			foreach ($db_images as $db_image) {
				$this->assertElementPresent("//form[@name='imageForm']//table//a[text()='".$db_image['name']."']");
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
		$this->assertElementPresent('form');
		$this->zbxTestDropdownSelectWait('imagetype', $image['imagetype'] == IMAGE_TYPE_ICON ? 'Icon' : 'Background');
		$this->zbxTestClickWait('link='.$image['name']);
		$this->zbxTestTextPresent(array('Name', 'Type', 'Upload', 'Image'));
		$this->assertElementPresent('save');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Image updated');

		$this->assertEquals($old_image_hash, DBhash($sql_image));
	}
}
