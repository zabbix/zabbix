<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	public static function allIcons() {
		return DBdata('SELECT name FROM images WHERE imagetype=1 ORDER BY imageid limit 5');
	}

	public static function allBgImages() {
		return DBdata('SELECT name FROM images WHERE imagetype=2 ORDER BY imageid limit 5');
	}

	/**
	* @dataProvider allIcons
	*/
	public function testPageAdministrationGeneralImages_CheckLayoutIcons($icon_name) {

		$this->zbxTestLogin('adm.images.php');
		$this->assertElementPresent('configDropDown');
		$this->assertElementPresent('form');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestTextPresent(array('CONFIGURATION OF IMAGES', 'Images', 'Type'));
		$this->assertElementPresent('imagetype');
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Icon']");
		$this->assertElementPresent("//select[@id='imagetype']/option[text()='Background']");
		$this->zbxTestTextPresent(array($icon_name['name']));
	}

	/**
	* @dataProvider allBgImages
	*/
	public function testPageAdministrationGeneralImages_CheckLayoutBgImages($bgimage) {

		$BgImagesCount = DBdata('SELECT count(name) FROM images WHERE imagetype=2 ORDER BY imageid');

		if ($BgImagesCount==0) {
				$this->zbxTestTextPresent(array('No images defined.'));
		}
		else {
				$this->zbxTestLogin('adm.images.php');
				$this->assertElementPresent('configDropDown');
				$this->zbxTestDropdownSelectWait('imagetype', 'Background');
				$this->assertElementPresent('form');
				$this->zbxTestCheckTitle('Configuration of Zabbix');
				$this->zbxTestTextPresent(array('CONFIGURATION OF IMAGES', 'Images', 'Type'));
				$this->assertElementPresent('imagetype');
				$this->assertElementPresent("//select[@id='imagetype']/option[text()='Icon']");
				$this->assertElementPresent("//select[@id='imagetype']/option[text()='Background']");
				$this->assertElementPresent("//form[@name='imageForm']//table//a[text()='".$bgimage['name']."']");
		}
	}

	/**
	* @dataProvider allIcons
	*/
	public function testPageAdministrationGeneralImages_IconSimpleUpdate($icon_name) {

		$sqlIconImages = 'SELECT * FROM images WHERE imagetype=1 ORDER BY imageid limit 5';
		$oldHashIconImages=DBhash($sqlIconImages);

		$this->zbxTestLogin('adm.images.php');
		$this->assertElementPresent('form');
		$this->zbxTestDropdownSelectWait('imagetype', 'Icon');
		$this->zbxTestClickWait('link='.$icon_name['name']);
		$this->zbxTestTextPresent(array('Name', 'Type', 'Upload', 'Image'));
		$this->assertElementPresent('update');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Image updated');

		$newHashIconImages = DBhash($sqlIconImages);
		$this->assertEquals($oldHashIconImages, $newHashIconImages, "Chuck Norris: no-change icon image update should not update data in table 'images'");
	}

	/**
	* @dataProvider allBgImages
	*/
	public function testPageAdministrationGeneralImages_BgImageSimpleUpdate($bgimage_name) {

		$sqlBgImages = 'SELECT * FROM images WHERE imagetype=2 ORDER BY imageid limit 5';
		$oldHashBgImages=DBhash($sqlBgImages);

		$this->zbxTestLogin('adm.images.php');
		$this->assertElementPresent('form');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestClickWait('link='.$bgimage_name['name']);
		$this->zbxTestTextPresent(array('Name', 'Type', 'Upload', 'Image'));
		$this->assertElementPresent('update');
		$this->assertElementPresent('delete');
		$this->assertElementPresent('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Image updated');

		$newHashBgImages = DBhash($sqlBgImages);
		$this->assertEquals($oldHashBgImages, $newHashBgImages, "Chuck Norris: no-change background image update should not update data in table 'images'");
	}
}
