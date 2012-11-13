<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
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
		$this->login('slideconf.php');
		$this->checkTitle('Configuration of slide shows');

		$this->ok('CONFIGURATION OF SLIDE SHOWS');
		$this->ok('SLIDE SHOWS');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		// Header
		$this->ok(array('Name', 'Delay', 'Count of slides'));
		// Data
		$this->ok(array($slideshow['name']));
		$this->dropdown_select('go', 'Delete selected');
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

		$this->login('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of slide shows');
		$this->ok('Slide show updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF SLIDE SHOWS');

		$this->assertEquals($oldHashSlideShow, DBhash($sqlSlideShow), "Chuck Norris: Slide show update changed data in table 'slideshows'");
		$this->assertEquals($oldHashSlide, DBhash($sqlSlide), "Chuck Norris: Slide show update changed data in table 'slides'");
	}

	public function testPageSlideShows_Create() {
		$this->login('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->button_click('form');
		$this->wait();

		$this->ok('CONFIGURATION OF SLIDE SHOWS');
		$this->ok('Slide');
		$this->ok('Name');
		$this->ok('Default delay (in seconds)');
		$this->ok('Slides');
		$this->ok(array('Screen', 'Delay', 'Action'));
		$this->button_click('cancel');
		$this->wait();
		$this->ok('SLIDE SHOWS');
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

		$this->login('slideconf.php');
		$this->checkTitle('Configuration of slide shows');
		$this->checkbox_select("shows[$slideshowid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->checkTitle('Configuration of slide shows');
		$this->ok('Slide show deleted');
		$this->ok('CONFIGURATION OF SLIDE SHOWS');

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
?>
