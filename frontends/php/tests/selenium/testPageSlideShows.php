<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/class.ctest.php');


class testPageSlideShows extends CTest
{
	// Returns all slide shows
	public static function allSlideShows()
	{
		DBConnect($error);

		$slideshows=array();

		$result=DBselect("select * from slideshows order by slideshowid");
		while($slideshow=DBfetch($result))
		{
			$slideshows[]=array($slideshow);
		}
		DBclose();

		return $slideshows;
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_SimpleTest($slideshow)
	{
		$this->login('slideconf.php');
		$this->assertTitle('Configuration of slideshows');

		$this->ok('CONFIGURATION OF SLIDE SHOWS');
		$this->ok('SLIDE SHOWS');
		$this->ok('Displaying');
		$this->nok('Displaying 0');
		// Header
		$this->ok(array('Name','Delay','Count of slides'));
		// Data
		$this->ok(array($slideshow['name']));
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_SimpleUpdate($slideshow)
	{
		$name=$slideshow['name'];
		$slideshowid=$slideshow['slideshowid'];

		$sql1="select * from slideshows where name='$name' order by slideshowid";
		$oldHashSlideShow=$this->DBhash($sql1);
		$sql2="select * from slides where slideshowid=$slideshowid order by slideid";
		$oldHashSlide=$this->DBhash($sql2);

		$this->login('slideconf.php');
		$this->assertTitle('Configuration of slideshows');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Configuration of slideshows');
		$this->ok('Slideshow updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF SLIDE SHOWS');

		$this->assertEquals($oldHashSlideShow,$this->DBhash($sql1),"Chuck Norris: Slide show update changed data in table 'slideshows'");
		$this->assertEquals($oldHashSlide,$this->DBhash($sql2),"Chuck Norris: Slide show update changed data in table 'slides'");
	}

	public function testPageSlideShows_Create()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allSlideShows
	*/
	public function testPageSlideShows_MassDelete($slideshow)
	{
		$slideshowid=$slideshow['slideshowid'];
		$name=$slideshow['name'];

		$this->chooseOkOnNextConfirmation();

		$this->DBsave_tables(array('slideshows','slides'));

		$this->login('slideconf.php');
		$this->assertTitle('Configuration of slideshows');
		$this->checkbox_select("shows[$slideshowid]");
		$this->dropdown_select('go','Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->assertTitle('Configuration of slideshows');
		$this->ok('Slideshow deleted');
		$this->ok('CONFIGURATION OF SLIDE SHOWS');

		$sql="select * from slideshows where slideshowid=$slideshowid";
		$this->assertEquals(0,$this->DBcount($sql));
		$sql="select * from slides where slideshowid=$slideshowid";
		$this->assertEquals(0,$this->DBcount($sql));

		$this->DBrestore_tables(array('slides','slideshows'));
	}
}
?>
