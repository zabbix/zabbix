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

class testPageSlideShows extends CWebTest {

	public static function allSlideShows() {
		return DBdata("select * from slideshows order by slideshowid");
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_CheckLayout($slideshow) {
		$this->zbxTestLogin('slideconf.php');
		$this->zbxTestCheckTitle('Configuration of slide shows');

		$this->zbxTestCheckHeader('Slide shows');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextNotPresent('Displaying 0');

		$this->zbxTestTextPresent(['Name', 'Delay', 'Number of slides', 'Actions']);

		$this->zbxTestTextPresent([$slideshow['name']]);
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
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestHrefClickWait('?form=update&slideshowid='.$slideshow['slideshowid']);
		$this->zbxTestCheckHeader('Slide shows');
		$this->zbxTestTextPresent(['Slide','Sharing']);
		$this->zbxTestTextPresent(['Owner', 'Name', 'Default delay', 'Slides']);

		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestTextPresent('Slide show updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckHeader('Slide shows');

		$this->assertEquals($oldHashSlideShow, DBhash($sqlSlideShow), "Chuck Norris: Slide show update changed data in table 'slideshows'");
		$this->assertEquals($oldHashSlide, DBhash($sqlSlide), "Chuck Norris: Slide show update changed data in table 'slides'");
	}

	public function testPageSlideShows_Create() {
		$this->zbxTestLogin('slideconf.php');
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestClickWait('form');

		$this->zbxTestCheckHeader('Slide shows');
		$this->zbxTestTextPresent(['Slide','Sharing']);
		$this->zbxTestTextPresent(['Owner', 'Name', 'Default delay', 'Slides']);
		$this->zbxTestTextPresent(['Screen', 'Delay', 'Action']);
		$this->zbxTestClickWait('cancel');
		$this->zbxTestTextPresent('Slide shows');
	}

	/**
	 * @dataProvider allSlideShows
	 * @backup-once slideshows
	 */
	public function testPageSlideShows_DeleteSelected($slideshow) {
		$slideshowid = $slideshow['slideshowid'];
		$name = $slideshow['name'];

		$this->zbxTestLogin('slideconf.php');
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckboxSelect('shows_'.$slideshowid);
		$this->zbxTestClickButton('slideshow.massdelete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestTextPresent('Slide show deleted');
		$this->zbxTestCheckHeader('Slide shows');

		$sql = "select * from slideshows where slideshowid=$slideshowid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from slides where slideshowid=$slideshowid";
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * @backup-once slideshows
	 */
	public function testPageSlideShows_MassDeleteAll() {
		$this->zbxTestLogin('slideconf.php');
		$this->zbxTestCheckTitle('Configuration of slide shows');
		$this->zbxTestCheckboxSelect('all_shows');
		$this->zbxTestClickButton('slideshow.massdelete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Slide show deleted');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount('SELECT NULL FROM slideshows'));
		$this->assertEquals(0, DBcount('SELECT NULL FROM slides'));
	}
}
