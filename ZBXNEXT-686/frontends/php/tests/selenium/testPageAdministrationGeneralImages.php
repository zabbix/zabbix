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
		$this->zbxTestAssertElementPresentId('configDropDown');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestCheckTitle('Configuration of images');
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent('Type');
		$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);
		$this->zbxTestTextPresent([$icon_name['name']]);
	}

// TODO: need background images
	/**
	* @dataProvider allBgImages
	*/
/*
	public function testPageAdministrationGeneralImages_CheckLayoutBgImages($bgimage) {

		$BgImagesCount = DBdata('SELECT count(name) FROM images WHERE imagetype=2');

		if ($BgImagesCount==0) {
				$this->zbxTestTextPresent(['No images defined.']);
		}
		else {
				$this->zbxTestLogin('adm.images.php');
				$this->zbxTestAssertElementPresentId('configDropDown');
				$this->zbxTestDropdownSelectWait('imagetype', 'Background');
				$this->zbxTestAssertElementPresentId('form');
				$this->zbxTestCheckTitle('Configuration of images');
				$this->zbxTestCheckHeader('Images');
				$this->zbxTestDropdownHasOptions('imagetype', ['Icon', 'Background']);
				$this->zbxTestAssertElementPresentXpath("//form[@action='adm.images.php']//a[text()='".$bgimage['name']."']"));
		}
	}
*/

	/**
	* @dataProvider allIcons
	*/
	public function testPageAdministrationGeneralImages_IconSimpleUpdate($icon_name) {

		$sqlIconImages = 'SELECT * FROM images WHERE imagetype=1 ORDER BY imageid limit 5';
		$oldHashIconImages=DBhash($sqlIconImages);

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestDropdownSelectWait('imagetype', 'Icon');
		$this->zbxTestClickLinkText($icon_name['name']);
		$this->zbxTestCheckHeader('Images');
		$this->zbxTestTextPresent(['Name', 'Upload', 'Image']);
		$this->zbxTestAssertElementPresentId('update');
		$this->zbxTestAssertElementPresentId('delete');
		$this->zbxTestAssertElementPresentId('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Image updated');

		$newHashIconImages = DBhash($sqlIconImages);
		$this->assertEquals($oldHashIconImages, $newHashIconImages, "Chuck Norris: no-change icon image update should not update data in table 'images'");
	}

// TODO: need background images
	/**
	* @dataProvider allBgImages
	*/
/*
	public function testPageAdministrationGeneralImages_BgImageSimpleUpdate($bgimage_name) {

		$sqlBgImages = 'SELECT * FROM images WHERE imagetype=2 ORDER BY imageid limit 5';
		$oldHashBgImages=DBhash($sqlBgImages);

		$this->zbxTestLogin('adm.images.php');
		$this->zbxTestAssertElementPresentId('form');
		$this->zbxTestDropdownSelectWait('imagetype', 'Background');
		$this->zbxTestClickLinkText($bgimage_name['name']);
		$this->zbxTestTextPresent(['Name', 'Upload', 'Image']);
		$this->zbxTestAssertElementPresentId('update');
		$this->zbxTestAssertElementPresentId('delete');
		$this->zbxTestAssertElementPresentId('cancel');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Image updated');

		$newHashBgImages = DBhash($sqlBgImages);
		$this->assertEquals($oldHashBgImages, $newHashBgImages, "Chuck Norris: no-change background image update should not update data in table 'images'");
	}
*/
}
