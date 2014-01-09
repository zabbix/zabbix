<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class testPageSlideShows extends CWebTest {
	// Returns all slide shows
	public static function allSlideShows() {
		return DBdata("select * from slideshows order by slideshowid");
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_CheckLayout($slideshow) {
		$this->zbxTestLogin('slideconf.php');
		$this->checkTitle('Configuration of slide shows');

		$this->zbxTestTextPresent('CONFIGURATION OF SLIDE SHOWS');
		$this->zbxTestTextPresent('SLIDE SHOWS');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');
		// Header
		$this->zbxTestTextPresent(array('Name', 'Delay', 'Count of slides'));
		// Data
		$this->zbxTestTextPresent(array($slideshow['name']));
		$this->zbxTestDropdownHasOptions('go', array('Delete selected'));
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_SimpleUpdate($slideshow) {
		$name = $slideshow['name'];
		$slideshowid = $slideshow['slideshowid'];

		$sqlSlideShow = "select * from slideshows where name='$name' order by slideshowid";
		$oldHashSlideShow = DBhash($sqlSlideShow);
		$sqlSlide = "select * from slides where slideshowid=$slideshowid order by slideid";
		$oldHashSlide = DBhash($sqlSlide);

		$this->zbxTestLogin('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of slide shows');
		$this->zbxTestTextPresent('Slide show updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('CONFIGURATION OF SLIDE SHOWS');

		$this->assertEquals($oldHashSlideShow, DBhash($sqlSlideShow), "Chuck Norris: Slide show update changed data in table 'slideshows'");
		$this->assertEquals($oldHashSlide, DBhash($sqlSlide), "Chuck Norris: Slide show update changed data in table 'slides'");
	}

	public function testPageSlideShows_Create() {
		$this->zbxTestLogin('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->zbxTestClickWait('form');

		$this->zbxTestTextPresent('CONFIGURATION OF SLIDE SHOWS');
		$this->zbxTestTextPresent('Slide');
		$this->zbxTestTextPresent('Name');
		$this->zbxTestTextPresent('Default delay (in seconds)');
		$this->zbxTestTextPresent('Slides');
		$this->zbxTestTextPresent(array('Screen', 'Delay', 'Action'));
		$this->zbxTestClickWait('cancel');
		$this->zbxTestTextPresent('SLIDE SHOWS');
	}

	public function testPageSlideShows_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_MassDelete($slideshow) {
		$slideshowid = $slideshow['slideshowid'];
		$name = $slideshow['name'];

		$this->chooseOkOnNextConfirmation();

		DBsave_tables('slideshows');

		$this->zbxTestLogin('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->zbxTestCheckboxSelect('shows['.$slideshowid.']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of slide shows');
		$this->zbxTestTextPresent('Slide show deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF SLIDE SHOWS');

		$sql = "select * from slideshows where slideshowid=$slideshowid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from slides where slideshowid=$slideshowid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('slideshows');
	}

	public function testPageSlideShows_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
