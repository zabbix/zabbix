<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testFormSysmap extends CWebTest{
	public $mapName = "Test Map";
	public $mapName2 = "Test Map 2";

	public function testFormSysmapOpen(){
		$this->login('sysmaps.php');
		$this->assertTitle('Network maps');
	}

	public function testFormSysmapCreate(){
		$this->login('sysmaps.php');
		$this->button_click('form');
		$this->wait();
		$this->input_type('name',$this->mapName);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Network map added');
		$this->ok($this->mapName);
	}

	public function testFormSysmapCreateLongMapName(){
		$mapName="0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789";
		$this->login('sysmaps.php');
		$this->button_click('form');
		$this->wait();
		$this->input_type('name',$mapName);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('ERROR');
	}

	public function testFormSysmapSimpleUpdate(){
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Network map updated');
		$this->ok($this->mapName);
	}

	public function testFormSysmapUpdateMapName(){
// Update Map
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName.'"]/../../td/a[text()="Edit"]');
		$this->wait();

		$this->input_type('name', $this->mapName2);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Network map updated');
	}

	public function testFormSysmapDelete(){
		$this->chooseOkOnNextConfirmation();
// Delete Map
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName2.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('delete');
		$this->waitForConfirmation();
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Network map deleted');
	}

	public function testFormSysmapCloneMap(){
// Update Map
		$this->login('sysmaps.php');
		$this->click('//a[text()="Local network"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('clone');
		$this->input_type('name',$this->mapName2);
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Network maps');
		$this->ok('Network map added');
	}

	public function testFormSysmapDeleteClonedMap(){
		$this->chooseOkOnNextConfirmation();

// Delete Map
		$this->login('sysmaps.php');
		$this->click('//a[text()="'.$this->mapName2.'"]/../../td/a[text()="Edit"]');
		$this->wait();
		$this->button_click('delete');
		$this->wait();
		$this->getConfirmation();
		$this->assertTitle('Network maps');
		$this->ok('Network map deleted');
	}

}
?>
